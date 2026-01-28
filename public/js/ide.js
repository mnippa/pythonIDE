import { createEditor } from './editor.api.js';

const EDITOR_ID = 'editor-container';
const OUTPUT_ID = 'output-container';
const RUN_BTN_ID = 'run-btn';

let editor;
let pyodide;

async function init() {
    // Editor erstellen
    editor = await createEditor(EDITOR_ID, 'print("Hello from Pyodide!")');

    // Pyodide laden
    const { loadPyodide } = await import('../pyodide/pyodide.mjs');
    pyodide = await loadPyodide({
        indexURL: '/pythonIDE/public/pyodide/'
    });

    console.log("Pyodide ready");

    const outputContainer = document.getElementById(OUTPUT_ID);

    // Pyodide stdout/stderr umleiten
    pyodide.setStdout({
        batched: (s) => { outputContainer.textContent += s; }
    });
    pyodide.setStderr({
        batched: (s) => { outputContainer.textContent += s; }
    });

    // Run-Button Event
    document.getElementById(RUN_BTN_ID).addEventListener('click', async () => {
        outputContainer.textContent = ''; // vorherigen Output löschen
        const code = editor.getValue();
        try {
            await pyodide.runPythonAsync(code);
        } catch (err) {
            outputContainer.textContent += err;
        }
    });
}

// Init starten
document.addEventListener('DOMContentLoaded', init);
