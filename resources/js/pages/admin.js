import { $, $$ } from '../lib/dom';

/**
 * Small enhancements for the admin panel.
 */
export default function initAdmin() {
    // Mobile sidebar
    const sidebar = $('[data-admin-sidebar]');
    $('[data-admin-menu]')?.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
        sidebar.classList.toggle('flex');
    });

    // Tool filter (Tools and SEO → Tool pages)
    const filter = $('[data-admin-tool-filter]');
    filter?.addEventListener('input', () => {
        const term = filter.value.trim().toLowerCase();
        $$('[data-admin-tool]').forEach((row) => row.classList.toggle('hidden', term !== '' && !row.dataset.adminTool.includes(term)));
        $$('[data-admin-tool-group]').forEach((group) => group.classList.toggle('hidden', !group.querySelector('[data-admin-tool]:not(.hidden)')));
    });

    // Confirmation for destructive forms
    $$('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    initCounters();
    initAdForm();
    initSeoForm();
    initRedirectForm();
}

/**
 * Character counters; data-count="min-max" colours the count when outside the range.
 */
function initCounters() {
    $$('[data-count]').forEach((field) => {
        const [min, max] = (field.dataset.count || '').split('-').map(Number);
        const counter = document.createElement('span');
        counter.className = 'float-right text-xs font-normal tabular-nums';
        field.closest('div')?.querySelector('.label')?.append(counter);

        const update = () => {
            const length = (field.value || field.placeholder || '').length;
            const usingDefault = !field.value && field.placeholder;
            const ok = !min || (length >= min && length <= max);
            counter.textContent = `${length} chars${usingDefault ? ' (default)' : ''}${min ? ` · ${min}–${max}` : ''}`;
            counter.classList.toggle('text-emerald-600', ok);
            counter.classList.toggle('text-amber-600', !ok);
        };

        field.addEventListener('input', update);
        update();
    });
}

/**
 * Ad unit form: show the fields and positions that fit the chosen network and format.
 */
function initAdForm() {
    const form = $('[data-ad-form]');
    if (!form) return;

    const catalog = JSON.parse(form.dataset.catalog);
    const network = $('[data-ad-network]', form);
    const format = $('[data-ad-format]', form);
    const placement = $('[data-ad-placement]', form);
    const help = $('[data-ad-help]', form);
    const tools = $('[data-ad-tools]', form);

    const labelFor = (key, net) => catalog.labels[net]?.[key] || catalog.formats[key].label;

    const update = (changed) => {
        const allowed = catalog.networks[network.value].formats;

        // Formats offered by the network, with the network's own names.
        [...format.options].forEach((option) => {
            const ok = allowed.includes(option.value);
            option.hidden = !ok;
            option.disabled = !ok;
            option.textContent = labelFor(option.value, network.value);
        });
        if (!allowed.includes(format.value)) format.value = allowed[0];

        const definition = catalog.formats[format.value];
        help.textContent = catalog.help[network.value]?.[format.value] || '';

        // Positions suitable for the format; jump to the recommended one when the format changes.
        [...placement.options].forEach((option) => (option.disabled = !definition.placements.includes(option.value)));
        if (changed || placement.selectedOptions[0]?.disabled) placement.value = definition.placement;

        $$('[data-ad-show]', form).forEach((el) => el.classList.toggle('hidden', el.dataset.adShow !== format.value));
        $$('[data-ad-hide]', form).forEach((el) => el.classList.toggle('hidden', el.dataset.adHide === format.value));

        const linkMode = placement.value === 'download' ? 'download' : 'slot';
        $$('[data-ad-link-mode]', form).forEach((el) => el.classList.toggle('hidden', el.dataset.adLinkMode !== linkMode));

        const scope = $('[data-ad-scope]:checked', form)?.value;
        tools.classList.toggle('hidden', scope !== 'only' && scope !== 'except');
    };

    network.addEventListener('change', () => update('network'));
    format.addEventListener('change', () => update('format'));
    placement.addEventListener('change', () => update());
    $$('[data-ad-scope]', form).forEach((radio) => radio.addEventListener('change', () => update()));

    const toolFilter = $('[data-ad-tools-filter]', form);
    toolFilter?.addEventListener('input', () => {
        const term = toolFilter.value.trim().toLowerCase();
        $$('[data-ad-tool]', form).forEach((row) => row.classList.toggle('hidden', term !== '' && !row.dataset.adTool.includes(term)));
    });
    toolFilter?.addEventListener('keydown', (event) => event.key === 'Enter' && event.preventDefault());

    update();
}

/**
 * Tool SEO editor: live Google preview, word count, outline and FAQ rows.
 */
function initSeoForm() {
    const form = $('[data-seo-form]');
    if (!form) return;

    const template = form.dataset.titleTemplate || '%s';
    const title = $('[data-serp="title"]', form);
    const description = $('[data-serp="description"]', form);
    const serpTitle = $('[data-serp-title]');
    const serpDescription = $('[data-serp-description]');

    const updatePreview = () => {
        serpTitle.textContent = template.replace('%s', title.value.trim() || serpTitle.dataset.default);
        serpDescription.textContent = description.value.trim() || serpDescription.dataset.default;
    };
    title.addEventListener('input', updatePreview);
    description.addEventListener('input', updatePreview);
    updatePreview();

    // Word counter for the article
    const article = $('[data-words]', form);
    if (article) {
        const counter = document.createElement('span');
        counter.className = 'float-right text-xs font-normal tabular-nums';
        article.closest('div')?.querySelector('.label')?.append(counter);
        const update = () => {
            const words = article.value.trim() ? article.value.trim().split(/\s+/).length : 0;
            counter.textContent = ` · ${words} words`;
            counter.classList.toggle('text-emerald-600', words >= 300);
            counter.classList.toggle('text-amber-600', words < 300);
        };
        article.addEventListener('input', update);
        update();

        $('[data-insert-outline]', form)?.addEventListener('click', (event) => {
            const outline = event.currentTarget.dataset.insertOutline;
            article.value = article.value.trim() ? `${article.value.trimEnd()}\n\n${outline}` : outline;
            article.dispatchEvent(new Event('input'));
            article.focus();
        });
    }

    // FAQ rows
    const list = $('[data-faq-list]', form);
    const rowTemplate = $('[data-faq-template]', form);
    let next = list.children.length + 1000;

    $('[data-faq-add]', form)?.addEventListener('click', () => {
        const html = rowTemplate.innerHTML.replaceAll('__INDEX__', String(next++));
        list.insertAdjacentHTML('beforeend', html);
        list.lastElementChild?.querySelector('input')?.focus();
    });

    list.addEventListener('click', (event) => {
        if (event.target.closest('[data-faq-remove]')) event.target.closest('[data-faq-row]')?.remove();
    });
}

/**
 * Redirect form: no target for "410 Gone".
 */
function initRedirectForm() {
    const type = $('[data-redirect-type]');
    const target = $('[data-redirect-target]');
    if (!type || !target) return;

    const update = () => target.classList.toggle('hidden', type.value === '410');
    type.addEventListener('change', update);
    update();
}
