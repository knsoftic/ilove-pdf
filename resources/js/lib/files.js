import { ToolError } from './errors';
import { formatBytes } from './dom';

/**
 * Detects a file format from its first bytes ("magic numbers").
 */
export async function sniff(file) {
    const bytes = new Uint8Array(await file.slice(0, 1032).arrayBuffer());
    const ascii = (start, length) => String.fromCharCode(...bytes.slice(start, start + length));

    if (ascii(0, 1032).includes('%PDF-')) return 'pdf';
    if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'jpg';
    if (bytes[0] === 0x89 && ascii(1, 3) === 'PNG') return 'png';
    if (ascii(0, 4) === 'RIFF' && ascii(8, 4) === 'WEBP') return 'webp';
    if (bytes[0] === 0x50 && bytes[1] === 0x4b) return 'zip';
    if (bytes[0] === 0xd0 && bytes[1] === 0xcf && bytes[2] === 0x11 && bytes[3] === 0xe0) return 'ole';
    if (ascii(0, 5) === '{\\rtf') return 'rtf';
    return bytes.includes(0) ? 'binary' : 'text';
}

const EXPECTED = {
    pdf: ['pdf'],
    jpg: ['jpg'],
    jpeg: ['jpg'],
    png: ['png'],
    webp: ['webp'],
    docx: ['zip'],
    xlsx: ['zip'],
    pptx: ['zip'],
    odt: ['zip'],
    ods: ['zip'],
    odp: ['zip'],
    doc: ['ole'],
    xls: ['ole'],
    ppt: ['ole'],
    rtf: ['rtf'],
    csv: ['text'],
    txt: ['text'],
    html: ['text'],
    htm: ['text'],
};

export function extensionOf(name) {
    const match = /\.([a-z0-9]+)$/i.exec(name || '');
    return match ? match[1].toLowerCase() : '';
}

/**
 * Validates files against a tool's accepted extensions and size limit.
 * Returns the valid files; throws when none are valid.
 */
export async function validateFiles(files, { extensions, maxBytes, label }) {
    const valid = [];
    const errors = [];

    for (const file of files) {
        const ext = extensionOf(file.name);

        if (!extensions.includes(ext)) {
            errors.push(new ToolError('unsupported_format', `"${file.name}" is not supported. Allowed: ${label}.`));
            continue;
        }

        if (file.size === 0) {
            errors.push(new ToolError('unsupported_format', `"${file.name}" is empty.`));
            continue;
        }

        if (maxBytes && file.size > maxBytes) {
            errors.push(new ToolError('file_too_large', `"${file.name}" is larger than ${formatBytes(maxBytes, 0)}.`));
            continue;
        }

        const kind = await sniff(file);

        if (EXPECTED[ext] && !EXPECTED[ext].includes(kind)) {
            errors.push(new ToolError(ext === 'pdf' ? 'invalid_pdf' : 'unsupported_format', ext === 'pdf' ? `"${file.name}" is not a valid PDF document.` : `The content of "${file.name}" does not match its file type.`));
            continue;
        }

        valid.push(file);
    }

    return { valid, errors };
}

export function baseName(name, fallback = 'document') {
    const base = String(name || '')
        .replace(/\.[^.]+$/, '')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^A-Za-z0-9._ -]+/g, '')
        .trim()
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^[-.]+|[-.]+$/g, '')
        .slice(0, 80);

    return base || fallback;
}

export function outputName(original, suffix, extension) {
    const base = baseName(original);
    return `${suffix ? `${base}-${suffix}` : base}.${extension}`;
}

export function download(blobOrUrl, filename) {
    const url = blobOrUrl instanceof Blob ? URL.createObjectURL(blobOrUrl) : blobOrUrl;
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || '';
    a.rel = 'noopener';
    document.body.append(a);
    a.click();
    a.remove();

    if (blobOrUrl instanceof Blob) setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

export function readText(file) {
    return file.text();
}

export async function readBytes(file) {
    return new Uint8Array(await file.arrayBuffer());
}

/** Loads an image file into an ImageBitmap/HTMLImageElement with EXIF orientation applied. */
export async function loadImage(file) {
    if ('createImageBitmap' in window) {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            /* fall back below */
        }
    }

    const url = URL.createObjectURL(file);
    try {
        const img = new Image();
        img.decoding = 'async';
        img.src = url;
        await img.decode();
        return img;
    } finally {
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
}

export function canvasToBlob(canvas, type = 'image/png', quality = 0.92) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new ToolError('out_of_memory'))), type, quality);
    });
}
