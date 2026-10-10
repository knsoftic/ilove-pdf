import { h } from '../lib/dom';
import { openModal } from '../lib/modal';
import { STAMPS } from './constants';

function field(label, input) {
    return h('label', { class: 'block' }, h('span', { class: 'label' }, label), input);
}

/**
 * Watermark settings. Resolves with { text, size, color, opacity, angle } or null.
 */
export function watermarkDialog() {
    return new Promise((resolve) => {
        const text = h('input', { type: 'text', class: 'input', value: 'CONFIDENTIAL', maxlength: '120' });
        const size = h('input', { type: 'number', class: 'input', value: '64', min: '8', max: '300' });
        const color = h('input', { type: 'color', class: 'h-10 w-full cursor-pointer rounded-xl border border-slate-300 p-1', value: '#dc2626' });
        const opacity = h('input', { type: 'range', class: 'w-full accent-brand-600', min: '5', max: '100', value: '25' });
        const angle = h('input', { type: 'range', class: 'w-full accent-brand-600', min: '-90', max: '90', step: '5', value: '45' });

        openModal({
            title: 'Add watermark to all pages',
            body: h('div', { class: 'grid gap-4 sm:grid-cols-2' }, h('div', { class: 'sm:col-span-2' }, field('Text', text)), field('Font size', size), field('Colour', color), field('Opacity', opacity), field('Rotation', angle)),
            actions: [
                { label: 'Cancel', onClick: (close) => close(null) },
                {
                    label: 'Add watermark',
                    primary: true,
                    onClick: (close) => (text.value.trim() ? close({ text: text.value.trim(), size: Number(size.value) || 64, color: color.value, opacity: Number(opacity.value) / 100, angle: Number(angle.value) }) : text.focus()),
                },
            ],
            onClose: (value) => resolve(value),
        });
    });
}

/**
 * Page number settings. Resolves with { position, format, size, color } or null.
 */
export function pageNumberDialog() {
    return new Promise((resolve) => {
        const position = h(
            'select',
            { class: 'input' },
            ['bottom-center', 'bottom-right', 'bottom-left', 'top-center', 'top-right', 'top-left'].map((p) => h('option', { value: p }, p.replace('-', ' ').replace(/^\w/, (c) => c.toUpperCase()))),
        );
        const format = h(
            'select',
            { class: 'input' },
            [
                ['{n}', '1'],
                ['Page {n}', 'Page 1'],
                ['Page {n} of {total}', 'Page 1 of 9'],
                ['{n} / {total}', '1 / 9'],
            ].map(([value, label]) => h('option', { value }, label)),
        );
        const size = h('input', { type: 'number', class: 'input', value: '11', min: '6', max: '48' });
        const color = h('input', { type: 'color', class: 'h-10 w-full cursor-pointer rounded-xl border border-slate-300 p-1', value: '#111827' });

        openModal({
            title: 'Add page numbers',
            body: h('div', { class: 'grid gap-4 sm:grid-cols-2' }, field('Position', position), field('Format', format), field('Font size', size), field('Colour', color)),
            actions: [
                { label: 'Cancel', onClick: (close) => close(null) },
                { label: 'Add page numbers', primary: true, onClick: (close) => close({ position: position.value, format: format.value, size: Number(size.value) || 11, color: color.value }) },
            ],
            onClose: (value) => resolve(value),
        });
    });
}

/**
 * Stamp picker. Resolves with a stamp definition or null.
 */
export function stampDialog() {
    return new Promise((resolve) => {
        const grid = h(
            'div',
            { class: 'grid grid-cols-2 gap-3 sm:grid-cols-3' },
            STAMPS.map((stamp) =>
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'flex h-16 items-center justify-center rounded-xl border-[3px] bg-white text-sm font-extrabold tracking-wider transition hover:scale-[1.03] dark:bg-slate-900',
                        style: { color: stamp.color, borderColor: stamp.color },
                        onclick: () => modal.close(stamp),
                    },
                    stamp.date ? `${stamp.label} ${new Date().toLocaleDateString()}` : stamp.label,
                ),
            ),
        );

        const modal = openModal({ title: 'Choose a stamp', body: grid, size: 'max-w-lg', onClose: (value) => resolve(value) });
    });
}
