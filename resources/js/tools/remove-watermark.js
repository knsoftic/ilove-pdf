import { zlibSync } from 'fflate';
import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { parseContent, serializeContent, textOf } from '../lib/content-stream';
import { streamBytes } from '../lib/pdf-images';
import { outputName } from '../lib/files';
import { ToolError } from '../lib/errors';

/**
 * Removes watermarks "where technically possible":
 *  - watermark / stamp annotations
 *  - marked content tagged as /Artifact /Watermark (Acrobat, Word, …)
 *  - optional content groups (layers) named like "watermark"
 *  - Acrobat watermark form XObjects (PieceInfo ADBE_CompoundType)
 *  - text drawing operations that contain a given text
 */
export async function run({ files, options, progress }) {
    const { PDFName, PDFDict, PDFArray, PDFRawStream, PDFRef, decodePDFRawStream } = await pdfLib();
    const file = files[0];
    const { doc } = await openDocument(file);
    const context = doc.context;
    const N = (n) => PDFName.of(n);
    const needle = String(options.text || '').trim();
    let removed = 0;

    // Layers whose name suggests a watermark.
    const watermarkLayers = new Set();
    const ocProperties = doc.catalog.lookup(N('OCProperties'));
    if (options.layers !== false && ocProperties instanceof PDFDict) {
        const groups = ocProperties.lookup(N('OCGs'));
        if (groups instanceof PDFArray) {
            for (const ref of groups.asArray()) {
                const group = context.lookup(ref);
                const name = group?.lookup?.(N('Name'))?.decodeText?.() || '';
                if (/water\s*mark|draft|confidential|sample|stamp/i.test(name)) watermarkLayers.add(ref.toString());
            }
        }
    }

    const isWatermarkForm = (stream) => {
        const piece = stream?.dict?.lookup(N('PieceInfo'));
        const compound = piece?.lookup?.(N('ADBE_CompoundType'))?.lookup?.(N('Private'));
        return compound?.toString?.() === '/Watermark';
    };

    const decode = (stream) => streamBytes(stream, decodePDFRawStream);

    /**
     * Filters one content stream. Returns new bytes, or null when unchanged.
     */
    const filterContent = (bytes, resources) => {
        const ops = parseContent(bytes);
        const out = [];
        let skipDepth = 0;
        const stack = [];
        const properties = resources?.lookup?.(N('Properties'));
        const xobjects = resources?.lookup?.(N('XObject'));
        let changed = false;

        for (const o of ops) {
            if (o.op === 'BDC' || o.op === 'BMC') {
                const tagName = o.operands[0] || '';
                const props = o.operands[1] || '';
                let drop = false;

                if (options.artifacts !== false && tagName === '/Artifact' && /\/Subtype\s*\/Watermark/.test(props)) drop = true;

                if (tagName === '/OC' && watermarkLayers.size) {
                    const ref = properties?.get?.(N(props.replace(/^\//, '')));
                    if (ref instanceof PDFRef && watermarkLayers.has(ref.toString())) drop = true;
                }

                stack.push(drop || skipDepth > 0);
                if (drop || skipDepth > 0) {
                    skipDepth++;
                    changed = true;
                    continue;
                }
                out.push(o);
                continue;
            }

            if (o.op === 'EMC') {
                const skipped = stack.pop();
                if (skipped) {
                    skipDepth--;
                    continue;
                }
                out.push(o);
                continue;
            }

            if (skipDepth > 0) continue;

            if (o.op === 'Do' && xobjects instanceof PDFDict) {
                const object = xobjects.lookup(N(String(o.operands[0] || '').replace(/^\//, '')));
                if (options.artifacts !== false && object instanceof PDFRawStream && isWatermarkForm(object)) {
                    changed = true;
                    removed++;
                    continue;
                }
            }

            if (needle && ['Tj', 'TJ', "'", '"'].includes(o.op) && textOf(o).includes(needle)) {
                changed = true;
                removed++;
                continue;
            }

            out.push(o);
        }

        return changed ? serializeContent(out) : null;
    };

    const pages = doc.getPages();
    const visitedForms = new Set();

    const cleanForms = (resources) => {
        const xobjects = resources?.lookup?.(N('XObject'));
        if (!(xobjects instanceof PDFDict)) return;

        for (const [, ref] of xobjects.entries()) {
            const key = ref.toString();
            if (visitedForms.has(key)) continue;
            visitedForms.add(key);

            const stream = context.lookup(ref);
            if (!(stream instanceof PDFRawStream) || stream.dict.lookup(N('Subtype'))?.toString() !== '/Form') continue;

            const bytes = decode(stream);
            if (!bytes) continue;
            const formResources = stream.dict.lookup(N('Resources')) || resources;
            const result = filterContent(bytes, formResources);

            if (result) {
                const dict = stream.dict.clone(context);
                dict.set(N('Filter'), N('FlateDecode'));
                dict.delete(N('DecodeParms'));
                context.assign(ref, PDFRawStream.of(dict, zlibSync(result)));
                removed++;
            }

            cleanForms(formResources);
        }
    };

    for (const [i, page] of pages.entries()) {
        progress((i / pages.length) * 90, `Checking page ${i + 1} of ${pages.length}…`);

        // 1. Annotations
        if (options.annotations !== false) {
            const annots = page.node.Annots();
            if (annots instanceof PDFArray) {
                const keep = annots.asArray().filter((ref) => {
                    const subtype = context.lookup(ref)?.lookup?.(N('Subtype'))?.toString();
                    const drop = subtype === '/Watermark' || subtype === '/Stamp';
                    if (drop) removed++;
                    return !drop;
                });
                if (keep.length !== annots.size()) page.node.set(N('Annots'), context.obj(keep));
            }
        }

        // 2. Page content
        const { Resources, Contents } = page.node.normalizedEntries();
        const streams = Contents instanceof PDFArray ? Contents.asArray().map((r) => context.lookup(r)) : Contents ? [context.lookup(Contents)] : [];
        const parts = streams.filter(Boolean).map(decode);

        if (parts.length && parts.every(Boolean)) {
            const joined = new Uint8Array(parts.reduce((s, p) => s + p.length + 1, 0));
            let offset = 0;
            for (const part of parts) {
                joined.set(part, offset);
                joined[offset + part.length] = 0x0a;
                offset += part.length + 1;
            }

            const result = filterContent(joined, Resources);
            if (result) {
                page.node.set(N('Contents'), context.register(context.flateStream(result)));
                removed++;
            }
        }

        // 3. Form XObjects drawn by the page
        cleanForms(Resources);
    }

    if (!removed) {
        throw new ToolError(
            'nothing_found',
            'No removable watermark was found. The watermark may be part of the page image or drawn as ordinary content – try entering its exact text in "Also remove text matching".',
        );
    }

    progress(94, 'Saving…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'no-watermark', 'pdf'),
        stats: { pages: pages.length, removed },
    };
}
