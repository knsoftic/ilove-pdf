<?php

namespace App\Http\Middleware;

use App\Services\Pdf\TempCleaner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Removes expired temporary files after the response has been sent, on a
 * small share of requests. This keeps storage clean even on hosting without
 * cron; the scheduled `pdf:cleanup` command remains the primary mechanism.
 */
class CleanupLottery
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        [$chance, $outOf] = config('pdftoolkit.cleanup_lottery', [2, 100]);

        if ($chance <= 0 || random_int(1, max(1, $outOf)) > $chance) {
            return;
        }

        try {
            Cache::lock('pdftoolkit.cleanup', 120)->get(fn () => app(TempCleaner::class)->run());
        } catch (Throwable) {
            // Never let housekeeping affect visitors.
        }
    }
}
