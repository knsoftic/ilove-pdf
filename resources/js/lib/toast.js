import { h, icon } from './dom';

const STYLES = {
    info: 'bg-slate-900 text-white dark:bg-white dark:text-slate-900',
    success: 'bg-emerald-600 text-white',
    error: 'bg-rose-600 text-white',
};

export function toast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toasts');
    if (!container) return;

    const el = h(
        'div',
        { class: `pointer-events-auto flex max-w-md items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium shadow-lg animate-fade-up ${STYLES[type] || STYLES.info}`, role: 'status' },
        h('span', { html: icon(type === 'error' ? 'alert' : type === 'success' ? 'check' : 'info', 'size-4 shrink-0') }),
        h('span', {}, message),
    );

    container.append(el);
    setTimeout(() => {
        el.style.transition = 'opacity .3s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, duration);
}
