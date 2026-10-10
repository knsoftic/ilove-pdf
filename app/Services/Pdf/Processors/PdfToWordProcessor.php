<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Documents\DocxBuilder;
use App\Services\Pdf\Engines\LibreOfficeEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\LayoutAnalyzer;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Pdf\ProcessResult;
use App\Services\Pdf\ScannedPdfReader;
use Throwable;

/**
 * PDF -> DOCX.
 *
 * "flow" (default, pure PHP): rebuilds headings and paragraphs from the
 * positioned text so the document is easy to edit.
 * "layout" (LibreOffice): keeps the visual layout using text frames.
 */
class PdfToWordProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly PdfTextExtractor $extractor,
        private readonly LayoutAnalyzer $layout,
        private readonly DocxBuilder $docx,
        private readonly LibreOfficeEngine $libreOffice,
        private readonly ScannedPdfReader $scanned,
    ) {}

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pageCount = $this->pdf($file);
        $name = $this->name($file, '', 'docx');

        if ($context->option('mode') === 'layout' && $this->libreOffice->available()) {
            $context->progress(15, 'Converting with LibreOffice…');
            $output = $this->libreOffice->convert($file->path, $context->workDir('lo'), 'docx:MS Word 2007 XML', 'writer_pdf_import');

            return $context->result($output, $name, ['pages' => $pageCount]);
        }

        $context->progress(10, 'Reading text and layout…');
        $pages = [];
        $pageSize = null;

        try {
            foreach ($this->extractor->positioned($file->path) as $i => $page) {
                $pageSize ??= ['width' => $page['width'], 'height' => $page['height']];
                $pages[] = $this->layout->blocks($this->layout->lines($page['items']));
                $context->progress(10 + (int) (60 * ($i + 1) / max(1, $pageCount)), 'Rebuilding page '.($i + 1).' of '.$pageCount.'…');
            }
        } catch (ToolException $e) {
            if ($e->errorCode === 'password_protected') {
                throw $e;
            }
        } catch (Throwable) {
            $pages = [];
        }

        // Fallback: plain text per page (e.g. when the PHP parser struggled).
        if ($this->isEmpty($pages)) {
            $pages = array_map(fn (string $text) => array_values(array_filter(array_map(
                fn ($paragraph) => ['type' => 'paragraph', 'text' => preg_replace('/\s*\n\s*/', ' ', trim($paragraph))],
                preg_split("/\n\s*\n/", $text)
            ), fn ($block) => $block['text'] !== '')), $this->extractor->pages($file->path, $context->workDir()));
        }

        // No text layer at all: a scanned document – recognise it with OCR.
        if ($this->isEmpty($pages) && $this->scanned->available()) {
            $pages = [];

            foreach ($this->scanned->positioned($file->path, $pageCount, $context, $context->option('ocr_language')) as $page) {
                $pageSize ??= ['width' => $page['width'], 'height' => $page['height']];
                $pages[] = $this->layout->blocks($this->layout->lines($page['items']));
            }
        }

        if ($this->isEmpty($pages)) {
            throw ToolException::make('nothing_found', $this->scanned->available()
                ? 'No text was found in this PDF, not even with text recognition (OCR).'
                : 'No text was found in this PDF. It may be a scanned document – run it through "OCR PDF" first.');
        }

        $context->progress(80, 'Writing Word document…');
        $output = $this->docx->fromBlocks($pages, $context->workPath('document.docx'), $file->baseName(), $pageSize);

        return $context->result($output, $name, ['pages' => $pageCount]);
    }

    private function isEmpty(array $pages): bool
    {
        foreach ($pages as $blocks) {
            if ($blocks !== []) {
                return false;
            }
        }

        return true;
    }
}
