@php
    $class = $tool->processorClass();
    $requirements = $class && class_exists($class) ? $class::requirements($tool->params()) : [];
    $names = [
        'libreoffice' => 'LibreOffice', 'ghostscript' => 'Ghostscript', 'qpdf' => 'qpdf', 'tesseract' => 'Tesseract OCR',
        'ocr' => 'Tesseract OCR', 'rasterizer' => 'Ghostscript or Poppler', 'pdfimages' => 'Poppler (pdfimages)', 'gd' => 'PHP GD',
    ];
    $needed = collect($requirements)->map(fn ($set) => collect($set)->map(fn ($n) => $names[$n] ?? $n)->implode(' + '))->implode(' or ');
@endphp
<div class="card p-8 text-center">
    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15">
        <x-icon name="server" class="size-7" />
    </span>
    <h2 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">This tool is temporarily unavailable</h2>
    <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600 dark:text-slate-400">
        The server is missing a component this tool needs. Please try one of the related tools below or come back later.
    </p>
    @if ($needed)
        <p class="mx-auto mt-4 max-w-lg rounded-xl bg-slate-100 px-4 py-2 text-xs text-slate-500 dark:bg-slate-800">
            Server requirement: <strong>{{ $needed }}</strong> – see README.md › “Server binaries”.
        </p>
    @endif
</div>
