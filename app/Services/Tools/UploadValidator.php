<?php

namespace App\Services\Tools;

use App\Exceptions\ToolException;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\UploadedFile;

/**
 * Validates uploaded files for a tool:
 *  - upload succeeded and is within the size limit
 *  - extension is allowed for the tool and not on the block list
 *  - MIME type detected from the content (finfo) matches the extension
 *  - the file signature ("magic bytes") matches the format
 *
 * The original file name is never trusted for anything else.
 */
class UploadValidator
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<string> Validated extension for each file.
     */
    public function validate(Tool $tool, array $files, bool $required = true): array
    {
        if ($files === []) {
            if ($required) {
                throw ToolException::make('no_file');
            }

            return [];
        }

        $max = min($tool->maxFiles(), $this->settings->maxFiles());

        if (count($files) > $max) {
            throw ToolException::make('too_many_files', "Please upload at most {$max} files at once.");
        }

        if ($required && count($files) < $tool->minFiles()) {
            throw ToolException::make('too_few_files', "Please add at least {$tool->minFiles()} files.");
        }

        $allowed = $this->registry->extensionsFor($tool);
        $maxBytes = $this->settings->maxUploadBytes();
        $blocked = config('pdftoolkit.security.blocked_extensions', []);
        $extensions = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $error = $file instanceof UploadedFile ? $file->getError() : null;

                throw ToolException::make(in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'file_too_large' : 'unsupported_format');
            }

            if ($file->getSize() > $maxBytes) {
                throw ToolException::make('file_too_large', 'Files can be at most '.format_bytes($maxBytes).'.');
            }

            if ($file->getSize() === 0) {
                throw ToolException::make('unsupported_format', 'One of the files is empty.');
            }

            $name = strtolower((string) $file->getClientOriginalName());
            $extension = strtolower($file->getClientOriginalExtension());

            // Reject dangerous extensions anywhere in the name ("invoice.php.pdf").
            foreach (array_slice(explode('.', $name), 1) as $part) {
                if (in_array($part, $blocked, true)) {
                    throw ToolException::make('unsupported_format', 'This file type is not allowed.');
                }
            }

            if (! in_array($extension, $allowed, true)) {
                throw ToolException::make('unsupported_format', 'Unsupported file format. Allowed: '.$this->registry->acceptLabel($tool).'.');
            }

            $group = $this->groupFor($tool, $extension);
            $mime = (string) $file->getMimeType();

            if (! in_array($mime, FileTypes::GROUPS[$group]['mimes'], true)) {
                throw ToolException::make($group === 'pdf' ? 'invalid_pdf' : 'unsupported_format');
            }

            if (! FileTypes::signatureMatches($file->getRealPath(), $extension)) {
                throw ToolException::make($group === 'pdf' ? 'invalid_pdf' : 'unsupported_format', $group === 'pdf'
                    ? null
                    : 'The content of "'.e(mb_substr($file->getClientOriginalName(), 0, 80)).'" does not match its file type.');
            }

            if (in_array($group, ['image', 'word', 'excel', 'powerpoint'], true) && $this->containsServerScript($file->getRealPath())) {
                throw ToolException::make('unsupported_format', 'This file type is not allowed.');
            }

            $extensions[] = $extension === 'jpeg' ? 'jpg' : ($extension === 'htm' ? 'html' : $extension);
        }

        return $extensions;
    }

    private function groupFor(Tool $tool, string $extension): string
    {
        foreach ($tool->accept() as $group) {
            if (in_array($extension, FileTypes::GROUPS[$group]['extensions'] ?? [], true)) {
                return $group;
            }
        }

        throw ToolException::make('unsupported_format');
    }

    /**
     * Defence in depth against polyglot uploads (e.g. an image with PHP code
     * appended). Files are never stored in a web accessible place anyway.
     */
    private function containsServerScript(string $path): bool
    {
        $handle = fopen($path, 'rb');
        $head = (string) fread($handle, 16384);
        fclose($handle);

        return (bool) preg_match('/<\?(php|=)/i', $head);
    }
}
