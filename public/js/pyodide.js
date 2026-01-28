import { writeOutput } from './output.js';

let pyodideInstance = null;

export async function initPyodide() {
    writeOutput('Lade Python Runtime...');

    // ESM-Version direkt vom CDN laden
    const pyodideModule = await import('https://cdn.jsdelivr.net/pyodide/v0.24.1/full/pyodide.mjs');
    pyodideInstance = await pyodideModule.loadPyodide({
        stdout: (t) => writeOutput(t),
        stderr: (t) => writeOutput(t)
    });

    writeOutput('Python bereit.');
}

export async function runPython(code) {
    if (!pyodideInstance) {
        writeOutput('Python noch nicht geladen!');
        return;
    }
    try {
        await pyodideInstance.runPythonAsync(code);
    } catch (err) {
        writeOutput(err.toString());
    }
}
