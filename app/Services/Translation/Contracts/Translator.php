<?php

namespace App\Services\Translation\Contracts;

/**
 * A translation engine. Implement this interface (and register it in
 * TranslationManager::driver()) to connect any other open-source or local
 * engine – e.g. a self-hosted NLLB / Marian / Opus-MT server – without
 * touching the rest of the application.
 */
interface Translator
{
    public function name(): string;

    public function available(): bool;

    /**
     * Translates a list of strings (order preserved).
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translate(array $texts, string $source, string $target): array;

    /**
     * Detects the language of a text, or null when unsupported.
     */
    public function detect(string $text): ?string;
}
