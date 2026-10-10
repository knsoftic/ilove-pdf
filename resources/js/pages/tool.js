import { $, $$, show, hide, formatBytes } from '../lib/dom';
import { FileQueue } from '../components/file-queue';
import { OptionsForm } from '../components/options-form';
import { PagePicker } from '../components/page-picker';
import { renderStats } from '../components/result-stats';
import { upload, pollJob, sendDelete } from '../lib/http';
import { toToolError, ToolError } from '../lib/errors';
import { takeFiles } from '../lib/handoff';
import { zipFiles } from '../lib/zip';
import { recordUsage } from '../lib/usage';
import { toast } from '../lib/toast';

// Browser handlers are code-split: only the module of the current tool is loaded.
const handlers = import.meta.glob('../tools/*.js');

export function loadHandler(name) {
    const loader = handlers[`../tools/${name}.js`];
    if (!loader) throw new ToolError('engine_unavailable');
    return loader();
}

/**
 * Generic tool page: upload -> options -> process (browser or server) -> result.
 */
export default async function initToolPage() {
    const root = $('#tool-app');
    if (!root?.dataset.config) return;

    const config = JSON.parse(root.dataset.config);
    if (!config.engines.length) return;

    const els = {
        options: $('[data-options]', root),
        optionsPanel: $('[data-options-panel]', root),
        actions: $('[data-actions]', root),
        process: $('[data-process]', root),
        progress: $('[data-progress]', root),
        progressTitle: $('[data-progress-title]', root),
        progressMessage: $('[data-progress-message]', root),
        progressPercent: $('[data-progress-percent]', root),
        progressBar: $('[data-progress-bar]', root),
        result: $('[data-result]', root),
        resultTitle: $('[data-result-title]', root),
        resultFile: $('[data-result-file]', root),
        resultStats: $('[data-result-stats]', root),
        resultExtra: $('[data-result-extra]', root),
        download: $('[data-download]', root),
        deleteNow: $('[data-delete-now]', root),
        error: $('[data-error]', root),
        errorTitle: $('[data-error-title]', root),
        errorMessage: $('[data-error-message]', root),
        upload: $('[data-upload]', root),
        filesPanel: $('[data-files-panel]', root),
        engineSwitch: $('[data-engine-switch]', root),
        privacyNote: $('[data-privacy-note]'),
    };

    const options = new OptionsForm(els.options, config);
    let handler = null;
    let controller = null;
    let objectUrl = null;
    let serverJob = null;
    let state = 'upload';

    const engine = () => $('[data-engine]:checked', root)?.value || config.engines[0];

    const ctxBase = () => ({
        tool: config,
        params: config.params || {},
        options: options.values(),
        optionFiles: options.files(),
        files: queue.files,
        form: options,
        root,
    });

    async function getHandler() {
        if (!config.browser) return null;
        handler ??= await loadHandler(config.browser);
        return handler;
    }

    // ---- Files ---------------------------------------------------------

    const pickers = new Map();

    const filesOptional = () => {
        const rule = config.filesOptional;
        return rule ? String(options.values()[rule.option]) === rule.value : false;
    };

    const queue = new FileQueue({
        root,
        config,
        onChange: async (files) => {
            updateUi();

            for (const [, picker] of pickers) {
                if (files[0] && !picker.container.classList.contains('hidden')) picker.load(files[0]).catch(() => {});
            }

            if (files.length && config.browser) {
                try {
                    const mod = await getHandler();
                    await mod.onFiles?.({ ...ctxBase(), files });
                } catch (error) {
                    showError(toToolError(error));
                }
            }
        },
    });

    // "Select visually" page pickers.
    $$('[data-open-picker]', root).forEach((button) => {
        const key = button.dataset.openPicker;
        const container = $(`[data-page-picker="${key}"]`, root);
        const input = $(`[data-option="${key}"]`, root);
        const picker = new PagePicker(container, input);
        pickers.set(key, picker);

        button.addEventListener('click', async () => {
            const opening = container.classList.contains('hidden');
            show(container, opening);
            if (opening && queue.files[0]) {
                try {
                    await picker.load(queue.files[0]);
                } catch (error) {
                    show(container, false);
                    showError(toToolError(error));
                }
            }
        });
    });

    options.onChange(() => updateUi());

    $$('[data-engine]', root).forEach((radio) =>
        radio.addEventListener('change', () => {
            const browser = engine() === 'browser';
            if (els.privacyNote) {
                els.privacyNote.querySelector('span').textContent = browser ? els.privacyNote.dataset.browserText : els.privacyNote.dataset.serverText;
            }
        }),
    );

    function updateUi() {
        if (state !== 'upload' && state !== 'ready') return;
        const optional = filesOptional();
        const ready = queue.files.length > 0 || optional;
        state = ready ? 'ready' : 'upload';

        show(els.actions, ready);
        if (optional) {
            hide(els.upload);
            hide(els.filesPanel);
        } else if (!queue.files.length) {
            show(els.upload);
        }
        els.process.disabled = !ready;
    }

    // ---- Processing ----------------------------------------------------

    function setProgress(percent, message, title) {
        if (title) els.progressTitle.textContent = title;
        if (message) els.progressMessage.textContent = message;

        if (percent === null || percent === undefined) {
            els.progressBar.classList.add('indeterminate');
            els.progressPercent.textContent = '';
        } else {
            const value = Math.max(0, Math.min(100, Math.round(percent)));
            els.progressBar.classList.remove('indeterminate');
            els.progressBar.style.width = `${value}%`;
            els.progressPercent.textContent = `${value}%`;
        }
    }

    function setView(view) {
        state = view;
        show(els.progress, view === 'progress');
        show(els.result, view === 'result');
        hide(els.error);
        show(els.actions, view === 'ready');
        show(els.optionsPanel, view !== 'result' && view !== 'progress');
        show(els.engineSwitch, view !== 'result' && view !== 'progress');
        show(els.filesPanel, (view === 'ready' || view === 'error') && queue.files.length > 0);
        if (view === 'progress' || view === 'result') hide(els.upload);
        queue.lock(view === 'progress');
    }

    function showError(error) {
        if (error.code === 'cancelled') {
            setView('ready');
            updateUi();
            return;
        }

        setView('ready');
        updateUi();
        els.errorTitle.textContent = error.title || 'Something went wrong';
        els.errorMessage.textContent = error.message;
        show(els.error);
        els.error.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async function run() {
        hide(els.error);

        if (!filesOptional()) {
            if (!queue.files.length) return showError(new ToolError('no_file'));
            if (queue.files.length < (config.minFiles || 1)) {
                return showError(new ToolError('too_few_files', `Please add at least ${config.minFiles} files.`));
            }
        }

        const invalid = options.validate();
        if (invalid) return showError(new ToolError('invalid_options', invalid));

        controller = new AbortController();
        setView('progress');
        setProgress(0, 'Starting…', 'Processing…');
        els.progress.scrollIntoView({ behavior: 'smooth', block: 'center' });

        try {
            const result = engine() === 'browser' ? await runInBrowser() : await runOnServer();
            showResult(result);
        } catch (error) {
            showError(toToolError(error));
        } finally {
            controller = null;
        }
    }

    async function runInBrowser() {
        const mod = await getHandler();
        setProgress(null, 'Working in your browser…', 'Processing…');

        const output = await mod.run({
            ...ctxBase(),
            signal: controller.signal,
            progress: (percent, message) => {
                if (controller?.signal.aborted) throw new ToolError('cancelled');
                setProgress(percent, message);
            },
        });

        recordUsage(config.endpoints.usage);

        // Several files are packed into one ZIP for a single download.
        if (output.files) {
            if (output.files.length === 1) {
                Object.assign(output, { blob: output.files[0].blob, filename: output.files[0].name });
            } else {
                setProgress(95, 'Creating ZIP archive…');
                output.blob = await zipFiles(output.files);
                output.stats = { files: output.files.length, ...(output.stats || {}) };
            }
        }

        return { ...output, local: true };
    }

    async function runOnServer() {
        const data = new FormData();
        if (!filesOptional()) queue.files.forEach((file) => data.append('files[]', file, file.name));
        data.append('options', JSON.stringify(options.values()));

        setProgress(0, 'Uploading…', 'Uploading your file');

        let job = await upload(config.endpoints.process, data, {
            signal: controller.signal,
            onProgress: (fraction) => {
                if (fraction >= 1) setProgress(null, 'Processing on the server…', 'Processing…');
                else setProgress(fraction * 100, `Uploading… ${formatBytes(queue.files.reduce((s, f) => s + f.size, 0) * fraction)}`);
            },
        });

        serverJob = job;

        job = await pollJob(job, {
            signal: controller.signal,
            onProgress: (current) => {
                serverJob = current;
                setProgress(current.status === 'queued' ? null : current.progress, current.message || 'Processing…', current.status === 'queued' ? 'Waiting in queue…' : 'Processing…');
            },
        });

        serverJob = job;

        return {
            url: job.result.download_url,
            filename: job.result.filename,
            size: job.result.size,
            stats: job.result.stats,
            local: false,
        };
    }

    async function showResult(result) {
        setProgress(100, 'Done');
        setView('result');

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;

        const filename = result.filename || 'download';
        const size = result.blob?.size ?? result.size;

        els.resultTitle.textContent = result.title || (result.blob || result.url ? 'Your file is ready!' : 'Done!');
        els.resultFile.textContent = result.blob || result.url ? `${filename}${size ? ` · ${formatBytes(size)}` : ''}` : result.subtitle || '';
        renderStats(els.resultStats, result.stats || {});

        els.resultExtra.replaceChildren();
        if (result.render) els.resultExtra.append(result.render);

        if (result.blob) {
            objectUrl = URL.createObjectURL(result.blob);
            els.download.href = objectUrl;
            els.download.download = filename;
            show(els.download);
        } else if (result.url) {
            els.download.href = result.url;
            els.download.download = filename;
            show(els.download);
        } else {
            hide(els.download);
        }

        show(els.deleteNow, !result.local && Boolean(serverJob));
        els.result.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function restart() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        serverJob = null;
        queue.clear();
        pickers.forEach((picker) => {
            picker.destroy();
            hide(picker.container);
        });
        state = 'upload';
        setView('upload');
        hide(els.result);
        show(els.optionsPanel);
        show(els.engineSwitch);
        updateUi();
        handler?.reset?.(ctxBase());
        window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
    }

    els.process?.addEventListener('click', run);
    $$('[data-retry]', root).forEach((btn) => btn.addEventListener('click', run));
    $$('[data-restart]', root).forEach((btn) => btn.addEventListener('click', restart));
    $('[data-cancel]', root)?.addEventListener('click', () => controller?.abort());

    els.deleteNow?.addEventListener('click', async () => {
        if (serverJob?.status_url) await sendDelete(serverJob.status_url);
        hide(els.deleteNow);
        hide(els.download);
        toast('Your files were deleted from the server.', 'success');
    });

    // Setup hook (e.g. tools with custom option widgets).
    if (config.browser && config.engines.includes('browser')) {
        getHandler()
            .then((mod) => mod.setup?.(ctxBase()))
            .catch(() => {});
    }

    updateUi();

    // Files handed over from the home page.
    const handed = await takeFiles();
    if (handed.length) queue.add(handed);
}
