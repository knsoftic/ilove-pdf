<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Seo\IndexNow;
use App\Services\Seo\SeoAudit;
use App\Services\Seo\SeoManager;
use App\Services\Seo\Sitemap;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\Tool;
use App\Services\Tools\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Search engine optimisation: titles & descriptions, structured data, tool
 * articles and FAQs, verification codes, analytics, sitemap and robots.txt.
 */
class SeoController extends Controller
{
    /**
     * Search engine / service => meta tag name.
     */
    public const VERIFICATION = [
        'google' => ['Google Search Console', 'google-site-verification'],
        'bing' => ['Bing Webmaster Tools', 'msvalidate.01'],
        'yandex' => ['Yandex Webmaster', 'yandex-verification'],
        'baidu' => ['Baidu Webmaster', 'baidu-site-verification'],
        'pinterest' => ['Pinterest', 'p:domain_verify'],
        'facebook' => ['Facebook domain verification', 'facebook-domain-verification'],
        'monetag' => ['Monetag site verification', 'monetag'],
    ];

    public const LOCALES = [
        'en_US' => 'English (US)', 'en_GB' => 'English (UK)', 'ur_PK' => 'Urdu (Pakistan)', 'hi_IN' => 'Hindi (India)',
        'ar_AR' => 'Arabic', 'bn_IN' => 'Bengali', 'zh_CN' => 'Chinese', 'es_ES' => 'Spanish', 'fr_FR' => 'French',
        'de_DE' => 'German', 'it_IT' => 'Italian', 'pt_BR' => 'Portuguese (Brazil)', 'ru_RU' => 'Russian',
        'tr_TR' => 'Turkish', 'fa_IR' => 'Persian', 'id_ID' => 'Indonesian', 'ms_MY' => 'Malay',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ToolRegistry $registry,
    ) {}

    public function overview(SeoAudit $audit): View
    {
        return view('admin.seo.overview', ['audit' => $audit->run()]);
    }

    // ---- General ---------------------------------------------------------

    public function general(): View
    {
        return view('admin.seo.general', [
            's' => $this->settings->all(),
            'separators' => SeoManager::SEPARATORS,
            'locales' => self::LOCALES,
            'preview' => app(SeoManager::class)->title('Merge PDF Online – Free, Fast & Secure'),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_separator' => ['required', Rule::in(SeoManager::SEPARATORS)],
            'title_append_site' => ['nullable', 'boolean'],
            'og_locale' => ['required', Rule::in(array_keys(self::LOCALES))],
            'twitter_handle' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'social_profiles' => ['nullable', 'string', 'max:3000'],
            'seo_noindex_site' => ['nullable', 'boolean'],
        ]);

        $profiles = [];

        foreach (preg_split('/\R/', (string) ($data['social_profiles'] ?? '')) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! filter_var($line, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $line)) {
                return back()->withInput()->withErrors(['social_profiles' => "“{$line}” is not a valid profile URL (it must start with https://)."]);
            }

            $profiles[] = $line;
        }

        $this->settings->set([
            'title_separator' => $data['title_separator'],
            'title_append_site' => $request->boolean('title_append_site'),
            'og_locale' => $data['og_locale'],
            'twitter_handle' => isset($data['twitter_handle']) ? '@'.ltrim($data['twitter_handle'], '@') : null,
            'social_profiles' => array_values(array_unique($profiles)),
            'seo_noindex_site' => $request->boolean('seo_noindex_site'),
        ]);

