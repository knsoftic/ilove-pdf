import { openPdfJs, closePdfJs } from '../lib/pdf';
import { readBytes, outputName } from '../lib/files';
import { pageLines, linesToText } from '../lib/text-layout';

/**
 * Extracts text in the browser with PDF.js. Pages without text (scans) can
 * be passed through OCR by the caller (see pages/text.js).
 *
 * Returns { text, pages: string[], emptyPages: number[] }.
 */
export async function extract(file, { layout = false, progress, signal } = {}) {
    const doc = await openPdfJs(await readBytes(file), { fileName: file.name, file });
    const pages = [];
    const emptyPages = [];

    try {
        for (let i = 1; i <= doc.numPages; i++) {
            if (signal?.aborted) throw new DOMException('Cancelled', 'AbortError');

            const page = await doc.getPage(i);
            const text = linesToText(await pageLines(page), { layout });
            page.cleanup();

            pages.push(text);
            if (text.trim().length < 3) emptyPages.push(i);

            progress?.((i / doc.numPages) * 100, `Reading page ${i} of ${doc.numPages}…`);
        }
    } finally {
        closePdfJs(doc);
    }

    return { pages, emptyPages, text: pages.join('\n\f') };
}

/**
 * Generic-runner entry point (TXT download).
 */
export async function run({ files, options, progress, signal }) {
    const { text, pages, emptyPages } = await extract(files[0], { layout: Boolean(options.layout), progress, signal });

    return {
        blob: new Blob(['﻿', text.replace(/\f/g, '')], { type: 'text/plain;charset=utf-8' }),
        filename: outputName(files[0].name, '', 'txt'),
        stats: { pages: pages.length, characters: text.length, words: (text.match(/\S+/g) || []).length, empty_pages: emptyPages.join(',') },
    };
}
