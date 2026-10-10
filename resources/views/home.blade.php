@extends('layouts.app')

@section('page', 'home')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60rem_30rem_at_50%_-10%,var(--color-brand-200),transparent)] opacity-70 dark:bg-[radial-gradient(60rem_30rem_at_50%_-10%,var(--color-brand-900),transparent)]"></div>
        <div class="pointer-events-none absolute -top-24 right-[-10rem] -z-10 size-[28rem] rounded-full bg-rose-300/30 blur-3xl dark:bg-rose-500/10"></div>

        <div class="container-page pt-14 pb-16 sm:pt-20">
            <div class="mx-auto max-w-3xl text-center">
                <span class="badge bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                    <x-icon name="sparkles" class="size-3.5" /> {{ $toolCount }} free tools · No sign-up · No watermarks
                </span>
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                    All Your PDF Tools <span class="bg-linear-to-r from-rose-500 via-brand-600 to-violet-600 bg-clip-text text-transparent">in One Place</span>
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                    Free, fast and easy PDF tools. Edit, convert, compress, merge, split and translate your PDF files directly from your browser.
                </p>
            </div>

            <div class="mx-auto mt-10 max-w-3xl" id="home-upload">
                <div class="dropzone shadow-soft" data-home-dropzone tabindex="0" role="button" aria-label="Upload a file">
                    <span class="flex size-16 items-center justify-center rounded-2xl bg-linear-to-br from-rose-500 to-brand-600 text-white shadow-lg shadow-brand-500/30">
                        <x-icon name="cloud-upload" class="size-8" />
                    </span>
                    <p class="mt-5 text-xl font-semibold text-slate-900 dark:text-white">Drag &amp; Drop Your PDF Here</p>
                    <p class="my-3 text-sm font-medium tracking-widest text-slate-400 uppercase">or</p>
                    <button type="button" class="btn btn-primary btn-lg" data-home-pick>
                        <x-icon name="folder-open" class="size-5" /> Choose File
                    </button>
                    <input type="file" class="sr-only" data-home-input multiple
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.odt,.rtf,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.odp,.html,.htm,.txt">
                    <p class="mt-5 text-xs text-slate-500 dark:text-slate-400">
                        Supported: PDF, JPG, PNG, WEBP, Word, Excel, PowerPoint, HTML, TXT · Max {{ format_bytes($maxUpload, 0) }} per file
                    </p>
                </div>
                <x-privacy-note class="mt-4" />
            </div>

            <div class="mx-auto mt-10 grid max-w-4xl grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                @foreach ([
                    ['shield-check', 'Private & secure', 'Files deleted automatically'],
                    ['user-x', 'No registration', 'Use every tool instantly'],
                    ['zap', 'Fast', 'Most tools run in your browser'],
                    ['smartphone', 'Works everywhere', 'Desktop, tablet & mobile'],
                ] as [$icon, $title, $text])
                    <div class="flex items-start gap-3 rounded-2xl border border-slate-200/70 bg-white/70 p-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/60">
                        <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-brand-600 dark:text-brand-400" />
                        <span><span class="block font-semibold text-slate-900 dark:text-white">{{ $title }}</span><span class="text-xs text-slate-500 dark:text-slate-400">{{ $text }}</span></span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Popular tools --}}
    <section class="container-page py-12" aria-labelledby="popular-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 id="popular-heading" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Most popular PDF tools</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-400">Everything you need to work with PDF files – free and without limits.</p>
            </div>
            <a href="{{ route('tools.index') }}" class="btn btn-secondary">View all {{ $toolCount }} tools <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($popular as $tool)
                <x-tool-card :tool="$tool" />
            @endforeach
        </div>
    </section>

    <x-ad position="home_middle" />

    {{-- Categories --}}
    <section class="container-page py-12" aria-labelledby="categories-heading">
        <h2 id="categories-heading" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Browse by category</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $key => $group)
                <div class="card p-5" style="--tool-color: {{ $group['category']['color'] }}">
                    <div class="flex items-center gap-3">
                        <span class="tool-icon size-11"><x-icon :name="$group['category']['icon']" class="size-5" /></span>
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ $group['category']['name'] }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $group['tools']->count() }} tools</p>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $group['category']['description'] }}</p>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($group['tools']->take(8) as $tool)
                            <li><a href="{{ $tool->url() }}" class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-brand-50 hover:text-brand-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300">{{ $tool->name() }}</a></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('tools.index') }}#{{ $key }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 dark:text-brand-400">See all <x-icon name="arrow-right" class="size-4" /></a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section class="container-page py-12" aria-labelledby="features-heading">
        <div class="mx-auto max-w-2xl text-center">
            <h2 id="features-heading" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Why people love {{ setting('site_name') }}</h2>
            <p class="mt-3 text-slate-600 dark:text-slate-400">Professional PDF tools built on proven open-source software – without accounts, subscriptions or hidden costs.</p>
        </div>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['shield-check', 'Privacy first', 'Many tools work entirely in your browser. Files processed on our server are deleted automatically after processing.'],
                ['heart', '100% free', 'Every tool is free with no registration, no subscription, no watermarks and no daily limits.'],
                ['zap', 'Fast processing', 'Browser-side processing means no upload wait. Heavy jobs run on optimized open-source engines.'],
                ['smartphone', 'Any device', 'A responsive interface that works on Windows, macOS, Linux, Android and iPhone.'],
                ['translate', '16 languages', 'Translate PDFs into English, Urdu, Hindi, Arabic, Chinese, Spanish and more with open-source engines.'],
                ['code', 'Open-source inside', 'Powered by PDF.js, pdf-lib, qpdf, Ghostscript, LibreOffice and Tesseract OCR.'],
            ] as [$icon, $title, $text])
                <div class="card p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"><x-icon :name="$icon" class="size-5" /></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="container-page py-12" aria-labelledby="how-heading">
        <div class="card overflow-hidden p-8 sm:p-10">
            <h2 id="how-heading" class="text-center text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">How it works</h2>
            <ol class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ([
                    ['Choose a tool', 'Pick one of our PDF tools or simply drop your file above.'],
                    ['Upload your file', 'Drag and drop or choose a file. Adjust the options if you like.'],
                    ['Download the result', 'Your file is ready in seconds – and deleted from our server automatically.'],
                ] as $i => [$title, $text])
                    <li class="text-center">
                        <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-linear-to-br from-rose-500 to-brand-600 text-lg font-bold text-white">{{ $i + 1 }}</span>
                        <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="container-page py-12" aria-labelledby="faq-heading">
        <h2 id="faq-heading" class="text-center text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Frequently asked questions</h2>
        <div class="mx-auto mt-8 max-w-3xl divide-y divide-slate-200 dark:divide-slate-800">
            @foreach ([
                ['Are these PDF tools really free?', 'Yes. All tools are completely free to use. There is no registration, no subscription and no watermark.'],
                ['Is it safe to use?', 'Yes. Whenever possible files are processed directly in your browser and never uploaded. Files that need server processing are transferred over an encrypted connection, stored in a private location and deleted automatically after processing.'],
                ['Do I need to install anything?', 'No. Everything works in your web browser on any device.'],
                ['What is the maximum file size?', 'You can upload files up to '.format_bytes($maxUpload, 0).'. Browser-based tools can often handle even larger files.'],
            ] as [$q, $a])
                <details class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-slate-900 dark:text-white">
                        {{ $q }}
                        <x-icon name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Tool picker after dropping a file on the home page --}}
    <dialog id="tool-picker" class="m-auto w-[min(42rem,calc(100%-2rem))] rounded-3xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">What would you like to do?</h2>
                <p class="mt-1 truncate text-sm text-slate-500" data-picker-files></p>
            </div>
            <button type="button" class="btn-icon" data-picker-close aria-label="Close"><x-icon name="x" class="size-5" /></button>
        </div>
        <div class="grid max-h-[60vh] gap-2 overflow-y-auto p-4 sm:grid-cols-2" data-picker-tools></div>
        <div class="border-t border-slate-200 p-4 text-center dark:border-slate-800">
            <a href="{{ route('tools.index') }}" class="text-sm font-semibold text-brand-600 dark:text-brand-400">Browse all tools</a>
        </div>
    </dialog>

    <script type="application/json" id="quick-tools">@json($quickTools)</script>
@endsection
