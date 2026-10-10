import { openPdfJs, pdfLib, loadPdf, closePdfJs } from '../lib/pdf';
import { readBytes, outputName } from '../lib/files';
import { pageLines, findInLines } from '../lib/text-layout';
import { h, escapeHtml } from '../lib/dom';
import { ToolError } from '../lib/errors';
import { pdfBlob, saveDocument } from '../lib/pdf-tools';

/**
 * Finds every occurrence of a word or phrase. Shows the matches with their
 * page and context, and offers the PDF with all matches highlighted.
 */
export async function run({ files, options, progress, signal }) {
    const file = files[0];
    const query = String(options.query || '').trim();
    if (!query) throw new ToolError('invalid_options', 'Please enter a word or phrase to search for.');

    const bytes = await readBytes(file);
    const doc = await openPdfJs(bytes, { fileName: file.name, file });
    const results = [];

    try {
        for (let i = 1; i <= doc.numPages; i++) {
            if (signal?.aborted) throw new ToolError('cancelled');
            const page = await doc.getPage(i);
            const lines = await pageLines(page);
            for (const match of findInLines(lines, query, { caseSensitive: options.case_sensitive, wholeWord: options.whole_word })) {
                results.push({ page: i, ...match });
            }
            page.cleanup();
            progress((i / doc.numPages) * 80, `Searching page ${i} of ${doc.numPages}…`);
        }
    } finally {
        closePdfJs(doc);
    }

    const render = renderResults(results, query);

    if (!results.length) {
        return { title: 'No matches found', subtitle: `"${query}" does not appear in the text of this PDF.`, render, stats: { matches: 0 } };
    }

    // Highlighted copy of the PDF.
    progress(85, 'Highlighting matches…');
    const { rgb, BlendMode } = await pdfLib();
    const { doc: pdf } = await loadPdf(bytes, { fileName: file.name, file });

    for (const result of results) {
        const page = pdf.getPage(result.page - 1);
        for (const rect of result.rects) {
            page.drawRectangle({ ...rect, color: rgb(1, 0.9, 0.1), opacity: 0.45, blendMode: BlendMode.Multiply });
        }
    }

    const pagesWithMatches = new Set(results.map((r) => r.page)).size;

    return {
        title: `${results.length} match${results.length === 1 ? '' : 'es'} found`,
        blob: pdfBlob(await saveDocument(pdf)),
        filename: outputName(file.name, 'highlighted', 'pdf'),
        render,
        stats: { matches: results.length, pages: pagesWithMatches },
    };
}

function renderResults(results, query) {
    if (!results.length) return h('p', { class: 'text-sm text-slate-500' }, 'Tip: scanned PDFs contain no text – run OCR first.');

    const list = h('ol', { class: 'max-h-80 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-left text-sm dark:border-slate-700 dark:bg-slate-800/50' });

    for (const result of results.slice(0, 500)) {
        const text = result.line.text.replace(/\s+/g, ' ');
        const start = Math.max(0, result.start - 60);
        const before = text.slice(start, result.start);
        const after = text.slice(result.end, result.end + 60);

        list.append(
            h(
                'li',
                { class: 'flex gap-3 rounded-lg bg-white p-2.5 dark:bg-slate-900' },
                h('span', { class: 'shrink-0 rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' }, `Page ${result.page}`),
                h('span', { class: 'min-w-0 text-slate-600 dark:text-slate-300', html: `${start > 0 ? '…' : ''}${escapeHtml(before)}<mark class="rounded bg-yellow-200 px-0.5 text-slate-900">${escapeHtml(result.text)}</mark>${escapeHtml(after)}…` }),
            ),
        );
    }

    if (results.length > 500) list.append(h('li', { class: 'text-center text-xs text-slate-500' }, `…and ${results.length - 500} more`));

    return h('div', { class: 'w-full' }, h('p', { class: 'mb-2 text-left text-sm font-medium text-slate-700 dark:text-slate-300' }, `Results for “${query}”`), list);
}
