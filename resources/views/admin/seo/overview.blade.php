@extends('layouts.admin')

@section('title', 'SEO')

@section('content')
    @include('admin.seo._tabs')

    @php
        $score = $audit['score'];
        $tone = $score >= 80 ? ['Good', 'text-emerald-600 dark:text-emerald-400', 'bg-emerald-500'] : ($score >= 50 ? ['Needs work', 'text-amber-600 dark:text-amber-400', 'bg-amber-500'] : ['Poor', 'text-rose-600 dark:text-rose-400', 'bg-rose-500']);
        $statusStyle = [
            'ok' => ['check-circle', 'text-emerald-600 dark:text-emerald-400', 'Passed'],
            'warn' => ['alert', 'text-amber-600 dark:text-amber-400', 'Improve'],
            'error' => ['alert', 'text-rose-600 dark:text-rose-400', 'Problem'],
            'info' => ['info', 'text-sky-600 dark:text-sky-400', 'Info'],
        ];
        $worst = collect($audit['tools'])->sortBy('score')->take(8);
    @endphp

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card p-6" aria-labelledby="score-title">
            <h2 id="score-title" class="text-sm font-medium text-slate-500">Overall SEO score</h2>
            <p class="mt-2 flex items-baseline gap-2">
                <span class="text-5xl font-extrabold text-slate-900 tabular-nums dark:text-white">{{ $score }}</span>
                <span class="text-lg text-slate-400">/ 100</span>
            </p>
            <p class="mt-1 text-sm font-semibold {{ $tone[1] }}">{{ $tone[0] }}</p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $score }}" aria-label="SEO score">
                <div class="h-full rounded-full {{ $tone[2] }}" style="width: {{ $score }}%"></div>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <dt class="text-xs text-slate-500">Tool pages (avg.)</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-900 tabular-nums dark:text-white">{{ $audit['average'] }}/100</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <dt class="text-xs text-slate-500">Site checks passed</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-900 tabular-nums dark:text-white">{{ collect($audit['checks'])->where('status', 'ok')->count() }}/{{ count($audit['checks']) }}</dd>
                </div>
            </dl>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('sitemap') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="map" class="size-4" /> sitemap.xml</a>
                <a href="{{ route('robots') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="file-text" class="size-4" /> robots.txt</a>
            </div>
        </section>

        <section class="card p-6 xl:col-span-2" aria-labelledby="checks-title">
            <h2 id="checks-title" class="font-semibold text-slate-900 dark:text-white">Site checklist</h2>
            <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($audit['checks'] as $check)
                    @php [$icon, $color, $statusLabel] = $statusStyle[$check['status']]; @endphp
                    <li class="flex items-start gap-3 py-3">
                        <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0 {{ $color }}" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $check['title'] }} <span class="sr-only">({{ $statusLabel }})</span></p>
                            <p class="mt-0.5 text-xs break-words text-slate-500">{{ $check['detail'] }}</p>
                        </div>
                        @if ($check['route'] && $check['status'] !== 'ok')
                            <a href="{{ route($check['route']) }}" class="btn btn-ghost btn-sm shrink-0">Fix <x-icon name="arrow-right" class="size-3.5" /></a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="card mt-6 overflow-hidden" aria-labelledby="worst-title">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 id="worst-title" class="font-semibold text-slate-900 dark:text-white">Tool pages to improve first</h2>
                <p class="text-xs text-slate-500">Add a focus keyword, a 300+ word article and FAQ questions to rank better.</p>
            </div>
            <a href="{{ route('admin.seo.tools') }}" class="btn btn-secondary btn-sm">All tool pages <x-icon name="arrow-right" class="size-3.5" /></a>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($worst as $slug => $row)
                @include('admin.seo._tool-row', ['row' => $row])
            @endforeach
        </ul>
    </section>
@endsection
