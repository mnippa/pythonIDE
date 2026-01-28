// editor-setup.js

async function initPyodideAndEditor() {
    // Pyodide lokal laden
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    // stdout und stderr auf #output-container umleiten
    await pyodide.runPythonAsync(`
import sys
from js import document

class JSOutput:
    def write(self, s):
        if s.strip() != "":
            output = document.getElementById("output-container")
            output.innerText += s

    def flush(self):
        pass

sys.stdout = JSOutput()
sys.stderr = JSOutput()
`);

    // black einmal beim Laden installieren
    await pyodide.loadPackage("micropip");
    await pyodide.runPythonAsync(`
import micropip
await micropip.install('black')
`);

    // Editor starten
    require(["vs/editor/editor.main"], function () {
        const editor = monaco.editor.create(document.getElementById("editor-container"), {
            value: "# Python code hier\nprint('Hello World!')",
            language: "python",
            theme: "vs-dark",
            automaticLayout: true,
            tabSize: 4,
            insertSpaces: true,
        });

        // Autocomplete für Keywords
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

        // Run Button
        document.getElementById("run-btn").addEventListener("click", () => {
            document.getElementById("output-container").innerText = ""; // vorher löschen
            pyodide.runPythonAsync(editor.getValue()).catch(console.error);
        });

        // Format Button
        document.getElementById("format-btn").addEventListener("click", async () => {
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
        });

        // Live Syntax-Fehleranzeige
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
    });
}

// Start
initPyodideAndEditor();
