@extends('layouts.admin')

@section('title', 'SEO · General')

@section('content')
    @include('admin.seo._tabs')

    <form method="POST" action="{{ route('admin.seo.general.update') }}" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Page titles</h2>
            <p class="mt-1 text-sm text-slate-500">Example: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $preview }}</span></p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="title_separator">Separator between page title and site name</label>
                    <select id="title_separator" name="title_separator" class="input">
                        @foreach ($separators as $separator)
                            <option value="{{ $separator }}" @selected(old('title_separator', $s['title_separator']) === $separator)>{{ $separator }}</option>
                        @endforeach
                    </select>
                </div>
                <x-admin.toggle class="self-end" name="title_append_site" label="Add the site name to titles" hint="“Merge PDF {{ $s['title_separator'] }} {{ $s['site_name'] }}”" :checked="old('title_append_site', $s['title_append_site'])" />
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Social media &amp; structured data</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="og_locale">Content language (og:locale)</label>
                    <select id="og_locale" name="og_locale" class="input">
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected(old('og_locale', $s['og_locale']) === $code)>{{ $label }} – {{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="twitter_handle">X / Twitter handle</label>
                    <input id="twitter_handle" name="twitter_handle" class="input" value="{{ old('twitter_handle', $s['twitter_handle']) }}" placeholder="@yourbrand" maxlength="40">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="social_profiles">Social profiles (one URL per line)</label>
                    <textarea id="social_profiles" name="social_profiles" rows="4" class="input font-mono text-xs" placeholder="https://facebook.com/yourpage&#10;https://x.com/yourbrand&#10;https://youtube.com/@yourchannel">{{ old('social_profiles', implode("\n", (array) $s['social_profiles'])) }}</textarea>
                    <p class="help">Added to the Organization schema (“sameAs”) so Google can connect your profiles with the website.</p>
                </div>
            </div>
            <p class="mt-4 text-sm text-slate-500">The logo and the social sharing image (1200 × 630) are uploaded in <a href="{{ route('admin.settings') }}" class="font-medium text-brand-600 dark:text-brand-400">Settings → Branding</a>.</p>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Search engine visibility</h2>
            <x-admin.toggle class="mt-4" name="seo_noindex_site" label="Discourage search engines from indexing this site" hint="Use while the site is under construction. Adds noindex to every page and blocks crawlers in robots.txt." :checked="old('seo_noindex_site', $s['seo_noindex_site'])" />
            @if ($s['seo_noindex_site'])
                <p class="mt-3 flex items-center gap-2 text-sm font-medium text-rose-600"><x-icon name="alert" class="size-4" /> Search engines are currently blocked.</p>
            @endif
        </section>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save</button>
        </div>
    </form>
@endsection
