/**
 * Turns PDF.js text items into reading-order lines with character offsets,
 * so text can be extracted with sensible spacing and matches can be located
 * on the page.
 */

const RTL = /[֐-ࣿיִ-﷿ﹰ-﻿]/;

// Ligatures and Arabic presentation forms -> base letters (NFKC on these ranges only).
const PRESENTATION_FORMS = /[ﬀ-ﬆﬓ-ﬗיִ-﷿ﹰ-﻿]/g;
export const normalizeText = (text) => text.replace(PRESENTATION_FORMS, (c) => c.normalize('NFKC'));

/**
 * @returns {Promise<Array<{text: string, y: number, height: number, rtl: boolean, items: Array<{str: string, x: number, y: number, width: number, height: number, start: number, end: number}>}>>}
 */
export async function pageLines(page) {
    const content = await page.getTextContent();
    const items = [];

    for (const item of content.items) {
        // Whitespace-only runs are spacing artefacts: geometry decides spacing.
        if (!('str' in item) || item.str.trim() === '') continue;
        const [a, b, c, d, x, y] = item.transform;
        const height = item.height || Math.hypot(c, d) || Math.hypot(a, b) || 10;
        // Skip vertical/rotated runs from the line logic (rare).
        if (Math.abs(b) > Math.abs(a) * 2 && Math.abs(a) < 0.01) continue;
        items.push({ str: normalizeText(item.str), x, y, width: item.width, height, dir: item.dir, eol: item.hasEOL, fontName: item.fontName, family: content.styles?.[item.fontName]?.fontFamily });
    }

    items.sort((p, q) => q.y - p.y || p.x - q.x);

    const lines = [];

    for (const item of items) {
        const line = lines[lines.length - 1];
        if (line && Math.abs(line.y - item.y) <= Math.min(line.height, item.height) * 0.5) {
            line.items.push(item);
            line.height = Math.max(line.height, item.height);
        } else {
            lines.push({ y: item.y, height: item.height, items: [item] });
        }
    }

    for (const line of lines) {
        line.items.sort((p, q) => p.x - q.x);
        const rtlChars = line.items.reduce((n, it) => n + (RTL.test(it.str) ? it.str.length : 0), 0);
        const total = line.items.reduce((n, it) => n + it.str.length, 0);
        line.rtl = rtlChars > total / 2;

        const ordered = line.rtl ? [...line.items].reverse() : line.items;
        let text = '';
        let prev = null;
        const cells = [];

        for (const item of ordered) {
            let newCell = !prev;

            if (prev) {
                const gap = line.rtl ? prev.x - (item.x + item.width) : item.x - (prev.x + prev.width);
                const charWidth = item.width / Math.max(1, item.str.length) || item.height * 0.5;

                // A gap wider than about one em separates table cells.
                newCell = gap > Math.max(item.height * 0.9, charWidth * 2);

                if (gap > charWidth * 0.3 && !text.endsWith(' ') && !item.str.startsWith(' ')) {
                    text += newCell ? ' '.repeat(Math.min(12, Math.max(2, Math.round(gap / charWidth)))) : ' ';
                }
            }

            if (newCell) cells.push({ x0: item.x, x1: item.x + item.width, text: item.str });
            else {
                const cell = cells[cells.length - 1];
                cell.x0 = Math.min(cell.x0, item.x);
                cell.x1 = Math.max(cell.x1, item.x + item.width);
                cell.text += (text.endsWith(' ') ? ' ' : '') + item.str;
            }

            item.start = text.length;
            text += item.str;
            item.end = text.length;
            prev = item;
        }

        line.text = text;
        line.cells = cells.map((c) => ({ ...c, text: c.text.replace(/\s+/g, ' ').trim() }));
    }

    return lines.filter((line) => line.text.trim() !== '');
}

/**
 * Aligns the cells of several lines into table rows. Columns are the merged
 * horizontal extents of all cells, so right-aligned numbers and centred
 * headers land in the same column.
 *
 * @param {Array<Array<{x0: number, x1: number, text: string}>>} cellLines
 * @returns {string[][]}
 */
