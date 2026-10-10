<?php

namespace App\Services\Ocr;

use App\Exceptions\ToolException;
use App\Services\Ocr\Contracts\OcrEngine;
use App\Services\System\Capabilities;
use App\Services\System\CommandRunner;

/**
 * Tesseract OCR (https://github.com/tesseract-ocr/tesseract, Apache-2.0)
 * through its command line interface.
 */
class TesseractEngine implements OcrEngine
{
    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly CommandRunner $runner,
    ) {}

    public function available(): bool
    {
        return $this->capabilities->has('tesseract');
    }

    public function languages(): array
    {
        return $this->capabilities->tesseractLanguages();
    }

    public function text(string $image, string $language): string
    {
        $result = $this->runner->mustRun(
            [$this->bin(), $image, 'stdout', '-l', $language, '--psm', '3'],
            'ocr_failed',
            180,
            null,
            ['OMP_THREAD_LIMIT' => '1']
        );

        return trim($result->output());
    }

    public function words(string $image, string $language): array
    {
        $result = $this->runner->mustRun(
            [$this->bin(), $image, 'stdout', '-l', $language, '--psm', '3', 'tsv'],
            'ocr_failed',
            240,
            null,
            ['OMP_THREAD_LIMIT' => '1']
        );

        return self::parseTsv($result->output());
    }

    /**
     * Word boxes from Tesseract's TSV output (level 5 rows).
     *
     * @return list<array{line: string, left: int, top: int, width: int, height: int, conf: float, text: string}>
     */
    public static function parseTsv(string $tsv): array
    {
        $words = [];

        foreach (preg_split('/\R/', $tsv) as $row) {
            $fields = explode("\t", $row);

            // level page block paragraph line word left top width height conf text
            if (count($fields) < 12 || $fields[0] !== '5') {
                continue;
            }

            $text = trim($fields[11]);

            if ($text === '' || (float) $fields[10] < 0) {
                continue;
            }

            $words[] = [
                'line' => $fields[1].'-'.$fields[2].'-'.$fields[3].'-'.$fields[4],
                'left' => (int) $fields[6],
                'top' => (int) $fields[7],
                'width' => (int) $fields[8],
                'height' => (int) $fields[9],
                'conf' => (float) $fields[10],
                'text' => $text,
            ];
        }

        return $words;
    }

    public function pdf(string $image, string $language, string $outputBase): string
    {
        $this->runner->mustRun(
            [$this->bin(), $image, $outputBase, '-l', $language, '--psm', '3', '-c', 'textonly_pdf=0', 'pdf'],
            'ocr_failed',
            240,
            null,
            ['OMP_THREAD_LIMIT' => '1']
        );

        $pdf = $outputBase.'.pdf';

        if (! is_file($pdf)) {
            throw ToolException::make('ocr_failed');
        }

        return $pdf;
    }

    private function bin(): string
    {
        return $this->capabilities->path('tesseract') ?? throw ToolException::make('engine_unavailable');
    }
}
