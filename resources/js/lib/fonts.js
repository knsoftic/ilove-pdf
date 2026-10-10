import { vendorUrl } from './config';
import { pdfLib } from './pdf';

/**
 * Fonts for text drawn into PDFs in the browser.
 *
 * The 14 standard PDF fonts only cover Western European characters
 * (WinAnsi). For anything else (Cyrillic, Greek, Arabic, Turkish, Polish …)
 * the open-source DejaVu Sans font is embedded (subset) with fontkit.
 */
const cache = new Map();
let fontkitPromise = null;
let fontBytes = {};

const WIN_ANSI_EXTRA = new Set([
    0x20ac, 0x201a, 0x0192, 0x201e, 0x2026, 0x2020, 0x2021, 0x02c6, 0x2030, 0x0160, 0x2039, 0x0152, 0x017d, 0x2018, 0x2019, 0x201c, 0x201d, 0x2022, 0x2013, 0x2014, 0x02dc, 0x2122, 0x0161, 0x203a, 0x0153, 0x017e, 0x0178,
]);

export function isWinAnsi(text) {
    for (const ch of String(text)) {
        const code = ch.codePointAt(0);
        if (code === 10 || code === 13 || code === 9) continue;
        if ((code >= 0x20 && code <= 0x7e) || (code >= 0xa0 && code <= 0xff) || WIN_ANSI_EXTRA.has(code)) continue;
        return false;
    }
    return true;
}

async function unicodeFontBytes(bold) {
    const name = bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';
    fontBytes[name] ??= fetch(vendorUrl(`fonts/${name}`)).then((r) => {
        if (!r.ok) throw new Error('Font not available');
        return r.arrayBuffer();
    });
    return fontBytes[name];
}

/**
 * Returns an embedded font able to render `text` (cached per document).
 *
 * @param {'helvetica'|'times'|'courier'} family
 */
export async function fontFor(doc, text, { family = 'helvetica', bold = false, italic = false } = {}) {
    const { StandardFonts } = await pdfLib();
    const unicode = !isWinAnsi(text);
    const key = unicode ? `unicode-${bold}` : `${family}-${bold}-${italic}`;

    let docCache = cache.get(doc);
    if (!docCache) cache.set(doc, (docCache = new Map()));
    if (docCache.has(key)) return docCache.get(key);

    let fontPromise;

    if (unicode) {
        fontPromise = (async () => {
            fontkitPromise ??= import('@cantoo/fontkit').then((m) => m.default || m);
            doc.registerFontkit(await fontkitPromise);
            return doc.embedFont(await unicodeFontBytes(bold), { subset: true });
        })();
    } else {
        const map = {
            helvetica: [StandardFonts.Helvetica, StandardFonts.HelveticaBold, StandardFonts.HelveticaOblique, StandardFonts.HelveticaBoldOblique],
            times: [StandardFonts.TimesRoman, StandardFonts.TimesRomanBold, StandardFonts.TimesRomanItalic, StandardFonts.TimesRomanBoldItalic],
            courier: [StandardFonts.Courier, StandardFonts.CourierBold, StandardFonts.CourierOblique, StandardFonts.CourierBoldOblique],
        }[family] || [StandardFonts.Helvetica, StandardFonts.HelveticaBold, StandardFonts.HelveticaOblique, StandardFonts.HelveticaBoldOblique];

        fontPromise = doc.embedFont(map[(bold ? 1 : 0) + (italic ? 2 : 0)]);
    }

    docCache.set(key, fontPromise);
    return fontPromise;
}
