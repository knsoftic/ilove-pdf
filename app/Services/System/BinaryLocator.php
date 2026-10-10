<?php

namespace App\Services\System;

use Throwable;

/**
 * Finds optional open-source binaries (qpdf, Ghostscript, Poppler, LibreOffice,
 * Tesseract) either from the configured path, the system PATH or the usual
 * installation folders on Windows and macOS.
 */
class BinaryLocator
{
    /**
     * name => [command names, extra absolute/glob paths, version arguments, version regex]
     */
    private const DEFINITIONS = [
        'qpdf' => [
            ['qpdf'],
            ['C:/Program Files/qpdf*/bin/qpdf.exe', '/opt/homebrew/bin/qpdf', '/usr/local/bin/qpdf'],
            ['--version'],
            '/qpdf version ([\d.]+)/i',
        ],
        'ghostscript' => [
            ['gs', 'gswin64c', 'gswin32c'],
            ['C:/Program Files/gs/gs*/bin/gswin64c.exe', 'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe', '/opt/homebrew/bin/gs', '/usr/local/bin/gs'],
            ['--version'],
            '/^\s*([\d.]+)/',
        ],
        'pdftotext' => [
            ['pdftotext'],
            ['C:/Program Files/poppler*/Library/bin/pdftotext.exe', 'C:/poppler*/Library/bin/pdftotext.exe', 'C:/Program Files/Git/mingw64/bin/pdftotext.exe', '/opt/homebrew/bin/pdftotext'],
            ['-v'],
            '/pdftotext version ([\d.]+)/i',
        ],
        'pdftoppm' => [
            ['pdftoppm'],
            ['C:/Program Files/poppler*/Library/bin/pdftoppm.exe', 'C:/poppler*/Library/bin/pdftoppm.exe', '/opt/homebrew/bin/pdftoppm'],
            ['-v'],
            '/pdftoppm version ([\d.]+)/i',
        ],
        'pdfimages' => [
            ['pdfimages'],
            ['C:/Program Files/poppler*/Library/bin/pdfimages.exe', 'C:/poppler*/Library/bin/pdfimages.exe', '/opt/homebrew/bin/pdfimages'],
            ['-v'],
            '/pdfimages version ([\d.]+)/i',
        ],
        'pdftohtml' => [
            ['pdftohtml'],
            ['C:/Program Files/poppler*/Library/bin/pdftohtml.exe', 'C:/poppler*/Library/bin/pdftohtml.exe', '/opt/homebrew/bin/pdftohtml'],
            ['-v'],
            '/pdftohtml version ([\d.]+)/i',
        ],
        'libreoffice' => [
            ['soffice', 'libreoffice'],
            ['C:/Program Files/LibreOffice/program/soffice.com', 'C:/Program Files/LibreOffice/program/soffice.exe', '/Applications/LibreOffice.app/Contents/MacOS/soffice', '/usr/bin/soffice', '/usr/lib/libreoffice/program/soffice'],
            ['--version'],
            '/LibreOffice ([\d.]+)/i',
        ],
        'tesseract' => [
            ['tesseract'],
            ['C:/Program Files/Tesseract-OCR/tesseract.exe', 'C:/Program Files (x86)/Tesseract-OCR/tesseract.exe', '/opt/homebrew/bin/tesseract', '/usr/local/bin/tesseract'],
            ['--version'],
            '/tesseract v?([\d.]+)/i',
        ],
    ];

    public function __construct(private readonly CommandRunner $runner) {}

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * @return array{available: bool, path: ?string, version: ?string}
     */
    public function locate(string $name): array
    {
        [$commands, $paths, $versionArgs, $regex] = self::DEFINITIONS[$name];

        $configured = config("pdftoolkit.binaries.{$name}");
        $candidates = $configured ? [$configured] : [...$commands, ...$this->expandPaths($paths)];

        foreach ($candidates as $candidate) {
            if ($version = $this->probe($candidate, $versionArgs, $regex)) {
                return ['available' => true, 'path' => $candidate, 'version' => $version];
            }
        }

        return ['available' => false, 'path' => null, 'version' => null];
    }

    private function probe(string $binary, array $args, string $regex): ?string
    {
        try {
            $result = $this->runner->run([$binary, ...$args], 20);
        } catch (Throwable) {
            return null;
        }

        $output = $result->output()."\n".$result->errorOutput();

        if (preg_match($regex, $output, $m)) {
            return $m[1];
        }

        return $result->successful() && trim($output) !== '' ? 'unknown' : null;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function expandPaths(array $paths): array
    {
        $found = [];

        foreach ($paths as $path) {
            $isWindowsPath = (bool) preg_match('/^[A-Z]:\//i', $path);

            if ($isWindowsPath !== (PHP_OS_FAMILY === 'Windows')) {
                continue;
            }

            foreach (str_contains($path, '*') ? (glob($path) ?: []) : [$path] as $match) {
                if (is_file($match)) {
                    $found[] = $match;
                }
            }
        }

        // Prefer the newest version when several are installed side by side.
        rsort($found, SORT_NATURAL);

        return $found;
    }
}
