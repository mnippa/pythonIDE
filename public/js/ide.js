// IDE Setup
import './editor.api.js'; // optional, falls du Funktionen aus editor.api.js brauchst

// AMD Loader von Monaco einbinden
import * as monacoLoader from '../monaco/min/vs/loader.js';

const require = monacoLoader.require;
require.config({ paths: { 'vs': '../monaco/min/vs' } });

require(['vs/editor/editor.main'], function () {
    // Editor erstellen
    const editor = monaco.editor.create(document.getElementById('editor'), {
        value: `print("Hello World")`,
        language: 'python',
        theme: 'vs-light',
        automaticLayout: true
    });

    // Run Button
    const outputDiv = document.getElementById('output');
    document.getElementById('runButton').addEventListener('click', async () => {
        outputDiv.textContent = '';
        const code = editor.getValue();

        // Pyodide wird dynamisch geladen
        if (!window.pyodide) {
            outputDiv.textContent = 'Lade Pyodide...';
            window.pyodide = await import('../pyodide/pyodide.js')
                .then(module => module.loadPyodide({ indexURL: '../pyodide/' }));
            outputDiv.textContent = '';
        }

        try {
            const result = await window.pyodide.runPythonAsync(code);
            outputDiv.textContent = result ?? '';
        } catch (err) {
            outputDiv.textContent = err;
        }
    });
});
