<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Temporary, per-job working directories outside the public web root.
 *
 *   storage/app/pdf-temp/{40 random chars}/input|work|output
 *
 * Job ids are validated against a strict pattern before they are ever used
 * in a path, which makes path traversal impossible.
 */
class TempStorage
{
    public const ID_PATTERN = '/^[A-Za-z0-9]{40}$/';

    public function root(): string
    {
        $root = rtrim(config('pdftoolkit.temp_path'), '/\\');

        if (! is_dir($root)) {
            File::ensureDirectoryExists($root, 0750);
            // Belt and braces in case the folder ever ends up web accessible.
            File::put($root.DIRECTORY_SEPARATOR.'.htaccess', "Require all denied\nDeny from all\n");
            File::put($root.DIRECTORY_SEPARATOR.'.gitignore', "*\n!.gitignore\n");
        }

        return $root;
    }

    public function newId(): string
    {
        return Str::random(40);
    }

    public static function isValidId(?string $id): bool
    {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1;
    }

    public function path(string $id, string $sub = ''): string
    {
        if (! self::isValidId($id)) {
            throw ToolException::make('job_not_found');
        }

        $path = $this->root().DIRECTORY_SEPARATOR.$id;

        if ($sub !== '') {
            if (str_contains($sub, '..') || str_contains($sub, "\0")) {
                throw ToolException::make('server_error');
            }

            $path .= DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sub), DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * Creates the directory skeleton of a new job and returns its id.
     */
    public function create(): string
    {
        $id = $this->newId();

        foreach (['input', 'work', 'output'] as $dir) {
            File::ensureDirectoryExists($this->path($id, $dir), 0750);
        }

        return $id;
    }

    public function exists(string $id): bool
    {
        return self::isValidId($id) && is_dir($this->path($id));
    }

    public function delete(string $id): void
    {
        if (self::isValidId($id)) {
            File::deleteDirectory($this->path($id));
        }
    }

    public function size(string $id): int
    {
        $size = 0;

        foreach (File::allFiles($this->path($id)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * @return list<string> Ids of all job directories.
     */
    public function all(): array
    {
        return collect(File::directories($this->root()))
            ->map(fn ($dir) => basename($dir))
            ->filter(fn ($name) => self::isValidId($name))
            ->values()
            ->all();
    }
}
