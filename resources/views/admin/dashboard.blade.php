@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $max = max(1, max($days));
        $labels = array_keys($days);
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Tasks today', number_format($today), 'zap'],
            ['Tasks (30 days)', number_format($total30), 'bar-chart'],
            ['Tools enabled', $toolsEnabled.' / '.$toolsTotal, 'grid'],
            ['Temporary files', $activeJobs.' jobs · '.format_bytes($tempBytes), 'folder-open'],
        ] as [$label, $value, $icon])
            <div class="card p-5">
                <div class="flex items-center gap-2 text-sm text-slate-500"><x-icon :name="$icon" class="size-4" /> {{ $label }}</div>
                <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <a href="{{ route('admin.seo') }}" class="card flex items-center gap-4 p-5 transition hover:border-brand-300">
            <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-icon name="trending-up" class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-500">SEO score</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums dark:text-white">{{ $seoScore }}/100 @if (setting('seo_noindex_site'))<span class="badge ml-1 bg-rose-50 align-middle text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">search engines blocked</span>@endif</p>
            </div>
            <x-icon name="chevron-right" class="size-5 text-slate-400" />
        </a>
        <a href="{{ route('admin.ads') }}" class="card flex items-center gap-4 p-5 transition hover:border-brand-300">
            <span class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"><x-icon name="megaphone" class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-500">Advertising</p>
                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ $adsEnabled ? ($adUnits.' active '.Str::plural('unit', $adUnits)) : 'Switched off' }}</p>
            </div>
            <x-icon name="chevron-right" class="size-5 text-slate-400" />
        </a>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Usage per day --}}
        <section class="card p-5 xl:col-span-2" aria-labelledby="usage-title">
            <div class="flex items-baseline justify-between">
                <h2 id="usage-title" class="font-semibold text-slate-900 dark:text-white">Completed tasks per day</h2>
                <span class="text-xs text-slate-500">Last 30 days · browser + server</span>
            </div>

            <div class="mt-5 grid grid-cols-[auto_1fr] gap-x-3">
                <div class="flex h-44 flex-col justify-between text-right text-[11px] text-slate-400 tabular-nums" aria-hidden="true">
                    <span>{{ number_format($max) }}</span>
                    <span>{{ number_format(intdiv($max, 2)) }}</span>
                    <span>0</span>
                </div>
                <div class="relative h-44">
                    {{-- Recessive grid --}}
                    <div class="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                        <span class="border-t border-dashed border-slate-200 dark:border-slate-800"></span>
                        <span class="border-t border-dashed border-slate-200 dark:border-slate-800"></span>
                        <span class="border-t border-slate-300 dark:border-slate-700"></span>
                    </div>
                    <div class="relative flex h-full items-end gap-[2px]" role="img" aria-label="Bar chart of completed tasks per day for the last 30 days">
                        @foreach ($days as $date => $count)
                            <div class="group relative flex h-full flex-1 items-end">
                                <div class="w-full rounded-t-[4px] bg-[#6366f1] transition-opacity group-hover:opacity-80" style="height: {{ $count ? max(2, round($count / $max * 100, 2)) : 0 }}%"></div>
                                <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-lg group-hover:block dark:bg-white dark:text-slate-900">
                                    {{ \Illuminate\Support\Carbon::parse($date)->format('M j') }} · <strong>{{ number_format($count) }}</strong> tasks
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <span></span>
                <div class="mt-2 flex justify-between text-[11px] text-slate-400" aria-hidden="true">
                    <span>{{ \Illuminate\Support\Carbon::parse($labels[0])->format('M j') }}</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($labels[intdiv(count($labels), 2)])->format('M j') }}</span>
                    <span>Today</span>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-xs font-medium text-brand-600 dark:text-brand-400">Show as table</summary>
                <table class="mt-3 w-full text-left text-xs">
                    <thead><tr class="text-slate-500"><th class="py-1 font-medium">Date</th><th class="py-1 text-right font-medium">Tasks</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach (array_reverse($days, true) as $date => $count)
                            <tr><td class="py-1">{{ $date }}</td><td class="py-1 text-right tabular-nums">{{ number_format($count) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        {{-- Top tools --}}
        <section class="card p-5" aria-labelledby="top-title">
            <h2 id="top-title" class="font-semibold text-slate-900 dark:text-white">Most used tools</h2>
            @if ($topTools->isEmpty())
                <p class="mt-4 text-sm text-slate-500">No usage recorded yet.</p>
            @else
                <table class="mt-4 w-full text-sm">
                    <thead><tr class="text-left text-xs text-slate-500"><th class="pb-2 font-medium">Tool</th><th class="pb-2 text-right font-medium">Browser</th><th class="pb-2 text-right font-medium">Server</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($topTools as $row)
                            <tr>
                                <td class="py-2 pr-2 font-medium text-slate-700 dark:text-slate-200">{{ $row['tool'] }}</td>
                                <td class="py-2 text-right tabular-nums">{{ number_format($row['browser']) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ number_format($row['server']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 dark:text-white">Server capabilities</h2>
                <a href="{{ route('admin.system') }}" class="text-sm font-medium text-brand-600 dark:text-brand-400">Details</a>
            </div>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($capabilities['binaries'] as $name => $binary)
                    <li class="flex items-center gap-2 text-sm">
                        @if ($binary['available'])
                            <x-icon name="check-circle" class="size-4 text-emerald-600" /> <span>{{ $name }}</span> <span class="text-xs text-slate-400">{{ $binary['version'] }}</span>
                        @else
                            <x-icon name="alert" class="size-4 text-amber-500" /> <span class="text-slate-500">{{ $name }}</span> <span class="text-xs text-slate-400">not installed</span>
                        @endif
                    </li>
                @endforeach
                <li class="flex items-center gap-2 text-sm">
                    @if ($translation['available'])
                        <x-icon name="check-circle" class="size-4 text-emerald-600" /> translation: {{ $translation['engine'] }}
                    @else
                        <x-icon name="alert" class="size-4 text-amber-500" /> <span class="text-slate-500">translation server</span> <span class="text-xs text-slate-400">browser fallback</span>
                    @endif
                </li>
            </ul>
        </section>

        <section class="card p-5">
            <h2 class="font-semibold text-slate-900 dark:text-white">Attention</h2>
            @if ($unavailable->isEmpty())
                <p class="mt-4 flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400"><x-icon name="check-circle" class="size-5" /> Every enabled tool can run on this server or in the browser.</p>
            @else
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">These enabled tools are unavailable until the required software is installed (see <a href="{{ route('admin.system') }}" class="text-brand-600 underline">System</a>) – or disable them under Tools:</p>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($unavailable as $tool)
                        <li class="badge bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"><x-icon name="alert" class="size-3.5" /> {{ $tool->name() }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
