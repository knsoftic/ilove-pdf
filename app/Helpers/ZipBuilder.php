<?php

namespace App\Helpers;

use App\Exceptions\ToolException;
use ZipArchive;

final class ZipBuilder
{
    /**
     * Packs files into a ZIP archive.
     *
     * @param  array<string, string>  $files  Entry name => absolute path.
     */
    public static function create(string $target, array $files): string
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ToolException::make('server_error');
        }

        $used = [];

        foreach ($files as $name => $path) {
            $entry = self::uniqueName(basename(str_replace('\\', '/', (string) $name)), $used);
            $zip->addFile($path, $entry);

            // Already-compressed formats gain nothing from deflate.
            if (preg_match('/\.(jpe?g|png|webp|zip|docx|xlsx|pptx)$/i', $entry)) {
                $zip->setCompressionName($entry, ZipArchive::CM_STORE);
            }
        }

        $zip->close();

        return $target;
    }

    private static function uniqueName(string $name, array &$used): string
    {
        $candidate = $name;
        $i = 2;

        while (isset($used[strtolower($candidate)])) {
            $candidate = pathinfo($name, PATHINFO_FILENAME)."-{$i}.".pathinfo($name, PATHINFO_EXTENSION);
            $i++;
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }
}
