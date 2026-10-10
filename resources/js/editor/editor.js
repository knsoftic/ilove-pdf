import { IText, FabricText, Rect, Ellipse, Line, Path, PencilBrush, FabricImage, Group } from 'fabric';
import { $, $$, h, debounce, show } from '../lib/dom';
import { validateFiles, readBytes, download, outputName } from '../lib/files';
import { openPdfJs, closePdfJs } from '../lib/pdf';
import { fontStyle } from '../lib/text-layout';
import { takeFiles } from '../lib/handoff';
import { toast } from '../lib/toast';
import { openModal, confirmDialog } from '../lib/modal';
import { recordUsage } from '../lib/usage';
import { toToolError } from '../lib/errors';
import { FONTS, CUSTOM_PROPS, TOOLS, DRAG_TOOLS, uid } from './constants';
import { History } from './history';
import { PageView } from './page-view';
import { exportPdf } from './exporter';

const TEXT_KINDS = new Set(['text', 'edit-text', 'watermark', 'page-number']);
const ONE_SHOT = new Set(['image', 'signature', 'stamp', 'watermark', 'page-number']);

/**
 * Browser PDF editor: PDF.js renders the pages, Fabric.js holds the user's
 * additions, pdf-lib writes the result. Nothing is uploaded.
 */
class Editor {
    constructor(root, { file, bytes, pdf, config, preset }) {
        this.root = root;
        this.file = file;
        this.bytes = bytes;
        this.pdf = pdf;
        this.config = config;
        this.preset = preset;

        this.pages = [];
        this.allPages = new Map();
        this.zoom = 1;
        this.tool = 'select';
        this.restoring = 0;
        this.history = new History();
        this.drag = null;
        this.fontsLoaded = new WeakSet();

        this.style = {
            fontFamily: 'helvetica',
            fontSize: 16,
            bold: false,
            italic: false,
            underline: false,
            color: '#111827',
            fill: '#bfdbfe',
            filled: false,
            strokeWidth: 3,
            opacity: 100,
            highlight: '#fde047',
            highlightOpacity: 45,
        };

        this.viewport = $('[data-viewport]', root);
        this.host = $('[data-pages]', root);
        this.thumbs = $('[data-thumbs]', root);
        this.imageInput = $('[data-image-input]', root);
        this.commitLater = debounce(() => this.commit(), 250);
        this.renderVisible = debounce(() => this.visiblePages().forEach((p) => p.render().catch(() => {})), 120);
    }

    // ------------------------------------------------------------------
    // Setup
    // ------------------------------------------------------------------

    async mount() {
        document.body.classList.add('editor-open');
        $('[data-file-name]', this.root).textContent = this.file.name;

        for (let i = 0; i < this.pdf.numPages; i++) {
            const page = new PageView(this, { id: uid('p'), srcIndex: i });
            this.allPages.set(page.id, page);
            this.pages.push(page);
        }

        await Promise.all(this.pages.map((p) => p.init()));
        this.pages.forEach((p) => this.host.append(p.el));

        this.zoom = this.fitZoom();
        this.pages.forEach((p) => p.layout());
        this.observe();
        this.renumber();
        this.buildThumbs();
        this.bind();
        this.commit(true);
        this.applyPreset();
    }

    fitZoom() {
        const available = Math.max(280, this.viewport.clientWidth - 40);
        const widest = Math.max(...this.pages.map((p) => p.width));
        return Math.max(0.25, Math.min(1.75, available / widest));
    }

