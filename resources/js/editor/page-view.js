import { Canvas, Point } from 'fabric';
import { h, icon } from '../lib/dom';
import { CUSTOM_PROPS } from './constants';
import { normalizeText } from '../lib/text-layout';

/**
 * One page of the editor: the PDF rendered by PDF.js underneath and a
 * Fabric.js canvas on top holding everything the user adds.
 *
 * Object coordinates are "page units": the PDF.js viewport at scale 1 with
 * the page rotation applied. Zooming only changes the Fabric zoom factor.
 */
export class PageView {
    constructor(editor, { id, srcIndex, rotation = 0, json = null }) {
        this.editor = editor;
        this.id = id;
        this.srcIndex = srcIndex;
        this.rotation = rotation;
        this.json = json; // serialised objects (string) until the canvas exists
        this.canvas = null;
        this.renderTask = null;
        this.renderedZoom = 0;
        this.textCache = null;

        this.buildDom();
    }

    async init() {
        this.pdfPage = await this.editor.pdf.getPage(this.srcIndex + 1);
        this.updateViewport();
        this.layout();
    }

    get totalRotation() {
        return (((this.pdfPage.rotate + this.rotation) % 360) + 360) % 360;
    }

    updateViewport() {
        this.viewport = this.pdfPage.getViewport({ scale: 1, rotation: this.totalRotation });
        this.width = this.viewport.width;
        this.height = this.viewport.height;
        this.textCache = null;
    }

    buildDom() {
        this.label = h('span', { class: 'font-medium' });
        const action = (name, iconName, title) => h('button', { type: 'button', class: 'btn-icon size-7', title, 'aria-label': title, html: icon(iconName, 'size-4'), onclick: () => this.editor.pageAction(name, this) });

        this.bar = h(
            'div',
            { class: 'mb-1.5 flex w-full items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400' },
            this.label,
            h('div', { class: 'flex items-center gap-0.5' }, action('rotate-left', 'rotate-ccw', 'Rotate left'), action('rotate-right', 'rotate', 'Rotate right'), action('duplicate', 'copy', 'Duplicate page'), action('delete', 'trash', 'Delete page')),
        );

        this.pdfCanvas = h('canvas', { class: 'pdf-layer', 'aria-hidden': 'true' });
        this.fabricEl = h('canvas');
        this.hitBox = h('div', { class: 'text-hit hidden' });
        this.pageEl = h('div', { class: 'editor-page' }, this.pdfCanvas, this.fabricEl, this.hitBox);
        this.el = h('div', { class: 'flex flex-col items-center', dataset: { pageId: this.id } }, this.bar, this.pageEl);
    }

    setNumber(number, total) {
        this.label.textContent = `Page ${number} of ${total}`;
        this.number = number;
    }

    layout() {
        const zoom = this.editor.zoom;
        const w = Math.round(this.width * zoom);
        const hgt = Math.round(this.height * zoom);
        this.pageEl.style.width = `${w}px`;
        this.pageEl.style.height = `${hgt}px`;
        this.bar.style.width = `${w}px`;

        if (this.canvas) {
            this.canvas.setDimensions({ width: w, height: hgt });
            this.canvas.setZoom(zoom);
            this.canvas.requestRenderAll();
        }
    }

    /** Creates the Fabric canvas (lazily, when the page first becomes visible). */
    async ensureCanvas() {
        if (this.canvas) return this.canvas;

        const zoom = this.editor.zoom;
        this.canvas = new Canvas(this.fabricEl, {
            width: Math.round(this.width * zoom),
            height: Math.round(this.height * zoom),
            preserveObjectStacking: true,
            selection: true,
            allowTouchScrolling: true,
            stopContextMenu: true,
            enableRetinaScaling: true,
            targetFindTolerance: 6,
        });
        this.canvas.setZoom(zoom);
        this.canvas.page = this;

        if (this.json) {
            this.editor.restoring++;
            try {
                await this.canvas.loadFromJSON(JSON.parse(this.json));
            } finally {
                this.editor.restoring--;
            }
            this.canvas.requestRenderAll();
        }

        this.editor.attachCanvas(this);
        return this.canvas;
    }

    /** Serialised objects of the page. */
    serialize() {
        if (!this.canvas) return this.json;

        const data = this.canvas.toObject(CUSTOM_PROPS);
        const objects = (data.objects || []).filter((o) => !o.temp);
        return objects.length ? JSON.stringify({ version: data.version, objects }) : null;
    }

    async load(json) {
        this.json = json;
        if (!this.canvas) return;

        this.editor.restoring++;
        try {
            this.canvas.discardActiveObject();
            this.canvas.clear();
            if (json) await this.canvas.loadFromJSON(JSON.parse(json));
        } finally {
            this.editor.restoring--;
        }
        this.canvas.requestRenderAll();
    }

    async render(force = false) {
        const zoom = this.editor.zoom;
        if (!force && this.renderedZoom === zoom) return;

        this.renderTask?.cancel();
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        const viewport = this.pdfPage.getViewport({ scale: zoom * ratio, rotation: this.totalRotation });

        // Render off-screen, then swap: avoids flicker while zooming.
        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        this.renderTask = this.pdfPage.render({ canvas, viewport, annotationMode: 1 });

        try {
            await this.renderTask.promise;
        } catch (error) {
            if (error?.name === 'RenderingCancelledException') return;
            throw error;
        }

        this.pdfCanvas.width = canvas.width;
        this.pdfCanvas.height = canvas.height;
        this.pdfCanvas.getContext('2d').drawImage(canvas, 0, 0);
        this.renderedZoom = zoom;
        this.renderScale = zoom * ratio;
    }

