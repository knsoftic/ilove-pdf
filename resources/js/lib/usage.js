import { csrfToken } from './config';

/**
 * Anonymous usage counter for in-browser tasks (tool name only).
 */
export function recordUsage(url) {
    if (!url) return;

    try {
        const data = new FormData();
        data.append('_token', csrfToken());

        if (!navigator.sendBeacon?.(url, data)) {
            fetch(url, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(() => {});
        }
    } catch {
        /* ignore */
    }
}
