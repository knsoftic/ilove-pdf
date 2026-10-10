<?php

namespace App\Services\Pdf;

use App\Helpers\FileName;

/**
 * An uploaded file stored under a random name in the job's input folder.
 */
final class InputFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly int $size,
    ) {}

    public function baseName(string $fallback = 'document'): string
    {
        return FileName::base($this->originalName, $fallback);
    }

    public function contents(): string
    {
        return (string) file_get_contents($this->path);
    }
}
