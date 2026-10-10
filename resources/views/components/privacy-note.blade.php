@props(['browser' => false])
@php
    $browserText = 'Processed in your browser – your files never leave your device.';
    $serverText = setting('privacy_notice');
@endphp
<p {{ $attributes->merge(['class' => 'flex items-start justify-center gap-2 text-center text-sm text-slate-500 dark:text-slate-400']) }}
   data-browser-text="{{ $browserText }}" data-server-text="{{ $serverText }}">
    <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-emerald-600" />
    <span>{{ $browser ? $browserText : $serverText }}</span>
</p>
