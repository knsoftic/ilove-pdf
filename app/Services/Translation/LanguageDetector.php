<?php

namespace App\Services\Translation;

/**
 * Lightweight, offline language identification for the supported languages:
 * Unicode script analysis first, then stop-word statistics for Latin scripts.
 */
class LanguageDetector
{
    private const STOPWORDS = [
        'en' => 'the and of to in is that for it with as was on are be this by not or have from at which you an were their has will can but all been',
        'es' => 'de la que el en y los del se las por un para con no una su al es lo como más pero sus le ya o este sí porque esta entre cuando muy sin sobre también',
        'fr' => 'de la le et les des en un du une que est pour qui dans par pas au sur ne se ce il avec plus sont son mais nous vous elle ou aux cette été',
        'de' => 'der die und in den von zu das mit sich des auf für ist im dem nicht ein eine als auch es an werden aus er hat dass sie nach wird bei einer um',
        'it' => 'di e il la che in a per un del è non una sono le con si da gli i della al lo come anche più nel ma alla delle questo ha dei sua',
        'pt' => 'de a o que e do da em um para é com não uma os no se na por mais as dos como mas foi ao ele das tem à seu sua ou ser quando muito',
        'tr' => 've bir bu da de için ile olarak çok daha ne gibi olan en ama kadar sonra değil her var ki ya o şey ben sen biz olduğu ancak veya',
        'id' => 'yang dan di ini itu dengan untuk tidak dari dalam akan pada juga ke karena bisa ada saya mereka tersebut oleh sudah adalah dapat atau seperti saja',
        'ms' => 'yang dan di ini itu dengan untuk tidak dari dalam akan pada juga ke kerana boleh ada saya mereka tersebut oleh telah ialah adalah daripada kepada sahaja',
    ];

    private const DISTINCTIVE = [
        'id' => ['karena', 'bisa', 'sudah', 'saja', 'tetapi', 'belum', 'sekarang', 'dapat'],
        'ms' => ['kerana', 'boleh', 'ialah', 'daripada', 'sahaja', 'tetapi', 'sekarang', 'kepada', 'iaitu'],
    ];

    /**
     * @return array{language: ?string, confidence: float}
     */
    public function detect(string $text): array
    {
        $sample = mb_substr(preg_replace('/\s+/u', ' ', $text) ?? '', 0, 6000);

        if (trim($sample) === '') {
            return ['language' => null, 'confidence' => 0.0];
        }

        $scripts = [
            'arabic' => preg_match_all('/\p{Arabic}/u', $sample),
            'devanagari' => preg_match_all('/\p{Devanagari}/u', $sample),
            'bengali' => preg_match_all('/\p{Bengali}/u', $sample),
            'han' => preg_match_all('/\p{Han}/u', $sample),
            'kana' => preg_match_all('/[\p{Hiragana}\p{Katakana}]/u', $sample),
            'hangul' => preg_match_all('/\p{Hangul}/u', $sample),
            'cyrillic' => preg_match_all('/\p{Cyrillic}/u', $sample),
            'latin' => preg_match_all('/\p{Latin}/u', $sample),
        ];

        $letters = max(1, array_sum($scripts));
        arsort($scripts);
        $script = array_key_first($scripts);
        $share = $scripts[$script] / $letters;

        $language = match ($script) {
            'arabic' => $this->arabicScriptLanguage($sample),
            'devanagari' => 'hi',
            'bengali' => 'bn',
            'han' => $scripts['kana'] > $scripts['han'] * 0.2 ? 'ja' : 'zh',
            'kana' => 'ja',
            'hangul' => 'ko',
            'cyrillic' => 'ru',
            default => null,
        };

        if ($script === 'latin') {
            [$language, $confidence] = $this->latinLanguage($sample);

            return ['language' => $language, 'confidence' => round($confidence * $share, 2)];
        }

        return ['language' => $language, 'confidence' => round(min(1, $share + 0.1), 2)];
    }

    private function arabicScriptLanguage(string $sample): string
    {
        $urdu = preg_match_all('/[ٹڈڑںےۓھ]/u', $sample);
        $persian = preg_match_all('/[پچژگکی]/u', $sample);
        $arabic = preg_match_all('/[يكةى]/u', $sample);

        if ($urdu > 2) {
            return 'ur';
        }

        return $persian > $arabic ? 'fa' : 'ar';
    }

    /**
     * @return array{0: ?string, 1: float}
     */
    private function latinLanguage(string $sample): array
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($sample), -1, PREG_SPLIT_NO_EMPTY);
        $total = max(1, count($words));
        $counts = array_count_values($words);
        $scores = [];

        foreach (self::STOPWORDS as $language => $list) {
            $score = 0;

            foreach (explode(' ', $list) as $word) {
                $score += $counts[$word] ?? 0;
            }

            $scores[$language] = $score / $total;
        }

        // Diacritics are a strong hint.
        $hints = [
            'de' => '/[äöüß]/u', 'tr' => '/[ğışİ]/u', 'es' => '/[ñ¿¡]/u',
            'pt' => '/[ãõ]/u', 'fr' => '/[èêëœæ]/u', 'it' => '/\b(è|perché)\b/u',
        ];

        foreach ($hints as $language => $pattern) {
            if (preg_match_all($pattern, $sample) > 2) {
                $scores[$language] *= 1.35;
            }
        }

        foreach (self::DISTINCTIVE as $language => $list) {
            foreach ($list as $word) {
                $scores[$language] += ($counts[$word] ?? 0) * 0.5 / $total;
            }
        }

        arsort($scores);
        $best = array_key_first($scores);

        if ($scores[$best] < 0.02) {
            return [null, 0.0];
        }

        return [$best, min(1.0, $scores[$best] * 3)];
    }
}
