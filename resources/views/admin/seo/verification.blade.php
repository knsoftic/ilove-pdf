@extends('layouts.admin')

@section('title', 'SEO · Verification & analytics')

@section('content')
    @include('admin.seo._tabs')

    <form method="POST" action="{{ route('admin.seo.verification.update') }}" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Site verification</h2>
            <p class="mt-1 text-sm text-slate-500">Paste the whole meta tag or only the code inside <code class="text-xs">content="…"</code>. The tag is added to every page.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach ($services as $key => [$label, $meta])
                    <div>
                        <label class="label" for="verification-{{ $key }}">{{ $label }}</label>
                        <input id="verification-{{ $key }}" name="verification[{{ $key }}]" class="input font-mono text-xs" value="{{ old('verification.'.$key, $s['verification'][$key] ?? '') }}" maxlength="500" placeholder="&lt;meta name=&quot;{{ $meta }}&quot; content=&quot;…&quot;&gt;">
                    </div>
                @endforeach
            </div>
            <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <p class="font-medium text-slate-900 dark:text-white">After verifying</p>
                <ol class="mt-1 list-decimal space-y-1 pl-5">
                    <li>Google Search Console → Sitemaps → submit <code class="text-xs">{{ route('sitemap') }}</code></li>
                    <li>Bing Webmaster Tools → Sitemaps → submit the same URL (or use IndexNow under “Sitemap &amp; robots”).</li>
                </ol>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Analytics</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="ga4_id">Google Analytics 4 – Measurement ID</label>
                    <input id="ga4_id" name="ga4_id" class="input font-mono" value="{{ old('ga4_id', $s['ga4_id']) }}" placeholder="G-XXXXXXXXXX" maxlength="30">
                </div>
                <div>
                    <label class="label" for="gtm_id">Google Tag Manager – Container ID</label>
                    <input id="gtm_id" name="gtm_id" class="input font-mono" value="{{ old('gtm_id', $s['gtm_id']) }}" placeholder="GTM-XXXXXXX" maxlength="20">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="analytics_code">Other analytics code (Plausible, Matomo, Clarity…)</label>
                    <textarea id="analytics_code" name="analytics_code" rows="4" class="input font-mono text-xs" placeholder="&lt;script&gt;…&lt;/script&gt;">{{ old('analytics_code', $s['analytics_code']) }}</textarea>
                </div>
            </div>
            <p class="mt-4 text-sm text-slate-500">No tracking is active unless you add it here. Mention analytics and cookies in your <a href="{{ route('admin.content') }}" class="font-medium text-brand-600 dark:text-brand-400">privacy policy</a>.</p>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Custom code</h2>
            <div class="mt-5 grid gap-5">
                <div>
                    <label class="label" for="head_code">Inside &lt;head&gt;</label>
                    <textarea id="head_code" name="head_code" rows="4" class="input font-mono text-xs">{{ old('head_code', $s['head_code']) }}</textarea>
                </div>
                <div>
                    <label class="label" for="body_code">Before &lt;/body&gt;</label>
                    <textarea id="body_code" name="body_code" rows="4" class="input font-mono text-xs">{{ old('body_code', $s['body_code']) }}</textarea>
                </div>
            </div>
            <p class="help mt-3">Ad network code belongs in <a href="{{ route('admin.ads') }}" class="font-medium text-brand-600 dark:text-brand-400">Advertising</a>, where it can be targeted to pages and devices.</p>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save</button>
        </div>
    </form>
@endsection
