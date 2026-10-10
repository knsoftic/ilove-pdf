/**
 * Runtime configuration rendered by the Blade layout (window.PDFTK).
 */
export const config = window.PDFTK || { base: '', csrf: '', vendor: '/vendor', routes: {}, ocr: {} };

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || config.csrf || '';
}

export function vendorUrl(path) {
    return `${config.vendor.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
}
