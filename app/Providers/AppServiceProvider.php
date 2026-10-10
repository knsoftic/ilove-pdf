<?php

namespace App\Providers;

use App\Services\Ads\AdManager;
use App\Services\Ocr\OcrManager;
use App\Services\Pdf\JobManager;
use App\Services\Pdf\PdfInspector;
use App\Services\Pdf\TempStorage;
use App\Services\Settings\SettingsRepository;
use App\Services\System\Capabilities;
use App\Services\Tools\ToolRegistry;
use App\Services\Translation\TranslationManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            SettingsRepository::class,
            Capabilities::class,
            ToolRegistry::class,
            TempStorage::class,
            JobManager::class,
            PdfInspector::class,
            TranslationManager::class,
            OcrManager::class,
            AdManager::class,
        ] as $singleton) {
            $this->app->singleton($singleton);
        }
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        View::composer(['layouts.app', 'partials.*', 'errors.*'], function ($view) {
            $settings = app(SettingsRepository::class);
            $registry = app(ToolRegistry::class);

            $view->with([
                'site' => [
                    'name' => $settings->get('site_name'),
                    'tagline' => $settings->get('site_tagline'),
                    'logo' => $settings->get('logo_path'),
                    'favicon' => $settings->get('favicon_path'),
                    'footer' => $settings->get('footer_text'),
                    'privacy_notice' => $settings->get('privacy_notice'),
                    'twitter' => $settings->get('twitter_handle'),
                    'analytics' => $settings->get('analytics_code'),
                    'head_code' => $settings->get('head_code'),
                    'body_code' => $settings->get('body_code'),
                ],
                'navTools' => collect(config('tools.navigation', []))
                    ->map(fn ($slug) => $registry->find($slug))
                    ->filter(fn ($tool) => $tool?->isEnabled()),
                'footerGroups' => $registry->grouped(),
            ]);
        });
    }

    private function configureRateLimiting(): void
    {
        $limits = config('pdftoolkit.rate_limits');
        $tooMany = fn (string $name) => fn (Request $request) => Limit::perMinute((int) $limits[$name])->by($name.'|'.$request->ip());

        RateLimiter::for('pdf-jobs', $tooMany('jobs'));
        RateLimiter::for('pdf-status', $tooMany('status'));
        RateLimiter::for('pdf-downloads', $tooMany('downloads'));
        RateLimiter::for('pdf-text', $tooMany('text'));

        // Translation calls are chunked by the browser – allow bursts.
        RateLimiter::for('pdf-translate', fn (Request $request) => Limit::perMinute((int) $limits['text'] * 3)->by('translate|'.$request->ip()));

        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute((int) $limits['admin_login'])->by('admin-login|'.$request->ip()),
            Limit::perHour(30)->by('admin-login-email|'.strtolower((string) $request->input('email'))),
        ]);
    }
}
