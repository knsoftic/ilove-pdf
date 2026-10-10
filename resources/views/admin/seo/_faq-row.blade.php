<div class="grid gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-faq-row>
    <div class="flex items-center gap-2">
        <input name="faq[{{ $i }}][q]" value="{{ $q }}" class="input" maxlength="250" placeholder="Question" aria-label="Question">
        <button type="button" class="btn-icon shrink-0 hover:text-rose-600" data-faq-remove aria-label="Remove question"><x-icon name="trash" class="size-4" /></button>
    </div>
    <textarea name="faq[{{ $i }}][a]" rows="2" class="input" maxlength="2000" placeholder="Answer" aria-label="Answer">{{ $a }}</textarea>
</div>
