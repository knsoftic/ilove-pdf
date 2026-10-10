<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\ImageService;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;
use Illuminate\Support\Facades\File;

/**
 * Compresses JPG / PNG / WEBP images with GD. An image is never returned
 * bigger than it was uploaded when the format stays the same.
 */
class ImageCompressProcessor extends AbstractProcessor
{
    public function __construct(private readonly ImageService $images) {}

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
        $quality = (int) $context->option('quality', 75);
        $maxDimension = (int) $context->option('max_dimension', 0);
        $files = [];
        $originalTotal = 0;
        $newTotal = 0;
        $inputs = $context->inputs();

        foreach ($inputs as $i => $input) {
            $sourceFormat = $this->images->formatOf($input->path);
            $format = $context->option('format', 'keep') === 'keep' ? $sourceFormat : $context->option('format');
            $image = $this->images->load($input->path);

            if ($maxDimension > 0 && max(imagesx($image), imagesy($image)) > $maxDimension) {
                $scale = $maxDimension / max(imagesx($image), imagesy($image));
                $image = $this->images->resize($image, (int) round(imagesx($image) * $scale), (int) round(imagesy($image) * $scale));
            }

            $output = $context->workPath("image-{$i}.{$format}");
            $this->images->save($image, $output, $format, $quality);
            imagedestroy($image);

            clearstatcache(true, $output);

            if ($format === $sourceFormat && $maxDimension === 0 && filesize($output) >= $input->size) {
                File::copy($input->path, $output);
            }

            $originalTotal += $input->size;
            $newTotal += filesize($output);
            $files[$this->name($input, 'compressed', $format)] = $output;

            $context->progress((int) (95 * ($i + 1) / count($inputs)), 'Compressing image '.($i + 1).' of '.count($inputs).'…');
        }

        return $context->resultFromFiles($files, 'compressed-images.zip', $this->sizeStats($originalTotal, $newTotal));
    }
}
