<?php

namespace App\Services\Pdf\Engines;

use App\Exceptions\ToolException;
use App\Services\System\Capabilities;
use App\Services\System\CommandRunner;

/**
 * Wrapper around qpdf (https://qpdf.sourceforge.io, Apache-2.0) – a fast,
 * reliable tool for structural PDF operations and encryption.
 *
 * qpdf exit codes: 0 = success, 2 = errors, 3 = success with warnings.
 */
class QpdfEngine
{
    // 3 = finished with warnings (output written). Checked here instead of
    // using --warning-exit-0, which older qpdf versions do not know.
    private const OK = [0, 3];

    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly CommandRunner $runner,
    ) {}

    public function available(): bool
    {
        return $this->capabilities->has('qpdf');
    }

    private function bin(): string
    {
        return $this->capabilities->path('qpdf') ?? throw ToolException::make('engine_unavailable');
    }

    public function pageCount(string $pdf): int
    {
        $result = $this->runner->mustRun([$this->bin(), '--show-npages', $pdf], 'invalid_pdf', 60, null, [], self::OK);

        return (int) trim($result->output());
    }

    public function isEncrypted(string $pdf): bool
    {
        return $this->runner->run([$this->bin(), '--is-encrypted', $pdf], 30)->exitCode() === 0;
    }

    public function requiresPassword(string $pdf): bool
    {
        return $this->runner->run([$this->bin(), '--requires-password', $pdf], 30)->exitCode() === 0;
    }

    /**
     * Builds a new PDF from pages of one or more documents.
     *
     * @param  list<array{0:string,1:list<int>}>  $parts  [pdf path, 1-based pages]
     * @param  array<int, int>  $rotations  output page number => angle (90/180/270)
     */
    public function assemble(array $parts, string $output, array $rotations = []): void
    {
        $args = [$this->bin(), '--empty', '--pages'];

        foreach ($parts as [$pdf, $pages]) {
            $args[] = $pdf;
            $args[] = $this->compactRange($pages);
        }

        $args[] = '--';

        foreach ($this->groupRotations($rotations) as $angle => $pages) {
            $args[] = "--rotate=+{$angle}:".$this->compactRange($pages);
        }

        $args[] = $output;

        $this->runner->mustRun($args, 'conversion_failed', null, null, [], self::OK);
    }

    public function encrypt(string $input, string $output, string $userPassword, string $ownerPassword, array $permissions, int $bits = 256, string $workDir = ''): void
    {
        $yes = fn (bool $allowed) => $allowed ? 'y' : 'n';

        $args = [
            '--encrypt', $userPassword, $ownerPassword, (string) $bits,
            '--print='.($permissions['print'] ? 'full' : 'none'),
            '--extract='.$yes($permissions['copy']),
            '--assemble='.$yes($permissions['assemble'] ?? $permissions['modify']),
            '--annotate='.$yes($permissions['modify']),
            '--form='.$yes($permissions['forms'] ?? $permissions['modify']),
            '--modify-other='.$yes($permissions['modify']),
            '--',
        ];

        $this->runWithArgFile($args, $input, $output, $workDir);
    }

    public function decrypt(string $input, string $output, ?string $password, string $workDir = ''): void
    {
        $args = ['--decrypt'];

        if ($password !== null && $password !== '') {
            $args[] = '--password='.$password;
        }

        $this->runWithArgFile($args, $input, $output, $workDir, 'wrong_password');
    }

    public function optimize(string $input, string $output, bool $linearize = false, bool $removeMetadata = false): void
    {
        $args = [
            $this->bin(),
            '--object-streams=generate', '--compress-streams=y', '--recompress-flate',
            '--compression-level=9', '--remove-unreferenced-resources=yes',
        ];

        if ($linearize) {
            $args[] = '--linearize';
        }

        if ($removeMetadata) {
            $args[] = '--remove-info';
            $args[] = '--remove-metadata';
        }

        $this->runner->mustRun([...$args, $input, $output], 'conversion_failed', null, null, [], self::OK);
    }

    public function flatten(string $input, string $output, bool $forms = true, bool $annotations = true): void
    {
        $args = [$this->bin()];

        if ($forms) {
            $args[] = '--generate-appearances';
        }

        $args[] = '--flatten-annotations='.($annotations ? 'all' : 'screen');
        $args[] = $input;
        $args[] = $output;

        $this->runner->mustRun($args, 'conversion_failed', null, null, [], self::OK);
    }

    /**
     * Passwords are written to a private argument file instead of the command
     * line, so they never show up in the process list of the server.
     */
    private function runWithArgFile(array $args, string $input, string $output, string $workDir, string $errorCode = 'conversion_failed'): void
    {
        $argFile = rtrim($workDir ?: sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'qpdf-'.bin2hex(random_bytes(8)).'.args';
        file_put_contents($argFile, implode("\n", $args)."\n");
        @chmod($argFile, 0600);

        try {
            $this->runner->mustRun([$this->bin(), '@'.$argFile, $input, $output], $errorCode, null, null, [], self::OK);
        } finally {
            @unlink($argFile);
        }
    }

    /**
     * [1,2,3,5,7,8] => "1-3,5,7-8"
     *
     * @param  list<int>  $pages
     */
    public function compactRange(array $pages): string
    {
        $parts = [];
        $start = $prev = null;

        foreach ($pages as $page) {
            if ($start === null) {
                $start = $prev = $page;
            } elseif ($page === $prev + 1) {
                $prev = $page;
            } else {
                $parts[] = $start === $prev ? (string) $start : "{$start}-{$prev}";
                $start = $prev = $page;
            }
        }

        if ($start !== null) {
            $parts[] = $start === $prev ? (string) $start : "{$start}-{$prev}";
        }

        return implode(',', $parts);
    }

    /**
     * @param  array<int, int>  $rotations
     * @return array<int, list<int>>
     */
    private function groupRotations(array $rotations): array
    {
        $groups = [];

        foreach ($rotations as $page => $angle) {
            $angle = (($angle % 360) + 360) % 360;

            if ($angle !== 0) {
                $groups[$angle][] = $page;
            }
        }

        foreach ($groups as &$pages) {
            sort($pages);
        }

        return $groups;
    }
}
