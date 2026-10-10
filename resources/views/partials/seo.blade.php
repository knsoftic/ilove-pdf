@php
    $siteName = $site['name'] ?? config('app.name');
    $title = $seo?->title ?: $siteName;
    $description = $seo?->description ?: '';
    $robots = setting('seo_noindex_site') ? 'noindex,nofollow' : ($seo?->robots ?? 'index,follow');
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
@if ($seo?->keywords)
    <meta name="keywords" content="{{ $seo->keywords }}">
@endif
<meta name="robots" content="{{ $robots }}">
@if ($seo?->canonical)
    <link rel="canonical" href="{{ $seo->canonical }}">
@endif

{{-- Open Graph --}}
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $seo?->type ?? 'website' }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
@if ($seo?->canonical)
    <meta property="og:url" content="{{ $seo->canonical }}">
@endif
@if ($seo?->image)
    <meta property="og:image" content="{{ $seo->image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
@endif
<meta property="og:locale" content="{{ setting('og_locale', 'en_US') }}">

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if ($seo?->image)
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif
@if ($site['twitter'] ?? null)
    <meta name="twitter:site" content="{{ $site['twitter'] }}">
@endif

@if ($seo && ($seo->schemas || count($seo->breadcrumbs) > 1))
    <script type="application/ld+json">{!! $seo->jsonLd() !!}</script>
@endif
