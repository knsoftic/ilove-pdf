@extends('layouts.admin')

@section('title', 'Advertising')

@use('App\Services\Ads\AdCatalog')

@php
    $active = $units->where('is_active', true)->count();
    $live = $s['ads_enabled'] && $active > 0;
    $byGroup = $units->groupBy(fn ($unit) => AdCatalog::placementGroup($unit->placement) ?? 'slot');
    $groupNames = ['script' => 'Scripts (popunder, social bar, push, MultiTag…)', 'slot' => 'Visible ad positions (banners, native)', 'link' => 'Direct links'];
@endphp

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        {{-- Status --}}
        <section @class([
            'card flex flex-wrap items-center gap-4 p-5',
            'border-emerald-200 dark:border-emerald-500/30' => $live,
        ])>
            <span @class([
                'flex size-11 items-center justify-center rounded-xl',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $live,
                'bg-slate-100 text-slate-500 dark:bg-slate-800' => ! $live,
            ])><x-icon name="megaphone" class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-slate-900 dark:text-white">
                    @if ($live)
                        Ads are live – {{ $active }} active {{ Str::plural('unit', $active) }}
                    @elseif (! $s['ads_enabled'])
                        Ads are switched off
                    @else
                        No active ad units yet
                    @endif
                </p>
                <p class="text-sm text-slate-500">
                    @if (! $s['ads_enabled'])
                        Add your ad units, then turn on “Show ads on the website” in the settings below.
                    @else
                        Ads are shown to visitors{{ $s['ads_hide_for_admin'] ? ' (not to you while signed in)' : '' }}.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('admin.ads.preview') }}" @unless (session('ads_preview')) target="_blank" @endunless>
                @csrf
                <button type="submit" class="btn btn-secondary"><x-icon name="eye" class="size-4" /> {{ session('ads_preview') ? 'Stop preview' : 'Preview ad positions' }}</button>
            </form>
            <a href="{{ route('admin.ads.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add ad unit</a>
        </section>

        {{-- Quick start --}}
        <section class="card p-5" aria-labelledby="quick-add">
            <h2 id="quick-add" class="font-semibold text-slate-900 dark:text-white">Quick add</h2>
            <p class="mt-1 text-sm text-slate-500">Pick the ad format you created in your ad network – the form is pre-filled with the recommended position.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach (['adsterra', 'monetag'] as $network)
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            <span class="size-2.5 rounded-full" style="background: {{ AdCatalog::NETWORKS[$network]['color'] }}"></span>
                            {{ AdCatalog::NETWORKS[$network]['name'] }}
                            <a href="{{ AdCatalog::NETWORKS[$network]['site'] }}" target="_blank" rel="noopener noreferrer" class="ml-auto text-xs font-normal text-slate-400 hover:text-brand-600">{{ parse_url(AdCatalog::NETWORKS[$network]['site'], PHP_URL_HOST) }} <x-icon name="external" class="inline size-3" /></a>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (collect($presets)->where('network', $network) as $preset)
                                <a href="{{ route('admin.ads.create', array_filter(['network' => $preset['network'], 'format' => $preset['format'], 'size' => $preset['size'] ?? null, 'devices' => $preset['devices'] ?? null])) }}" class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-brand-50 hover:text-brand-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300"><x-icon name="plus" class="size-3" /> {{ $preset['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-slate-500">Google AdSense or another network? Use <a href="{{ route('admin.ads.create', ['network' => 'adsense', 'format' => 'auto_ads']) }}" class="font-medium text-brand-600 dark:text-brand-400">AdSense</a> or <a href="{{ route('admin.ads.create', ['network' => 'custom', 'format' => 'custom']) }}" class="font-medium text-brand-600 dark:text-brand-400">custom code</a>.</p>
        </section>

        {{-- Units --}}
        @if ($units->isEmpty())
            <section class="card px-6 py-12 text-center">
                <x-icon name="megaphone" class="mx-auto size-10 text-slate-300 dark:text-slate-600" />
                <p class="mt-3 font-medium text-slate-900 dark:text-white">No ad units yet</p>
                <p class="mt-1 text-sm text-slate-500">Use “Quick add” above to paste your first Adsterra or Monetag code.</p>
            </section>
        @else
            @foreach ($groupNames as $group => $groupName)
                @continue(! isset($byGroup[$group]))
                <section class="card overflow-hidden">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm font-semibold text-slate-900 dark:border-slate-800 dark:bg-slate-900/60 dark:text-white">{{ $groupName }}</h2>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($byGroup[$group] as $unit)
                            @php $network = AdCatalog::NETWORKS[$unit->network] ?? AdCatalog::NETWORKS['custom']; @endphp
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 {{ $unit->is_active ? '' : 'opacity-60' }}">
                                <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $network['color'] }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-slate-900 dark:text-white">{{ $unit->name }}
                                        @unless ($unit->is_active)<span class="badge ml-1 bg-slate-100 text-slate-500 dark:bg-slate-800">paused</span>@endunless
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $network['name'] }} · {{ AdCatalog::formatLabel($unit->format, $unit->network) }}{{ $unit->option('size') ? ' '.str_replace('x', '×', $unit->option('size')) : '' }}
                                        · {{ AdCatalog::PLACEMENTS[$unit->placement]['label'] ?? $unit->placement }}
                                        · {{ AdCatalog::DEVICES[$unit->devices] ?? $unit->devices }}
                                        · {{ AdCatalog::PAGE_SCOPES[$unit->page_scope] ?? $unit->page_scope }}{{ $unit->tools ? ' ('.count($unit->tools).')' : '' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <form method="POST" action="{{ route('admin.ads.toggle', $unit) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-ghost btn-sm"><x-icon :name="$unit->is_active ? 'pause' : 'play'" class="size-4" /> {{ $unit->is_active ? 'Pause' : 'Activate' }}</button>
                                    </form>
                                    <a href="{{ route('admin.ads.edit', $unit) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" /> Edit</a>
                                    <form method="POST" action="{{ route('admin.ads.destroy', $unit) }}" data-confirm="Delete “{{ $unit->name }}”?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon hover:text-rose-600" aria-label="Delete {{ $unit->name }}"><x-icon name="trash" class="size-4" /></button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif

        {{-- Settings --}}
        <form id="settings" method="POST" action="{{ route('admin.ads.settings') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="card p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Settings</h2>
                <div class="mt-5 grid gap-3">
                    <x-admin.toggle name="ads_enabled" label="Show ads on the website" hint="Master switch for all ad units. ads.txt and the service worker file stay available when it is off." :checked="$s['ads_enabled']" />
                    <x-admin.toggle name="ads_label" label="Show an “Advertisement” label above visible ads" hint="Recommended – visitors trust clearly marked ads more." :checked="$s['ads_label']" />
                    <x-admin.toggle name="ads_hide_for_admin" label="Hide ads while I am signed in to the admin" hint="Avoids accidental own impressions and clicks (which ad networks may treat as invalid traffic)." :checked="$s['ads_hide_for_admin']" />
                </div>
            </section>

            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">ads.txt</h2>
                        <p class="mt-1 text-sm text-slate-500">Authorised sellers file served at <code class="text-xs">{{ url('ads.txt') }}</code>. Paste the lines your ad networks give you.</p>
                    </div>
                    @if ($s['ads_txt'])
                        <a href="{{ route('ads.txt') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="external" class="size-4" /> Open</a>
                    @endif
                </div>
                <textarea name="ads_txt" rows="5" class="input mt-4 font-mono text-xs" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0">{{ old('ads_txt', $s['ads_txt']) }}</textarea>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Service worker (Monetag Push Notifications &amp; site verification)</h2>
                <p class="mt-1 text-sm text-slate-500">Monetag asks you to upload a file such as <code class="text-xs">sw.js</code> to the root of your site. Paste its content here instead – it is served at <code class="text-xs">{{ url($s['sw_filename'] ?: 'sw.js') }}</code>.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)]">
                    <div>
                        <label class="label" for="sw_filename">File name</label>
                        <input id="sw_filename" name="sw_filename" class="input font-mono text-sm" value="{{ old('sw_filename', $s['sw_filename'] ?: 'sw.js') }}" required maxlength="60">
                    </div>
                    <div>
                        <label class="label" for="sw_code">File content</label>
                        <textarea id="sw_code" name="sw_code" rows="5" class="input font-mono text-xs" placeholder="self.options = { … }&#10;importScripts('https://…/sw.js')">{{ old('sw_code', $s['sw_code']) }}</textarea>
                    </div>
                </div>
                <p class="help mt-3">A service worker from an ad network runs on your whole domain. Only paste code you received from your ad network’s dashboard.</p>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save settings</button>
            </div>
        </form>

        {{-- Guides --}}
        <section class="card p-6" aria-labelledby="guides">
            <h2 id="guides" class="text-base font-semibold text-slate-900 dark:text-white">Setup guides</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" open>
                    <summary class="cursor-pointer font-medium text-slate-900 dark:text-white">Adsterra</summary>
                    <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-slate-600 dark:text-slate-300">
                        <li>Create a publisher account at adsterra.com and add your website (your public domain, not localhost).</li>
                        <li>After approval, open <em>Websites</em> and click <em>Get code</em> next to a format (Popunder, Social Bar, Native Banner, Banner or Direct Link).</li>
                        <li>Click the matching “Quick add” button above and paste the code. The recommended position is pre-selected.</li>
                        <li>Banners: create 728×90 for desktop and 320×50 or 300×250 for mobile and target them with “Devices”.</li>
                        <li>Turn on “Show ads on the website” and use “Preview ad positions” to check the layout.</li>
                    </ol>
                </details>
                <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" open>
                    <summary class="cursor-pointer font-medium text-slate-900 dark:text-white">Monetag</summary>
                    <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-slate-600 dark:text-slate-300">
                        <li>Create a publisher account at monetag.com and add your site.</li>
                        <li>Verify the site: copy the <code class="text-xs">&lt;meta name="monetag"&gt;</code> tag into <a href="{{ route('admin.seo.verification') }}" class="font-medium text-brand-600 dark:text-brand-400">SEO → Verification</a>, or paste the sw.js content in the service worker box above.</li>
                        <li>Create a zone – MultiTag is easiest (it combines OnClick, Push, In-Page Push and Vignette) – and paste its tag via “Quick add”.</li>
                        <li>For Push Notifications, keep the service worker file configured above.</li>
                        <li>Turn on “Show ads on the website”.</li>
                    </ol>
                </details>
            </div>
            <p class="mt-4 text-xs text-slate-500">Ad networks place cookies and may show pop-ups. Update your <a href="{{ route('admin.content') }}" class="font-medium text-brand-600 dark:text-brand-400">privacy policy</a> and keep SECURITY_CSP in mind: when ads are active the Content-Security-Policy is relaxed automatically.</p>
        </section>
    </div>
@endsection
