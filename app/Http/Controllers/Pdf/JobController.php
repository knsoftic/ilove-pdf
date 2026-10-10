<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\ToolException;
use App\Helpers\FileName;
use App\Http\Controllers\Controller;
use App\Services\Pdf\JobManager;
use App\Services\Pdf\ProcessingJob;
use App\Services\Tools\OptionValidator;
use App\Services\Tools\ToolRegistry;
use App\Services\Tools\UploadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Server-side processing API:
 *
 *   POST   /api/tools/{tool}/jobs   upload files + options, start processing
 *   GET    /api/jobs/{job}          status / progress (signed URL)
 *   DELETE /api/jobs/{job}          delete files immediately (signed URL)
 *   GET    /download/{job}          stream the result (signed URL)
 */
class JobController extends Controller
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly JobManager $jobs,
        private readonly UploadValidator $uploads,
        private readonly OptionValidator $options,
    ) {}

    public function store(Request $request, string $tool): JsonResponse
    {
        $tool = $this->registry->find($tool);

        if (! $tool || ! $tool->isEnabled()) {
            throw ToolException::make('tool_disabled');
        }

        if (! in_array('server', $this->registry->enginesFor($tool), true)) {
            throw ToolException::make('engine_unavailable');
        }

        $rawOptions = json_decode((string) $request->input('options', '{}'), true);
        $options = $this->options->validate($tool, is_array($rawOptions) ? $rawOptions : []);

        $files = array_values(array_filter(
            (array) $request->file('files', []),
            fn ($file) => $file instanceof UploadedFile
        ));

        $optional = $tool->filesOptionalWhen();
        $filesRequired = ! ($optional && (string) ($options[$optional[0]] ?? '') === $optional[1]);

        $extensions = $this->uploads->validate($tool, $filesRequired ? $files : [], $filesRequired);

        $job = $this->jobs->create($tool, $filesRequired ? $files : [], $extensions, $options, $this->owner($request));
        $job = $this->jobs->dispatch($job, $tool);

        return response()->json($this->payload($job));
    }

    public function show(Request $request, string $job): JsonResponse
    {
        $job = $this->jobs->findOrFail($job, $this->owner($request));

        return response()->json($this->payload($job))->header('Cache-Control', 'no-store');
    }

    public function download(Request $request, string $job): BinaryFileResponse
    {
        $job = $this->jobs->findOrFail($job, $this->owner($request));

        if ($job->status() !== ProcessingJob::DONE) {
            throw ToolException::make('job_not_ready');
        }

        $path = $this->jobs->resultPath($job);

        if (! is_file($path)) {
            throw ToolException::make('job_not_found');
        }

        $this->jobs->markDownloaded($job);
        $result = $job->result();
        $inline = $request->boolean('inline') && in_array($result['mime'], ['text/plain', 'application/pdf'], true);

        return response()->file($path, [
            'Content-Type' => $result['mime'],
            'Content-Disposition' => FileName::disposition($result['filename'], $inline ? 'inline' : 'attachment'),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function destroy(Request $request, string $job): JsonResponse
    {
        $record = $this->jobs->find($job);

        if ($record && (! config('pdftoolkit.bind_jobs_to_session') || $record->owner() === null || hash_equals((string) $record->owner(), $this->owner($request)))) {
            $this->jobs->delete($record->id());
        }

        return response()->json(['deleted' => true]);
    }

    private function payload(ProcessingJob $job): array
    {
        $ttl = now()->addMinutes((int) config('pdftoolkit.job_ttl', 30));
        $download = $job->status() === ProcessingJob::DONE
            ? URL::temporarySignedRoute('jobs.download', $ttl, ['job' => $job->id()], absolute: false)
            : null;

        return $job->toPublicArray($download ? url($download) : null) + [
            'status_url' => url(URL::temporarySignedRoute('api.jobs.show', $ttl, ['job' => $job->id()], absolute: false)),
        ];
    }

    /**
     * Random token that ties jobs to the visitor's browser session.
     */
    private function owner(Request $request): string
    {
        $session = $request->session();

        if (! $session->has('pdftk_owner')) {
            $session->put('pdftk_owner', Str::random(40));
        }

        return (string) $session->get('pdftk_owner');
    }
}
