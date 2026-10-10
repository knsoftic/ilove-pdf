<?php

namespace App\Services\Translation;

use App\Exceptions\ToolException;
use App\Services\Translation\Contracts\Translator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LibreTranslate (https://github.com/LibreTranslate/LibreTranslate, AGPL-3.0),
 * a free and open-source machine translation API you can host yourself:
 *
 *   pip install libretranslate && libretranslate --load-only en,ur,hi,ar,zh,es,...
 *   # or: docker run -p 5000:5000 libretranslate/libretranslate
 */
class LibreTranslateTranslator implements Translator
{
    public function name(): string
    {
        return 'LibreTranslate';
    }

    private function url(string $path): string
    {
        return rtrim((string) config('pdftoolkit.translation.libretranslate.url'), '/').$path;
    }

    private function code(string $language): string
    {
        return config("pdftoolkit.translation.engine_codes.libretranslate.{$language}", $language);
    }

    public function available(): bool
    {
        return Cache::remember('pdftoolkit.libretranslate.up', 300, function () {
            try {
                return Http::timeout(4)->acceptJson()->get($this->url('/languages'))->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    public function translate(array $texts, string $source, string $target): array
    {
        if ($texts === []) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('pdftoolkit.translation.timeout', 120))
                ->acceptJson()
                ->asJson()
                ->post($this->url('/translate'), array_filter([
                    'q' => array_values($texts),
                    'source' => $source === 'auto' ? 'auto' : $this->code($source),
                    'target' => $this->code($target),
                    'format' => 'text',
                    'api_key' => config('pdftoolkit.translation.libretranslate.api_key'),
                ], fn ($v) => $v !== null && $v !== ''));
        } catch (Throwable $e) {
            Log::warning('LibreTranslate request failed', ['error' => $e->getMessage()]);

            throw ToolException::make('translation_failed', null, $e);
        }

        if (! $response->successful()) {
            Log::warning('LibreTranslate returned an error', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            throw ToolException::make('translation_failed', $response->status() === 400
                ? 'This language pair is not installed on the translation server.'
                : null);
        }

        $translated = $response->json('translatedText');

        return array_values(is_array($translated) ? $translated : [$translated]);
    }

    public function detect(string $text): ?string
    {
        try {
            $response = Http::timeout(15)->acceptJson()->asJson()->post($this->url('/detect'), array_filter([
                'q' => mb_substr($text, 0, 2000),
                'api_key' => config('pdftoolkit.translation.libretranslate.api_key'),
            ]));

            $code = $response->json('0.language');

            if (! $code) {
                return null;
            }

            $map = array_flip((array) config('pdftoolkit.translation.engine_codes.libretranslate', []));

            return $map[$code] ?? $code;
        } catch (Throwable) {
            return null;
        }
    }
}
