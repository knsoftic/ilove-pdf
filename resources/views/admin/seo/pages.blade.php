@extends('layouts.admin')

@section('title', 'SEO · Pages')

@section('content')
    @include('admin.seo._tabs')

    <form method="POST" action="{{ route('admin.seo.pages.update') }}" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Home page</h2>
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="text-sm text-brand-600 dark:text-brand-400">View</a>
            </div>
            <div class="mt-5 grid gap-5">
                <div>
                    <label class="label" for="seo_title">SEO title</label>
                    <input id="seo_title" name="seo_title" class="input" value="{{ old('seo_title', $s['seo_title']) }}" required maxlength="120" data-count="30-60">
                    <p class="help">Used exactly as written (the site name is not added on the home page).</p>
                </div>
                <div>
                    <label class="label" for="seo_description">Meta description</label>
                    <textarea id="seo_description" name="seo_description" rows="3" class="input" required maxlength="320" data-count="120-160">{{ old('seo_description', $s['seo_description']) }}</textarea>
                </div>
                <div>
                    <label class="label" for="seo_keywords">Keywords</label>
                    <input id="seo_keywords" name="seo_keywords" class="input" value="{{ old('seo_keywords', $s['seo_keywords']) }}" maxlength="500">
                </div>
            </div>
        </section>

        <section class="card p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">All tools page</h2>
                <a href="{{ route('tools.index') }}" target="_blank" rel="noopener" class="text-sm text-brand-600 dark:text-brand-400">View</a>
            </div>
            <div class="mt-5 grid gap-5">
                <div>
                    <label class="label" for="seo_tools_title">SEO title</label>
                    <input id="seo_tools_title" name="seo_tools_title" class="input" value="{{ old('seo_tools_title', $s['seo_tools_title']) }}" maxlength="120" placeholder="All PDF Tools – {count} Free Online PDF Tools" data-count="30-60">
                    <p class="help"><code>{count}</code> is replaced with the number of enabled tools.</p>
                </div>
                <div>
                    <label class="label" for="seo_tools_description">Meta description</label>
                    <textarea id="seo_tools_description" name="seo_tools_description" rows="2" class="input" maxlength="320" placeholder="Every free PDF tool in one place: convert, edit, sign, merge, split, compress, protect, OCR and translate PDF files online. No registration required." data-count="120-160">{{ old('seo_tools_description', $s['seo_tools_description']) }}</textarea>
                </div>
            </div>
        </section>

        @foreach ($pages as $page => [$heading, $description])
            @php $values = $s['seo_pages'][$page] ?? []; @endphp
            <section class="card p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $heading }}</h2>
                    <a href="{{ route('pages.'.$page) }}" target="_blank" rel="noopener" class="text-sm text-brand-600 dark:text-brand-400">View</a>
                </div>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label class="label" for="page-{{ $page }}-title">SEO title</label>
                        <input id="page-{{ $page }}-title" name="pages[{{ $page }}][title]" class="input" value="{{ old('pages.'.$page.'.title', $values['title'] ?? '') }}" maxlength="120" placeholder="{{ $heading }}">
                    </div>
                    <div>
                        <label class="label" for="page-{{ $page }}-description">Meta description</label>
                        <textarea id="page-{{ $page }}-description" name="pages[{{ $page }}][description]" rows="2" class="input" maxlength="320" placeholder="{{ $description }}">{{ old('pages.'.$page.'.description', $values['description'] ?? '') }}</textarea>
                    </div>
                    <x-admin.toggle name="pages[{{ $page }}][noindex]" label="Hide from search engines (noindex)" :checked="old('pages.'.$page.'.noindex', $values['noindex'] ?? false)" />
                </div>
            </section>
        @endforeach

        <p class="text-sm text-slate-500">The text of these pages is edited under <a href="{{ route('admin.content') }}" class="font-medium text-brand-600 dark:text-brand-400">Pages</a>. Tool pages have their own SEO editor under <a href="{{ route('admin.seo.tools') }}" class="font-medium text-brand-600 dark:text-brand-400">Tool pages</a>.</p>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save</button>
        </div>
    </form>
@endsection
