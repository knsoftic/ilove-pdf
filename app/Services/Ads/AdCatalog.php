<?php

namespace App\Services\Ads;

/**
 * Ad networks, formats and page positions supported by the ad manager.
 *
 * Formats are grouped by how they are rendered:
 *  - script: invisible tags placed in <head> / <body> (popunder, social bar, push…)
 *  - slot:   visible units rendered inside a position on the page (banners, native)
 *  - link:   a URL (direct link / smartlink) shown as a button or opened on download
 *  - any:    custom HTML that may go anywhere
 */
final class AdCatalog
{
    public const NETWORKS = [
        'adsterra' => [
            'name' => 'Adsterra',
            'color' => '#e8442e',
            'site' => 'https://adsterra.com',
            'formats' => ['popunder', 'social_bar', 'native', 'banner', 'direct_link', 'custom'],
        ],
        'monetag' => [
            'name' => 'Monetag',
            'color' => '#7c3aed',
            'site' => 'https://monetag.com',
            'formats' => ['multitag', 'popunder', 'push', 'social_bar', 'vignette', 'direct_link', 'custom'],
        ],
        'adsense' => [
            'name' => 'Google AdSense',
            'color' => '#1a73e8',
            'site' => 'https://adsense.google.com',
            'formats' => ['auto_ads', 'banner', 'native', 'custom'],
        ],
        'custom' => [
            'name' => 'Other network',
            'color' => '#64748b',
            'site' => null,
            'formats' => ['popunder', 'social_bar', 'multitag', 'push', 'vignette', 'auto_ads', 'banner', 'native', 'direct_link', 'custom'],
        ],
    ];

    public const FORMATS = [
        'popunder' => ['label' => 'Popunder', 'kind' => 'script', 'placement' => 'head'],
        'social_bar' => ['label' => 'Social Bar', 'kind' => 'script', 'placement' => 'body_end'],
        'multitag' => ['label' => 'MultiTag', 'kind' => 'script', 'placement' => 'head'],
        'push' => ['label' => 'Push Notifications', 'kind' => 'script', 'placement' => 'head'],
        'vignette' => ['label' => 'Vignette / Interstitial', 'kind' => 'script', 'placement' => 'head'],
        'auto_ads' => ['label' => 'Auto ads', 'kind' => 'script', 'placement' => 'head'],
        'banner' => ['label' => 'Display banner', 'kind' => 'slot', 'placement' => 'tool_top'],
        'native' => ['label' => 'Native banner', 'kind' => 'slot', 'placement' => 'tool_bottom'],
        'direct_link' => ['label' => 'Direct Link', 'kind' => 'link', 'placement' => 'download'],
        'custom' => ['label' => 'Custom HTML / JavaScript', 'kind' => 'any', 'placement' => 'tool_top'],
    ];

    /**
     * Network specific names for the same format.
     */
    public const FORMAT_LABELS = [
        'adsterra' => ['direct_link' => 'Direct Link (Smartlink)'],
        'monetag' => [
            'popunder' => 'OnClick (Popunder)',
            'social_bar' => 'In-Page Push',
            'vignette' => 'Vignette Banner',
            'multitag' => 'MultiTag (all formats in one tag)',
        ],
        'adsense' => [
            'banner' => 'Display ad unit',
            'native' => 'In-article / in-feed ad',
        ],
    ];

    public const PLACEMENTS = [
        'head' => ['label' => 'Inside <head> (invisible script)', 'group' => 'script'],
        'body_start' => ['label' => 'Right after <body> (invisible script)', 'group' => 'script'],
        'body_end' => ['label' => 'Before </body> (invisible script)', 'group' => 'script'],
        'header' => ['label' => 'Below the header – all pages', 'group' => 'slot'],
        'home_middle' => ['label' => 'Home page – between the sections', 'group' => 'slot'],
        'tools_index' => ['label' => 'All tools page – above the list', 'group' => 'slot'],
        'tool_top' => ['label' => 'Tool pages – above the upload area', 'group' => 'slot'],
        'tool_bottom' => ['label' => 'Tool pages – below the tool and result', 'group' => 'slot'],
        'tool_content' => ['label' => 'Tool pages – between FAQ and related tools', 'group' => 'slot'],
        'footer' => ['label' => 'Above the footer – all pages', 'group' => 'slot'],
        'download' => ['label' => 'Opens in a new tab when a visitor clicks “Download”', 'group' => 'link'],
    ];

    public const SIZES = [
        '728x90' => '728 × 90 – Leaderboard (desktop)',
        '468x60' => '468 × 60 – Banner (desktop/tablet)',
        '300x250' => '300 × 250 – Medium rectangle (all devices)',
        '320x50' => '320 × 50 – Mobile banner',
        '160x600' => '160 × 600 – Wide skyscraper',
        '160x300' => '160 × 300 – Half skyscraper',
        'responsive' => 'Responsive / other size',
    ];

    public const DEVICES = [
        'all' => 'All devices',
        'desktop' => 'Desktop & tablet',
        'mobile' => 'Mobile phones',
    ];

    public const PAGE_SCOPES = [
        'all' => 'All pages',
        'home' => 'Home page only',
        'tools' => 'All tool pages',
        'only' => 'Only the selected tools',
        'except' => 'All pages except the selected tools',
    ];

