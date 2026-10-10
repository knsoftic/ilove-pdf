/**
 * Undo / redo of complete editor states.
 *
 * A state is { order: [pageId], pages: { [pageId]: { srcIndex, rotation, json } } }
 * where json is the serialised object layer of the page (a string). Strings
 * of unchanged pages are shared between states, so memory stays modest.
 */
export class History {
    constructor(limit = 60) {
        this.limit = limit;
        this.stack = [];
        this.index = -1;
    }

    push(state) {
        this.stack = this.stack.slice(0, this.index + 1);
        this.stack.push(state);

        if (this.stack.length > this.limit) this.stack.shift();
        this.index = this.stack.length - 1;
    }

    get current() {
        return this.stack[this.index] || null;
    }

    canUndo() {
        return this.index > 0;
    }

    canRedo() {
        return this.index < this.stack.length - 1;
    }

    undo() {
        return this.canUndo() ? this.stack[--this.index] : null;
    }

    redo() {
        return this.canRedo() ? this.stack[++this.index] : null;
    }
}
