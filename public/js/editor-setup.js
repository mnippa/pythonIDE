// editor-setup.js
import { loadPyodide } from "https://cdn.jsdelivr.net/pyodide/v0.23.4/full/pyodide.js";

let pyodide;

// === 1. Pyodide laden ===
async function initPyodide() {
    pyodide = await loadPyodide({
        indexURL: "https://cdn.jsdelivr.net/pyodide/v0.23.4/full/"
    });
    console.log("Pyodide ready");
}
await initPyodide();

// === 2. Monaco Editor initialisieren ===
require(["vs/editor/editor.main"], function () {
    const editor = monaco.editor.create(document.getElementById("editor-container"), {
        value: "# Python code hier",
        language: "python",
        theme: "vs-dark",
        automaticLayout: true,
        tabSize: 4,
        insertSpaces: true,
    });

    // ==============================
    // 2a. Autovervollständigung
    // ==============================
    monaco.languages.registerCompletionItemProvider('python', {
        provideCompletionItems: function(model, position) {
            const word = model.getWordUntilPosition(position);
            const range = {
                startLineNumber: position.lineNumber,
                endLineNumber: position.lineNumber,
                startColumn: word.startColumn,
                endColumn: word.endColumn
            };
            return {
                suggestions: [
                    { label: 'print', kind: monaco.languages.CompletionItemKind.Function, insertText: 'print()', range },
                    { label: 'def', kind: monaco.languages.CompletionItemKind.Keyword, insertText: 'def ', range },
                    { label: 'import', kind: monaco.languages.CompletionItemKind.Keyword, insertText: 'import ', range },
                    { label: 'for', kind: monaco.languages.CompletionItemKind.Keyword, insertText: 'for ', range },
                    { label: 'if', kind: monaco.languages.CompletionItemKind.Keyword, insertText: 'if ', range },
                ]
            };
        }
    });

    // ==============================
    // 2b. Live Syntaxprüfung
    // ==============================
    editor.onDidChangeModelContent(async () => {
        const code = editor.getValue();
        try {
            await pyodide.runPythonAsync(code);
            monaco.editor.setModelMarkers(editor.getModel(), 'python', []);
        } catch(e) {
            monaco.editor.setModelMarkers(editor.getModel(), 'python', [{
                startLineNumber: e.lineNumber || 1,
                startColumn: 1,
                endLineNumber: e.lineNumber || 1,
                endColumn: 100,
                message: e.message,
                severity: monaco.MarkerSeverity.Error
            }]);
        }
    });

    // ==============================
    // 2c. Run-Button
    // ==============================
    document.getElementById("run-btn").addEventListener("click", async () => {
        const code = editor.getValue();
        try {
            const result = await pyodide.runPythonAsync(code);
            document.getElementById("output-container").innerText = result ?? "";
        } catch(e) {
            document.getElementById("output-container").innerText = e;
        }
    });

    // ==============================
    // 2d. Format-Button
    // ==============================
    async function formatCode() {
        try {
            const code = editor.getValue();
            const formatted = await pyodide.runPythonAsync(`
import black
black.format_str("""${code}""", mode=black.FileMode())
            `);
            editor.setValue(formatted);
        } catch(e) {
            console.error("Formatierung fehlgeschlagen:", e);
            alert("Formatierung fehlgeschlagen: " + e);
        }
    }

    document.getElementById("format-btn").addEventListener("click", formatCode);
});
