<?php

namespace App\Services\Seo;

use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\ToolRegistry;
use Throwable;

/**
 * URLs listed in sitemap.xml (and submitted to IndexNow).
 */
class Sitemap
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ToolRegistry $registry,
        private readonly SeoManager $seo,
    ) {}

    /**
     * @return list<array{loc: string, priority: string, changefreq: string}>
     */
    public function urls(): array
    {
        if ($this->settings->get('seo_noindex_site', false)) {
            return [];
        }

        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('tools.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
        ];

        foreach ($this->registry->enabled() as $tool) {
            if (! $tool->noindex()) {
                $urls[] = [
                    'loc' => $tool->url(),
                    'priority' => $tool->isPopular() ? '0.9' : '0.8',
                    'changefreq' => 'monthly',
                ];
            }
        }

        if ($this->settings->get('sitemap_pages', true)) {
            foreach (array_keys(SeoManager::PAGES) as $page) {
                if ($this->seo->pageIndexable($page)) {
                    $urls[] = ['loc' => route('pages.'.$page), 'priority' => '0.3', 'changefreq' => 'yearly'];
                }
            }
        }

        return $urls;
    }

    public function lastModified(): string
    {
        $times = [filemtime(config_path('tools.php')) ?: time()];

        try {
            if ($updated = Setting::query()->max('updated_at')) {
                $times[] = strtotime((string) $updated);
            }
        } catch (Throwable) {
            // not migrated
        }

        return date('Y-m-d', max($times));
    }

    public function xml(): string
    {
        $lastmod = $this->lastModified();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->urls() as $url) {
            $xml .= sprintf(
                "  <url><loc>%s</loc><lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%s</priority></url>\n",
                e($url['loc']), $lastmod, $url['changefreq'], $url['priority']
            );
        }

        return $xml."</urlset>\n";
    }
}
