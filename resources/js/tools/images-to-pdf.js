import { pdfLib } from '../lib/pdf';
import { readBytes, outputName, loadImage, canvasToBlob } from '../lib/files';
import { pdfBlob, saveDocument } from '../lib/pdf-tools';
import { nextFrame } from '../lib/dom';

const SIZES = { a4: [595.28, 841.89], letter: [612, 792] };
const MARGINS = { none: 0, small: 20, large: 50 };

/** EXIF orientation of a JPEG (1 when unknown). */
function jpegOrientation(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let offset = 2;

    while (offset < view.byteLength - 4) {
        const marker = view.getUint16(offset);
        const length = view.getUint16(offset + 2);

        if (marker === 0xffe1 && view.getUint32(offset + 4) === 0x45786966) {
            const tiff = offset + 10;
            const little = view.getUint16(tiff) === 0x4949;
            const entries = view.getUint16(tiff + view.getUint32(tiff + 4, little), little);
            const first = tiff + view.getUint32(tiff + 4, little) + 2;

            for (let i = 0; i < entries; i++) {
                const entry = first + i * 12;
                if (entry + 10 > view.byteLength) break;
                if (view.getUint16(entry, little) === 0x0112) return view.getUint16(entry + 8, little);
            }
            return 1;
        }

        if ((marker & 0xff00) !== 0xff00) break;
        offset += 2 + length;
    }

    return 1;
}

/**
 * Prepares an image for embedding. JPEGs without rotation are embedded
 * byte-for-byte (no quality loss); everything else goes through a canvas.
 */
async function prepare(doc, file) {
    const bytes = await readBytes(file);
    const isJpeg = bytes[0] === 0xff && bytes[1] === 0xd8;
    const isPng = bytes[0] === 0x89 && bytes[1] === 0x50;

    if (isJpeg && jpegOrientation(bytes) === 1) {
        try {
            const image = await doc.embedJpg(bytes);
            return { image, width: image.width, height: image.height };
        } catch {
            /* CMYK or unusual JPEG – fall back to canvas */
        }
    }

    if (isPng) {
        try {
            const image = await doc.embedPng(bytes);
            return { image, width: image.width, height: image.height };
        } catch {
            /* fall back to canvas */
        }
    }

    // Rotated JPEG, WEBP or unusual files: decode (EXIF applied) and re-encode.
    const bitmap = await loadImage(file);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0);
    bitmap.close?.();

    // Keep transparency as PNG, otherwise use a high quality JPEG.
    const pixels = ctx.getImageData(0, 0, Math.min(canvas.width, 64), Math.min(canvas.height, 64)).data;
    let transparent = false;
    for (let i = 3; i < pixels.length; i += 4) {
        if (pixels[i] < 255) {
            transparent = true;
            break;
        }
    }

    const blob = await canvasToBlob(canvas, transparent ? 'image/png' : 'image/jpeg', 0.92);
    const data = new Uint8Array(await blob.arrayBuffer());
    const image = transparent ? await doc.embedPng(data) : await doc.embedJpg(data);
    canvas.width = canvas.height = 0;

    return { image, width: image.width, height: image.height };
}

function addImagePage(doc, { image, width, height }, options) {
    const margin = options.page_size === 'fit' ? 0 : MARGINS[options.margin] ?? 20;
    // 96 DPI: one pixel = 0.75 pt
    const naturalW = width * 0.75;
    const naturalH = height * 0.75;

    let pageW;
    let pageH;

    if (options.page_size === 'fit' || !SIZES[options.page_size]) {
        [pageW, pageH] = [naturalW, naturalH];
    } else {
        [pageW, pageH] = SIZES[options.page_size];
        const orientation = options.orientation === 'auto' || !options.orientation ? (width > height ? 'landscape' : 'portrait') : options.orientation;
        if (orientation === 'landscape') [pageW, pageH] = [pageH, pageW];
    }

    const scale = options.page_size === 'fit' ? 1 : Math.min((pageW - 2 * margin) / naturalW, (pageH - 2 * margin) / naturalH);
    const drawW = naturalW * scale;
    const drawH = naturalH * scale;

    const page = doc.addPage([pageW, pageH]);
    page.drawImage(image, { x: (pageW - drawW) / 2, y: (pageH - drawH) / 2, width: drawW, height: drawH });
}

/**
 * JPG / PNG / WEBP -> PDF.
 */
export async function run({ files, options, progress }) {
    const { PDFDocument } = await pdfLib();
    const merge = options.merge !== false;

    if (merge) {
        const doc = await PDFDocument.create();
        for (const [i, file] of files.entries()) {
            progress((i / files.length) * 90, `Adding image ${i + 1} of ${files.length}…`);
            await nextFrame();
            addImagePage(doc, await prepare(doc, file), options);
        }

        progress(94, 'Saving PDF…');
        return {
            blob: pdfBlob(await saveDocument(doc)),
            filename: files.length === 1 ? outputName(files[0].name, '', 'pdf') : outputName(files[0].name, 'and-more', 'pdf'),
            stats: { pages: files.length },
        };
    }

    const output = [];
    for (const [i, file] of files.entries()) {
        progress((i / files.length) * 90, `Converting image ${i + 1} of ${files.length}…`);
        const doc = await PDFDocument.create();
        addImagePage(doc, await prepare(doc, file), options);
        output.push({ name: outputName(file.name, '', 'pdf'), blob: pdfBlob(await saveDocument(doc)) });
    }

    return { files: output, filename: 'images-to-pdf.zip' };
}
