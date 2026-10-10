import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { parseRanges, parsePages } from '../lib/pages';
import { outputName } from '../lib/files';
import { nextFrame } from '../lib/dom';

/**
 * Split a PDF: every page, page ranges, fixed chunks or extract pages.
 */
export async function run({ files, options, progress }) {
    const { PDFDocument } = await pdfLib();
    const file = files[0];
    const { doc } = await openDocument(file);
    const count = doc.getPageCount();
    const mode = options.mode || 'ranges';

    let groups;

    switch (mode) {
        case 'every':
            groups = Array.from({ length: count }, (_, i) => [i + 1]);
            break;
        case 'fixed': {
            const size = Math.max(1, Number(options.size) || 1);
            groups = [];
            for (let start = 1; start <= count; start += size) {
                groups.push(Array.from({ length: Math.min(size, count - start + 1) }, (_, i) => start + i));
            }
            break;
        }
        case 'extract':
            groups = [parsePages(options.pages, count)];
            break;
        default:
            groups = parseRanges(options.ranges, count).map(([a, b]) => Array.from({ length: b - a + 1 }, (_, i) => a + i));
    }

    const digits = String(count).length;
    const output = [];

    for (const [i, pages] of groups.entries()) {
        progress((i / groups.length) * 95, `Creating file ${i + 1} of ${groups.length}…`);
        await nextFrame();

        const part = await PDFDocument.create();
        const copied = await part.copyPages(doc, pages.map((p) => p - 1));
        copied.forEach((page) => part.addPage(page));

        const label =
            mode === 'extract' ? 'extracted' : pages.length === 1 ? `page-${String(pages[0]).padStart(digits, '0')}` : `pages-${pages[0]}-${pages[pages.length - 1]}`;

        output.push({ name: outputName(file.name, label, 'pdf'), blob: pdfBlob(await saveDocument(part)) });
    }

    return {
        files: output,
        filename: outputName(file.name, 'split', 'zip'),
        stats: { files: output.length, pages: count },
    };
}
