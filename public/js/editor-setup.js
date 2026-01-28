async function initPyodideAndEditor() {

    /* ---------------- Pyodide ---------------- */

    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    // stdout → rechter Output-Container
    await pyodide.runPythonAsync(`
from js import document
import sys

class JSOut:
    def write(self, s):
        if s.strip():
            document.getElementById("output-container").innerText += s
    def flush(self): pass

sys.stdout = JSOut()
    `);

    /* -------- Hilfsfunktion: Fehler sauber parsen -------- */

function parsePythonError(message) {
    let line = 1;
    let error = "Python error";

    const lines = message.split("\n");

    for (const l of lines) {
        // SyntaxError
        let m = l.match(/File "<string>", line (\d+)/);
        if (m) line = parseInt(m[1], 10);

        // RuntimeError
        m = l.match(/File "<exec>", line (\d+)/);
        if (m) line = parseInt(m[1], 10);

        if (l.includes("Error:")) error = l.trim();
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

        const outputEl = document.getElementById("output-container");
        const lintEl   = document.getElementById("lint-container");

        /* -------- Live Syntaxcheck (nur compile, kein Run) -------- */

        let debounce;
        editor.onDidChangeModelContent(() => {
            clearTimeout(debounce);
            debounce = setTimeout(async () => {

                monaco.editor.setModelMarkers(editor.getModel(), "python", []);
                lintEl.innerText = "";

                const code = editor.getValue();

                try {
                    await pyodide.runPythonAsync(
                        `compile(${JSON.stringify(code)}, "<string>", "exec")`
                    );
                } catch (e) {
                    const parsed = parsePythonError(e.message);

                    lintEl.innerText =
                        `Zeile ${parsed.line}: ${parsed.error}`;

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

        /* ---------------- Run Button ---------------- */

        document.getElementById("run-btn").addEventListener("click", async () => {

            outputEl.innerText = "";
            lintEl.innerText   = "";
            monaco.editor.setModelMarkers(editor.getModel(), "python", []);

            const code = editor.getValue();

            /* 1️⃣ Syntaxcheck */
            try {
                await pyodide.runPythonAsync(
                    `compile(${JSON.stringify(code)}, "<string>", "exec")`
                );
            } catch (e) {
                const parsed = parsePythonError(e.message);

                lintEl.innerText =
                    `Zeile ${parsed.line}: ${parsed.error}`;

                monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                    startLineNumber: parsed.line,
                    startColumn: 1,
                    endLineNumber: parsed.line,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);

                return; // ❌ kein Run
            }

            /* 2️⃣ Ausführen */
            try {
                await pyodide.runPythonAsync(code);
            } catch (e) {
                const parsed = parsePythonError(e.message);

                lintEl.innerText =
                    `Zeile ${parsed.line}: ${parsed.error}`;

                monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                    startLineNumber: parsed.line,
                    startColumn: 1,
                    endLineNumber: parsed.line,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);
            }
        });

    }); // require
}

initPyodideAndEditor();
