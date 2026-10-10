@extends('layouts.app')

@section('page', 'translator')

@section('content')
    @include('tools.partials.hero')

    <section class="container-page py-8 sm:py-10">
        <x-ad position="tool_top" />

        <div id="translator" class="mx-auto max-w-6xl" data-config='@json($config)' data-translation='@json($translation)' style="--tool-color: {{ $color }}">
            {{-- Step 1: upload --}}
            <div class="mx-auto max-w-3xl" data-tr-upload>
                <ol class="mb-6 grid grid-cols-2 gap-2 text-xs text-slate-500 sm:grid-cols-4">
                    @foreach (['Upload PDF', 'Extract text', 'Choose language', 'Download translation'] as $i => $step)
                        <li class="flex items-center gap-2 rounded-xl bg-white p-2.5 shadow-soft dark:bg-slate-900">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-bold text-white">{{ $i + 1 }}</span>{{ $step }}
                        </li>
                    @endforeach
                </ol>

                <div class="dropzone" data-tr-dropzone tabindex="0" role="button" aria-label="Select PDF file">
                    <span class="tool-icon size-14"><x-icon name="translate" class="size-7" /></span>
                    <button type="button" class="btn btn-primary btn-lg mt-5"><x-icon name="folder-open" class="size-5" /> Select PDF file</button>
                    <p class="mt-3 text-sm text-slate-500">or drop a PDF here</p>
                    <p class="mt-4 text-xs text-slate-400">PDF · up to {{ format_bytes($config['maxBytes'], 0) }}</p>
                    <input type="file" class="sr-only" accept=".pdf,application/pdf" data-tr-input>
                </div>

                <div class="card mt-5 grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
                    <div>
                        <label class="label" for="tr-ocr">Language of scanned pages (OCR)</label>
                        <select id="tr-ocr" class="input" data-tr-ocr>
                            @foreach (config('pdftoolkit.ocr.languages') as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (count($engines) > 1)
                        <div>
                            <span class="label">Extract text</span>
                            <div class="segmented">
                                @foreach ($engines as $engine)
                                    <label><input type="radio" name="engine" value="{{ $engine }}" class="sr-only" data-engine @checked($loop->first)> {{ $engine === 'browser' ? 'In my browser' : 'On the server' }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @unless ($translation['server'])
                    <p class="mt-4 flex items-start gap-2 rounded-xl bg-sky-50 p-3 text-sm text-sky-800 dark:bg-sky-500/10 dark:text-sky-200" data-tr-engine-note>
                        <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
                        <span>Translation runs with the on-device translator of your browser where available (e.g. Chrome on desktop). The site owner can also connect a free, self-hosted LibreTranslate server.</span>
                    </p>
                @endunless
            </div>

            {{-- Progress --}}
            <div class="card mx-auto hidden max-w-3xl p-6 sm:p-8" data-tr-progress role="status" aria-live="polite">
                <div class="flex items-center gap-4">
                    <span class="relative flex size-12 shrink-0 items-center justify-center">
                        <span class="absolute inset-0 animate-spin rounded-full border-4 border-brand-100 border-t-brand-600 dark:border-slate-700 dark:border-t-brand-400"></span>
                        <x-icon name="translate" class="size-5 text-brand-600" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900 dark:text-white" data-progress-title>Working…</p>
                        <p class="truncate text-sm text-slate-500" data-progress-message>Please wait</p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums text-brand-600" data-progress-percent></span>
                </div>
                <div class="progress-track mt-5"><div class="progress-bar" style="width:0%" data-progress-bar></div></div>
                <div class="mt-4 text-center"><button type="button" class="btn btn-ghost btn-sm" data-tr-cancel>Cancel</button></div>
            </div>

            {{-- Workspace --}}
            <div class="hidden" data-tr-workspace>
                <div class="card mb-4 flex flex-wrap items-end gap-3 p-4">
                    <div class="min-w-40 flex-1">
                        <label class="label" for="tr-source">From</label>
                        <select id="tr-source" class="input" data-tr-source>
                            <option value="auto">Detect automatically</option>
                            @foreach ($translation['languages'] as $code => $language)
                                <option value="{{ $code }}">{{ $language['name'] }} – {{ $language['native'] }}</option>
                            @endforeach
                        </select>
                        <p class="help" data-tr-detected></p>
                    </div>
                    <button type="button" class="btn-icon mb-6 size-10" data-tr-swap title="Swap languages" aria-label="Swap languages"><x-icon name="convert" class="size-5" /></button>
                    <div class="min-w-40 flex-1">
                        <label class="label" for="tr-target">To</label>
                        <select id="tr-target" class="input" data-tr-target>
                            @foreach ($translation['languages'] as $code => $language)
                                <option value="{{ $code }}" @selected($code === 'en')>{{ $language['name'] }} – {{ $language['native'] }}</option>
                            @endforeach
                        </select>
                        <p class="help" data-tr-engine></p>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg mb-6" data-tr-translate><x-icon name="translate" class="size-5" /> Translate</button>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="card overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5 dark:border-slate-800">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Original text</h2>
                            <span class="text-xs text-slate-500" data-tr-original-stats></span>
                        </div>
                        <textarea class="block h-[55vh] w-full resize-y border-0 bg-white p-4 text-sm leading-6 focus:ring-0 focus:outline-none dark:bg-slate-950" dir="auto" spellcheck="false" data-tr-original aria-label="Original text"></textarea>
                    </div>
                    <div class="card overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2 dark:border-slate-800">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Translation</h2>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" class="btn btn-secondary btn-sm" data-tr-action="copy"><x-icon name="clipboard" class="size-4" /> Copy</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-tr-action="txt">TXT</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-tr-action="docx">DOCX</button>
                                <button type="button" class="btn btn-primary btn-sm" data-tr-action="pdf"><x-icon name="download" class="size-4" /> PDF</button>
                            </div>
                        </div>
                        <textarea class="block h-[55vh] w-full resize-y border-0 bg-white p-4 text-sm leading-7 focus:ring-0 focus:outline-none dark:bg-slate-950" spellcheck="false" placeholder="The translation will appear here." data-tr-output aria-label="Translation"></textarea>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <button type="button" class="btn btn-secondary" data-tr-restart><x-icon name="refresh" class="size-4" /> Translate another PDF</button>
                </div>
            </div>
        </div>

        <x-ad position="tool_bottom" />
    </section>

    @include('tools.partials.info')
@endsection
