<?php

namespace App\Services\Pdf;

/**
 * The output of a processor: one file plus optional statistics shown to the
 * visitor (e.g. original size, new size, number of pages).
 */
final class ProcessResult
{
    /**
     * @param  array<string, scalar|null>  $stats
     */
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly string $mime,
        public readonly array $stats = [],
    ) {}

    public static function mimeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
