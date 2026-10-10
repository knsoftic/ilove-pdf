<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\File;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Creates PDFs from text or HTML with mPDF (GPL-2.0).
 *
 * mPDF ships open-source fonts for Latin, Cyrillic, Greek, Arabic, Urdu,
 * Persian, Devanagari, Bengali and CJK scripts and handles right-to-left
 * text, so translated documents render correctly in every supported language.
 */
class PdfBuilder
{
    public const RTL_LANGUAGES = ['ar', 'ur', 'fa', 'he', 'ps', 'sd', 'ug', 'yi'];

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * @param  array{page_size?: string, orientation?: string, font?: string, font_size?: int|float, lang?: ?string, title?: ?string}  $options
     */
    public function fromText(string $text, string $output, array $options = []): string
    {
        $mpdf = $this->make($options);
        $rtl = in_array($options['lang'] ?? null, self::RTL_LANGUAGES, true);
        $size = (float) ($options['font_size'] ?? 11);
        $mono = ($options['font'] ?? 'sans') === 'mono';

        $mpdf->WriteHTML(sprintf(
            'body{font-size:%spt;line-height:1.45;} p{margin:0 0 %spt 0;%s} %s',
            $size,
            round($size * 0.6, 1),
            $mono ? 'white-space:pre-wrap;' : '',
            $rtl ? 'body,p{direction:rtl;text-align:right;}' : ''
        ), HTMLParserMode::HEADER_CSS);

        // Paragraphs are written in batches to stay below PCRE limits on big texts.
        $batch = '';

        foreach (preg_split("/\n{2,}/", str_replace(["\r\n", "\r"], "\n", $text)) as $paragraph) {
            if (str_contains($paragraph, "\f")) {
                foreach (explode("\f", $paragraph) as $i => $part) {
                    if ($i > 0) {
                        $batch .= '<pagebreak />';
                    }
                    $batch .= $this->paragraph($part, $mono);
                }
            } else {
                $batch .= $this->paragraph($paragraph, $mono);
            }

            if (strlen($batch) > 50000) {
                $mpdf->WriteHTML($batch, HTMLParserMode::HTML_BODY);
                $batch = '';
            }
        }

        if ($batch !== '') {
            $mpdf->WriteHTML($batch, HTMLParserMode::HTML_BODY);
        }

        $mpdf->Output($output, Destination::FILE);

        return $output;
    }

    public function fromHtml(string $html, string $output, array $options = []): string
    {
        $mpdf = $this->make($options);
        $clean = $this->sanitizer->sanitize($html);

        if (trim(strip_tags($clean, '<img><hr><table>')) === '') {
            $clean = '<p>&nbsp;</p>';
        }

        $mpdf->WriteHTML($clean);
        $mpdf->Output($output, Destination::FILE);

        return $output;
    }

    public function make(array $options = []): Mpdf
    {
        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $format = match (strtolower((string) ($options['page_size'] ?? 'a4'))) {
            'a3' => 'A3',
            'a5' => 'A5',
            'letter' => 'Letter',
            'legal' => 'Legal',
            default => 'A4',
        };

        $font = match ($options['font'] ?? 'sans') {
            'serif' => 'dejavuserif',
            'mono' => 'dejavusansmono',
            default => 'dejavusans',
        };

        @ini_set('pcre.backtrack_limit', '5000000');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => ($options['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P',
            'tempDir' => $tempDir,
            'default_font' => $font,
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'showImageErrors' => false,
            'curlTimeout' => 1,
            'curlFollowLocation' => false,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->baseScript = 1;
        $mpdf->useSubstitutions = true;
        $mpdf->SetCreator(setting('site_name', 'PDF Toolkit'));

        if (! empty($options['title'])) {
            $mpdf->SetTitle($options['title']);
        }

        if (in_array($options['lang'] ?? null, self::RTL_LANGUAGES, true)) {
            $mpdf->SetDirectionality('rtl');
        }

        return $mpdf;
    }

    private function paragraph(string $text, bool $preformatted): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($preformatted) {
            return '<p>'.$escaped.'</p>';
        }

        return trim($text) === '' ? '' : '<p>'.nl2br($escaped, false).'</p>';
    }
}
