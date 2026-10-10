<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\ImagePdfWriter;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * JPG / PNG / WEBP -> PDF, in the order the images were uploaded.
 */
class ImagesToPdfProcessor extends AbstractProcessor
{
    private const SIZES = [
        'a4' => [595.28, 841.89],
        'letter' => [612.0, 792.0],
    ];

    public static function requirements(array $params = []): array
    {
        return [['gd']];
    }

    public static function queued(): bool
    {
        return false;
    }

    public function handle(JobContext $context): ProcessResult
    {
        $inputs = $context->inputs();
        $margin = match ($context->option('margin', 'small')) {
            'none' => 0.0,
            'large' => 50.0,
            default => 20.0,
        };
        $merge = (bool) $context->option('merge', true);
        $total = count($inputs);

        if ($merge) {
            $writer = new ImagePdfWriter($context->workPath('images.pdf'));

            foreach ($inputs as $i => $input) {
                $writer->addImagePage($input->path, $this->pageSize($context, $input->path), $context->option('page_size') === 'fit' ? 0.0 : $margin);
                $context->progress((int) (90 * ($i + 1) / $total), 'Adding image '.($i + 1)." of {$total}…");
            }

            $name = $total === 1 ? $this->name($inputs[0], '', 'pdf') : $this->name($inputs[0], 'and-more', 'pdf');

            return $context->result($writer->close(), $name, ['pages' => $total]);
        }

        $files = [];

        foreach ($inputs as $i => $input) {
            $writer = new ImagePdfWriter($context->workPath('image-'.($i + 1).'.pdf'));
            $writer->addImagePage($input->path, $this->pageSize($context, $input->path), $context->option('page_size') === 'fit' ? 0.0 : $margin);
            $files[$this->name($input, '', 'pdf')] = $writer->close();
            $context->progress((int) (90 * ($i + 1) / $total), 'Converting image '.($i + 1)." of {$total}…");
        }

        return $context->resultFromFiles($files, 'images-to-pdf.zip', ['files' => count($files)]);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function pageSize(JobContext $context, string $image): ?array
    {
        $size = self::SIZES[$context->option('page_size', 'a4')] ?? null;

        if ($size === null) {
            return null;
        }

        $orientation = $context->option('orientation', 'auto');

        if ($orientation === 'auto') {
            [$w, $h] = getimagesize($image) ?: [1, 1];
            $exif = function_exists('exif_read_data') ? (@exif_read_data($image)['Orientation'] ?? 1) : 1;

            if ($exif >= 5) {
                [$w, $h] = [$h, $w];
            }

            $orientation = $w > $h ? 'landscape' : 'portrait';
        }

        return $orientation === 'landscape' ? [$size[1], $size[0]] : $size;
    }
}
