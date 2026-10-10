@extends('layouts.admin')

@section('title', 'System')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.system.refresh') }}">@csrf <button class="btn btn-secondary"><x-icon name="refresh" class="size-4" /> Detect software again</button></form>
            <form method="POST" action="{{ route('admin.system.cleanup') }}">@csrf <button class="btn btn-secondary"><x-icon name="trash" class="size-4" /> Delete expired temporary files</button></form>
        </div>

        <section class="card overflow-hidden">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Open-source binaries</h2>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-500 dark:bg-slate-900/60">
                    <tr><th class="px-5 py-2 font-medium">Software</th><th class="px-5 py-2 font-medium">Status</th><th class="hidden px-5 py-2 font-medium md:table-cell">Path</th><th class="px-5 py-2 font-medium">Used for</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @php
                        $uses = [
                            'qpdf' => 'Merge, split, page tools, protect/unlock, optimize, flatten (server)',
                            'ghostscript' => 'Compression, grayscale, page rendering',
                            'pdftotext' => 'Text extraction, PDF to Excel/CSV',
                            'pdftoppm' => 'Page rendering (images, OCR, PowerPoint)',
                            'pdfimages' => 'Extract images (server)',
                            'pdftohtml' => 'PDF to HTML (server)',
                            'libreoffice' => 'Word/Excel/PowerPoint to PDF, layout PDF to Word',
                            'tesseract' => 'OCR (server)',
                        ];
                    @endphp
                    @foreach ($report['binaries'] as $name => $binary)
                        <tr>
                            <td class="px-5 py-2.5 font-medium">{{ $name }}</td>
                            <td class="px-5 py-2.5">
                                @if ($binary['available'])
                                    <span class="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><x-icon name="check" class="size-3.5" /> {{ $binary['version'] }}</span>
                                @else
                                    <span class="badge bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Not installed</span>
                                @endif
                            </td>
                            <td class="hidden max-w-xs truncate px-5 py-2.5 font-mono text-xs text-slate-500 md:table-cell">{{ $binary['path'] ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-500">{{ $uses[$name] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:border-slate-800">Missing software only disables the server engine of a tool – most tools also run in the browser. Set custom paths in <code class="kbd">.env</code> (e.g. <code class="kbd">GHOSTSCRIPT_PATH</code>) and click "Detect software again".</p>
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="card p-5">
                <h2 class="font-semibold text-slate-900 dark:text-white">PHP</h2>
                <dl class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
                    @foreach ($php as $key => $value)
                        <dt class="text-slate-500">{{ $key }}</dt><dd class="font-mono text-xs">{{ $value }}</dd>
                    @endforeach
                    @foreach ($report['php'] as $ext => $ok)
                        <dt class="text-slate-500">{{ $ext }}</dt><dd>{!! $ok ? '<span class="text-emerald-600">✔</span>' : '<span class="text-amber-600">missing</span>' !!}</dd>
                    @endforeach
                </dl>
            </section>
            <section class="card p-5">
                <h2 class="font-semibold text-slate-900 dark:text-white">Processing</h2>
                <dl class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Queue connection</dt><dd class="font-mono text-xs">{{ $queue }}</dd>
                    <dt class="text-slate-500">Temporary folder</dt><dd class="truncate font-mono text-xs" title="{{ $tempPath }}">{{ $tempPath }}</dd>
                    <dt class="text-slate-500">Active job folders</dt><dd>{{ $jobs }}</dd>
                    <dt class="text-slate-500">Job lifetime</dt><dd>{{ config('pdftoolkit.job_ttl') }} minutes</dd>
                    <dt class="text-slate-500">Translation driver</dt><dd>{{ $translation['driver'] }} {!! $translation['available'] ? '<span class="text-emerald-600">✔ online</span>' : '<span class="text-amber-600">(browser fallback)</span>' !!}</dd>
                    <dt class="text-slate-500">OCR languages (server)</dt><dd class="text-xs">{{ implode(', ', $report['tesseract_languages']) ?: '—' }}</dd>
                    <dt class="text-slate-500">Detected</dt><dd class="text-xs">{{ \Illuminate\Support\Carbon::parse($report['detected_at'])->diffForHumans() }}</dd>
                </dl>
            </section>
        </div>

        <section class="card overflow-hidden">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Tool engines</h2>
            <div class="grid gap-x-6 px-5 py-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($engines as $row)
                    <div class="flex items-center justify-between border-b border-slate-100 py-1.5 dark:border-slate-800">
                        <span class="truncate">{{ $row['tool']->name() }}</span>
                        <span class="ml-2 shrink-0 text-xs {{ $row['engines'] === [] ? 'text-amber-600' : 'text-slate-500' }}">{{ $row['engines'] === [] ? 'unavailable' : implode(' → ', $row['engines']) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
