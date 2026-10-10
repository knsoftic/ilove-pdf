<?php

namespace App\Services\Pdf\Processors;

use App\Helpers\FileName;
use App\Services\Pdf\InputFile;
use App\Services\Pdf\PdfInspector;

abstract class AbstractProcessor implements Processor
{
    public static function requirements(array $params = []): array
    {
        return [];
    }

    public static function queued(): bool
    {
        return true;
    }

    protected function inspector(): PdfInspector
    {
        return app(PdfInspector::class);
    }

    /**
     * Validates the PDF and returns its page count.
     */
    protected function pdf(InputFile $file, bool $allowEncrypted = false): int
    {
        return $this->inspector()->assertProcessable($file->path, $allowEncrypted);
    }

    protected function name(InputFile $file, string $suffix, string $extension): string
    {
        return FileName::make($file->originalName, $suffix, $extension);
    }

    /**
     * Size statistics shown on the result screen.
     */
    protected function sizeStats(int $original, int $new): array
    {
        return [
            'original_size' => $original,
            'new_size' => $new,
            'saved_percent' => $original > 0 ? max(0, round((1 - $new / $original) * 100, 1)) : 0,
        ];
    }
}
