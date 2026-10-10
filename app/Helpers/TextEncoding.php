<?php

namespace App\Helpers;

final class TextEncoding
{
    /**
     * Converts uploaded text of unknown encoding to UTF-8.
     */
    public static function toUtf8(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return substr($bytes, 3);
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($bytes, "\xFE\xFF")) {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }

        if (mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        return mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    }

    /**
     * Charset declared by an HTML document, if any.
     */
    public static function htmlToUtf8(string $html): string
    {
        if (! mb_check_encoding($html, 'UTF-8') && preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m)) {
            try {
                return mb_convert_encoding($html, 'UTF-8', $m[1]);
            } catch (\ValueError) {
                // unknown charset name
            }
        }

        return self::toUtf8($html);
    }
}
