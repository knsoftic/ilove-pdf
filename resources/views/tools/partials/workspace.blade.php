@php
    $options = $tool->options();
    $multiple = $tool->multiple();
    $fileWord = $multiple ? 'files' : 'file';
    $primaryGroup = $tool->accept()[0];
    $pickLabel = match ($primaryGroup) {
        'pdf' => $multiple ? 'Select PDF files' : 'Select PDF file',
        'image' => 'Select images',
        'word' => 'Select Word '.$fileWord,
        'excel' => 'Select Excel '.$fileWord,
        'powerpoint' => 'Select PowerPoint '.$fileWord,
        'html' => 'Select HTML file',
        'text' => 'Select text file',
        default => 'Select '.$fileWord,
    };
    // Group consecutive fields that share a "group" key (e.g. header / footer).
    $sections = [];
    foreach ($options as $key => $field) {
        $group = $field['group'] ?? null;
        $last = array_key_last($sections);
        if ($group && $last !== null && ($sections[$last]['group'] ?? null) === $group) {
            $sections[$last]['fields'][] = $field;
        } else {
            $sections[] = ['group' => $group, 'fields' => [$field]];
        }
    }
@endphp

{{-- 1. Upload --}}
<div data-upload>
    <div class="dropzone" data-dropzone tabindex="0" role="button" aria-label="{{ $pickLabel }}">
        <span class="tool-icon size-14"><x-icon name="cloud-upload" class="size-7" /></span>
        <button type="button" class="btn btn-primary btn-lg mt-5" data-pick>
            <x-icon name="folder-open" class="size-5" /> {{ $pickLabel }}
        </button>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">or drop {{ $fileWord }} here</p>
        <p class="mt-4 text-xs text-slate-400">
            {{ $acceptLabel }} · up to {{ format_bytes($config['maxBytes'], 0) }}{{ $multiple ? ' each · max '.$config['maxFiles'].' files' : '' }}
        </p>
        <input type="file" class="sr-only" data-input accept="{{ implode(',', array_map(fn ($e) => '.'.$e, $config['accept']['extensions'])) }}" @if ($multiple) multiple @endif>
    </div>
</div>

{{-- 2. Selected files --}}
<div class="card mt-5 hidden p-4 sm:p-5" data-files-panel>
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
            <span data-file-count>0</span> {{ $multiple ? 'files' : 'file' }} selected
            @if ($tool->sortable())<span class="ml-1 font-normal text-slate-500">· drag to reorder</span>@endif
        </h2>
        <div class="flex gap-2">
            @if ($multiple)
                <button type="button" class="btn btn-secondary btn-sm" data-add-more><x-icon name="plus" class="size-4" /> Add more</button>
            @else
                <button type="button" class="btn btn-secondary btn-sm" data-replace><x-icon name="refresh" class="size-4" /> Replace file</button>
            @endif
        </div>
    </div>
    <ul class="grid gap-2 {{ $tool->sortable() ? 'sm:grid-cols-2' : '' }}" data-file-list></ul>
</div>

{{-- 3. Options --}}
@if ($options !== [] || $tool->notice())
    <div class="card mt-5 p-4 sm:p-6" data-options-panel>
        @if ($tool->notice())
            <p class="mb-5 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="info" class="mt-0.5 size-4 shrink-0" /> {{ $tool->notice() }}
            </p>
        @endif
        @if ($options !== [])
            <form class="grid gap-5 sm:grid-cols-2" data-options novalidate onsubmit="return false">
                @foreach ($sections as $section)
                    @if ($section['group'])
                        <fieldset class="rounded-2xl border border-slate-200 p-4 sm:col-span-2 dark:border-slate-700">
                            <legend class="px-1 text-sm font-semibold text-slate-900 capitalize dark:text-white">{{ $section['group'] }}</legend>
                            <div class="grid gap-4 sm:grid-cols-3">
                                @foreach ($section['fields'] as $field)
                                    @include('tools.partials.field', ['field' => $field])
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        @foreach ($section['fields'] as $field)
                            @include('tools.partials.field', ['field' => $field])
                        @endforeach
                    @endif
                @endforeach
            </form>
        @endif
    </div>
@endif

