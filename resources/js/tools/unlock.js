import { openDocument, saveDocument, pdfBlob } from '../lib/pdf-tools';
import { outputName } from '../lib/files';
import { ToolError } from '../lib/errors';

/**
 * Removes the password / restrictions of a PDF (the correct password is
 * required when the file needs one to open).
 */
export async function run({ files, options, progress }) {
    const file = files[0];
    progress(20, 'Opening PDF…');

    const { doc, encrypted } = await openDocument(file, { password: options.password || undefined });

    if (!encrypted) {
        throw new ToolError('nothing_found', 'This PDF is not password protected – there is nothing to unlock.');
    }

    progress(70, 'Saving unlocked PDF…');
    const bytes = await saveDocument(doc);

    return {
        blob: pdfBlob(bytes),
        filename: outputName(file.name, 'unlocked', 'pdf'),
        stats: { pages: doc.getPageCount() },
    };
}
