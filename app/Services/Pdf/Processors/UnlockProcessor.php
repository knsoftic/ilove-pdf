<?php

namespace App\Services\Pdf\Processors;

use App\Exceptions\ToolException;
use App\Services\Pdf\Engines\QpdfEngine;
use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Removes encryption and permission restrictions (qpdf). A document that
 * needs a password to open can only be unlocked with that password.
 */
class UnlockProcessor extends AbstractProcessor
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

        if (! $this->qpdf->isEncrypted($file->path)) {
            throw ToolException::make('nothing_found', 'This PDF is not password protected – there is nothing to unlock.');
        }

        $password = (string) $context->option('password', '');

        if ($password === '' && $this->qpdf->requiresPassword($file->path)) {
            throw ToolException::make('password_protected', 'This PDF needs a password to open. Please enter it to unlock the file.');
        }

        $context->progress(30, 'Removing protection…');
        $output = $context->workPath('unlocked.pdf');
        $this->qpdf->decrypt($file->path, $output, $password, $context->workDir());

        return $context->result($output, $this->name($file, 'unlocked', 'pdf'));
    }
}
