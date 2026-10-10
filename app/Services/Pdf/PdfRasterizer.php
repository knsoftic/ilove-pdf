<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use App\Services\Pdf\Engines\GhostscriptEngine;
use App\Services\Pdf\Engines\PopplerEngine;
use Illuminate\Support\Facades\File;

/**
 * Renders PDF pages to PNG/JPG images with Ghostscript or pdftoppm.
 */
class PdfRasterizer
{
    public function __construct(
        private readonly GhostscriptEngine $ghostscript,
        private readonly PopplerEngine $poppler,
    ) {}

    public function available(): bool
    {
        return $this->ghostscript->available() || $this->poppler->has('pdftoppm');
    }

    /**
     * @param  list<int>  $pages  1-based page numbers.
     * @param  'png'|'jpg'  $format
     * @param  callable(int $done, int $total):void|null  $progress
     * @return array<int, string> page number => image path
     */
    public function render(string $pdf, string $dir, array $pages, string $format, int $dpi, int $quality = 85, ?callable $progress = null): array
    {
        if (! $this->available()) {
            throw ToolException::make('engine_unavailable');
        }

        $result = [];
        $done = 0;

        foreach ($this->runs($pages) as $i => [$first, $last]) {
            $runDir = $dir.DIRECTORY_SEPARATOR.'run-'.$i;
            File::ensureDirectoryExists($runDir);

            // pdftoppm is noticeably faster and lighter than Ghostscript.
            if ($this->poppler->has('pdftoppm')) {
                $this->poppler->rasterize($pdf, $runDir, $format, $dpi, $first, $last, $quality);
            } else {
                $this->ghostscript->rasterize($pdf, $runDir, $format, $dpi, $first, $last, $quality);
            }

            $files = glob($runDir.DIRECTORY_SEPARATOR.'*.{png,jpg}', GLOB_BRACE) ?: [];
            natsort($files);
            $files = array_values($files);

            foreach (range($first, $last) as $offset => $page) {
                if (isset($files[$offset])) {
                    $result[$page] = $files[$offset];
                }
            }

            $done += $last - $first + 1;

            if ($progress) {
                $progress($done, count($pages));
            }
        }

        if ($result === []) {
            throw ToolException::make('conversion_failed');
        }

        return $result;
    }

    /**
     * [1,2,3,7,8] => [[1,3],[7,8]]
     *
     * @param  list<int>  $pages
     * @return list<array{0:int,1:int}>
     */
    private function runs(array $pages): array
    {
        sort($pages);
        $runs = [];

        foreach (array_unique($pages) as $page) {
            $last = array_key_last($runs);

            if ($last !== null && $runs[$last][1] === $page - 1) {
                $runs[$last][1] = $page;
            } else {
                $runs[] = [$page, $page];
            }
        }

        return $runs;
    }
}
