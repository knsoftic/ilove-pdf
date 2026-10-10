import { h, icon } from './dom';

/**
 * Accessible modal dialogs built on <dialog>.
 */
export function openModal({ title, body, actions = [], size = 'max-w-md', onClose } = {}) {
    const dialog = h('dialog', {
        class: `m-auto w-[calc(100%-2rem)] ${size} rounded-3xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200`,
    });

    const close = (value) => {
        dialog.close();
        dialog.remove();
        onClose?.(value);
    };

    const header = h(
        'div',
        { class: 'flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800' },
        h('h2', { class: 'text-lg font-semibold text-slate-900 dark:text-white' }, title),
        h('button', { type: 'button', class: 'btn-icon', 'aria-label': 'Close', html: icon('x'), onclick: () => close(null) }),
    );

    const footer = actions.length
        ? h(
              'div',
              { class: 'flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800' },
              actions.map((action) =>
                  h(
                      'button',
                      {
                          type: action.submit ? 'submit' : 'button',
                          class: `btn ${action.primary ? 'btn-primary' : action.danger ? 'btn-danger' : 'btn-secondary'}`,
                          onclick: (event) => {
                              if (action.submit) return;
                              event.preventDefault();
                              action.onClick?.(close);
                          },
                      },
                      action.label,
                  ),
              ),
          )
        : null;

    dialog.append(header, h('div', { class: 'max-h-[75vh] overflow-y-auto p-5' }, body), footer || '');
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close(null);
    });

    document.body.append(dialog);
    dialog.showModal();

    return { dialog, close };
}

export function promptPassword({ fileName = 'This PDF', wrong = false } = {}) {
    return new Promise((resolve) => {
        const input = h('input', { type: 'password', class: 'input mt-3', autocomplete: 'current-password', placeholder: 'Password', 'aria-label': 'PDF password' });
        const form = h(
            'form',
            {
                onsubmit: (event) => {
                    event.preventDefault();
                    modal.close(input.value);
                },
            },
            h('p', { class: 'flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300', html: `${icon('lock', 'size-5 shrink-0 text-amber-500')}<span><strong></strong> is password protected. Enter the password to open it.</span>` }),
            wrong ? h('p', { class: 'mt-2 text-sm font-medium text-rose-600' }, 'The password was incorrect. Please try again.') : null,
            input,
            h('div', { class: 'mt-5 flex justify-end gap-2' }, h('button', { type: 'button', class: 'btn btn-secondary', onclick: () => modal.close(null) }, 'Cancel'), h('button', { type: 'submit', class: 'btn btn-primary' }, 'Open PDF')),
        );
        form.querySelector('strong').textContent = fileName;

        const modal = openModal({ title: 'Password required', body: form, onClose: (value) => resolve(value ?? null) });
        setTimeout(() => input.focus(), 50);
    });
}

export function confirmDialog({ title = 'Are you sure?', message = '', confirmLabel = 'Continue', danger = false } = {}) {
    return new Promise((resolve) => {
        openModal({
            title,
            body: h('p', { class: 'text-sm text-slate-600 dark:text-slate-300' }, message),
            actions: [
                { label: 'Cancel', onClick: (close) => close(false) },
                { label: confirmLabel, primary: !danger, danger, onClick: (close) => close(true) },
            ],
            onClose: (value) => resolve(Boolean(value)),
        });
    });
}
