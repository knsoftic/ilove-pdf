import { ToolError } from './errors';

/**
 * Page selection parsing, identical to App\Helpers\PageRange on the server.
 * Supports "7", "2-5", "5-", "5-end", "-3" and "last". 1-based.
 *
 * @returns {Array<[number, number]>}
 */
export function parseRanges(input, pageCount) {
    const value = String(input ?? '').trim();
    if (value === '') return [[1, pageCount]];

    const ranges = [];

    for (let token of value.split(/[,;]+/)) {
        token = token.replace(/\s+/g, '').toLowerCase().replace(/end|last/g, String(pageCount)).replace(/[–—]/g, '-');
        if (!token) continue;

        let start;
        let end;
        let m;

        if ((m = /^(\d+)$/.exec(token))) {
            start = end = Number(m[1]);
        } else if ((m = /^(\d*)-(\d*)$/.exec(token)) && (m[1] !== '' || m[2] !== '')) {
            start = m[1] === '' ? 1 : Number(m[1]);
            end = m[2] === '' ? pageCount : Number(m[2]);
        } else {
            throw new ToolError('invalid_page_range', `"${token}" is not a valid page or page range.`);
        }

        if (start < 1 || end < 1 || start > pageCount || end > pageCount) {
            throw new ToolError('invalid_page_range', `Page selection "${token}" is outside this document (1–${pageCount}).`);
        }

        ranges.push(start <= end ? [start, end] : [end, start]);
    }

    if (!ranges.length) throw new ToolError('invalid_page_range');
    return ranges;
}

/** Unique 1-based page numbers in the given order. */
export function parsePages(input, pageCount) {
    const pages = new Set();
    for (const [start, end] of parseRanges(input, pageCount)) {
        for (let p = start; p <= end; p++) pages.add(p);
    }
    return [...pages];
}

/** [1,2,3,5,7,8] => "1-3, 5, 7-8" */
export function formatPages(pages) {
    const sorted = [...new Set(pages)].sort((a, b) => a - b);
    const parts = [];
    let start = null;
    let prev = null;

    for (const page of sorted) {
        if (start === null) {
            start = prev = page;
        } else if (page === prev + 1) {
            prev = page;
        } else {
            parts.push(start === prev ? `${start}` : `${start}-${prev}`);
            start = prev = page;
        }
    }

    if (start !== null) parts.push(start === prev ? `${start}` : `${start}-${prev}`);
    return parts.join(', ');
}
