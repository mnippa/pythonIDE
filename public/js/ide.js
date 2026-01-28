import { createEditor } from './editor.api.js';

const OUTPUT_ID = 'output-container';
const EDITOR_ID = 'editor-container';
const RUN_BTN_ID = 'run-btn';

let editor;
let pyodide;

async function init() {
    // Editor erstellen
    editor = await createEditor(EDITOR_ID, 'print("Hello Pyodide")');

    // Pyodide initialisieren
    const { loadPyodide } = await import('/pythonIDE/public/pyodide/pyodide.mjs');
    pyodide = await loadPyodide({
        indexURL: '/pythonIDE/public/pyodide/'
    });

    console.log("Pyodide ready");

    // Run-Button Event
    document.getElementById(RUN_BTN_ID).addEventListener('click', async () => {
        const code = editor.getValue();
        const outputContainer = document.getElementById(OUTPUT_ID);
        try {
            const result = await pyodide.runPythonAsync(code);
            outputContainer.textContent = result ?? '';
        } catch (err) {
            outputContainer.textContent = err;
        }
    });
}

// Init starten
document.addEventListener('DOMContentLoaded', init);
