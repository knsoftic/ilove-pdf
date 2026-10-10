import { $, $$, show } from '../lib/dom';
import { validateFiles, download, outputName } from '../lib/files';
import { postForBlob } from '../lib/http';
import { config as appConfig } from '../lib/config';
import { extractPdfText, ocrPages } from '../lib/extract-pipeline';
import { takeFiles } from '../lib/handoff';
import { toast } from '../lib/toast';
import { confirmDialog } from '../lib/modal';
import { recordUsage } from '../lib/usage';
import { toToolError } from '../lib/errors';

/**
 * "PDF to Text" / "Extract text": extraction (browser or server), OCR for
 * scanned pages, a large editor with search & replace, and downloads.
 */
export default async function initTextTool() {
    const root = $('#text-tool');
    if (!root) return;

    const config = JSON.parse(root.dataset.config);
    const editor = $('[data-text-editor]', root);
    const uploadBox = $('[data-text-upload]', root);
    const progressBox = $('[data-text-progress]', root);
    const resultBox = $('[data-text-result]', root);
    const input = $('[data-text-input]', root);
    const zone = $('[data-text-dropzone]', root);

    let file = null;
    let pages = [];
    let controller = null;
    let matches = [];
    let matchIndex = -1;

    const option = (name) => {
        const el = $(`[data-text-option="${name}"]`, root);
        return el?.type === 'checkbox' ? el.checked : el?.value;
    };
    const engine = () => $('[data-engine]:checked', root)?.value || config.engines[0] || 'browser';

    function setProgress(percent, message) {
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

    function view(name) {
        show(uploadBox, name === 'upload');
        show(progressBox, name === 'progress');
        show(resultBox, name === 'result');
    }

    function compose() {
        const markers = option('markers');
        return pages.map((text, i) => (markers && pages.length > 1 ? `——— Page ${i + 1} ———\n${text}` : text)).join('\n\n');
    }

    function updateStats() {
        const text = editor.value;
        const words = (text.match(/\S+/g) || []).length;
        $('[data-text-stats]', root).textContent = `${pages.length} page${pages.length === 1 ? '' : 's'} · ${words.toLocaleString()} words · ${text.length.toLocaleString()} characters`;
    }

    function showOcrBanner(emptyPages) {
        const banner = $('[data-ocr-banner]', root);
        show(banner, emptyPages.length > 0);
        banner.classList.toggle('flex', emptyPages.length > 0);
        if (emptyPages.length) {
            $('[data-ocr-title]', root).textContent = emptyPages.length === pages.length ? 'This PDF contains no text layer' : `${emptyPages.length} page${emptyPages.length === 1 ? '' : 's'} without text`;
            $('[data-ocr-text]', root).textContent = `Pages ${emptyPages.slice(0, 12).join(', ')}${emptyPages.length > 12 ? '…' : ''} look like scanned images. Run OCR to recognise their text.`;
            banner.dataset.pages = emptyPages.join(',');
        }
    }

    async function process(selected) {
        const { valid, errors } = await validateFiles(selected.slice(0, 1), { extensions: ['pdf'], maxBytes: config.maxBytes, label: 'PDF' });
        errors.forEach((e) => toast(e.message, 'error', 5000));
        if (!valid.length) return;

        file = valid[0];
        controller = new AbortController();
        view('progress');
        $('[data-progress-title]', root).textContent = 'Extracting text…';
        setProgress(0, 'Starting…');

        try {
            const result = await extractPdfText(file, { engine: engine(), config, layout: option('layout'), signal: controller.signal, progress: setProgress });
            pages = result.pages;
            editor.value = compose();
            updateStats();
            showOcrBanner(result.emptyPages);
            view('result');
            if (engine() === 'browser') recordUsage(config.endpoints.usage);

            // A fully scanned document: start OCR straight away.
            if (result.emptyPages.length && result.emptyPages.length === pages.length) runOcr();
        } catch (error) {
            const e = toToolError(error);
            view('upload');
            if (e.code !== 'cancelled') toast(e.message, 'error', 6000);
        }
    }

    async function runOcr() {
        const banner = $('[data-ocr-banner]', root);
        const numbers = (banner.dataset.pages || '').split(',').filter(Boolean).map(Number);
        if (!numbers.length || !file) return;

        controller = new AbortController();
        view('progress');
        $('[data-progress-title]', root).textContent = 'Recognising text (OCR)…';
        setProgress(null, 'Loading the OCR engine…');

        try {
            const results = await ocrPages(file, numbers, { language: option('language'), signal: controller.signal, progress: setProgress });
            for (const [number, text] of results) pages[number - 1] = text;
            editor.value = compose();
            updateStats();
            showOcrBanner(pages.map((p, i) => (p.trim().length < 3 ? i + 1 : null)).filter(Boolean));
            toast('OCR finished.', 'success');
        } catch (error) {
            const e = toToolError(error);
            if (e.code !== 'cancelled') toast(e.message, 'error', 6000);
        } finally {
            view('result');
        }
    }

    // ---- Search & replace ----------------------------------------------

    const searchInput = $('[data-search-input]', root);
    const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    function findMatches() {
        const query = searchInput.value;
        matches = [];
        if (query) {
            const re = new RegExp(escape(query), 'gi');
            let m;
            while ((m = re.exec(editor.value))) {
                matches.push([m.index, m.index + m[0].length]);
                if (m[0].length === 0) re.lastIndex++;
            }
        }
        matchIndex = Math.min(matchIndex, matches.length - 1);
        $('[data-search-count]', root).textContent = query ? `${matches.length ? matchIndex + 1 : 0}/${matches.length}` : '';
    }

    function goTo(index) {
        if (!matches.length) return;
        matchIndex = (index + matches.length) % matches.length;
        const [start, end] = matches[matchIndex];
        editor.focus();
        editor.setSelectionRange(start, end);

        // Scroll the match into view.
        const lines = editor.value.slice(0, start).split('\n').length;
        const lineHeight = parseFloat(getComputedStyle(editor).lineHeight) || 24;
        editor.scrollTop = Math.max(0, (lines - 4) * lineHeight);

        $('[data-search-count]', root).textContent = `${matchIndex + 1}/${matches.length}`;
        searchInput.focus();
    }

    searchInput.addEventListener('input', () => {
        matchIndex = 0;
        findMatches();
        if (matches.length) goTo(0);
    });
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            goTo(matchIndex + (e.shiftKey ? -1 : 1));
        }
    });
    $('[data-search="next"]', root).addEventListener('click', () => goTo(matchIndex + 1));
    $('[data-search="prev"]', root).addEventListener('click', () => goTo(matchIndex - 1));

    $('[data-replace="one"]', root).addEventListener('click', () => {
        findMatches();
        if (!matches.length) return;
        const [start, end] = matches[Math.max(0, matchIndex)];
        editor.setRangeText($('[data-replace-input]', root).value, start, end, 'end');
        findMatches();
        updateStats();
        if (matches.length) goTo(matchIndex);
    });

    $('[data-replace="all"]', root).addEventListener('click', () => {
        const query = searchInput.value;
        if (!query) return;
        const count = (editor.value.match(new RegExp(escape(query), 'gi')) || []).length;
        editor.value = editor.value.replace(new RegExp(escape(query), 'gi'), () => $('[data-replace-input]', root).value);
        findMatches();
        updateStats();
        toast(`${count} replacement${count === 1 ? '' : 's'} made.`, 'success');
    });

    editor.addEventListener('input', () => {
        updateStats();
        if (searchInput.value) findMatches();
    });

    // ---- Actions ---------------------------------------------------------

    const name = (ext) => outputName(file?.name || 'text', '', ext);

    const actions = {
        copy: async () => {
            try {
                await navigator.clipboard.writeText(editor.value);
                toast('Text copied to the clipboard.', 'success');
            } catch {
                editor.select();
                document.execCommand('copy');
                toast('Text copied.', 'success');
            }
        },
        txt: () => download(new Blob(['﻿', editor.value], { type: 'text/plain;charset=utf-8' }), name('txt')),
        docx: () => exportAs('docx'),
        pdf: () => exportAs('pdf'),
        clear: async () => {
            if (editor.value && (await confirmDialog({ title: 'Clear the text?', message: 'The editor will be emptied. You can extract the text again at any time.', confirmLabel: 'Clear', danger: true }))) {
                editor.value = '';
                updateStats();
            }
        },
    };

    async function exportAs(format) {
        if (!editor.value.trim()) return toast('There is no text to download.', 'error');
        try {
            const { blob, filename } = await postForBlob(appConfig.routes.exportText, { text: editor.value, format, filename: file?.name || 'text' });
            download(blob, filename || name(format));
        } catch (error) {
            toast(toToolError(error).message, 'error');
        }
    }

    $$('[data-text-action]', root).forEach((b) => b.addEventListener('click', () => actions[b.dataset.textAction]()));
    $('[data-run-ocr]', root).addEventListener('click', runOcr);
    $('[data-text-cancel]', root).addEventListener('click', () => controller?.abort());
    $('[data-text-restart]', root).addEventListener('click', () => {
        pages = [];
        editor.value = '';
        view('upload');
    });
    $('[data-text-option="markers"]', root).addEventListener('change', () => {
        if (pages.length) {
            editor.value = compose();
            updateStats();
        }
    });

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

    const handed = await takeFiles();
    if (handed.length) process(handed);
}
