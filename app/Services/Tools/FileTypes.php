<?php

namespace App\Services\Tools;

/**
 * Input file groups accepted by tools, with their extensions, MIME types and
 * how their content signature is verified.
 */
final class FileTypes
{
    public const GROUPS = [
        'pdf' => [
            'label' => 'PDF',
            'extensions' => ['pdf'],
            'mimes' => ['application/pdf', 'application/x-pdf', 'application/acrobat', 'application/octet-stream'],
        ],
        'image' => [
            'label' => 'Images',
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes' => ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'],
        ],
        'word' => [
            'label' => 'Word',
            'extensions' => ['docx', 'doc', 'odt', 'rtf'],
            'mimes' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword', 'application/vnd.oasis.opendocument.text', 'application/rtf', 'text/rtf',
                'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/CDFV2',
                'application/x-ole-storage', 'text/plain',
            ],
        ],
        'excel' => [
            'label' => 'Excel',
            'extensions' => ['xlsx', 'xls', 'ods', 'csv'],
            'mimes' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel',
                'application/vnd.oasis.opendocument.spreadsheet', 'text/csv', 'text/plain', 'application/csv',
                'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/CDFV2',
                'application/x-ole-storage',
            ],
        ],
        'powerpoint' => [
            'label' => 'PowerPoint',
            'extensions' => ['pptx', 'ppt', 'odp'],
            'mimes' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-powerpoint', 'application/vnd.oasis.opendocument.presentation',
                'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/CDFV2',
                'application/x-ole-storage',
            ],
        ],
        'html' => [
            'label' => 'HTML',
            'extensions' => ['html', 'htm'],
            'mimes' => ['text/html', 'text/plain', 'application/xhtml+xml', 'text/xml'],
        ],
        'text' => [
            'label' => 'Text',
            'extensions' => ['txt'],
            'mimes' => ['text/plain', 'application/octet-stream', 'text/x-c', 'text/x-c++', 'text/x-asm'],
        ],
    ];

    /**
     * Browser MIME types per extension (for the file picker "accept" attribute).
     */
    public const BROWSER_MIMES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword', 'odt' => 'application/vnd.oasis.opendocument.text', 'rtf' => 'application/rtf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'csv' => 'text/csv',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt' => 'application/vnd.ms-powerpoint', 'odp' => 'application/vnd.oasis.opendocument.presentation',
        'html' => 'text/html', 'htm' => 'text/html', 'txt' => 'text/plain',
    ];

    /**
     * Checks the first bytes of a file against the expected format.
     */
    public static function signatureMatches(string $path, string $extension): bool
    {
        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        $head = (string) fread($handle, 8192);
        fclose($handle);

        return match ($extension) {
            'pdf' => str_contains(substr($head, 0, 1024), '%PDF-'),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
            'webp' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            'docx' => self::zipContains($path, 'word/document.xml'),
            'xlsx' => self::zipContains($path, 'xl/workbook.xml'),
            'pptx' => self::zipContains($path, 'ppt/presentation.xml'),
            'odt' => self::odfMimetype($path) === 'application/vnd.oasis.opendocument.text',
            'ods' => self::odfMimetype($path) === 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => self::odfMimetype($path) === 'application/vnd.oasis.opendocument.presentation',
            'doc', 'xls', 'ppt' => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'rtf' => str_starts_with(ltrim($head), '{\\rtf'),
            'csv', 'txt' => self::looksLikeText($head),
            'html', 'htm' => self::looksLikeText($head) && str_contains($head, '<'),
            default => false,
        };
    }

    public static function looksLikeText(string $bytes): bool
    {
        // UTF-16 text starts with a BOM and is full of NUL bytes – accept it.
        if (str_starts_with($bytes, "\xFF\xFE") || str_starts_with($bytes, "\xFE\xFF")) {
            return true;
        }

        return ! str_contains($bytes, "\0");
    }

    private static function zipContains(string $path, string $entry): bool
    {
        $zip = new \ZipArchive;

        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return false;
        }

        $found = $zip->locateName($entry) !== false;
        $zip->close();

        return $found;
    }

    private static function odfMimetype(string $path): ?string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return null;
        }

        $mime = $zip->getFromName('mimetype');
        $zip->close();

        return $mime === false ? null : trim($mime);
    }
}
