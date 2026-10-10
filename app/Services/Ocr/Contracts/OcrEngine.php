<?php

namespace App\Services\Ocr\Contracts;

/**
 * An OCR engine that can read text from page images. Implement this
 * interface to plug in another open-source engine (e.g. PaddleOCR, EasyOCR).
 */
interface OcrEngine
{
    public function available(): bool;

    /**
     * @return list<string> Installed language codes (e.g. "eng", "urd").
     */
    public function languages(): array;

    /**
     * Recognises the text of one image.
     */
    public function text(string $image, string $language): string;

    /**
     * Recognised words with their boxes in image pixels; words of one text
     * line share the same "line" key.
     *
     * @return list<array{line: string, left: int, top: int, width: int, height: int, conf: float, text: string}>
     */
    public function words(string $image, string $language): array;

    /**
     * Creates a one-page searchable PDF (image + invisible text layer) and
     * returns its path.
     */
    public function pdf(string $image, string $language, string $outputBase): string;
}
