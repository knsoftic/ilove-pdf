<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Pdf\JobController;
use App\Http\Controllers\Pdf\TextExportController;
use App\Http\Controllers\Pdf\ToolController;
use App\Http\Controllers\Pdf\TranslationController;
use App\Http\Controllers\Pdf\UsageController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');

Route::get('/about', PageController::class)->defaults('page', 'about')->name('pages.about');
Route::get('/privacy-policy', PageController::class)->defaults('page', 'privacy')->name('pages.privacy');
Route::get('/terms', PageController::class)->defaults('page', 'terms')->name('pages.terms');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/ads.txt', [SeoController::class, 'adsTxt'])->name('ads.txt');

// Root files configured in the admin: ad network service worker, IndexNow key.
Route::get('/{file}', [SeoController::class, 'rootFile'])
    ->where('file', '[A-Za-z0-9_-]{1,128}\.(js|txt)')
    ->name('root-file');

/*
|--------------------------------------------------------------------------
| Processing API (session + CSRF protected, rate limited)
|--------------------------------------------------------------------------
*/

Route::prefix('api')->name('api.')->group(function () {
    Route::post('tools/{tool}/jobs', [JobController::class, 'store'])
        ->middleware('throttle:pdf-jobs')
        ->name('jobs.store');

    Route::get('jobs/{job}', [JobController::class, 'show'])
        ->middleware(['signed:relative', 'throttle:pdf-status'])
        ->name('jobs.show');

    Route::delete('jobs/{job}', [JobController::class, 'destroy'])
        ->middleware(['signed:relative', 'throttle:pdf-status'])
        ->name('jobs.destroy');

    Route::post('export/text', TextExportController::class)
        ->middleware('throttle:pdf-text')
        ->name('export.text');

    Route::get('translate/languages', [TranslationController::class, 'languages'])->name('translate.languages');
    Route::post('translate/detect', [TranslationController::class, 'detect'])
        ->middleware('throttle:pdf-text')
        ->name('translate.detect');
    Route::post('translate', [TranslationController::class, 'translate'])
        ->middleware('throttle:pdf-translate')
        ->name('translate');

    Route::post('usage/{tool}', UsageController::class)
        ->middleware('throttle:pdf-text')
        ->name('usage');
});

Route::get('/download/{job}', [JobController::class, 'download'])
    ->middleware(['signed:relative', 'throttle:pdf-downloads'])
    ->name('jobs.download');

/*
|--------------------------------------------------------------------------
| Admin panel (website owner only – separate "admin" guard)
|--------------------------------------------------------------------------
*/

Route::prefix(trim(config('pdftoolkit.admin.path', 'admin'), '/'))->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'show'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:admin-login')->name('login.attempt');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::get('tools', [Admin\ToolController::class, 'index'])->name('tools');
        Route::put('tools', [Admin\ToolController::class, 'update'])->name('tools.update');

        Route::get('content', [Admin\ContentController::class, 'edit'])->name('content');
        Route::put('content', [Admin\ContentController::class, 'update'])->name('content.update');

        Route::get('ads', [Admin\AdsController::class, 'index'])->name('ads');
        Route::put('ads/settings', [Admin\AdsController::class, 'settings'])->name('ads.settings');
        Route::post('ads/preview', [Admin\AdsController::class, 'preview'])->name('ads.preview');
        Route::get('ads/create', [Admin\AdsController::class, 'create'])->name('ads.create');
        Route::post('ads', [Admin\AdsController::class, 'store'])->name('ads.store');
        Route::get('ads/{unit}/edit', [Admin\AdsController::class, 'edit'])->name('ads.edit');
        Route::put('ads/{unit}', [Admin\AdsController::class, 'update'])->name('ads.update');
        Route::patch('ads/{unit}/toggle', [Admin\AdsController::class, 'toggle'])->name('ads.toggle');
        Route::delete('ads/{unit}', [Admin\AdsController::class, 'destroy'])->name('ads.destroy');

        Route::prefix('seo')->name('seo')->controller(Admin\SeoController::class)->group(function () {
            Route::get('/', 'overview');
            Route::get('general', 'general')->name('.general');
            Route::put('general', 'updateGeneral')->name('.general.update');
            Route::get('verification', 'verification')->name('.verification');
            Route::put('verification', 'updateVerification')->name('.verification.update');
            Route::get('pages', 'pages')->name('.pages');
            Route::put('pages', 'updatePages')->name('.pages.update');
            Route::get('tools', 'tools')->name('.tools');
            Route::get('tools/{tool}', 'tool')->name('.tool');
            Route::put('tools/{tool}', 'updateTool')->name('.tool.update');
            Route::get('sitemap', 'sitemap')->name('.sitemap');
            Route::put('sitemap', 'updateSitemap')->name('.sitemap.update');
            Route::post('indexnow/key', 'indexNowKey')->name('.indexnow.key');
            Route::post('indexnow/submit', 'indexNowSubmit')->name('.indexnow.submit');
        });

        Route::prefix('seo')->name('seo.')->controller(Admin\RedirectController::class)->group(function () {
            Route::get('redirects', 'index')->name('redirects');
            Route::post('redirects', 'store')->name('redirects.store');
            Route::delete('redirects/{redirect}', 'destroy')->name('redirects.destroy');
            Route::put('not-found/settings', 'settings')->name('notfound.settings');
            Route::delete('not-found', 'clear')->name('notfound.clear');
            Route::delete('not-found/{log}', 'forget')->name('notfound.destroy');
        });

        Route::get('system', [Admin\SystemController::class, 'index'])->name('system');
        Route::post('system/cleanup', [Admin\SystemController::class, 'cleanup'])->name('system.cleanup');
        Route::post('system/refresh', [Admin\SystemController::class, 'refresh'])->name('system.refresh');

        Route::get('account', [Admin\AccountController::class, 'edit'])->name('account');
        Route::put('account', [Admin\AccountController::class, 'update'])->name('account.update');
    });
});

/*
|--------------------------------------------------------------------------
| Tool pages: /merge-pdf, /pdf-to-word, /pdf-editor, ...
|--------------------------------------------------------------------------
|
| One SEO friendly URL per tool, generated from config/tools.php. Aliases
| (e.g. /translate-pdf) redirect permanently to the canonical URL.
|
*/

$toolSlugs = array_keys(config('tools.tools', []));

foreach (config('tools.tools', []) as $definition) {
    array_push($toolSlugs, ...($definition['aliases'] ?? []));
}

Route::get('/{tool}', [ToolController::class, 'show'])
    ->where('tool', implode('|', array_map(fn ($slug) => preg_quote($slug, '/'), $toolSlugs)))
    ->name('tools.show');
