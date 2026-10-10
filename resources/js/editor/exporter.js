import { Color, FabricImage, Group, FabricText, Rect, Ellipse, Circle, Line, Path, StaticCanvas, util } from 'fabric';
import { pdfLib, loadPdf } from '../lib/pdf';
import { fontFor } from '../lib/fonts';

const FLIP = [1, 0, 0, -1, 0, 0];

function invert([a, b, c, d, e, f]) {
    const det = a * d - b * c;
    return [d / det, -b / det, -c / det, a / det, (c * f - d * e) / det, (b * e - a * f) / det];
}

/**
 * Writes the edited document with pdf-lib. Every object becomes real PDF
 * content (vector text with real fonts, vector shapes and paths, images),
 * so text stays selectable and shapes stay sharp at any zoom.
 */
export async function exportPdf(editor, onProgress) {
    const lib = await pdfLib();
    const { degrees, rgb, BlendMode, LineCapStyle, pushGraphicsState, popGraphicsState, concatTransformationMatrix } = lib;

    const { PDFDocument } = lib;
    const { doc: source } = await loadPdf(editor.bytes, { fileName: editor.file.name, file: editor.file });

    // 1. Page order, duplicates and deleted pages: copy the pages into a new
    //    document (robust for every page-tree layout) and keep the metadata.
    const doc = await PDFDocument.create({ updateMetadata: false });
    const copies = await doc.copyPages(source, editor.pages.map((view) => view.srcIndex));
    const ordered = editor.pages.map((view, i) => ({ view, page: doc.addPage(copies[i]) }));

    for (const key of ['Title', 'Author', 'Subject', 'Creator', 'Producer']) {
        const value = source[`get${key}`]?.();
        if (value) doc[`set${key}`](value);
    }
    const keywords = source.getKeywords?.();
    if (keywords) doc.setKeywords(keywords.split(/[,;]\s*/));
    doc.setModificationDate(new Date());

    // 2. Objects.
    const images = new Map();

    const colorOf = (value) => {
        if (!value || value === 'transparent' || typeof value !== 'string') return null;
        const [r, g, b, a] = new Color(value).getSource();
        return { color: rgb(r / 255, g / 255, b / 255), alpha: a ?? 1 };
    };

    const embedImage = async (src) => {
        if (images.has(src)) return images.get(src);

        let promise;
        if (/^data:image\/png/i.test(src)) promise = doc.embedPng(src);
        else if (/^data:image\/jpe?g/i.test(src)) promise = doc.embedJpg(src);
        else {
            promise = (async () => {
                const img = new Image();
                img.src = src;
                await img.decode();
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                return doc.embedPng(canvas.toDataURL('image/png'));
            })();
        }

        images.set(src, promise);
        return promise;
    };

    async function draw(page, obj, viewInverse) {
        if (obj.excludeFromExport || obj.visible === false) return;

        if (obj instanceof Group) {
            for (const child of obj.getObjects()) await draw(page, child, viewInverse);
            return;
        }

        const matrix = util.multiplyTransformMatrices(viewInverse, util.multiplyTransformMatrices(obj.calcTransformMatrix(), FLIP));
        const opacity = obj.opacity ?? 1;
        const fill = colorOf(obj.fill);
        const stroke = obj.strokeWidth > 0 ? colorOf(obj.stroke) : null;
        const blendMode = obj.globalCompositeOperation === 'multiply' ? BlendMode.Multiply : undefined;

        page.pushOperators(pushGraphicsState(), concatTransformationMatrix(...matrix));

        try {
            // IText and Textbox extend FabricText.
            if (obj instanceof FabricText) {
                await drawText(page, obj, fill, opacity);
            } else if (obj instanceof FabricImage) {
                const image = await embedImage(obj.getSrc());
                page.drawImage(image, { x: -obj.width / 2, y: -obj.height / 2, width: obj.width, height: obj.height, opacity, blendMode });
            } else if (obj instanceof Rect && (obj.rx > 0 || obj.ry > 0)) {
                // Rounded rectangle as an SVG path (y-down local coordinates).
                const w = obj.width / 2;
                const hh = obj.height / 2;
                const rx = Math.min(obj.rx || obj.ry, w);
                const ry = Math.min(obj.ry || obj.rx, hh);
                const d = `M ${-w + rx} ${-hh} H ${w - rx} Q ${w} ${-hh} ${w} ${-hh + ry} V ${hh - ry} Q ${w} ${hh} ${w - rx} ${hh} H ${-w + rx} Q ${-w} ${hh} ${-w} ${hh - ry} V ${-hh + ry} Q ${-w} ${-hh} ${-w + rx} ${-hh} Z`;
                page.drawSvgPath(d, {
                    x: 0,
                    y: 0,
                    color: fill?.color,
                    opacity: fill ? fill.alpha * opacity : undefined,
                    borderColor: stroke?.color,
                    borderWidth: stroke ? obj.strokeWidth : 0,
                    borderOpacity: stroke ? stroke.alpha * opacity : undefined,
                    blendMode,
                });
            } else if (obj instanceof Rect) {
                page.drawRectangle({
                    x: -obj.width / 2,
                    y: -obj.height / 2,
                    width: obj.width,
                    height: obj.height,
                    color: fill?.color,
                    opacity: fill ? fill.alpha * opacity : undefined,
                    borderColor: stroke?.color,
                    borderWidth: stroke ? obj.strokeWidth : 0,
                    borderOpacity: stroke ? stroke.alpha * opacity : undefined,
                    blendMode,
                });
            } else if (obj instanceof Circle || obj instanceof Ellipse) {
                const rx = obj instanceof Circle ? obj.radius : obj.rx;
                const ry = obj instanceof Circle ? obj.radius : obj.ry;
                page.drawEllipse({
                    x: 0,
                    y: 0,
                    xScale: rx,
                    yScale: ry,
                    color: fill?.color,
                    opacity: fill ? fill.alpha * opacity : undefined,
                    borderColor: stroke?.color,
                    borderWidth: stroke ? obj.strokeWidth : 0,
                    borderOpacity: stroke ? stroke.alpha * opacity : undefined,
                    blendMode,
                });
            } else if (obj instanceof Line) {
                const { x1, y1, x2, y2 } = obj.calcLinePoints();
                if (stroke) {
                    page.drawLine({
                        start: { x: x1, y: -y1 },
                        end: { x: x2, y: -y2 },
                        thickness: obj.strokeWidth,
                        color: stroke.color,
                        opacity: stroke.alpha * opacity,
                        lineCap: obj.strokeLineCap === 'round' ? LineCapStyle.Round : LineCapStyle.Butt,
                        blendMode,
                    });
                }
            } else if (obj instanceof Path) {
                const offset = obj.pathOffset || { x: 0, y: 0 };
                const d = obj.path
                    .map(([command, ...values]) => command + values.map((v, i) => (i % 2 === 0 ? v - offset.x : v - offset.y)).join(' '))
                    .join(' ');

                page.drawSvgPath(d, {
                    x: 0,
                    y: 0,
                    color: fill?.color,
                    opacity: fill ? fill.alpha * opacity : undefined,
                    borderColor: stroke?.color,
                    borderWidth: stroke ? obj.strokeWidth : 0,
                    borderOpacity: stroke ? stroke.alpha * opacity : undefined,
                    borderLineCap: obj.strokeLineCap === 'round' ? LineCapStyle.Round : undefined,
                    blendMode,
                });
            }
        } finally {
            page.pushOperators(popGraphicsState());
        }
    }

    async function drawText(page, obj, fill, opacity) {
        const lines = obj.textLines || String(obj.text || '').split('\n');
        const all = lines.join(' ');
        if (!all.trim()) return;

        const font = await fontFor(doc, all, {
            family: obj.pdfFont || 'helvetica',
            bold: obj.fontWeight === 'bold' || Number(obj.fontWeight) >= 600,
            italic: obj.fontStyle === 'italic',
        });

        const size = obj.fontSize;
        const color = fill?.color || rgb(0, 0, 0);
        const alpha = (fill?.alpha ?? 1) * opacity;
        const fraction = obj._fontSizeFraction ?? 0.222;
        let top = -obj.height / 2;

        for (let i = 0; i < lines.length; i++) {
            const impl = obj.getHeightOfLineImpl ? obj.getHeightOfLineImpl(i) : size * 1.13;
            const baseline = top + impl * (1 - fraction);
            const text = lines[i];

            if (text) {
                const x = -obj.width / 2 + (obj._getLineLeftOffset ? obj._getLineLeftOffset(i) : 0);
                page.drawText(text, { x, y: -baseline, size, font, color, opacity: alpha });

                const width = obj.getLineWidth ? obj.getLineWidth(i) : font.widthOfTextAtSize(text, size);
                const thickness = Math.max(0.5, size / 16);

                if (obj.underline) {
                    page.drawLine({ start: { x, y: -baseline - size * 0.12 }, end: { x: x + width, y: -baseline - size * 0.12 }, thickness, color, opacity: alpha });
                }
                if (obj.linethrough) {
                    page.drawLine({ start: { x, y: -baseline + size * 0.3 }, end: { x: x + width, y: -baseline + size * 0.3 }, thickness, color, opacity: alpha });
                }
            }

            top += obj.getHeightOfLine ? obj.getHeightOfLine(i) : size * 1.16;
        }
    }

    for (const [n, { view, page }] of ordered.entries()) {
        onProgress?.((n / ordered.length) * 90, `Writing page ${n + 1} of ${ordered.length}…`);

        page.setRotation(degrees(view.totalRotation));

        let objects = [];
        if (view.canvas) {
            objects = view.canvas.getObjects();
        } else if (view.json) {
            // Never-visible page with objects (e.g. after undo): load off-screen.
            const offscreen = new StaticCanvas(null, { width: view.width, height: view.height });
            await offscreen.loadFromJSON(JSON.parse(view.json));
            objects = offscreen.getObjects();
        }

        objects = objects.filter((o) => !o.temp);
        if (!objects.length) continue;

        const viewInverse = invert(view.viewport.transform);
        for (const obj of objects) await draw(page, obj, viewInverse);
    }

    onProgress?.(95, 'Saving…');
    return doc.save({ useObjectStreams: true });
}
