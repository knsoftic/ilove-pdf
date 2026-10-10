@extends('layouts.app')

@section('page', 'editor')

@php
    $tools = [
        ['select', 'cursor', 'Select & move', 'V', 'Select'],
        ['edit-text', 'edit-text', 'Edit existing text', 'E', 'Edit text'],
        ['text', 'type', 'Add text', 'T', 'Text'],
        ['highlight', 'highlighter', 'Highlight', 'H', 'Highlight'],
        ['underline', 'underline', 'Underline', 'U', 'Underline'],
        ['strike', 'strikethrough', 'Strikethrough', 'S', 'Strike'],
        ['draw', 'pen', 'Draw (pen)', 'P', 'Draw'],
        ['eraser', 'eraser', 'Eraser', 'X', 'Eraser'],
        ['rect', 'square', 'Rectangle', 'R', 'Box'],
        ['ellipse', 'circle', 'Circle', 'O', 'Circle'],
        ['line', 'line', 'Line', 'L', 'Line'],
        ['arrow', 'arrow', 'Arrow', 'A', 'Arrow'],
        ['image', 'image', 'Image', 'I', 'Image'],
        ['signature', 'signature', 'Signature', 'G', 'Sign'],
        ['stamp', 'stamp', 'Stamp', null, 'Stamp'],
        ['watermark', 'watermark', 'Watermark', null, 'Watermark'],
        ['page-number', 'hash', 'Page numbers', null, 'Numbers'],
    ];
@endphp

