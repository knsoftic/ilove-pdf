<?php

namespace App\Services\Seo;

use App\Services\Settings\SettingsRepository;
use App\Services\Tools\Tool;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Str;

class SeoManager
{
    public const SEPARATORS = ['|', '-', '–', '—', '•', '·', '»', ':'];

    public const PAGES = [
        'about' => ['About', 'Free, fast and privacy friendly PDF tools built on open-source software.'],
        'privacy' => ['Privacy Policy', 'How we handle your files and data: processed temporarily, deleted automatically, never shared.'],
        'terms' => ['Terms of Use', 'The terms that apply when you use our free PDF tools.'],
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ToolRegistry $registry,
    ) {}

    public function siteName(): string
    {
        return (string) $this->settings->get('site_name', 'PDF Toolkit');
    }

    public function image(): string
    {
        $path = $this->settings->get('og_image_path');

        return $path ? asset($path) : asset('images/og-image.png');
    }

    /**
     * "Page title | Site name" according to the title settings.
     */
    public function title(string $title): string
    {
        $site = $this->siteName();

        if ($title === '') {
            return $site;
        }

        if (! $this->settings->get('title_append_site', true) || Str::contains($title, $site, true)) {
            return $title;
        }

        $separator = $this->settings->get('title_separator') ?: '|';

        return "{$title} {$separator} {$site}";
    }

    public function home(): SeoData
    {
        $url = route('home');
        $organization = [
            '@type' => 'Organization',
            '@id' => $url.'#organization',
            'name' => $this->siteName(),
            'url' => $url,
            'logo' => $this->settings->get('logo_path') ? asset($this->settings->get('logo_path')) : asset('images/logo.svg'),
        ];

        if ($profiles = array_values(array_filter((array) $this->settings->get('social_profiles', [])))) {
            $organization['sameAs'] = $profiles;
        }

        return new SeoData(
            title: (string) $this->settings->get('seo_title') ?: $this->siteName(),
            description: (string) $this->settings->get('seo_description'),
            canonical: $url,
            keywords: (string) $this->settings->get('seo_keywords'),
            image: $this->image(),
            schemas: [
                [
                    '@type' => 'WebSite',
                    '@id' => $url.'#website',
                    'url' => $url,
                    'name' => $this->siteName(),
                    'description' => (string) $this->settings->get('seo_description'),
                    'publisher' => ['@id' => $url.'#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('tools.index').'?q={search_term_string}'],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                $organization,
            ],
        );
    }

    public function tool(Tool $tool): SeoData
    {
        $url = $tool->url();
        $category = $this->registry->categories()[$tool->category()] ?? null;

        $schemas = [
            [
                '@type' => 'SoftwareApplication',
                'name' => $tool->name(),
                'description' => $tool->metaDescription(),
                'url' => $url,
                'applicationCategory' => 'UtilitiesApplication',
                'operatingSystem' => 'Any (web browser)',
                'browserRequirements' => 'Requires JavaScript and a modern web browser.',
                'isAccessibleForFree' => true,
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                'publisher' => ['@type' => 'Organization', 'name' => $this->siteName()],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
                ], $tool->faq()),
            ],
        ];

        $breadcrumbs = [['name' => 'Home', 'url' => route('home')]];

        if ($category) {
            $breadcrumbs[] = ['name' => $category['name'], 'url' => route('tools.index').'#'.$tool->category()];
        }

        $breadcrumbs[] = ['name' => $tool->name(), 'url' => $url];

        return new SeoData(
            title: $this->title($tool->seoTitle()),
            description: Str::limit($tool->metaDescription(), 300),
            canonical: $url,
            keywords: $tool->keywords(),
            image: $this->image(),
            robots: $tool->noindex() ? 'noindex,follow' : SeoData::INDEX,
            schemas: $schemas,
            breadcrumbs: $breadcrumbs,
        );
    }

    public function toolsIndex(): SeoData
    {
        $tools = $this->registry->enabled();
        $title = $this->settings->get('seo_tools_title') ?: 'All PDF Tools – {count} Free Online PDF Tools';
        $description = $this->settings->get('seo_tools_description')
            ?: 'Every free PDF tool in one place: convert, edit, sign, merge, split, compress, protect, OCR and translate PDF files online. No registration required.';

        return new SeoData(
            title: $this->title(str_replace('{count}', (string) $tools->count(), $title)),
            description: str_replace('{count}', (string) $tools->count(), $description),
            canonical: route('tools.index'),
            keywords: (string) $this->settings->get('seo_keywords'),
            image: $this->image(),
            schemas: [[
                '@type' => 'ItemList',
                'name' => 'PDF tools',
                'itemListElement' => $tools->values()->map(fn (Tool $tool, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $tool->name(),
                    'url' => $tool->url(),
                ])->all(),
            ]],
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'All Tools', 'url' => route('tools.index')],
            ],
        );
    }

    /**
     * About / privacy / terms pages, with the overrides from SEO → Pages.
     */
    public function page(string $page): SeoData
    {
        [$heading, $description] = self::PAGES[$page];
        $override = (array) ($this->settings->get('seo_pages', [])[$page] ?? []);
        $url = route('pages.'.$page);

        return new SeoData(
            title: $this->title($override['title'] ?? $heading),
            description: $override['description'] ?? $description,
            canonical: $url,
            image: $this->image(),
            robots: empty($override['noindex']) ? SeoData::INDEX : 'noindex,follow',
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $heading, 'url' => $url],
            ],
        );
    }

    public function pageIndexable(string $page): bool
    {
        return empty($this->settings->get('seo_pages', [])[$page]['noindex']);
    }

    public function noindex(string $title): SeoData
    {
        return new SeoData(
            title: $this->title($title),
            description: (string) $this->settings->get('seo_description'),
            canonical: url()->current(),
            robots: 'noindex,nofollow',
        );
    }
}
