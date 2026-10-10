@php
    $runsInBrowser = ($engines[0] ?? null) === 'browser';
@endphp
<section class="relative overflow-hidden border-b border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900" style="--tool-color: {{ $color }}">
    <div class="pointer-events-none absolute inset-0 opacity-60" style="background: radial-gradient(40rem 16rem at 50% -20%, color-mix(in srgb, {{ $color }} 18%, transparent), transparent)"></div>
    <div class="container-page relative py-8 sm:py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500 dark:text-slate-400">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="{{ route('home') }}" class="hover:text-brand-600">Home</a></li>
                @if ($category)
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('tools.index') }}#{{ $tool->category() }}" class="hover:text-brand-600">{{ $category['name'] }}</a></li>
                @endif
                <li aria-hidden="true">/</li>
                <li class="text-slate-700 dark:text-slate-300" aria-current="page">{{ $tool->name() }}</li>
            </ol>
        </nav>
        <div class="mt-5 flex flex-col items-center text-center">
            <span class="tool-icon size-14 shadow-sm"><x-icon :name="$tool->icon()" class="size-7" /></span>
            <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ $tool->heading() }}</h1>
            <p class="mt-3 max-w-2xl text-base text-slate-600 sm:text-lg dark:text-slate-300">{{ $tool->description() }}</p>
            @if ($engines !== [])
                <p class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs">
                    @if ($runsInBrowser)
                        <span class="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><x-icon name="shield-check" class="size-3.5" /> Runs in your browser</span>
                    @else
                        <span class="badge bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"><x-icon name="server" class="size-3.5" /> Secure server processing</span>
                    @endif
                    <span class="badge bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="heart" class="size-3.5" /> Free · No sign-up</span>
                </p>
            @endif
        </div>
    </div>
</section>
