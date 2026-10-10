<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use App\Helpers\UnicodeText;
use App\Services\Pdf\Engines\PopplerEngine;
use Throwable;

/**
 * Extracts text from PDFs with pdftotext (Poppler/Xpdf) when installed and
 * with the pure-PHP smalot/pdfparser otherwise.
 */
class PdfTextExtractor
{
    public function __construct(
        private readonly PopplerEngine $poppler,
        private readonly PdfInspector $inspector,
    ) {}

    /**
     * @return list<string> Text of each page (index 0 = page 1).
     */
    public function pages(string $pdf, string $workDir, bool $layout = false): array
    {
        if ($this->poppler->has('pdftotext')) {
            try {
                return $this->normalisePages($this->poppler->text($pdf, $workDir.DIRECTORY_SEPARATOR.'text.txt', $layout));
            } catch (ToolException $e) {
                if (in_array($e->errorCode, ['password_protected', 'wrong_password'], true)) {
                    throw ToolException::make('password_protected');
                }
                // fall back to the PHP parser
            }
        }

        $pages = [];

        try {
            foreach ($this->inspector->document($pdf)->getPages() as $page) {
                $pages[] = str_replace(["\t", "\r\n", "\r"], [' ', "\n", "\n"], $page->getText());
            }
        } catch (ToolException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ToolException::make('corrupted_pdf', null, $e);
        }

        return $this->normalisePages($pages);
    }

    /**
     * Text fragments with position and font size for layout analysis.
     *
     * @return list<array{width: float, height: float, items: list<array{x: float, y: float, size: float, text: string, bold: bool, italic: bool}>}>
     */
    public function positioned(string $pdf): array
    {
        $result = [];

        try {
            foreach ($this->inspector->document($pdf)->getPages() as $page) {
                $box = $page->getDetails()['MediaBox'] ?? [0, 0, 612, 792];
                $fonts = [];
                $items = [];

                foreach ($page->getDataTm() as $row) {
                    $text = (string) ($row[1] ?? '');

                    if (trim($text) === '') {
                        continue;
                    }

                    $tm = array_map('floatval', (array) $row[0]);
                    $fontId = isset($row[2]) ? (string) $row[2] : '';
                    $fontSize = isset($row[3]) ? (float) $row[3] : 1.0;

                    if (! array_key_exists($fontId, $fonts)) {
                        $fonts[$fontId] = $this->fontName($page, $fontId);
                    }

                    $scale = sqrt(($tm[2] ?? 0) ** 2 + ($tm[3] ?? 1) ** 2) ?: 1;
                    $size = abs($fontSize * $scale);

                    if ($size < 1 || $size > 400) {
                        $size = max(1, min(400, abs($fontSize) ?: 10));
                    }

                    $text = $this->cleanText($text);

                    // The PHP parser returns right-to-left text in visual order.
                    if (UnicodeText::isRtl($text)) {
                        $text = UnicodeText::visualToLogical($text);
                    }

                    $items[] = [
                        'x' => $tm[4] ?? 0.0,
                        'y' => $tm[5] ?? 0.0,
                        'size' => round($size, 2),
                        'text' => $text,
                        'bold' => (bool) preg_match('/bold|black|heavy|semibold/i', $fonts[$fontId]),
                        'italic' => (bool) preg_match('/italic|oblique/i', $fonts[$fontId]),
                    ];
                }

                $result[] = [
                    'width' => abs((float) ($box[2] ?? 612) - (float) ($box[0] ?? 0)),
                    'height' => abs((float) ($box[3] ?? 792) - (float) ($box[1] ?? 0)),
                    'items' => $items,
                ];
            }
        } catch (ToolException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ToolException::make('corrupted_pdf', null, $e);
        }

        return $result;
    }

    private function fontName($page, string $fontId): string
    {
        if ($fontId === '') {
            return '';
        }

        try {
            $font = $page->getFont(ltrim($fontId, '/')) ?? $page->getFont($fontId);

            return $font ? (string) ($font->getDetails()['BaseFont'] ?? $font->getName()) : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function cleanText(string $text): string
    {
        $text = UnicodeText::normalize(str_replace(["\r", "\t", "\u{00A0}"], ['', ' ', ' '], $text));

        // Drop control characters that some PDFs map glyphs to.
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
    }

    /**
     * @param  list<string>  $pages
     * @return list<string>
     */
    private function normalisePages(array $pages): array
    {
        return array_map(function (string $text) {
            $text = $this->cleanText(str_replace(["\r\n", "\r"], "\n", $text));
            $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

            return rtrim($text);
        }, $pages);
    }
}
