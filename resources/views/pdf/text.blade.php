@extends('layouts.app')

@section('page', 'text')

@section('content')
    @include('tools.partials.hero')

    <section class="container-page py-8 sm:py-10">
        <x-ad position="tool_top" />

        <div id="text-tool" class="mx-auto max-w-5xl" data-config='@json($config)' style="--tool-color: {{ $color }}">
            {{-- Upload & options --}}
            <div data-text-upload class="mx-auto max-w-3xl">
                <div class="dropzone" data-text-dropzone tabindex="0" role="button" aria-label="Select PDF file">
                    <span class="tool-icon size-14"><x-icon :name="$tool->icon()" class="size-7" /></span>
                    <button type="button" class="btn btn-primary btn-lg mt-5"><x-icon name="folder-open" class="size-5" /> Select PDF file</button>
                    <p class="mt-3 text-sm text-slate-500">or drop a PDF here</p>
                    <p class="mt-4 text-xs text-slate-400">PDF · up to {{ format_bytes($config['maxBytes'], 0) }}</p>
                    <input type="file" class="sr-only" accept=".pdf,application/pdf" data-text-input>
                </div>

                <div class="card mt-5 grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-3.5 py-3 dark:border-slate-700">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Keep layout (columns &amp; spacing)</span>
                        <input type="checkbox" class="toggle" data-text-option="layout">
                    </label>
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-3.5 py-3 dark:border-slate-700">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Show page markers</span>
                        <input type="checkbox" class="toggle" data-text-option="markers" checked>
                    </label>
                    <div>
                        <label class="label" for="ocr-language">Language for scanned pages (OCR)</label>
                        <select id="ocr-language" class="input" data-text-option="language">
                            @foreach (config('pdftoolkit.ocr.languages') as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (count($engines) > 1)
                        <div>
                            <span class="label">Process</span>
                            <div class="segmented">
                                @foreach ($engines as $engine)
                                    <label><input type="radio" name="engine" value="{{ $engine }}" class="sr-only" data-engine @checked($loop->first)> {{ $engine === 'browser' ? 'In my browser' : 'On the server' }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <x-privacy-note class="mt-5" :browser="($engines[0] ?? 'browser') === 'browser'" />
            </div>

            {{-- Progress --}}
            <div class="card mx-auto hidden max-w-3xl p-6 sm:p-8" data-text-progress role="status" aria-live="polite">
                <div class="flex items-center gap-4">
                    <span class="relative flex size-12 shrink-0 items-center justify-center">
                        <span class="absolute inset-0 animate-spin rounded-full border-4 border-brand-100 border-t-brand-600 dark:border-slate-700 dark:border-t-brand-400"></span>
                        <x-icon name="file-text" class="size-5 text-brand-600" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900 dark:text-white" data-progress-title>Extracting text…</p>
                        <p class="truncate text-sm text-slate-500" data-progress-message>Please wait</p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums text-brand-600" data-progress-percent></span>
                </div>
                <div class="progress-track mt-5"><div class="progress-bar" style="width:0%" data-progress-bar></div></div>
                <div class="mt-4 text-center"><button type="button" class="btn btn-ghost btn-sm" data-text-cancel>Cancel</button></div>
            </div>

            {{-- Result editor --}}
            <div class="hidden" data-text-result>
                <div class="mb-3 hidden items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" data-ocr-banner>
                    <x-icon name="scan" class="mt-0.5 size-5 shrink-0" />
                    <div class="flex-1">
                        <p class="font-semibold" data-ocr-title>Some pages contain no text</p>
                        <p class="mt-0.5" data-ocr-text>These pages are probably scanned images. Run OCR to recognise their text.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm shrink-0" data-run-ocr><x-icon name="scan" class="size-4" /> Run OCR</button>
                </div>

                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3 dark:border-slate-800">
                        <button type="button" class="btn btn-secondary btn-sm" data-text-action="copy"><x-icon name="clipboard" class="size-4" /> Copy</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-text-action="txt"><x-icon name="download" class="size-4" /> TXT</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-text-action="docx"><x-icon name="file-word" class="size-4" /> DOC</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-text-action="pdf"><x-icon name="file-pdf" class="size-4" /> PDF</button>
                        <button type="button" class="btn btn-ghost btn-sm hover:text-rose-600" data-text-action="clear"><x-icon name="trash" class="size-4" /> Clear</button>
                        <span class="ml-auto text-xs text-slate-500" data-text-stats></span>
                    </div>

                    <div class="grid gap-2 border-b border-slate-200 bg-slate-50 p-3 md:grid-cols-2 dark:border-slate-800 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input type="search" class="input py-2 pl-9" placeholder="Search…" data-search-input aria-label="Search text">
                            </div>
                            <span class="w-16 text-center text-xs text-slate-500 tabular-nums" data-search-count></span>
                            <button type="button" class="btn-icon size-9" data-search="prev" aria-label="Previous match"><x-icon name="chevron-up" class="size-4" /></button>
                            <button type="button" class="btn-icon size-9" data-search="next" aria-label="Next match"><x-icon name="chevron-down" class="size-4" /></button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" class="input flex-1 py-2" placeholder="Replace with…" data-replace-input aria-label="Replace with">
                            <button type="button" class="btn btn-secondary btn-sm" data-replace="one">Replace</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-replace="all">All</button>
                        </div>
                    </div>

                    <textarea class="block min-h-[60vh] w-full resize-y border-0 bg-white p-4 font-mono text-[13px] leading-6 text-slate-800 focus:ring-0 focus:outline-none sm:p-6 dark:bg-slate-950 dark:text-slate-200" spellcheck="false" data-text-editor aria-label="Extracted text"></textarea>
                </div>

                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <button type="button" class="btn btn-secondary" data-text-restart><x-icon name="refresh" class="size-4" /> Extract another PDF</button>
                    <a href="{{ route('tools.show', 'pdf-translator') }}" class="btn btn-ghost"><x-icon name="translate" class="size-4" /> Translate this PDF</a>
                </div>
            </div>
        </div>

        <x-ad position="tool_bottom" />
    </section>

    @include('tools.partials.info')
@endsection
