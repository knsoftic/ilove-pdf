import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { outputName } from '../lib/files';

const KEEP = new Set(['/Link', '/Popup']);

/**
 * Flattens form fields and annotations into the page content so they can no
 * longer be edited (pdf-lib).
 */
export async function run({ files, options, progress }) {
    const lib = await pdfLib();
    const { PDFName, PDFDict, PDFArray, PDFRawStream, pushGraphicsState, popGraphicsState, concatTransformationMatrix, drawObject } = lib;
    const file = files[0];
    const { doc } = await openDocument(file);
    const context = doc.context;
    const N = (n) => PDFName.of(n);
    let flattened = 0;

    if (options.forms !== false) {
        progress(20, 'Flattening form fields…');
        try {
            const form = doc.getForm();
            flattened += form.getFields().length;
            form.flatten({ updateFieldAppearances: true });
        } catch (error) {
            console.warn('Form flattening skipped', error);
        }
    }

    if (options.annotations !== false) {
        const pages = doc.getPages();

        for (const [i, page] of pages.entries()) {
            progress(40 + (i / pages.length) * 50, `Flattening annotations on page ${i + 1}…`);

            const annots = page.node.Annots();
            if (!(annots instanceof PDFArray)) continue;

            const keep = [];

            for (const ref of annots.asArray()) {
                const annot = context.lookup(ref);
                if (!(annot instanceof PDFDict)) continue;

                const subtype = annot.lookup(N('Subtype'))?.toString();
                const flags = annot.lookup(N('F'))?.asNumber?.() ?? 0;
                const hidden = flags & 2 || flags & 32; // Hidden / NoView

                if (KEEP.has(subtype) || subtype === '/Widget') {
                    keep.push(ref);
                    continue;
                }

                let appearance = annot.lookup(N('AP'))?.lookup?.(N('N'));
                if (appearance instanceof PDFDict && !(appearance instanceof PDFRawStream)) {
                    const state = annot.lookup(N('AS'));
                    appearance = state ? appearance.lookup(state) : null;
                }

                const rect = annot.lookup(N('Rect'));

                if (!hidden && appearance instanceof PDFRawStream && rect instanceof PDFArray) {
                    const apRef = context.getObjectRef(appearance) || context.register(appearance);
                    const [x0, y0, x1, y1] = rect.asArray().map((n) => n.asNumber());
                    const bbox = appearance.dict.lookup(N('BBox'))?.asArray().map((n) => n.asNumber()) || [0, 0, x1 - x0, y1 - y0];
                    const matrix = appearance.dict.lookup(N('Matrix'))?.asArray().map((n) => n.asNumber()) || [1, 0, 0, 1, 0, 0];

                    // Transformed bounding box of the appearance (PDF 32000 §12.5.5).
                    const corners = [
                        [bbox[0], bbox[1]],
                        [bbox[2], bbox[1]],
                        [bbox[0], bbox[3]],
                        [bbox[2], bbox[3]],
                    ].map(([x, y]) => [matrix[0] * x + matrix[2] * y + matrix[4], matrix[1] * x + matrix[3] * y + matrix[5]]);
                    const tx0 = Math.min(...corners.map((c) => c[0]));
                    const ty0 = Math.min(...corners.map((c) => c[1]));
                    const tx1 = Math.max(...corners.map((c) => c[0]));
                    const ty1 = Math.max(...corners.map((c) => c[1]));

                    const sx = tx1 - tx0 ? Math.abs(x1 - x0) / (tx1 - tx0) : 1;
                    const sy = ty1 - ty0 ? Math.abs(y1 - y0) / (ty1 - ty0) : 1;
                    const left = Math.min(x0, x1);
                    const bottom = Math.min(y0, y1);

                    const name = page.node.newXObject('Flat', apRef);
                    page.pushOperators(pushGraphicsState(), concatTransformationMatrix(sx, 0, 0, sy, left - tx0 * sx, bottom - ty0 * sy), drawObject(name), popGraphicsState());
                    flattened++;
                }
                // Annotations without an appearance have nothing visible to keep.
            }

            if (keep.length) page.node.set(N('Annots'), context.obj(keep));
            else page.node.delete(N('Annots'));
        }
    }

    progress(94, 'Saving…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'flattened', 'pdf'),
        stats: { pages: doc.getPageCount(), removed: flattened || undefined },
    };
}
