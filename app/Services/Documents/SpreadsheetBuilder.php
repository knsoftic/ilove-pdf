<?php

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds XLSX workbooks (PhpSpreadsheet, MIT) and CSV files from rows.
 */
class SpreadsheetBuilder
{
    /**
     * @param  array<string, list<list<string>>>  $sheets  Sheet title => rows
     */
    public function xlsx(array $sheets, string $output, bool $numbers = true): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $book->getProperties()->setCreator(setting('site_name', 'PDF Toolkit'));

        if ($sheets === []) {
            $sheets = ['Sheet 1' => [['']]];
        }

        foreach ($sheets as $title => $rows) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', (string) $title), 0, 31));
            $maxColumns = 0;

            foreach ($rows as $r => $row) {
                $maxColumns = max($maxColumns, count($row));

                foreach (array_values($row) as $c => $value) {
                    $cell = $sheet->getCell([$c + 1, $r + 1]);
                    $number = $numbers ? $this->toNumber($value) : null;

                    if ($number !== null) {
                        $cell->setValueExplicit($number, DataType::TYPE_NUMERIC);
                    } else {
                        $cell->setValueExplicit($this->safeText($value), DataType::TYPE_STRING);
                    }
                }
            }

            for ($c = 1; $c <= $maxColumns; $c++) {
                $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
            }
        }

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($output);

        return $output;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    public function csv(array $rows, string $output, string $delimiter = ',', bool $numbers = false): string
    {
        $handle = fopen($output, 'wb');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding.

        foreach ($rows as $row) {
            $row = array_map(function ($value) use ($numbers) {
                $number = $numbers ? $this->toNumber($value) : null;

                return $number !== null ? (string) $number : $this->safeText($value);
            }, $row);

            fputcsv($handle, $row, $delimiter, '"', '');
        }

        fclose($handle);

        return $output;
    }

    /**
     * "1,234.50", "(12)", "45%" ... => float, or null when not a number.
     */
    public function toNumber(string $value): ?float
    {
        $v = trim(str_replace(["\u{00A0}", ' '], '', $value));

        if ($v === '' || ! preg_match('/^[(\-+]?[$€£¥₹]?\d[\d,.]*%?\)?$/u', $v)) {
            return null;
        }

        $negative = str_starts_with($v, '(') || str_starts_with($v, '-');
        $percent = str_ends_with(rtrim($v, ')'), '%');
        $v = preg_replace('/[^\d.,]/', '', $v);

        // "1.234,56" (European) vs "1,234.56"
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        if (! is_numeric($v)) {
            return null;
        }

        $number = (float) $v * ($negative ? -1 : 1);

        return $percent ? $number / 100 : $number;
    }

    /**
     * Prevents CSV/formula injection when the file is opened in a spreadsheet.
     */
    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
