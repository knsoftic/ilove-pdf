import { openPdfJs, renderPageToBlob, closePdfJs } from '../lib/pdf';
import { readBytes, outputName } from '../lib/files';
import { parsePages } from '../lib/pages';
import { ToolError } from '../lib/errors';

const TYPES = { jpg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };

/**
 * Renders PDF pages to JPG / PNG / WEBP with PDF.js.
 */
export async function run({ files, options, progress, signal }) {
    const file = files[0];
    const format = TYPES[options.format] ? options.format : 'jpg';
    const dpi = [72, 150, 300].includes(Number(options.dpi)) ? Number(options.dpi) : 150;
    const quality = Math.max(0.3, Math.min(1, (Number(options.quality) || 85) / 100));

    const doc = await openPdfJs(await readBytes(file), { fileName: file.name, file });
    const pages = parsePages(options.pages, doc.numPages);
    const digits = String(doc.numPages).length;
    const output = [];

    try {
        for (const [n, number] of pages.entries()) {
            if (signal?.aborted) throw new ToolError('cancelled');
            progress((n / pages.length) * 92, `Rendering page ${number} (${n + 1} of ${pages.length})…`);

            const page = await doc.getPage(number);
            // Keep phone memory in check: at most ~40 megapixels per page.
            const base = page.getViewport({ scale: 1 });
            const scale = Math.min(dpi / 72, Math.sqrt(40_000_000 / (base.width * base.height)));
            const blob = await renderPageToBlob(page, { scale, type: TYPES[format], quality });
            page.cleanup();

            if (format === 'webp' && blob.type !== 'image/webp') {
                throw new ToolError('unsupported_format', 'Your browser cannot create WEBP images. Please choose PNG or JPG.');
            }

            output.push({ name: outputName(file.name, `page-${String(number).padStart(digits, '0')}`, format), blob });
        }
    } finally {
        closePdfJs(doc);
    }

    return {
        files: output,
        filename: outputName(file.name, `${format}-images`, 'zip'),
        stats: { pages: output.length },
    };
}
