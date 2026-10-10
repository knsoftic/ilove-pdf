@extends('layouts.admin')

@section('title', 'Tools')

@section('content')
    <form method="POST" action="{{ route('admin.tools.update') }}" class="mx-auto max-w-5xl space-y-6">
        @csrf
        @method('PUT')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600 dark:text-slate-400">Enable or disable tools and rename them. Disabled tools disappear from the site, the sitemap and the API. Titles, descriptions, articles and FAQs are edited under <a href="{{ route('admin.seo.tools') }}" class="font-medium text-brand-600 dark:text-brand-400">SEO → Tool pages</a>.</p>
            <div class="relative w-full sm:w-72">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" class="input pl-9" placeholder="Filter tools…" data-admin-tool-filter aria-label="Filter tools">
            </div>
        </div>

        @foreach ($categories as $key => $category)
            @continue(! isset($tools[$key]))
            <section class="card overflow-hidden" data-admin-tool-group>
                <h2 class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm font-semibold text-slate-900 dark:border-slate-800 dark:bg-slate-900/60 dark:text-white">{{ $category['name'] }}</h2>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($tools[$key] as $tool)
                        @php
                            $engines = $registry->enginesFor($tool);
                            $override = $overrides[$tool->slug] ?? [];
                        @endphp
                        <li class="px-5 py-3" data-admin-tool="{{ strtolower($tool->name().' '.$tool->slug) }}">
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="checkbox" name="enabled[]" value="{{ $tool->slug }}" class="toggle" @checked($tool->isEnabled()) aria-label="Enable {{ $tool->name() }}">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $tool->name() }} <a href="{{ $tool->url() }}" target="_blank" rel="noopener" class="ml-1 text-xs font-normal text-slate-400 hover:text-brand-600">/{{ $tool->slug }}</a></p>
                                    <p class="text-xs text-slate-500">
                                        @if ($engines === [])
                                            <span class="text-amber-600">Unavailable – missing server software</span>
                                        @else
                                            Engines: {{ implode(' → ', array_map(fn ($e) => $e === 'browser' ? 'browser' : 'server', $engines)) }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex w-full items-center gap-2 sm:w-auto">
                                    <input class="input h-9 py-1.5 text-sm sm:w-56" name="names[{{ $tool->slug }}]" value="{{ $override['name'] ?? '' }}" placeholder="Name: {{ config('tools.tools.'.$tool->slug.'.name') }}" maxlength="80" aria-label="Custom name for {{ $tool->name() }}">
                                    <a href="{{ route('admin.seo.tool', $tool->slug) }}" class="btn btn-secondary btn-sm shrink-0"><x-icon name="search" class="size-4" /> SEO</a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <div class="sticky bottom-4 flex justify-end">
            <button type="submit" class="btn btn-primary btn-lg shadow-lg"><x-icon name="check" class="size-5" /> Save tools</button>
        </div>
    </form>
@endsection
