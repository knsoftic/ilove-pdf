import { h, icon, debounce } from '../lib/dom';
import { openPdfJs, closePdfJs } from '../lib/pdf';
import { parsePages, formatPages } from '../lib/pages';

/**
 * Thumbnail grid for picking pages visually. Keeps the page-range text input
 * and the visual selection in sync.
 */
export class PagePicker {
    constructor(container, input) {
        this.container = container;
        this.input = input;
        this.selected = new Set();
        this.doc = null;
        this.cells = [];

        input.addEventListener(
            'input',
            debounce(() => this.syncFromInput(), 250),
        );
    }

    async load(file) {
        this.container.replaceChildren(h('p', { class: 'py-6 text-center text-sm text-slate-500' }, 'Loading pages…'));
        closePdfJs(this.doc);
        this.doc = await openPdfJs(new Uint8Array(await file.arrayBuffer()), { fileName: file.name, file });
        this.render();
        this.syncFromInput();
    }

    render() {
        const count = this.doc.numPages;
        const toolbar = h(
            'div',
            { class: 'mb-3 flex flex-wrap items-center gap-2 text-xs' },
            ...[
                ['All', () => this.setSelection([...Array(count)].map((_, i) => i + 1))],
                ['None', () => this.setSelection([])],
                ['Odd', () => this.setSelection([...Array(count)].map((_, i) => i + 1).filter((p) => p % 2))],
                ['Even', () => this.setSelection([...Array(count)].map((_, i) => i + 1).filter((p) => !(p % 2)))],
            ].map(([label, fn]) => h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: fn }, label)),
            h('span', { class: 'ml-auto text-slate-500', 'data-picker-summary': '' }),
        );

        const grid = h('div', { class: 'grid max-h-[28rem] grid-cols-3 gap-3 overflow-y-auto rounded-xl bg-slate-100 p-3 sm:grid-cols-4 md:grid-cols-6 dark:bg-slate-800/60' });
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    this.renderThumb(Number(entry.target.dataset.page), entry.target.querySelector('[data-canvas]'));
                }
            }
        }, { root: grid, rootMargin: '200px' });

        let last = null;
        this.cells = [];

        for (let page = 1; page <= count; page++) {
            const cell = h(
                'button',
                { type: 'button', class: 'page-thumb cursor-pointer', dataset: { page }, 'aria-pressed': 'false', 'aria-label': `Page ${page}` },
                h('div', { class: 'flex aspect-[3/4] w-full items-center justify-center', 'data-canvas': '' }),
                h('span', { class: 'text-xs font-medium text-slate-600 dark:text-slate-300' }, String(page)),
                h('span', { class: 'absolute top-3 right-3 hidden size-5 items-center justify-center rounded-full bg-brand-600 text-white', 'data-check': '', html: icon('check', 'size-3') }),
            );

            cell.addEventListener('click', (event) => {
                if (event.shiftKey && last !== null) {
                    const [a, b] = [Math.min(last, page), Math.max(last, page)];
                    for (let p = a; p <= b; p++) this.selected.add(p);
                } else if (this.selected.has(page)) {
                    this.selected.delete(page);
                } else {
                    this.selected.add(page);
                }
                last = page;
                this.syncToInput();
            });

            observer.observe(cell);
            this.cells.push(cell);
            grid.append(cell);
        }

        this.container.replaceChildren(toolbar, grid, h('p', { class: 'mt-2 text-xs text-slate-500' }, 'Tip: hold Shift to select a range.'));
        this.updateCells();
    }

    async renderThumb(pageNumber, target) {
        try {
            const page = await this.doc.getPage(pageNumber);
            const base = page.getViewport({ scale: 1 });
            const viewport = page.getViewport({ scale: 180 / Math.max(base.width, base.height) });
            const canvas = h('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await page.render({ canvas, viewport }).promise;
            target.replaceChildren(canvas);
        } catch {
            target.innerHTML = icon('file', 'size-8 text-slate-300');
        }
    }

    setSelection(pages) {
        this.selected = new Set(pages);
        this.syncToInput();
    }

    syncToInput() {
        this.input.value = formatPages([...this.selected]);
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        this.updateCells();
    }

    syncFromInput() {
        if (!this.doc) return;
        try {
            this.selected = this.input.value.trim() === '' ? new Set() : new Set(parsePages(this.input.value, this.doc.numPages));
        } catch {
            return;
        }
        this.updateCells();
    }

    updateCells() {
        for (const cell of this.cells) {
            const selected = this.selected.has(Number(cell.dataset.page));
            cell.classList.toggle('is-selected', selected);
            cell.setAttribute('aria-pressed', String(selected));
            cell.querySelector('[data-check]').classList.toggle('hidden', !selected);
            cell.querySelector('[data-check]').classList.toggle('flex', selected);
        }

        const summary = this.container.querySelector('[data-picker-summary]');
        if (summary && this.doc) summary.textContent = `${this.selected.size} of ${this.doc.numPages} selected`;
    }

    destroy() {
        closePdfJs(this.doc);
        this.doc = null;
        this.container.replaceChildren();
    }
}
