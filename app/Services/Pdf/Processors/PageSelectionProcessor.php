<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Helpers\PageRange;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Extract, delete, rotate or duplicate selected pages (qpdf).
 */
class PageSelectionProcessor extends AbstractProcessor
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
        $count = $this->pdf($file);
        $selection = PageRange::pages($context->option('pages'), $count);
        $action = $context->param('action');
        $rotations = [];

        switch ($action) {
            case 'extract':
                $order = $selection;
                $suffix = 'extracted';
                break;

            case 'delete':
                $order = array_values(array_diff(range(1, $count), $selection));
                $suffix = 'edited';

                if ($order === []) {
                    throw ToolException::make('invalid_page_range', 'You cannot delete every page of the document.');
                }
                break;

            case 'rotate':
                $order = range(1, $count);
                $angle = (int) $context->option('angle', 90);
                $selected = trim((string) $context->option('pages')) === '' ? $order : $selection;

                foreach ($selected as $page) {
                    $rotations[$page] = $angle;
                }
                $suffix = 'rotated';
                break;

            case 'duplicate':
                $copies = max(1, min(50, (int) $context->option('copies', 1)));
                $order = [];

                foreach (range(1, $count) as $page) {
                    $order[] = $page;

                    if ($context->option('placement', 'after') === 'after' && in_array($page, $selection, true)) {
                        array_push($order, ...array_fill(0, $copies, $page));
                    }
                }

                if ($context->option('placement') === 'end') {
                    foreach (range(1, $copies) as $ignored) {
                        array_push($order, ...$selection);
                    }
                }
                $suffix = 'duplicated';
                break;

            default:
                throw ToolException::make('invalid_options');
        }

        $context->progress(40, 'Building document…');
        $output = $context->workPath('result.pdf');
        $this->qpdf->assemble($this->runs($file->path, $order), $output, $rotations);

        return $context->result($output, $this->name($file, $suffix, 'pdf'), ['pages' => count($order)]);
    }

    /**
     * qpdf takes page lists per file; consecutive references to the same file
     * are fine, so every page order can be expressed as one part.
     */
    private function runs(string $pdf, array $order): array
    {
        return [[$pdf, $order]];
    }
}
