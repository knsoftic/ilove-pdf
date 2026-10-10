import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { outputName } from '../lib/files';

function randomPassword() {
    const bytes = crypto.getRandomValues(new Uint8Array(24));
    return [...bytes].map((b) => b.toString(36).padStart(2, '0')).join('').slice(0, 32);
}

/**
 * Password-protects a PDF with AES-256 (or AES-128) in the browser.
 */
export async function run({ files, options, progress }) {
    const file = files[0];
    const { doc } = await openDocument(file);
    const algorithm = options.algorithm === 'AES-128' ? 'AES-128' : 'AES-256';
    const modifying = Boolean(options.allow_modifying);

    progress(40, 'Encrypting…');

    doc.encrypt({
        userPassword: String(options.password || ''),
        ownerPassword: options.owner_password ? String(options.owner_password) : randomPassword(),
        algorithm,
        permissions: {
            printing: options.allow_printing === false ? false : 'highResolution',
            copying: Boolean(options.allow_copying),
            modifying,
            annotating: modifying,
            fillingForms: options.allow_forms ?? modifying,
            contentAccessibility: true,
            documentAssembly: Boolean(options.allow_assembly),
        },
    });

    progress(80, 'Saving protected PDF…');
    const bytes = await saveDocument(doc, { useObjectStreams: false });

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'protected', 'pdf'),
        stats: { pages: doc.getPageCount(), encryption: algorithm },
    };
}
