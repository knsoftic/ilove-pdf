import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { describeImage, decodeImage, drawToCanvas, canvasToBytes, stripPrivateData } from '../lib/pdf-images';
import { outputName } from '../lib/files';
import { nextFrame } from '../lib/dom';

const LEVELS = {
    low: { quality: 0.82, maxSide: 2600, minBytes: 120_000 },
    medium: { quality: 0.66, maxSide: 1800, minBytes: 30_000 },
    high: { quality: 0.48, maxSide: 1250, minBytes: 8_000 },
};

/**
 * In-browser PDF compression: images are downsampled and re-encoded as
 * JPEG, application private data is removed and the file is rewritten
 * with compressed object streams. Text and vector graphics stay intact.
 */
export async function run({ files, options, progress }) {
    const { PDFRawStream, PDFName } = await pdfLib();
    const file = files[0];
    const settings = LEVELS[options.level] || LEVELS.medium;
    const { doc } = await openDocument(file);

    const images = doc.context.enumerateIndirectObjects().filter(([, obj]) => obj instanceof PDFRawStream && obj.dict.lookup(PDFName.of('Subtype')) === PDFName.of('Image'));

    let replaced = 0;

    for (const [i, [ref, stream]] of images.entries()) {
        progress((i / Math.max(1, images.length)) * 85, `Optimizing image ${i + 1} of ${images.length}…`);
        if (i % 3 === 0) await nextFrame();

        if (stream.contents.length < settings.minBytes) continue;

        try {
            const info = await describeImage(doc, stream);
            if (!info) continue;

            const source = await decodeImage(stream, info);
            if (!source) continue;

            const scale = Math.min(1, settings.maxSide / Math.max(info.width, info.height));
            const width = Math.max(1, Math.round(info.width * scale));
            const height = Math.max(1, Math.round(info.height * scale));

            const canvas = drawToCanvas(source, width, height);
            source.close?.();
            const jpeg = await canvasToBytes(canvas, 'image/jpeg', settings.quality);

            // Only keep the new image when it is clearly smaller.
            if (jpeg.length >= stream.contents.length * 0.9) continue;

            const entries = {
                Type: 'XObject',
                Subtype: 'Image',
                Width: width,
                Height: height,
                ColorSpace: 'DeviceRGB',
                BitsPerComponent: 8,
                Filter: 'DCTDecode',
            };

            const smask = stream.dict.get(PDFName.of('SMask'));
            if (smask) entries.SMask = smask;
            const interpolate = stream.dict.get(PDFName.of('Interpolate'));
            if (interpolate) entries.Interpolate = interpolate;

            doc.context.assign(ref, doc.context.stream(jpeg, entries));
            replaced++;
        } catch (error) {
            console.warn('Image skipped', error);
        }
    }

    progress(90, 'Removing unnecessary data…');
    await stripPrivateData(doc, { metadata: options.level === 'high' });

    progress(94, 'Saving compressed PDF…');
    let bytes = await saveDocument(doc);
    const original = file.size;

    // Never return a bigger file.
    let note = null;
    if (bytes.length >= original) {
        bytes = new Uint8Array(await file.arrayBuffer());
        note = 'This PDF is already well optimized.';
    }

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'compressed', 'pdf'),
        title: note || undefined,
        stats: {
            original_size: original,
            new_size: bytes.length,
            saved_percent: Math.max(0, Math.round((1 - bytes.length / original) * 1000) / 10),
            images: replaced || undefined,
        },
    };
}
