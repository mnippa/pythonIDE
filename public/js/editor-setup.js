async function initPyodideAndEditor() {

    /* ---------------- Pyodide ---------------- */
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    /* -------- Hilfsfunktion: Fehler sauber parsen -------- */
    function parsePythonError(message) {
        let line = 1;
        let error = "Python error";
        const lines = message.split("\n");
        for (const l of lines) {
            let m = l.match(/File "<string>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);
            m = l.match(/File "<exec>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);
            if (l.includes("Error:") || l.includes("Exception:")) error = l.trim();
        }
        return { line, error };
    }

    /* ---------------- Monaco ---------------- */
    require(["vs/editor/editor.main"], function () {

        const editor = monaco.editor.create(
            document.getElementById("editor-container"),
            {
                value: "# Python code hier\nprint('Hello World!')",
                language: "python",
                theme: "vs-dark",
                automaticLayout: true,
                tabSize: 4,
                insertSpaces: true
            }
        );

        const lintEl = document.getElementById("lint-container");
        const outputEl = document.getElementById("output-container");

        /* -------- Live Syntaxcheck + Unterwellen (kein Output) -------- */
        let debounce;
        editor.onDidChangeModelContent(() => {
            clearTimeout(debounce);
            debounce = setTimeout(async () => {
                monaco.editor.setModelMarkers(editor.getModel(), "python", []);
                const code = editor.getValue();
                try {
                    await pyodide.runPythonAsync(`compile(${JSON.stringify(code)}, "<string>", "exec")`);
                } catch (e) {
                    const parsed = parsePythonError(e.message);

                    // Unterwellen setzen
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

        /* ---------------- Hover Tooltip für Unterwellen ---------------- */
        monaco.languages.registerHoverProvider('python', {
            provideHover: function(model, position) {
                const markers = monaco.editor.getModelMarkers({ resource: model.uri });
                for (const m of markers) {
                    if (position.lineNumber === m.startLineNumber) {
                        return {
                            range: new monaco.Range(m.startLineNumber, 1, m.endLineNumber, 200),
                            contents: [{ value: m.message }]
                        };
                    }
                }
                return null;
            }
        });

        /* ---------------- Run Button ---------------- */
        document.getElementById("run-btn").addEventListener("click", async () => {

            // Rechte Ausgabe und Lintbereich leeren
            outputEl.innerText = "";
            lintEl.innerText   = "";
            monaco.editor.setModelMarkers(editor.getModel(), "python", []);

            const code = editor.getValue();

            /* 1️⃣ Syntaxcheck vor Run */
            try {
                await pyodide.runPythonAsync(`compile(${JSON.stringify(code)}, "<string>", "exec")`);
                lintEl.innerText = "Syntax-Check ✔️";
            } catch (e) {
                const parsed = parsePythonError(e.message);

                lintEl.innerText = `Zeile ${parsed.line}: ${parsed.error}`;
                monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                    startLineNumber: parsed.line,
                    startColumn: 1,
                    endLineNumber: parsed.line,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);

                return; // Kein Run bei Syntaxfehler
            }

            /* 2️⃣ Ausführen → rechter Output */
            try {
                await pyodide.runPythonAsync(`
from js import document
import sys

class JSOut:
    def __init__(self, element_id):
        self.el = document.getElementById(element_id)
    def write(self, s):
        if s.strip():
            self.el.innerText += s + "\\n"
    def flush(self): pass

# Backup global streams
old_out = sys.stdout
old_err = sys.stderr

# Setze temporäre Streams
sys.stdout = JSOut("output-container")
sys.stderr = JSOut("output-container")

try:
${code.split("\n").map(l => "    " + l).join("\n")}
finally:
    sys.stdout = old_out
    sys.stderr = old_err
                `);
            } catch (e) {
                const parsed = parsePythonError(e.message);

                // Runtime-Fehler: Begriff aus Meldung suchen
                let runtimeLine = parsed.line;
                const nameMatch = parsed.error.match(/name '(.*?)' is not defined/);
                if (nameMatch) {
                    const errorName = nameMatch[1];
                    const codeLines = editor.getValue().split("\n");
                    for (let i = 0; i < codeLines.length; i++) {
                        if (codeLines[i].includes(errorName)) {
                            runtimeLine = i + 1; // Editor-Zeilen zählen ab 1
                            break;
                        }
                    }
                }

                lintEl.innerText = `Zeile ${runtimeLine}: ${parsed.error}`;
                monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                    startLineNumber: runtimeLine,
                    startColumn: 1,
                    endLineNumber: runtimeLine,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);
            }

        });

    }); // require
}

initPyodideAndEditor();
