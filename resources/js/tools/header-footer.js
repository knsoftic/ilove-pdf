import { openDocument, saveDocument, pdfBlob, pageFrame, drawCenteredText, color, selectedIndices, fillPlaceholders } from '../lib/pdf-tools';
import { fontFor } from '../lib/fonts';
import { outputName, baseName } from '../lib/files';
import { ToolError } from '../lib/errors';

const SLOTS = [
    ['header_left', 'top', 'left'],
    ['header_center', 'top', 'center'],
    ['header_right', 'top', 'right'],
    ['footer_left', 'bottom', 'left'],
    ['footer_center', 'bottom', 'center'],
    ['footer_right', 'bottom', 'right'],
];

/**
 * Header and footer text with {page}, {total}, {date} and {filename}.
 */
export async function run({ files, options, progress }) {
    const file = files[0];
    const { doc } = await openDocument(file);
    const total = doc.getPageCount();
    const indices = selectedIndices(options.pages, total);
    const size = Math.max(5, Number(options.font_size) || 9);
    const margin = Math.max(0, Number(options.margin ?? 24));
    const fill = await color(options.color || '#374151');
    const date = new Date().toLocaleDateString();

    const slots = SLOTS.filter(([key]) => String(options[key] || '').trim() !== '');
    if (!slots.length) throw new ToolError('invalid_options', 'Please enter text for at least one header or footer position.');

    const sample = slots.map(([key]) => options[key]).join(' ') + baseName(file.name);
    const font = await fontFor(doc, sample);

    for (const [n, index] of indices.entries()) {
        if (n % 25 === 0) progress((n / indices.length) * 90, `Page ${index + 1}…`);

        const page = doc.getPage(index);
        const frame = pageFrame(page);
        const values = { page: index + 1, total, date, filename: file.name.replace(/\.pdf$/i, '') };

        for (const [key, row, col] of slots) {
            const text = fillPlaceholders(options[key], values);
            const width = font.widthOfTextAtSize(text, size);
            const height = font.heightAtSize(size, { descender: false });
            const cx = col === 'left' ? margin + width / 2 : col === 'right' ? frame.width - margin - width / 2 : frame.width / 2;
            const cy = row === 'top' ? frame.height - margin - height / 2 : margin + height / 2;

            await drawCenteredText(page, frame, text, { cx, cy, font, size, color: fill });
        }
    }

    progress(94, 'Saving…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'header-footer', 'pdf'),
        stats: { pages: indices.length },
    };
}
