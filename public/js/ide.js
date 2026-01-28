// ide.js
import * as monaco from '../monaco/esm/vs/editor/editor.api.js';

const editor = monaco.editor.create(document.getElementById('editor'), {
    value: "# Python Code hier",
    language: "python",
    theme: "vs-dark",
    automaticLayout: true,
});

export function getEditor() {
    return editor;
}
