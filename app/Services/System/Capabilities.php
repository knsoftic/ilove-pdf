<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Describes what this server can do: which binaries and PHP extensions are
 * installed, and which processing features that unlocks.
 */
class Capabilities
{
    private const CACHE_KEY = 'pdftoolkit.capabilities.v1';

    private ?array $report = null;

    public function __construct(
        private readonly BinaryLocator $locator,
        private readonly CommandRunner $runner,
    ) {}

    public function report(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        try {
            return $this->report = Cache::remember(
                self::CACHE_KEY,
                (int) config('pdftoolkit.capabilities_cache', 3600),
                fn () => $this->detect()
            );
        } catch (Throwable) {
            return $this->report = $this->detect();
        }
    }

    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        $this->report = null;

        return $this->report();
    }

    /**
     * Does the server provide this feature / binary / extension?
     */
    public function has(string $name): bool
    {
        $report = $this->report();

        return (bool) ($report['features'][$name]
            ?? $report['binaries'][$name]['available']
            ?? $report['php'][$name]
            ?? false);
    }

    public function path(string $binary): ?string
    {
        return $this->report()['binaries'][$binary]['path'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function tesseractLanguages(): array
    {
        return $this->report()['tesseract_languages'] ?? [];
    }

    private function detect(): array
    {
        $binaries = [];

        foreach (BinaryLocator::names() as $name) {
            $binaries[$name] = $this->locator->locate($name);
        }

        $gd = extension_loaded('gd');
        $gdInfo = $gd ? gd_info() : [];

        $php = [
            'gd' => $gd,
            'gd_webp' => $gd && ! empty($gdInfo['WebP Support']),
            'gd_jpeg' => $gd && ! empty($gdInfo['JPEG Support']),
            'gd_png' => $gd && ! empty($gdInfo['PNG Support']),
            'imagick' => extension_loaded('imagick'),
            'zip' => class_exists(\ZipArchive::class),
            'fileinfo' => extension_loaded('fileinfo'),
            'mbstring' => extension_loaded('mbstring'),
        ];

        $available = fn (string $name) => $binaries[$name]['available'];

        $features = [
            'rasterizer' => $available('ghostscript') || $available('pdftoppm'),
            'office' => $available('libreoffice'),
            'ocr' => $available('tesseract') && ($available('ghostscript') || $available('pdftoppm')),
            'compression' => $available('ghostscript'),
            'lossless_optimize' => $available('qpdf'),
            'page_operations' => $available('qpdf'),
            'encryption' => $available('qpdf'),
            'text_extraction' => true,
        ];

        return [
            'binaries' => $binaries,
            'php' => $php,
            'features' => $features,
            'tesseract_languages' => $available('tesseract') ? $this->tesseractLanguagesFrom($binaries['tesseract']['path']) : [],
            'detected_at' => now()->toIso8601String(),
        ];
    }

    private function tesseractLanguagesFrom(string $binary): array
    {
        try {
            $result = $this->runner->run([$binary, '--list-langs'], 20);
        } catch (Throwable) {
            return [];
        }

        $lines = preg_split('/\R/', trim($result->output()."\n".$result->errorOutput()));

        return array_values(array_filter(
            array_map('trim', $lines),
            fn ($line) => $line !== '' && preg_match('/^[a-z_]{3,}$/i', $line) && $line !== 'osd'
        ));
    }
}
