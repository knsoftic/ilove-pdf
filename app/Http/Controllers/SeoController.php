<?php

namespace App\Http\Controllers;

use App\Services\Seo\Sitemap;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function sitemap(Sitemap $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots(): Response
    {
        // The admin area is deliberately not listed (its path may be secret);
        // it sends "X-Robots-Tag: noindex" instead.
        $lines = $this->settings->get('seo_noindex_site', false)
            ? ['User-agent: *', 'Disallow: /']
            : ['User-agent: *', 'Allow: /', 'Disallow: /api/', 'Disallow: /download/'];

        if ($extra = trim((string) $this->settings->get('robots_extra'))) {
            $lines[] = '';
            array_push($lines, ...preg_split('/\R/', $extra));
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * ads.txt for Adsterra, Monetag, AdSense… (edited under Advertising → Settings).
     */
    public function adsTxt(): Response
    {
        $content = trim((string) $this->settings->get('ads_txt'));

        abort_if($content === '', 404);

        return response($content."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * Files that must live at the site root: the push-notification service
     * worker of an ad network (e.g. Monetag's sw.js) and the IndexNow key file.
     */
    public function rootFile(string $file): Response
    {
        $serviceWorker = (string) $this->settings->get('sw_filename', 'sw.js');
        $code = trim((string) $this->settings->get('sw_code'));

        if ($code !== '' && hash_equals($serviceWorker, $file)) {
            return response($code."\n", 200, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Service-Worker-Allowed' => '/',
                'Cache-Control' => 'no-cache',
            ]);
        }

        $key = (string) $this->settings->get('indexnow_key');

        if ($key !== '' && hash_equals($key.'.txt', $file)) {
            return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        abort(404);
    }
}
