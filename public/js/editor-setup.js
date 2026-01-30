async function initPyodideAndEditor() {

    /* ---------------- Pyodide ---------------- */
    const pyodide = await loadPyodide({ indexURL: "pyodide/" });
    console.log("Pyodide ready");

    // Benötigte Pakete laden
    await pyodide.loadPackage(["numpy", "matplotlib"]);

    /* -------- Fehlerparser -------- */
    function parsePythonError(message) {
        let line = 1;
        let error = "Python error";

        message.split("\n").forEach(l => {
            let m = l.match(/File "<string>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);

            m = l.match(/File "<exec>", line (\d+)/);
            if (m) line = parseInt(m[1], 10);

            if (l.includes("Error") || l.includes("Exception")) {
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
`import numpy as np
import matplotlib.pyplot as plt

x = np.linspace(0, 10, 100)
y = np.sin(x)

plt.plot(x, y)
plt.title("Beispielplot")
plt.show()`,
                language: "python",
                theme: "vs-dark",
                automaticLayout: true
            }
        );

        const outputEl = document.getElementById("output-container");
        const lintEl   = document.getElementById("lint-container");
        const plotEl   = document.getElementById("plot-container");

        /* -------- RUN -------- */
        document.getElementById("run-btn").addEventListener("click", async () => {

            outputEl.innerText = "";
            lintEl.innerText = "";
            plotEl.innerHTML = "";

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
import warnings

# 🔕 Nur diese Matplotlib-Warnung unterdrücken
warnings.filterwarnings(
    "ignore",
    message="FigureCanvasAgg is non-interactive"
)

# matplotlib auf Agg festnageln
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from io import BytesIO
import base64

class JSOut:
    def __init__(self, el):
        self.el = document.getElementById(el)
    def write(self, s):
        if s.strip():
            self.el.innerText += s + "\\n"
    def flush(self): pass

old_out, old_err = sys.stdout, sys.stderr
sys.stdout = JSOut("output-container")
sys.stderr = JSOut("lint-container")

try:
${code.split("\n").map(l => "    " + l).join("\n")}

    # 👉 Falls ein Plot existiert: rendern
    if plt.get_fignums():
        buf = BytesIO()
        plt.savefig(buf, format="png", bbox_inches="tight")
        plt.close("all")
        buf.seek(0)

        img = document.createElement("img")
        img.src = "data:image/png;base64," + base64.b64encode(buf.read()).decode("ascii")
        img.style.maxWidth = "100%"
        document.getElementById("plot-container").appendChild(img)

finally:
    sys.stdout, sys.stderr = old_out, old_err
                `);

            } catch (e) {
                const parsed = parsePythonError(e.message);
                lintEl.innerText = `Zeile ${parsed.line}: ${parsed.error}`;
            }

        });

    });
}

initPyodideAndEditor();
