<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Merges PDFs in the order they were uploaded (qpdf).
 */
class MergeProcessor extends AbstractProcessor
{
    public function __construct(private readonly QpdfEngine $qpdf) {}

    public static function requirements(array $params = []): array
    {
        return [['qpdf']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $inputs = $context->inputs();

        if (count($inputs) < 2) {
            throw ToolException::make('too_few_files', 'Please add at least two PDF files to merge.');
        }

        $parts = [];
        $pages = 0;

        foreach ($inputs as $i => $input) {
            $count = $this->pdf($input);
            $pages += $count;
            $parts[] = [$input->path, range(1, $count)];
            $context->progress((int) (40 * ($i + 1) / count($inputs)), 'Checking file '.($i + 1).' of '.count($inputs).'…');
        }

        if ($pages > (int) config('pdftoolkit.limits.max_pages', 1000)) {
            throw ToolException::make('too_many_pages');
        }

        $context->progress(60, 'Merging…');
        $output = $context->workPath('merged.pdf');
        $this->qpdf->assemble($parts, $output);

        return $context->result($output, 'merged.pdf', ['files' => count($inputs), 'pages' => $pages]);
    }
}
