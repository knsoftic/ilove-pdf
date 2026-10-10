<?php

namespace App\Services\Seo;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Handles requests that did not match a page: configured redirects first,
 * otherwise the path is counted in the 404 monitor.
 */
class Redirector
{
    public const MAX_LOG_ROWS = 1000;

    /**
     * Requests from vulnerability scanners – not worth logging.
     */
    private const JUNK = '~(\.(php\d?|phtml|asp|aspx|jsp|cgi|env|ini|log|sql|bak|old|swp|zip|tar|gz|rar|7z|yml|yaml|config)$)|(^/(wp-|wordpress|xmlrpc|cgi-bin|phpmyadmin|vendor/|\.))|(/\.)~i';

    public function __construct(private readonly SettingsRepository $settings) {}

    public static function normalize(string $path): string
    {
        $path = '/'.trim(rawurldecode(parse_url($path, PHP_URL_PATH) ?? ''), '/');

        return mb_substr($path, 0, 255);
    }

    public function handle(Request $request): ?Response
    {
        $path = self::normalize($request->getPathInfo());

        try {
            $redirect = Redirect::query()->where('source', $path)->first();
        } catch (Throwable) {
            return null;
        }

        if ($redirect) {
            Redirect::query()->whereKey($redirect->id)->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);

            if ($redirect->status_code === 410 || ! $redirect->target) {
                return response()->view('errors.tool', ['message' => 'This page has been removed.'], 410);
            }

            $target = str_starts_with($redirect->target, '/') ? url($redirect->target) : $redirect->target;

            return redirect()->to($target, $redirect->status_code);
        }

        $this->record($path);

        return null;
    }

    private function record(string $path): void
    {
        if (! $this->settings->get('log_404', true) || preg_match(self::JUNK, $path)) {
            return;
        }

        $admin = '/'.trim(config('pdftoolkit.admin.path', 'admin'), '/');

        if ($path === $admin || str_starts_with($path, $admin.'/')) {
            return;
        }

        try {
            DB::table('not_found_logs')->upsert(
                [['path' => $path, 'hits' => 1, 'last_seen_at' => now()]],
                ['path'],
                ['hits' => DB::raw('hits + 1'), 'last_seen_at' => now()]
            );

            // Keep the table small: drop the oldest entries now and then.
            if (random_int(1, 50) === 1 && NotFoundLog::query()->count() > self::MAX_LOG_ROWS) {
                $keep = NotFoundLog::query()->orderByDesc('last_seen_at')->limit(self::MAX_LOG_ROWS)->pluck('id');
                NotFoundLog::query()->whereNotIn('id', $keep)->delete();
            }
        } catch (Throwable) {
            // Logging must never break the 404 page.
        }
    }
}
