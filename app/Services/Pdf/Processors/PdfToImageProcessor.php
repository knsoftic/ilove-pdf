<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Helpers\PageRange;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\PdfRasterizer;
use App\Services\Pdf\ProcessResult;

/**
 * PDF pages -> JPG / PNG / WEBP images (ZIP when there are several).
 */
class PdfToImageProcessor extends AbstractProcessor
{
    public function __construct(private readonly PdfRasterizer $rasterizer) {}

    public static function requirements(array $params = []): array
    {
        return [['rasterizer']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pageCount = $this->pdf($file);
        $pages = PageRange::pages($context->option('pages'), $pageCount);
        $format = in_array($context->option('format'), ['jpg', 'png', 'webp'], true) ? $context->option('format') : 'jpg';
        $dpi = in_array((int) $context->option('dpi'), [72, 150, 300], true) ? (int) $context->option('dpi') : 150;
        $quality = max(30, min(100, (int) $context->option('quality', 85)));

        $images = $this->rasterizer->render(
            $file->path,
            $context->workDir('pages'),
            $pages,
            $format === 'jpg' ? 'jpg' : 'png',
            $dpi,
            $quality,
            fn ($done, $total) => $context->progress((int) (85 * $done / $total), "Rendering page {$done} of {$total}…")
        );

        $files = [];
        $digits = strlen((string) $pageCount);

        foreach ($images as $page => $path) {
            if ($format === 'webp') {
                $path = $this->toWebp($path, $quality);
            }

            $files[$this->name($file, 'page-'.str_pad((string) $page, $digits, '0', STR_PAD_LEFT), $format)] = $path;
        }

        $context->progress(95, 'Packing images…');

        return $context->resultFromFiles($files, $this->name($file, $format.'-images', 'zip'), ['pages' => count($files)]);
    }

    private function toWebp(string $png, int $quality): string
    {
        if (! function_exists('imagewebp')) {
            throw ToolException::make('engine_unavailable', 'WEBP output is not supported by this server.');
        }

        $image = imagecreatefrompng($png);
        $target = preg_replace('/\.png$/', '.webp', $png);
        imagewebp($image, $target, $quality);
        imagedestroy($image);
        @unlink($png);

        return $target;
    }
}
