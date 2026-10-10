<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Documents\SpreadsheetBuilder;
use App\Services\Pdf\Engines\PopplerEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\LayoutAnalyzer;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Pdf\ProcessResult;

/**
 * PDF -> XLSX / CSV. Detects columns from the text layout of each page.
 */
class PdfToSpreadsheetProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly PdfTextExtractor $extractor,
        private readonly LayoutAnalyzer $layout,
        private readonly SpreadsheetBuilder $spreadsheet,
        private readonly PopplerEngine $poppler,
    ) {}

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pageCount = $this->pdf($file);
        $format = $context->param('format', 'xlsx');

        $context->progress(10, 'Detecting tables…');
        $pages = $this->rowsPerPage($file->path, $context);

        if (collect($pages)->flatten()->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
            throw ToolException::make('nothing_found', 'No text or tables were found in this PDF. Scanned documents need OCR first.');
        }

        $context->progress(75, 'Writing spreadsheet…');
        $numbers = (bool) $context->option('numbers', $format === 'xlsx');

        if ($format === 'csv') {
            $rows = [];

            foreach ($pages as $i => $pageRows) {
                if ($i > 0 && $rows !== []) {
                    $rows[] = [];
                }
                array_push($rows, ...$pageRows);
            }

            $delimiter = match ($context->option('delimiter')) {
                'semicolon' => ';',
                'tab' => "\t",
                default => ',',
            };

            $output = $this->spreadsheet->csv($rows, $context->workPath('table.csv'), $delimiter, $numbers);

            return $context->result($output, $this->name($file, '', 'csv'), ['pages' => $pageCount, 'rows' => count($rows)]);
        }

        if ($context->option('sheets') === 'single') {
            $all = [];

            foreach ($pages as $pageRows) {
                array_push($all, ...$pageRows, ...[[]]);
            }

            $sheets = ['Document' => $all];
        } else {
            $sheets = [];

            foreach ($pages as $i => $pageRows) {
                $sheets['Page '.($i + 1)] = $pageRows ?: [['']];
            }
        }

        $output = $this->spreadsheet->xlsx($sheets, $context->workPath('table.xlsx'), $numbers);

        return $context->result($output, $this->name($file, '', 'xlsx'), ['pages' => $pageCount, 'sheets' => count($sheets)]);
    }

    /**
     * @return list<list<list<string>>>
     */
    private function rowsPerPage(string $pdf, JobContext $context): array
    {
        $candidates = [];

        // Positioned fragments from the PHP parser.
        try {
            $candidates[] = array_map(
                fn (array $page) => $this->layout->rows($this->layout->lines($page['items'])),
                $this->extractor->positioned($pdf)
            );
        } catch (ToolException $e) {
            if ($e->errorCode === 'password_protected') {
                throw $e;
            }
        }

        // pdftotext -layout keeps columns aligned with spaces.
        if ($this->poppler->has('pdftotext')) {
            try {
                $candidates[] = array_map(
                    fn (string $text) => $this->layout->rowsFromLayoutText($text),
                    $this->poppler->text($pdf, $context->workPath('layout.txt'), true)
                );
            } catch (ToolException) {
                // keep the other candidate
            }
        }

        if ($candidates === []) {
            throw ToolException::make('corrupted_pdf');
        }

        // Use whichever method recognised more table rows.
        usort($candidates, fn ($a, $b) => $this->score($b) <=> $this->score($a));

        return $candidates[0];
    }

    private function score(array $pages): int
    {
        return array_sum(array_map(fn ($rows) => $this->layout->tableScore($rows), $pages));
    }
}
