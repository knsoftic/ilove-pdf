import '@fontsource/dancing-script/600.css';
import '@fontsource/great-vibes/400.css';
import '@fontsource/caveat/600.css';
import { h } from '../lib/dom';
import { openModal } from '../lib/modal';
import { toast } from '../lib/toast';

const FONTS = [
    ['Dancing Script', 600],
    ['Great Vibes', 400],
    ['Caveat', 600],
];
const COLORS = ['#111827', '#1d4ed8', '#b91c1c'];

/** Signatures created during this visit (kept in memory only). */
const recent = [];

/**
 * Crops transparent borders and returns a PNG data URL (or null when empty).
 */
function trimmed(canvas) {
    const ctx = canvas.getContext('2d');
    const { width, height } = canvas;
    const data = ctx.getImageData(0, 0, width, height).data;
    let minX = width;
    let minY = height;
    let maxX = -1;
    let maxY = -1;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 8) {
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
            }
        }
    }

    if (maxX < 0) return null;

    const pad = 6;
    minX = Math.max(0, minX - pad);
    minY = Math.max(0, minY - pad);
    maxX = Math.min(width - 1, maxX + pad);
    maxY = Math.min(height - 1, maxY + pad);

    const out = document.createElement('canvas');
    out.width = maxX - minX + 1;
    out.height = maxY - minY + 1;
    out.getContext('2d').drawImage(canvas, minX, minY, out.width, out.height, 0, 0, out.width, out.height);
    return out.toDataURL('image/png');
}

function drawPanel(state) {
    const canvas = h('canvas', { class: 'signature-pad h-48 w-full cursor-crosshair rounded-xl border-2 border-dashed border-slate-300 bg-white dark:border-slate-600' });
    let drawing = false;
    let points = [];
    let ctx = null;

    const setup = () => {
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(rect.width * ratio);
        canvas.height = Math.round(rect.height * ratio);
        ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    };

    const position = (event) => {
        const rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top, p: event.pressure || 0.5 };
    };

    canvas.addEventListener('pointerdown', (event) => {
        if (!ctx) setup();
        canvas.setPointerCapture(event.pointerId);
        drawing = true;
        points = [position(event)];
        state.drawn = true;
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!drawing) return;
        points.push(position(event));
        if (points.length < 3) return;

        // Smooth with quadratic curves through mid-points.
        const [a, b, c] = points.slice(-3);
        ctx.strokeStyle = state.color;
        ctx.lineWidth = 1.6 + b.p * 2.4;
        ctx.beginPath();
        ctx.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
        ctx.quadraticCurveTo(b.x, b.y, (b.x + c.x) / 2, (b.y + c.y) / 2);
        ctx.stroke();
    });

    const stop = () => {
        drawing = false;
    };
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);

    const clear = () => {
        if (!ctx) setup();
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        state.drawn = false;
    };

    const panel = h(
        'div',
        { class: 'space-y-3' },
        canvas,
        h('div', { class: 'flex items-center justify-between' }, h('span', { class: 'text-xs text-slate-500' }, 'Draw with your mouse, finger or stylus.'), h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: clear }, 'Clear')),
    );

    panel.export = () => (state.drawn ? trimmed(canvas) : null);
    panel.activate = () => requestAnimationFrame(() => (ctx ? null : setup()));
    return panel;
}

function typePanel(state) {
    const input = h('input', { type: 'text', class: 'input text-lg', placeholder: 'Your name', maxlength: '60', value: '' });
    let font = FONTS[0];
    const previews = [];

    const choices = h(
        'div',
        { class: 'grid gap-2' },
        FONTS.map((f, i) => {
            const preview = h('span', { class: 'block truncate text-3xl', style: { fontFamily: `'${f[0]}'`, fontWeight: f[1], color: state.color } }, 'Your Signature');
            previews.push(preview);
            const button = h('button', { type: 'button', class: `choice justify-between ${i === 0 ? 'border-brand-500 ring-2 ring-brand-500/20' : ''}`, onclick: () => select(i) }, preview);
            return button;
        }),
    );

    const select = (index) => {
        font = FONTS[index];
        [...choices.children].forEach((b, i) => {
            b.classList.toggle('border-brand-500', i === index);
            b.classList.toggle('ring-2', i === index);
            b.classList.toggle('ring-brand-500/20', i === index);
        });
    };

    input.addEventListener('input', () => previews.forEach((p) => (p.textContent = input.value || 'Your Signature')));

    const panel = h('div', { class: 'space-y-3' }, input, choices);

    panel.refreshColor = () => previews.forEach((p) => (p.style.color = state.color));
    panel.export = async () => {
        const text = input.value.trim();
        if (!text) return null;
        await document.fonts.load(`${font[1]} 96px '${font[0]}'`);
        const canvas = document.createElement('canvas');
        canvas.width = 1400;
        canvas.height = 300;
        const ctx = canvas.getContext('2d');
        ctx.font = `${font[1]} 150px '${font[0]}'`;
        ctx.fillStyle = state.color;
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 30, 160, 1340);
        return trimmed(canvas);
    };
    panel.activate = () => input.focus();
    return panel;
}