@section('content')
    {{-- 1. Upload (before a PDF is opened) --}}
    <div id="editor-upload">
        @include('tools.partials.hero')

        <section class="container-page py-8 sm:py-10">
            <x-ad position="tool_top" />
            <div class="mx-auto max-w-3xl">
                <div class="dropzone" data-editor-dropzone tabindex="0" role="button" aria-label="Select PDF file">
                    <span class="tool-icon size-14" style="--tool-color: {{ $color }}"><x-icon :name="$tool->icon()" class="size-7" /></span>
                    <button type="button" class="btn btn-primary btn-lg mt-5" data-editor-pick><x-icon name="folder-open" class="size-5" /> Select PDF file</button>
                    <p class="mt-3 text-sm text-slate-500">or drop a PDF here</p>
                    <p class="mt-4 text-xs text-slate-400">PDF · up to {{ format_bytes($config['maxBytes'], 0) }}</p>
                    <input type="file" class="sr-only" accept=".pdf,application/pdf" data-editor-input>
                </div>
                <x-privacy-note class="mt-5" :browser="true" />
                <p class="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs text-slate-500">
                    @foreach (['Edit text', 'Add text & images', 'Highlight', 'Draw', 'Shapes', 'Sign', 'Stamps', 'Watermark', 'Page numbers', 'Rotate & reorder pages'] as $feature)
                        <span class="inline-flex items-center gap-1"><x-icon name="check" class="size-3.5 text-emerald-600" /> {{ $feature }}</span>
                    @endforeach
                </p>
            </div>
        </section>

        @include('tools.partials.info')
    </div>

    {{-- 2. Editor workspace --}}
    <div id="editor" class="editor-shell hidden flex-col bg-slate-100 dark:bg-slate-950" data-config='@json($config)' data-preset="{{ $tool->preset() }}">
        {{-- Top bar --}}
        <div class="flex h-14 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-2 sm:px-3 dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="btn-icon" data-action="close" title="Open another PDF" aria-label="Open another PDF"><x-icon name="folder-open" class="size-5" /></button>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" data-file-name></p>
                <p class="text-xs text-slate-500" data-page-info></p>
            </div>
            <div class="flex items-center gap-0.5">
                <button type="button" class="btn-icon" data-action="undo" title="Undo (Ctrl+Z)" aria-label="Undo" disabled><x-icon name="undo" class="size-5" /></button>
                <button type="button" class="btn-icon" data-action="redo" title="Redo (Ctrl+Y)" aria-label="Redo" disabled><x-icon name="redo" class="size-5" /></button>
                <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block dark:bg-slate-700"></span>
                <button type="button" class="btn-icon hidden sm:inline-flex" data-action="zoom-out" title="Zoom out" aria-label="Zoom out"><x-icon name="zoom-out" class="size-5" /></button>
                <button type="button" class="hidden w-14 rounded-lg py-1.5 text-center text-xs font-semibold text-slate-600 tabular-nums hover:bg-slate-100 sm:block dark:text-slate-300 dark:hover:bg-slate-800" data-action="zoom-fit" title="Fit to width" data-zoom-label>100%</button>
                <button type="button" class="btn-icon hidden sm:inline-flex" data-action="zoom-in" title="Zoom in" aria-label="Zoom in"><x-icon name="zoom-in" class="size-5" /></button>
                <button type="button" class="btn-icon hidden md:inline-flex" data-action="fullscreen" title="Fullscreen" aria-label="Fullscreen"><x-icon name="maximize" class="size-5" /></button>
            </div>
            <button type="button" class="btn btn-primary ml-1" data-action="download"><x-icon name="download" class="size-4" /> <span class="hidden sm:inline">Download</span></button>
        </div>

        {{-- Properties of the active tool / selection --}}
        <div class="flex min-h-12 shrink-0 items-center gap-3 overflow-x-auto border-b border-slate-200 bg-white px-3 py-1.5 text-sm dark:border-slate-800 dark:bg-slate-900" data-props>
            <span class="flex shrink-0 items-center gap-2 font-medium text-slate-700 dark:text-slate-200" data-props-title>Select</span>

            <div class="flex shrink-0 items-center gap-2" data-prop-group="font">
                <select class="input w-32 py-1.5" data-prop="fontFamily" aria-label="Font">
                    <option value="helvetica">Helvetica</option>
                    <option value="times">Times</option>
                    <option value="courier">Courier</option>
                </select>
                <select class="input w-20 py-1.5" data-prop="fontSize" aria-label="Font size">
                    @foreach ([8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 64, 72, 96] as $size)
                        <option value="{{ $size }}" @selected($size === 16)>{{ $size }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn-icon size-8" data-prop-toggle="bold" aria-pressed="false" title="Bold"><x-icon name="bold" class="size-4" :stroke="2.5" /></button>
                <button type="button" class="btn-icon size-8" data-prop-toggle="italic" aria-pressed="false" title="Italic"><x-icon name="italic" class="size-4" /></button>
                <button type="button" class="btn-icon size-8" data-prop-toggle="underline" aria-pressed="false" title="Underline"><x-icon name="underline" class="size-4" /></button>
            </div>

            <label class="flex shrink-0 items-center gap-1.5" data-prop-group="color" title="Colour">
                <span class="text-xs text-slate-500">Colour</span>
                <input type="color" value="#111827" class="h-8 w-10 cursor-pointer rounded-md border border-slate-300 bg-white p-0.5 dark:border-slate-700" data-prop="color">
            </label>

            <label class="flex shrink-0 items-center gap-1.5" data-prop-group="fill" title="Fill">
                <span class="text-xs text-slate-500">Fill</span>
                <input type="checkbox" class="accent-brand-600" data-prop="filled">
                <input type="color" value="#bfdbfe" class="h-8 w-10 cursor-pointer rounded-md border border-slate-300 bg-white p-0.5 dark:border-slate-700" data-prop="fill">
            </label>

            <label class="flex shrink-0 items-center gap-1.5" data-prop-group="width" title="Thickness">
                <span class="text-xs text-slate-500">Size</span>
                <input type="range" min="1" max="30" value="3" class="w-24 accent-brand-600" data-prop="strokeWidth">
            </label>

            <label class="flex shrink-0 items-center gap-1.5" data-prop-group="opacity" title="Opacity">
                <span class="text-xs text-slate-500">Opacity</span>
                <input type="range" min="10" max="100" value="100" class="w-20 accent-brand-600" data-prop="opacity">
            </label>

            <div class="flex shrink-0 items-center gap-1" data-prop-group="swatches">
                @foreach (['#fde047', '#86efac', '#f9a8d4', '#93c5fd', '#fdba74'] as $swatch)
                    <button type="button" class="size-6 rounded-full border-2 border-white shadow ring-1 ring-slate-300" style="background: {{ $swatch }}" data-swatch="{{ $swatch }}" aria-label="Colour {{ $swatch }}"></button>
                @endforeach
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-1" data-prop-group="object">
                <button type="button" class="btn btn-secondary btn-sm" data-action="duplicate-object"><x-icon name="copy" class="size-4" /> Duplicate</button>
                <button type="button" class="btn btn-secondary btn-sm hover:text-rose-600" data-action="delete-object"><x-icon name="trash" class="size-4" /> Delete</button>
            </div>

            <p class="shrink-0 text-xs text-slate-500" data-props-hint></p>
        </div>

        <div class="flex min-h-0 flex-1">
            {{-- Tool bar (desktop) --}}
            <nav class="hidden w-14 shrink-0 flex-col items-center gap-1 overflow-y-auto border-r border-slate-200 bg-white py-2 md:flex dark:border-slate-800 dark:bg-slate-900" aria-label="Editing tools" data-toolbar>
                @foreach ($tools as [$id, $icon, $label, $key, $short])
                    <button type="button" class="editor-tool btn-icon size-10 shrink-0" data-tool="{{ $id }}" aria-pressed="false" title="{{ $label }}{{ $key ? ' ('.$key.')' : '' }}" aria-label="{{ $label }}">
                        <x-icon :name="$icon" class="size-5" />
                    </button>
                    @if (in_array($id, ['edit-text', 'strike', 'eraser', 'arrow', 'signature']))
                        <span class="my-1 h-px w-8 shrink-0 bg-slate-200 dark:bg-slate-700"></span>
                    @endif
                @endforeach
            </nav>

            {{-- Page thumbnails --}}
            <aside class="hidden w-44 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex dark:border-slate-800 dark:bg-slate-900" aria-label="Pages">
                <p class="border-b border-slate-200 px-3 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:border-slate-800">Pages · drag to reorder</p>
                <div class="flex-1 space-y-3 overflow-y-auto p-3" data-thumbs></div>
            </aside>

            {{-- Pages --}}
            <main class="relative min-w-0 flex-1 overflow-auto" data-viewport tabindex="-1">
                <div class="flex flex-col items-center gap-6 px-3 py-6 pb-28 md:pb-10" data-pages></div>
            </main>
        </div>

        {{-- Tool bar (mobile) --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 flex gap-1 overflow-x-auto border-t border-slate-200 bg-white/95 px-2 py-2 backdrop-blur md:hidden dark:border-slate-800 dark:bg-slate-900/95" aria-label="Editing tools" data-toolbar>
            @foreach ($tools as [$id, $icon, $label, $key, $short])
                <button type="button" class="editor-tool flex w-16 shrink-0 flex-col items-center gap-0.5 rounded-lg px-1 py-1.5 text-[10px] text-slate-600 dark:text-slate-300" data-tool="{{ $id }}" aria-pressed="false" aria-label="{{ $label }}">
                    <x-icon :name="$icon" class="size-5" />
                    <span class="w-full truncate text-center">{{ $short }}</span>
                </button>
            @endforeach
        </nav>

        <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" data-image-input>
    </div>
@endsection
