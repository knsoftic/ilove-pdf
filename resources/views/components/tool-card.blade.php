@props(['tool', 'compact' => false])
@php
    $color = app(\App\Services\Tools\ToolRegistry::class)->categoryColor($tool->category());
@endphp
<a href="{{ $tool->url() }}"
   {{ $attributes->merge(['class' => 'group relative flex gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-soft transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lift dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700']) }}
   style="--tool-color: {{ $color }}"
   data-tool-card
   data-name="{{ strtolower($tool->name()) }}"
   data-keywords="{{ strtolower($tool->description().' '.$tool->keywords()) }}">
    <span class="tool-icon {{ $compact ? 'size-10' : 'size-12' }}">
        <x-icon :name="$tool->icon()" class="{{ $compact ? 'size-5' : 'size-6' }}" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">
            {{ $tool->name() }}
        </span>
        @unless ($compact)
            <span class="mt-1 line-clamp-2 block text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $tool->description() }}</span>
            <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 opacity-90 transition group-hover:gap-2 dark:text-brand-400">
                Use tool <x-icon name="arrow-right" class="size-4" />
            </span>
        @endunless
    </span>
</a>
