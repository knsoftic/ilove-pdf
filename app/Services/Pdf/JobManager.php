<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use App\Jobs\RunToolJob;
use App\Services\Pdf\Processors\Processor;
use App\Services\Stats\UsageRecorder;
use App\Services\Tools\Tool;
use App\Services\Tools\ToolRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates, runs and tracks processing jobs.
 *
 * A job is a folder in temporary storage with a job.json state file. Options
 * (which may contain passwords) are stored encrypted with the app key and are
 * removed as soon as the job finished.
 */
class JobManager
{
    public function __construct(
        private readonly TempStorage $storage,
        private readonly ToolRegistry $registry,
        private readonly UsageRecorder $usage,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $extensions  Validated (lower case) extension per file.
     */
    public function create(Tool $tool, array $files, array $extensions, array $options, ?string $owner): ProcessingJob
    {
        $id = $this->storage->create();
        $inputs = [];

        foreach (array_values($files) as $i => $file) {
            $extension = $extensions[$i];
            $name = sprintf('input-%02d.%s', $i + 1, $extension);
            $size = (int) $file->getSize();

            $file->move($this->storage->path($id, 'input'), $name);

            $inputs[] = [
                'name' => $name,
                'original' => mb_substr((string) $file->getClientOriginalName(), 0, 200),
                'extension' => $extension,
                'size' => $size,
            ];
        }

        $job = new ProcessingJob([
            'id' => $id,
            'tool' => $tool->slug,
            'status' => ProcessingJob::QUEUED,
            'progress' => 0,
            'message' => 'Waiting in queue…',
            'owner' => $owner,
            'inputs' => $inputs,
            'payload' => Crypt::encryptString(json_encode($options)),
            'result' => null,
            'error' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->save($job);

        return $job;
    }

    /**
     * Runs the job now (sync queue) or hands it to a queue worker.
     */
    public function dispatch(ProcessingJob $job, Tool $tool): ProcessingJob
    {
        $class = $tool->processorClass();
        $connection = config('pdftoolkit.queue.connection') ?: config('queue.default');

        if ($class::queued() && $connection !== 'sync') {
            RunToolJob::dispatch($job->id())
                ->onConnection($connection)
                ->onQueue(config('pdftoolkit.queue.name', 'pdf'));

            return $job;
        }

        return $this->process($job);
    }

    public function process(ProcessingJob $job): ProcessingJob
    {
        $tool = $this->registry->find($job->tool());

        if (! $tool || ! $tool->processorClass()) {
            return $this->fail($job, ToolException::make('engine_unavailable'));
        }

        $timeout = (int) config('pdftoolkit.limits.process_timeout', 300);
        @set_time_limit($timeout + 30);

        if (php_size_to_bytes(ini_get('memory_limit')) < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }

        $job->data['status'] = ProcessingJob::PROCESSING;
        $job->data['progress'] = 1;
        $job->data['message'] = 'Processing…';
        $this->save($job);

        try {
            $options = json_decode(Crypt::decryptString($job->data['payload'] ?? ''), true) ?: [];
            $inputs = array_map(fn (array $input) => new InputFile(
                $this->storage->path($job->id(), 'input/'.$input['name']),
                $input['original'],
                $input['extension'],
                $input['size'],
            ), $job->inputs());

            $context = new JobContext(
                $job->id(),
                $tool,
                $inputs,
                $options + $tool->params(),
                $this->storage,
                fn (int $percent, ?string $message) => $this->progress($job, $percent, $message),
            );

            /** @var Processor $processor */
            $processor = app($tool->processorClass());
            $result = $processor->handle($context);

            $job->data['status'] = ProcessingJob::DONE;
            $job->data['progress'] = 100;
            $job->data['message'] = 'Done';
            $job->data['result'] = [
                'file' => 'output/'.basename($result->path),
                'filename' => $result->filename,
                'mime' => $result->mime,
                'size' => filesize($result->path),
                'stats' => $result->stats,
            ];
            $job->data['finished_at'] = now()->toIso8601String();

            $this->usage->record($tool->slug, 'server');
        } catch (ToolException $e) {
            return $this->fail($job, $e);
        } catch (Throwable $e) {
            Log::error('PDF tool processing failed', [
                'tool' => $job->tool(),
                'job' => $job->id(),
                'exception' => $e,
            ]);

            return $this->fail($job, ToolException::make('server_error', null, $e));
        } finally {
            $this->cleanupWorkFiles($job);
        }

        $this->save($job);

        return $job;
    }

    public function find(string $id): ?ProcessingJob
    {
        if (! TempStorage::isValidId($id)) {
            return null;
        }

        $file = $this->storage->path($id, 'job.json');

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? new ProcessingJob($data) : null;
    }

    public function findOrFail(string $id, ?string $owner = null): ProcessingJob
    {
        $job = $this->find($id);

        if (! $job || $job->expiresAt()->isPast()) {
            throw ToolException::make('job_not_found');
        }

        if (config('pdftoolkit.bind_jobs_to_session') && $job->owner() !== null && ! hash_equals($job->owner(), (string) $owner)) {
            throw ToolException::make('job_not_found');
        }

        return $job;
    }

    public function save(ProcessingJob $job): void
    {
        $job->data['updated_at'] = now()->toIso8601String();

        if (is_dir($this->storage->path($job->id()))) {
            file_put_contents($this->storage->path($job->id(), 'job.json'), json_encode($job->data, JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    public function resultPath(ProcessingJob $job): string
    {
        $result = $job->result() ?? throw ToolException::make('job_not_ready');

        return $this->storage->path($job->id(), $result['file']);
    }

    public function markDownloaded(ProcessingJob $job): void
    {
        $job->data['downloaded_at'] ??= now()->toIso8601String();
        $this->save($job);
    }

    public function delete(string $id): void
    {
        $this->storage->delete($id);
    }

    private function progress(ProcessingJob $job, int $percent, ?string $message): void
    {
        $job->data['progress'] = $percent;
        $job->data['message'] = $message ?? $job->data['message'];
        $this->save($job);
    }

    private function fail(ProcessingJob $job, ToolException $e): ProcessingJob
    {
        $job->data['status'] = ProcessingJob::FAILED;
        $job->data['error'] = $e->toArray();
        $job->data['message'] = $e->getMessage();
        $job->data['finished_at'] = now()->toIso8601String();
        unset($job->data['payload']);

        $this->cleanupWorkFiles($job);
        File::deleteDirectory($this->storage->path($job->id(), 'output'));
        $this->save($job);

        return $job;
    }

    /**
     * Source files and intermediate files are removed as soon as processing
     * ends – only the result is kept until it is downloaded or expires.
     */
    private function cleanupWorkFiles(ProcessingJob $job): void
    {
        unset($job->data['payload']);

        File::deleteDirectory($this->storage->path($job->id(), 'work'));

        if (config('pdftoolkit.delete_inputs_after_processing', true)) {
            File::deleteDirectory($this->storage->path($job->id(), 'input'));
        }
    }
}
