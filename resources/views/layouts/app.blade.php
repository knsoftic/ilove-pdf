<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">

    @include('partials.seo', ['seo' => $seo ?? null])

    @if ($site['favicon'] ?? null)
        <link rel="icon" href="{{ asset($site['favicon']) }}">
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    {{-- Apply the colour theme before first paint (no flash). --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.head-tags')
    <x-ad position="head" />

    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-slate-50 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200" data-page="@yield('page', 'default')">
    @if ($gtm = setting('gtm_id'))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    <x-ad position="body_start" />

    @if (app(\App\Services\Ads\AdManager::class)->previewing())
        <form method="POST" action="{{ route('admin.ads.preview') }}" class="fixed right-4 bottom-4 z-[80] flex items-center gap-3 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg">
            @csrf
            Ad position preview is on
            <button type="submit" class="rounded-lg bg-white/20 px-2.5 py-1 hover:bg-white/30">Turn off</button>
        </form>
    @endif

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

    @include('partials.header')

    @unless (View::hasSection('fullscreen') || View::hasSection('noads'))
        <x-ad position="header" />
    @endunless

    <main id="main" class="flex-1 @hasSection('fullscreen') flex flex-col @endif">
        @yield('content')
    </main>

    @unless (View::hasSection('fullscreen'))
        @unless (View::hasSection('noads'))
            <x-ad position="footer" />
        @endunless
        @include('partials.footer')
    @endunless

    <div id="toasts" class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4" aria-live="polite"></div>

    @php
        $clientConfig = [
            'base' => rtrim(url('/'), '/'),
            'csrf' => csrf_token(),
            'vendor' => asset('vendor'),
            'siteName' => $site['name'] ?? 'PDF Toolkit',
            'routes' => [
                'exportText' => route('api.export.text'),
                'translate' => route('api.translate'),
                'detect' => route('api.translate.detect'),
                'languages' => route('api.translate.languages'),
            ],
            'ocr' => [
                'langPath' => config('pdftoolkit.ocr.browser_lang_path'),
                'languages' => config('pdftoolkit.ocr.languages'),
            ],
            'ads' => ['downloadLinks' => app(\App\Services\Ads\AdManager::class)->downloadLinks()],
        ];
    @endphp
    <script>window.PDFTK = @json($clientConfig);</script>
    @stack('scripts')

    <x-ad position="body_end" />
    @if ($site['body_code'] ?? null)
        {!! $site['body_code'] !!}
    @endif
</body>
</html>
