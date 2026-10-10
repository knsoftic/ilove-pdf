@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">General</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="site_name">Website name</label>
                    <input id="site_name" name="site_name" class="input" value="{{ old('site_name', $s['site_name']) }}" required maxlength="80">
                </div>
                <div>
                    <label class="label" for="site_tagline">Tagline</label>
                    <input id="site_tagline" name="site_tagline" class="input" value="{{ old('site_tagline', $s['site_tagline']) }}" maxlength="120">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="footer_text">Footer text</label>
                    <textarea id="footer_text" name="footer_text" rows="2" class="input" maxlength="500">{{ old('footer_text', $s['footer_text']) }}</textarea>
                </div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Branding</h2>
            <p class="mt-1 text-sm text-slate-500">PNG, JPG or WEBP. SVG is not accepted for security reasons.</p>
            <div class="mt-5 grid gap-6 sm:grid-cols-3">
                @foreach ([['logo', 'logo_path', 'Logo', 'Shown in the header (height 32px)'], ['favicon', 'favicon_path', 'Favicon', 'PNG or ICO, square'], ['og_image', 'og_image_path', 'Social sharing image', '1200 × 630 px recommended']] as [$input, $key, $label, $hint])
                    <div>
                        <span class="label">{{ $label }}</span>
                        <div class="flex h-24 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800/50">
                            @if ($s[$key])
                                <img src="{{ asset($s[$key]) }}" alt="{{ $label }}" class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-xs text-slate-400">Default</span>
                            @endif
                        </div>
                        <input type="file" name="{{ $input }}" accept="image/png,image/jpeg,image/webp{{ $input === 'favicon' ? ',.ico' : '' }}" class="mt-2 block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700">
                        <p class="help">{{ $hint }}</p>
                        @if ($s[$key])
                            <label class="mt-1 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300"><input type="checkbox" name="remove_{{ $input }}" value="1" class="accent-rose-600"> Remove</label>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Uploads &amp; processing</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="max_upload_mb">Maximum upload size (MB)</label>
                    <input id="max_upload_mb" type="number" name="max_upload_mb" class="input" min="1" max="2048" value="{{ old('max_upload_mb', $s['max_upload_mb'] ?? config('pdftoolkit.limits.max_upload_mb')) }}">
                    @if ($phpLimit < (($s['max_upload_mb'] ?? config('pdftoolkit.limits.max_upload_mb')) * 1048576))
                        <p class="mt-1.5 text-xs text-amber-600">PHP currently allows only {{ format_bytes($phpLimit, 0) }} (upload_max_filesize / post_max_size).</p>
                    @endif
                </div>
                <div>
                    <label class="label" for="max_files">Maximum files per task</label>
                    <input id="max_files" type="number" name="max_files" class="input" min="1" max="200" value="{{ old('max_files', $s['max_files'] ?? config('pdftoolkit.limits.max_files')) }}">
                </div>
                <div>
                    <label class="label" for="engine_preference">Preferred processing</label>
                    <select id="engine_preference" name="engine_preference" class="input">
                        <option value="browser" @selected(($s['engine_preference'] ?? config('pdftoolkit.engine_preference')) === 'browser')>In the browser when possible</option>
                        <option value="server" @selected(($s['engine_preference'] ?? config('pdftoolkit.engine_preference')) === 'server')>On the server when possible</option>
                    </select>
                </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-slate-900 dark:text-white">Allowed file types</h3>
            <p class="text-sm text-slate-500">Untick extensions you do not want to accept. Each group needs at least one type.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $group => $definition)
                    @php $allowed = $s['allowed_types'][$group] ?? []; @endphp
                    <fieldset class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <legend class="px-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $definition['label'] }}</legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            @foreach ($definition['extensions'] as $ext)
                                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="allowed[{{ $group }}][]" value="{{ $ext }}" class="accent-brand-600" @checked($allowed === [] || in_array($ext, $allowed, true))> .{{ $ext }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Privacy &amp; statistics</h2>
            <div class="mt-5 grid gap-5">
                <div>
                    <label class="label" for="privacy_notice">Privacy message shown on tool pages</label>
                    <input id="privacy_notice" name="privacy_notice" class="input" value="{{ old('privacy_notice', $s['privacy_notice']) }}" required maxlength="300">
                </div>
                <label class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-3.5 py-3 dark:border-slate-700">
                    <span>
                        <span class="block text-sm font-medium">Anonymous usage statistics</span>
                        <span class="block text-xs text-slate-500">Counts tasks per tool and day. No IP addresses, no file names.</span>
                    </span>
                    <input type="hidden" name="stats_enabled" value="0">
                    <input type="checkbox" name="stats_enabled" value="1" class="toggle" @checked($s['stats_enabled'])>
                </label>
                <p class="text-sm text-slate-500">Analytics, verification codes and custom &lt;head&gt; code are managed under <a href="{{ route('admin.seo.verification') }}" class="font-medium text-brand-600 dark:text-brand-400">SEO → Verification &amp; analytics</a>.</p>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" /> Save settings</button>
        </div>
    </form>
@endsection
