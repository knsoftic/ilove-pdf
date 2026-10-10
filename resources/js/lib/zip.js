/**
 * Packs several generated files into one ZIP (JSZip, MIT).
 *
 * @param {{name: string, blob: Blob}[]} files
 */
export async function zipFiles(files, onProgress) {
    const { default: JSZip } = await import('jszip');
    const zip = new JSZip();
    const used = new Set();

    for (const { name, blob } of files) {
        let entry = name;
        let i = 2;
        while (used.has(entry.toLowerCase())) {
            entry = name.replace(/(\.[^.]+)?$/, `-${i++}$1`);
        }
        used.add(entry.toLowerCase());

        // Images and PDFs are already compressed.
        const store = /\.(jpe?g|png|webp|zip|docx|xlsx|pptx)$/i.test(entry);
        zip.file(entry, blob, { compression: store ? 'STORE' : 'DEFLATE' });
    }

    return zip.generateAsync({ type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } }, (meta) => onProgress?.(meta.percent / 100));
}
