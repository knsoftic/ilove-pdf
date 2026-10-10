<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoManager;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Static content pages. Their text can be edited in the admin panel
 * (Markdown); sensible defaults are used until then.
 */
class PageController extends Controller
{
    public function __invoke(string $page, SettingsRepository $settings, SeoManager $seo): View
    {
        $markdown = $settings->get('page_'.$page) ?: view('pages.defaults.'.$page, [
            'site' => $settings->get('site_name'),
            'ttl' => config('pdftoolkit.job_ttl'),
        ])->render();

        return view('pages.content', [
            'seo' => $seo->page($page),
            'title' => SeoManager::PAGES[$page][0],
            'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }
}
