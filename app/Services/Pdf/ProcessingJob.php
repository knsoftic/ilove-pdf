<?php

namespace App\Services\Pdf;

use Illuminate\Support\Carbon;

/**
 * State of one processing job, persisted as job.json inside the job folder.
 * No database is involved.
 */
final class ProcessingJob
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public function __construct(public array $data) {}

    public function id(): string
    {
        return $this->data['id'];
    }

    public function tool(): string
    {
        return $this->data['tool'];
    }

    public function status(): string
    {
        return $this->data['status'];
    }

    public function isFinished(): bool
    {
        return in_array($this->status(), [self::DONE, self::FAILED], true);
    }

    public function owner(): ?string
    {
        return $this->data['owner'] ?? null;
    }

    /**
     * @return list<array{name:string,original:string,extension:string,size:int}>
     */
    public function inputs(): array
    {
        return $this->data['inputs'] ?? [];
    }

    public function result(): ?array
    {
        return $this->data['result'] ?? null;
    }

    public function createdAt(): Carbon
    {
        return Carbon::parse($this->data['created_at']);
    }

    public function downloadedAt(): ?Carbon
    {
        return isset($this->data['downloaded_at']) ? Carbon::parse($this->data['downloaded_at']) : null;
    }

    public function expiresAt(): Carbon
    {
        return $this->createdAt()->addMinutes((int) config('pdftoolkit.job_ttl', 30));
    }

    /**
     * Public representation for the status endpoint (no paths, no options).
     */
    public function toPublicArray(?string $downloadUrl = null): array
    {
        $result = $this->result();

        return [
            'id' => $this->id(),
            'status' => $this->status(),
            'progress' => (int) ($this->data['progress'] ?? 0),
            'message' => $this->data['message'] ?? null,
            'error' => $this->data['error'] ?? null,
            'result' => $result ? [
                'filename' => $result['filename'],
                'size' => $result['size'],
                'mime' => $result['mime'],
                'stats' => $result['stats'] ?? [],
                'download_url' => $downloadUrl,
            ] : null,
            'expires_at' => $this->expiresAt()->toIso8601String(),
        ];
    }
}
