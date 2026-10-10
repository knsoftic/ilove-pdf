<?php

namespace App\Helpers;

/**
 * Text clean-up for PDF extraction, especially for right-to-left scripts
 * (Arabic, Urdu, Persian), which PDFs usually store as presentation-form
 * glyphs in visual (reversed) order.
 */
final class UnicodeText
{
    private static ?array $presentationForms = null;

    /**
     * Presentation forms and ligatures -> base letters; bidi control
     * characters removed.
     */
    public static function normalize(string $text): string
    {
        self::$presentationForms ??= require __DIR__.'/unicode-presentation-forms.php';

        $text = strtr($text, self::$presentationForms);

        return preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;
    }

    public static function rtlLetters(string $text): int
    {
        return preg_match_all('/[\p{Arabic}\p{Hebrew}]/u', $text);
    }

    public static function isRtl(string $text): bool
    {
        $rtl = self::rtlLetters($text);

        return $rtl > 0 && $rtl >= preg_match_all('/\p{Latin}/u', $text);
    }

    /**
     * Converts a right-to-left string stored in visual order into logical
     * order. Numbers and Latin words keep their left-to-right order.
     */
    public static function visualToLogical(string $text): string
    {
        // Grapheme clusters keep combining marks (harakat) with their letter.
        preg_match_all('/[0-9A-Za-z][0-9A-Za-z.,:%\/\-]*|\X/u', $text, $matches);
        $tokens = array_reverse($matches[0]);

        $mirror = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<'];

        return implode('', array_map(fn ($token) => $mirror[$token] ?? $token, $tokens));
    }
}
