<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;
use Illuminate\Support\Str;

/**
 * Password protection with AES-256 (or AES-128) encryption and permissions.
 */
class ProtectProcessor extends AbstractProcessor
{
    public function __construct(private readonly QpdfEngine $qpdf) {}

    public static function requirements(array $params = []): array
    {
        return [['qpdf']];
    }

    public static function queued(): bool
    {
        return false;
    }

    public function handle(JobContext $context): ProcessResult
    {
        $file = $context->input();
        $pages = $this->pdf($file);
        $password = (string) $context->option('password', '');

        if (mb_strlen($password) < 4) {
            throw ToolException::make('invalid_options', 'Please choose a password with at least 4 characters.');
        }

        $owner = (string) $context->option('owner_password') ?: Str::random(32);
        $bits = $context->option('algorithm') === 'AES-128' ? 128 : 256;

        $context->progress(30, 'Encrypting…');
        $output = $context->workPath('protected.pdf');

        $this->qpdf->encrypt($file->path, $output, $password, $owner, [
            'print' => (bool) $context->option('allow_printing', true),
            'copy' => (bool) $context->option('allow_copying', false),
            'modify' => (bool) $context->option('allow_modifying', false),
            'forms' => (bool) $context->option('allow_forms', $context->option('allow_modifying', false)),
            'assemble' => (bool) $context->option('allow_assembly', false),
        ], $bits, $context->workDir());

        return $context->result($output, $this->name($file, 'protected', 'pdf'), ['pages' => $pages, 'encryption' => "AES-{$bits}"]);
    }
}
