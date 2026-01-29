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

    function extractNameErrorToken(message) {
        const m = message.match(/name '([^']+)' is not defined/);
        return m ? m[1] : null;
    }

    function findLineByToken(code, token) {
        const lines = code.split("\n");
        for (let i = 0; i < lines.length; i++) {
            if (lines[i].includes(token)) return i + 1;
        }
        return 1;
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

        /* -------- Live Syntaxcheck (nur compile) -------- */
        let debounce;
        editor.onDidChangeModelContent(() => {
            clearTimeout(debounce);
            debounce = setTimeout(async () => {

                monaco.editor.setModelMarkers(editor.getModel(), "python", []);
                const code = editor.getValue();

                try {
                    await pyodide.runPythonAsync(
                        `compile(${JSON.stringify(code)}, "<string>", "exec")`
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

        /* -------- Hover Tooltip -------- */
        monaco.languages.registerHoverProvider("python", {
            provideHover(model, position) {
                const markers = monaco.editor.getModelMarkers({ resource: model.uri });
                const m = markers.find(x => x.startLineNumber === position.lineNumber);
                if (!m) return null;

                return {
                    range: new monaco.Range(
                        m.startLineNumber, 1, m.endLineNumber, 200
                    ),
                    contents: [{ value: m.message }]
                };
            }
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
                const message = e.message;
                const parsed = parsePythonError(message);
                const token = extractNameErrorToken(message);

                let line = parsed.line;
                if (token) line = findLineByToken(code, token);

                lintEl.innerText = `Zeile ${line}: ${parsed.error}`;

                monaco.editor.setModelMarkers(editor.getModel(), "python", [{
                    startLineNumber: line,
                    startColumn: 1,
                    endLineNumber: line,
                    endColumn: 200,
                    message: parsed.error,
                    severity: monaco.MarkerSeverity.Error
                }]);

                /* 🔔 Bounce */
                setTimeout(() => {
                    const decos = editor.getLineDecorations(line) || [];
                    decos.forEach(d => {
                        const cls = d.options.glyphMarginClassName;
                        if (!cls) return;
                        const el = document.querySelector(`.${cls}`);
                        if (!el) return;
                        el.classList.add("bounce");
                        setTimeout(() => el.classList.remove("bounce"), 1000);
                    });
                }, 50);
            }
        });
    });
}

initPyodideAndEditor();
