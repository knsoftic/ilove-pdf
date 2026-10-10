<?php

namespace App\Services\Translation;

use App\Exceptions\ToolException;
use App\Services\Translation\Contracts\Translator;

/**
 * Chooses the configured translation engine and translates text while
 * keeping its line structure intact.
 */
class TranslationManager
{
    private ?Translator $driver = null;

    public function __construct(private readonly LanguageDetector $detector) {}

    public function driver(): Translator
    {
        return $this->driver ??= match (config('pdftoolkit.translation.driver')) {
            'libretranslate' => app(LibreTranslateTranslator::class),
            'argos' => app(ArgosTranslator::class),
            default => app(NullTranslator::class),
        };
    }

    public function available(): bool
    {
        return $this->driver()->available();
    }

    /**
     * @return array<string, array{name: string, native: string, rtl: bool}>
     */
    public function languages(): array
    {
        return collect(config('pdftoolkit.translation.languages', []))
            ->map(fn ($l) => ['name' => $l[0], 'native' => $l[1], 'rtl' => (bool) $l[2]])
            ->all();
    }

    public function isSupported(string $language): bool
    {
        return array_key_exists($language, $this->languages());
    }

    /**
     * @return array{language: ?string, confidence: float, engine: string}
     */
    public function detect(string $text): array
    {
        if ($this->available() && ($language = $this->driver()->detect($text)) && $this->isSupported($language)) {
            return ['language' => $language, 'confidence' => 0.9, 'engine' => $this->driver()->name()];
        }

        $result = $this->detector->detect($text);

        if ($result['language'] && ! $this->isSupported($result['language'])) {
            $result['language'] = null;
        }

        return $result + ['engine' => 'built-in'];
    }

    /**
     * Translates line by line so paragraphs, lists and page breaks survive.
     */
    public function translate(string $text, string $source, string $target): string
    {
        if (! $this->available()) {
            throw ToolException::make('translation_unavailable');
        }

        if ($source === $target) {
            return $text;
        }

        $lines = preg_split("/(\r\n|\n|\f)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $toTranslate = [];

        foreach ($lines as $i => $line) {
            if ($i % 2 === 0 && trim($line) !== '' && preg_match('/\p{L}/u', $line)) {
                $toTranslate[$i] = $line;
            }
        }

        foreach (array_chunk($toTranslate, 40, true) as $batch) {
            $translated = $this->driver()->translate(array_values($batch), $source, $target);

            foreach (array_keys($batch) as $j => $index) {
                $lines[$index] = $translated[$j] ?? $lines[$index];
            }
        }

        return implode('', $lines);
    }
}
