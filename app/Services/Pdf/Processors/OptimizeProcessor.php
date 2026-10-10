<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;
use Illuminate\Support\Facades\File;

/**
 * Lossless optimisation: object streams, recompressed streams, unused
 * resources removed, optional linearisation ("fast web view").
 */
class OptimizeProcessor extends AbstractProcessor
{
    public function __construct(private readonly QpdfEngine $qpdf) {}

    public static function requirements(array $params = []): array
    {
        return [['qpdf']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $this->pdf($file);
        $original = filesize($file->path);

        $context->progress(30, 'Optimizing…');
        $output = $context->workPath('optimized.pdf');
        $this->qpdf->optimize($file->path, $output, (bool) $context->option('linearize'), (bool) $context->option('remove_metadata', true));

        clearstatcache(true, $output);

        // Linearised output is wanted even when it is slightly larger.
        if (! $context->option('linearize') && filesize($output) >= $original) {
            File::copy($file->path, $output);
        }

        return $context->result($output, $this->name($file, 'optimized', 'pdf'), $this->sizeStats($original, filesize($output)));
    }
}
