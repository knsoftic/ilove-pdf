import { pdfLib, loadPdf } from './pdf';
import { readBytes } from './files';
import { parsePages } from './pages';

/**
 * Helpers shared by the in-browser tool handlers.
 */

export async function openDocument(file, extra = {}) {
    const bytes = await readBytes(file);
    const loaded = await loadPdf(bytes, { fileName: file.name, file, ...extra });
    return { ...loaded, bytes };
}

export function pdfBlob(bytes) {
    return new Blob([bytes], { type: 'application/pdf' });
}

export async function saveDocument(doc, options = {}) {
    return doc.save({ useObjectStreams: true, ...options });
}

export function hexToRgbValues(hex) {
    const value = String(hex || '#000000').replace('#', '');
    const full = value.length === 3 ? value.split('').map((c) => c + c).join('') : value.padEnd(6, '0');
    return [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16) / 255);
}

export async function color(hex) {
    const { rgb } = await pdfLib();
    return rgb(...hexToRgbValues(hex));
}

/** 0-based page indices from a page range option ("" = all pages). */
export function selectedIndices(value, pageCount) {
    return parsePages(value, pageCount).map((p) => p - 1);
}

/**
 * The page as the reader sees it (after /Rotate and CropBox), with a function
 * mapping visual coordinates (origin bottom-left) into PDF user space.
 *
 * Anything drawn at visual angle θ must be drawn with rotate = θ + rotation.
 */
export function pageFrame(page) {
    const rotation = (((page.getRotation().angle || 0) % 360) + 360) % 360;
    const box = page.getCropBox ? page.getCropBox() : page.getMediaBox();
    const { x: bx, y: by, width: bw, height: bh } = box;
    const swapped = rotation === 90 || rotation === 270;

    const toPdf = (x, y) => {
        switch (rotation) {
            case 90:
                return [bx + bw - y, by + x];
            case 180:
                return [bx + bw - x, by + bh - y];
            case 270:
                return [bx + y, by + bh - x];
            default:
                return [bx + x, by + y];
        }
    };

    return { width: swapped ? bh : bw, height: swapped ? bw : bh, rotation, toPdf };
}

/**
 * Position of a box on a page for a 9-grid position like "bottom-center".
 * Returns the visual centre point of the box.
 */
export function anchor(position, frame, boxWidth, boxHeight, margin) {
    const [row, col] = String(position || 'middle-center').split('-');
    const cx = col === 'left' ? margin + boxWidth / 2 : col === 'right' ? frame.width - margin - boxWidth / 2 : frame.width / 2;
    const cy = row === 'top' ? frame.height - margin - boxHeight / 2 : row === 'bottom' ? margin + boxHeight / 2 : frame.height / 2;
    return { cx, cy };
}

/**
 * Draws text centred on a visual point, rotated by `angle` degrees
 * (counter-clockwise), correctly on rotated pages.
 */
export async function drawCenteredText(page, frame, text, { cx, cy, font, size, color: fill, opacity = 1, angle = 0 }) {
    const { degrees } = await pdfLib();
    const width = font.widthOfTextAtSize(text, size);
    const height = font.heightAtSize(size, { descender: false }) * 0.8;
    const rad = (angle * Math.PI) / 180;

    // Bottom-left origin of the text so that its centre lands on (cx, cy).
    const vx = cx - ((width / 2) * Math.cos(rad) - (height / 2) * Math.sin(rad));
    const vy = cy - ((width / 2) * Math.sin(rad) + (height / 2) * Math.cos(rad));
    const [x, y] = frame.toPdf(vx, vy);

    page.drawText(text, { x, y, size, font, color: fill, opacity, rotate: degrees(angle + frame.rotation) });
    return { width, height };
}

/**
 * Draws an image centred on a visual point, rotated by `angle`.
 */
export async function drawCenteredImage(page, frame, image, { cx, cy, width, height, opacity = 1, angle = 0 }) {
    const { degrees } = await pdfLib();
    const rad = (angle * Math.PI) / 180;
    const vx = cx - ((width / 2) * Math.cos(rad) - (height / 2) * Math.sin(rad));
    const vy = cy - ((width / 2) * Math.sin(rad) + (height / 2) * Math.cos(rad));
    const [x, y] = frame.toPdf(vx, vy);

    page.drawImage(image, { x, y, width, height, opacity, rotate: degrees(angle + frame.rotation) });
}

/** Replaces placeholders such as {page}, {total}, {date}, {filename}. */
export function fillPlaceholders(template, values) {
    return String(template || '').replace(/\{(\w+)\}/g, (match, key) => (key in values ? String(values[key]) : match));
}

/** Embeds a PNG/JPEG (or WEBP via canvas) image file into a document. */
export async function embedImageFile(doc, file) {
    const bytes = await readBytes(file);

    if (bytes[0] === 0xff && bytes[1] === 0xd8) return doc.embedJpg(bytes);
    if (bytes[0] === 0x89 && bytes[1] === 0x50) return doc.embedPng(bytes);

    // Other formats: convert through a canvas.
    const bitmap = await createImageBitmap(file);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    return doc.embedPng(new Uint8Array(await blob.arrayBuffer()));
}
