@extends('layouts.admin')

@section('title', 'Pages')

@section('content')
    <form method="POST" action="{{ route('admin.content.update') }}" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-600 dark:text-slate-400">Write in <strong>Markdown</strong> (# headings, **bold**, - lists, [links](https://example.com)). HTML is removed for safety. Leave a page empty to use the built-in default text shown as placeholder.</p>

        @foreach (['privacy' => ['Privacy policy', 'pages.privacy'], 'terms' => ['Terms of use', 'pages.terms'], 'about' => ['About', 'pages.about']] as $page => [$label, $route])
            <section class="card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $label }}</h2>
                    <a href="{{ route($route) }}" target="_blank" rel="noopener" class="text-sm text-brand-600 dark:text-brand-400">View page</a>
                </div>
                <textarea name="page_{{ $page }}" rows="14" class="input mt-4 font-mono text-[13px]" placeholder="{{ $defaults[$page] }}">{{ old('page_'.$page, $s['page_'.$page]) }}</textarea>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save pages</button>
        </div>
    </form>
@endsection
