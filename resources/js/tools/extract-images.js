import { pdfjs, openPdfJs, closePdfJs } from '../lib/pdf';
import { readBytes, outputName } from '../lib/files';
import { makeCanvas, canvasToBytes } from '../lib/pdf-images';
import { ToolError } from '../lib/errors';

/**
 * Extracts embedded images with PDF.js, which decodes every image type found
 * in PDFs (JPEG, JPEG 2000, JBIG2, CCITT, Flate…). Images keep their original
 * pixel size; photos are saved as high quality JPEG, others as PNG.
 */
export async function run({ files, options, progress, signal }) {
    const { OPS } = await pdfjs();
    const file = files[0];
    const minSize = Math.max(0, Number(options.min_size ?? 32));
    const doc = await openPdfJs(await readBytes(file), { fileName: file.name, file });
    const seen = new Set();
    const output = [];

    try {
        for (let p = 1; p <= doc.numPages; p++) {
            if (signal?.aborted) throw new ToolError('cancelled');
            progress((p / doc.numPages) * 92, `Scanning page ${p} of ${doc.numPages}…`);

            const page = await doc.getPage(p);
            const ops = await page.getOperatorList();
            let index = 0;

            for (let i = 0; i < ops.fnArray.length; i++) {
                const fn = ops.fnArray[i];
                if (fn !== OPS.paintImageXObject && fn !== OPS.paintImageXObjectRepeat) continue;

                const id = ops.argsArray[i][0];
                const key = id.startsWith('g_') ? id : `${p}:${id}`;
                if (seen.has(key)) continue;
                seen.add(key);

                const store = id.startsWith('g_') ? page.commonObjs : page.objs;
                const image = await new Promise((resolve) => {
                    try {
                        store.get(id, resolve);
                    } catch {
                        resolve(null);
                    }
                });

                if (!image || image.width < minSize || image.height < minSize) continue;

                const blob = await encode(image);
                if (!blob) continue;

                index++;
                output.push({ name: outputName(file.name, `page-${p}-image-${index}`, blob.type === 'image/png' ? 'png' : 'jpg'), blob });
            }

            page.cleanup();
        }
    } finally {
        closePdfJs(doc);
    }

    if (!output.length) throw new ToolError('nothing_found', 'No images were found in this PDF.');

    return { files: output, filename: outputName(file.name, 'images', 'zip'), stats: { images: output.length } };
}

async function encode(image) {
    const { width, height } = image;
    const canvas = makeCanvas(width, height);
    const ctx = canvas.getContext('2d');
    let transparent = false;

    if (image.bitmap) {
        ctx.drawImage(image.bitmap, 0, 0);
    } else if (image.data) {
        const rgba = new Uint8ClampedArray(width * height * 4);
        const src = image.data;

        if (image.kind === 3) {
            rgba.set(src.subarray(0, rgba.length));
            transparent = true;
        } else if (image.kind === 2) {
            for (let s = 0, d = 0; d < rgba.length; s += 3, d += 4) {
                rgba[d] = src[s];
                rgba[d + 1] = src[s + 1];
                rgba[d + 2] = src[s + 2];
                rgba[d + 3] = 255;
            }
        } else if (image.kind === 1) {
            // 1 bit per pixel, rows padded to whole bytes.
            const rowBytes = (width + 7) >> 3;
            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    const bit = (src[y * rowBytes + (x >> 3)] >> (7 - (x & 7))) & 1;
                    const v = bit ? 255 : 0;
                    const d = (y * width + x) * 4;
                    rgba[d] = rgba[d + 1] = rgba[d + 2] = v;
                    rgba[d + 3] = 255;
                }
            }
        } else {
            return null;
        }

        ctx.putImageData(new ImageData(rgba, width, height), 0, 0);
    } else {
        return null;
    }

    if (!transparent) {
        const sample = ctx.getImageData(0, 0, Math.min(width, 64), Math.min(height, 64)).data;
        for (let i = 3; i < sample.length; i += 4) {
            if (sample[i] < 255) {
                transparent = true;
                break;
            }
        }
    }

    // Large opaque images are photos: JPEG keeps the size reasonable.
    const photo = !transparent && width * height > 600_000;
    const bytes = await canvasToBytes(canvas, photo ? 'image/jpeg' : 'image/png', 0.95);
    return new Blob([bytes], { type: photo ? 'image/jpeg' : 'image/png' });
}
