import { h, formatBytes } from '../lib/dom';

const LABELS = {
    original_size: 'Original size',
    new_size: 'New size',
    saved_percent: 'Saved',
    pages: 'Pages',
    files: 'Files',
    sheets: 'Sheets',
    slides: 'Slides',
    rows: 'Rows',
    images: 'Images',
    characters: 'Characters',
    words: 'Words',
    matches: 'Matches',
    removed: 'Removed',
    dimensions: 'Dimensions',
    encryption: 'Encryption',
};

function format(key, value) {
    if (key === 'original_size' || key === 'new_size') return formatBytes(value);
    if (key === 'saved_percent') return `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 1 })}%`;
    if (typeof value === 'number') return value.toLocaleString();
    return String(value);
}

/**
 * Renders result statistics as small tiles.
 */
export function renderStats(container, stats = {}) {
    container.replaceChildren();

    for (const [key, value] of Object.entries(stats)) {
        if (!(key in LABELS) || value === null || value === '' || value === undefined) continue;

        const highlight = key === 'saved_percent' || key === 'new_size';
        container.append(
            h(
                'div',
                { class: `rounded-xl px-3 py-3 ${highlight ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-slate-100 dark:bg-slate-800'}` },
                h('dt', { class: 'text-xs text-slate-500 dark:text-slate-400' }, LABELS[key]),
                h('dd', { class: `mt-0.5 text-lg font-bold ${highlight ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-900 dark:text-white'}` }, format(key, value)),
            ),
        );
    }

    container.classList.toggle('hidden', container.children.length === 0);
}
