/**
 * Minimal PDF content stream tokenizer / serializer.
 *
 * A content stream is parsed into a list of operations:
 *   { op: 'rg', operands: ['0.2', '0.4', '1'] }
 * Operand values keep their exact source text, so untouched operations are
 * written back byte-for-byte. Inline images (BI … ID … EI) are kept as raw
 * data.
 */

const WHITESPACE = new Set([0x00, 0x09, 0x0a, 0x0c, 0x0d, 0x20]);
const DELIMITERS = new Set([0x28, 0x29, 0x3c, 0x3e, 0x5b, 0x5d, 0x7b, 0x7d, 0x2f, 0x25]);

export function bytesToBinaryString(bytes) {
    let out = '';
    for (let i = 0; i < bytes.length; i += 0x8000) {
        out += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }
    return out;
}

export function binaryStringToBytes(text) {
    const bytes = new Uint8Array(text.length);
    for (let i = 0; i < text.length; i++) bytes[i] = text.charCodeAt(i) & 0xff;
    return bytes;
}

export function parseContent(bytes) {
    const src = typeof bytes === 'string' ? bytes : bytesToBinaryString(bytes);
    const n = src.length;
    const ops = [];
    let operands = [];
    let i = 0;

    const code = (k) => src.charCodeAt(k);

    while (i < n) {
        const c = code(i);

        if (WHITESPACE.has(c)) {
            i++;
            continue;
        }

        // Comment
        if (c === 0x25) {
            while (i < n && code(i) !== 0x0a && code(i) !== 0x0d) i++;
            continue;
        }

        const start = i;

        if (c === 0x28) {
            // Literal string with balanced parentheses and escapes.
            let depth = 0;
            for (; i < n; i++) {
                const ch = code(i);
                if (ch === 0x5c) {
                    i++;
                    continue;
                }
                if (ch === 0x28) depth++;
                else if (ch === 0x29 && --depth === 0) {
                    i++;
                    break;
                }
            }
            operands.push(src.slice(start, i));
            continue;
        }

        if (c === 0x3c && code(i + 1) === 0x3c) {
            i = skipDictionary(src, i);
            operands.push(src.slice(start, i));
            continue;
        }

        if (c === 0x3c) {
            i = src.indexOf('>', i) + 1 || n;
            operands.push(src.slice(start, i));
            continue;
        }

        if (c === 0x5b) {
            i = skipArray(src, i);
            operands.push(src.slice(start, i));
            continue;
        }

        if (c === 0x2f) {
            i++;
            while (i < n && !WHITESPACE.has(code(i)) && !DELIMITERS.has(code(i))) i++;
            operands.push(src.slice(start, i));
            continue;
        }

        // Number or operator keyword.
        while (i < n && !WHITESPACE.has(code(i)) && !DELIMITERS.has(code(i))) i++;
        if (i === start) {
            i++; // stray delimiter such as ')' or '}' – skip it
            continue;
        }

        const word = src.slice(start, i);

        if (/^[+-]?(\d+\.?\d*|\.\d+)$/.test(word) || word === 'true' || word === 'false' || word === 'null') {
            operands.push(word);
            continue;
        }

        if (word === 'BI') {
            // Inline image: keep "BI ... ID <data> EI" verbatim.
            const idPos = findKeyword(src, 'ID', i);
            let dataStart = idPos + 2;
            if (WHITESPACE.has(code(dataStart))) dataStart++;
            const eiPos = findInlineImageEnd(src, dataStart);
            ops.push({ op: 'BI', operands: [], raw: src.slice(start, eiPos + 2) });
            i = eiPos + 2;
            operands = [];
            continue;
        }

        ops.push({ op: word, operands });
        operands = [];
    }

    return ops;
}

