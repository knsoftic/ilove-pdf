<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use App\Services\Ocr\OcrManager;

/**
 * Reads scanned (image-only) PDFs with OCR and returns the same structure as
 * PdfTextExtractor::positioned(), so tables, headings and paragraphs can be
 * rebuilt exactly like for PDFs with a text layer.
 */
class ScannedPdfReader
{
    public function __construct(
        private readonly OcrManager $ocr,
        private readonly PdfRasterizer $rasterizer,
    ) {}

    public function available(): bool
    {
        return $this->ocr->available() && $this->rasterizer->available();
    }

    /**
     * @return list<array{width: float, height: float, items: list<array>}>
     */
    public function positioned(string $pdf, int $pageCount, JobContext $context, ?string $language, int $fromPercent = 10, int $toPercent = 70): array
    {
        $max = (int) config('pdftoolkit.ocr.max_auto_pages', 20);

        if ($pageCount > $max) {
            throw ToolException::make('nothing_found', "This PDF looks scanned. Automatic text recognition works for up to {$max} pages – run it through “OCR PDF” first.");
        }

        $language = $this->ocr->language($language);
        $dpi = (int) config('pdftoolkit.ocr.dpi', 300);
        $span = max(1, $toPercent - $fromPercent);

        $context->progress($fromPercent, 'No text layer found – recognising text (OCR)…');
        $images = $this->rasterizer->render($pdf, $context->workDir('ocr-pages'), range(1, $pageCount), 'png', $dpi);

        $pages = [];
        $done = 0;

        foreach ($images as $number => $image) {
            $context->progress($fromPercent + (int) ($span * $done / count($images)), "Recognising text on page {$number} of {$pageCount}…");
            $size = @getimagesize($image) ?: [0, 0];
            $pages[] = self::pageFromWords($this->ocr->engine()->words($image, $language), (int) $size[0], (int) $size[1], $dpi);
            @unlink($image);
            $done++;
        }

        return $pages;
    }

    /**
     * Converts OCR word boxes (pixels, top-left origin) into positioned text
     * items (points, bottom-left origin like PDF). All words of a line share
     * one baseline and size, so the layout analysis keeps them together.
     *
     * @param  list<array{line: string, left: int, top: int, width: int, height: int, text: string}>  $words
     * @return array{width: float, height: float, items: list<array>}
     */
    public static function pageFromWords(array $words, int $imageWidth, int $imageHeight, int $dpi): array
    {
        $scale = $dpi / 72;
        $lines = [];

        foreach ($words as $word) {
            $lines[$word['line']][] = $word;
        }

        $items = [];

        foreach ($lines as $lineWords) {
            $bottoms = array_map(fn ($word) => $word['top'] + $word['height'], $lineWords);
            $heights = array_map(fn ($word) => $word['height'], $lineWords);
            sort($bottoms);
            sort($heights);

            $baseline = $bottoms[intdiv(count($bottoms), 2)];
            // A high percentile approximates the font size (ascender to descender).
            $size = max(4, $heights[(int) floor((count($heights) - 1) * 0.8)] / $scale);

            foreach ($lineWords as $word) {
                $items[] = [
                    'x' => round($word['left'] / $scale, 2),
                    'y' => round(($imageHeight - $baseline) / $scale, 2),
                    'width' => round($word['width'] / $scale, 2),
                    'size' => round($size, 2),
                    'text' => $word['text'],
                    'bold' => false,
                    'italic' => false,
                ];
            }
        }

        return ['width' => $imageWidth / $scale, 'height' => $imageHeight / $scale, 'items' => $items];
    }
}
