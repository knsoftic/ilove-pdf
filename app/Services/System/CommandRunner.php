<?php

namespace App\Services\System;

use App\Exceptions\ToolException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Runs external binaries safely.
 *
 * Commands are always passed as argument arrays (never through a shell), so
 * user supplied values such as passwords cannot inject shell syntax.
 */
class CommandRunner
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    public function run(array $command, ?int $timeout = null, ?string $cwd = null, array $env = [], ?string $input = null): ProcessResult
    {
        $timeout ??= (int) config('pdftoolkit.limits.process_timeout', 300);

        $process = Process::timeout($timeout)->env($env);

        if ($cwd !== null) {
            $process = $process->path($cwd);
        }

        if ($input !== null) {
            $process = $process->input($input);
        }

        return $process->run($command);
    }

    /**
     * Runs a command and throws a friendly ToolException when it fails.
     *
     * @param  list<string>  $command
     * @param  list<int>  $okExitCodes
     */
    public function mustRun(array $command, string $errorCode = 'conversion_failed', ?int $timeout = null, ?string $cwd = null, array $env = [], array $okExitCodes = [0]): ProcessResult
    {
        try {
            $result = $this->run($command, $timeout, $cwd, $env);
        } catch (Throwable $e) {
            Log::warning('External command could not run', ['binary' => basename($command[0] ?? ''), 'error' => $e->getMessage()]);

            throw ToolException::make($errorCode, null, $e);
        }

        if (! in_array($result->exitCode(), $okExitCodes, true)) {
            $stderr = trim($result->errorOutput()."\n".$result->output());

            Log::warning('External command failed', [
                'binary' => basename($command[0] ?? ''),
                'exit' => $result->exitCode(),
                'output' => mb_substr($stderr, 0, 2000),
            ]);

            throw $this->mapError($stderr, $errorCode);
        }

        return $result;
    }

    private function mapError(string $output, string $fallback): ToolException
    {
        $lower = strtolower($output);

        return match (true) {
            str_contains($lower, 'invalid password'),
            str_contains($lower, 'incorrect password') => ToolException::make('wrong_password'),
            str_contains($lower, 'password') && str_contains($lower, 'encrypt') => ToolException::make('password_protected'),
            str_contains($lower, 'not a pdf file'),
            str_contains($lower, "can't find pdf header"),
            str_contains($lower, 'unable to find trailer') => ToolException::make('invalid_pdf'),
            str_contains($lower, 'damaged'),
            str_contains($lower, 'corrupt') => ToolException::make('corrupted_pdf'),
            default => ToolException::make($fallback),
        };
    }
}