    observe() {
        this.observer?.disconnect();
        this.observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    const page = this.allPages.get(entry.target.dataset.pageId);
                    if (!page) continue;
                    page.ensureCanvas().catch(() => {});
                    page.render().catch(() => {});
                }
            },
            { root: this.viewport, rootMargin: '800px 0px' },
        );
        this.pages.forEach((p) => this.observer.observe(p.el));
    }

    visiblePages() {
        const box = this.viewport.getBoundingClientRect();
        return this.pages.filter((p) => {
            const r = p.pageEl.getBoundingClientRect();
            return r.bottom > box.top - 400 && r.top < box.bottom + 400;
        });
    }

    /** The page occupying most of the viewport. */
    currentPage() {
        const box = this.viewport.getBoundingClientRect();
        let best = this.pages[0];
        let bestArea = -1;

        for (const page of this.pages) {
            const r = page.pageEl.getBoundingClientRect();
            const visible = Math.max(0, Math.min(r.bottom, box.bottom) - Math.max(r.top, box.top));
            if (visible > bestArea) {
                bestArea = visible;
                best = page;
            }
        }

        return best;
    }

    /** Centre of the visible part of a page, in page units. */
    visibleCenter(page) {
        const box = this.viewport.getBoundingClientRect();
        const r = page.pageEl.getBoundingClientRect();
        const top = Math.max(r.top, box.top);
        const bottom = Math.min(r.bottom, box.bottom);
        const y = bottom > top ? ((top + bottom) / 2 - r.top) / this.zoom : page.height / 2;
        return { x: page.width / 2, y: Math.max(40, Math.min(page.height - 40, y)) };
    }

    attachCanvas(page) {
        const canvas = page.canvas;

        canvas.on('mouse:down', (e) => this.onDown(page, e));
        canvas.on('mouse:move', (e) => this.onMove(page, e));
        canvas.on('mouse:up', (e) => this.onUp(page, e));
        canvas.on('mouse:out', () => page.showHit(null));
        canvas.on('path:created', (e) => {
            e.path.set({ kind: 'draw', objectId: uid(), strokeLineCap: 'round', strokeLineJoin: 'round' });
            this.changed(page);
        });

        for (const event of ['object:added', 'object:modified']) {
            canvas.on(event, (e) => {
                if (!e.target?.temp) this.changed(page);
            });
        }

        canvas.on('object:removed', (e) => {
            const target = e.target;
            if (!target || target.temp) return;

            // Removing an edited text restores the original (removes its cover).
            if (target.linkId && !this.restoring) {
                const cover = canvas.getObjects().find((o) => o.objectId === target.linkId);
                if (cover) canvas.remove(cover);
            }
            this.changed(page);
        });

        canvas.on('text:editing:exited', (e) => {
            const target = e.target;
            if (target && !String(target.text || '').trim()) canvas.remove(target);
            this.changed(page);
        });

        canvas.on('text:changed', () => this.changed(page));

        for (const event of ['selection:created', 'selection:updated']) {
            canvas.on(event, () => {
                // Only one page can have a selection at a time.
                this.pages.forEach((p) => p !== page && p.canvas?.getActiveObject() && (p.canvas.discardActiveObject(), p.canvas.requestRenderAll()));
                this.updateProps();
            });
        }
        canvas.on('selection:cleared', () => this.updateProps());

        this.configureCanvas(page);
    }

    configureCanvas(page) {
        const canvas = page.canvas;
        if (!canvas) return;

        const tool = this.tool;
        canvas.isDrawingMode = tool === 'draw';

        if (tool === 'draw') {
            const brush = new PencilBrush(canvas);
            brush.color = this.rgba(this.style.color, this.style.opacity / 100);
            brush.width = this.style.strokeWidth;
            brush.strokeLineCap = 'round';
            canvas.freeDrawingBrush = brush;
        }

        canvas.selection = tool === 'select';
        canvas.skipTargetFind = !['select', 'text', 'edit-text', 'eraser'].includes(tool);
        canvas.allowTouchScrolling = tool === 'select';
        canvas.defaultCursor = { text: 'text', 'edit-text': 'text', eraser: 'cell', draw: 'crosshair' }[tool] || (DRAG_TOOLS.has(tool) ? 'crosshair' : 'default');
        canvas.hoverCursor = tool === 'eraser' ? 'cell' : 'move';
        canvas.upperCanvasEl.style.touchAction = tool === 'select' ? 'pan-y' : 'none';

        if (tool !== 'select') {
            canvas.discardActiveObject();
            canvas.requestRenderAll();
        }
    }

    // ------------------------------------------------------------------
    // Tools
    // ------------------------------------------------------------------

    async setTool(name) {
        if (ONE_SHOT.has(name)) {
            await this.runOneShot(name);
            return;
        }

        this.tool = name;
        $$('[data-tool]', this.root).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.tool === name)));
        this.pages.forEach((p) => this.configureCanvas(p));
        this.pages.forEach((p) => p.showHit(null));
        this.updateProps();
    }

    async runOneShot(name) {
        const page = this.currentPage();

        if (name === 'image') {
            this.imageInput.click();
            return;
        }

        if (name === 'signature') {
            const { createSignature } = await import('./signature');
            const url = await createSignature();
            if (url) await this.addImage(url, 'signature', page);
            return;
        }

        const dialogs = await import('./dialogs');

        if (name === 'stamp') {
            const stamp = await dialogs.stampDialog();
            if (stamp) await this.addStamp(stamp, page);
        } else if (name === 'watermark') {
            const settings = await dialogs.watermarkDialog();
            if (settings) this.addWatermark(settings);
        } else if (name === 'page-number') {
            const settings = await dialogs.pageNumberDialog();
            if (settings) this.addPageNumbers(settings);
        }
    }

    async onDown(page, e) {
        const point = e.scenePoint;
        const tool = this.tool;

        if (tool === 'text') {
            if (e.target instanceof FabricText) return;
            this.addText(page, point);
            return;
        }

        if (tool === 'edit-text') {
            if (e.target instanceof FabricText) return;
            const item = await page.textItemAt(point);
            if (item) await this.editTextItem(page, item);
            else toast('Click directly on a line of text to edit it.', 'info', 2500);
            return;
        }

        if (tool === 'eraser') {
            this.erasing = true;
            this.erase(page, e.target);
            return;
        }

        if (DRAG_TOOLS.has(tool)) {
            this.drag = { page, start: point, preview: this.previewShape(page, point) };
        }
    }

    async onMove(page, e) {
        const point = e.scenePoint;

        if (this.drag && this.drag.page === page) {
            this.updatePreview(this.drag, point);
            return;
        }

        if (this.tool === 'eraser' && this.erasing) {
            this.erase(page, e.target);
            return;
        }

        if (this.tool === 'edit-text' && !e.target) {
            if (this.hoverPending) return;
            this.hoverPending = true;
            const item = await page.textItemAt(point);
            this.hoverPending = false;
            page.showHit(item);
            page.canvas.defaultCursor = item ? 'text' : 'default';
        }
    }

    async onUp(page, e) {
        this.erasing = false;
        if (!this.drag || this.drag.page !== page) return;

        const { start, preview } = this.drag;
        const end = e.scenePoint;
        this.drag = null;
        page.canvas.remove(preview);

        const created = await this.finishShape(page, start, end);

        if (created.length && ['rect', 'ellipse', 'line', 'arrow'].includes(this.tool)) {
            await this.setTool('select');
            page.canvas.setActiveObject(created[0]);
            page.canvas.requestRenderAll();
            this.updateProps();
        }
    }

    previewShape(page, point) {
        const common = { left: point.x, top: point.y, originX: 'left', originY: 'top', selectable: false, evented: false, temp: true, excludeFromExport: true, strokeUniform: true };
        let shape;

        if (this.tool === 'ellipse') shape = new Ellipse({ ...common, rx: 0, ry: 0, fill: 'transparent', stroke: this.style.color, strokeWidth: this.style.strokeWidth });
        else if (this.tool === 'line' || this.tool === 'arrow') shape = new Line([point.x, point.y, point.x, point.y], { ...common, stroke: this.style.color, strokeWidth: this.style.strokeWidth });
        else if (this.tool === 'highlight') shape = new Rect({ ...common, width: 0, height: 0, fill: this.rgba(this.style.highlight, 0.35), strokeWidth: 0 });
        else shape = new Rect({ ...common, width: 0, height: 0, fill: 'transparent', stroke: this.tool === 'rect' ? this.style.color : '#4f46e5', strokeWidth: this.tool === 'rect' ? this.style.strokeWidth : 1, strokeDashArray: this.tool === 'rect' ? null : [4, 3] });

        page.canvas.add(shape);
        return shape;
    }

    updatePreview(drag, point) {
        const { start, preview } = drag;

        if (preview instanceof Line) {
            preview.set({ x2: point.x, y2: point.y });
        } else if (preview instanceof Ellipse) {
            preview.set({ left: Math.min(start.x, point.x), top: Math.min(start.y, point.y), rx: Math.abs(point.x - start.x) / 2, ry: Math.abs(point.y - start.y) / 2 });
        } else {
            preview.set({ left: Math.min(start.x, point.x), top: Math.min(start.y, point.y), width: Math.abs(point.x - start.x), height: Math.abs(point.y - start.y) });
        }

        preview.setCoords();
        drag.page.canvas.requestRenderAll();
    }

    async finishShape(page, start, end) {
        const canvas = page.canvas;
        const s = this.style;
        const tiny = Math.abs(end.x - start.x) < 4 && Math.abs(end.y - start.y) < 4;
        const opacity = s.opacity / 100;
        const base = { originX: 'left', originY: 'top', strokeUniform: true, objectId: uid() };
        const created = [];

        if (['highlight', 'underline', 'strike'].includes(this.tool)) {
            const lines = await this.textLinesIn(page, start, end, tiny);

            if (!lines.length && tiny) return created;
            const boxes = lines.length ? lines : [{ x0: Math.min(start.x, end.x), x1: Math.max(start.x, end.x), y0: Math.min(start.y, end.y), y1: Math.max(start.y, end.y) }];

            for (const box of boxes) {
                const height = box.y1 - box.y0;
                let shape;

                if (this.tool === 'highlight') {
                    shape = new Rect({ ...base, objectId: uid(), left: box.x0, top: box.y0, width: box.x1 - box.x0, height, fill: s.highlight, opacity: s.highlightOpacity / 100, globalCompositeOperation: 'multiply', strokeWidth: 0, kind: 'highlight' });
                } else {
                    const y = this.tool === 'underline' ? box.y1 - height * 0.1 : box.y0 + height * 0.55;
                    shape = new Line([box.x0, y, box.x1, y], { ...base, objectId: uid(), stroke: s.color, strokeWidth: Math.max(1, s.strokeWidth * 0.5), opacity, kind: this.tool });
                }

                canvas.add(shape);
                created.push(shape);
            }
            return created;
        }

        let x0 = Math.min(start.x, end.x);
        let y0 = Math.min(start.y, end.y);
        let w = Math.abs(end.x - start.x);
        let hgt = Math.abs(end.y - start.y);

        if (tiny) {
            // A simple click creates a default sized shape.
            w = 140;
            hgt = this.tool === 'ellipse' ? 140 : 90;
            x0 = start.x - w / 2;
            y0 = start.y - hgt / 2;
            end = { x: start.x + 120, y: start.y };
        }

        const stroke = { stroke: s.color, strokeWidth: s.strokeWidth, opacity };
        const fill = s.filled ? s.fill : 'transparent';
        let shape;

        if (this.tool === 'rect') shape = new Rect({ ...base, ...stroke, left: x0, top: y0, width: w, height: hgt, fill, kind: 'shape' });
        else if (this.tool === 'ellipse') shape = new Ellipse({ ...base, ...stroke, left: x0, top: y0, rx: w / 2, ry: hgt / 2, fill, kind: 'shape' });
        else if (this.tool === 'line') shape = new Line([start.x, start.y, end.x, end.y], { ...base, ...stroke, strokeLineCap: 'round', kind: 'shape' });
        else if (this.tool === 'arrow') shape = this.arrow(start, end, stroke);

        if (shape) {
            canvas.add(shape);
            created.push(shape);
        }

        return created;
    }

    arrow(start, end, stroke) {
        const angle = Math.atan2(end.y - start.y, end.x - start.x);
        const head = Math.max(10, stroke.strokeWidth * 4);
        const spread = Math.PI / 7;
        const p1 = { x: end.x - head * Math.cos(angle - spread), y: end.y - head * Math.sin(angle - spread) };
        const p2 = { x: end.x - head * Math.cos(angle + spread), y: end.y - head * Math.sin(angle + spread) };
        const d = `M ${start.x} ${start.y} L ${end.x} ${end.y} M ${p1.x} ${p1.y} L ${end.x} ${end.y} L ${p2.x} ${p2.y}`;

        return new Path(d, { ...stroke, fill: '', strokeLineCap: 'round', strokeLineJoin: 'round', strokeUniform: true, objectId: uid(), kind: 'shape' });
    }

    /**
     * Text lines (from the PDF) touched by a drag or click, as boxes snapped
     * to the text height.
     */
    async textLinesIn(page, start, end, click) {
        const items = await page.textItems();
        const rect = { x0: Math.min(start.x, end.x), x1: Math.max(start.x, end.x), y0: Math.min(start.y, end.y), y1: Math.max(start.y, end.y) };

        const hits = items.filter((it) => {
            if (click) return start.x >= it.x && start.x <= it.x + it.width && start.y >= it.y && start.y <= it.y + it.height;
            const ox = Math.min(rect.x1, it.x + it.width) - Math.max(rect.x0, it.x);
            const oy = Math.min(rect.y1, it.y + it.height) - Math.max(rect.y0, it.y);
            return ox > 0 && oy > it.height * 0.3;
        });

        const lines = [];
        for (const it of hits.sort((a, b) => a.y - b.y || a.x - b.x)) {
            const cy = it.y + it.height / 2;
            const line = lines.find((l) => Math.abs((l.y0 + l.y1) / 2 - cy) < it.height * 0.5);
            if (line) {
                line.x0 = Math.min(line.x0, it.x);
                line.x1 = Math.max(line.x1, it.x + it.width);
                line.y0 = Math.min(line.y0, it.y);
                line.y1 = Math.max(line.y1, it.y + it.height);
            } else {
                lines.push({ x0: it.x, x1: it.x + it.width, y0: it.y, y1: it.y + it.height });
            }
        }

        if (!click) {
            // Rectangular selection: clip every line to the dragged box.
            for (const line of lines) {
                line.x0 = Math.max(line.x0, rect.x0);
                line.x1 = Math.min(line.x1, rect.x1);
            }
        }

        return lines.filter((l) => l.x1 - l.x0 > 1);
    }

    addText(page, point) {
        const s = this.style;
        const text = new IText('Type here', {
            left: point.x,
            top: point.y - s.fontSize * 0.6,
            originX: 'left',
            originY: 'top',
            fontFamily: FONTS[s.fontFamily].css,
            pdfFont: s.fontFamily,
            fontSize: s.fontSize,
            fill: s.color,
            fontWeight: s.bold ? 'bold' : 'normal',
            fontStyle: s.italic ? 'italic' : 'normal',
            underline: s.underline,
            opacity: s.opacity / 100,
            kind: 'text',
            objectId: uid(),
        });

        page.canvas.add(text);
        page.canvas.setActiveObject(text);
        text.enterEditing();
        text.selectAll();
        this.setTool('select');
    }

    /**
     * "Edit text": covers the original text with its background colour and
     * puts an editable copy with matching size, colour and style on top.
     */
    async editTextItem(page, item) {
        if (!this.fontsLoaded.has(page)) {
            await page.pdfPage.getOperatorList();
            this.fontsLoaded.add(page);
        }

        const colors = page.sampleColors(item);
        const style = await fontStyle(page.pdfPage, item.fontName, item.fontFamily);
        const coverId = uid('c');

        const cover = new Rect({
            left: item.x - 1,
            top: item.y - 1,
            width: item.width + 2,
            height: item.height + 2,
            originX: 'left',
            originY: 'top',
            fill: colors.background,
            strokeWidth: 0,
            selectable: false,
            evented: false,
            kind: 'cover',
            objectId: coverId,
        });

        const text = new IText(item.str, {
            left: item.x,
            top: item.y + item.size * 0.02,
            originX: 'left',
            originY: 'top',
            fontFamily: FONTS[style.family].css,
            pdfFont: style.family,
            fontSize: item.size,
            fill: colors.text,
            fontWeight: style.bold ? 'bold' : 'normal',
            fontStyle: style.italic ? 'italic' : 'normal',
            kind: 'edit-text',
            linkId: coverId,
            objectId: uid(),
        });

        page.showHit(null);
        page.canvas.add(cover, text);
        page.canvas.setActiveObject(text);
        text.enterEditing();
        text.setSelectionStart(text.text.length);
        text.setSelectionEnd(text.text.length);
        page.canvas.requestRenderAll();
    }

    erase(page, target) {
        if (!target || target.temp || target.kind === 'cover') return;
        page.canvas.remove(target);
        page.canvas.requestRenderAll();
    }

    async addImage(url, kind, page = this.currentPage()) {
        const canvas = await page.ensureCanvas();
        const image = await FabricImage.fromURL(url);
        const center = this.visibleCenter(page);
        const maxWidth = page.width * (kind === 'signature' ? 0.32 : 0.5);
        const scale = Math.min(1, maxWidth / image.width, (page.height * 0.5) / image.height);

        image.set({ left: center.x, top: center.y, originX: 'center', originY: 'center', scaleX: scale, scaleY: scale, kind, objectId: uid() });
        canvas.add(image);
        await this.setTool('select');
        canvas.setActiveObject(image);
        canvas.requestRenderAll();
        this.updateProps();
    }

    async addStamp(stamp, page = this.currentPage()) {
        const canvas = await page.ensureCanvas();
        const label = stamp.date ? `${stamp.label} ${new Date().toLocaleDateString()}` : stamp.label;
        const text = new FabricText(label, { fontFamily: FONTS.helvetica.css, pdfFont: 'helvetica', fontWeight: 'bold', fontSize: 26, fill: stamp.color, originX: 'center', originY: 'center', left: 0, top: 0 });
        const frame = new Rect({ width: text.width + 34, height: text.height + 16, rx: 8, ry: 8, fill: 'transparent', stroke: stamp.color, strokeWidth: 4, originX: 'center', originY: 'center', left: 0, top: 0 });
        const center = this.visibleCenter(page);
        const group = new Group([frame, text], { left: center.x, top: center.y, originX: 'center', originY: 'center', angle: -8, opacity: 0.9, kind: 'stamp', objectId: uid() });

        canvas.add(group);
        await this.setTool('select');
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
    }

    /** Adds an object to a page even when its canvas does not exist yet. */
    placeObject(page, object) {
        if (page.canvas) {
            page.canvas.add(object);
            page.canvas.requestRenderAll();
            return;
        }

        const data = page.json ? JSON.parse(page.json) : { objects: [] };
        data.objects.push(object.toObject(CUSTOM_PROPS));
        page.json = JSON.stringify(data);
        page.dirty = true;
    }

    addWatermark({ text, size, color, opacity, angle }) {
        for (const page of this.pages) {
            this.placeObject(
                page,
                new FabricText(text, {
                    left: page.width / 2,
                    top: page.height / 2,
                    originX: 'center',
                    originY: 'center',
                    fontFamily: FONTS.helvetica.css,
                    pdfFont: 'helvetica',
                    fontWeight: 'bold',
                    fontSize: size,
                    fill: color,
                    opacity,
                    angle: -angle,
                    kind: 'watermark',
                    objectId: uid(),
                }),
            );
        }
        this.commit();
        toast(`Watermark added to ${this.pages.length} page${this.pages.length === 1 ? '' : 's'}.`, 'success');
    }

    addPageNumbers({ position, format, size, color }) {
        const [row, col] = position.split('-');
        const margin = 24;

        this.pages.forEach((page, i) => {
            const label = format.replace('{n}', i + 1).replace('{total}', this.pages.length);
            this.placeObject(
                page,
                new FabricText(label, {
                    left: col === 'left' ? margin : col === 'right' ? page.width - margin : page.width / 2,
                    top: row === 'top' ? margin : page.height - margin,
                    originX: col === 'left' ? 'left' : col === 'right' ? 'right' : 'center',
                    originY: row === 'top' ? 'top' : 'bottom',
                    fontFamily: FONTS.helvetica.css,
                    pdfFont: 'helvetica',
                    fontSize: size,
                    fill: color,
                    kind: 'page-number',
                    numberFormat: format,
                    objectId: uid(),
                }),
            );
        });

        this.commit();
        toast('Page numbers added.', 'success');
    }

    // ------------------------------------------------------------------
    // Pages
    // ------------------------------------------------------------------

    async pageAction(action, page) {
        if (action === 'rotate-left' || action === 'rotate-right') {
            await page.rotate(action === 'rotate-right' ? 90 : -90);
            this.renderThumb(page);
            this.commit();
        } else if (action === 'duplicate') {
            const copy = new PageView(this, { id: uid('p'), srcIndex: page.srcIndex, rotation: page.rotation, json: page.serialize() });
            await copy.init();
            this.allPages.set(copy.id, copy);
            this.pages.splice(this.pages.indexOf(page) + 1, 0, copy);
            page.el.after(copy.el);
            this.observer.observe(copy.el);
            this.afterStructureChange();
            toast('Page duplicated.', 'success', 2000);
        } else if (action === 'delete') {
            if (this.pages.length === 1) {
                toast('A PDF needs at least one page.', 'error');
                return;
            }
            this.pages.splice(this.pages.indexOf(page), 1);
            page.el.remove();
            this.afterStructureChange();
        }
    }

    afterStructureChange() {
        this.renumber();
        this.buildThumbs();
        this.commit();
    }

    renumber() {
        const total = this.pages.length;
        this.pages.forEach((page, i) => {
            page.setNumber(i + 1, total);

            const update = (obj) => {
                if (obj.kind === 'page-number' && obj.numberFormat) {
                    obj.text = obj.numberFormat.replace('{n}', i + 1).replace('{total}', total);
                }
            };

            if (page.canvas) {
                page.canvas.getObjects().forEach((obj) => {
                    if (obj.kind === 'page-number') {
                        update(obj);
                        obj.initDimensions?.();
                        obj.setCoords();
                    }
                });
                page.canvas.requestRenderAll();
            } else if (page.json) {
                const data = JSON.parse(page.json);
                data.objects.forEach(update);
                page.json = JSON.stringify(data);
            }
        });

        $('[data-page-info]', this.root).textContent = `${total} page${total === 1 ? '' : 's'}`;
    }

    buildThumbs() {
        if (!this.thumbs) return;
        this.thumbs.replaceChildren(
            ...this.pages.map((page, i) => {
                const canvas = h('canvas', { class: 'mx-auto max-h-36 max-w-full rounded border border-slate-200 bg-white shadow-sm' });
                const item = h(
                    'button',
                    { type: 'button', class: 'block w-full rounded-lg p-1.5 text-center hover:bg-slate-100 dark:hover:bg-slate-800', dataset: { pageId: page.id }, onclick: () => page.el.scrollIntoView({ behavior: 'smooth', block: 'start' }) },
                    canvas,
                    h('span', { class: 'mt-1 block text-xs text-slate-500' }, String(i + 1)),
                );
                page.thumbCanvas = canvas;
                this.renderThumb(page);
                return item;
            }),
        );

        if (!this.sortable) {
            import('sortablejs').then(({ default: Sortable }) => {
                this.sortable = Sortable.create(this.thumbs, {
                    animation: 160,
                    ghostClass: 'sortable-ghost',
                    onEnd: () => {
                        const order = [...this.thumbs.children].map((el) => el.dataset.pageId);
                        this.pages.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
                        this.pages.forEach((p) => this.host.append(p.el));
                        this.afterStructureChange();
                    },
                });
            });
        }
    }

    async renderThumb(page) {
        if (!page.thumbCanvas) return;
        try {
            const viewport = page.pdfPage.getViewport({ scale: 140 / Math.max(page.width, page.height), rotation: page.totalRotation });
            page.thumbCanvas.width = Math.ceil(viewport.width);
            page.thumbCanvas.height = Math.ceil(viewport.height);
            await page.pdfPage.render({ canvas: page.thumbCanvas, viewport }).promise;
        } catch {
            /* ignore */
        }
    }

    // ------------------------------------------------------------------
    // History
    // ------------------------------------------------------------------

    changed(page) {
        if (this.restoring) return;
        page.dirty = true;
        this.commitLater();
    }

    commit(initial = false) {
        if (this.restoring) return;

        const pages = {};
        for (const page of this.allPages.values()) {
            if (page.dirty || page.snapshot === undefined) {
                page.snapshot = page.serialize();
                page.dirty = false;
            }
            pages[page.id] = { srcIndex: page.srcIndex, rotation: page.rotation, json: page.snapshot };
        }

        const state = { order: this.pages.map((p) => p.id), pages };
        const current = this.history.current;

        if (!initial && current && JSON.stringify(current.order) === JSON.stringify(state.order) && Object.keys(pages).every((id) => current.pages[id]?.json === pages[id].json && current.pages[id]?.rotation === pages[id].rotation)) {
            return;
        }

        this.history.push(state);
        this.updateHistoryButtons();
    }

    async restore(state) {
        if (!state) return;
        this.restoring++;

        try {
            for (const id of state.order) {
                const page = this.allPages.get(id);
                const saved = state.pages[id];

                if (page.rotation !== saved.rotation) {
                    page.rotation = saved.rotation;
                    page.updateViewport();
                    page.renderedZoom = 0;
                    page.layout();
                }

                if (page.snapshot !== saved.json) {
                    await page.load(saved.json);
                    page.snapshot = saved.json;
                }
            }

            this.pages.forEach((p) => p.el.remove());
            this.pages = state.order.map((id) => this.allPages.get(id));
            this.pages.forEach((p) => {
                this.host.append(p.el);
                this.observer.observe(p.el);
            });

            this.renumber();
            this.buildThumbs();
            this.renderVisible();
        } finally {
            this.restoring--;
        }

        this.updateHistoryButtons();
        this.updateProps();
    }

    updateHistoryButtons() {
        $('[data-action="undo"]', this.root).disabled = !this.history.canUndo();
        $('[data-action="redo"]', this.root).disabled = !this.history.canRedo();
    }

    // ------------------------------------------------------------------
    // Properties bar
    // ------------------------------------------------------------------

    activeObjects() {
        for (const page of this.pages) {
            const objects = page.canvas?.getActiveObjects() || [];
            if (objects.length) return { page, objects };
        }
        return { page: null, objects: [] };
    }

    updateProps() {
        const { objects } = this.activeObjects();
        const info = TOOLS[this.tool] || TOOLS.select;
        let groups = info.groups;
        let title = info.title;

        if (objects.length) {
            const obj = objects[0];
            title = objects.length > 1 ? `${objects.length} objects selected` : 'Selected';

            if (TEXT_KINDS.has(obj.kind)) groups = ['font', 'color', 'opacity'];
            else if (obj.kind === 'highlight') groups = ['swatches', 'opacity'];
            else if (obj.kind === 'draw' || obj.kind === 'underline' || obj.kind === 'strike') groups = ['color', 'width', 'opacity'];
            else if (obj.kind === 'shape') groups = obj instanceof Line || obj instanceof Path ? ['color', 'width', 'opacity'] : ['color', 'fill', 'width', 'opacity'];
            else groups = ['opacity'];

            groups = [...groups, 'object'];
            this.syncControls(obj);
        }

        $$('[data-prop-group]', this.root).forEach((el) => show(el, groups.includes(el.dataset.propGroup)));
        $('[data-props-title]', this.root).textContent = title;
        $('[data-props-hint]', this.root).textContent = objects.length ? '' : info.hint;

        const opacity = $('[data-prop="opacity"]', this.root);
        opacity.value = this.tool === 'highlight' && !objects.length ? this.style.highlightOpacity : objects.length ? Math.round((objects[0].opacity ?? 1) * 100) : this.style.opacity;
    }

    syncControls(obj) {
        const set = (prop, value) => {
            const el = $(`[data-prop="${prop}"]`, this.root);
            if (el && value !== undefined && value !== null) el.type === 'checkbox' ? (el.checked = Boolean(value)) : (el.value = value);
        };

        if (obj instanceof FabricText) {
            set('fontFamily', obj.pdfFont || 'helvetica');
            set('fontSize', Math.round(obj.fontSize));
            set('color', this.toHex(obj.fill));
            this.setToggle('bold', obj.fontWeight === 'bold');
            this.setToggle('italic', obj.fontStyle === 'italic');
            this.setToggle('underline', Boolean(obj.underline));
        } else {
            if (obj.stroke) set('color', this.toHex(obj.stroke));
            if (obj.strokeWidth) set('strokeWidth', Math.round(obj.strokeWidth));
            set('filled', obj.fill && obj.fill !== 'transparent');
            if (obj.fill && obj.fill !== 'transparent' && obj.kind !== 'highlight') set('fill', this.toHex(obj.fill));
        }
    }

    setToggle(name, value) {
        const el = $(`[data-prop-toggle="${name}"]`, this.root);
        if (el) el.setAttribute('aria-pressed', String(Boolean(value)));
    }

    applyToSelection(changes) {
        const { page, objects } = this.activeObjects();
        if (!objects.length) return;

        for (const obj of objects) {
            const values = typeof changes === 'function' ? changes(obj) : changes;
            if (values) obj.set(values);
            obj.initDimensions?.();
            obj.setCoords();
        }

        page.canvas.requestRenderAll();
        this.changed(page);
    }

    onPropChange(prop, el) {
        const value = el.type === 'checkbox' ? el.checked : el.type === 'range' || prop === 'fontSize' ? Number(el.value) : el.value;

        if (prop === 'opacity' && this.tool === 'highlight' && !this.activeObjects().objects.length) {
            this.style.highlightOpacity = value;
        } else {
            this.style[prop] = value;
        }

        const isText = (o) => o instanceof FabricText;

        this.applyToSelection((obj) => {
            switch (prop) {
                case 'fontFamily':
                    return isText(obj) ? { fontFamily: FONTS[value].css, pdfFont: value } : null;
                case 'fontSize':
                    return isText(obj) ? { fontSize: value } : null;
                case 'color':
                    return isText(obj) ? { fill: value } : { stroke: value };
                case 'fill':
                    return !isText(obj) && obj.fill && obj.fill !== 'transparent' ? { fill: value } : null;
                case 'filled':
                    return isText(obj) || obj instanceof Line || obj instanceof Path ? null : { fill: value ? this.style.fill : 'transparent' };
                case 'strokeWidth':
                    return isText(obj) ? null : { strokeWidth: value };
                case 'opacity':
                    return { opacity: value / 100 };
                default:
                    return null;
            }
        });

        if (this.tool === 'draw') this.pages.forEach((p) => this.configureCanvas(p));
    }

    // ------------------------------------------------------------------
    // UI bindings
    // ------------------------------------------------------------------

    bind() {
        if (this.bound) return;
        this.bound = true;

        $$('[data-tool]', this.root).forEach((button) => button.addEventListener('click', () => this.setTool(button.dataset.tool)));

        $$('[data-prop]', this.root).forEach((el) => {
            el.addEventListener(el.type === 'range' || el.type === 'color' ? 'input' : 'change', () => this.onPropChange(el.dataset.prop, el));
        });

        $$('[data-prop-toggle]', this.root).forEach((button) =>
            button.addEventListener('click', () => {
                const name = button.dataset.propToggle;
                const value = button.getAttribute('aria-pressed') !== 'true';
                button.setAttribute('aria-pressed', String(value));
                this.style[name] = value;
                this.applyToSelection((obj) => {
                    if (!(obj instanceof FabricText)) return null;
                    if (name === 'bold') return { fontWeight: value ? 'bold' : 'normal' };
                    if (name === 'italic') return { fontStyle: value ? 'italic' : 'normal' };
                    return { underline: value };
                });
            }),
        );

        $$('[data-swatch]', this.root).forEach((button) =>
            button.addEventListener('click', () => {
                this.style.highlight = button.dataset.swatch;
                this.applyToSelection((obj) => (obj.kind === 'highlight' ? { fill: button.dataset.swatch } : null));
            }),
        );

        const actions = {
            undo: () => this.restore(this.history.undo()),
            redo: () => this.restore(this.history.redo()),
            'zoom-in': () => this.setZoom(this.zoom * 1.2),
            'zoom-out': () => this.setZoom(this.zoom / 1.2),
            'zoom-fit': () => this.setZoom(this.fitZoom()),
            fullscreen: () => (document.fullscreenElement ? document.exitFullscreen() : this.root.requestFullscreen?.()),
            download: () => this.download(),
            close: () => this.close(),
            'delete-object': () => this.deleteSelection(),
            'duplicate-object': () => this.duplicateSelection(),
        };

        $$('[data-action]', this.root).forEach((button) => button.addEventListener('click', () => actions[button.dataset.action]?.()));

        this.imageInput.addEventListener('change', async () => {
            const file = this.imageInput.files?.[0];
            this.imageInput.value = '';
            if (!file) return;
            if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) {
                toast('Please choose a PNG, JPG or WEBP image.', 'error');
                return;
            }
            const url = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.readAsDataURL(file);
            });
            await this.addImage(url, 'image');
        });

        this.viewport.addEventListener('scroll', () => this.renderVisible(), { passive: true });
        window.addEventListener('resize', debounce(() => this.renderVisible(), 200));

        this.keyHandler = (event) => this.onKey(event);
        document.addEventListener('keydown', this.keyHandler);

        this.beforeUnload = (event) => {
            if (this.history.canUndo()) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this.beforeUnload);
    }

    onKey(event) {
        const target = event.target;
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName) || target?.isContentEditable;
        const editingText = this.pages.some((p) => p.canvas?.getActiveObject()?.isEditing);

        if (event.key === 'Escape' && !editingText) {
            this.setTool('select');
            this.pages.forEach((p) => p.canvas?.discardActiveObject() && p.canvas.requestRenderAll());
            return;
        }

        if (typing || editingText || document.querySelector('dialog[open]')) return;

        const ctrl = event.ctrlKey || event.metaKey;

        if (ctrl && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            this.restore(event.shiftKey ? this.history.redo() : this.history.undo());
        } else if (ctrl && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            this.restore(this.history.redo());
        } else if (ctrl && event.key.toLowerCase() === 'd') {
            event.preventDefault();
            this.duplicateSelection();
        } else if (event.key === 'Delete' || event.key === 'Backspace') {
            if (this.activeObjects().objects.length) {
                event.preventDefault();
                this.deleteSelection();
            }
        } else if (!ctrl && !event.altKey) {
            const map = { v: 'select', e: 'edit-text', t: 'text', h: 'highlight', u: 'underline', s: 'strike', p: 'draw', x: 'eraser', r: 'rect', o: 'ellipse', l: 'line', a: 'arrow', i: 'image', g: 'signature' };
            const tool = map[event.key.toLowerCase()];
            if (tool) this.setTool(tool);
        }
    }

    deleteSelection() {
        const { page, objects } = this.activeObjects();
        if (!objects.length) return;
        page.canvas.discardActiveObject();
        objects.forEach((obj) => page.canvas.remove(obj));
        page.canvas.requestRenderAll();
    }

    async duplicateSelection() {
        const { page, objects } = this.activeObjects();
        if (!objects.length) return;

        const clones = [];
        for (const obj of objects) {
            if (obj.kind === 'cover') continue;
            const clone = await obj.clone(CUSTOM_PROPS);
            clone.set({ left: clone.left + 16, top: clone.top + 16, objectId: uid(), linkId: null });
            page.canvas.add(clone);
            clones.push(clone);
        }

        page.canvas.discardActiveObject();
        if (clones.length === 1) page.canvas.setActiveObject(clones[0]);
        page.canvas.requestRenderAll();
    }

    setZoom(value) {
        const zoom = Math.max(0.25, Math.min(4, value));
        const ratio = zoom / this.zoom;
        const scrollTop = this.viewport.scrollTop;

        this.zoom = zoom;
        this.pages.forEach((p) => {
            p.layout();
            p.showHit(null);
        });
        this.viewport.scrollTop = scrollTop * ratio;
        $('[data-zoom-label]', this.root).textContent = `${Math.round(zoom * 100)}%`;
        this.renderVisible();
    }

    applyPreset() {
        $('[data-zoom-label]', this.root).textContent = `${Math.round(this.zoom * 100)}%`;
        const preset = this.preset;

        if (preset === 'signature') {
            this.setTool('select').then(() => this.setTool('signature'));
        } else if (preset === 'image') {
            this.setTool('select');
            const hint = $('[data-props-hint]', this.root);
            hint.replaceChildren(h('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: () => this.setTool('image') }, 'Choose an image to add'));
        } else if (preset && TOOLS[preset]) {
            this.setTool(preset);
        } else {
            this.setTool('select');
        }
    }

    async download() {
        this.pages.forEach((p) => p.canvas?.getActiveObject()?.exitEditing?.());

        const status = h('p', { class: 'text-sm text-slate-600 dark:text-slate-300' }, 'Preparing your PDF…');
        const bar = h('div', { class: 'progress-bar indeterminate' });
        const modal = openModal({ title: 'Saving your PDF', body: h('div', { class: 'space-y-4' }, status, h('div', { class: 'progress-track' }, bar)) });

        try {
            const bytes = await exportPdf(this, (percent, message) => {
                status.textContent = message;
                bar.classList.remove('indeterminate');
                bar.style.width = `${Math.round(percent)}%`;
            });

            download(new Blob([bytes], { type: 'application/pdf' }), outputName(this.file.name, 'edited', 'pdf'));
            recordUsage(this.config.endpoints?.usage);
            modal.close();
            toast('Your edited PDF has been downloaded.', 'success');
        } catch (error) {
            modal.close();
            toast(toToolError(error).message, 'error', 6000);
            console.error(error);
        }
    }

    async close() {
        if (this.history.canUndo() && !(await confirmDialog({ title: 'Close this document?', message: 'Your changes will be lost unless you download the PDF first.', confirmLabel: 'Close without saving', danger: true }))) {
            return;
        }

        this.destroy();
        document.body.classList.remove('editor-open');
        window.scrollTo({ top: 0 });
    }

    destroy() {
        this.observer?.disconnect();
        document.removeEventListener('keydown', this.keyHandler);
        window.removeEventListener('beforeunload', this.beforeUnload);
        this.allPages.forEach((p) => p.destroy());
        this.sortable?.destroy();
        this.thumbs?.replaceChildren();
        closePdfJs(this.pdf);
        activeEditor = null;
    }

    // ------------------------------------------------------------------

    rgba(hex, alpha) {
        const value = hex.replace('#', '');
        const [r, g, b] = [0, 2, 4].map((i) => parseInt(value.slice(i, i + 2), 16));
        return `rgba(${r},${g},${b},${alpha})`;
    }

    toHex(color) {
        if (!color || typeof color !== 'string') return '#000000';
        if (color.startsWith('#')) return color.length === 4 ? `#${[...color.slice(1)].map((c) => c + c).join('')}` : color.slice(0, 7);
        const match = color.match(/\d+/g);
        if (!match) return '#000000';
        return `#${match
            .slice(0, 3)
            .map((n) => Number(n).toString(16).padStart(2, '0'))
            .join('')}`;
    }
}

let activeEditor = null;

/**
 * Editor page: pick a PDF, then open the workspace.
 */
export default async function initEditorPage() {
    const root = $('#editor');
    if (!root) return;

    const config = JSON.parse(root.dataset.config);
    const input = $('[data-editor-input]');
    const zone = $('[data-editor-dropzone]');

    async function open(files) {
        const { valid, errors } = await validateFiles(files.slice(0, 1), { extensions: ['pdf'], maxBytes: config.maxBytes, label: 'PDF' });
        errors.forEach((e) => toast(e.message, 'error', 5000));
        if (!valid.length) return;

        const file = valid[0];
        zone.classList.add('opacity-60', 'pointer-events-none');

        try {
            const bytes = await readBytes(file);
            const pdf = await openPdfJs(bytes, { fileName: file.name, file });
            activeEditor?.destroy();
            activeEditor = new Editor(root, { file, bytes, pdf, config, preset: root.dataset.preset || null });
            await activeEditor.mount();
        } catch (error) {
            toast(toToolError(error).message, 'error', 6000);
            document.body.classList.remove('editor-open');
        } finally {
            zone.classList.remove('opacity-60', 'pointer-events-none');
        }
    }

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });
    input.addEventListener('change', () => {
        const files = [...input.files];
        input.value = '';
        open(files);
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
        open([...(e.dataTransfer?.files || [])]);
    });

    const handed = await takeFiles();
    if (handed.length) open(handed);

    // Test hook (used by automated browser tests).
    window.__pdfEditor = () => activeEditor;
}
