<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Builds safe, human friendly download names.
 *
 * Original client file names are never used on disk. They are only cleaned
 * up here to produce a nice "Content-Disposition" name for the result.
 */
final class FileName
{
    public static function base(?string $original, string $fallback = 'document'): string
    {
        $name = pathinfo((string) $original, PATHINFO_FILENAME);
        $name = Str::ascii($name);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '', $name) ?? '';
        $name = preg_replace('/[\s_]+/', '-', trim($name)) ?? '';
        $name = trim(preg_replace('/-+/', '-', $name) ?? '', '-.');
        $name = Str::limit($name, 80, '');

        return $name !== '' ? $name : $fallback;
    }

    public static function make(?string $original, string $suffix, string $extension, string $fallback = 'document'): string
    {
        $base = self::base($original, $fallback);
        $suffix = trim($suffix, '-');

        return ($suffix !== '' ? "{$base}-{$suffix}" : $base).'.'.ltrim(strtolower($extension), '.');
    }

    /**
     * Value for a Content-Disposition header with an RFC 5987 UTF-8 variant.
     */
    public static function disposition(string $filename, string $type = 'attachment'): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($filename)) ?: 'download';

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $ascii, rawurlencode($filename));
    }
}
