@php
    $tool = $row['tool'];
    $score = $row['score'];
    [$badge, $label] = $score >= 80
        ? ['bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'Good']
        : ($score >= 50 ? ['bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'Fair'] : ['bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300', 'Poor']);
    $missing = collect($row['checks'])->where('ok', false)->pluck('label')->take(2);
@endphp
<li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3" data-admin-tool="{{ strtolower($tool->name().' '.$tool->slug) }}">
    <span class="badge {{ $badge }} min-w-[5.5rem] justify-center tabular-nums" title="{{ $label }}">{{ $score }} · {{ $label }}</span>
    <div class="min-w-0 flex-1">
        <p class="truncate font-medium text-slate-900 dark:text-white">
            {{ $tool->name() }}
            @unless ($tool->isEnabled())<span class="badge ml-1 bg-slate-100 text-slate-500 dark:bg-slate-800">disabled</span>@endunless
            @if ($tool->noindex())<span class="badge ml-1 bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">noindex</span>@endif
        </p>
        <p class="truncate text-xs text-slate-500">{{ $missing->isEmpty() ? 'All checks passed' : 'To do: '.$missing->implode(' · ') }}</p>
    </div>
    <a href="{{ route('admin.seo.tool', $tool->slug) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" /> Edit SEO</a>
</li>
