@extends('layouts.admin')

@section('title', 'SEO · '.$tool->name())

@section('content')
    @include('admin.seo._tabs')

    @php
        $faq = old('faq', array_map(fn ($item) => ['q' => $item[0], 'a' => $item[1]], $override['custom_faq'] ?? []));
        $score = $audit['score'];
        $tone = $score >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($score >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400');
        $defaultFaq = collect($defaults->faq())->pluck(0);
        $outline = "## What is {$tool->name()}?\n\n\n\n## How does {$tool->name()} work?\n\n\n\n## Why use our free {$tool->name()} tool?\n\n- \n- \n- \n\n## Is it safe to use?\n\n";
    @endphp

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.seo.tools') }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" /> All tool pages</a>
        <a href="{{ $tool->url() }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm ml-auto"><x-icon name="external" class="size-4" /> View page</a>
    </div>

    <form method="POST" action="{{ route('admin.seo.tool.update', $tool->slug) }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]" data-seo-form data-title-template="{{ $siteTitle }}">
        @csrf
        @method('PUT')

        <div class="min-w-0 space-y-6">
            <section class="card p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Search result</h2>
                <p class="mt-1 text-sm text-slate-500">Leave a field empty to use the default shown in grey.</p>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label class="label" for="focus">Focus keyword</label>
                        <input id="focus" name="focus" class="input" value="{{ old('focus', $override['focus'] ?? '') }}" maxlength="80" placeholder="e.g. merge pdf" data-seo-focus>
                        <p class="help">The main search phrase this page should rank for. Use it in the title, description, heading and article.</p>
                    </div>
                    <div>
                        <label class="label" for="title">SEO title</label>
                        <input id="title" name="title" class="input" value="{{ old('title', $override['title'] ?? '') }}" maxlength="120" placeholder="{{ $defaults->seoTitle() }}" data-count="30-60" data-serp="title">
                        <p class="help">Shown as the blue link in Google. The site name is added automatically (SEO → General).</p>
                    </div>
                    <div>
                        <label class="label" for="meta">Meta description</label>
                        <textarea id="meta" name="meta" rows="3" class="input" maxlength="320" placeholder="{{ $defaults->metaDescription() }}" data-count="120-160" data-serp="description">{{ old('meta', $override['meta'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="label" for="keywords">Meta keywords</label>
                        <input id="keywords" name="keywords" class="input" value="{{ old('keywords', $override['keywords'] ?? '') }}" maxlength="400" placeholder="{{ $defaults->keywords() }}">
                        <p class="help">Comma separated. Google ignores this tag, some other search engines still read it.</p>
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Page content</h2>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label class="label" for="heading">H1 heading</label>
                        <input id="heading" name="heading" class="input" value="{{ old('heading', $override['heading'] ?? '') }}" maxlength="120" placeholder="{{ $defaults->heading() }}" data-count="10-70" data-seo-heading>
                    </div>
                    <div>
                        <label class="label" for="description">Short description</label>
                        <input id="description" name="description" class="input" value="{{ old('description', $override['description'] ?? '') }}" maxlength="200" placeholder="{{ $defaults->description() }}" data-count="50-160">
                        <p class="help">Shown under the heading and on the tool cards.</p>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <label class="label mb-0" for="article">Article below the tool <span class="font-normal text-slate-400">(Markdown)</span></label>
                            <button type="button" class="btn btn-ghost btn-sm" data-insert-outline="{{ $outline }}"><x-icon name="plus" class="size-3.5" /> Insert outline</button>
                        </div>
                        <textarea id="article" name="article" rows="16" class="input mt-1.5 font-mono text-[13px] leading-6" maxlength="60000" placeholder="## What is {{ $tool->name() }}?&#10;&#10;Write a helpful text about this tool…" data-words>{{ old('article', $override['article'] ?? '') }}</textarea>
                        <p class="help">Use ## for headings, **bold**, - lists and [links](/merge-pdf). 300–1000 words of original, helpful text help the page rank. HTML is removed.</p>
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">FAQ questions</h2>
                        <p class="mt-1 text-sm text-slate-500">Shown on the page and as FAQ structured data for Google.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-faq-add><x-icon name="plus" class="size-4" /> Add question</button>
                </div>

                <div class="mt-5 space-y-3" data-faq-list>
                    @foreach ($faq as $i => $item)
                        @include('admin.seo._faq-row', ['i' => $i, 'q' => $item['q'] ?? '', 'a' => $item['a'] ?? ''])
                    @endforeach
                </div>
                <template data-faq-template>
                    @include('admin.seo._faq-row', ['i' => '__INDEX__', 'q' => '', 'a' => ''])
                </template>

                <x-admin.toggle class="mt-4" name="faq_replace" label="Replace the default questions" hint="Off: your questions are shown first, followed by the default ones." :checked="old('faq_replace', $override['faq_replace'] ?? false)" />

                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">Default questions ({{ $defaultFaq->count() }})</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-500">
                        @foreach ($defaultFaq as $question)
                            <li>{{ $question }}</li>
                        @endforeach
                    </ul>
                </details>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Indexing</h2>
                <x-admin.toggle class="mt-4" name="noindex" label="Hide this page from search engines (noindex)" hint="The page keeps working but is removed from Google and the sitemap." :checked="old('noindex', $override['noindex'] ?? false)" />
            </section>

            <div class="sticky bottom-4 z-10 flex justify-end">
                <button type="submit" class="btn btn-primary btn-lg shadow-lg"><x-icon name="check" class="size-5" /> Save SEO</button>
            </div>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="card p-5" aria-labelledby="serp-title">
                <h2 id="serp-title" class="text-sm font-semibold text-slate-900 dark:text-white">Google preview</h2>
                <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4 font-[Arial,sans-serif] dark:border-slate-700">
                    <p class="truncate text-xs text-[#202124]">{{ parse_url(url('/'), PHP_URL_HOST) }} <span class="text-[#5f6368]">› {{ $tool->slug }}</span></p>
                    <p class="mt-1 line-clamp-2 text-lg leading-6 text-[#1a0dab]" data-serp-title data-default="{{ $defaults->seoTitle() }}">{{ str_replace('%s', $tool->seoTitle(), $siteTitle) }}</p>
                    <p class="mt-1 line-clamp-2 text-sm leading-5 text-[#4d5156]" data-serp-description data-default="{{ $defaults->metaDescription() }}">{{ $tool->metaDescription() }}</p>
                </div>
                <p class="help">Google may shorten long titles (≈ 60 characters) and descriptions (≈ 160).</p>
            </section>

            <section class="card p-5" aria-labelledby="checks-title">
                <div class="flex items-baseline justify-between">
                    <h2 id="checks-title" class="text-sm font-semibold text-slate-900 dark:text-white">SEO score</h2>
                    <p class="text-2xl font-extrabold tabular-nums {{ $tone }}">{{ $score }}<span class="text-sm font-medium text-slate-400">/100</span></p>
                </div>
                <ul class="mt-3 space-y-2">
                    @foreach ($audit['checks'] as $check)
                        <li class="flex items-start gap-2 text-sm">
                            <x-icon :name="$check['ok'] ? 'check-circle' : 'alert'" @class(['mt-0.5 size-4 shrink-0', 'text-emerald-600 dark:text-emerald-400' => $check['ok'], 'text-amber-500' => ! $check['ok']]) />
                            <span class="{{ $check['ok'] ? 'text-slate-600 dark:text-slate-300' : 'text-slate-900 dark:text-white' }}">{{ $check['label'] }}<span class="sr-only">{{ $check['ok'] ? ' – passed' : ' – to do' }}</span></span>
                        </li>
                    @endforeach
                </ul>
                <p class="help mt-3">The score is updated after saving.</p>
            </section>
        </aside>
    </form>
@endsection
