import { openPdfJs, closePdfJs } from '../lib/pdf';
import { readBytes, outputName, baseName } from '../lib/files';
import { pageLines, alignCells } from '../lib/text-layout';
import { escapeHtml } from '../lib/dom';
import { ToolError } from '../lib/errors';

/**
 * PDF -> HTML with PDF.js: positioned text ("layout") or clean paragraphs
 * with headings ("text").
 */
export async function run({ files, options, progress, signal }) {
    const file = files[0];
    const mode = options.mode === 'text' ? 'text' : 'layout';
    const doc = await openPdfJs(await readBytes(file), { fileName: file.name, file });
    const pageCount = doc.numPages;
    let body = '';
    let hasText = false;

    try {
        for (let i = 1; i <= doc.numPages; i++) {
            if (signal?.aborted) throw new ToolError('cancelled');
            progress((i / doc.numPages) * 90, `Converting page ${i} of ${doc.numPages}…`);

            const page = await doc.getPage(i);
            const viewport = page.getViewport({ scale: 1 });
            const lines = await pageLines(page);
            if (lines.length) hasText = true;

            body += mode === 'layout' ? layoutPage(lines, viewport, i) : textPage(lines, i);
            page.cleanup();
        }
    } finally {
        closePdfJs(doc);
    }

    if (!hasText) throw new ToolError('nothing_found', 'No text was found in this PDF. Scanned documents need OCR first.');

    const css =
        mode === 'text'
            ? 'body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem;line-height:1.6;color:#1f2937}section{margin-bottom:2.5rem;padding-bottom:2rem;border-bottom:1px solid #e5e7eb}table{border-collapse:collapse;margin:1rem 0;width:100%}td,th{border:1px solid #d1d5db;padding:.35rem .6rem;text-align:left}th{background:#f3f4f6}'
            : 'body{background:#f3f4f6;margin:0;padding:24px;font-family:Helvetica,Arial,sans-serif}.page{position:relative;background:#fff;margin:0 auto 24px;box-shadow:0 1px 4px rgba(0,0,0,.15);overflow:hidden}.page span{position:absolute;white-space:pre;line-height:1;color:#111}@media(max-width:700px){body{padding:8px}}';

    const html = `<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>${escapeHtml(baseName(file.name))}</title>\n<style>${css}</style>\n</head>\n<body>\n${body}</body>\n</html>\n`;

    return {
        blob: new Blob([html], { type: 'text/html;charset=utf-8' }),
        filename: outputName(file.name, '', 'html'),
        stats: { pages: pageCount },
    };
}

function layoutPage(lines, viewport, number) {
    let html = `<div class="page" id="page-${number}" style="width:${viewport.width.toFixed(1)}pt;height:${viewport.height.toFixed(1)}pt">`;

    for (const line of lines) {
        for (const item of line.items) {
            const [x, y] = viewport.convertToViewportPoint(item.x, item.y);
            const top = y - item.height * 0.85;
            const dir = line.rtl ? ' dir="rtl"' : '';
            html += `<span${dir} style="left:${x.toFixed(1)}pt;top:${top.toFixed(1)}pt;font-size:${item.height.toFixed(1)}pt">${escapeHtml(item.str)}</span>`;
        }
    }

    return `${html}</div>\n`;
}

function textPage(lines, number) {
    if (!lines.length) return `<section id="page-${number}"></section>\n`;

    const sizes = lines.map((l) => Math.round(l.height)).sort((a, b) => a - b);
    const body = sizes[Math.floor(sizes.length / 2)] || 10;
    let html = `<section id="page-${number}">\n`;
    let paragraph = '';
    let previous = null;

    let table = [];

    const flush = () => {
        if (paragraph.trim()) html += `<p>${escapeHtml(paragraph.trim())}</p>\n`;
        paragraph = '';
    };

    const flushTable = () => {
        if (table.length >= 2) {
            html += '<table>\n';
            alignCells(table).forEach((row, r) => {
                const tag = r === 0 ? 'th' : 'td';
                html += `<tr>${row.map((cell) => `<${tag}>${escapeHtml(cell)}</${tag}>`).join('')}</tr>\n`;
            });
            html += '</table>\n';
        } else if (table.length === 1) {
            html += `<p>${escapeHtml(table[0].map((c) => c.text).join(' '))}</p>\n`;
        }
        table = [];
    };

    for (const line of lines) {
        // Lines with several cells (large gaps) form tables.
        if (line.cells.length >= 2) {
            flush();
            table.push(line.cells);
            previous = line;
            continue;
        }
        flushTable();

        const text = line.text.replace(/\s+/g, ' ').trim();
        const ratio = line.height / body;
        const dir = line.rtl ? ' dir="rtl"' : '';

        if (ratio >= 1.25 && text.length < 160) {
            flush();
            const level = ratio >= 1.8 ? 1 : ratio >= 1.4 ? 2 : 3;
            html += `<h${level}${dir}>${escapeHtml(text)}</h${level}>\n`;
            previous = line;
            continue;
        }

        const gap = previous ? previous.y - line.y : 0;
        if (gap > Math.max(previous?.height || 0, line.height) * 1.9) flush();

        paragraph = paragraph ? (paragraph.endsWith('-') ? paragraph.slice(0, -1) + text : `${paragraph} ${text}`) : text;
        previous = line;
    }

    flush();
    flushTable();
    return `${html}</section>\n`;
}
