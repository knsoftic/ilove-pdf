<?php

namespace App\Services\Pdf\Engines;

use App\Exceptions\ToolException;
use App\Services\System\Capabilities;
use App\Services\System\CommandRunner;

/**
 * Wrapper around Ghostscript (https://ghostscript.com, AGPL) used for real
 * PDF compression, grayscale conversion and page rasterisation.
 * Always runs with -dSAFER so documents cannot access the file system.
 */
class GhostscriptEngine
{
    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly CommandRunner $runner,
    ) {}

    public function available(): bool
    {
        return $this->capabilities->has('ghostscript');
    }

    private function base(): array
    {
        $bin = $this->capabilities->path('ghostscript') ?? throw ToolException::make('engine_unavailable');

        return [$bin, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET', '-dNOPROMPT'];
    }

    /**
     * @param  'low'|'medium'|'high'  $level
     */
    public function compress(string $input, string $output, string $level): void
    {
        [$preset, $dpi, $quality] = match ($level) {
            'low' => ['/printer', 200, 0.85],
            'high' => ['/screen', 72, 0.4],
            default => ['/ebook', 120, 0.6],
        };

        // The output file must be set before "-c": Ghostscript processes the
        // arguments in order and opens the device when the PostScript runs.
        $this->runner->mustRun([
            ...$this->base(),
            '-sDEVICE=pdfwrite',
            '-sOutputFile='.$output,
            '-dCompatibilityLevel=1.5',
            "-dPDFSETTINGS={$preset}",
            '-dDetectDuplicateImages=true',
            '-dCompressFonts=true',
            '-dSubsetFonts=true',
            '-dDownsampleColorImages=true',
            '-dDownsampleGrayImages=true',
            '-dDownsampleMonoImages=true',
            "-dColorImageResolution={$dpi}",
            "-dGrayImageResolution={$dpi}",
            '-dMonoImageResolution='.max(150, $dpi * 2),
            '-dColorImageDownsampleThreshold=1.2',
            '-dGrayImageDownsampleThreshold=1.2',
            '-c', "<< /ColorACSImageDict << /QFactor {$quality} /Blend 1 /HSamples [2 1 1 2] /VSamples [2 1 1 2] >> /GrayACSImageDict << /QFactor {$quality} /Blend 1 /HSamples [2 1 1 2] /VSamples [2 1 1 2] >> >> setdistillerparams",
            '-f',
            $input,
        ], 'conversion_failed');
    }

    public function grayscale(string $input, string $output): void
    {
        $this->runner->mustRun([
            ...$this->base(),
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.5',
            '-sColorConversionStrategy=Gray',
            '-dProcessColorModel=/DeviceGray',
            '-dOverrideICC',
            '-sOutputFile='.$output,
            $input,
        ], 'conversion_failed');
    }

    /**
     * Renders pages first..last into "{dir}/page-0001.png" etc. (numbered from 1).
     */
    public function rasterize(string $input, string $dir, string $format, int $dpi, int $first, int $last, int $quality = 85): void
    {
        $device = match ($format) {
            'jpg', 'jpeg' => 'jpeg',
            'gray' => 'pnggray',
            default => 'png16m',
        };
        $extension = $device === 'jpeg' ? 'jpg' : 'png';

        $this->runner->mustRun([
            ...$this->base(),
            "-sDEVICE={$device}",
            "-r{$dpi}",
            "-dJPEGQ={$quality}",
            '-dTextAlphaBits=4',
            '-dGraphicsAlphaBits=4',
            "-dFirstPage={$first}",
            "-dLastPage={$last}",
            '-sOutputFile='.$dir.DIRECTORY_SEPARATOR."page-%04d.{$extension}",
            $input,
        ], 'conversion_failed');
    }
}
