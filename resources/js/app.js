import '@fontsource-variable/inter';
import { $, $$ } from './lib/dom';
import { initAdLinks } from './lib/ad-links';

/* ---------------------------------------------------------------------------
 | Dark mode
 * ------------------------------------------------------------------------- */
$$('[data-theme-toggle]').forEach((button) =>
    button.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        } catch {
            /* private mode */
        }
    }),
);

/* ---------------------------------------------------------------------------
 | Mobile navigation
 * ------------------------------------------------------------------------- */
const navToggle = $('[data-nav-toggle]');
const navPanel = $('[data-nav-panel]');

navToggle?.addEventListener('click', () => {
    const open = navPanel.classList.toggle('hidden') === false;
    navToggle.setAttribute('aria-expanded', String(open));
});

/* ---------------------------------------------------------------------------
 | Direct-link ads on download buttons (configured in the admin)
 * ------------------------------------------------------------------------- */
initAdLinks(window.PDFTK?.ads?.downloadLinks);

/* ---------------------------------------------------------------------------
 | Page modules (code-split, loaded only where needed)
 * ------------------------------------------------------------------------- */
const pages = {
    home: () => import('./pages/home'),
    'tools-index': () => import('./pages/tools-index'),
    tool: () => import('./pages/tool'),
    editor: () => import('./editor/editor'),
    organize: () => import('./pages/organize'),
    text: () => import('./pages/text'),
    translator: () => import('./pages/translator'),
    admin: () => import('./pages/admin'),
};

const page = document.body.dataset.page;

if (pages[page]) {
    pages[page]()
        .then((module) => module.default?.())
        .catch((error) => console.error('Failed to start page script', error));
}
