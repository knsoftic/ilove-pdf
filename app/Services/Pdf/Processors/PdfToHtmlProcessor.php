<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Helpers\UnicodeText;
use App\Services\Pdf\Engines\PopplerEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\LayoutAnalyzer;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Pdf\ProcessResult;

/**
 * PDF -> HTML. Uses pdftohtml (Poppler) for the layout mode when installed,
 * otherwise renders positioned text (layout) or clean paragraphs (text).
 */
class PdfToHtmlProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly PdfTextExtractor $extractor,
        private readonly LayoutAnalyzer $layout,
        private readonly PopplerEngine $poppler,
    ) {}

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pageCount = $this->pdf($file);
        $mode = $context->option('mode', 'layout');
        $name = $this->name($file, '', 'html');

        if ($mode === 'layout' && $this->poppler->has('pdftohtml')) {
            $context->progress(20, 'Converting with pdftohtml…');
            $output = $context->workPath('document.html');
            $this->poppler->html($file->path, $output);

            if (is_file($output)) {
                return $context->result($output, $name, ['pages' => $pageCount]);
            }
        }

        $context->progress(20, 'Reading text and layout…');
        $pages = $this->extractor->positioned($file->path);
        $title = e($file->baseName());
        $body = '';

        foreach ($pages as $i => $page) {
            $body .= $mode === 'text' ? $this->textPage($page, $i + 1) : $this->layoutPage($page, $i + 1);
        }

        if (trim(strip_tags($body)) === '') {
            throw ToolException::make('nothing_found', 'No text was found in this PDF. Scanned documents need OCR first.');
        }

        $css = $mode === 'text'
            ? 'body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem;line-height:1.6;color:#1f2937}section{margin-bottom:3rem}hr{border:0;border-top:1px solid #e5e7eb;margin:2rem 0}table{border-collapse:collapse;margin:1rem 0;width:100%}td,th{border:1px solid #d1d5db;padding:.35rem .6rem;text-align:left}th{background:#f3f4f6}'
            : 'body{background:#f3f4f6;margin:0;padding:24px;font-family:Helvetica,Arial,sans-serif}.page{position:relative;background:#fff;margin:0 auto 24px;box-shadow:0 1px 4px rgba(0,0,0,.15);overflow:hidden}.page span{position:absolute;white-space:pre;line-height:1;color:#111}';

        $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{$title}</title>\n<style>{$css}</style>\n</head>\n<body>\n{$body}</body>\n</html>\n";

        $output = $context->workPath('document.html');
        file_put_contents($output, $html);

        return $context->result($output, $name, ['pages' => $pageCount]);
    }

    private function layoutPage(array $page, int $number): string
    {
        $html = sprintf('<div class="page" id="page-%d" style="width:%spt;height:%spt">', $number, round($page['width'], 2), round($page['height'], 2));

        foreach ($page['items'] as $item) {
            $top = $page['height'] - $item['y'] - $item['size'] * 0.85;
            $style = sprintf('left:%spt;top:%spt;font-size:%spt', round($item['x'], 2), round($top, 2), round($item['size'], 2));

            if ($item['bold']) {
                $style .= ';font-weight:bold';
            }

            if ($item['italic']) {
                $style .= ';font-style:italic';
            }

            $html .= '<span style="'.$style.'">'.e($item['text']).'</span>';
        }

        return $html."</div>\n";
    }

    private function textPage(array $page, int $number): string
    {
        $html = "<section id=\"page-{$number}\">\n";

        foreach ($this->layout->blocks($this->layout->lines($page['items'])) as $block) {
            if ($block['type'] === 'table') {
                $html .= "<table>\n";

                foreach ($block['rows'] as $r => $row) {
                    $tag = $r === 0 ? 'th' : 'td';
                    $html .= '<tr>'.implode('', array_map(fn ($cell) => "<{$tag}>".e($cell)."</{$tag}>", $row))."</tr>\n";
                }

                $html .= "</table>\n";

                continue;
            }

            $text = e($block['text']);
            $dir = UnicodeText::isRtl($block['text']) ? ' dir="rtl"' : '';
            $html .= $block['type'] === 'heading'
                ? sprintf("<h%d%s>%s</h%d>\n", $block['level'], $dir, $text, $block['level'])
                : "<p{$dir}>{$text}</p>\n";
        }

        return $html."</section>\n<hr>\n";
    }
}
