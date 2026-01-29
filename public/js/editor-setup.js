async function initPyodideAndEditor() {

    /* ---------------- Pyodide ---------------- */
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    /* -------- Fehlerparser -------- */
    function parsePythonError(message) {
        let line = 1;
        let error = "Python error";

        message.split("\n").forEach(l => {
            let m = l.match(/File "<string>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);

            m = l.match(/File "<exec>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);

            if (l.includes("Error:") || l.includes("Exception:")) {
                error = l.trim();
            }
        });

        return { line, error };
    }

    function extractNameError(message) {
        const name = message.match(/name '([^']+)' is not defined/);
        const suggestion = message.match(/Did you mean: '([^']+)'/);
        return {
            wrong: name ? name[1] : null,
            suggestion: suggestion ? suggestion[1] : null
        };
    }

    function findLineByToken(code, token) {
        const lines = code.split("\n");
        for (let i = 0; i < lines.length; i++) {
            if (lines[i].includes(token)) return i + 1;
        }
        return null;
    }

    /* ---------------- Monaco ---------------- */
    require(["vs/editor/editor.main"], function () {

        const editor = monaco.editor.create(
            document.getElementById("editor-container"),
            {
                value: "# Python code hier\nprint('Hello World!')",
                language: "python",
                theme: "vs-dark",
                automaticLayout: true
            }
        );

        const lintEl = document.getElementById("lint-container");
        const outputEl = document.getElementById("output-container");

        /* -------- Live Syntaxcheck -------- */
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

        /* ---------------- RUN ---------------- */
        document.getElementById("run-btn").addEventListener("click", async () => {

            outputEl.innerText = "";
            lintEl.innerText = "";
            monaco.editor.setModelMarkers(editor.getModel(), "python", []);

            const code = editor.getValue();

            /* 1️⃣ Syntaxcheck */
            try {
                await pyodide.runPythonAsync(
                    `compile(${JSON.stringify(code)}, "<string>", "exec")`
                );
            } catch (e) {
                const parsed = parsePythonError(e.message);
                lintEl.innerText = `Zeile ${parsed.line}: ${parsed.error}`;
                return;
            }

            /* 2️⃣ Ausführen */
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
                const { wrong, suggestion } = extractNameError(e.message);

                let line = parsed.line;

                if (wrong) {
                    const found = findLineByToken(code, wrong);
                    if (found) line = found;
                }

                /* ✨ AUTOKORREKTUR */
                if (wrong && suggestion && line) {
                    const model = editor.getModel();
                    const lineText = model.getLineContent(line);
                    const col = lineText.indexOf(wrong) + 1;

                    if (col > 0) {
                        editor.executeEdits("autocorrect", [{
                            range: new monaco.Range(
                                line,
                                col,
                                line,
                                col + wrong.length
                            ),
                            text: suggestion
                        }]);

                        lintEl.innerText =
                            `Autokorrektur: ${wrong} → ${suggestion} (Zeile ${line})`;

                       monaco.editor.setModelMarkers(model, "python", [{
    startLineNumber: line,
    startColumn: col,
    endLineNumber: line,
    endColumn: col + suggestion.length,
    message: "", // 👈 leer lassen → kein doppeltes Popup
    severity: monaco.MarkerSeverity.Info
}]);


                        editor.revealLineInCenter(line);
                        return;
                    }
                }

                /* Fallback: normaler Runtimefehler */
                lintEl.innerText = `Zeile ${line}: ${parsed.error}`;
                monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                    startLineNumber: line,
                    startColumn: 1,
                    endLineNumber: line,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);
            }
        });
    });
}

initPyodideAndEditor();
