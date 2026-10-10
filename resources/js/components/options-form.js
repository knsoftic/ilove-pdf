import { $, $$, show } from '../lib/dom';

/**
 * Reads tool options from the form rendered by tools/partials/field.blade.php
 * and handles conditional fields (data-show-if), live range/colour labels
 * and password confirmation.
 */
export class OptionsForm {
    constructor(form, config) {
        this.form = form;
        this.config = config;
        this.listeners = new Set();

        if (!form) return;

        form.addEventListener('input', (event) => this.handle(event));
        form.addEventListener('change', (event) => this.handle(event));

        $$('[data-toggle-password]', form).forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });

        this.applyVisibility();
    }

    handle(event) {
        const el = event.target;

        if (el.type === 'range') {
            const out = $(`[data-range-output="${el.dataset.option}"]`, this.form);
            if (out) out.textContent = `${el.value}${el.dataset.unit || ''}`;
        }

        if (el.type === 'color') {
            const label = $(`[data-color-value="${el.dataset.option}"]`, this.form);
            if (label) label.textContent = el.value;
        }

        this.applyVisibility();
        this.listeners.forEach((fn) => fn(this.values()));
    }

    onChange(fn) {
        this.listeners.add(fn);
    }

    /** Plain option values (no File objects) – safe to send to the server. */
    values() {
        const values = {};
        if (!this.form) return values;

        for (const el of $$('[data-option]', this.form)) {
            const key = el.dataset.option;

            if (el.type === 'radio') {
                if (el.checked) values[key] = el.value;
                else if (!(key in values)) values[key] = null;
            } else if (el.type === 'checkbox') {
                values[key] = el.checked;
            } else if (el.type === 'number' || el.type === 'range') {
                values[key] = el.value === '' ? null : Number(el.value);
            } else {
                values[key] = el.value;
            }
        }

        const metadata = $$('[data-meta]', this.form);
        if (metadata.length) {
            values.metadata = Object.fromEntries(metadata.map((input) => [input.dataset.meta, input.value]));
        }

        return values;
    }

    /** File inputs inside the options (e.g. watermark image). */
    files() {
        const files = {};
        $$('[data-option-file]', this.form || document.createElement('form')).forEach((input) => {
            files[input.dataset.optionFile] = input.files?.[0] || null;
        });
        return files;
    }

    set(key, value) {
        const elements = $$(`[data-option="${key}"]`, this.form);
        for (const el of elements) {
            if (el.type === 'radio') el.checked = el.value === String(value);
            else if (el.type === 'checkbox') el.checked = Boolean(value);
            else el.value = value ?? '';
        }
        this.applyVisibility();
    }

    isVisible(key) {
        const wrapper = $(`[data-field="${key}"]`, this.form);
        return wrapper ? !wrapper.classList.contains('hidden') || wrapper.querySelector('input[type=hidden]') !== null : false;
    }

    applyVisibility() {
        if (!this.form) return;
        const values = this.values();

        for (const wrapper of $$('[data-show-if]', this.form)) {
            let conditions = {};
            try {
                conditions = JSON.parse(wrapper.dataset.showIf);
            } catch {
                /* ignore */
            }

            const visible = Object.entries(conditions).every(([key, allowed]) => (Array.isArray(allowed) ? allowed : [allowed]).map(String).includes(String(values[key])));
            show(wrapper, visible);
        }
    }

    /**
     * Returns an error message, or null when the options are valid.
     */
    validate() {
        if (!this.form) return null;
        const values = this.values();

        for (const [key, field] of Object.entries(this.config.options || {})) {
            if (!field.required || !this.isVisible(key)) continue;
            const value = values[key];
            if (value === null || value === undefined || String(value).trim() === '') {
                return `Please fill in "${field.label}".`;
            }
        }

        for (const input of $$('input[type=password][required]', this.form)) {
            if (!this.isVisible(input.dataset.option)) continue;
            const min = Number(input.getAttribute('minlength') || 0);
            if (input.value.length < min) return `The password must have at least ${min} characters.`;
        }

        for (const confirm of $$('[data-confirm-for]', this.form)) {
            const key = confirm.dataset.confirmFor;
            if (this.isVisible(key) && confirm.value !== String(values[key] ?? '')) {
                return 'The passwords do not match.';
            }
        }

        return null;
    }
}
