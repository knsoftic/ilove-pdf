<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Helpers\FileName;
use App\Helpers\TextEncoding;
use App\Services\Documents\PdfBuilder;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;
use App\Services\Translation\LanguageDetector;

/**
 * Plain text -> PDF with automatic font selection for every script and
 * right-to-left layout for Arabic, Urdu and Persian.
 */
class TextToPdfProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly PdfBuilder $pdf,
        private readonly LanguageDetector $detector,
    ) {}

    public static function queued(): bool
    {
        return false;
    }

    public function handle(JobContext $context): ProcessResult
    {
        if ($context->option('source') === 'paste' || ! $context->hasInputs()) {
            $text = (string) $context->option('text', '');
            $name = 'text.pdf';
        } else {
            $input = $context->input();
            $text = TextEncoding::toUtf8($input->contents());
            $name = FileName::make($input->originalName, '', 'pdf');
        }

        if (trim($text) === '') {
            throw ToolException::make('nothing_found', 'There is no text to convert.');
        }

        $context->progress(30, 'Creating PDF…');
        $output = $this->pdf->fromText($text, $context->workPath('text.pdf'), [
            'font' => $context->option('font', 'sans'),
            'font_size' => (float) $context->option('font_size', 11),
            'page_size' => $context->option('page_size', 'a4'),
            'orientation' => $context->option('orientation', 'portrait'),
            'lang' => $this->detector->detect($text)['language'] ?? null,
        ]);

        return $context->result($output, $name);
    }
}
