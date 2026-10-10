<?php

namespace App\Services\Pdf\Engines;

use App\Exceptions\ToolException;
use App\Services\System\Capabilities;
use App\Services\System\CommandRunner;

/**
 * Wrapper around the Poppler / Xpdf command line utilities
 * (pdftotext, pdftoppm, pdfimages, pdftohtml – GPL).
 */
class PopplerEngine
{
    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly CommandRunner $runner,
    ) {}

    public function has(string $tool): bool
    {
        return $this->capabilities->has($tool);
    }

    private function bin(string $tool): string
    {
        return $this->capabilities->path($tool) ?? throw ToolException::make('engine_unavailable');
    }

    /**
     * @return list<string> Text per page.
     */
    public function text(string $pdf, string $output, bool $layout = false, ?int $first = null, ?int $last = null): array
    {
        $args = [$this->bin('pdftotext'), '-enc', 'UTF-8'];

        if ($layout) {
            $args[] = '-layout';
        }

        if ($first) {
            array_push($args, '-f', (string) $first);
        }

        if ($last) {
            array_push($args, '-l', (string) $last);
        }

        $this->runner->mustRun([...$args, $pdf, $output], 'conversion_failed');

        $text = (string) @file_get_contents($output);
        $pages = explode("\f", $text);

        // pdftotext ends the last page with a form feed as well.
        if (count($pages) > 1 && trim(end($pages)) === '') {
            array_pop($pages);
        }

        return $pages;
    }

    public function rasterize(string $pdf, string $dir, string $format, int $dpi, int $first, int $last, int $quality = 85): void
    {
        $args = [$this->bin('pdftoppm'), $format === 'jpg' ? '-jpeg' : '-png', '-r', (string) $dpi, '-f', (string) $first, '-l', (string) $last];

        if ($format === 'jpg') {
            array_push($args, '-jpegopt', "quality={$quality}");
        }

        $this->runner->mustRun([...$args, $pdf, $dir.DIRECTORY_SEPARATOR.'page'], 'conversion_failed');
    }

    public function images(string $pdf, string $dir): void
    {
        $this->runner->mustRun([$this->bin('pdfimages'), '-all', '-p', $pdf, $dir.DIRECTORY_SEPARATOR.'img'], 'conversion_failed');
    }

    public function html(string $pdf, string $output): void
    {
        $this->runner->mustRun([
            $this->bin('pdftohtml'), '-s', '-i', '-noframes', '-q', '-enc', 'UTF-8', $pdf, $output,
        ], 'conversion_failed');
    }
}
