<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\Engines\GhostscriptEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Converts all colours (text, vector graphics and images) to grayscale while
 * keeping text selectable (Ghostscript).
 */
class GrayscaleProcessor extends AbstractProcessor
{
    public function __construct(private readonly GhostscriptEngine $ghostscript) {}

    public static function requirements(array $params = []): array
    {
        return [['ghostscript']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pages = $this->pdf($file);

        $context->progress(20, 'Converting colours to grayscale…');
        $output = $context->workPath('grayscale.pdf');
        $this->ghostscript->grayscale($file->path, $output);

        return $context->result($output, $this->name($file, 'grayscale', 'pdf'), ['pages' => $pages] + $this->sizeStats(filesize($file->path), filesize($output)));
    }
}
