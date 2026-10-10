/**
 * Direct links (Adsterra / Monetag smartlinks) configured to open in a new tab
 * when a visitor clicks a download button – at most once per configured interval.
 */
const DOWNLOAD_TRIGGERS = [
    '[data-download]',
    '[data-action="download"]',
    '[data-org="download"]',
    '[data-org="extract"]',
    '[data-text-action="txt"]',
    '[data-text-action="docx"]',
    '[data-text-action="pdf"]',
    '[data-tr-action]',
].join(',');

const storageKey = (link) => `pdftk-ad-link-${link.id}`;

function due(link) {
    try {
        const last = Number(localStorage.getItem(storageKey(link)) || 0);
        return Date.now() - last >= link.every * 60_000;
    } catch {
        return true;
    }
}

function remember(link) {
    try {
        localStorage.setItem(storageKey(link), String(Date.now()));
    } catch {
        /* storage blocked – the link may open again next time */
    }
}

export function initAdLinks(links) {
    if (!Array.isArray(links) || links.length === 0) return;

    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest?.(DOWNLOAD_TRIGGERS);
            if (!trigger || trigger.disabled || trigger.getAttribute('aria-disabled') === 'true') return;

            const link = links.find(due);
            if (!link) return;

            remember(link);
            window.open(link.url, '_blank', 'noopener');
        },
        true,
    );
}
