/**
 * Shared constants of the PDF editor.
 */

/** PDF standard font families and the CSS fonts used to preview them. */
export const FONTS = {
    helvetica: { css: 'Arial, Helvetica, sans-serif', label: 'Helvetica' },
    times: { css: '"Times New Roman", Times, serif', label: 'Times' },
    courier: { css: '"Courier New", Courier, monospace', label: 'Courier' },
};

/** Custom properties kept when objects are serialised (undo/redo, export). */
export const CUSTOM_PROPS = ['kind', 'pdfFont', 'coverId', 'linkId', 'numberFormat', 'objectId', 'excludeFromExport'];

export const STAMPS = [
    { label: 'APPROVED', color: '#16a34a' },
    { label: 'REJECTED', color: '#dc2626' },
    { label: 'CONFIDENTIAL', color: '#dc2626' },
    { label: 'DRAFT', color: '#475569' },
    { label: 'PAID', color: '#16a34a' },
    { label: 'FINAL', color: '#2563eb' },
    { label: 'COPY', color: '#2563eb' },
    { label: 'VOID', color: '#dc2626' },
    { label: 'RECEIVED', color: '#7c3aed', date: true },
    { label: 'SIGN HERE', color: '#ea580c' },
];

/** Tool → title shown in the properties bar and which property groups it uses. */
export const TOOLS = {
    select: { title: 'Select', groups: [], hint: 'Click an object to move, resize or change it.' },
    'edit-text': { title: 'Edit text', groups: ['font', 'color'], hint: 'Click any text in the PDF to change it.' },
    text: { title: 'Add text', groups: ['font', 'color', 'opacity'], hint: 'Click on the page where the text should go.' },
    highlight: { title: 'Highlight', groups: ['swatches', 'opacity'], hint: 'Drag over text to highlight it.' },
    underline: { title: 'Underline', groups: ['color', 'width'], hint: 'Drag over text to underline it.' },
    strike: { title: 'Strikethrough', groups: ['color', 'width'], hint: 'Drag over text to strike it through.' },
    draw: { title: 'Draw', groups: ['color', 'width', 'opacity'], hint: 'Draw freehand with your mouse, pen or finger.' },
    eraser: { title: 'Eraser', groups: [], hint: 'Click or drag over objects you added to remove them.' },
    rect: { title: 'Rectangle', groups: ['color', 'fill', 'width', 'opacity'], hint: 'Drag on the page to draw a rectangle.' },
    ellipse: { title: 'Circle', groups: ['color', 'fill', 'width', 'opacity'], hint: 'Drag on the page to draw a circle or ellipse.' },
    line: { title: 'Line', groups: ['color', 'width', 'opacity'], hint: 'Drag on the page to draw a line.' },
    arrow: { title: 'Arrow', groups: ['color', 'width', 'opacity'], hint: 'Drag on the page to draw an arrow.' },
    image: { title: 'Image', groups: ['opacity'], hint: 'Choose an image – it is placed on the current page.' },
    signature: { title: 'Signature', groups: [], hint: 'Create your signature and drag it into place.' },
    stamp: { title: 'Stamp', groups: [], hint: 'Pick a stamp – it is placed on the current page.' },
    watermark: { title: 'Watermark', groups: [], hint: 'Add a watermark to every page.' },
    'page-number': { title: 'Page numbers', groups: [], hint: 'Number all pages.' },
};

/** Tools that create objects by dragging on the page. */
export const DRAG_TOOLS = new Set(['rect', 'ellipse', 'line', 'arrow', 'highlight', 'underline', 'strike']);

let counter = 0;
export const uid = (prefix = 'o') => `${prefix}${Date.now().toString(36)}${(counter++).toString(36)}`;
