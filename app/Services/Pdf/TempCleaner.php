<?php

namespace App\Services\Pdf;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Deletes expired temporary files:
 *  - every job older than PDF_JOB_TTL minutes
 *  - downloaded results after PDF_DOWNLOADED_TTL minutes
 *  - failed jobs after a few minutes
 *  - orphaned folders and stale mPDF cache files
 */
class TempCleaner
{
    public function __construct(
        private readonly TempStorage $storage,
        private readonly JobManager $jobs,
    ) {}

    /**
     * @return array{deleted: int, kept: int, freed: int}
     */
    public function run(bool $all = false, bool $dryRun = false): array
    {
        $deleted = 0;
        $kept = 0;
        $freed = 0;
        $ttl = (int) config('pdftoolkit.job_ttl', 30);

        foreach ($this->storage->all() as $id) {
            if ($all || $this->expired($id, $ttl)) {
                $freed += $this->storage->size($id);
                $deleted++;

                if (! $dryRun) {
                    $this->storage->delete($id);
                }
            } else {
                $kept++;
            }
        }

        if (! $dryRun) {
            $this->cleanMpdfCache();
        }

        return ['deleted' => $deleted, 'kept' => $kept, 'freed' => $freed];
    }

    private function expired(string $id, int $ttl): bool
    {
        $job = $this->jobs->find($id);
        $now = Carbon::now();

        if (! $job) {
            $modified = Carbon::createFromTimestamp(filemtime($this->storage->path($id)) ?: 0);

            return $modified->addMinutes($ttl)->isPast();
        }

        if ($job->expiresAt()->isPast()) {
            return true;
        }

        if ($downloaded = $job->downloadedAt()) {
            return $downloaded->addMinutes((int) config('pdftoolkit.downloaded_ttl', 5))->lt($now);
        }

        if ($job->status() === ProcessingJob::FAILED) {
            return Carbon::parse($job->data['finished_at'] ?? $job->data['updated_at'])->addMinutes(5)->lt($now);
        }

        return false;
    }

    private function cleanMpdfCache(): void
    {
        $dir = storage_path('app/mpdf-tmp');

        if (! is_dir($dir)) {
            return;
        }

        foreach (File::allFiles($dir) as $file) {
            if ($file->getMTime() < time() - 3600) {
                @unlink($file->getPathname());
            }
        }
    }
}
