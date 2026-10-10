<?php

namespace App\Services\Pdf\Processors;

use App\Helpers\PageRange;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Splits a PDF by every page, page ranges, fixed chunk size, or extracts
 * selected pages into one document (qpdf).
 */
class SplitProcessor extends AbstractProcessor
{
    public function __construct(private readonly QpdfEngine $qpdf) {}

    public static function requirements(array $params = []): array
    {
        return [['qpdf']];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $count = $this->pdf($file);
        $mode = $context->option('mode', 'ranges');

        $groups = match ($mode) {
            'every' => array_map(fn ($page) => [$page], range(1, $count)),
            'fixed' => array_chunk(range(1, $count), max(1, (int) $context->option('size', 1))),
            'extract' => [PageRange::pages($context->option('pages'), $count)],
            default => array_map(
                fn ($range) => range($range[0], $range[1]),
                PageRange::ranges($context->option('ranges'), $count)
            ),
        };

        $files = [];
        $digits = strlen((string) $count);

        foreach ($groups as $i => $pages) {
            $label = count($pages) === 1
                ? 'page-'.str_pad((string) $pages[0], $digits, '0', STR_PAD_LEFT)
                : 'pages-'.$pages[0].'-'.end($pages);

            if ($mode === 'extract') {
                $label = 'extracted';
            }

            $output = $context->workPath("part-{$i}.pdf");
            $this->qpdf->assemble([[$file->path, $pages]], $output);
            $files[$this->name($file, $label, 'pdf')] = $output;

            $context->progress((int) (90 * ($i + 1) / count($groups)), 'Creating file '.($i + 1).' of '.count($groups).'…');
        }

        return $context->resultFromFiles($files, $this->name($file, 'split', 'zip'), ['files' => count($files), 'pages' => $count]);
    }
}
