import { openDocument, saveDocument, pdfBlob, pageFrame, anchor, drawCenteredText, color } from '../lib/pdf-tools';
import { fontFor } from '../lib/fonts';
import { outputName } from '../lib/files';

/**
 * Adds page numbers like "Page 3 of 12" at the chosen position.
 */
export async function run({ files, options, progress }) {
    const file = files[0];
    const { doc } = await openDocument(file);
    const pages = doc.getPages();
    const format = options.format || '{n}';
    const start = Number.isFinite(Number(options.start)) ? Number(options.start) : 1;
    const size = Math.max(5, Number(options.font_size) || 11);
    const margin = Math.max(0, Number(options.margin ?? 24));
    const skipFirst = Boolean(options.skip_first);
    const fill = await color(options.color || '#111827');

    const numbered = skipFirst ? pages.length - 1 : pages.length;
    const font = await fontFor(doc, format.replace(/\{\w+\}/g, '0123456789'));

    for (const [i, page] of pages.entries()) {
        if (skipFirst && i === 0) continue;
        if (i % 25 === 0) progress((i / pages.length) * 90, `Numbering page ${i + 1}…`);

        const number = start + (skipFirst ? i - 1 : i);
        const label = format.replace('{n}', String(number)).replace('{total}', String(numbered + start - 1));
        const frame = pageFrame(page);
        const width = font.widthOfTextAtSize(label, size);
        const height = font.heightAtSize(size, { descender: false });
        const { cx, cy } = anchor(options.position || 'bottom-center', frame, width, height, margin);

        await drawCenteredText(page, frame, label, { cx, cy, font, size, color: fill });
    }

    progress(94, 'Saving…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'numbered', 'pdf'),
        stats: { pages: numbered },
    };
}
