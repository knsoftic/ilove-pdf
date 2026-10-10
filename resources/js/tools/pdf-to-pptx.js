import { openPdfJs, renderPageToBlob, closePdfJs } from '../lib/pdf';
import { readBytes, outputName, baseName } from '../lib/files';
import { buildPptx } from '../lib/pptx';
import { config } from '../lib/config';
import { ToolError } from '../lib/errors';

/**
 * PDF -> PowerPoint in the browser: every page becomes a slide picture.
 */
export async function run({ files, options, progress, signal }) {
    const file = files[0];
    const doc = await openPdfJs(await readBytes(file), { fileName: file.name, file });
    const target = options.quality === 'high' ? 2400 : 1600;
    const slides = [];

    try {
        for (let i = 1; i <= doc.numPages; i++) {
            if (signal?.aborted) throw new ToolError('cancelled');
            progress((i / doc.numPages) * 85, `Rendering page ${i} of ${doc.numPages}…`);

            const page = await doc.getPage(i);
            const viewport = page.getViewport({ scale: 1 });
            const scale = target / Math.max(viewport.width, viewport.height);
            const blob = await renderPageToBlob(page, { scale, type: 'image/jpeg', quality: 0.88 });
            slides.push({ blob, width: viewport.width, height: viewport.height });
            page.cleanup();
        }
    } finally {
        closePdfJs(doc);
    }

    progress(92, 'Building presentation…');
    const blob = await buildPptx(slides, { title: baseName(file.name), creator: config.siteName });

    return { blob, filename: outputName(file.name, '', 'pptx'), stats: { slides: slides.length } };
}