function uploadPanel(state) {
    const input = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', class: 'block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-700' });
    const removeBg = h('input', { type: 'checkbox', class: 'accent-brand-600', checked: true });
    const preview = h('img', { class: 'hidden max-h-40 rounded-lg border border-slate-200 bg-[repeating-conic-gradient(#e2e8f0_0_25%,transparent_0_50%)] bg-[length:16px_16px] p-2', alt: '' });
    let dataUrl = null;

    const process = async () => {
        const file = input.files?.[0];
        if (!file) return;
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, 1200 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        if (removeBg.checked) {
            // Make the (near) white paper transparent.
            const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const d = image.data;
            for (let i = 0; i < d.length; i += 4) {
                const lightness = (d[i] + d[i + 1] + d[i + 2]) / 3;
                if (lightness > 200) d[i + 3] = Math.max(0, Math.round(((255 - lightness) / 55) * 255));
            }
            ctx.putImageData(image, 0, 0);
        }

        dataUrl = trimmed(canvas);
        preview.src = dataUrl || '';
        preview.classList.toggle('hidden', !dataUrl);
    };

    input.addEventListener('change', process);
    removeBg.addEventListener('change', process);

    const panel = h(
        'div',
        { class: 'space-y-3' },
        input,
        h('label', { class: 'flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300' }, removeBg, 'Remove white background'),
        preview,
    );
    panel.export = () => dataUrl;
    panel.activate = () => {};
    return panel;
}

/**
 * Opens the signature dialog. Resolves with a PNG data URL or null.
 */
export function createSignature() {
    return new Promise((resolve) => {
        const state = { color: COLORS[0], drawn: false };
        const panels = { draw: drawPanel(state), type: typePanel(state), upload: uploadPanel(state) };
        let active = 'draw';

        const tabs = h(
            'div',
            { class: 'segmented mb-4' },
            Object.keys(panels).map((key) =>
                h('label', {}, h('input', { type: 'radio', name: 'sig-tab', class: 'sr-only', checked: key === active, onchange: () => switchTo(key) }), { draw: 'Draw', type: 'Type', upload: 'Upload' }[key]),
            ),
        );

        const colors = h(
            'div',
            { class: 'mt-4 flex items-center gap-2 text-sm text-slate-500' },
            'Colour:',
            COLORS.map((color) =>
                h('button', {
                    type: 'button',
                    class: 'size-7 rounded-full border-2 border-white shadow ring-1 ring-slate-300',
                    style: { background: color },
                    'aria-label': `Colour ${color}`,
                    onclick: () => {
                        state.color = color;
                        panels.type.refreshColor();
                    },
                }),
            ),
        );

        const recentList = recent.length
            ? h(
                  'div',
                  { class: 'mt-5 border-t border-slate-200 pt-4 dark:border-slate-700' },
                  h('p', { class: 'mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase' }, 'Recent signatures'),
                  h(
                      'div',
                      { class: 'flex flex-wrap gap-2' },
                      recent.map((url) => h('button', { type: 'button', class: 'rounded-lg border border-slate-200 bg-white p-2 hover:border-brand-400', onclick: () => modal.close(url) }, h('img', { src: url, alt: 'Signature', class: 'h-10' }))),
                  ),
              )
            : null;

        const body = h('div', {}, tabs, h('div', { 'data-panel-host': '' }, panels.draw), colors, recentList);

        const switchTo = (key) => {
            active = key;
            body.querySelector('[data-panel-host]').replaceChildren(panels[key]);
            panels[key].activate();
            colors.classList.toggle('hidden', key === 'upload');
        };

        const modal = openModal({
            title: 'Create your signature',
            body,
            size: 'max-w-xl',
            actions: [
                { label: 'Cancel', onClick: (close) => close(null) },
                {
                    label: 'Use signature',
                    primary: true,
                    onClick: async (close) => {
                        const url = await panels[active].export();
                        if (!url) {
                            toast(active === 'type' ? 'Please type your name.' : active === 'upload' ? 'Please choose an image.' : 'Please draw your signature.', 'error');
                            return;
                        }
                        recent.unshift(url);
                        recent.splice(5);
                        close(url);
                    },
                },
            ],
            onClose: (value) => resolve(value || null),
        });

        panels.draw.activate();
    });
}
