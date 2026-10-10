import { openPdfJs, pdfLib, loadPdf, closePdfJs } from '../lib/pdf';
import { readBytes, outputName } from '../lib/files';
import { pageLines, findInLines, fontStyle } from '../lib/text-layout';
import { fontFor } from '../lib/fonts';
import { ToolError } from '../lib/errors';
import { pdfBlob, saveDocument } from '../lib/pdf-tools';

/**
 * Replaces text "where technically possible": every match is covered with a
 * white box and the replacement is written on top in a matching size.
 */
export async function run({ files, options, progress, signal }) {
    const file = files[0];
    const find = String(options.find || '').trim();
    const replacement = String(options.replace ?? '');
    if (!find) throw new ToolError('invalid_options', 'Please enter the text to find.');

    const bytes = await readBytes(file);
    const viewer = await openPdfJs(bytes, { fileName: file.name, file });
    const matches = [];

    try {
        for (let i = 1; i <= viewer.numPages; i++) {
            if (signal?.aborted) throw new ToolError('cancelled');
            const page = await viewer.getPage(i);
            const found = findInLines(await pageLines(page), find, { caseSensitive: options.case_sensitive, wholeWord: options.whole_word });

            // Fonts reach the main thread with the operator list; needed to
            // match the original style (bold / italic / serif …).
            if (found.length) await page.getOperatorList();

            for (const match of found) {
                const style = await fontStyle(page, match.items[0].fontName, match.items[0].family);
                matches.push({ page: i - 1, ...match, style });
            }
            page.cleanup();
            progress((i / viewer.numPages) * 60, `Searching page ${i}…`);
        }
    } finally {
        closePdfJs(viewer);
    }

    if (!matches.length) {
        throw new ToolError('nothing_found', `"${find}" was not found in the text of this PDF.`);
    }

    const { rgb } = await pdfLib();
    const { doc } = await loadPdf(bytes, { fileName: file.name, file });

    for (const [n, match] of matches.entries()) {
        if (n % 20 === 0) progress(60 + (n / matches.length) * 30, `Replacing ${n + 1} of ${matches.length}…`);

        const font = replacement ? await fontFor(doc, replacement, match.style) : null;
        const page = doc.getPage(match.page);
        const first = match.rects[0];
        const last = match.rects[match.rects.length - 1];
        const x = Math.min(...match.rects.map((r) => r.x));
        const width = Math.max(...match.rects.map((r) => r.x + r.width)) - x;
        const y = Math.min(...match.rects.map((r) => r.y));
        const height = Math.max(...match.rects.map((r) => r.y + r.height)) - y;

        page.drawRectangle({ x: x - 0.5, y, width: width + 1, height, color: rgb(1, 1, 1) });

        if (font) {
            // Text height ≈ 85% of the box; shrink to fit the original width if needed.
            let size = first.height / 1.18;
            const natural = font.widthOfTextAtSize(replacement, size);
            if (natural > width * 1.15 && natural > 0) size *= (width * 1.15) / natural;
            const baseline = last.y + (last.height / 1.18) * 0.22;
            page.drawText(replacement, { x, y: baseline, size, font, color: rgb(0, 0, 0) });
        }
    }

    progress(94, 'Saving…');

    return {
        blob: pdfBlob(await saveDocument(doc)),
        filename: outputName(file.name, 'edited', 'pdf'),
        title: `${matches.length} replacement${matches.length === 1 ? '' : 's'} made`,
        stats: { matches: matches.length },
    };
}
