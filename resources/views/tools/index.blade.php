@extends('layouts.app')

@section('page', 'tools-index')

@section('content')
    <section class="container-page pt-12 pb-6">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> <span class="mx-1">/</span> <span class="text-slate-700 dark:text-slate-300">All Tools</span>
        </nav>
        <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">All PDF Tools</h1>
                <p class="mt-3 max-w-2xl text-slate-600 dark:text-slate-400">{{ $total }} free tools to convert, edit, organize, compress, secure, OCR and translate PDF files. No registration required.</p>
            </div>
            <div class="relative w-full lg:max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400" />
                <input type="search" class="input py-3 pl-11 text-base" placeholder="Search PDF tools..." value="{{ $query }}" data-tool-search autocomplete="off" aria-label="Search PDF tools">
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2" data-category-filter>
            <button type="button" class="badge cursor-pointer bg-brand-600 px-3 py-1.5 text-sm text-white" data-category="all">All</button>
            @foreach ($groups as $key => $group)
                <button type="button" class="badge cursor-pointer bg-slate-100 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300" data-category="{{ $key }}">{{ $group['category']['name'] }}</button>
            @endforeach
        </div>
    </section>

    <x-ad position="tools_index" />

    <div class="container-page pb-12">
        @foreach ($groups as $key => $group)
            <section id="{{ $key }}" class="scroll-mt-24 py-6" data-category-section="{{ $key }}" aria-labelledby="cat-{{ $key }}">
                <div class="mb-4 flex items-center gap-3" style="--tool-color: {{ $group['category']['color'] }}">
                    <span class="tool-icon size-9"><x-icon :name="$group['category']['icon']" class="size-5" /></span>
                    <div>
                        <h2 id="cat-{{ $key }}" class="text-xl font-bold text-slate-900 dark:text-white">{{ $group['category']['name'] }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $group['category']['description'] }}</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($group['tools'] as $tool)
                        <x-tool-card :tool="$tool" />
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="hidden py-16 text-center" data-no-results>
            <x-icon name="search" class="mx-auto size-10 text-slate-300" />
            <p class="mt-4 font-semibold text-slate-900 dark:text-white">No tools found</p>
            <p class="mt-1 text-sm text-slate-500">Try another word, e.g. "merge", "word" or "compress".</p>
        </div>
    </div>
@endsection
