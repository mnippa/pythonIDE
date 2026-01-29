async function initPyodideAndEditor() {

    /* ---------------- Pyodide ---------------- */
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    /* ---------------- Fehlerparser ---------------- */
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
        return {
            wrong: message.match(/name '([^']+)' is not defined/)?.[1] ?? null,
            suggestion: message.match(/Did you mean: '([^']+)'/)?.[1] ?? null
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

        const model = editor.getModel();
        const lintEl = document.getElementById("lint-container");
        const outputEl = document.getElementById("output-container");

        const undoBtn = document.getElementById("undo-btn");
        const redoBtn = document.getElementById("redo-btn");

        /* ---------------- Undo / Redo ---------------- */
        function updateUndoRedoButtons() {
            undoBtn.disabled = !model.canUndo();
            redoBtn.disabled = !model.canRedo();
        }

        undoBtn.onclick = () => editor.trigger("ui", "undo");
        redoBtn.onclick = () => editor.trigger("ui", "redo");

        editor.onDidChangeModelContent(updateUndoRedoButtons);
        updateUndoRedoButtons();

        /* ---------------- Live Syntaxcheck (grün) ---------------- */
        let debounce;
        editor.onDidChangeModelContent(() => {
            clearTimeout(debounce);
            debounce = setTimeout(async () => {

                monaco.editor.setModelMarkers(model, "syntax", []);

                try {
                    await pyodide.runPythonAsync(
                        `compile(${JSON.stringify(editor.getValue())}, "<string>", "exec")`
                    );

                    monaco.editor.setModelMarkers(model, "syntax", [{
                        startLineNumber: 1,
                        startColumn: 1,
                        endLineNumber: 1,
                        endColumn: 1,
                        message: "Syntax OK",
                        severity: monaco.MarkerSeverity.Hint   // 🟢
                    }]);

                } catch (e) {
                    const parsed = parsePythonError(e.message);
                    monaco.editor.setModelMarkers(model, "syntax", [{
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

            monaco.editor.setModelMarkers(model, "runtime", []);
            monaco.editor.setModelMarkers(model, "info", []);

            const code = editor.getValue();

            /* 1️⃣ Syntaxcheck */
            try {
                await pyodide.runPythonAsync(
                    `compile(${JSON.stringify(code)}, "<string>", "exec")`
                );
                lintEl.innerHTML = `<span style="color:green">Syntax-Check ✔️</span>`;
            } catch (e) {
                const parsed = parsePythonError(e.message);
                lintEl.innerText = `Zeile ${parsed.line}: ${parsed.error}`;
                return;
            }

            /* 2️⃣ Run */
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

                /* ✨ Autokorrektur */
                if (wrong && suggestion && line) {

                    const text = model.getLineContent(line);
                    const col = text.indexOf(wrong) + 1;

                    if (col > 0) {
                        editor.executeEdits("autocorrect", [{
                            range: new monaco.Range(
                                line, col,
                                line, col + wrong.length
                            ),
                            text: suggestion
                        }]);

                        lintEl.innerText =
                            `Autokorrektur: ${wrong} → ${suggestion} (Zeile ${line})`;

                        monaco.editor.setModelMarkers(model, "info", [{
                            startLineNumber: line,
                            startColumn: col,
                            endLineNumber: line,
                            endColumn: col + suggestion.length,
                            message: "",
                            severity: monaco.MarkerSeverity.Info
                        }]);

                        editor.revealLineInCenter(line);
                        updateUndoRedoButtons();
                        return;
                    }
                }

                /* Fallback Runtimefehler */
                lintEl.innerText = `Zeile ${line}: ${parsed.error}`;
                monaco.editor.setModelMarkers(model, "runtime", [{
                    startLineNumber: line,
                    startColumn: 1,
                    endLineNumber: line,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);

                editor.revealLineInCenter(line);
            }
        });
    });
}

initPyodideAndEditor();