    /**
     * Where to get the code and where to put it – shown in the ad form.
     */
    public const HELP = [
        'adsterra' => [
            '*' => 'Adsterra dashboard → Websites → Add website. When the site is approved click “Get code” next to the ad unit and paste the complete code below.',
            'popunder' => 'Paste the Popunder <script> tag exactly as given. Recommended position: inside <head>. Adsterra limits how often a visitor sees it.',
            'social_bar' => 'Paste the Social Bar <script> tag. Recommended position: before </body>. Tip: exclude the PDF editor so the bar never covers its toolbar.',
            'native' => 'Paste the script and the <div id="container-…"> together, exactly as given. Choose a visible position such as “below the tool”.',
            'banner' => 'Copy the banner code (atOptions + invoke.js) for the size you created. Use 728×90 for desktop and 320×50 or 300×250 for mobile phones.',
            'direct_link' => 'Copy your Direct Link URL. It can open in a new tab when a visitor clicks “Download” (with a frequency limit) or appear as a sponsored button.',
        ],
        'monetag' => [
            '*' => 'Monetag dashboard → Sites → Add site and verify it (add the Monetag meta tag under SEO → Verification, or upload the sw.js file under Advertising → Settings). Then create a zone and copy its tag.',
            'multitag' => 'One MultiTag combines OnClick, Push, In-Page Push and Vignette. Put it in <head> and do not add those formats separately.',
            'popunder' => 'Paste the OnClick (Popunder) tag. Recommended position: inside <head>.',
            'push' => 'Paste the Push Notifications tag in <head> and paste the content of Monetag’s sw.js file in Advertising → Settings → Service worker.',
            'social_bar' => 'Paste the In-Page Push tag. Recommended position: inside <head> or before </body>.',
            'vignette' => 'Paste the Vignette Banner tag. Recommended position: inside <head>.',
            'direct_link' => 'Copy the Direct Link URL of your zone. It can open on “Download” clicks (with a frequency limit) or appear as a sponsored button.',
        ],
        'adsense' => [
            '*' => 'AdSense → Ads. Remember to add the AdSense line to ads.txt (Advertising → Settings).',
            'auto_ads' => 'Paste the Auto ads <script> into <head>. Google places the ads automatically.',
            'banner' => 'Create a display ad unit and paste the <ins> + <script> code.',
            'native' => 'Create an in-article or in-feed ad unit and paste the code.',
        ],
        'custom' => [
            '*' => 'Paste the HTML or JavaScript code from your ad network. Script tags are executed as entered.',
            'direct_link' => 'Enter the advertiser URL. It opens on “Download” clicks or is shown as a sponsored button.',
        ],
    ];

    public static function kind(string $format): string
    {
        return self::FORMATS[$format]['kind'] ?? 'any';
    }

    public static function formatLabel(string $format, ?string $network = null): string
    {
        return self::FORMAT_LABELS[$network][$format] ?? self::FORMATS[$format]['label'] ?? $format;
    }

    public static function placementGroup(string $placement): ?string
    {
        return self::PLACEMENTS[$placement]['group'] ?? null;
    }

    /**
     * Positions a format may use.
     *
     * @return list<string>
     */
    public static function placementsFor(string $format): array
    {
        $groups = match (self::kind($format)) {
            'script' => ['script'],
            'slot' => ['slot'],
            'link' => ['link', 'slot'],
            default => ['script', 'slot'],
        };

        return array_keys(array_filter(self::PLACEMENTS, fn ($placement) => in_array($placement['group'], $groups, true)));
    }

    public static function help(string $network, string $format): string
    {
        $help = self::HELP[$network] ?? self::HELP['custom'];

        return trim(($help['*'] ?? '').' '.($help[$format] ?? self::HELP['custom'][$format] ?? ''));
    }

    /**
     * Everything the admin form script needs.
     */
    public static function forClient(): array
    {
        $formats = [];

        foreach (self::FORMATS as $key => $format) {
            $formats[$key] = $format + ['placements' => self::placementsFor($key)];
        }

        $help = [];

        foreach (self::NETWORKS as $network => $definition) {
            foreach ($definition['formats'] as $format) {
                $help[$network][$format] = self::help($network, $format);
            }
        }

        return [
            'networks' => array_map(fn ($network) => ['formats' => $network['formats']], self::NETWORKS),
            'formats' => $formats,
            'labels' => self::FORMAT_LABELS,
            'help' => $help,
        ];
    }

    /**
     * Ready-made starting points shown on the ads page.
     *
     * @return list<array{network: string, format: string, label: string, size?: string, devices?: string}>
     */
    public static function presets(): array
    {
        return [
            ['network' => 'adsterra', 'format' => 'popunder', 'label' => 'Popunder'],
            ['network' => 'adsterra', 'format' => 'social_bar', 'label' => 'Social Bar'],
            ['network' => 'adsterra', 'format' => 'native', 'label' => 'Native Banner'],
            ['network' => 'adsterra', 'format' => 'banner', 'label' => 'Banner 728×90', 'size' => '728x90', 'devices' => 'desktop'],
            ['network' => 'adsterra', 'format' => 'banner', 'label' => 'Banner 320×50', 'size' => '320x50', 'devices' => 'mobile'],
            ['network' => 'adsterra', 'format' => 'banner', 'label' => 'Banner 300×250', 'size' => '300x250'],
            ['network' => 'adsterra', 'format' => 'direct_link', 'label' => 'Direct Link'],
            ['network' => 'monetag', 'format' => 'multitag', 'label' => 'MultiTag'],
            ['network' => 'monetag', 'format' => 'popunder', 'label' => 'OnClick (Popunder)'],
            ['network' => 'monetag', 'format' => 'push', 'label' => 'Push Notifications'],
            ['network' => 'monetag', 'format' => 'social_bar', 'label' => 'In-Page Push'],
            ['network' => 'monetag', 'format' => 'vignette', 'label' => 'Vignette Banner'],
            ['network' => 'monetag', 'format' => 'direct_link', 'label' => 'Direct Link'],
        ];
    }
}
