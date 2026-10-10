<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Helpers\FileName;
use App\Helpers\TextEncoding;
use App\Services\Documents\PdfBuilder;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * HTML file or pasted HTML -> PDF (mPDF). External resources are stripped by
 * the HtmlSanitizer, so rendering never makes network or file requests.
 */
class HtmlToPdfProcessor extends AbstractProcessor
{
    public function __construct(private readonly PdfBuilder $pdf) {}

    public static function queued(): bool
    {
        return false;
    }

    public function handle(JobContext $context): ProcessResult
    {
        if ($context->option('source') === 'paste' || ! $context->hasInputs()) {
            $html = (string) $context->option('html', '');
            $name = 'document.pdf';
        } else {
            $input = $context->input();
            $html = TextEncoding::htmlToUtf8($input->contents());
            $name = FileName::make($input->originalName, '', 'pdf');
        }

        if (trim(strip_tags($html, '<img>')) === '') {
            throw ToolException::make('nothing_found', 'There is no HTML content to convert.');
        }

        $context->progress(30, 'Rendering HTML…');
        $output = $this->pdf->fromHtml($html, $context->workPath('document.pdf'), [
            'page_size' => $context->option('page_size', 'a4'),
            'orientation' => $context->option('orientation', 'portrait'),
        ]);

        return $context->result($output, $name);
    }
}