        return back()->with('status', 'SEO settings saved.');
    }

    // ---- Verification & analytics ------------------------------------------

    public function verification(): View
    {
        return view('admin.seo.verification', ['s' => $this->settings->all(), 'services' => self::VERIFICATION]);
    }

    public function updateVerification(Request $request): RedirectResponse
    {
        $rules = [
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/i'],
            'gtm_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,12}$/i'],
            'analytics_code' => ['nullable', 'string', 'max:20000'],
            'head_code' => ['nullable', 'string', 'max:20000'],
            'body_code' => ['nullable', 'string', 'max:20000'],
        ];

        foreach (array_keys(self::VERIFICATION) as $service) {
            $rules["verification.{$service}"] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules, [
            'ga4_id.regex' => 'The Google Analytics 4 ID looks like G-XXXXXXXXXX.',
            'gtm_id.regex' => 'The Google Tag Manager ID looks like GTM-XXXXXXX.',
        ]);

        $verification = [];

        foreach (self::VERIFICATION as $service => [$label]) {
            $value = self::verificationValue((string) ($data['verification'][$service] ?? ''));

            if ($value === false) {
                return back()->withInput()->withErrors(["verification.{$service}" => "The {$label} code is not valid. Paste the meta tag or only its content value."]);
            }

            if ($value !== '') {
                $verification[$service] = $value;
            }
        }

        $this->settings->set([
            'verification' => $verification,
            'ga4_id' => isset($data['ga4_id']) ? strtoupper($data['ga4_id']) : null,
            'gtm_id' => isset($data['gtm_id']) ? strtoupper($data['gtm_id']) : null,
            'analytics_code' => $data['analytics_code'] ?? null,
            'head_code' => $data['head_code'] ?? null,
            'body_code' => $data['body_code'] ?? null,
        ]);

        return back()->with('status', 'Verification and analytics saved.');
    }

    /**
     * Accepts either the content value or the whole <meta …> tag.
     */
    public static function verificationValue(string $input): string|false
    {
        $input = trim($input);

        if ($input === '') {
            return '';
        }

        if (str_contains($input, '<') && preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $input, $match)) {
            $input = trim($match[1]);
        }

        return preg_match('/^[A-Za-z0-9_\-:=+\/.]{1,200}$/', $input) ? $input : false;
    }

    // ---- Pages -------------------------------------------------------------

    public function pages(): View
    {
        return view('admin.seo.pages', ['s' => $this->settings->all(), 'pages' => SeoManager::PAGES]);
    }

    public function updatePages(Request $request): RedirectResponse
    {
        $rules = [
            'seo_title' => ['required', 'string', 'max:120'],
            'seo_description' => ['required', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'seo_tools_title' => ['nullable', 'string', 'max:120'],
            'seo_tools_description' => ['nullable', 'string', 'max:320'],
        ];

        foreach (array_keys(SeoManager::PAGES) as $page) {
            $rules["pages.{$page}.title"] = ['nullable', 'string', 'max:120'];
            $rules["pages.{$page}.description"] = ['nullable', 'string', 'max:320'];
            $rules["pages.{$page}.noindex"] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);
        $pages = [];

        foreach (array_keys(SeoManager::PAGES) as $page) {
            $pages[$page] = array_filter([
                'title' => $data['pages'][$page]['title'] ?? null,
                'description' => $data['pages'][$page]['description'] ?? null,
                'noindex' => $request->boolean("pages.{$page}.noindex") ?: null,
            ]);
        }

        $this->settings->set([
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
            'seo_keywords' => $data['seo_keywords'] ?? null,
            'seo_tools_title' => $data['seo_tools_title'] ?? null,
            'seo_tools_description' => $data['seo_tools_description'] ?? null,
            'seo_pages' => array_filter($pages),
        ]);

        return back()->with('status', 'Page SEO saved.');
    }

    // ---- Tools -------------------------------------------------------------

    public function tools(SeoAudit $audit): View
    {
        $rows = $this->registry->all()->map(fn ($tool) => ['tool' => $tool] + $audit->tool($tool));

        return view('admin.seo.tools', [
            'rows' => $rows,
            'categories' => $this->registry->categories(),
        ]);
    }

    public function tool(string $tool, SeoAudit $audit): View
    {
        $tool = $this->registry->find($tool) ?? abort(404);

        return view('admin.seo.tool', [
            'tool' => $tool,
            'override' => (array) ($this->settings->get('tool_overrides', [])[$tool->slug] ?? []),
            'defaults' => new Tool($tool->slug, config('tools.tools.'.$tool->slug)),
            'audit' => $audit->tool($tool),
            'siteTitle' => app(SeoManager::class)->title('%s'),
        ]);
    }

    public function updateTool(Request $request, string $tool): RedirectResponse
    {
        $tool = $this->registry->find($tool) ?? abort(404);

        $data = $request->validate([
            'focus' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:120'],
            'meta' => ['nullable', 'string', 'max:320'],
            'keywords' => ['nullable', 'string', 'max:400'],
            'heading' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:200'],
            'article' => ['nullable', 'string', 'max:60000'],
            'faq' => ['nullable', 'array', 'max:20'],
            'faq.*.q' => ['nullable', 'string', 'max:250'],
            'faq.*.a' => ['nullable', 'string', 'max:2000'],
            'faq_replace' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
        ]);

        $faq = [];

        foreach ((array) ($data['faq'] ?? []) as $item) {
            $question = trim((string) ($item['q'] ?? ''));
            $answer = trim((string) ($item['a'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $faq[] = [$question, $answer];
            }
        }

        $overrides = (array) $this->settings->get('tool_overrides', []);
        $current = (array) ($overrides[$tool->slug] ?? []);

        // Only the name stays with the "Tools" page; everything else is SEO.
        $seo = [
            'focus' => $data['focus'] ?? null,
            'title' => $data['title'] ?? null,
            'meta' => $data['meta'] ?? null,
            'keywords' => $data['keywords'] ?? null,
            'heading' => $data['heading'] ?? null,
            'description' => $data['description'] ?? null,
            'article' => isset($data['article']) ? trim(str_replace("\r\n", "\n", $data['article'])) : null,
            'custom_faq' => $faq ?: null,
            'faq_replace' => $faq && $request->boolean('faq_replace') ? true : null,
            'noindex' => $request->boolean('noindex') ? true : null,
        ];

        $overrides[$tool->slug] = array_filter(
            array_merge($current, $seo),
            fn ($value) => $value !== null && $value !== '' && $value !== [],
        );

        if ($overrides[$tool->slug] === []) {
            unset($overrides[$tool->slug]);
        }

        $this->settings->set(['tool_overrides' => $overrides]);

        return back()->with('status', "SEO for “{$tool->name()}” saved.");
    }

    // ---- Sitemap, robots.txt, IndexNow -------------------------------------

    public function sitemap(Sitemap $sitemap, IndexNow $indexNow): View
    {
        return view('admin.seo.sitemap', [
            's' => $this->settings->all(),
            'urls' => $sitemap->urls(),
            'indexNow' => $indexNow,
        ]);
    }

    public function updateSitemap(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sitemap_pages' => ['nullable', 'boolean'],
            'seo_noindex_site' => ['nullable', 'boolean'],
            'robots_extra' => ['nullable', 'string', 'max:5000'],
        ]);

        $extra = trim(str_replace("\r\n", "\n", (string) ($data['robots_extra'] ?? '')));

        foreach (array_filter(explode("\n", $extra), fn ($line) => trim($line) !== '') as $line) {
            if (! preg_match('/^\s*(#|(user-agent|allow|disallow|crawl-delay|sitemap|host|clean-param)\s*:)/i', $line)) {
                return back()->withInput()->withErrors(['robots_extra' => "Invalid robots.txt line: “{$line}”. Use User-agent:, Allow:, Disallow:, Crawl-delay: or Sitemap:."]);
            }
        }

        $this->settings->set([
            'sitemap_pages' => $request->boolean('sitemap_pages'),
            'seo_noindex_site' => $request->boolean('seo_noindex_site'),
            'robots_extra' => $extra ?: null,
        ]);

        return back()->with('status', 'Sitemap and robots.txt saved.');
    }

    public function indexNowKey(IndexNow $indexNow): RedirectResponse
    {
        $indexNow->generateKey();

        return back()->with('status', 'A new IndexNow key was created.');
    }

    public function indexNowSubmit(IndexNow $indexNow, Sitemap $sitemap): RedirectResponse
    {
        $result = $indexNow->submit(array_column($sitemap->urls(), 'loc'));

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->withErrors(['indexnow' => $result['message']]);
    }
}
