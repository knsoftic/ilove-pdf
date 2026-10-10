import { pdfLib } from '../lib/pdf';
import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { stripPrivateData } from '../lib/pdf-images';
import { outputName } from '../lib/files';
import { $, $$ } from '../lib/dom';

const FIELDS = ['title', 'author', 'subject', 'keywords', 'creator', 'producer'];

function formatDate(date) {
    return date instanceof Date && !Number.isNaN(date.getTime()) ? date.toLocaleString() : '—';
}

/**
 * Shows the current metadata of the uploaded PDF in the form.
 */
export async function onFiles({ files, root }) {
    if (!files[0]) return;

    const { doc } = await openDocument(files[0]);
    const values = {
        title: doc.getTitle() || '',
        author: doc.getAuthor() || '',
        subject: doc.getSubject() || '',
        keywords: doc.getKeywords() || '',
        creator: doc.getCreator() || '',
        producer: doc.getProducer() || '',
    };

    for (const input of $$('[data-meta]', root)) {
        input.value = values[input.dataset.meta] ?? '';
    }

    const dates = $('[data-meta-dates]', root);
    if (dates) {
        dates.textContent = `Created: ${formatDate(doc.getCreationDate())} · Modified: ${formatDate(doc.getModificationDate())}`;
    }

    // Show what is currently stored even in "remove" mode.
    const summary = Object.entries(values).filter(([, v]) => v).map(([k, v]) => `${k[0].toUpperCase()}${k.slice(1)}: ${v}`);
    const panel = $('[data-options-panel]', root);
    let box = $('[data-meta-summary]', root);

    if (panel && !box) {
        box = document.createElement('div');
        box.dataset.metaSummary = '';
        box.className = 'mb-5 rounded-xl bg-slate-100 p-3 text-xs leading-5 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
        panel.prepend(box);
    }

    if (box) {
        box.innerHTML = '';
        const title = document.createElement('p');
        title.className = 'mb-1 font-semibold text-slate-900 dark:text-white';
        title.textContent = 'Current metadata';
        box.append(title);
        const lines = summary.length ? summary : ['This PDF has no document information.'];
        for (const line of [...lines, `Created: ${formatDate(doc.getCreationDate())}`, `Modified: ${formatDate(doc.getModificationDate())}`]) {
            const p = document.createElement('p');
            p.className = 'truncate';
            p.textContent = line;
            box.append(p);
        }
    }
}

export function reset({ root }) {
    $('[data-meta-summary]', root)?.remove();
}

/**
 * Removes or edits document information and XMP metadata.
 */
export async function run({ files, options, progress }) {
    const { PDFName } = await pdfLib();
    const file = files[0];
    const { doc } = await openDocument(file);
    const mode = options.mode || 'remove';

    progress(40, mode === 'remove' ? 'Removing metadata…' : 'Saving metadata…');

    if (mode === 'remove') {
        const info = doc.getInfoDict();
        for (const key of info.keys()) info.delete(key);
        await stripPrivateData(doc, { metadata: true });
        doc.catalog.delete(PDFName.of('Metadata'));
    } else {
        const meta = options.metadata || {};
        doc.setTitle(meta.title || '');
        doc.setAuthor(meta.author || '');
        doc.setSubject(meta.subject || '');
        doc.setKeywords(String(meta.keywords || '').split(/[,;]/).map((k) => k.trim()).filter(Boolean));
        doc.setCreator(meta.creator || '');
        doc.setProducer(meta.producer || '');
        doc.setModificationDate(new Date());

        // Drop empty entries instead of storing empty strings.
        const info = doc.getInfoDict();
        for (const key of FIELDS.map((f) => PDFName.of(f[0].toUpperCase() + f.slice(1)))) {
            const value = info.lookup(key);
            if (value && String(value.decodeText?.() ?? '').trim() === '') info.delete(key);
        }
    }

    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, mode === 'remove' ? 'clean' : 'metadata', 'pdf'),
        title: mode === 'remove' ? 'Metadata removed!' : 'Metadata saved!',
        stats: { pages: doc.getPageCount() },
    };
}
