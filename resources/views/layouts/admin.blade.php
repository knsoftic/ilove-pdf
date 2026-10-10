<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') · {{ setting('site_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>
        try { if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200" data-page="admin">
    @php
        $nav = [
            ['admin.dashboard', 'bar-chart', 'Dashboard'],
            ['admin.settings', 'settings', 'Settings'],
            ['admin.tools', 'grid', 'Tools'],
            ['admin.seo', 'trending-up', 'SEO'],
            ['admin.ads', 'megaphone', 'Advertising'],
            ['admin.content', 'file-text', 'Pages'],
            ['admin.system', 'server', 'System'],
            ['admin.account', 'user', 'Account'],
        ];
    @endphp
    <div class="flex min-h-full">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex dark:border-slate-800 dark:bg-slate-900" data-admin-sidebar>
            <div class="flex h-16 items-center gap-2.5 border-b border-slate-200 px-5 dark:border-slate-800">
                <span class="flex size-8 items-center justify-center rounded-lg bg-linear-to-br from-rose-500 to-brand-600 text-white"><x-icon name="logo" class="size-4" :stroke="2.2" /></span>
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ setting('site_name') }}</p>
                    <p class="text-xs text-slate-500">Admin panel</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 p-3" aria-label="Admin">
                @foreach ($nav as [$route, $icon, $label])
                    <a href="{{ route($route) }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => request()->routeIs($route, $route.'.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => ! request()->routeIs($route, $route.'.*'),
                    ])>
                        <x-icon :name="$icon" class="size-5" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="space-y-1 border-t border-slate-200 p-3 dark:border-slate-800">
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><x-icon name="external" class="size-5" /> View website</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-rose-600 dark:text-slate-300 dark:hover:bg-slate-800"><x-icon name="log-out" class="size-5" /> Sign out</button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/90">
                <button type="button" class="btn-icon lg:hidden" data-admin-menu aria-label="Open menu"><x-icon name="menu" class="size-6" /></button>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">@yield('title')</h1>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" class="btn-icon" data-theme-toggle aria-label="Toggle dark mode"><x-icon name="moon" class="size-5 dark:hidden" /><x-icon name="sun" class="hidden size-5 dark:block" /></button>
                    <span class="hidden text-sm text-slate-500 sm:inline">{{ auth('admin')->user()?->name }}</span>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @if (session('status'))
                    <div class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" role="status">
                        <x-icon name="check-circle" class="size-5" /> {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
                        <p class="font-semibold">Please correct the following:</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <div id="toasts" class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4"></div>
</body>
</html>
