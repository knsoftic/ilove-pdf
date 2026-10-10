import { $, $$, show } from '../lib/dom';
import { validateFiles, download, outputName } from '../lib/files';
import { postJson, postForBlob } from '../lib/http';
import { config as appConfig } from '../lib/config';
import { extractPdfText, ocrPages, chunkText } from '../lib/extract-pipeline';
import { takeFiles } from '../lib/handoff';
import { toast } from '../lib/toast';
import { recordUsage } from '../lib/usage';
import { ToolError, toToolError } from '../lib/errors';

/** Rejects with `error` when the promise does not settle in time (or is cancelled). */
function withTimeout(promise, ms, error, signal = currentSignal) {
    let timer;
    const cancelled = new Promise((_, reject) => signal?.addEventListener('abort', () => reject(new ToolError('cancelled')), { once: true }));
    return Promise.race([promise, cancelled, new Promise((_, reject) => (timer = setTimeout(() => reject(error), ms)))]).finally(() => clearTimeout(timer));
}

let currentSignal = null;

/**
 * PDF translator: extract text -> detect language -> translate in chunks
 * (self-hosted LibreTranslate/Argos on the server, or the browser's own
 * on-device translator) -> edit -> download TXT / DOCX / PDF.
 */
export default async function initTranslator() {
    const root = $('#translator');
    if (!root) return;

    const config = JSON.parse(root.dataset.config);
    const translation = JSON.parse(root.dataset.translation);
    const languages = translation.languages;
    const original = $('[data-tr-original]', root);
    const output = $('[data-tr-output]', root);
    const source = $('[data-tr-source]', root);
    const target = $('[data-tr-target]', root);

    let file = null;
    let controller = null;
    let detected = null;

    const browserTranslator = typeof self !== 'undefined' && 'Translator' in self;
    const engine = () => (translation.server ? 'server' : browserTranslator ? 'browser' : null);

    const view = (name) => {
        show($('[data-tr-upload]', root), name === 'upload');
        show($('[data-tr-progress]', root), name === 'progress');
        show($('[data-tr-workspace]', root), name === 'workspace');
    };

    function setProgress(percent, message, title) {
        if (title) $('[data-progress-title]', root).textContent = title;
        $('[data-progress-message]', root).textContent = message || '';
        const bar = $('[data-progress-bar]', root);
        if (percent === null || percent === undefined) {
            bar.classList.add('indeterminate');
            $('[data-progress-percent]', root).textContent = '';
        } else {
            bar.classList.remove('indeterminate');
            bar.style.width = `${Math.round(percent)}%`;
            $('[data-progress-percent]', root).textContent = `${Math.round(percent)}%`;
        }
    }

    function describeEngine() {
        const label = $('[data-tr-engine]', root);
        const e = engine();
        label.textContent = e === 'server' ? `Engine: ${translation.engine} (open source)` : e === 'browser' ? "Engine: your browser's on-device translator" : 'No translation engine available in this browser.';
        $('[data-tr-translate]', root).disabled = !e;
    }

    function stats() {
        const words = (original.value.match(/\S+/g) || []).length;
        $('[data-tr-original-stats]', root).textContent = `${words.toLocaleString()} words`;
    }

    function applyDirection() {
        output.dir = languages[target.value]?.rtl ? 'rtl' : 'ltr';
        output.style.fontSize = ['ur', 'ar', 'fa'].includes(target.value) ? '1.05rem' : '';
    }

    async function detectLanguage(text) {
        try {
            const result = await postJson(appConfig.routes.detect, { text: text.slice(0, 5000) });
            detected = result.language;
        } catch {
            detected = null;
        }

        const hint = $('[data-tr-detected]', root);
        if (detected && languages[detected]) {
            hint.textContent = `Detected: ${languages[detected].name}`;
            source.value = detected;
            if (target.value === detected) target.value = detected === 'en' ? 'ur' : 'en';
        } else {
            hint.textContent = 'Language could not be detected – please choose it.';
        }
        applyDirection();
    }

    async function process(files) {
        const { valid, errors } = await validateFiles(files.slice(0, 1), { extensions: ['pdf'], maxBytes: config.maxBytes, label: 'PDF' });
        errors.forEach((e) => toast(e.message, 'error', 5000));
        if (!valid.length) return;

        file = valid[0];
        controller = new AbortController();
        view('progress');
        setProgress(0, 'Starting…', 'Extracting text…');

        try {
            const extractEngine = $('[data-engine]:checked', root)?.value || config.engines[0] || 'browser';
            const result = await extractPdfText(file, { engine: extractEngine, config, signal: controller.signal, progress: setProgress });
            const pages = result.pages;

            if (result.emptyPages.length) {
                setProgress(null, 'Loading the OCR engine…', 'Recognising scanned pages (OCR)…');
                const ocr = await ocrPages(file, result.emptyPages, { language: $('[data-tr-ocr]', root).value, signal: controller.signal, progress: setProgress });
                for (const [number, text] of ocr) pages[number - 1] = text;
            }

            const text = pages.join('\n\n').trim();
            if (!text) throw new ToolError('nothing_found', 'No text could be found in this PDF.');

            original.value = text;
            output.value = '';
            stats();
            await detectLanguage(text);
            describeEngine();
            view('workspace');
        } catch (error) {
            const e = toToolError(error);
            view('upload');
            if (e.code !== 'cancelled') toast(e.message, 'error', 6000);
        }
    }

    async function translate() {
        const text = original.value.trim();
        if (!text) return toast('There is no text to translate.', 'error');

        const from = source.value === 'auto' ? detected || 'auto' : source.value;
        const to = target.value;

        if (from === to) return toast('Please choose a different target language.', 'error');

        const mode = engine();
        if (!mode) return toast('No translation engine is available in this browser.', 'error', 6000);

        const chunks = chunkText(text, 3500);
        controller = new AbortController();
        currentSignal = controller.signal;
        view('progress');
        setProgress(0, `Part 1 of ${chunks.length}`, `Translating to ${languages[to]?.name || to}…`);

        const parts = [];

        try {
            if (mode === 'server') {
                for (const [i, chunk] of chunks.entries()) {
                    if (controller.signal.aborted) throw new ToolError('cancelled');
                    const { translation: translated } = await postJson(appConfig.routes.translate, { text: chunk, source: from, target: to, first: i === 0 }, { signal: controller.signal });
                    parts.push(translated);
                    setProgress(((i + 1) / chunks.length) * 100, `Part ${Math.min(i + 2, chunks.length)} of ${chunks.length}`);
                }
            } else {
                if (from === 'auto') throw new ToolError('translation_failed', 'Please choose the language of the document.');

                const unresponsive = new ToolError('translation_failed', "Your browser's translator is not responding. Please try Chrome on a desktop computer, or ask the site owner to connect a LibreTranslate server.");

                setProgress(null, 'Checking the on-device translator…');
                const availability = await withTimeout(self.Translator.availability({ sourceLanguage: from, targetLanguage: to }), 20_000, unresponsive);

                if (availability === 'unavailable') {
                    throw new ToolError('translation_failed', `Your browser cannot translate from ${languages[from]?.name} to ${languages[to]?.name}.`);
                }

                if (availability !== 'available') {
                    setProgress(null, 'Downloading the language pack (one time only)…');
                }

                const translator = await withTimeout(
                    self.Translator.create({
                        sourceLanguage: from,
                        targetLanguage: to,
                        signal: controller.signal,
                        monitor: (m) => m.addEventListener('downloadprogress', (e) => setProgress(e.loaded * 100, 'Downloading the language pack (one time only)…')),
                    }),
                    300_000,
                    unresponsive,
                );

                for (const [i, chunk] of chunks.entries()) {
                    if (controller.signal.aborted) throw new ToolError('cancelled');
                    parts.push(await withTimeout(translator.translate(chunk, { signal: controller.signal }), 120_000, unresponsive));
                    setProgress(((i + 1) / chunks.length) * 100, `Part ${Math.min(i + 2, chunks.length)} of ${chunks.length}`);
                }
                translator.destroy?.();
                recordUsage(config.endpoints.usage);
            }

            output.value = parts.join('');
            applyDirection();
            toast('Translation finished.', 'success');
        } catch (error) {
            const e = toToolError(error);
            if (e.code !== 'cancelled') toast(e.message, 'error', 7000);
        } finally {
            view('workspace');
        }
    }

    async function exportAs(format) {
        const text = output.value.trim();
        if (!text) return toast('Translate the document first.', 'error');

        const name = outputName(file?.name || 'translation', target.value, format);

        if (format === 'txt') {
            download(new Blob(['﻿', text], { type: 'text/plain;charset=utf-8' }), name);
            return;
        }

        try {
            const { blob } = await postForBlob(appConfig.routes.exportText, { text, format, filename: name, lang: target.value });
            download(blob, name);
        } catch (error) {
            toast(toToolError(error).message, 'error');
        }
    }

    $('[data-tr-translate]', root).addEventListener('click', translate);
    $('[data-tr-cancel]', root).addEventListener('click', () => controller?.abort());
    $('[data-tr-restart]', root).addEventListener('click', () => {
        original.value = '';
        output.value = '';
        view('upload');
    });
    $('[data-tr-swap]', root).addEventListener('click', () => {
        if (source.value === 'auto') return;
        [source.value, target.value] = [target.value, source.value];
        [original.value, output.value] = [output.value || original.value, output.value ? original.value : ''];
        applyDirection();
        stats();
    });
    target.addEventListener('change', applyDirection);
    original.addEventListener('input', stats);

    $$('[data-tr-action]', root).forEach((button) =>
        button.addEventListener('click', async () => {
            const action = button.dataset.trAction;
            if (action === 'copy') {
                await navigator.clipboard?.writeText(output.value);
                toast('Translation copied.', 'success');
            } else {
                exportAs(action);
            }
        }),
    );

    const zone = $('[data-tr-dropzone]', root);
    const input = $('[data-tr-input]', root);
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });
    ['dragenter', 'dragover'].forEach((t) =>
        zone.addEventListener(t, (e) => {
            e.preventDefault();
            zone.classList.add('is-dragging');
        }),
    );
    ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, () => zone.classList.remove('is-dragging')));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        process([...(e.dataTransfer?.files || [])]);
    });
    input.addEventListener('change', () => {
        process([...input.files]);
        input.value = '';
    });

    describeEngine();

    const handed = await takeFiles();
    if (handed.length) process(handed);
}
