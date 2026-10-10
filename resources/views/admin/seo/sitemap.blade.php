@extends('layouts.admin')

@section('title', 'SEO · Sitemap & robots')

@section('content')
    @include('admin.seo._tabs')

    <div class="mx-auto max-w-4xl space-y-6">
        <form method="POST" action="{{ route('admin.seo.sitemap.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">XML sitemap</h2>
                        <p class="mt-1 text-sm text-slate-500">Generated automatically: enabled tools, home, all tools{{ $s['sitemap_pages'] ? ' and the legal pages' : '' }}. Pages set to noindex are left out.</p>
                    </div>
                    <a href="{{ route('sitemap') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="external" class="size-4" /> Open sitemap.xml</a>
                </div>
                <div class="mt-5 grid gap-4">
                    <x-admin.toggle name="sitemap_pages" label="Include About, Privacy policy and Terms" :checked="old('sitemap_pages', $s['sitemap_pages'])" />
                </div>
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-brand-600 dark:text-brand-400">{{ count($urls) }} URLs in the sitemap</summary>
                    <ul class="mt-2 max-h-64 space-y-1 overflow-auto rounded-xl bg-slate-50 p-3 font-mono text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                        @foreach ($urls as $url)
                            <li class="truncate">{{ $url['loc'] }}</li>
                        @endforeach
                    </ul>
                </details>
            </section>

            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">robots.txt</h2>
                    <a href="{{ route('robots') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="external" class="size-4" /> Open robots.txt</a>
                </div>
                <div class="mt-5 grid gap-5">
                    <x-admin.toggle name="seo_noindex_site" label="Block all search engines (site under construction)" hint="Adds “Disallow: /” and noindex on every page." :checked="old('seo_noindex_site', $s['seo_noindex_site'])" />
                    <div>
                        <label class="label" for="robots_extra">Additional rules</label>
                        <textarea id="robots_extra" name="robots_extra" rows="6" class="input font-mono text-xs" placeholder="# Example: block an AI crawler&#10;User-agent: GPTBot&#10;Disallow: /">{{ old('robots_extra', $s['robots_extra']) }}</textarea>
                        <p class="help">Appended after the default rules. The sitemap line is added automatically.</p>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save</button>
            </div>
        </form>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">IndexNow – instant indexing</h2>
            <p class="mt-1 text-sm text-slate-500">Free protocol supported by Bing, Yandex, Seznam and Naver. Submit your URLs after adding tools or changing their SEO texts.</p>

            @error('indexnow')
                <p class="mt-3 flex items-center gap-2 text-sm font-medium text-rose-600"><x-icon name="alert" class="size-4" /> {{ $message }}</p>
            @enderror

            <div class="mt-5 flex flex-wrap items-center gap-3">
                @if ($indexNow->key())
                    <code class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs break-all dark:bg-slate-800">{{ $indexNow->keyUrl() }}</code>
                    <form method="POST" action="{{ route('admin.seo.indexnow.submit') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm" @disabled(! $indexNow->isPublicHost())><x-icon name="zap" class="size-4" /> Submit {{ count($urls) }} URLs</button>
                    </form>
                    <form method="POST" action="{{ route('admin.seo.indexnow.key') }}" data-confirm="Create a new key? The old key file stops working.">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="refresh" class="size-4" /> New key</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.seo.indexnow.key') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Create IndexNow key</button>
                    </form>
                @endif
            </div>
            @unless ($indexNow->isPublicHost())
                <p class="help mt-3">Available once the site runs on its public domain (APP_URL is currently {{ config('app.url') }}).</p>
            @endunless
        </section>
    </div>
@endsection