{{-- Engine choice when both browser and server processing are possible --}}
@if (count($engines) > 1)
    <div class="mt-4 flex flex-wrap items-center justify-center gap-3 text-sm" data-engine-switch>
        <span class="text-slate-500">Process:</span>
        <div class="segmented">
            @foreach ($engines as $engine)
                <label>
                    <input type="radio" name="engine" value="{{ $engine }}" class="sr-only" data-engine @checked($loop->first)>
                    {{ $engine === 'browser' ? 'In my browser' : 'On the server' }}
                </label>
            @endforeach
        </div>
    </div>
@endif

{{-- 4. Action --}}
<div class="mt-6 hidden text-center" data-actions>
    <button type="button" class="btn btn-primary btn-lg min-w-60 shadow-lg shadow-brand-500/25" data-process>
        {{ $tool->action() }} <x-icon name="arrow-right" class="size-5" />
    </button>
</div>

{{-- 5. Progress --}}
<div class="card mt-5 hidden p-6 sm:p-8" data-progress role="status" aria-live="polite">
    <div class="flex items-center gap-4">
        <span class="relative flex size-12 shrink-0 items-center justify-center">
            <span class="absolute inset-0 animate-spin rounded-full border-4 border-brand-100 border-t-brand-600 dark:border-slate-700 dark:border-t-brand-400"></span>
            <x-icon :name="$tool->icon()" class="size-5 text-brand-600 dark:text-brand-400" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900 dark:text-white" data-progress-title>Processing…</p>
            <p class="truncate text-sm text-slate-500" data-progress-message>Please wait</p>
        </div>
        <span class="text-sm font-semibold tabular-nums text-brand-600 dark:text-brand-400" data-progress-percent></span>
    </div>
    <div class="progress-track mt-5"><div class="progress-bar" style="width:0%" data-progress-bar></div></div>
    <div class="mt-4 text-center">
        <button type="button" class="btn btn-ghost btn-sm" data-cancel>Cancel</button>
    </div>
</div>

{{-- 6. Result --}}
<div class="card mt-5 hidden overflow-hidden" data-result>
    <div class="flex flex-col items-center p-6 text-center sm:p-8">
        <span class="flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
            <x-icon name="check" class="size-7" :stroke="2.5" />
        </span>
        <h2 class="mt-4 text-xl font-bold text-slate-900 dark:text-white" data-result-title>Your file is ready!</h2>
        <p class="mt-1 text-sm text-slate-500" data-result-file></p>
        <dl class="mt-5 grid w-full max-w-xl grid-cols-2 gap-3 sm:grid-cols-3" data-result-stats></dl>
        <div class="mt-5 w-full" data-result-extra></div>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="#" class="btn btn-primary btn-lg" data-download download>
                <x-icon name="download" class="size-5" /> Download
            </a>
            <button type="button" class="btn btn-secondary btn-lg" data-restart>
                <x-icon name="refresh" class="size-5" /> Process another file
            </button>
        </div>
        <button type="button" class="mt-4 hidden text-xs text-slate-500 underline-offset-2 hover:text-rose-600 hover:underline" data-delete-now>
            Delete my files from the server now
        </button>
    </div>
    @if ($related->isNotEmpty())
        <div class="border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
            <p class="mb-3 text-center text-xs font-semibold tracking-wide text-slate-500 uppercase">Continue with</p>
            <div class="flex flex-wrap justify-center gap-2">
                @foreach ($related->take(5) as $next)
                    <a href="{{ $next->url() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:border-brand-300 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <x-icon :name="$next->icon()" class="size-4" /> {{ $next->name() }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- 7. Error --}}
<div class="mt-5 hidden rounded-2xl border border-rose-200 bg-rose-50 p-5 dark:border-rose-500/30 dark:bg-rose-500/10" data-error role="alert">
    <div class="flex items-start gap-3">
        <x-icon name="alert" class="mt-0.5 size-5 shrink-0 text-rose-600" />
        <div class="flex-1">
            <p class="font-semibold text-rose-800 dark:text-rose-300" data-error-title>Something went wrong</p>
            <p class="mt-1 text-sm text-rose-700 dark:text-rose-300/90" data-error-message></p>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2 pl-8">
        <button type="button" class="btn btn-secondary btn-sm" data-retry><x-icon name="refresh" class="size-4" /> Try again</button>
        <button type="button" class="btn btn-ghost btn-sm" data-restart>Start over</button>
    </div>
</div>
