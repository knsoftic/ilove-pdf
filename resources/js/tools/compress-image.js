import { loadImage, canvasToBlob, outputName, extensionOf } from '../lib/files';
import { ToolError } from '../lib/errors';

const TYPES = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };

export async function drawScaled(file, maxSide = 0) {
    const bitmap = await loadImage(file);
    let { width, height } = bitmap;

    if (maxSide > 0 && Math.max(width, height) > maxSide) {
        const scale = maxSide / Math.max(width, height);
        width = Math.round(width * scale);
        height = Math.round(height * scale);
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close?.();
    return canvas;
}

/**
 * Re-encodes images with the canvas API (EXIF orientation applied).
 */
export async function run({ files, options, progress }) {
    const quality = Math.max(0.1, Math.min(0.95, (Number(options.quality) || 75) / 100));
    const output = [];
    let before = 0;
    let after = 0;

    for (const [i, file] of files.entries()) {
        progress((i / files.length) * 95, `Compressing ${file.name}…`);

        const sourceExt = extensionOf(file.name) === 'jpeg' ? 'jpg' : extensionOf(file.name);
        const ext = options.format && options.format !== 'keep' ? options.format : sourceExt;
        const canvas = await drawScaled(file, Number(options.max_dimension) || 0);

        if (ext === 'jpg') {
            // JPEG has no transparency: paint on white.
            const ctx = canvas.getContext('2d');
            ctx.globalCompositeOperation = 'destination-over';
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }

        let blob = await canvasToBlob(canvas, TYPES[ext], quality);
        if (ext === 'webp' && blob.type !== 'image/webp') throw new ToolError('unsupported_format', 'Your browser cannot create WEBP images.');

        // Same format and not resized: never return a bigger file.
        if (ext === sourceExt && !Number(options.max_dimension) && blob.size >= file.size) blob = file;

        before += file.size;
        after += blob.size;
        output.push({ name: outputName(file.name, 'compressed', ext), blob });
        canvas.width = canvas.height = 0;
    }

    return {
        files: output,
        filename: 'compressed-images.zip',
        stats: { original_size: before, new_size: after, saved_percent: Math.max(0, Math.round((1 - after / before) * 1000) / 10) },
    };
}
