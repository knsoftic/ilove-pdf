<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\Engines\LibreOfficeEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\PdfRasterizer;
use App\Services\Pdf\ProcessResult;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;

/**
 * PDF -> PPTX: every page becomes a full-slide picture (PHPPresentation),
 * or an editable Impress import when only LibreOffice is available.
 */
class PdfToPowerpointProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly PdfRasterizer $rasterizer,
        private readonly LibreOfficeEngine $libreOffice,
    ) {}

    public static function requirements(array $params = []): array
    {
        return [['rasterizer'], ['libreoffice']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pageCount = $this->pdf($file);
        $name = $this->name($file, '', 'pptx');

        if (! $this->rasterizer->available()) {
            $context->progress(15, 'Converting with LibreOffice…');
            $output = $this->libreOffice->convert($file->path, $context->workDir('lo'), 'pptx', 'impress_pdf_import');

            return $context->result($output, $name, ['slides' => $pageCount]);
        }

        $dpi = $context->option('quality') === 'high' ? 200 : 120;
        $images = $this->rasterizer->render(
            $file->path,
            $context->workDir('pages'),
            range(1, $pageCount),
            'jpg',
            $dpi,
            85,
            fn ($done, $total) => $context->progress(5 + (int) (70 * $done / $total), "Rendering page {$done} of {$total}…")
        );

        $context->progress(80, 'Building presentation…');
        $presentation = new PhpPresentation;
        $presentation->getDocumentProperties()->setCreator(setting('site_name', 'PDF Toolkit'));

        [$firstW, $firstH] = getimagesize(reset($images));
        // Slides are sized like the first page (96 px per inch).
        $slideW = (int) round($firstW / $dpi * 96);
        $slideH = (int) round($firstH / $dpi * 96);
        $presentation->getLayout()->setCX($slideW, DocumentLayout::UNIT_PIXEL)->setCY($slideH, DocumentLayout::UNIT_PIXEL);

        $first = true;

        foreach ($images as $image) {
            $slide = $first ? $presentation->getActiveSlide() : $presentation->createSlide();
            $first = false;

            [$w, $h] = getimagesize($image);
            $scale = min($slideW / $w, $slideH / $h);
            $width = (int) round($w * $scale);
            $height = (int) round($h * $scale);

            $slide->createDrawingShape()
                ->setName('Page')
                ->setPath($image)
                ->setResizeProportional(false)
                ->setWidth($width)
                ->setHeight($height)
                ->setOffsetX((int) (($slideW - $width) / 2))
                ->setOffsetY((int) (($slideH - $height) / 2));
        }

        $output = $context->workPath('presentation.pptx');
        IOFactory::createWriter($presentation, 'PowerPoint2007')->save($output);

        return $context->result($output, $name, ['slides' => count($images)]);
    }
}
