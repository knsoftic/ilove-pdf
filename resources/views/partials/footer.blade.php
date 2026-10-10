<footer class="mt-16 border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="container-page py-12">
        <div class="grid gap-10 lg:grid-cols-[1.2fr_3fr]">
            <div>
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-bold text-slate-900 dark:text-white">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-rose-500 to-brand-600 text-white">
                        <x-icon name="logo" class="size-5" :stroke="2.2" />
                    </span>
                    <span class="text-lg">{{ $site['name'] }}</span>
                </a>
                <p class="mt-4 max-w-sm text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $site['footer'] }}</p>
                <p class="mt-4 flex max-w-sm items-start gap-2 rounded-xl bg-emerald-50 p-3 text-xs leading-5 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0" />
                    {{ $site['privacy_notice'] }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:grid-cols-4">
                @foreach (collect($footerGroups)->take(7) as $group)
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $group['category']['name'] }}</h3>
                        <ul class="mt-3 space-y-2">
                            @foreach ($group['tools']->take(6) as $tool)
                                <li><a href="{{ $tool->url() }}" class="text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400">{{ $tool->name() }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Company</h3>
                    <ul class="mt-3 space-y-2">
                        <li><a href="{{ route('tools.index') }}" class="text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400">All tools</a></li>
                        <li><a href="{{ route('pages.about') }}" class="text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400">About</a></li>
                        <li><a href="{{ route('pages.privacy') }}" class="text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400">Privacy Policy</a></li>
                        <li><a href="{{ route('pages.terms') }}" class="text-sm text-slate-600 hover:text-brand-600 dark:text-slate-400">Terms of Use</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:text-slate-400">
            <p>&copy; {{ date('Y') }} {{ $site['name'] }}. Free and open-source PDF tools.</p>
            <p class="flex items-center gap-1.5">Built with open-source software: PDF.js, pdf-lib, qpdf, Ghostscript, LibreOffice, Tesseract.</p>
        </div>
    </div>
</footer>
