<?php

namespace App\Services\Translation;

use App\Exceptions\ToolException;
use App\Services\Translation\Contracts\Translator;

/**
 * Used when no server-side engine is configured. The browser then offers its
 * built-in on-device translator where available.
 */
class NullTranslator implements Translator
{
    public function name(): string
    {
        return 'None';
    }

    public function available(): bool
    {
        return false;
    }

    public function translate(array $texts, string $source, string $target): array
    {
        throw ToolException::make('translation_unavailable');
    }

    public function detect(string $text): ?string
    {
        return null;
    }
}
