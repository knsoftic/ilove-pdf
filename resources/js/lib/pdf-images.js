import { unzlibSync, inflateSync } from 'fflate';
import { pdfLib } from './pdf';

/**
 * Low-level access to image XObjects inside a pdf-lib document:
 * decoding (JPEG via the browser, Flate RGB/Gray via fflate) and
 * re-encoding through a canvas.
 */

export function makeCanvas(width, height) {
    if (typeof OffscreenCanvas !== 'undefined') return new OffscreenCanvas(width, height);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    return canvas;
}

export async function canvasToBytes(canvas, type, quality) {
    const blob = canvas.convertToBlob
        ? await canvas.convertToBlob({ type, quality })
        : await new Promise((resolve) => canvas.toBlob(resolve, type, quality));
    return new Uint8Array(await blob.arrayBuffer());
}

/**
 * Describes an image stream, or returns null when it is not something we
 * can safely decode (masks, exotic colour spaces, 16-bit, …).
 */
export async function describeImage(doc, stream) {
    const { PDFName, PDFArray, PDFRawStream, PDFDict } = await pdfLib();
    const dict = stream.dict;
    const get = (key) => dict.lookup(PDFName.of(key));

    if (get('Subtype') !== PDFName.of('Image')) return null;
    if (get('ImageMask')?.asBoolean?.() || get('ImageMask')?.toString() === 'true') return null;
    if (get('Decode')) return null;

    let filter = get('Filter');
    if (filter instanceof PDFArray) {
        if (filter.size() !== 1) return null;
        filter = filter.lookup(0);
    }
    const filterName = filter ? filter.toString() : null;

    const width = get('Width')?.asNumber?.();
    const height = get('Height')?.asNumber?.();
    const bpc = get('BitsPerComponent')?.asNumber?.() ?? 8;
    if (!width || !height) return null;

    let colorSpace = get('ColorSpace');
    let components = null;

    if (colorSpace instanceof PDFName) {
        components = { '/DeviceRGB': 3, '/DeviceGray': 1, '/DeviceCMYK': 4, '/CalRGB': 3, '/CalGray': 1 }[colorSpace.toString()] ?? null;
    } else if (colorSpace instanceof PDFArray) {
        const kind = colorSpace.lookup(0)?.toString();
        if (kind === '/ICCBased') {
            const icc = colorSpace.lookup(1);
            const n = icc instanceof PDFRawStream || icc?.dict instanceof PDFDict ? icc.dict.lookup(PDFName.of('N'))?.asNumber?.() : null;
            components = n || null;
        } else if (kind === '/CalRGB') components = 3;
        else if (kind === '/CalGray') components = 1;
    } else if (!colorSpace && filterName === '/DCTDecode') {
        components = 3;
    }

    if (!components || components === 4) return null; // CMYK conversion in the browser is not colour-safe

    const parms = get('DecodeParms');
    const predictor = parms instanceof PDFDict ? parms.lookup(PDFName.of('Predictor'))?.asNumber?.() ?? 1 : 1;

    return { width, height, bpc, components, filter: filterName, predictor, hasSMask: Boolean(dict.get(PDFName.of('SMask'))) };
}

/**
 * Decodes an image stream into something drawable on a canvas.
 */
export async function decodeImage(stream, info) {
    if (info.filter === '/DCTDecode') {
        return createImageBitmap(new Blob([stream.contents], { type: 'image/jpeg' }));
    }

    if (info.filter === '/FlateDecode' && info.bpc === 8 && (info.components === 3 || info.components === 1)) {
        let raw;
        try {
            raw = unzlibSync(stream.contents);
        } catch {
            raw = inflateSync(stream.contents);
        }

        if (info.predictor >= 10) raw = unpredictPng(raw, info.components, info.width);

        const { width, height, components } = info;
        if (raw.length < width * height * components) return null;

        const rgba = new Uint8ClampedArray(width * height * 4);
        for (let p = 0, s = 0; p < width * height; p++, s += components) {
            const o = p * 4;
            if (components === 3) {
                rgba[o] = raw[s];
                rgba[o + 1] = raw[s + 1];
                rgba[o + 2] = raw[s + 2];
            } else {
                rgba[o] = rgba[o + 1] = rgba[o + 2] = raw[s];
            }
            rgba[o + 3] = 255;
        }

        return new ImageData(rgba, width, height);
    }

    return null;
}

/** Reverses PNG row filters (PDF predictors 10–15). */
export function unpredictPng(data, colors, columns) {
    const bpp = colors;
    const rowLength = columns * colors;
    const rows = Math.floor(data.length / (rowLength + 1));
    const out = new Uint8Array(rows * rowLength);
    let prev = new Uint8Array(rowLength);

    for (let r = 0; r < rows; r++) {
        const filter = data[r * (rowLength + 1)];
        const line = data.subarray(r * (rowLength + 1) + 1, (r + 1) * (rowLength + 1));
        const cur = out.subarray(r * rowLength, (r + 1) * rowLength);

        for (let i = 0; i < rowLength; i++) {
            const a = i >= bpp ? cur[i - bpp] : 0;
            const b = prev[i];
            const c = i >= bpp ? prev[i - bpp] : 0;
            let v = line[i];

            if (filter === 1) v += a;
            else if (filter === 2) v += b;
            else if (filter === 3) v += (a + b) >> 1;
            else if (filter === 4) {
                const p = a + b - c;
                const pa = Math.abs(p - a);
                const pb = Math.abs(p - b);
                const pc = Math.abs(p - c);
                v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
            }

            cur[i] = v & 0xff;
        }
        prev = cur;
    }

    return out;
}

/**
 * Draws decoded image data onto a canvas of the given size.
 */
export function drawToCanvas(source, width, height, { grayscale = false } = {}) {
    const canvas = makeCanvas(width, height);
    const ctx = canvas.getContext('2d');

    if (source instanceof ImageData) {
        if (source.width === width && source.height === height) {
            ctx.putImageData(source, 0, 0);
        } else {
            const tmp = makeCanvas(source.width, source.height);
            tmp.getContext('2d').putImageData(source, 0, 0);
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(tmp, 0, 0, width, height);
        }
    } else {
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(source, 0, 0, width, height);
    }

    if (grayscale) {
        const image = ctx.getImageData(0, 0, width, height);
        const d = image.data;
        for (let i = 0; i < d.length; i += 4) {
            const y = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            d[i] = d[i + 1] = d[i + 2] = y;
        }
        ctx.putImageData(image, 0, 0);
    }

    return canvas;
}

/**
 * Decoded bytes of any stream object: raw streams read from the file and
 * content streams created by pdf-lib (e.g. the q/Q wrappers it adds when a
 * page is normalised). Returns null when the filter is unsupported.
 */
export function streamBytes(stream, decodePDFRawStream) {
    try {
        if (typeof stream?.getUnencodedContents === 'function') return stream.getUnencodedContents();
        return decodePDFRawStream(stream).decode();
    } catch {
        return null;
    }
}

/**
 * Removes data that only matters to the authoring application and
 * page thumbnails – often megabytes in Illustrator/InDesign exports.
 */
export async function stripPrivateData(doc, { metadata = false } = {}) {
    const { PDFName } = await pdfLib();

    for (const page of doc.getPages()) {
        page.node.delete(PDFName.of('Thumb'));
        page.node.delete(PDFName.of('PieceInfo'));
        if (metadata) page.node.delete(PDFName.of('Metadata'));
    }

    doc.catalog.delete(PDFName.of('PieceInfo'));

    if (metadata) {
        doc.catalog.delete(PDFName.of('Metadata'));
    }
}