export function alignCells(cellLines) {
    const spans = cellLines.flat().map((c) => [c.x0, Math.max(c.x0 + 0.1, c.x1)]).sort((a, b) => a[0] - b[0]);
    const columns = [];

    for (const [x0, x1] of spans) {
        const last = columns[columns.length - 1];
        if (last && x0 <= last[1]) last[1] = Math.max(last[1], x1);
        else columns.push([x0, x1]);
    }

    return cellLines.map((cells) => {
        const row = Array(columns.length).fill('');
        for (const cell of cells) {
            let best = 0;
            let bestOverlap = -Infinity;
            columns.forEach(([x0, x1], i) => {
                let overlap = Math.min(x1, cell.x1) - Math.max(x0, cell.x0);
                if (overlap <= 0) overlap = -Math.abs((x0 + x1) / 2 - (cell.x0 + cell.x1) / 2);
                if (overlap > bestOverlap) {
                    bestOverlap = overlap;
                    best = i;
                }
            });
            row[best] = row[best] ? `${row[best]} ${cell.text}` : cell.text;
        }
        return row;
    });
}

/**
 * Style of a PDF.js font: { bold, italic, family: 'helvetica'|'times'|'courier' }.
 */
export async function fontStyle(page, fontName, cssFamily = '') {
    const font = await new Promise((resolve) => {
        const timer = setTimeout(() => resolve(null), 1500);
        try {
            page.commonObjs.get(fontName, (value) => {
                clearTimeout(timer);
                resolve(value);
            });
        } catch {
            clearTimeout(timer);
            resolve(null);
        }
    });

    const name = `${font?.name || ''} ${font?.loadedName || ''}`.toLowerCase();
    const mono = /mono|courier|consol/.test(name) || cssFamily === 'monospace';
    const sans = /sans|arial|helvetica|verdana|calibri/.test(name) || cssFamily === 'sans-serif';
    const serif = !sans && (/times|serif|roman|georgia|garamond|cambria/.test(name) || cssFamily === 'serif');
    const family = mono ? 'courier' : serif ? 'times' : 'helvetica';

    return {
        bold: Boolean(font?.bold || font?.black || /bold|black|heavy|semibold/.test(name)),
        italic: Boolean(font?.italic || /italic|oblique/.test(name)),
        family,
    };
}

/**
 * Plain text of a page. `layout` keeps runs of spaces; otherwise they are
 * collapsed. Paragraphs are separated by a blank line.
 */
export function linesToText(lines, { layout = false } = {}) {
    let out = '';
    let previous = null;

    for (const line of lines) {
        if (previous) {
            const gap = previous.y - line.y;
            out += gap > Math.max(previous.height, line.height) * 1.9 ? '\n\n' : '\n';
        }
        out += layout ? line.text.replace(/\s+$/, '') : line.text.replace(/ {2,}/g, ' ').trim();
        previous = line;
    }

    return out;
}

/**
 * Extracts the text of every page of a PDF.js document.
 */
export async function extractDocumentText(doc, { layout = false, onProgress, signal } = {}) {
    const pages = [];

    for (let i = 1; i <= doc.numPages; i++) {
        if (signal?.aborted) throw new DOMException('Cancelled', 'AbortError');
        const page = await doc.getPage(i);
        pages.push(linesToText(await pageLines(page), { layout }));
        page.cleanup();
        onProgress?.(i, doc.numPages);
    }

    return pages;
}

/**
 * Finds matches of `query` in the lines of a page.
 *
 * @returns {Array<{line: object, start: number, end: number, rects: Array<{x: number, y: number, width: number, height: number}>}>}
 */
export function findInLines(lines, query, { caseSensitive = false, wholeWord = false } = {}) {
    if (!query) return [];

    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+');
    const pattern = new RegExp(wholeWord ? `(?<![\\p{L}\\p{N}])${escaped}(?![\\p{L}\\p{N}])` : escaped, `gu${caseSensitive ? '' : 'i'}`);
    const matches = [];

    for (const line of lines) {
        for (const m of line.text.matchAll(pattern)) {
            const start = m.index;
            const end = start + m[0].length;
            const rects = [];
            const items = [];

            for (const item of line.items) {
                const from = Math.max(start, item.start);
                const to = Math.min(end, item.end);
                if (from >= to) continue;

                const len = Math.max(1, item.end - item.start);
                const x0 = item.x + (item.width * (from - item.start)) / len;
                const x1 = item.x + (item.width * (to - item.start)) / len;
                rects.push({ x: Math.min(x0, x1), y: item.y - item.height * 0.22, width: Math.abs(x1 - x0), height: item.height * 1.18 });
                items.push(item);
            }

            if (rects.length) matches.push({ line, start, end, text: m[0], rects, items });
        }
    }

    return matches;
}