    /**
     * Text runs of the page in page units: [{ str, x, y, width, height, fontName }]
     * (x/y = top-left corner).
     */
    async textItems() {
        if (this.textCache) return this.textCache;

        const content = await this.pdfPage.getTextContent();
        const items = [];

        for (const item of content.items) {
            if (!item.str || !item.str.trim()) continue;

            const [a, b, c, d, e, f] = item.transform;
            const size = Math.hypot(c, d) || item.height || 10;
            const width = item.width;
            const angle = Math.atan2(b, a);
            if (Math.abs(angle) > 0.05) continue; // skip rotated text runs

            // Box in PDF space (descender .25, ascender .9 of the font size).
            const corners = [
                [e, f - size * 0.25],
                [e + width, f - size * 0.25],
                [e, f + size * 0.9],
                [e + width, f + size * 0.9],
            ].map(([x, y]) => this.viewport.convertToViewportPoint(x, y));

            const xs = corners.map((p) => p[0]);
            const ys = corners.map((p) => p[1]);
            const style = content.styles?.[item.fontName] || {};

            items.push({
                str: normalizeText(item.str),
                x: Math.min(...xs),
                y: Math.min(...ys),
                width: Math.max(...xs) - Math.min(...xs),
                height: Math.max(...ys) - Math.min(...ys),
                size,
                fontName: item.fontName,
                fontFamily: style.fontFamily,
                vertical: this.totalRotation % 180 !== 0,
            });
        }

        this.textCache = items;
        return items;
    }

    async textItemAt(point) {
        const items = await this.textItems();
        return items.find((it) => point.x >= it.x - 1 && point.x <= it.x + it.width + 1 && point.y >= it.y - 1 && point.y <= it.y + it.height + 1) || null;
    }

    showHit(box) {
        if (!box) {
            this.hitBox.classList.add('hidden');
            return;
        }
        const zoom = this.editor.zoom;
        Object.assign(this.hitBox.style, {
            left: `${box.x * zoom}px`,
            top: `${box.y * zoom}px`,
            width: `${box.width * zoom}px`,
            height: `${box.height * zoom}px`,
        });
        this.hitBox.classList.remove('hidden');
    }

    /**
     * Samples the rendered page: background colour around a box and the
     * darkest colour inside it (≈ text colour).
     */
    sampleColors(box) {
        const scale = this.renderScale || 1;
        const ctx = this.pdfCanvas.getContext('2d', { willReadFrequently: true });
        const x = Math.max(0, Math.floor(box.x * scale));
        const y = Math.max(0, Math.floor(box.y * scale));
        const w = Math.max(1, Math.min(this.pdfCanvas.width - x, Math.ceil(box.width * scale)));
        const hh = Math.max(1, Math.min(this.pdfCanvas.height - y, Math.ceil(box.height * scale)));

        let background = '#ffffff';
        let text = '#111111';

        try {
            const data = ctx.getImageData(x, y, w, hh).data;
            const buckets = new Map();
            let darkest = 255 * 3;

            for (let i = 0; i < data.length; i += 4) {
                const key = `${data[i] >> 3},${data[i + 1] >> 3},${data[i + 2] >> 3}`;
                const bucket = buckets.get(key) || { n: 0, r: 0, g: 0, b: 0 };
                bucket.n++;
                bucket.r += data[i];
                bucket.g += data[i + 1];
                bucket.b += data[i + 2];
                buckets.set(key, bucket);

                const sum = data[i] + data[i + 1] + data[i + 2];
                if (sum < darkest) {
                    darkest = sum;
                    text = `rgb(${data[i]},${data[i + 1]},${data[i + 2]})`;
                }
            }

            // Most frequent colour (exact average of its bucket) = background.
            const top = [...buckets.values()].sort((p, q) => q.n - p.n)[0];
            if (top) {
                background = `rgb(${Math.round(top.r / top.n)},${Math.round(top.g / top.n)},${Math.round(top.b / top.n)})`;
            }
        } catch {
            /* tainted or empty canvas */
        }

        return { background, text };
    }

    /**
     * Rotates the page by ±90° and moves the existing objects along.
     */
    async rotate(delta) {
        const canvas = await this.ensureCanvas();
        const oldW = this.width;
        const oldH = this.height;

        this.rotation = (((this.rotation + delta) % 360) + 360) % 360;
        this.updateViewport();

        for (const obj of canvas.getObjects()) {
            const center = obj.getCenterPoint();
            const next = delta > 0 ? { x: oldH - center.y, y: center.x } : { x: center.y, y: oldW - center.x };
            obj.rotate((obj.angle || 0) + delta);
            obj.setPositionByOrigin(new Point(next.x, next.y), 'center', 'center');
            obj.setCoords();
        }

        this.renderedZoom = 0;
        this.layout();
        await this.render(true);
    }

    destroy() {
        this.renderTask?.cancel();
        this.canvas?.dispose();
        this.canvas = null;
        this.el.remove();
    }
}
