import { upload, pollJob } from './http';
import { ToolError } from './errors';
import { openPdfJs, closePdfJs } from './pdf';
import { readBytes } from './files';

/**
 * Text extraction shared by "PDF to Text" and the PDF translator.
 *
 * @returns {Promise<{pages: string[], emptyPages: number[]}>}
 */
export async function extractPdfText(file, { engine = 'browser', config, layout = false, signal, progress } = {}) {
    if (engine === 'server') {
        const data = new FormData();
        data.append('files[]', file, file.name);
        data.append('options', JSON.stringify({ layout }));

        progress?.(0, 'Uploading…');
        let job = await upload(config.endpoints.process, data, {
            signal,
            onProgress: (f) => progress?.(f < 1 ? f * 60 : null, f < 1 ? 'Uploading…' : 'Extracting text on the server…'),
        });
        job = await pollJob(job, { signal, onProgress: (j) => progress?.(60 + (j.progress || 0) * 0.35, j.message || 'Extracting text…') });

        const response = await fetch(job.result.download_url, { credentials: 'same-origin' });
        if (!response.ok) throw new ToolError('server_error');
        const text = (await response.text()).replace(/^﻿/, '');
        const pages = text.split('\f').map((p) => p.replace(/^\n/, '').replace(/\s+$/, ''));

        return { pages, emptyPages: pages.map((p, i) => (p.trim().length < 3 ? i + 1 : null)).filter(Boolean), statusUrl: job.status_url };
    }

    const { extract } = await import('../tools/pdf-to-text');
    return extract(file, { layout, signal, progress });
}

/**
 * OCR of selected pages in the browser (Tesseract.js).
 *
 * @returns {Promise<Map<number, string>>}
 */
export async function ocrPages(file, pageNumbers, { language = 'eng', signal, progress } = {}) {
    const { renderForOcr } = await import('../tools/ocr');
    const { createOcrWorker, recognize } = await import('./ocr');
    const doc = await openPdfJs(await readBytes(file), { fileName: file.name, file });
    const results = new Map();
    let current = 0;

    const worker = await createOcrWorker(language, (fraction, message) => {
        const base = (current / pageNumbers.length) * 100;
        progress?.(fraction === null ? null : base + (fraction * 100) / pageNumbers.length, `Page ${pageNumbers[current]}: ${message}`);
    });

    try {
        for (const [n, number] of pageNumbers.entries()) {
            if (signal?.aborted) throw new ToolError('cancelled');
            current = n;
            const page = await doc.getPage(number);
            const { canvas } = await renderForOcr(page);
            const { text } = await recognize(worker, canvas);
            results.set(number, text.trim());
            canvas.width = canvas.height = 0;
            page.cleanup();
        }
    } finally {
        await worker.terminate();
        closePdfJs(doc);
    }

    return results;
}

/**
 * Splits text into chunks of at most `size` characters on paragraph / line
 * boundaries (for translation requests).
 */
export function chunkText(text, size = 3500) {
    const chunks = [];
    let current = '';

    for (const part of text.split(/(\n\s*\n)/)) {
        if ((current + part).length > size && current) {
            chunks.push(current);
            current = '';
        }

        if (part.length > size) {
            // Very long paragraph: split on lines, then on sentence ends.
            for (const piece of part.split(/(?<=\n)|(?<=[.!?।۔])\s+/)) {
                if ((current + piece).length > size && current) {
                    chunks.push(current);
                    current = '';
                }
                current += piece.length > size ? piece.slice(0, size) : piece;
            }
        } else {
            current += part;
        }
    }

    if (current) chunks.push(current);
    return chunks;
}
