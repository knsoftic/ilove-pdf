<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A user-facing processing error.
 *
 * Only the message of a ToolException is ever shown to visitors. Every other
 * exception is logged and replaced by the generic "server_error" message, so
 * no file paths or stack traces can leak.
 */
class ToolException extends RuntimeException
{
    public const MESSAGES = [
        'invalid_pdf' => 'This file is not a valid PDF document.',
        'corrupted_pdf' => 'This PDF appears to be damaged or corrupted and cannot be processed.',
        'password_protected' => 'This PDF is password protected. Please unlock it first or enter the password.',
        'wrong_password' => 'The password you entered is incorrect.',
        'file_too_large' => 'This file is too large. Please upload a smaller file.',
        'unsupported_format' => 'This file format is not supported by this tool.',
        'too_many_files' => 'Too many files. Please upload fewer files at once.',
        'too_few_files' => 'Please add more files to use this tool.',
        'no_file' => 'Please choose a file to process.',
        'too_many_pages' => 'This PDF has too many pages to be processed here.',
        'invalid_page_range' => 'The page selection is not valid for this document.',
        'invalid_options' => 'Some of the options are not valid.',
        'ocr_failed' => 'Text recognition (OCR) failed for this document.',
        'conversion_failed' => 'The conversion failed. The file may be damaged or use unsupported features.',
        'translation_failed' => 'The translation failed. Please try again in a moment.',
        'translation_unavailable' => 'No translation engine is configured on this server.',
        'engine_unavailable' => 'This tool is not available on this server right now.',
        'tool_disabled' => 'This tool is currently disabled.',
        'job_not_found' => 'This file has expired or was already deleted. Please process it again.',
        'job_not_ready' => 'Your file is still being processed.',
        'nothing_found' => 'Nothing was found to process in this document.',
        'rate_limited' => 'Too many requests. Please wait a minute and try again.',
        'server_error' => 'Something went wrong while processing your file. Please try again.',
    ];

    public function __construct(
        public readonly string $errorCode,
        ?string $message = null,
        public readonly int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? self::MESSAGES[$errorCode] ?? self::MESSAGES['server_error'], 0, $previous);
    }

    public static function make(string $code, ?string $message = null, ?Throwable $previous = null): self
    {
        $status = match ($code) {
            'file_too_large' => 413,
            'job_not_found' => 404,
            'rate_limited' => 429,
            'tool_disabled', 'engine_unavailable', 'translation_unavailable' => 503,
            'server_error' => 500,
            default => 422,
        };

        return new self($code, $message, $status, $previous);
    }

    public function toArray(): array
    {
        return ['code' => $this->errorCode, 'message' => $this->getMessage()];
    }
}
