import { $, $$, show } from '../lib/dom';

/**
 * All tools page: instant search and category filter.
 */
export default function initToolsIndex() {
    const search = $('[data-tool-search]');
    const sections = $$('[data-category-section]');
    const buttons = $$('[data-category]');
    const empty = $('[data-no-results]');
    let category = 'all';

    function apply() {
        const terms = (search?.value || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
        let visibleTotal = 0;

        for (const section of sections) {
            const inCategory = category === 'all' || section.dataset.categorySection === category;
            let visible = 0;

            for (const card of $$('[data-tool-card]', section)) {
                const haystack = `${card.dataset.name} ${card.dataset.keywords}`;
                const match = inCategory && terms.every((term) => haystack.includes(term));
                show(card, match);
                if (match) visible++;
            }

            show(section, visible > 0);
            visibleTotal += visible;
        }

        show(empty, visibleTotal === 0);
    }

    search?.addEventListener('input', apply);

    buttons.forEach((button) =>
        button.addEventListener('click', () => {
            category = button.dataset.category;
            buttons.forEach((b) => {
                const active = b === button;
                b.classList.toggle('bg-brand-600', active);
                b.classList.toggle('text-white', active);
                b.classList.toggle('bg-slate-100', !active);
                b.classList.toggle('text-slate-700', !active);
            });
            apply();
        }),
    );

    if (search?.value) apply();
    if (location.hash) {
        const target = buttons.find((b) => b.dataset.category === location.hash.slice(1));
        target?.click();
    }
}
