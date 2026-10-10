@php
    $navItems = collect([
        ['label' => 'Home', 'url' => route('home'), 'active' => request()->routeIs('home')],
    ])->merge($navTools->map(fn ($tool) => [
        'label' => $tool->name(),
        'url' => $tool->url(),
        'active' => request()->route('tool') === $tool->slug,
    ]))->values();
@endphp
<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur-lg dark:border-slate-800 dark:bg-slate-950/85">
    <div class="container-page flex h-16 items-center gap-4">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 font-bold text-slate-900 dark:text-white" aria-label="{{ $site['name'] }} home">
            @if ($site['logo'])
                <img src="{{ asset($site['logo']) }}" alt="{{ $site['name'] }}" class="h-8 w-auto">
            @else
                <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-rose-500 to-brand-600 text-white shadow-sm">
                    <x-icon name="logo" class="size-5" :stroke="2.2" />
                </span>
                <span class="text-lg tracking-tight">{{ $site['name'] }}</span>
            @endif
        </a>

        <nav class="ml-4 hidden flex-1 items-center gap-0.5 xl:flex" aria-label="Main">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}" @class([
                    'rounded-lg px-2.5 py-2 text-sm font-medium whitespace-nowrap transition',
                    'text-brand-700 bg-brand-50 dark:bg-brand-500/10 dark:text-brand-300' => $item['active'],
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $item['active'],
                ])>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-1.5">
            <a href="{{ route('tools.index') }}" class="btn btn-primary hidden sm:inline-flex">
                <x-icon name="grid" class="size-4" />
                All Tools
            </a>
            <button type="button" class="btn-icon" data-theme-toggle aria-label="Toggle dark mode">
                <x-icon name="moon" class="size-5 dark:hidden" />
                <x-icon name="sun" class="hidden size-5 dark:block" />
            </button>
            <button type="button" class="btn-icon xl:hidden" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu">
                <x-icon name="menu" class="size-6" />
            </button>
        </div>
    </div>

    {{-- Mobile navigation --}}
    <div id="mobile-nav" class="hidden border-t border-slate-200 bg-white xl:hidden dark:border-slate-800 dark:bg-slate-950" data-nav-panel>
        <nav class="container-page grid grid-cols-2 gap-1 py-3 sm:grid-cols-3" aria-label="Mobile">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}" @class([
                    'rounded-lg px-3 py-2.5 text-sm font-medium',
                    'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => $item['active'],
                    'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! $item['active'],
                ])>{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ route('tools.index') }}" class="col-span-2 mt-1 btn btn-primary sm:col-span-3">
                <x-icon name="grid" class="size-4" /> All Tools
            </a>
        </nav>
    </div>
</header>
