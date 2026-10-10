<?php

namespace App\Services\Seo;

/**
 * Meta data for one page: title, description, canonical URL, Open Graph /
 * Twitter cards and Schema.org structured data.
 */
final class SeoData
{
    public const INDEX = 'index,follow,max-image-preview:large';

    /**
     * @param  list<array>  $schemas  JSON-LD objects
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $keywords = null,
        public string $type = 'website',
        public ?string $image = null,
        public string $robots = self::INDEX,
        public array $schemas = [],
        public array $breadcrumbs = [],
    ) {}

    public function jsonLd(): string
    {
        $graph = $this->schemas;

        if (count($this->breadcrumbs) > 1) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ], $this->breadcrumbs, array_keys($this->breadcrumbs)),
            ];
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
