async function initPyodideAndEditor() {
    // --- Pyodide laden ---
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    // Pakete laden
    await pyodide.loadPackage("micropip");
    await pyodide.runPythonAsync(`
import micropip
await micropip.install('black')
`);

    // --- Setup Output Container ---
    async function setupPyodideOutput() {
        await pyodide.runPythonAsync(`
from js import document
class JSOutput:
    def write(self, s):
        if s.strip() != "":
            document.getElementById("output-container").innerText += s
    def flush(self): pass

import sys
sys.stdout = JSOutput()
# Runtime-Fehler werden über JS catch behandelt
        `);
    }
    await setupPyodideOutput();

    // --- Monaco Editor ---
    require(["vs/editor/editor.main"], function () {
        const editor = monaco.editor.create(document.getElementById("editor-container"), {
            value: "# Python code hier\nprint('Hello World!')",
            language: "python",
            theme: "vs-dark",
            automaticLayout: true,
            tabSize: 4,
            insertSpaces: true,
        });

        const outputEl = document.getElementById("output-container");
        const lintEl = document.getElementById("lint-container");

        // --- Live Toggle ---
        let liveOutput = false;
        const toggleBtn = document.getElementById("toggle-live");
        toggleBtn.addEventListener("click", () => {
            liveOutput = !liveOutput;
            toggleBtn.textContent = `Live Output: ${liveOutput ? "ON" : "OFF"}`;
        });

        // --- Autocomplete ---
        const pythonKeywords = ['print','def','import','for','if','else','elif','while','class','try','except','return','with','as'];
        function parseIdentifiers(code) {
            const identifiers = new Set();
            const varRegex = /^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=/gm;
            const funcRegex = /^\s*def\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/gm;
            let match;
            while ((match = varRegex.exec(code)) !== null) identifiers.add(match[1]);
            while ((match = funcRegex.exec(code)) !== null) identifiers.add(match[1]);
            return Array.from(identifiers);
        }
        monaco.languages.registerCompletionItemProvider('python', {
            provideCompletionItems: function(model, position) {
                const code = model.getValue();
                const identifiers = parseIdentifiers(code);
                const suggestions = [];
                pythonKeywords.forEach(k => suggestions.push({label:k,kind:monaco.languages.CompletionItemKind.Keyword,insertText:k}));
                identifiers.forEach(id => suggestions.push({label:id,kind:monaco.languages.CompletionItemKind.Variable,insertText:id}));
                return { suggestions };
            }
        });

        // --- Live Syntaxprüfung beim Tippen ---
        let syntaxDebounce;
        editor.onDidChangeModelContent(() => {
            clearTimeout(syntaxDebounce);
            syntaxDebounce = setTimeout(async () => {
                const code = editor.getValue();
                monaco.editor.setModelMarkers(editor.getModel(), 'python', []);
                lintEl.innerText = "";
                try {
                    const codeJSON = JSON.stringify(code);
                    await pyodide.runPythonAsync(`compile(${codeJSON}, "<string>", "exec")`);
                } catch(e) {
                    lintEl.innerText = e.message;
                    const line = e.lineNumber || 1;
                    monaco.editor.setModelMarkers(editor.getModel(), 'python', [{
                        startLineNumber: line,
                        startColumn: 1,
                        endLineNumber: line,
                        endColumn: 100,
                        message: e.message,
                        severity: monaco.MarkerSeverity.Error
                    }]);
                }
            }, 500); // 500ms Debounce
        });

        // --- Run Button ---
        document.getElementById("run-btn").addEventListener("click", async () => {
            outputEl.innerText = "";
            lintEl.innerText = "";
            monaco.editor.setModelMarkers(editor.getModel(), 'python', []);

            const code = editor.getValue();

            // 1️⃣ Syntax Check vor Run
            try {
                const codeJSON = JSON.stringify(code);
                await pyodide.runPythonAsync(`compile(${codeJSON}, "<string>", "exec")`);
            } catch(e) {
                lintEl.innerText = e.message;
                const line = e.lineNumber || 1;
                monaco.editor.setModelMarkers(editor.getModel(), 'python', [{
                    startLineNumber: line,
                    startColumn: 1,
                    endLineNumber: line,
                    endColumn: 100,
                    message: e.message,
                    severity: monaco.MarkerSeverity.Error
                }]);
                return; // Syntaxfehler → Abbruch
            }

            // 2️⃣ Runtime Run
            try {
                await pyodide.runPythonAsync(code);
            } catch(e) {
                // Runtime-Fehler → nur relevante Zeile unten
                let lines = e.message.split("\n");
                let relevant = lines.find(l => !l.includes("Traceback") && !l.startsWith("  File")) || lines[lines.length-1];
                lintEl.innerText = relevant;

                const lineMatch = e.message.match(/<string>, line (\d+)/);
                let line = 1;
                if(lineMatch) line = parseInt(lineMatch[1]);
                monaco.editor.setModelMarkers(editor.getModel(), 'python', [{
                    startLineNumber: line,
                    startColumn: 1,
                    endLineNumber: line,
                    endColumn: 100,
                    message: relevant,
                    severity: monaco.MarkerSeverity.Error
                }]);
            }
        });

        // --- Format Button ---
        document.getElementById("format-btn").addEventListener("click", async () => {
            try {
                const code = editor.getValue();
                const formatted = await pyodide.runPythonAsync(`
import black
black.format_str("""${code}""", mode=black.FileMode())
                `);
                editor.setValue(formatted);
            } catch(e) {
                lintEl.innerText += "Formatierung fehlgeschlagen: " + e + "\n";
            }
        });

    }); // require
} // init
initPyodideAndEditor();
