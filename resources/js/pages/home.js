import { $, h, escapeHtml } from '../lib/dom';
import { extensionOf } from '../lib/files';
import { stashFiles } from '../lib/handoff';
import { toast } from '../lib/toast';

/**
 * Home page: files dropped on the hero upload area open a tool picker and
 * are handed to the chosen tool in the browser (no upload yet).
 */
export default function initHome() {
    const zone = $('[data-home-dropzone]');
    const input = $('[data-home-input]');
    const dialog = $('#tool-picker');
    const tools = JSON.parse($('#quick-tools')?.textContent || '[]');

    if (!zone || !input) return;

    const open = () => input.click();
    zone.addEventListener('click', open);
    zone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            open();
        }
    });

    ['dragenter', 'dragover'].forEach((type) =>
        zone.addEventListener(type, (e) => {
            e.preventDefault();
            zone.classList.add('is-dragging');
        }),
    );
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, () => zone.classList.remove('is-dragging')));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        choose([...(e.dataTransfer?.files || [])]);
    });
    input.addEventListener('change', () => {
        choose([...input.files]);
        input.value = '';
    });

    dialog?.querySelector('[data-picker-close]')?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
    });

    function choose(files) {
        if (!files.length) return;

        const extensions = [...new Set(files.map((f) => extensionOf(f.name)))];
        const matching = tools.filter((tool) => extensions.every((ext) => tool.accept.includes(ext)) && (files.length === 1 || tool.multiple));

        if (!matching.length) {
            toast('No tool accepts this combination of files. Try one file type at a time.', 'error', 5000);
            return;
        }

        const list = dialog.querySelector('[data-picker-tools]');
        dialog.querySelector('[data-picker-files]').textContent = files.length === 1 ? files[0].name : `${files.length} files selected`;

        list.replaceChildren(
            ...matching.map((tool) =>
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-brand-300 hover:bg-brand-50 dark:border-slate-700 dark:hover:bg-brand-500/10',
                        style: `--tool-color:${tool.color}`,
                        onclick: async () => {
                            await stashFiles(files);
                            window.location.href = tool.url;
                        },
                    },
                    h('span', { class: 'tool-icon size-10', html: tool.svg || escapeHtml(tool.name.slice(0, 1)) }),
                    h('span', { class: 'min-w-0' }, h('span', { class: 'block font-semibold text-slate-900 dark:text-white' }, tool.name), h('span', { class: 'block truncate text-xs text-slate-500' }, tool.description)),
                ),
            ),
        );

        dialog.showModal();
    }
}
