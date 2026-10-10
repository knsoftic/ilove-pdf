import { openDocument, saveDocument, pdfBlob, pageFrame, anchor, drawCenteredText, drawCenteredImage, color, selectedIndices, embedImageFile } from '../lib/pdf-tools';
import { fontFor } from '../lib/fonts';
import { pdfLib } from '../lib/pdf';
import { outputName } from '../lib/files';
import { ToolError } from '../lib/errors';
import { nextFrame } from '../lib/dom';

/**
 * Text or image watermark on selected pages, at a 9-grid position or tiled.
 */
export async function run({ files, options, optionFiles, progress }) {
    const { PDFOperator, PDFOperatorNames, PDFName } = await pdfLib();
    const file = files[0];
    const { doc } = await openDocument(file);
    const indices = selectedIndices(options.pages, doc.getPageCount());
    const opacity = Math.max(0.05, Math.min(1, (Number(options.opacity) || 30) / 100));
    const angle = Number(options.rotation) || 0;
    const position = options.position || 'middle-center';
    const isImage = options.type === 'image';

    let font;
    let text;
    let size;
    let fill;
    let image;

    if (isImage) {
        if (!optionFiles.image) throw new ToolError('invalid_options', 'Please choose a watermark image.');
        image = await embedImageFile(doc, optionFiles.image);
    } else {
        text = String(options.text || '').trim();
        if (!text) throw new ToolError('invalid_options', 'Please enter the watermark text.');
        size = Math.max(6, Number(options.font_size) || 48);
        font = await fontFor(doc, text, { bold: true });
        fill = await color(options.color || '#dc2626');
    }

    for (const [n, index] of indices.entries()) {
        progress((n / indices.length) * 90, `Watermarking page ${index + 1}…`);
        if (n % 10 === 0) await nextFrame();

        const page = doc.getPage(index);
        const frame = pageFrame(page);

        let boxW;
        let boxH;

        if (isImage) {
            boxW = (frame.width * Math.max(5, Number(options.scale) || 40)) / 100;
            boxH = (boxW * image.height) / image.width;
        } else {
            boxW = font.widthOfTextAtSize(text, size);
            boxH = font.heightAtSize(size, { descender: false });
        }

        const centres = [];

        if (position === 'tile') {
            const stepX = boxW * 1.6 + 40;
            const stepY = boxH * 3 + 60;
            let row = 0;
            for (let y = stepY / 2; y < frame.height + stepY; y += stepY, row++) {
                for (let x = (row % 2 ? stepX / 2 : 0) + boxW / 2; x < frame.width + stepX; x += stepX) {
                    centres.push({ cx: x, cy: y });
                }
            }
        } else {
            const rad = (Math.abs(angle) * Math.PI) / 180;
            // Rotated bounding box, so corners keep their margin.
            const rotW = Math.abs(boxW * Math.cos(rad)) + Math.abs(boxH * Math.sin(rad));
            const rotH = Math.abs(boxW * Math.sin(rad)) + Math.abs(boxH * Math.cos(rad));
            centres.push(anchor(position, frame, rotW, rotH, 24));
        }

        // Tag the watermark as a standard pagination artifact (like Acrobat does),
        // so it is ignored by screen readers and can be removed again later.
        page.pushOperators(PDFOperator.of(PDFOperatorNames.BeginMarkedContentSequence, [PDFName.of('Artifact'), doc.context.obj({ Type: 'Pagination', Subtype: 'Watermark' })]));

        for (const { cx, cy } of centres) {
            if (isImage) {
                await drawCenteredImage(page, frame, image, { cx, cy, width: boxW, height: boxH, opacity, angle });
            } else {
                await drawCenteredText(page, frame, text, { cx, cy, font, size, color: fill, opacity, angle });
            }
        }

        page.pushOperators(PDFOperator.of(PDFOperatorNames.EndMarkedContent));
    }

    progress(94, 'Saving…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'watermarked', 'pdf'),
        stats: { pages: indices.length },
    };
}
