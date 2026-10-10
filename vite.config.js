import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { cpSync, existsSync, mkdirSync, statSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

/**
 * Copies runtime assets that are loaded by URL (not bundled) into
 * public/vendor, so the site never depends on a third-party CDN for them:
 * PDF.js character maps, fonts, wasm decoders and ICC profiles, the
 * Tesseract OCR worker/core, and DejaVu fonts for Unicode text in PDFs.
 */
function copyVendorAssets() {
    const copies = [
        ['node_modules/pdfjs-dist/cmaps', 'public/vendor/pdfjs/cmaps'],
        ['node_modules/pdfjs-dist/standard_fonts', 'public/vendor/pdfjs/standard_fonts'],
        ['node_modules/pdfjs-dist/wasm', 'public/vendor/pdfjs/wasm'],
        ['node_modules/pdfjs-dist/iccs', 'public/vendor/pdfjs/iccs'],
        ['node_modules/tesseract.js/dist/worker.min.js', 'public/vendor/tesseract/worker.min.js'],
        ['node_modules/tesseract.js-core/tesseract-core-lstm.wasm.js', 'public/vendor/tesseract/core/tesseract-core-lstm.wasm.js'],
        ['node_modules/tesseract.js-core/tesseract-core-simd-lstm.wasm.js', 'public/vendor/tesseract/core/tesseract-core-simd-lstm.wasm.js'],
        ['node_modules/tesseract.js-core/tesseract-core-relaxedsimd-lstm.wasm.js', 'public/vendor/tesseract/core/tesseract-core-relaxedsimd-lstm.wasm.js'],
        ['vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf', 'public/vendor/fonts/DejaVuSans.ttf'],
        ['vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf', 'public/vendor/fonts/DejaVuSans-Bold.ttf'],
    ];

    return {
        name: 'pdftk-copy-vendor-assets',
        buildStart() {
            for (const [from, to] of copies) {
                const source = resolve(from);
                const target = resolve(to);

                if (!existsSync(source)) {
                    this.warn(`Skipping missing asset ${from}`);
                    continue;
                }

                if (existsSync(target) && statSync(source).isFile() && statSync(target).mtimeMs >= statSync(source).mtimeMs) {
                    continue;
                }

                mkdirSync(dirname(target), { recursive: true });
                cpSync(source, target, { recursive: true, force: true });
            }
        },
    };
}

export default defineConfig({
    plugins: [
        copyVendorAssets(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // PDF.js and Fabric are large but loaded on demand only.
        chunkSizeWarningLimit: 1600,
        target: 'es2022',
        rollupOptions: {
            output: {
                // Emit the PDF.js worker (.mjs) as .js: many servers (e.g. Nginx's
                // default mime.types) send .mjs as application/octet-stream, which
                // browsers refuse to run as a module.
                assetFileNames: (asset) =>
                    (asset.names?.[0] ?? asset.name ?? '').endsWith('.mjs') ? 'assets/[name]-[hash].js' : 'assets/[name]-[hash][extname]',
            },
        },
    },
    worker: {
        format: 'es',
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/public/vendor/**'],
        },
    },
});
