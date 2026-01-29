async function initPyodideAndEditor() {

    /* ---------------- Pyodide ---------------- */
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    const loadedPackages = new Set();

    const RUNTIME_PACKAGES = new Set([
        "numpy",
        "pandas",
        "matplotlib",
        "plotly"
    ]);

    async function loadSelectedPackages() {
        const checkboxes = document.querySelectorAll(
            "#packages input[type=checkbox]"
        );

        const toLoad = [];

        checkboxes.forEach(cb => {
            if (
                cb.checked &&
                RUNTIME_PACKAGES.has(cb.value) &&
                !loadedPackages.has(cb.value)
            ) {
                toLoad.push(cb.value);
            }
        });

        if (toLoad.length === 0) return;

        console.log("Loading packages:", toLoad);
        await pyodide.loadPackage(toLoad);

        toLoad.forEach(p => loadedPackages.add(p));
    }

    /* ---------------- Fehlerparser ---------------- */
    function parsePythonError(message) {
        let line = 1;
        let error = "Python error";

        message.split("\n").forEach(l => {
            let m = l.match(/File "<string>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);

            if (l.includes("Error:") || l.includes("Exception:")) {
                error = l.trim();
            }
        });

        return { line, error };
    }

    /* ---------------- Monaco ---------------- */
    require(["vs/editor/editor.main"], function () {

        const editor = monaco.editor.create(
            document.getElementById("editor-container"),
            {
                value:
`# Beispiele:
import math
import numpy as np

print(math.sqrt(16))
print(np.array([1,2,3]) * 2)
`,
                language: "python",
                theme: "vs-dark",
                automaticLayout: true
            }
        );

        const outputEl = document.getElementById("output-container");
        const lintEl   = document.getElementById("lint-container");

        /* ---------------- Live Syntaxcheck ---------------- */
        let debounce;
        editor.onDidChangeModelContent(() => {
            clearTimeout(debounce);
            debounce = setTimeout(async () => {
                monaco.editor.setModelMarkers(editor.getModel(), "python", []);
                try {
                    await pyodide.runPythonAsync(
                        `compile(${JSON.stringify(editor.getValue())}, "<string>", "exec")`
                    );
                } catch (e) {
                    const parsed = parsePythonError(e.message);
                    monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                        startLineNumber: parsed.line,
                        startColumn: 1,
                        endLineNumber: parsed.line,
                        endColumn: 200,
                        message: parsed.error,
                        severity: monaco.MarkerSeverity.Error
                    }]);
                }
            }, 400);
        });

        /* ---------------- Numpy Introspection ---------------- */
        let npMethods = [];
        async function refreshNumpyMethods() {
            if (!loadedPackages.has("numpy")) return;
            try {
                npMethods = await pyodide.runPythonAsync(`
import numpy as np
dir(np)
                `);
            } catch(e) {
                console.error("Fehler beim Abrufen von np dir():", e);
            }
        }

        /* ---------------- Autocomplete ---------------- */
        monaco.languages.registerCompletionItemProvider("python", {
            triggerCharacters: [".", " "],
            provideCompletionItems: async (model, position) => {
                const text = model.getValueInRange({
                    startLineNumber: position.lineNumber,
                    startColumn: 1,
                    endLineNumber: position.lineNumber,
                    endColumn: position.column
                });

                const suggestions = [];

                // import math
                if (/import\s+ma?$/.test(text)) {
                    suggestions.push({
                        label: "math",
                        kind: monaco.languages.CompletionItemKind.Module,
                        insertText: "math"
                    });
                }

                // import numpy
                if (/import\s+num?$/.test(text)) {
                    suggestions.push({
                        label: "numpy",
                        kind: monaco.languages.CompletionItemKind.Module,
                        insertText: "numpy as np"
                    });
                }

                // math.
                if (/math\.$/.test(text)) {
                    ["sqrt","sin","cos","pi","log"].forEach(fn=>{
                        suggestions.push({
                            label: fn,
                            kind: monaco.languages.CompletionItemKind.Function,
                            insertText: fn
                        });
                    });
                }

                // np.
                if (/np\.$/.test(text)) {
                    await refreshNumpyMethods();
                    npMethods.forEach(fn=>{
                        suggestions.push({
                            label: fn,
                            kind: monaco.languages.CompletionItemKind.Function,
                            insertText: fn
                        });
                    });
                }

                return { suggestions };
            }
        });

        /* ---------------- RUN ---------------- */
        document.getElementById("run-btn").addEventListener("click", async () => {

            outputEl.innerText = "";
            lintEl.innerText = "";
            monaco.editor.setModelMarkers(editor.getModel(), "python", []);

            const code = editor.getValue();

            /* Syntaxcheck */
            try {
                await pyodide.runPythonAsync(
                    `compile(${JSON.stringify(code)}, "<string>", "exec")`
                );
            } catch (e) {
                const parsed = parsePythonError(e.message);
                lintEl.innerText = `Zeile ${parsed.line}: ${parsed.error}`;
                return;
            }

            /* Pakete laden */
            try {
                await loadSelectedPackages();
            } catch (e) {
                lintEl.innerText = "Fehler beim Laden der Pakete:\n" + e.message;
                return;
            }

            /* Ausführen */
            try {
                await pyodide.runPythonAsync(`
from js import document
import sys

class JSOut:
    def __init__(self, el):
        self.el = document.getElementById(el)
    def write(self, s):
        if s.strip():
            self.el.innerText += s + "\\n"
    def flush(self): pass

old_out, old_err = sys.stdout, sys.stderr
sys.stdout = sys.stderr = JSOut("output-container")

try:
${code.split("\n").map(l => "    " + l).join("\n")}
finally:
    sys.stdout, sys.stderr = old_out, old_err
                `);
            } catch (e) {
                const parsed = parsePythonError(e.message);
                lintEl.innerText = `Zeile ${parsed.line}: ${parsed.error}`;
            }
        });

        /* ---------------- Undo/Redo Buttons ---------------- */
        const undoBtn = document.getElementById("undo-btn");
        const redoBtn = document.getElementById("redo-btn");

        if (undoBtn) {
            undoBtn.addEventListener("click", () => editor.trigger("keyboard", "undo", null));
        }
        if (redoBtn) {
            redoBtn.addEventListener("click", () => editor.trigger("keyboard", "redo", null));
        }

    });
}

initPyodideAndEditor();
