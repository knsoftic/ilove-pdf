<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\Engines\GhostscriptEngine;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;
use Illuminate\Support\Facades\File;

/**
 * Compresses PDFs with Ghostscript (image downsampling + recompression).
 * When only qpdf is available a lossless structural optimisation is done.
 * The smallest valid result is returned – never a bigger file.
 */
class CompressProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly GhostscriptEngine $ghostscript,
        private readonly QpdfEngine $qpdf,
    ) {}

    public static function requirements(array $params = []): array
    {
        return [['ghostscript'], ['qpdf']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pages = $this->pdf($file);
        $level = in_array($context->option('level'), ['low', 'medium', 'high'], true) ? $context->option('level') : 'medium';
        $original = filesize($file->path);
        $candidates = [];

        if ($this->ghostscript->available()) {
            $context->progress(15, 'Compressing images and fonts…');
            $gsOutput = $context->workPath('compressed-gs.pdf');
            $this->ghostscript->compress($file->path, $gsOutput, $level);
            $candidates[] = $gsOutput;
        }

        if ($this->qpdf->available()) {
            $context->progress(70, 'Optimizing document structure…');
            $source = $candidates[0] ?? $file->path;
            $qpdfOutput = $context->workPath('compressed-qpdf.pdf');

            try {
                $this->qpdf->optimize($source, $qpdfOutput);
                $candidates[] = $qpdfOutput;
            } catch (\Throwable) {
                // keep the Ghostscript result
            }
        }

        $best = $file->path;
        $bestSize = $original;

        foreach ($candidates as $candidate) {
            clearstatcache(true, $candidate);

            if (is_file($candidate) && filesize($candidate) > 0 && filesize($candidate) < $bestSize) {
                $best = $candidate;
                $bestSize = filesize($candidate);
            }
        }

        if ($best === $file->path) {
            File::copy($file->path, $best = $context->workPath('original.pdf'));
        }

        return $context->result($best, $this->name($file, 'compressed', 'pdf'), $this->sizeStats($original, $bestSize) + ['pages' => $pages]);
    }
}
