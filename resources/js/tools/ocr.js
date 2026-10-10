import { pdfLib, openPdfJs, renderPage, closePdfJs } from '../lib/pdf';
import { readBytes, outputName } from '../lib/files';
import { parsePages } from '../lib/pages';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { fontFor, isWinAnsi } from '../lib/fonts';
import { createOcrWorker, recognize } from '../lib/ocr';
import { ToolError } from '../lib/errors';

/**
 * Renders a page for OCR at ~300 DPI (capped for memory).
 */
export async function renderForOcr(page) {
    const base = page.getViewport({ scale: 1 });
    const scale = Math.min(300 / 72, Math.sqrt(14_000_000 / (base.width * base.height)));
    return renderPage(page, { scale });
}

/**
 * OCR in the browser with Tesseract.js: searchable PDF or plain text.
 */
export async function run({ files, options, progress, signal }) {
    const file = files[0];
    const bytes = await readBytes(file);
    const viewer = await openPdfJs(bytes, { fileName: file.name, file });
    const pages = parsePages(options.pages, viewer.numPages);
    const language = options.language || 'eng';
    let current = 0;

    const worker = await createOcrWorker(language, (fraction, message) => {
        const base = (current / pages.length) * 85;
        progress(fraction === null ? null : base + (fraction * 85) / pages.length, `Page ${pages[current] ?? ''}: ${message}`);
    });

    const results = new Map();

    try {
        for (const [n, number] of pages.entries()) {
            if (signal?.aborted) throw new ToolError('cancelled');
            current = n;
            progress((n / pages.length) * 85, `Preparing page ${number}…`);

            const page = await viewer.getPage(number);
            const { canvas, viewport } = await renderForOcr(page);
            const result = await recognize(worker, canvas);
            results.set(number, { ...result, viewport });
            canvas.width = canvas.height = 0;
            page.cleanup();
        }
    } finally {
        await worker.terminate();
        closePdfJs(viewer);
    }

    const text = pages.map((p) => results.get(p)?.text.trim() || '').join('\n\f');

    if (!text.replace(/\s/g, '')) {
        throw new ToolError('ocr_failed', 'No text could be recognised. Check the selected language and the scan quality.');
    }

    if (options.output === 'text') {
        return {
            blob: new Blob(['﻿', text.replace(/\f/g, '\n')], { type: 'text/plain;charset=utf-8' }),
            filename: outputName(file.name, 'ocr', 'txt'),
            stats: { pages: pages.length, characters: text.length },
        };
    }

    progress(88, 'Adding the text layer…');
    const pdf = await addTextLayer(file, results);

    return {
        blob: pdfBlob(pdf),
        filename: outputName(file.name, 'ocr', 'pdf'),
        stats: { pages: pages.length, words: [...results.values()].reduce((n, r) => n + r.words.length, 0) },
    };
}

/**
 * Writes the recognised words as invisible text over the original pages,
 * which makes the PDF searchable and selectable.
 */
export async function addTextLayer(file, results) {
    const { beginText, endText, setFontAndSize, setTextRenderingMode, setTextMatrix, showText, TextRenderingMode } = await pdfLib();
    const { doc } = await openDocument(file);
    const allText = [...results.values()].map((r) => r.text).join(' ');
    const font = await fontFor(doc, isWinAnsi(allText) ? 'A' : allText);
    const supported = font.getCharacterSet ? new Set(font.getCharacterSet()) : null;

    for (const [number, { words, viewport }] of results) {
        const page = doc.getPage(number - 1);
        const fontKey = page.node.newFontDictionary(font.name, font.ref);
        const operators = [];

        for (const word of words) {
            const text = word.text.trim();
            if (!text) continue;
            if (supported && [...text].some((ch) => !supported.has(ch.codePointAt(0)))) continue;
            if (!supported && !isWinAnsi(text)) continue;

            const { x0, y0, x1, y1 } = word.bbox;
            const [ax, ay] = viewport.convertToPdfPoint(x0, y1);
            const [bx, by] = viewport.convertToPdfPoint(x1, y1);
            const [cx, cy] = viewport.convertToPdfPoint(x0, y0);

            const width = Math.hypot(bx - ax, by - ay);
            const height = Math.hypot(cx - ax, cy - ay);
            if (width < 0.5 || height < 0.5) continue;

            const size = height * 0.9;
            const natural = font.widthOfTextAtSize(text, size) || 1;
            const scale = width / natural;
            const cos = (bx - ax) / width;
            const sin = (by - ay) / width;

            operators.push(
                beginText(),
                setFontAndSize(fontKey, size),
                setTextRenderingMode(TextRenderingMode.Invisible),
                setTextMatrix(cos * scale, sin * scale, -sin, cos, ax, ay + height * 0.15),
                showText(font.encodeText(text)),
                endText(),
            );
        }

        if (operators.length) page.pushOperators(...operators);
    }

    return saveDocument(doc);
}
