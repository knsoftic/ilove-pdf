<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Pdf\Engines\PopplerEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Saves every embedded image in its native format (pdfimages -all).
 */
class ExtractImagesProcessor extends AbstractProcessor
{
    private const KEEP = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'jp2', 'webp'];

    public function __construct(private readonly PopplerEngine $poppler) {}

    public static function requirements(array $params = []): array
    {
        return [['pdfimages']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $this->pdf($file);
        $minSize = max(0, (int) $context->option('min_size', 32));

        $context->progress(20, 'Extracting images…');
        $dir = $context->workDir('images');
        $this->poppler->images($file->path, $dir);

        $files = [];
        $found = glob($dir.DIRECTORY_SEPARATOR.'img-*') ?: [];
        natsort($found);

        foreach ($found as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($extension, self::KEEP, true)) {
                continue;
            }

            $size = @getimagesize($path);

            if ($size && ($size[0] < $minSize || $size[1] < $minSize)) {
                continue;
            }

            // img-003-012.png => page 3, image 12
            preg_match('/img-(\d+)-(\d+)/', basename($path), $m);
            $label = isset($m[1]) ? sprintf('page-%s-image-%s', ltrim($m[1], '0') ?: '0', ltrim($m[2], '0') ?: '0') : basename($path);
            $files[$this->name($file, $label, $extension)] = $path;
        }

        if ($files === []) {
            throw ToolException::make('nothing_found', 'No images were found in this PDF.');
        }

        return $context->resultFromFiles($files, $this->name($file, 'images', 'zip'), ['images' => count($files)]);
    }
}
