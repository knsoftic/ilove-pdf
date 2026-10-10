@extends('layouts.admin')

@section('title', 'SEO · Tool pages')

@section('content')
    @include('admin.seo._tabs')

    @php
        $grouped = $rows->groupBy(fn ($row) => $row['tool']->category());
        $average = $rows->isEmpty() ? 0 : (int) round($rows->avg('score'));
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-600 dark:text-slate-400">
            Every tool has its own page in Google. Give each page a focus keyword, a unique title and description, an article and FAQ questions.
            Average score: <strong class="text-slate-900 tabular-nums dark:text-white">{{ $average }}/100</strong>.
        </p>
        <div class="relative w-full sm:w-72">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input type="search" class="input pl-9" placeholder="Filter tools…" data-admin-tool-filter aria-label="Filter tools">
        </div>
    </div>

    <div class="space-y-6">
        @foreach ($categories as $key => $category)
            @continue(! isset($grouped[$key]))
            <section class="card overflow-hidden" data-admin-tool-group>
                <h2 class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm font-semibold text-slate-900 dark:border-slate-800 dark:bg-slate-900/60 dark:text-white">{{ $category['name'] }}</h2>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($grouped[$key]->sortBy('score') as $row)
                        @include('admin.seo._tool-row', ['row' => $row])
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
