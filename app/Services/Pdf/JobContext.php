<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use App\Helpers\ZipBuilder;
use App\Services\Tools\Tool;
use Closure;
use Illuminate\Support\Facades\File;

/**
 * Everything a processor needs: the uploaded files, the validated options,
 * working folders and a way to report progress.
 */
final class JobContext
{
    private float $lastProgressAt = 0;

    /**
     * @param  list<InputFile>  $inputs
     */
    public function __construct(
        public readonly string $jobId,
        public readonly Tool $tool,
        private readonly array $inputs,
        private readonly array $options,
        private readonly TempStorage $storage,
        private readonly ?Closure $onProgress = null,
    ) {}

    /**
     * @return list<InputFile>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    public function input(int $index = 0): InputFile
    {
        return $this->inputs[$index] ?? throw ToolException::make('no_file');
    }

    public function hasInputs(): bool
    {
        return $this->inputs !== [];
    }

    public function option(string $key, mixed $default = null): mixed
    {
        $value = $this->options[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->tool->params()[$key] ?? $default;
    }

    /**
     * A (created) folder for intermediate files, e.g. workDir('pages').
     */
    public function workDir(string $sub = ''): string
    {
        $path = $this->storage->path($this->jobId, 'work'.($sub !== '' ? '/'.$sub : ''));
        File::ensureDirectoryExists($path);

        return $path;
    }

    /**
     * Path of an intermediate file inside the work folder.
     */
    public function workPath(string $file): string
    {
        return $this->workDir().DIRECTORY_SEPARATOR.basename($file);
    }

    public function outputPath(string $name): string
    {
        return $this->storage->path($this->jobId, 'output/'.basename($name));
    }

    public function progress(int $percent, ?string $message = null): void
    {
        $now = microtime(true);

        if ($this->onProgress && ($percent >= 100 || $now - $this->lastProgressAt > 0.4)) {
            $this->lastProgressAt = $now;
            ($this->onProgress)(max(0, min(100, $percent)), $message);
        }
    }

    /**
     * Turns a produced file into the job result.
     */
    public function result(string $path, string $downloadName, array $stats = []): ProcessResult
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw ToolException::make('conversion_failed');
        }

        $target = $this->outputPath('result.'.pathinfo($downloadName, PATHINFO_EXTENSION));

        if (realpath($path) !== realpath($target)) {
            File::move($path, $target);
        }

        return new ProcessResult($target, $downloadName, ProcessResult::mimeFor($downloadName), $stats);
    }

    /**
     * One file is returned as-is, several files are packed into a ZIP.
     *
     * @param  array<string, string>  $files  Download name => path
     */
    public function resultFromFiles(array $files, string $zipName, array $stats = []): ProcessResult
    {
        if ($files === []) {
            throw ToolException::make('nothing_found');
        }

        if (count($files) === 1) {
            return $this->result(reset($files), array_key_first($files), $stats);
        }

        $zip = ZipBuilder::create($this->outputPath('result.zip'), $files);

        return new ProcessResult($zip, $zipName, 'application/zip', $stats + ['files' => count($files)]);
    }
}
