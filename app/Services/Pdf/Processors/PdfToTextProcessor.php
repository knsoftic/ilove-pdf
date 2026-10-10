<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\JobContext;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Pdf\ProcessResult;

/**
 * PDF -> UTF-8 text. Pages are separated by a form feed (\f) so the browser
 * editor can show page markers.
 */
class PdfToTextProcessor extends AbstractProcessor
{
    public function __construct(private readonly PdfTextExtractor $extractor) {}

    public static function queued(): bool
    {
        return false;
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pageCount = $this->pdf($file);

        $context->progress(20, 'Extracting text…');
        $pages = $this->extractor->pages($file->path, $context->workDir(), (bool) $context->option('layout', false));

        $emptyPages = [];

        foreach ($pages as $i => $text) {
            if (mb_strlen(trim($text)) < 3) {
                $emptyPages[] = $i + 1;
            }
        }

        $text = implode("\n\f", $pages);
        $output = $context->workPath('text.txt');
        file_put_contents($output, $text);

        return $context->result($output, $this->name($file, '', 'txt'), [
            'pages' => $pageCount,
            'characters' => mb_strlen($text),
            'words' => count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)),
            'empty_pages' => implode(',', $emptyPages),
        ]);
    }
}
