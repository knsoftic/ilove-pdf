import { $, $$, h, icon, show } from '../lib/dom';
import { validateFiles, readBytes, download, outputName } from '../lib/files';
import { openPdfJs, loadPdf, pdfLib, closePdfJs } from '../lib/pdf';
import { takeFiles } from '../lib/handoff';
import { toast } from '../lib/toast';
import { openModal } from '../lib/modal';
import { recordUsage } from '../lib/usage';
import { toToolError } from '../lib/errors';

let seq = 0;

/**
 * Visual page manager: reorder (drag), rotate, duplicate, delete, extract
 * and combine pages of one or more PDFs – entirely in the browser.
 */
export default async function initOrganizer() {
    const root = $('#organizer');
    if (!root) return;

    const config = JSON.parse(root.dataset.config);
    const grid = $('[data-grid]', root);
    const upload = $('[data-organizer-upload]', root);
    const workspace = $('[data-organizer-workspace]', root);
    const input = $('[data-organizer-input]', root);
    const addInput = $('[data-organizer-add]', root);

    let sources = []; // { file, bytes, pdf }
    let cards = []; // { id, source, index, rotation, selected }
    let history = [];
    let lastClicked = null;
    let sortable = null;

    const thumbObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    thumbObserver.unobserve(entry.target);
                    renderThumb(entry.target);
                }
            }
        },
        { rootMargin: '400px' },
    );

    async function addSources(files, { reset = false } = {}) {
        const { valid, errors } = await validateFiles(files, { extensions: ['pdf'], maxBytes: config.maxBytes, label: 'PDF' });
        errors.forEach((e) => toast(e.message, 'error', 5000));
        if (!valid.length) return;

        if (reset) {
            sources.forEach((s) => closePdfJs(s.pdf));
            sources = [];
            cards = [];
            history = [];
        }

        for (const file of valid) {
            try {
                const bytes = await readBytes(file);
                const pdf = await openPdfJs(bytes, { fileName: file.name, file });
                const source = { file, bytes, pdf };
                sources.push(source);

                for (let i = 0; i < pdf.numPages; i++) {
                    const page = await pdf.getPage(i + 1);
                    cards.push({ id: `c${++seq}`, source, index: i, rotation: 0, baseRotation: page.rotate, selected: false });
                }
            } catch (error) {
                toast(toToolError(error).message, 'error', 5000);
            }
        }

        if (!cards.length) return;

        show(upload, false);
        show(workspace, true);
        render();
    }

    function snapshot() {
        history.push(cards.map((c) => ({ ...c })));
        if (history.length > 50) history.shift();
        $('[data-org="undo"]', root).disabled = false;
    }

    function render() {
        grid.replaceChildren(...cards.map((card, i) => cardElement(card, i)));

        $('[data-count]', root).textContent = cards.length;
        updateSelection();

        sortable?.destroy();
        import('sortablejs').then(({ default: Sortable }) => {
            sortable = Sortable.create(grid, {
                animation: 180,
                ghostClass: 'sortable-ghost',
                filter: '[data-card-action]',
                preventOnFilter: false,
                onStart: () => snapshot(),
                onEnd: () => {
                    const order = [...grid.children].map((el) => el.dataset.id);
                    cards.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
                    renumber();
                },
            });
        });
    }

    function renumber() {
        [...grid.children].forEach((el, i) => (el.querySelector('[data-number]').textContent = i + 1));
    }

    function cardElement(card, i) {
        const thumb = h('div', { class: 'flex aspect-[3/4] w-full items-center justify-center overflow-hidden', 'data-thumb': '' });
        const action = (name, iconName, title) =>
            h('button', {
                type: 'button',
                class: 'btn-icon size-7 bg-white/90 shadow-sm dark:bg-slate-800/90',
                title,
                'aria-label': title,
                'data-card-action': name,
                html: icon(iconName, 'size-4'),
                onclick: (event) => {
                    event.stopPropagation();
                    cardAction(name, card);
                },
            });

        const el = h(
            'div',
            {
                class: `page-thumb group cursor-pointer select-none ${card.selected ? 'is-selected' : ''}`,
                dataset: { id: card.id },
                tabindex: '0',
                role: 'button',
                'aria-pressed': String(card.selected),
                onclick: (event) => toggle(card, event),
                onkeydown: (event) => {
                    if (event.key === ' ' || event.key === 'Enter') {
                        event.preventDefault();
                        toggle(card, event);
                    }
                },
            },
            thumb,
            h(
                'div',
                { class: 'flex w-full items-center justify-between text-xs' },
                h('span', { class: 'font-semibold text-slate-700 dark:text-slate-200', 'data-number': '' }, String(i + 1)),
                sources.length > 1 ? h('span', { class: 'truncate pl-2 text-slate-400', title: card.source.file.name }, card.source.file.name) : null,
            ),
            h(
                'div',
                { class: 'absolute top-3 right-3 flex flex-col gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100' },
                action('rotate-right', 'rotate', 'Rotate right'),
                action('rotate-left', 'rotate-ccw', 'Rotate left'),
                action('duplicate', 'copy', 'Duplicate'),
                action('delete', 'trash', 'Delete'),
            ),
            h('span', { class: `absolute top-3 left-3 size-5 items-center justify-center rounded-full bg-brand-600 text-white ${card.selected ? 'flex' : 'hidden'}`, 'data-check': '', html: icon('check', 'size-3') }),
        );

        el.card = card;
        thumbObserver.observe(el);
        return el;
    }

    async function renderThumb(el) {
        const card = el.card;
        const target = el.querySelector('[data-thumb]');

        try {
            const page = await card.source.pdf.getPage(card.index + 1);
            const rotation = (card.baseRotation + card.rotation) % 360;
            const base = page.getViewport({ scale: 1, rotation });
            const viewport = page.getViewport({ scale: 300 / Math.max(base.width, base.height), rotation });
            const canvas = h('canvas', { class: 'max-h-full max-w-full' });
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await page.render({ canvas, viewport }).promise;
            target.replaceChildren(canvas);
        } catch {
            target.innerHTML = icon('file', 'size-8 text-slate-300');
        }
    }

    function toggle(card, event) {
        const index = cards.indexOf(card);

        if (event.shiftKey && lastClicked !== null) {
            const [a, b] = [Math.min(lastClicked, index), Math.max(lastClicked, index)];
            for (let i = a; i <= b; i++) cards[i].selected = true;
        } else if (event.ctrlKey || event.metaKey) {
            card.selected = !card.selected;
        } else {
            card.selected = !card.selected;
        }

        lastClicked = index;
        updateSelection();
    }

    function updateSelection() {
        const selected = cards.filter((c) => c.selected).length;
        $('[data-selected-count]', root).textContent = selected;
        $$('[data-needs-selection]', root).forEach((b) => (b.disabled = selected === 0));

        for (const el of grid.children) {
            const on = el.card.selected;
            el.classList.toggle('is-selected', on);
            el.setAttribute('aria-pressed', String(on));
            const check = el.querySelector('[data-check]');
            check.classList.toggle('hidden', !on);
            check.classList.toggle('flex', on);
        }
    }

    function rotate(list, delta) {
        snapshot();
        for (const card of list) {
            card.rotation = (((card.rotation + delta) % 360) + 360) % 360;
            const el = grid.querySelector(`[data-id="${card.id}"]`);
            if (el) renderThumb(el);
        }
    }

    function duplicate(list) {
        snapshot();
        for (const card of list) {
            const copy = { ...card, id: `c${++seq}`, selected: false };
            cards.splice(cards.indexOf(card) + 1, 0, copy);
        }
        render();
    }

    function remove(list) {
        if (list.length >= cards.length) {
            toast('A PDF needs at least one page.', 'error');
            return;
        }
        snapshot();
        cards = cards.filter((c) => !list.includes(c));
        render();
    }

    function cardAction(name, card) {
        if (name === 'rotate-right') rotate([card], 90);
        else if (name === 'rotate-left') rotate([card], -90);
        else if (name === 'duplicate') duplicate([card]);
        else if (name === 'delete') remove([card]);
    }

    async function build(list) {
        const { PDFDocument, degrees } = await pdfLib();
        const out = await PDFDocument.create();
        const loaded = new Map();

        for (const card of list) {
            if (!loaded.has(card.source)) {
                const { doc } = await loadPdf(card.source.bytes, { fileName: card.source.file.name, file: card.source.file });
                loaded.set(card.source, doc);
            }

            const [page] = await out.copyPages(loaded.get(card.source), [card.index]);
            page.setRotation(degrees((card.baseRotation + card.rotation) % 360));
            out.addPage(page);
        }

        return out.save({ useObjectStreams: true });
    }

    async function exportPdf(list, suffix) {
        const modal = openModal({ title: 'Creating your PDF', body: h('div', { class: 'progress-track' }, h('div', { class: 'progress-bar indeterminate' })) });

        try {
            const bytes = await build(list);
            const name = outputName(sources[0].file.name, suffix, 'pdf');
            download(new Blob([bytes], { type: 'application/pdf' }), name);
            recordUsage(config.endpoints?.usage);
            toast(`${name} downloaded.`, 'success');
        } catch (error) {
            toast(toToolError(error).message, 'error', 6000);
        } finally {
            modal.close();
        }
    }

    const actions = {
        'select-all': () => {
            cards.forEach((c) => (c.selected = true));
            updateSelection();
        },
        'select-none': () => {
            cards.forEach((c) => (c.selected = false));
            updateSelection();
        },
        'rotate-left': () => rotate(cards.filter((c) => c.selected), -90),
        'rotate-right': () => rotate(cards.filter((c) => c.selected), 90),
        duplicate: () => duplicate(cards.filter((c) => c.selected)),
        delete: () => remove(cards.filter((c) => c.selected)),
        extract: () => exportPdf(cards.filter((c) => c.selected), 'selected-pages'),
        download: () => exportPdf(cards, 'organized'),
        add: () => addInput.click(),
        undo: () => {
            const previous = history.pop();
            if (previous) {
                cards = previous;
                render();
            }
            $('[data-org="undo"]', root).disabled = history.length === 0;
        },
        restart: () => {
            sources.forEach((s) => closePdfJs(s.pdf));
            sources = [];
            cards = [];
            history = [];
            grid.replaceChildren();
            show(workspace, false);
            show(upload, true);
        },
    };

    $$('[data-org]', root).forEach((button) => button.addEventListener('click', () => actions[button.dataset.org]?.()));

    $('[data-thumb-size]', root)?.addEventListener('input', (event) => grid.style.setProperty('--thumb', `${event.target.value}px`));

    const dropzone = $('[data-organizer-dropzone]', root);
    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });
    ['dragenter', 'dragover'].forEach((type) =>
        dropzone.addEventListener(type, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragging');
        }),
    );
    ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, () => dropzone.classList.remove('is-dragging')));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        addSources([...(e.dataTransfer?.files || [])].slice(0, 1), { reset: true });
    });

    input.addEventListener('change', () => {
        addSources([...input.files].slice(0, 1), { reset: true });
        input.value = '';
    });
    addInput.addEventListener('change', () => {
        snapshot();
        addSources([...addInput.files]);
        addInput.value = '';
    });

    if (root.dataset.preset === 'reorder') {
        $('[data-org-hint]', root).textContent = 'Drag and drop the pages into the order you want, then click "Download PDF".';
    }

    const handed = await takeFiles();
    if (handed.length) addSources(handed.slice(0, 1), { reset: true });

    window.__organizer = () => ({ cards, sources });
}
