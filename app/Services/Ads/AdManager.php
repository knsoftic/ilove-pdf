<?php

namespace App\Services\Ads;

use App\Models\AdUnit;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Decides which ad units appear on the current page and renders them.
 *
 * Ad code is trusted HTML entered by the site owner in the admin panel and is
 * output unchanged. Targeting (page, device) is evaluated on the server so
 * banners that are not meant for a device are never loaded there.
 */
class AdManager
{
    private const CACHE_KEY = 'pdftoolkit.ad_units.v1';

    /** @var list<array>|null */
    private ?array $units = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);

        if (app()->resolved(self::class)) {
            app(self::class)->units = null;
        }
    }

    /**
     * Active units, ordered (cached until an ad unit changes).
     *
     * @return list<array>
     */
    public function units(): array
    {
        if ($this->units !== null) {
            return $this->units;
        }

        try {
            return $this->units = Cache::rememberForever(self::CACHE_KEY, fn () => AdUnit::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (AdUnit $unit) => $unit->only(['id', 'network', 'format', 'placement', 'code', 'url', 'options', 'devices', 'page_scope', 'tools']))
                ->all());
        } catch (Throwable) {
            // Not migrated yet.
            return $this->units = [];
        }
    }

    /**
     * Ads are shown at all for this visitor?
     */
    public function enabled(): bool
    {
        if (! $this->settings->get('ads_enabled', false)) {
            return false;
        }

        return ! ($this->settings->get('ads_hide_for_admin', false) && $this->isAdmin());
    }

    /**
     * The site owner previews the ad positions (placeholders with names).
     */
    public function previewing(): bool
    {
        try {
            return $this->isAdmin() && (bool) session('ads_preview', false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Third-party ad code will be loaded on this page.
     */
    public function active(): bool
    {
        return $this->enabled() && $this->units() !== [];
    }

    /**
     * Units for a position that match the current page and device.
     *
     * @return list<array>
     */
    public function for(string $placement, ?Request $request = null): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $request ??= request();

        return array_values(array_filter(
            $this->units(),
            fn (array $unit) => $unit['placement'] === $placement && $this->matches($unit, $request),
        ));
    }

    public function render(string $placement): string
    {
        $group = AdCatalog::placementGroup($placement);
        $units = $this->for($placement);
        $preview = $this->previewing();

        if ($group === 'script') {
            return implode("\n", array_map(fn ($unit) => (string) $unit['code'], $units));
        }

        if ($units === [] && ! $preview) {
            return '';
        }

        $html = '';

        if ($preview) {
            $label = e(AdCatalog::PLACEMENTS[$placement]['label'] ?? $placement);
            $html .= '<div class="ad-placeholder"><span>Ad position</span><strong>'.$label.'</strong><code>'.e($placement).'</code></div>';
        }

        foreach ($units as $unit) {
            $html .= $this->renderUnit($unit);
        }

        $label = $units !== [] && $this->settings->get('ads_label', true)
            ? '<span class="ad-label">Advertisement</span>'
            : '';

        return '<div class="ad-slot" data-ad-slot="'.e($placement).'">'.$label.$html.'</div>';
    }

    /**
     * Direct links that open when a visitor clicks a download button.
     *
     * @return list<array{url: string, every: int}>
     */
    public function downloadLinks(): array
    {
        return array_map(fn (array $unit) => [
            'id' => $unit['id'],
            'url' => $unit['url'],
            'every' => max(0, (int) ($unit['options']['frequency'] ?? 30)),
        ], array_filter($this->for('download'), fn ($unit) => ! empty($unit['url'])));
    }

    public function isMobile(?Request $request = null): bool
    {
        $agent = (string) ($request ?? request())->userAgent();

        return (bool) preg_match('/Mobi|iPhone|iPod|Android.+Mobile|Windows Phone|Opera Mini|BlackBerry|IEMobile/i', $agent);
    }

    private function renderUnit(array $unit): string
    {
        if ($unit['format'] === 'direct_link') {
            if (empty($unit['url'])) {
                return '';
            }

            $label = e($unit['options']['label'] ?? 'Sponsored link');

            return '<div class="ad-unit"><a href="'.e($unit['url']).'" target="_blank" rel="sponsored nofollow noopener" class="ad-link">'.$label.'</a></div>';
        }

        $height = null;

        if (preg_match('/^(\d+)x(\d+)$/', (string) ($unit['options']['size'] ?? ''), $size)) {
            $height = (int) $size[2];
        }

        $style = $height ? ' style="min-height: '.$height.'px"' : '';

        return '<div class="ad-unit"'.$style.'>'.$unit['code'].'</div>';
    }

    private function matches(array $unit, Request $request): bool
    {
        $mobile = $this->isMobile($request);

        if (($unit['devices'] === 'mobile' && ! $mobile) || ($unit['devices'] === 'desktop' && $mobile)) {
            return false;
        }

        $route = $request->route();
        $name = $route?->getName();
        $tool = $name === 'tools.show' ? (string) $route->parameter('tool') : null;
        $selected = (array) ($unit['tools'] ?? []);

        return match ($unit['page_scope']) {
            'home' => $name === 'home',
            'tools' => $tool !== null,
            'only' => $tool !== null && in_array($tool, $selected, true),
            'except' => $tool === null || ! in_array($tool, $selected, true),
            default => true,
        };
    }

    private function isAdmin(): bool
    {
        try {
            return auth('admin')->check();
        } catch (Throwable) {
            return false;
        }
    }
}
