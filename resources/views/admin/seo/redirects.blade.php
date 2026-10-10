@extends('layouts.admin')

@section('title', 'SEO · Redirects & 404')

@section('content')
    @include('admin.seo._tabs')

    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <section class="card self-start p-6" aria-labelledby="new-redirect">
            <h2 id="new-redirect" class="text-base font-semibold text-slate-900 dark:text-white">Add redirect</h2>
            <p class="mt-1 text-sm text-slate-500">Send visitors and search engines from an old or broken URL to the right page.</p>
            <form method="POST" action="{{ route('admin.seo.redirects.store') }}" class="mt-5 grid gap-4">
                @csrf
                <div>
                    <label class="label" for="source">Old URL (path)</label>
                    <input id="source" name="source" class="input font-mono text-sm" value="{{ old('source', $prefill) }}" placeholder="/old-page" required maxlength="255">
                </div>
                <div>
                    <label class="label" for="status_code">Type</label>
                    <select id="status_code" name="status_code" class="input" data-redirect-type>
                        @foreach (\App\Models\Redirect::CODES as $code => $label)
                            <option value="{{ $code }}" @selected((int) old('status_code', 301) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-redirect-target>
                    <label class="label" for="target">New URL</label>
                    <input id="target" name="target" class="input font-mono text-sm" value="{{ old('target') }}" placeholder="/merge-pdf or https://…" maxlength="2048">
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="size-4" /> Save redirect</button>
                <p class="help">Redirects apply to URLs that do not exist (anymore), e.g. a renamed or disabled tool.</p>
            </form>
        </section>

        <div class="min-w-0 space-y-6">
            <section class="card overflow-hidden" aria-labelledby="redirect-list">
                <h2 id="redirect-list" class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Redirects ({{ $redirects->total() }})</h2>
                @if ($redirects->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No redirects yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs text-slate-500 dark:bg-slate-900/60">
                                <tr>
                                    <th class="px-5 py-2 font-medium">From</th>
                                    <th class="px-3 py-2 font-medium">To</th>
                                    <th class="px-3 py-2 font-medium">Type</th>
                                    <th class="px-3 py-2 text-right font-medium">Hits</th>
                                    <th class="px-5 py-2"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($redirects as $redirect)
                                    <tr>
                                        <td class="max-w-56 truncate px-5 py-2.5 font-mono text-xs" title="{{ $redirect->source }}">{{ $redirect->source }}</td>
                                        <td class="max-w-64 truncate px-3 py-2.5 font-mono text-xs text-slate-500" title="{{ $redirect->target }}">{{ $redirect->target ?? '—' }}</td>
                                        <td class="px-3 py-2.5"><span class="badge bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $redirect->status_code }}</span></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($redirect->hits) }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            <form method="POST" action="{{ route('admin.seo.redirects.destroy', $redirect) }}" data-confirm="Delete this redirect?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon hover:text-rose-600" aria-label="Delete redirect {{ $redirect->source }}"><x-icon name="trash" class="size-4" /></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($redirects->hasPages())
                        <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm dark:border-slate-800">
                            @if ($redirects->onFirstPage())
                                <span></span>
                            @else
                                <a href="{{ $redirects->previousPageUrl() }}" class="btn btn-secondary btn-sm"><x-icon name="chevron-left" class="size-4" /> Previous</a>
                            @endif
                            <span class="text-xs text-slate-500">Page {{ $redirects->currentPage() }} of {{ $redirects->lastPage() }}</span>
                            @if ($redirects->hasMorePages())
                                <a href="{{ $redirects->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next <x-icon name="chevron-right" class="size-4" /></a>
                            @else
                                <span></span>
                            @endif
                        </div>
                    @endif
                @endif
            </section>

            <section class="card overflow-hidden" aria-labelledby="not-found">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 id="not-found" class="font-semibold text-slate-900 dark:text-white">404 monitor ({{ number_format($notFoundTotal) }})</h2>
                        <p class="text-xs text-slate-500">Missing pages visitors or crawlers asked for. Only the path and a counter are stored.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.seo.notfound.settings') }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="log_404" value="{{ $logEnabled ? 0 : 1 }}">
                            <button type="submit" class="btn btn-secondary btn-sm"><x-icon :name="$logEnabled ? 'pause' : 'play'" class="size-4" /> {{ $logEnabled ? 'Pause logging' : 'Resume logging' }}</button>
                        </form>
                        @if ($notFoundTotal)
                            <form method="POST" action="{{ route('admin.seo.notfound.clear') }}" data-confirm="Clear the whole 404 log?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="trash" class="size-4" /> Clear</button>
                            </form>
                        @endif
                    </div>
                </div>
                @if ($notFound->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $logEnabled ? 'No missing pages recorded.' : 'Logging is paused.' }}</p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($notFound as $entry)
                            <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                                <span class="min-w-0 flex-1 truncate font-mono text-xs" title="{{ $entry->path }}">{{ $entry->path }}</span>
                                <span class="text-xs text-slate-500 tabular-nums">{{ number_format($entry->hits) }}× · {{ $entry->last_seen_at?->diffForHumans() }}</span>
                                <a href="{{ route('admin.seo.redirects', ['source' => $entry->path]) }}#source" class="btn btn-secondary btn-sm"><x-icon name="redirect" class="size-4" /> Redirect</a>
                                <form method="POST" action="{{ route('admin.seo.notfound.destroy', $entry) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon" aria-label="Remove {{ $entry->path }} from the log"><x-icon name="x" class="size-4" /></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
