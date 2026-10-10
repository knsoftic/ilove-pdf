@props(['name', 'label', 'hint' => null, 'checked' => false])
<label {{ $attributes->merge(['class' => 'flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-3.5 py-3 dark:border-slate-700']) }}>
    <span>
        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </span>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="toggle" @checked($checked)>
</label>
