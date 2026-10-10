import { csrfToken } from './config';
import { ToolError } from './errors';
import { sleep } from './dom';

function errorFrom(status, body) {
    if (body?.error?.code) return new ToolError(body.error.code, body.error.message);
    if (status === 413) return new ToolError('file_too_large');
    if (status === 419) return new ToolError('server_error', 'Your session has expired. Please reload the page and try again.');
    if (status === 429) return new ToolError('rate_limited');
    return new ToolError('server_error');
}

/**
 * Multipart upload with progress (fetch cannot report upload progress).
 */
export function upload(url, formData, { onProgress, signal } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.responseType = 'json';

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && onProgress) onProgress(event.loaded / event.total);
        };
        xhr.onload = () => {
            const body = xhr.response;
            if (xhr.status >= 200 && xhr.status < 300) resolve(body);
            else reject(errorFrom(xhr.status, body));
        };
        xhr.onerror = () => reject(new ToolError('network'));
        xhr.onabort = () => reject(new ToolError('cancelled'));

        signal?.addEventListener('abort', () => xhr.abort(), { once: true });
        xhr.send(formData);
    });
}

async function request(url, { method = 'GET', body, signal, accept = 'application/json' } = {}) {
    let response;

    try {
        response = await fetch(url, {
            method,
            signal,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: accept,
                ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body && !(body instanceof FormData) ? JSON.stringify(body) : body,
        });
    } catch (error) {
        if (error.name === 'AbortError') throw new ToolError('cancelled');
        throw new ToolError('network');
    }

    if (!response.ok) {
        let data = null;
        try {
            data = await response.json();
        } catch {
            /* not JSON */
        }
        throw errorFrom(response.status, data);
    }

    return response;
}

export async function getJson(url, options = {}) {
    return (await request(url, options)).json();
}

export async function postJson(url, body, options = {}) {
    return (await request(url, { ...options, method: 'POST', body })).json();
}

export async function sendDelete(url) {
    try {
        await request(url, { method: 'DELETE' });
    } catch {
        /* best effort */
    }
}

/**
 * POST and receive a file. Returns { blob, filename }.
 */
export async function postForBlob(url, body, options = {}) {
    const response = await request(url, { ...options, method: 'POST', body, accept: '*/*' });
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = /filename\*=UTF-8''([^;]+)/i.exec(disposition) || /filename="?([^";]+)"?/i.exec(disposition);

    return { blob: await response.blob(), filename: match ? decodeURIComponent(match[1]) : 'download' };
}

/**
 * Polls a server job until it is done or failed.
 */
export async function pollJob(job, { onProgress, signal, interval = 700 } = {}) {
    let current = job;
    let delay = interval;

    while (current.status === 'queued' || current.status === 'processing') {
        onProgress?.(current);
        await sleep(delay);
        if (signal?.aborted) throw new ToolError('cancelled');
        current = await getJson(current.status_url, { signal });
        delay = Math.min(delay * 1.15, 2500);
    }

    if (current.status === 'failed') {
        throw new ToolError(current.error?.code || 'server_error', current.error?.message);
    }

    onProgress?.(current);
    return current;
}
