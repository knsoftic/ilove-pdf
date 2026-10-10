/**
 * Passes files dropped on one page (e.g. the home page) to a tool page
 * without uploading them anywhere: they are kept for a moment in the
 * browser's own IndexedDB.
 */
const DB = 'pdftk-handoff';
const STORE = 'files';

function open() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function stashFiles(files) {
    try {
        const db = await open();
        const payload = await Promise.all(files.map(async (file) => ({ name: file.name, type: file.type, data: await file.arrayBuffer(), lastModified: file.lastModified })));

        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).put({ files: payload, at: Date.now() }, 'pending');
            tx.oncomplete = resolve;
            tx.onerror = () => reject(tx.error);
        });
        db.close();
        return true;
    } catch {
        return false;
    }
}

export async function takeFiles() {
    try {
        const db = await open();
        const record = await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            const store = tx.objectStore(STORE);
            const get = store.get('pending');
            get.onsuccess = () => {
                store.delete('pending');
                resolve(get.result);
            };
            tx.onerror = () => reject(tx.error);
        });
        db.close();

        // Ignore stale hand-offs (older than 10 minutes).
        if (!record || Date.now() - record.at > 600_000) return [];

        return record.files.map((f) => new File([f.data], f.name, { type: f.type, lastModified: f.lastModified }));
    } catch {
        return [];
    }
}
