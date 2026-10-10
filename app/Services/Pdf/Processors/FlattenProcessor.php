<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Flattens form fields and annotations into the page content (qpdf).
 */
class FlattenProcessor extends AbstractProcessor
{
    public function __construct(private readonly QpdfEngine $qpdf) {}

    public static function requirements(array $params = []): array
    {
        return [['qpdf']];
    }

    public static function queued(): bool
    {
        return false;
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pages = $this->pdf($file);

        $context->progress(30, 'Flattening…');
        $output = $context->workPath('flattened.pdf');
        $this->qpdf->flatten($file->path, $output, (bool) $context->option('forms', true), (bool) $context->option('annotations', true));

        return $context->result($output, $this->name($file, 'flattened', 'pdf'), ['pages' => $pages]);
    }
}
