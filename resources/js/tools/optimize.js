import { zlibSync } from 'fflate';
import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { stripPrivateData } from '../lib/pdf-images';
import { outputName } from '../lib/files';

/**
 * Lossless optimisation: compresses uncompressed streams, removes thumbnails,
 * private application data and (optionally) metadata, and writes compact
 * object streams.
 */
export async function run({ files, options, progress }) {
    const { PDFRawStream, PDFName } = await pdfLib();
    const file = files[0];
    const { doc } = await openDocument(file);

    progress(30, 'Compressing streams…');
    let compressed = 0;

    for (const [ref, obj] of doc.context.enumerateIndirectObjects()) {
        if (!(obj instanceof PDFRawStream) || obj.dict.get(PDFName.of('Filter')) || obj.contents.length < 256) continue;

        // FlateDecode expects zlib-wrapped data.
        const zlib = zlibSync(obj.contents, { level: 9 });

        if (zlib.length < obj.contents.length) {
            const dict = obj.dict.clone(doc.context);
            dict.set(PDFName.of('Filter'), PDFName.of('FlateDecode'));
            dict.delete(PDFName.of('Length'));
            doc.context.assign(ref, PDFRawStream.of(dict, zlib));
            compressed++;
        }
    }

    progress(60, 'Removing unnecessary data…');
    await stripPrivateData(doc, { metadata: Boolean(options.remove_metadata) });

    if (options.remove_metadata) {
        const info = doc.getInfoDict();
        for (const key of info.keys()) info.delete(key);
    }

    progress(85, 'Writing optimized PDF…');
    let bytes = await saveDocument(doc, { objectsPerTick: 200 });

    if (bytes.length >= file.size) {
        bytes = new Uint8Array(await file.arrayBuffer());
    }

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'optimized', 'pdf'),
        stats: {
            original_size: file.size,
            new_size: bytes.length,
            saved_percent: Math.max(0, Math.round((1 - bytes.length / file.size) * 1000) / 10),
        },
    };
}
