<?php

namespace App\Services\Ocr;

use App\Exceptions\ToolException;
use App\Services\Ocr\Contracts\OcrEngine;

/**
 * Entry point for server-side OCR. Validates requested languages against the
 * language data installed for the engine.
 */
class OcrManager
{
    public function __construct(private readonly TesseractEngine $tesseract) {}

    public function engine(): OcrEngine
    {
        return $this->tesseract;
    }

    public function available(): bool
    {
        return $this->engine()->available();
    }

    /**
     * Turns "eng" or "eng+urd" into a language string the engine can use.
     */
    public function language(?string $requested): string
    {
        $installed = $this->engine()->languages();
        $wanted = array_filter(explode('+', strtolower((string) $requested)), fn ($code) => preg_match('/^[a-z_]{3,10}$/', $code));
        $wanted = $wanted ?: ['eng'];

        if ($installed === []) {
            return implode('+', $wanted);
        }

        $missing = array_diff($wanted, $installed);

        if ($missing !== []) {
            $names = config('pdftoolkit.ocr.languages', []);
            $label = implode(', ', array_map(fn ($code) => $names[$code] ?? $code, $missing));

            throw ToolException::make('ocr_failed', "OCR language data for {$label} is not installed on this server.");
        }

        return implode('+', $wanted);
    }
}
