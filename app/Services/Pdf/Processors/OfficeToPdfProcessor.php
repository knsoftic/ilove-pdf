<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Documents\PdfBuilder;
use App\Services\Pdf\Engines\LibreOfficeEngine;
use App\Services\Pdf\InputFile;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIO;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtml;
use PhpOffice\PhpWord\IOFactory as WordIO;
use Throwable;

/**
 * Word / Excel / PowerPoint -> PDF.
 *
 * LibreOffice gives the best fidelity and is used whenever it is installed.
 * Without it, Word and Excel files are rendered through PHPWord /
 * PhpSpreadsheet -> HTML -> mPDF, which handles text, tables and images well
 * but not every advanced layout feature.
 */
class OfficeToPdfProcessor extends AbstractProcessor
{
    public function __construct(
        private readonly LibreOfficeEngine $libreOffice,
        private readonly PdfBuilder $pdf,
    ) {}

    public static function requirements(array $params = []): array
    {
        return ($params['kind'] ?? null) === 'powerpoint' ? [['libreoffice']] : [];
    }

    public function handle(JobContext $context): ProcessResult
    {
        $files = [];
        $inputs = $context->inputs();
        $total = count($inputs);

        foreach ($inputs as $i => $input) {
            $context->progress((int) (90 * $i / $total), 'Converting '.($i + 1)." of {$total}…");
            $files[$this->name($input, '', 'pdf')] = $this->convert($input, $context, $i);
        }

        return $context->resultFromFiles($files, 'converted-pdfs.zip', ['files' => count($files)]);
    }

    private function convert(InputFile $input, JobContext $context, int $index): string
    {
        if ($this->libreOffice->available()) {
            return $this->libreOffice->convert($input->path, $context->workDir('lo-'.$index), 'pdf');
        }

        $output = $context->workPath("document-{$index}.pdf");

        try {
            return match ($context->param('kind')) {
                'word' => $this->fromWord($input, $output),
                'excel' => $this->fromSpreadsheet($input, $output),
                default => throw ToolException::make('engine_unavailable'),
            };
        } catch (ToolException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw ToolException::make('conversion_failed', 'This document could not be converted. It may be damaged, password protected or use features that need LibreOffice on the server.', $e);
        }
    }

    private function fromWord(InputFile $input, string $output): string
    {
        $reader = match ($input->extension) {
            'docx' => 'Word2007',
            'odt' => 'ODText',
            'rtf' => 'RTF',
            'doc' => 'MsDoc',
            default => throw ToolException::make('unsupported_format'),
        };

        $document = WordIO::load($input->path, $reader);
        $html = WordIO::createWriter($document, 'HTML')->getContent();

        return $this->pdf->fromHtml($html, $output, ['title' => $input->baseName()]);
    }

    private function fromSpreadsheet(InputFile $input, string $output): string
    {
        $book = SpreadsheetIO::load($input->path);
        $writer = new SpreadsheetHtml($book);
        $writer->writeAllSheets();
        $writer->setEmbedImages(true);

        $html = $writer->generateHtmlAll();
        $columns = 0;

        foreach ($book->getAllSheets() as $sheet) {
            $columns = max($columns, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        }

        return $this->pdf->fromHtml($html, $output, [
            'title' => $input->baseName(),
            'orientation' => $columns > 7 ? 'landscape' : 'portrait',
        ]);
    }
}
