import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { parsePages } from '../lib/pages';
import { outputName } from '../lib/files';
import { ToolError } from '../lib/errors';

/**
 * Extract, delete, rotate or duplicate selected pages (params.action).
 */
export async function run({ files, options, params, progress }) {
    const { PDFDocument, degrees } = await pdfLib();
    const file = files[0];
    const { doc } = await openDocument(file);
    const count = doc.getPageCount();
    const action = params.action;
    const hasSelection = String(options.pages || '').trim() !== '';
    const selection = parsePages(options.pages, count);

    progress(30, 'Updating pages…');

    let result = doc;
    let suffix = action;

    if (action === 'extract') {
        result = await PDFDocument.create();
        const pages = await result.copyPages(doc, selection.map((p) => p - 1));
        pages.forEach((page) => result.addPage(page));
        suffix = 'extracted';
    } else if (action === 'delete') {
        if (selection.length >= count) {
            throw new ToolError('invalid_page_range', 'You cannot delete every page of the document.');
        }
        [...selection].sort((a, b) => b - a).forEach((p) => doc.removePage(p - 1));
        suffix = 'edited';
    } else if (action === 'rotate') {
        const angle = Number(options.angle) || 90;
        const targets = hasSelection ? selection : Array.from({ length: count }, (_, i) => i + 1);
        for (const p of targets) {
            const page = doc.getPage(p - 1);
            page.setRotation(degrees((page.getRotation().angle + angle) % 360));
        }
        suffix = 'rotated';
    } else if (action === 'duplicate') {
        const copies = Math.max(1, Math.min(50, Number(options.copies) || 1));
        const atEnd = options.placement === 'end';
        const sorted = [...selection].sort((a, b) => a - b);

        if (atEnd) {
            for (let c = 0; c < copies; c++) {
                const pages = await doc.copyPages(doc, sorted.map((p) => p - 1));
                pages.forEach((page) => doc.addPage(page));
            }
        } else {
            // Insert from the back so earlier indices stay valid.
            for (const p of [...sorted].reverse()) {
                const pages = await doc.copyPages(doc, Array(copies).fill(p - 1));
                pages.forEach((page, i) => doc.insertPage(p + i, page));
            }
        }
        suffix = 'duplicated';
    } else {
        throw new ToolError('invalid_options');
    }

    progress(85, 'Saving…');
    const bytes = await saveDocument(result);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, suffix, 'pdf'),
        stats: { pages: result.getPageCount() },
    };
}
