<?php

namespace App\Services\Translation;

use App\Exceptions\ToolException;
use App\Services\System\CommandRunner;
use App\Services\Translation\Contracts\Translator;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Argos Translate (https://github.com/argosopentech/argos-translate, MIT),
 * fully offline neural translation via its command line tool:
 *
 *   pip install argostranslate
 *   argospm install translate-en_ur   # one package per language pair
 */
class ArgosTranslator implements Translator
{
    public function __construct(private readonly CommandRunner $runner) {}

    public function name(): string
    {
        return 'Argos Translate';
    }

    private function bin(): string
    {
        return (string) config('pdftoolkit.translation.argos.binary', 'argos-translate');
    }

    public function available(): bool
    {
        return Cache::remember('pdftoolkit.argos.up', 600, function () {
            try {
                return $this->runner->run([$this->bin(), '--help'], 30)->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    public function translate(array $texts, string $source, string $target): array
    {
        if ($source === 'auto') {
            throw ToolException::make('translation_failed', 'Please choose the source language.');
        }

        $results = [];

        foreach ($texts as $text) {
            $result = $this->runner->run(
                [$this->bin(), '--from-lang', $source, '--to-lang', $target],
                (int) config('pdftoolkit.translation.timeout', 120),
                null,
                ['PYTHONIOENCODING' => 'utf-8'],
                $text,
            );

            if (! $result->successful()) {
                throw ToolException::make('translation_failed', 'This language pair is not installed for Argos Translate.');
            }

            $results[] = rtrim($result->output(), "\r\n");
        }

        return $results;
    }

    public function detect(string $text): ?string
    {
        return null;
    }
}
