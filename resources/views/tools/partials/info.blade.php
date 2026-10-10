<section class="container-page grid gap-8 pb-4 lg:grid-cols-[1fr_1fr]">
    <div class="card p-6 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">How to use {{ $tool->name() }}</h2>
        <ol class="mt-6 space-y-5">
            @foreach ($tool->steps() as $i => [$title, $text])
                <li class="flex gap-4">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="card p-6 sm:p-8">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Frequently asked questions</h2>
        <div class="mt-4 divide-y divide-slate-200 dark:divide-slate-800">
            @foreach ($tool->faq() as [$question, $answer])
                <details class="group py-3" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium text-slate-900 dark:text-white">
                        {{ $question }}
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

@if ($tool->article())
    <section class="container-page pt-4 pb-4" aria-label="About {{ $tool->name() }}">
        <article class="card prose-page mx-auto max-w-none p-6 sm:p-8">
            {!! $tool->articleHtml() !!}
        </article>
    </section>
@endif

<x-ad position="tool_content" />

@if ($related->isNotEmpty())
    <section class="container-page py-10" aria-labelledby="related-heading">
        <h2 id="related-heading" class="text-xl font-bold text-slate-900 dark:text-white">Related tools</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($related as $relatedTool)
                <x-tool-card :tool="$relatedTool" />
            @endforeach
        </div>
    </section>
@endif
