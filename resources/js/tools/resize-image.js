import { loadImage, canvasToBlob, outputName, extensionOf } from '../lib/files';

const TYPES = { jpg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };

function targetSize(options, w, h) {
    if ((options.mode || 'percent') === 'percent') {
        const scale = Math.max(1, Math.min(400, Number(options.percent) || 50)) / 100;
        return [Math.max(1, Math.round(w * scale)), Math.max(1, Math.round(h * scale))];
    }

    const width = Number(options.width) || 0;
    const height = Number(options.height) || 0;
    const keep = options.keep_ratio !== false;

    if (width && height && !keep) return [width, height];
    if (width && height) {
        const scale = Math.min(width / w, height / h);
        return [Math.round(w * scale), Math.round(h * scale)];
    }
    if (width) return [width, Math.max(1, Math.round((h * width) / w))];
    if (height) return [Math.max(1, Math.round((w * height) / h)), height];
    return [w, h];
}

/**
 * Resizes images with high quality canvas resampling.
 */
export async function run({ files, options, progress }) {
    const output = [];
    let dimensions = '';

    for (const [i, file] of files.entries()) {
        progress((i / files.length) * 95, `Resizing ${file.name}…`);

        const bitmap = await loadImage(file);
        const [width, height] = targetSize(options, bitmap.width, bitmap.height);

        // Downscale in steps of 50% for smoother results.
        let source = bitmap;
        let sw = bitmap.width;
        let sh = bitmap.height;
        while (sw / 2 > width && sh / 2 > height) {
            const step = document.createElement('canvas');
            step.width = Math.round(sw / 2);
            step.height = Math.round(sh / 2);
            step.getContext('2d').drawImage(source, 0, 0, step.width, step.height);
            source = step;
            sw = step.width;
            sh = step.height;
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(source, 0, 0, width, height);
        bitmap.close?.();

        const sourceExt = extensionOf(file.name) === 'jpeg' ? 'jpg' : extensionOf(file.name);
        const ext = options.format && options.format !== 'keep' ? options.format : sourceExt;
        if (ext === 'jpg') {
            ctx.globalCompositeOperation = 'destination-over';
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, width, height);
        }

        const blob = await canvasToBlob(canvas, TYPES[ext] || 'image/png', 0.9);
        output.push({ name: outputName(file.name, `${width}x${height}`, ext), blob });
        dimensions = `${width} × ${height} px`;
    }

    return { files: output, filename: 'resized-images.zip', stats: files.length === 1 ? { dimensions } : {} };
}
