import { config, vendorUrl } from './config';
import { ToolError } from './errors';

/**
 * Tesseract.js (Apache-2.0) – OCR in the browser.
 *
 * The worker and the WebAssembly core are served from this site
 * (public/vendor/tesseract). Language data is loaded from
 * config.ocr.langPath (a CDN by default, or your own server).
 */
export async function createOcrWorker(language = 'eng', onProgress) {
    const Tesseract = await import('tesseract.js');
    const langPath = (config.ocr?.langPath || '').includes('{lang}') ? undefined : config.ocr?.langPath || undefined;

    try {
        const worker = await Tesseract.createWorker(language.split('+'), Tesseract.OEM.LSTM_ONLY, {
            workerPath: vendorUrl('tesseract/worker.min.js'),
            corePath: vendorUrl('tesseract/core/'),
            langPath,
            workerBlobURL: false,
            logger: (m) => {
                if (m.status === 'recognizing text') onProgress?.(m.progress, 'Recognising text…');
                else if (/loading|initializ/i.test(m.status)) onProgress?.(null, `${m.status[0].toUpperCase()}${m.status.slice(1)}…`);
            },
        });

        return worker;
    } catch (error) {
        console.error(error);
        throw new ToolError('ocr_failed', 'The OCR engine could not be loaded. Please check your connection and try again.');
    }
}

/**
 * Recognises a canvas. Returns { text, words: [{text, bbox:{x0,y0,x1,y1}}] }.
 */
export async function recognize(worker, canvas) {
    const { data } = await worker.recognize(canvas, {}, { text: true, blocks: true });
    const words = [];

    for (const block of data.blocks || []) {
        for (const paragraph of block.paragraphs || []) {
            for (const line of paragraph.lines || []) {
                for (const word of line.words || []) {
                    if (word.text?.trim() && word.confidence > 20) words.push({ text: word.text, bbox: word.bbox, line: line.bbox });
                }
            }
        }
    }

    return { text: data.text || '', words };
}