function skipDictionary(src, i) {
    let depth = 0;
    while (i < src.length) {
        if (src[i] === '<' && src[i + 1] === '<') {
            depth++;
            i += 2;
        } else if (src[i] === '>' && src[i + 1] === '>') {
            depth--;
            i += 2;
            if (depth === 0) return i;
        } else if (src[i] === '(') {
            i = skipString(src, i);
        } else {
            i++;
        }
    }
    return i;
}

function skipArray(src, i) {
    let depth = 0;
    while (i < src.length) {
        const ch = src[i];
        if (ch === '(') {
            i = skipString(src, i);
            continue;
        }
        if (ch === '[') depth++;
        else if (ch === ']' && --depth === 0) return i + 1;
        i++;
    }
    return i;
}

function skipString(src, i) {
    let depth = 0;
    for (; i < src.length; i++) {
        if (src[i] === '\\') {
            i++;
            continue;
        }
        if (src[i] === '(') depth++;
        else if (src[i] === ')' && --depth === 0) return i + 1;
    }
    return i;
}

function findKeyword(src, keyword, from) {
    for (let i = from; i < src.length - 1; i++) {
        if (src.startsWith(keyword, i) && (i === 0 || WHITESPACE.has(src.charCodeAt(i - 1))) && (WHITESPACE.has(src.charCodeAt(i + keyword.length)) || i + keyword.length >= src.length)) {
            return i;
        }
    }
    return src.length;
}

function findInlineImageEnd(src, from) {
    for (let i = from; i < src.length - 1; i++) {
        if (src[i] === 'E' && src[i + 1] === 'I' && WHITESPACE.has(src.charCodeAt(i - 1)) && (i + 2 >= src.length || WHITESPACE.has(src.charCodeAt(i + 2)))) {
            return i;
        }
    }
    return src.length - 2;
}

export function serializeContent(ops) {
    let out = '';
    for (const { op, operands, raw } of ops) {
        if (raw !== undefined) {
            out += `${raw}\n`;
            continue;
        }
        out += operands.length ? `${operands.join(' ')} ${op}\n` : `${op}\n`;
    }
    return binaryStringToBytes(out);
}

/** Decodes a PDF literal or hex string operand into a binary string. */
export function decodePdfString(token) {
    if (token.startsWith('<')) {
        const hex = token.slice(1, -1).replace(/\s+/g, '');
        let out = '';
        for (let i = 0; i < hex.length; i += 2) out += String.fromCharCode(parseInt(hex.slice(i, i + 2).padEnd(2, '0'), 16));
        return out;
    }

    const body = token.slice(1, -1);
    let out = '';

    for (let i = 0; i < body.length; i++) {
        const ch = body[i];
        if (ch !== '\\') {
            out += ch;
            continue;
        }

        const next = body[++i];
        const escapes = { n: '\n', r: '\r', t: '\t', b: '\b', f: '\f', '(': '(', ')': ')', '\\': '\\' };

        if (next in escapes) out += escapes[next];
        else if (/[0-7]/.test(next)) {
            let octal = next;
            while (octal.length < 3 && /[0-7]/.test(body[i + 1])) octal += body[++i];
            out += String.fromCharCode(parseInt(octal, 8));
        } else if (next === '\r' || next === '\n') {
            if (next === '\r' && body[i + 1] === '\n') i++;
        } else if (next !== undefined) out += next;
    }

    return out;
}

/** Text of a text-showing operation (Tj, TJ, ', "), as raw bytes. */
export function textOf(operation) {
    const { op, operands } = operation;

    if (op === 'Tj' || op === "'") return decodePdfString(operands[operands.length - 1] || '()');
    if (op === '"') return decodePdfString(operands[2] || '()');

    if (op === 'TJ') {
        const array = operands[0] || '[]';
        const parts = [];
        const re = /\((?:\\.|[^\\)])*\)|<[0-9A-Fa-f\s]*>/g;
        let m;
        while ((m = re.exec(array))) parts.push(decodePdfString(m[0]));
        return parts.join('');
    }

    return '';
}
