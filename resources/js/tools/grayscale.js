import { zlibSync, unzlibSync, inflateSync } from 'fflate';
import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { parseContent, serializeContent } from '../lib/content-stream';
import { decodeImage, drawToCanvas, canvasToBytes, unpredictPng, streamBytes } from '../lib/pdf-images';
import { outputName } from '../lib/files';
import { nextFrame } from '../lib/dom';

const fmt = (v) => String(Math.round(Math.max(0, Math.min(1, v)) * 10000) / 10000);
const rgbToGray = (r, g, b) => 0.299 * r + 0.587 * g + 0.114 * b;
const cmykToGray = (c, m, y, k) => 1 - Math.min(1, 0.3 * c + 0.59 * m + 0.11 * y + k);

/**
 * Converts a PDF to grayscale in the browser while keeping text and vector
 * graphics as they are: colour operators in every content stream are
 * rewritten, colour images are converted and indexed palettes are greyed.
 */
export async function run({ files, progress }) {
    const lib = await pdfLib();
    const { PDFName, PDFDict, PDFArray, PDFRawStream, PDFRef, PDFNumber, PDFHexString, PDFString, decodePDFRawStream } = lib;
    const file = files[0];
    const { doc } = await openDocument(file);
    const context = doc.context;
    const done = new Set();
    let images = 0;

    const N = (name) => PDFName.of(name);
    const tag = (ref) => (ref instanceof PDFRef ? ref.toString() : null);

    const decodeStream = (stream) => streamBytes(stream, decodePDFRawStream);

    // ---- Colour spaces --------------------------------------------------

    const greyPalette = (csArray) => {
        // [/Indexed base hival lookup]
        const base = csArray.lookup(1);
        const baseKind = kindOf(base, null);
        if (baseKind !== 'rgb') return;

        const lookup = csArray.lookup(3);
        let bytes = null;

        if (lookup instanceof PDFHexString || lookup instanceof PDFString) bytes = lookup.asBytes();
        else if (lookup instanceof PDFRawStream) bytes = decodeStream(lookup);
        if (!bytes) return;

        const grey = new Uint8Array(bytes.length);
        for (let i = 0; i + 2 < bytes.length; i += 3) {
            const y = Math.round(rgbToGray(bytes[i], bytes[i + 1], bytes[i + 2]));
            grey[i] = grey[i + 1] = grey[i + 2] = y;
        }

        csArray.set(3, PDFHexString.of([...grey].map((b) => b.toString(16).padStart(2, '0')).join('')));
    };

    function kindOf(cs, resources) {
        if (!cs) return 'other';

        if (cs instanceof PDFName) {
            const name = cs.toString();
            if (name === '/DeviceRGB' || name === '/RGB') return 'rgb';
            if (name === '/DeviceCMYK' || name === '/CMYK') return 'cmyk';
            if (name === '/DeviceGray' || name === '/G') return 'gray';
            if (name === '/Pattern') return 'other';

            const named = resources?.lookup(N('ColorSpace'))?.lookup?.(cs);
            return named ? kindOf(named, null) : 'other';
        }

        if (cs instanceof PDFArray) {
            const family = cs.lookup(0)?.toString();
            if (family === '/ICCBased') {
                const n = cs.lookup(1)?.dict?.lookup(N('N'))?.asNumber?.();
                return n === 3 ? 'rgb' : n === 4 ? 'cmyk' : n === 1 ? 'gray' : 'other';
            }
            if (family === '/CalRGB') return 'rgb';
            if (family === '/CalGray') return 'gray';
            if (family === '/Indexed' || family === '/I') {
                if (!done.has(cs)) {
                    done.add(cs);
                    greyPalette(cs);
                }
                return 'other';
            }
        }

        return 'other';
    }

    // ---- Content streams -----------------------------------------------

    function convertOps(bytes, resources) {
        const ops = parseContent(bytes);
        let fill = 'gray';
        let stroke = 'gray';
        const stack = [];

        for (const o of ops) {
            const nums = o.operands.map(Number);

            switch (o.op) {
                case 'q':
                    stack.push([fill, stroke]);
                    break;
                case 'Q':
                    [fill, stroke] = stack.pop() || ['gray', 'gray'];
                    break;
                case 'rg':
                case 'RG':
                    if (nums.length === 3) {
                        o.operands = [fmt(rgbToGray(...nums))];
                        o.op = o.op === 'rg' ? 'g' : 'G';
                    }
                    break;
                case 'k':
                case 'K':
                    if (nums.length === 4) {
                        o.operands = [fmt(cmykToGray(...nums))];
                        o.op = o.op === 'k' ? 'g' : 'G';
                    }
                    break;
                case 'cs':
                case 'CS': {
                    const name = PDFName.of(String(o.operands[0] || '').replace(/^\//, ''));
                    const kind = kindOf(name, resources);
                    if (kind === 'rgb' || kind === 'cmyk' || kind === 'gray') o.operands = ['/DeviceGray'];
                    if (o.op === 'cs') fill = kind;
                    else stroke = kind;
                    break;
                }
                case 'sc':
                case 'scn':
                case 'SC':
                case 'SCN': {
                    const kind = o.op.toLowerCase() === o.op ? fill : stroke;
                    if (nums.some(Number.isNaN)) break;
                    if (kind === 'rgb' && nums.length === 3) o.operands = [fmt(rgbToGray(...nums))];
                    else if (kind === 'cmyk' && nums.length === 4) o.operands = [fmt(cmykToGray(...nums))];
                    break;
                }
                default:
            }
        }

        return serializeContent(ops);
    }

    async function convertResources(resources) {
        if (!(resources instanceof PDFDict)) return;

        const xobjects = resources.lookup(N('XObject'));
        if (xobjects instanceof PDFDict) {
            for (const [, ref] of xobjects.entries()) {
                const key = tag(ref);
                if (key && done.has(key)) continue;
                if (key) done.add(key);

                const object = context.lookup(ref);
                if (!(object instanceof PDFRawStream)) continue;

                const subtype = object.dict.lookup(N('Subtype'))?.toString();

                if (subtype === '/Image') await convertImage(ref, object, resources);
                else if (subtype === '/Form') await convertForm(ref, object, resources);
            }
        }

        // Make sure palettes of colour spaces used by operators get converted.
        const spaces = resources.lookup(N('ColorSpace'));
        if (spaces instanceof PDFDict) {
            for (const [, cs] of spaces.entries()) kindOf(context.lookup(cs), null);
        }
    }

    async function convertForm(ref, stream, parentResources) {
        const bytes = decodeStream(stream);
        if (!bytes) return;

        const resources = stream.dict.lookup(N('Resources')) || parentResources;
        const converted = convertOps(bytes, resources);
        const dict = stream.dict.clone(context);
        dict.delete(N('Filter'));
        dict.delete(N('DecodeParms'));
        dict.set(N('Filter'), N('FlateDecode'));
        context.assign(ref, PDFRawStream.of(dict, zlibSync(converted)));

        await convertResources(resources);
    }

    async function convertImage(ref, stream) {
        const dict = stream.dict;
        const cs = dict.lookup(N('ColorSpace'));
        const kind = kindOf(cs, null);
        const bpc = dict.lookup(N('BitsPerComponent'))?.asNumber?.() ?? 8;
        let filter = dict.lookup(N('Filter'));
        if (filter instanceof PDFArray) filter = filter.size() === 1 ? filter.lookup(0) : null;
        const filterName = filter?.toString();
        const width = dict.lookup(N('Width'))?.asNumber?.();
        const height = dict.lookup(N('Height'))?.asNumber?.();

        if (kind === 'gray' || kind === 'other' || !width || !height || dict.lookup(N('ImageMask'))) return;

        // Lossless path: 8-bit Flate RGB -> 8-bit Flate gray.
        if (filterName === '/FlateDecode' && bpc === 8 && kind === 'rgb' && !dict.lookup(N('Decode'))) {
            let raw;
            try {
                raw = unzlibSync(stream.contents);
            } catch {
                raw = inflateSync(stream.contents);
            }

            const parms = dict.lookup(N('DecodeParms'));
            const predictor = parms instanceof PDFDict ? parms.lookup(N('Predictor'))?.asNumber?.() ?? 1 : 1;
            if (predictor >= 10) raw = unpredictPng(raw, 3, width);
            if (raw.length < width * height * 3) return;

            const grey = new Uint8Array(width * height);
            for (let p = 0, s = 0; p < grey.length; p++, s += 3) grey[p] = rgbToGray(raw[s], raw[s + 1], raw[s + 2]);

            const newDict = dict.clone(context);
            newDict.set(N('ColorSpace'), N('DeviceGray'));
            newDict.set(N('Filter'), N('FlateDecode'));
            newDict.delete(N('DecodeParms'));
            context.assign(ref, PDFRawStream.of(newDict, zlibSync(grey)));
            images++;
            return;
        }

        // JPEG (RGB or CMYK): decode in the browser, re-encode greyed.
        if (filterName === '/DCTDecode' && !dict.lookup(N('Decode'))) {
            const source = await decodeImage(stream, { filter: '/DCTDecode' });
            if (!source) return;
            const canvas = drawToCanvas(source, width, height, { grayscale: true });
            source.close?.();
            const jpeg = await canvasToBytes(canvas, 'image/jpeg', 0.9);

            const newDict = dict.clone(context);
            newDict.set(N('ColorSpace'), N('DeviceRGB'));
            newDict.set(N('BitsPerComponent'), PDFNumber.of(8));
            newDict.set(N('Width'), PDFNumber.of(width));
            newDict.set(N('Height'), PDFNumber.of(height));
            newDict.delete(N('DecodeParms'));
            context.assign(ref, PDFRawStream.of(newDict, jpeg));
            images++;
        }
    }

    // ---- Pages & annotations --------------------------------------------

    const pages = doc.getPages();

    for (const [i, page] of pages.entries()) {
        progress((i / pages.length) * 90, `Converting page ${i + 1} of ${pages.length}…`);
        await nextFrame();

        const { Resources, Contents } = page.node.normalizedEntries();
        const streams = Contents instanceof PDFArray ? Contents.asArray().map((r) => context.lookup(r)) : Contents ? [context.lookup(Contents)] : [];
        const parts = streams.filter(Boolean).map(decodeStream).filter(Boolean);

        if (parts.length === streams.length && parts.length) {
            const joined = new Uint8Array(parts.reduce((s, p) => s + p.length + 1, 0));
            let offset = 0;
            for (const part of parts) {
                joined.set(part, offset);
                joined[offset + part.length] = 0x0a;
                offset += part.length + 1;
            }

            const converted = convertOps(joined, Resources);
            page.node.set(N('Contents'), context.register(context.flateStream(converted)));
        }

        await convertResources(Resources);

        const annots = page.node.Annots();
        if (annots instanceof PDFArray) {
            for (const annotRef of annots.asArray()) {
                const annot = context.lookup(annotRef);
                if (!(annot instanceof PDFDict)) continue;

                const c = annot.lookup(N('C'));
                if (c instanceof PDFArray && c.size() === 3) {
                    const [r, g, b] = c.asArray().map((n) => n.asNumber?.() ?? 0);
                    annot.set(N('C'), context.obj([rgbToGray(r, g, b)]));
                }

                const ap = annot.lookup(N('AP'));
                if (!(ap instanceof PDFDict)) continue;

                for (const [, value] of ap.entries()) {
                    const targets = context.lookup(value) instanceof PDFDict && !(context.lookup(value) instanceof PDFRawStream) ? context.lookup(value).values() : [value];
                    for (const target of targets) {
                        const key = tag(target);
                        const stream = context.lookup(target);
                        if (!(stream instanceof PDFRawStream) || !key || done.has(key)) continue;
                        done.add(key);
                        await convertForm(target, stream, Resources);
                    }
                }
            }
        }
    }

    progress(94, 'Saving…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'grayscale', 'pdf'),
        stats: { pages: pages.length, images: images || undefined },
    };
}
