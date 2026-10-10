@php
    $tabs = [
        ['admin.seo', 'Overview', []],
        ['admin.seo.general', 'General', []],
        ['admin.seo.verification', 'Verification & analytics', []],
        ['admin.seo.pages', 'Pages', []],
        ['admin.seo.tools', 'Tool pages', ['admin.seo.tool']],
        ['admin.seo.redirects', 'Redirects & 404', []],
        ['admin.seo.sitemap', 'Sitemap & robots', []],
    ];
@endphp
<nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:overflow-visible sm:px-0" aria-label="SEO sections">
    <div class="segmented flex-nowrap whitespace-nowrap sm:flex-wrap">
        @foreach ($tabs as [$route, $label, $also])
            <a href="{{ route($route) }}" @class([
                'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' => request()->routeIs($route, ...$also),
                'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white' => ! request()->routeIs($route, ...$also),
            ]) @if (request()->routeIs($route, ...$also)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </div>
</nav>
