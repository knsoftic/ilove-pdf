import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { vendorUrl } from './config';
import { ToolError, toToolError } from './errors';
import { promptPassword } from './modal';
import { canvasToBlob } from './files';

let pdfjsPromise = null;
let pdfLibPromise = null;

/**
 * PDF.js (Apache-2.0), loaded on demand.
 */
export function pdfjs() {
    pdfjsPromise ??= import('pdfjs-dist').then((lib) => {
        lib.GlobalWorkerOptions.workerSrc = workerUrl;
        return lib;
    });
    return pdfjsPromise;
}

/**
 * pdf-lib (MIT, Cantoo fork with encryption support), loaded on demand.
 */
export function pdfLib() {
    pdfLibPromise ??= import('@cantoo/pdf-lib');
    return pdfLibPromise;
}

const passwords = new WeakMap();

/**
 * Opens a document with PDF.js, asking for the password when needed.
 *
 * @param {Uint8Array} bytes
 */
export async function openPdfJs(bytes, { fileName, password, file } = {}) {
    const lib = await pdfjs();
    let attempt = password ?? (file ? passwords.get(file) : undefined);
    let wrong = false;

    for (;;) {
        try {
            const task = lib.getDocument({
                // PDF.js transfers the buffer to its worker, so give it a copy.
                data: bytes.slice(),
                password: attempt,
                cMapUrl: vendorUrl('pdfjs/cmaps/'),
                cMapPacked: true,
                standardFontDataUrl: vendorUrl('pdfjs/standard_fonts/'),
                wasmUrl: vendorUrl('pdfjs/wasm/'),
                iccUrl: vendorUrl('pdfjs/iccs/'),
                isEvalSupported: false,
                enableXfa: false,
                // Keeps font names / bold / italic flags available (text replacement, editor).
                fontExtraProperties: true,
            });
            const doc = await task.promise;
            if (file && attempt) passwords.set(file, attempt);
            return doc;
        } catch (error) {
            if (error?.name !== 'PasswordException') throw toToolError(error);

            wrong = error.code === 2;
            attempt = await promptPassword({ fileName, wrong });

            if (attempt === null) throw new ToolError('password_protected');
        }
    }
}

/**
 * Loads a document with pdf-lib. Encrypted files are decrypted (the user is
 * asked for the password when one is required to open the file).
 *
 * @returns {Promise<{doc: import('@cantoo/pdf-lib').PDFDocument, encrypted: boolean, password: string|null}>}
 */
export async function loadPdf(bytes, { fileName, file, password, updateMetadata = false } = {}) {
    const { PDFDocument } = await pdfLib();
    const options = { updateMetadata, throwOnInvalidObject: false };

    try {
        return { doc: await PDFDocument.load(bytes, options), encrypted: false, password: null };
    } catch (error) {
        if (!/encrypted/i.test(String(error?.message)) && error?.name !== 'EncryptedPDFError') {
            throw toToolError(error);
        }
    }

    // Owner-password only (restrictions without an open password).
    let attempt = password ?? (file ? passwords.get(file) : undefined) ?? '';
    let wrong = false;

    for (;;) {
        try {
            const doc = await PDFDocument.load(bytes, { ...options, password: attempt });
            if (file) passwords.set(file, attempt);
            return { doc, encrypted: true, password: attempt };
        } catch (error) {
            const message = String(error?.message);

            if (!/NEEDS PASSWORD|Password incorrect/i.test(message)) throw toToolError(error);

            wrong = attempt !== '' && /incorrect/i.test(message);
            attempt = await promptPassword({ fileName, wrong });

            if (attempt === null) throw new ToolError('password_protected');
        }
    }
}

/**
 * Releases a PDF.js document and its worker resources (PDF.js ≥ 5 destroys
 * documents through their loading task).
 */
export function closePdfJs(doc) {
    if (!doc) return;
    try {
        if (doc.loadingTask?.destroy) doc.loadingTask.destroy();
        else doc.destroy?.();
    } catch {
        /* already destroyed */
    }
}

/** The password used for a file earlier in this page session (if any). */
export function knownPassword(file) {
    return passwords.get(file);
}

/**
 * Renders a PDF.js page into a canvas.
 */
export async function renderPage(page, { scale = 1, canvas = document.createElement('canvas'), background = '#ffffff', rotation } = {}) {
    const viewport = page.getViewport({ scale, rotation: rotation ?? page.rotate });
    const ratio = 1;
    canvas.width = Math.max(1, Math.floor(viewport.width * ratio));
    canvas.height = Math.max(1, Math.floor(viewport.height * ratio));

    const ctx = canvas.getContext('2d', { alpha: false });
    ctx.fillStyle = background;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    await page.render({ canvas, viewport, background }).promise;
    return { canvas, viewport };
}

export async function renderPageToBlob(page, { scale = 1, type = 'image/png', quality = 0.9, grayscale = false } = {}) {
    const { canvas } = await renderPage(page, { scale });

    if (grayscale) {
        const ctx = canvas.getContext('2d');
        const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const d = image.data;
        for (let i = 0; i < d.length; i += 4) {
            const y = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            d[i] = d[i + 1] = d[i + 2] = y;
        }
        ctx.putImageData(image, 0, 0);
    }

    const blob = await canvasToBlob(canvas, type, quality);
    canvas.width = canvas.height = 0; // free memory early
    return blob;
}

/**
 * Picks a render scale so the longest side has about `targetPx` pixels,
 * capped by `maxScale` (memory safety on phones).
 */
export function scaleFor(page, targetPx, maxScale = 4) {
    const viewport = page.getViewport({ scale: 1 });
    return Math.min(maxScale, targetPx / Math.max(viewport.width, viewport.height));
}
