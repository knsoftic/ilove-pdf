<?php

namespace App\Services\Seo;

use App\Models\NotFoundLog;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\Tool;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Str;
use Throwable;

/**
 * Simple on-page SEO checks for the admin panel.
 */
class SeoAudit
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ToolRegistry $registry,
        private readonly Sitemap $sitemap,
    ) {}

    /**
     * Score 0–100 and a checklist for one tool page.
     *
     * @return array{score: int, checks: list<array{ok: bool, label: string, points: int}>}
     */
    public function tool(Tool $tool): array
    {
        $title = $tool->seoTitle();
        $description = $tool->metaDescription();
        $focus = mb_strtolower(trim((string) $tool->focusKeyword()));
        $words = str_word_count(strip_tags(Str::markdown((string) $tool->article())));
        $has = fn (string $text) => $focus !== '' && str_contains(mb_strtolower($text), $focus);

        $checks = [
            [self::between(mb_strlen($title), 30, 65), 'SEO title is 30–65 characters ('.mb_strlen($title).')', 15],
            [self::between(mb_strlen($description), 120, 160), 'Meta description is 120–160 characters ('.mb_strlen($description).')', 15],
            [$focus !== '', 'Focus keyword is set', 10],
            [$has($title), 'Focus keyword appears in the SEO title', 10],
            [$has($description), 'Focus keyword appears in the meta description', 10],
            [$has($tool->heading()), 'Focus keyword appears in the H1 heading', 10],
            [$words >= 300, 'Article below the tool has 300+ words ('.$words.')', 20],
            [count($tool->customFaq()) >= 2, 'At least 2 custom FAQ questions', 5],
            [! $tool->noindex(), 'Page can be indexed by search engines', 5],
        ];

        $score = 0;
        $list = [];

        foreach ($checks as [$ok, $label, $points]) {
            $score += $ok ? $points : 0;
            $list[] = ['ok' => $ok, 'label' => $label, 'points' => $points];
        }

        if ($words >= 100 && $words < 300) {
            $score += 10; // partial credit for a short article
        }

        return ['score' => min(100, $score), 'checks' => $list];
    }

    /**
     * @return array{score: int, checks: list<array>, tools: array<string, array>, average: int}
     */
    public function run(): array
    {
        $tools = [];

        foreach ($this->registry->enabled() as $slug => $tool) {
            $tools[$slug] = ['tool' => $tool] + $this->tool($tool);
        }

        $average = $tools ? (int) round(array_sum(array_column($tools, 'score')) / count($tools)) : 0;
        $checks = $this->siteChecks($tools, $average);

        $passed = count(array_filter($checks, fn ($check) => $check['status'] === 'ok'));
        $relevant = count(array_filter($checks, fn ($check) => $check['status'] !== 'info'))
            ?: 1;
        $siteScore = (int) round(100 * min($passed, $relevant) / $relevant);

        return [
            'score' => (int) round(($siteScore + $average) / 2),
            'checks' => $checks,
            'tools' => $tools,
            'average' => $average,
        ];
    }

    private function siteChecks(array $tools, int $average): array
    {
        $url = config('app.url');
        $host = (string) parse_url((string) $url, PHP_URL_HOST);
        $local = $host === '' || in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test');
        $verification = (array) $this->settings->get('verification', []);
        $homeTitle = (string) $this->settings->get('seo_title');
        $homeDescription = (string) $this->settings->get('seo_description');

        $titles = array_map(fn ($entry) => mb_strtolower($entry['tool']->seoTitle()), $tools);
        $duplicates = count($titles) - count(array_unique($titles));

        try {
            $notFound = NotFoundLog::query()->count();
        } catch (Throwable) {
            $notFound = 0;
        }

        $check = fn (string $status, string $title, string $detail, ?string $route = null) => compact('status', 'title', 'detail', 'route');

        return [
            $this->settings->get('seo_noindex_site')
                ? $check('error', 'Search engines are blocked', 'The whole site is set to “noindex”. Turn it off before going live.', 'admin.seo.sitemap')
                : $check('ok', 'Search engines may index the site', 'robots.txt and meta tags allow indexing.'),
            $local
                ? $check('error', 'APP_URL points to a local address', 'Canonical URLs and the sitemap use '.$url.'. Set APP_URL in .env to your real domain.')
                : (str_starts_with((string) $url, 'https://')
                    ? $check('ok', 'Site uses HTTPS', $url)
                    : $check('warn', 'Site does not use HTTPS', 'Google prefers HTTPS. Install an SSL certificate and update APP_URL.')),
            self::between(mb_strlen($homeTitle), 30, 65)
                ? $check('ok', 'Home page title length is good', mb_strlen($homeTitle).' characters')
                : $check('warn', 'Home page title should be 30–65 characters', 'Currently '.mb_strlen($homeTitle).' characters.', 'admin.seo.pages'),
            self::between(mb_strlen($homeDescription), 120, 160)
                ? $check('ok', 'Home page description length is good', mb_strlen($homeDescription).' characters')
                : $check('warn', 'Home page description should be 120–160 characters', 'Currently '.mb_strlen($homeDescription).' characters.', 'admin.seo.pages'),
            ! empty($verification['google'])
                ? $check('ok', 'Google Search Console is verified', 'Verification meta tag is active.')
                : $check('warn', 'Google Search Console is not connected', 'Add the verification code and submit your sitemap in Search Console.', 'admin.seo.verification'),
            ! empty($verification['bing'])
                ? $check('ok', 'Bing Webmaster Tools is verified', 'Verification meta tag is active.')
                : $check('info', 'Bing Webmaster Tools is not connected', 'Optional – Bing also powers DuckDuckGo and Yahoo results.', 'admin.seo.verification'),
            $this->settings->get('og_image_path')
                ? $check('ok', 'Custom social sharing image', 'Used for Facebook, WhatsApp, X and LinkedIn previews.')
                : $check('info', 'Default social sharing image', 'Upload your own 1200×630 image in Settings → Branding.', 'admin.settings'),
            $duplicates === 0
                ? $check('ok', 'All tool titles are unique', count($tools).' tool pages checked.')
                : $check('warn', $duplicates.' duplicate tool titles', 'Each page should have its own title.', 'admin.seo.tools'),
            $average >= 60
                ? $check('ok', 'Tool pages are well optimised', 'Average tool score: '.$average.'/100.')
                : $check('warn', 'Tool pages can be improved', 'Average tool score: '.$average.'/100. Add focus keywords, articles and FAQs.', 'admin.seo.tools'),
            $check('info', 'Sitemap contains '.count($this->sitemap->urls()).' URLs', route('sitemap'), 'admin.seo.sitemap'),
            $notFound > 0
                ? $check('info', $notFound.' broken URLs were requested', 'Redirect them to the right page to keep visitors and rankings.', 'admin.seo.redirects')
                : $check('ok', 'No broken links recorded', 'The 404 monitor has not seen missing pages.'),
        ];
    }

    private static function between(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }
}
