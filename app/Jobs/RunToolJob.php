<?php

namespace App\Jobs;

use App\Exceptions\ToolException;
use App\Services\Pdf\JobManager;
use App\Services\Pdf\ProcessingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs a heavy processing job on a queue worker. Only the job id is queued;
 * files and (encrypted) options stay in the job's temporary folder.
 */
class RunToolJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $jobId)
    {
        $this->timeout = (int) config('pdftoolkit.limits.process_timeout', 300) + 60;
    }

    public function handle(JobManager $jobs): void
    {
        $job = $jobs->find($this->jobId);

        // Expired or deleted by the visitor in the meantime.
        if (! $job || $job->status() !== ProcessingJob::QUEUED) {
            return;
        }

        $jobs->process($job);
    }

    public function failed(?Throwable $e): void
    {
        $jobs = app(JobManager::class);
        $job = $jobs->find($this->jobId);

        if ($job && ! $job->isFinished()) {
            $error = ToolException::make('server_error');
            $job->data['status'] = ProcessingJob::FAILED;
            $job->data['error'] = $error->toArray();
            $job->data['message'] = $error->getMessage();
            unset($job->data['payload']);
            $jobs->save($job);
        }
    }
}
