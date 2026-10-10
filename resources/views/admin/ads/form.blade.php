@extends('layouts.admin')

@use('App\Services\Ads\AdCatalog')

@section('title', $unit->exists ? 'Edit ad unit' : 'New ad unit')

@php
    $network = old('network', $unit->network);
    $format = old('format', $unit->format);
    $placement = old('placement', $unit->placement);
    $scope = old('page_scope', $unit->page_scope);
    $selectedTools = old('tools', $unit->tools ?? []);
@endphp

@section('content')
    <form method="POST" action="{{ $unit->exists ? route('admin.ads.update', $unit) : route('admin.ads.store') }}" class="mx-auto max-w-4xl space-y-6" data-ad-form data-catalog='@json($catalog)'>
        @csrf
        @if ($unit->exists)
            @method('PUT')
        @endif

        <a href="{{ route('admin.ads') }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" /> All ad units</a>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Ad unit</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name (only shown in the admin)</label>
                    <input id="name" name="name" class="input" value="{{ old('name', $unit->name) }}" required maxlength="100">
                </div>
                <div>
                    <label class="label" for="network">Ad network</label>
                    <select id="network" name="network" class="input" data-ad-network>
                        @foreach (AdCatalog::NETWORKS as $key => $definition)
                            <option value="{{ $key }}" @selected($network === $key)>{{ $definition['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="format">Ad format</label>
                    <select id="format" name="format" class="input" data-ad-format>
                        @foreach (AdCatalog::FORMATS as $key => $definition)
                            <option value="{{ $key }}" @selected($format === $key) @if (! in_array($key, AdCatalog::NETWORKS[$network]['formats'] ?? [], true)) hidden disabled @endif>{{ AdCatalog::formatLabel($key, $network) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 flex gap-3 rounded-xl bg-sky-50 p-4 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                    <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
                    <p data-ad-help>{{ AdCatalog::help($network, $format) }}</p>
                </div>
                <div class="sm:col-span-2" data-ad-show="banner">
                    <label class="label" for="size">Banner size</label>
                    <select id="size" name="size" class="input sm:w-1/2">
                        @foreach (AdCatalog::SIZES as $key => $label)
                            <option value="{{ $key }}" @selected(old('size', $unit->option('size', '300x250')) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help">Space for the banner is reserved while it loads, so the page does not jump.</p>
                </div>
            </div>
        </section>

        <section class="card p-6" data-ad-hide="direct_link">
            <label class="label" for="code">Ad code</label>
            <textarea id="code" name="code" rows="10" class="input font-mono text-xs" placeholder="&lt;script type=&quot;text/javascript&quot; src=&quot;//…&quot;&gt;&lt;/script&gt;" spellcheck="false">{{ old('code', $unit->code) }}</textarea>
            <p class="help">Paste the complete code exactly as your ad network shows it. It is added to the page unchanged.</p>
        </section>

        <section class="card p-6" data-ad-show="direct_link">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Direct link</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="url">Direct Link URL</label>
                    <input id="url" name="url" type="url" class="input font-mono text-sm" value="{{ old('url', $unit->url) }}" placeholder="https://…" maxlength="2048">
                </div>
                <div data-ad-link-mode="download">
                    <label class="label" for="frequency">Open at most every … minutes</label>
                    <input id="frequency" name="frequency" type="number" min="0" max="10080" class="input" value="{{ old('frequency', $unit->option('frequency', 30)) }}">
                    <p class="help">Per visitor. 0 = on every download click.</p>
                </div>
                <div data-ad-link-mode="slot">
                    <label class="label" for="label">Button text</label>
                    <input id="label" name="label" class="input" value="{{ old('label', $unit->option('label', 'Sponsored link')) }}" maxlength="60">
                </div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Where and to whom</h2>
            <div class="mt-5 grid gap-6">
                <div>
                    <label class="label" for="placement">Position</label>
                    <select id="placement" name="placement" class="input" data-ad-placement>
                        @foreach (['script' => 'Invisible scripts', 'slot' => 'Visible positions', 'link' => 'Direct link'] as $group => $groupLabel)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach (AdCatalog::PLACEMENTS as $key => $definition)
                                    @continue($definition['group'] !== $group)
                                    <option value="{{ $key }}" @selected($placement === $key) @disabled(! in_array($key, AdCatalog::placementsFor($format), true))>{{ $definition['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="help">Use “Preview ad positions” on the Advertising page to see every position on the live site.</p>
                </div>

                <fieldset>
                    <legend class="label">Devices</legend>
                    <div class="segmented">
                        @foreach (AdCatalog::DEVICES as $key => $label)
                            <label><input type="radio" name="devices" value="{{ $key }}" class="sr-only" @checked(old('devices', $unit->devices) === $key)> {{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label">Pages</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (AdCatalog::PAGE_SCOPES as $key => $label)
                            <label class="choice"><input type="radio" name="page_scope" value="{{ $key }}" class="mt-0.5 accent-brand-600" data-ad-scope @checked($scope === $key)> <span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    <div class="mt-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-ad-tools>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Tools</p>
                            <input type="search" class="input h-8 max-w-48 py-1 text-xs" placeholder="Filter…" data-ad-tools-filter aria-label="Filter tools">
                        </div>
                        <div class="grid max-h-64 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($tools as $slug => $tool)
                                <label class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-ad-tool="{{ strtolower($tool->name().' '.$slug) }}">
                                    <input type="checkbox" name="tools[]" value="{{ $slug }}" class="accent-brand-600" @checked(in_array($slug, (array) $selectedTools, true))> {{ $tool->name() }}
                                </label>
                            @endforeach
                        </div>
                        <p class="help">Tip: exclude the PDF Editor for Social Bar / In-Page Push so they never cover the editor toolbar.</p>
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="card p-6">
            <div class="grid gap-5 sm:grid-cols-[1fr_12rem]">
                <x-admin.toggle name="is_active" label="Active" hint="Paused units are kept but not shown." :checked="old('is_active', $unit->is_active)" />
                <div>
                    <label class="label" for="sort_order">Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="-1000" max="1000" class="input" value="{{ old('sort_order', $unit->sort_order) }}">
                    <p class="help">Lower numbers come first in a position.</p>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.ads') }}" class="btn btn-secondary btn-lg">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> {{ $unit->exists ? 'Save ad unit' : 'Create ad unit' }}</button>
        </div>
    </form>
@endsection
