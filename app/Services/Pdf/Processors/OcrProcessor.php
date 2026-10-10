<?php

namespace App\Services\Pdf\Processors;

use App\Helpers\PageRange;
use App\Services\Ocr\OcrManager;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\PdfRasterizer;
use App\Services\Pdf\ProcessResult;

/**
 * OCR for scanned PDFs: renders pages at 300 DPI and runs Tesseract to
 * produce a searchable PDF or plain text.
 */
class OcrProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly OcrManager $ocr,
        private readonly PdfRasterizer $rasterizer,
        private readonly QpdfEngine $qpdf,
    ) {}

    public static function requirements(array $params = []): array
    {
        return [['ocr', 'qpdf']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $count = $this->pdf($file);
        $pages = PageRange::pages($context->option('pages'), $count);
        $language = $this->ocr->language($context->option('language', 'eng'));
        $engine = $this->ocr->engine();
        $total = count($pages);

        $images = $this->rasterizer->render(
            $file->path,
            $context->workDir('pages'),
            $pages,
            'jpg',
            (int) config('pdftoolkit.ocr.dpi', 300),
            80,
            fn ($done) => $context->progress((int) (20 * $done / $total), "Preparing page {$done} of {$total}…")
        );

        if ($context->option('output') === 'text') {
            $texts = [];
            $i = 0;

            foreach ($images as $page => $image) {
                $context->progress(20 + (int) (75 * $i++ / $total), "Recognising page {$page}…");
                $texts[] = $engine->text($image, $language);
            }

            $output = $context->workPath('ocr.txt');
            file_put_contents($output, implode("\n\f", $texts));

            return $context->result($output, $this->name($file, 'ocr', 'txt'), ['pages' => $total, 'characters' => mb_strlen(implode('', $texts))]);
        }

        $parts = [];
        $i = 0;

        foreach ($images as $page => $image) {
            $context->progress(20 + (int) (70 * $i++ / $total), "Recognising page {$page}…");
            $parts[] = [$engine->pdf($image, $language, $context->workDir('ocr').DIRECTORY_SEPARATOR.'page-'.$page), [1]];
        }

        $context->progress(92, 'Assembling searchable PDF…');
        $output = $context->workPath('searchable.pdf');
        $this->qpdf->assemble($parts, $output);

        return $context->result($output, $this->name($file, 'ocr', 'pdf'), ['pages' => $total]);
    }
}
