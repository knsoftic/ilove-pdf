@extends('layouts.app')

@section('page', 'organize')

@section('content')
    @include('tools.partials.hero')

    <section class="container-page py-8 sm:py-10">
        <x-ad position="tool_top" />

        <div id="organizer" data-config='@json($config)' data-preset="{{ $tool->preset() }}" style="--tool-color: {{ $color }}">
            {{-- Upload --}}
            <div class="mx-auto max-w-3xl" data-organizer-upload>
                <div class="dropzone" data-organizer-dropzone tabindex="0" role="button" aria-label="Select PDF file">
                    <span class="tool-icon size-14"><x-icon :name="$tool->icon()" class="size-7" /></span>
                    <button type="button" class="btn btn-primary btn-lg mt-5"><x-icon name="folder-open" class="size-5" /> Select PDF file</button>
                    <p class="mt-3 text-sm text-slate-500">or drop a PDF here</p>
                    <p class="mt-4 text-xs text-slate-400">PDF · up to {{ format_bytes($config['maxBytes'], 0) }}</p>
                </div>
                <x-privacy-note class="mt-5" :browser="true" />
            </div>

            {{-- Workspace --}}
            <div class="hidden" data-organizer-workspace>
                <div class="sticky top-16 z-20 -mx-4 mb-5 border-y border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="mr-2 text-sm font-semibold text-slate-900 dark:text-white"><span data-count>0</span> pages · <span data-selected-count>0</span> selected</p>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="select-all">Select all</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="select-none">None</button>
                        <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block dark:bg-slate-700"></span>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="rotate-left" data-needs-selection title="Rotate selected left"><x-icon name="rotate-ccw" class="size-4" /><span class="hidden sm:inline">Left</span></button>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="rotate-right" data-needs-selection title="Rotate selected right"><x-icon name="rotate" class="size-4" /><span class="hidden sm:inline">Right</span></button>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="duplicate" data-needs-selection><x-icon name="copy" class="size-4" /><span class="hidden sm:inline">Duplicate</span></button>
                        <button type="button" class="btn btn-secondary btn-sm hover:text-rose-600" data-org="delete" data-needs-selection><x-icon name="trash" class="size-4" /><span class="hidden sm:inline">Delete</span></button>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="extract" data-needs-selection><x-icon name="file-export" class="size-4" /><span class="hidden sm:inline">Download selected</span></button>
                        <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block dark:bg-slate-700"></span>
                        <button type="button" class="btn btn-secondary btn-sm" data-org="add"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">Add PDF</span></button>
                        <button type="button" class="btn-icon size-8" data-org="undo" title="Undo" aria-label="Undo" disabled><x-icon name="undo" class="size-4" /></button>
                        <label class="ml-auto hidden items-center gap-2 text-xs text-slate-500 md:flex">
                            <x-icon name="zoom-out" class="size-4" />
                            <input type="range" min="110" max="260" value="170" class="w-24 accent-brand-600" data-thumb-size aria-label="Thumbnail size">
                            <x-icon name="zoom-in" class="size-4" />
                        </label>
                        <button type="button" class="btn btn-primary ml-auto md:ml-2" data-org="download"><x-icon name="download" class="size-4" /> Download PDF</button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500" data-org-hint>Drag pages to reorder them. Click a page to select it (Shift-click to select a range).</p>
                </div>

                <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(var(--thumb, 170px), 1fr))" data-grid></div>

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <button type="button" class="btn btn-primary btn-lg" data-org="download"><x-icon name="download" class="size-5" /> Download PDF</button>
                    <button type="button" class="btn btn-secondary btn-lg" data-org="restart"><x-icon name="refresh" class="size-5" /> Start over</button>
                </div>
            </div>

            <input type="file" class="sr-only" accept=".pdf,application/pdf" data-organizer-input>
            <input type="file" class="sr-only" accept=".pdf,application/pdf" multiple data-organizer-add>
        </div>

        <x-ad position="tool_bottom" />
    </section>

    @include('tools.partials.info')
@endsection
