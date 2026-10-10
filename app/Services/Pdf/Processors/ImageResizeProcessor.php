<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\ImageService;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Resizes images by percentage or to pixel dimensions with GD.
 */
class ImageResizeProcessor extends AbstractProcessor
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
        $files = [];
        $inputs = $context->inputs();
        $last = null;

        foreach ($inputs as $i => $input) {
            $image = $this->images->load($input->path);
            [$w, $h] = [imagesx($image), imagesy($image)];
            [$newW, $newH] = $this->targetSize($context, $w, $h);

            $image = $this->images->resize($image, $newW, $newH);
            $format = $context->option('format', 'keep') === 'keep' ? $this->images->formatOf($input->path) : $context->option('format');
            $output = $context->workPath("image-{$i}.{$format}");
            $this->images->save($image, $output, $format, 90);
            imagedestroy($image);

            $files[$this->name($input, "{$newW}x{$newH}", $format)] = $output;
            $last = "{$newW} × {$newH} px";

            $context->progress((int) (95 * ($i + 1) / count($inputs)), 'Resizing image '.($i + 1).' of '.count($inputs).'…');
        }

        return $context->resultFromFiles($files, 'resized-images.zip', count($files) === 1 ? ['dimensions' => $last] : []);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function targetSize(JobContext $context, int $w, int $h): array
    {
        if ($context->option('mode', 'percent') === 'percent') {
            $scale = max(1, min(400, (int) $context->option('percent', 50))) / 100;

            return [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        }

        $width = (int) $context->option('width', 0);
        $height = (int) $context->option('height', 0);
        $keep = (bool) $context->option('keep_ratio', true);

        if ($width > 0 && $height > 0 && ! $keep) {
            return [$width, $height];
        }

        if ($width > 0 && $height > 0) {
            $scale = min($width / $w, $height / $h);

            return [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        }

        if ($width > 0) {
            return [$width, max(1, (int) round($h * $width / $w))];
        }

        if ($height > 0) {
            return [max(1, (int) round($w * $height / $h)), $height];
        }

        return [$w, $h];
    }
}
