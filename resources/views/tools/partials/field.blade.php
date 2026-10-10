@php
    /** @var array $field */
    $key = $field['key'];
    $id = 'opt-'.$key;
    $type = $field['type'];
    $default = $field['default'];
    $showIf = $field['show_if'] ?? null;
    $requires = $field['requires'] ?? [];
@endphp
<div class="{{ $type === 'hidden' ? 'hidden' : '' }} {{ in_array($type, ['choice', 'textarea', 'metadata', 'position', 'pages']) || ! empty($field['cards']) ? 'sm:col-span-2' : '' }}"
     data-field="{{ $key }}" @if ($showIf) data-show-if='@json($showIf)' @endif>
    @switch($type)
        @case('choice')
            <span class="label">{{ $field['label'] }}</span>
            @if (! empty($field['cards']))
                <div class="grid gap-2 {{ count($field['choices']) > 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2' }}" role="radiogroup" aria-label="{{ $field['label'] }}">
                    @foreach ($field['choices'] as $value => $choice)
                        @php $missing = isset($requires[$value]) && ! $capabilities->has($requires[$value]); @endphp
                        <label class="choice">
                            <input type="radio" name="{{ $key }}" value="{{ $value }}" class="mt-0.5 accent-brand-600" data-option="{{ $key }}" @checked((string) $default === (string) $value) @disabled($missing)>
                            <span>
                                <span class="block font-semibold text-slate-900 dark:text-white">{{ $choice['label'] }}</span>
                                @if ($choice['hint'])<span class="block text-xs text-slate-500 dark:text-slate-400">{{ $choice['hint'] }}</span>@endif
                                @if ($missing)<span class="mt-1 block text-xs text-amber-600">Not available on this server</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @else
                <div class="segmented" role="radiogroup" aria-label="{{ $field['label'] }}">
                    @foreach ($field['choices'] as $value => $choice)
                        <label>
                            <input type="radio" name="{{ $key }}" value="{{ $value }}" class="sr-only" data-option="{{ $key }}" @checked((string) $default === (string) $value)>
                            {{ $choice['label'] }}
                        </label>
                    @endforeach
                </div>
            @endif
            @break

        @case('select')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <select id="{{ $id }}" name="{{ $key }}" class="input" data-option="{{ $key }}">
                @foreach ($field['choices'] as $value => $choice)
                    <option value="{{ $value }}" @selected((string) $default === (string) $value)>{{ $choice['label'] }}</option>
                @endforeach
            </select>
            @break

        @case('ocr-language')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <select id="{{ $id }}" name="{{ $key }}" class="input" data-option="{{ $key }}">
                @foreach (config('pdftoolkit.ocr.languages') as $code => $name)
                    <option value="{{ $code }}" @selected($default === $code)>{{ $name }}</option>
                @endforeach
                <option value="eng+urd">English + Urdu</option>
                <option value="eng+ara">English + Arabic</option>
                <option value="eng+hin">English + Hindi</option>
            </select>
            @break

        @case('number')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <div class="relative">
                <input id="{{ $id }}" type="number" name="{{ $key }}" class="input {{ isset($field['unit']) ? 'pr-24' : '' }}" data-option="{{ $key }}"
                       value="{{ $default }}" min="{{ $field['min'] ?? '' }}" max="{{ $field['max'] ?? '' }}" step="{{ $field['step'] ?? 1 }}" inputmode="numeric">
                @isset($field['unit'])
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $field['unit'] }}</span>
                @endisset
            </div>
            @break

        @case('range')
            <div class="flex items-center justify-between">
                <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
                <output class="text-sm font-semibold text-brand-600 dark:text-brand-400" data-range-output="{{ $key }}">{{ $default }}{{ isset($field['unit']) && strlen($field['unit']) < 3 ? $field['unit'] : '' }}</output>
            </div>
            <input id="{{ $id }}" type="range" name="{{ $key }}" class="w-full accent-brand-600" data-option="{{ $key }}" data-unit="{{ isset($field['unit']) && strlen($field['unit']) < 3 ? $field['unit'] : '' }}"
                   value="{{ $default }}" min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? 100 }}" step="{{ $field['step'] ?? 1 }}">
            @if (isset($field['unit']) && strlen($field['unit']) >= 3)
                <p class="help">{{ $field['unit'] }}</p>
            @endif
            @break

        @case('color')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <div class="flex items-center gap-2">
                <input id="{{ $id }}" type="color" name="{{ $key }}" value="{{ $default }}" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-900" data-option="{{ $key }}">
                <span class="font-mono text-sm text-slate-500" data-color-value="{{ $key }}">{{ $default }}</span>
            </div>
            @break

        @case('toggle')
            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-3.5 py-3 dark:border-slate-700">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $field['label'] }}</span>
                <input type="checkbox" name="{{ $key }}" class="toggle" data-option="{{ $key }}" @checked($default)>
            </label>
            @break

        @case('password')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <div class="relative">
                <input id="{{ $id }}" type="password" name="{{ $key }}" class="input pr-11" data-option="{{ $key }}" autocomplete="new-password"
                       maxlength="{{ $field['max'] ?? 128 }}" @if (! empty($field['required'])) required minlength="{{ $field['min'] ?? 1 }}" @endif>
                <button type="button" class="absolute inset-y-0 right-1 my-auto btn-icon size-8" data-toggle-password="{{ $id }}" aria-label="Show password"><x-icon name="eye" class="size-4" /></button>
            </div>
            @if (! empty($field['confirm']))
                <label for="{{ $id }}-confirm" class="label mt-3">Confirm password</label>
                <input id="{{ $id }}-confirm" type="password" class="input" data-confirm-for="{{ $key }}" autocomplete="new-password" maxlength="{{ $field['max'] ?? 128 }}">
            @endif
            @break

        @case('pages')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <div class="flex gap-2">
                <input id="{{ $id }}" type="text" name="{{ $key }}" class="input" data-option="{{ $key }}" placeholder="{{ $field['placeholder'] ?? '' }}" autocomplete="off" value="{{ $default }}">
                @if (! empty($field['picker']))
                    <button type="button" class="btn btn-secondary shrink-0" data-open-picker="{{ $key }}"><x-icon name="grid" class="size-4" /> <span class="hidden sm:inline">Select visually</span></button>
                @endif
            </div>
            @if (! empty($field['picker']))
                <div class="mt-3 hidden" data-page-picker="{{ $key }}"></div>
            @endif
            @break

        @case('position')
            <span class="label">{{ $field['label'] }}</span>
            @php
                $rows = $field['rows'] ?? ['top', 'middle', 'bottom'];
            @endphp
            <div class="flex flex-wrap items-center gap-4">
                <div class="inline-grid grid-cols-3 gap-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800/50" role="radiogroup" aria-label="{{ $field['label'] }}">
                    @foreach ($rows as $row)
                        @foreach (['left', 'center', 'right'] as $col)
                            <label class="cursor-pointer" title="{{ ucfirst($row) }} {{ $col }}">
                                <input type="radio" name="{{ $key }}" value="{{ $row }}-{{ $col }}" class="peer sr-only" data-option="{{ $key }}" @checked($default === "{$row}-{$col}")>
                                <span class="flex size-9 items-center justify-center rounded-lg border border-slate-300 bg-white transition peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:[&>span]:bg-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                                    <span class="size-2 rounded-full bg-slate-400"></span>
                                </span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
                @if (! empty($field['tile']))
                    <label class="choice">
                        <input type="radio" name="{{ $key }}" value="tile" class="mt-0.5 accent-brand-600" data-option="{{ $key }}" @checked($default === 'tile')>
                        <span><span class="block font-semibold">Tile</span><span class="block text-xs text-slate-500">Repeat across the page</span></span>
                    </label>
                @endif
            </div>
            @break

        @case('textarea')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <textarea id="{{ $id }}" name="{{ $key }}" rows="{{ $field['rows'] ?? 5 }}" class="input font-mono text-[13px]" data-option="{{ $key }}" maxlength="{{ $field['max'] ?? 100000 }}" placeholder="{{ $field['placeholder'] ?? '' }}"></textarea>
            @break

        @case('file')
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <input id="{{ $id }}" type="file" accept="{{ $field['accept'] ?? '' }}" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-slate-300" data-option-file="{{ $key }}">
            @break

        @case('metadata')
            <fieldset class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                <legend class="px-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $field['label'] }}</legend>
                <div class="grid gap-3 sm:grid-cols-2" data-metadata-fields>
                    @foreach (['title' => 'Title', 'author' => 'Author', 'subject' => 'Subject', 'keywords' => 'Keywords', 'creator' => 'Creator (application)', 'producer' => 'Producer'] as $metaKey => $metaLabel)
                        <div>
                            <label for="meta-{{ $metaKey }}" class="label">{{ $metaLabel }}</label>
                            <input id="meta-{{ $metaKey }}" type="text" class="input" data-meta="{{ $metaKey }}" maxlength="500">
                        </div>
                    @endforeach
                    <div class="text-xs text-slate-500 sm:col-span-2" data-meta-dates></div>
                </div>
            </fieldset>
            @break

        @case('hidden')
            <input type="hidden" name="{{ $key }}" value="{{ $default }}" data-option="{{ $key }}">
            @break

        @default
            <label for="{{ $id }}" class="label">{{ $field['label'] }}</label>
            <input id="{{ $id }}" type="text" name="{{ $key }}" class="input" data-option="{{ $key }}" value="{{ $default }}" maxlength="{{ $field['max'] ?? 1000 }}" placeholder="{{ $field['placeholder'] ?? '' }}" autocomplete="off">
    @endswitch

    @if (! empty($field['help']) && $type !== 'range')
        <p class="help">{{ $field['help'] }}</p>
    @endif
</div>
