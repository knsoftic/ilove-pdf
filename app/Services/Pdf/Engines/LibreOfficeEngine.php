<?php

namespace App\Services\Pdf\Engines;

use App\Exceptions\ToolException;
use App\Services\System\Capabilities;
use App\Services\System\CommandRunner;
use Illuminate\Support\Facades\File;

/**
 * Headless LibreOffice (https://www.libreoffice.org, MPL-2.0) for high
 * fidelity Office <-> PDF conversions.
 *
 * Each conversion uses its own throw-away user profile so several
 * conversions can safely run in parallel.
 */
class LibreOfficeEngine
{
    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly CommandRunner $runner,
    ) {}

    public function available(): bool
    {
        return $this->capabilities->has('libreoffice');
    }

    /**
     * @return string Path of the converted file.
     */
    public function convert(string $input, string $outDir, string $format, ?string $inputFilter = null, ?int $timeout = null): string
    {
        $bin = $this->capabilities->path('libreoffice') ?? throw ToolException::make('engine_unavailable');
        $profile = $outDir.DIRECTORY_SEPARATOR.'lo-profile';
        File::ensureDirectoryExists($profile);

        $args = [
            $bin,
            '-env:UserInstallation='.$this->fileUri($profile),
            '--headless', '--invisible', '--nologo', '--norestore', '--nolockcheck', '--nodefault', '--nofirststartwizard',
        ];

        if ($inputFilter) {
            $args[] = '--infilter='.$inputFilter;
        }

        array_push($args, '--convert-to', $format, '--outdir', $outDir, $input);

        try {
            $this->runner->mustRun($args, 'conversion_failed', $timeout ?? max(120, (int) config('pdftoolkit.limits.process_timeout')), null, [
                'HOME' => $profile,
            ]);
        } finally {
            File::deleteDirectory($profile);
        }

        $extension = explode(':', $format)[0];
        $expected = $outDir.DIRECTORY_SEPARATOR.pathinfo($input, PATHINFO_FILENAME).'.'.$extension;

        if (! is_file($expected) || filesize($expected) === 0) {
            throw ToolException::make('conversion_failed');
        }

        return $expected;
    }

    private function fileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $segments = array_map(
            fn ($segment) => preg_match('/^[A-Za-z]:$/', $segment) ? $segment : rawurlencode($segment),
            explode('/', $path)
        );

        return 'file://'.(str_starts_with($path, '/') ? '' : '/').implode('/', $segments);
    }
}
