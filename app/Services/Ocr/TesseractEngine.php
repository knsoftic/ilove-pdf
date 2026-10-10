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
