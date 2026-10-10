import { $, h, icon, formatBytes, show } from '../lib/dom';
import { validateFiles, extensionOf } from '../lib/files';
import { toast } from '../lib/toast';
import { pdfjs, closePdfJs } from '../lib/pdf';
import { vendorUrl } from '../lib/config';

let uid = 0;

/**
 * Manages the selected files of a tool: drag & drop, file picker, validation,
 * thumbnails, removing, replacing and (optionally) drag-to-reorder.
 */
export class FileQueue {
    constructor({ root, config, onChange }) {
        this.root = root;
        this.config = config;
        this.onChange = onChange;
        this.items = [];

        this.dropzone = $('[data-dropzone]', root);
        this.input = $('[data-input]', root);
        this.list = $('[data-file-list]', root);
        this.panel = $('[data-files-panel]', root);
        this.count = $('[data-file-count]', root);
        this.uploadSection = $('[data-upload]', root);

        this.bind();
    }

    get files() {
        return this.items.map((item) => item.file);
    }

    bind() {
        const pick = () => this.input?.click();

        this.dropzone?.addEventListener('click', (event) => {
            if (event.target.closest('[data-pick]') || event.target === this.dropzone || this.dropzone.contains(event.target)) pick();
        });
        this.dropzone?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                pick();
            }
        });
        this.input?.addEventListener('change', () => {
            const files = [...this.input.files];
            this.input.value = '';
            this.add(files, { replace: this.replacing });
            this.replacing = false;
        });
        $('[data-add-more]', this.root)?.addEventListener('click', pick);
        $('[data-replace]', this.root)?.addEventListener('click', () => {
            this.replacing = true;
            pick();
        });

        // Accept drops anywhere on the tool while it is idle.
        let depth = 0;
        const target = this.root;
        target.addEventListener('dragenter', (event) => {
            if (!event.dataTransfer?.types?.includes('Files')) return;
            event.preventDefault();
            depth++;
            this.dropzone?.classList.add('is-dragging');
        });
        target.addEventListener('dragover', (event) => {
            if (event.dataTransfer?.types?.includes('Files')) event.preventDefault();
        });
        target.addEventListener('dragleave', () => {
            depth = Math.max(0, depth - 1);
            if (!depth) this.dropzone?.classList.remove('is-dragging');
        });
        target.addEventListener('drop', (event) => {
            if (!event.dataTransfer?.files?.length) return;
            event.preventDefault();
            depth = 0;
            this.dropzone?.classList.remove('is-dragging');
            if (this.locked) return;
            this.add([...event.dataTransfer.files]);
        });

        if (this.config.sortable && this.list) {
            import('sortablejs').then(({ default: Sortable }) => {
                Sortable.create(this.list, {
                    animation: 180,
                    handle: '[data-handle]',
                    ghostClass: 'sortable-ghost',
                    onEnd: () => {
                        const order = [...this.list.children].map((li) => li.dataset.id);
                        this.items.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
                        this.changed();
                    },
                });
            });
        }
    }

    async add(files, { replace = false } = {}) {
        if (!files.length) return;

        const { valid, errors } = await validateFiles(files, {
            extensions: this.config.accept.extensions,
            maxBytes: this.config.maxBytes,
            label: this.config.accept.label,
        });

        errors.slice(0, 3).forEach((error) => toast(error.message, 'error', 5000));
        if (!valid.length) return;

        if (!this.config.multiple || replace) {
            this.items = [];
        }

        let accepted = this.config.multiple ? valid : valid.slice(0, 1);
        const room = this.config.maxFiles - this.items.length;

        if (accepted.length > room) {
            toast(`You can add up to ${this.config.maxFiles} files.`, 'error');
            accepted = accepted.slice(0, Math.max(0, room));
        }

        for (const file of accepted) {
            this.items.push({ id: `f${++uid}`, file });
        }

        this.render();
        this.changed();
    }

    remove(id) {
        this.items = this.items.filter((item) => item.id !== id);
        this.render();
        this.changed();
    }

    clear() {
        this.items = [];
        this.render();
        this.changed();
    }

    lock(locked) {
        this.locked = locked;
    }

    changed() {
        this.onChange?.(this.files);
    }

    render() {
        const hasFiles = this.items.length > 0;
        show(this.panel, hasFiles);
        show(this.uploadSection, !hasFiles);
        if (this.count) this.count.textContent = this.items.length;
        if (!this.list) return;

        const existing = new Map([...this.list.children].map((li) => [li.dataset.id, li]));
        const fragment = document.createDocumentFragment();

        this.items.forEach((item, index) => {
            const li = existing.get(item.id) || this.renderItem(item);
            const position = li.querySelector('[data-position]');
            if (position) position.textContent = index + 1;
            fragment.append(li);
        });

        this.list.replaceChildren(fragment);
    }

    renderItem(item) {
        const { file } = item;
        const ext = extensionOf(file.name);
        const thumb = h('div', { class: 'flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800', html: icon(['jpg', 'jpeg', 'png', 'webp'].includes(ext) ? 'image' : 'file', 'size-6') });
        const meta = h('p', { class: 'text-xs text-slate-500' }, `${formatBytes(file.size)} · ${ext.toUpperCase()}`);

        const li = h(
            'li',
            { class: 'flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-2.5 dark:border-slate-700 dark:bg-slate-900', dataset: { id: item.id } },
            this.config.sortable
                ? h('span', { class: 'flex cursor-grab items-center gap-1 text-slate-400 active:cursor-grabbing', 'data-handle': '', title: 'Drag to reorder' }, h('span', { html: icon('grip', 'size-4') }), h('span', { class: 'w-5 text-center text-xs font-semibold text-slate-500', 'data-position': '' }))
                : null,
            thumb,
            h('div', { class: 'min-w-0 flex-1' }, h('p', { class: 'truncate text-sm font-medium text-slate-900 dark:text-white', title: file.name }, file.name), meta),
            h('button', { type: 'button', class: 'btn-icon size-8 shrink-0 hover:text-rose-600', 'aria-label': `Remove ${file.name}`, html: icon('x', 'size-4'), onclick: () => this.remove(item.id) }),
        );

        this.loadThumbnail(file, ext, thumb, meta);
        return li;
    }

    async loadThumbnail(file, ext, container, meta) {
        try {
            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                const url = URL.createObjectURL(file);
                const img = h('img', { src: url, alt: '', class: 'size-full object-cover', onload: () => setTimeout(() => URL.revokeObjectURL(url), 1000) });
                container.replaceChildren(img);
                return;
            }

            if (ext !== 'pdf' || file.size > 150 * 1024 * 1024) return;

            const lib = await pdfjs();
            const doc = await lib.getDocument({
                data: new Uint8Array(await file.arrayBuffer()),
                cMapUrl: vendorUrl('pdfjs/cmaps/'),
                cMapPacked: true,
                standardFontDataUrl: vendorUrl('pdfjs/standard_fonts/'),
                wasmUrl: vendorUrl('pdfjs/wasm/'),
            }).promise;

            meta.textContent = `${formatBytes(file.size)} · ${doc.numPages} page${doc.numPages === 1 ? '' : 's'}`;

            const page = await doc.getPage(1);
            const viewport = page.getViewport({ scale: 1 });
            const scale = 96 / Math.max(viewport.width, viewport.height);
            const canvas = h('canvas', { class: 'size-full object-contain bg-white' });
            const vp = page.getViewport({ scale });
            canvas.width = Math.ceil(vp.width);
            canvas.height = Math.ceil(vp.height);
            await page.render({ canvas, viewport: vp }).promise;
            container.replaceChildren(canvas);
            closePdfJs(doc);
        } catch (error) {
            if (error?.name === 'PasswordException') {
                meta.textContent = `${formatBytes(file.size)} · password protected`;
                container.innerHTML = icon('lock', 'size-5 text-amber-500');
            }
        }
    }
}
