import { pdfLib } from '../lib/pdf';
import { openDocument, pdfBlob, saveDocument } from '../lib/pdf-tools';
import { nextFrame } from '../lib/dom';

/**
 * Merge PDFs in the browser (pdf-lib).
 */
export async function run({ files, progress }) {
    const { PDFDocument } = await pdfLib();
    const merged = await PDFDocument.create();

    for (const [i, file] of files.entries()) {
        progress((i / files.length) * 90, `Adding ${file.name} (${i + 1} of ${files.length})…`);
        await nextFrame();

        const { doc } = await openDocument(file);
        const pages = await merged.copyPages(doc, doc.getPageIndices());
        pages.forEach((page) => merged.addPage(page));
    }

    progress(94, 'Saving merged PDF…');
    const bytes = await saveDocument(merged);

    return {
        blob: pdfBlob(bytes),
        filename: 'merged.pdf',
        stats: { files: files.length, pages: merged.getPageCount() },
    };
}
