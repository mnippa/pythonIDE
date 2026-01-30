// public/js/editor-setup.js (ES module)

async function initPyodideAndEditor() {
  /* ---------------- Pyodide ---------------- */
  const pyodide = await loadPyodide({ indexURL: "pyodide/" });
  console.log("Pyodide ready");

  await pyodide.loadPackage(["numpy", "matplotlib"]);

  /* ---------------- Error helpers ---------------- */
  function bestErrorLine(full) {
    const lines = String(full || "")
      .split("\n")
      .map(x => x.trim())
      .filter(Boolean);

    for (let i = lines.length - 1; i >= 0; i--) {
      if (lines[i].includes("Error") || lines[i].includes("Exception")) {
        return lines[i];
      }
    }
    return lines[lines.length - 1] || "Python error";
  }

  function extractSearchToken(errLine) {
    let m = errLine.match(/name '([^']+)'/);
    if (m) return m[1];
    m = errLine.match(/No module named '([^']+)'/);
    if (m) return m[1];
    m = errLine.match(/KeyError:\s*'([^']+)'/);
    if (m) return m[1];
    m = errLine.match(/attribute '([^']+)'/);
    if (m) return m[1];
    return null;
  }

  function escapeRegExp(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function findLineByToken(code, token) {
    const lines = String(code || "").split("\n");
    const re = new RegExp(`\\b${escapeRegExp(token)}\\b`);
    for (let i = 0; i < lines.length; i++) {
      if (re.test(lines[i])) return i + 1;
    }
    return 1;
  }

  function resolveErrorLine(message, code) {
    const s = String(message || "");

    let m = s.match(/File "<usercode>", line (\d+)/);
    if (m) return { line: +m[1], error: bestErrorLine(s) };

    m = s.match(/File "", line (\d+)/);
    if (m) return { line: +m[1], error: bestErrorLine(s) };

    const err = bestErrorLine(s);
    const token = extractSearchToken(err);
    return {
      line: token ? findLineByToken(code, token) : 1,
      error: err
    };
  }

  /* ---------------- Monaco ---------------- */
  require(["vs/editor/editor.main"], function () {
    const editor = monaco.editor.create(
      document.getElementById("editor-container"),
      {
        value: `import numpy as np
import matplotlib.pyplot as plt

x = np.linspace(0, 10, 100)

plt.figure()
plt.plot(x, np.sin(x))
plt.title("sin(x)")

plt.figure()
plt.plot(x, np.cos(x))
plt.title("cos(x)")

plt.show()
print("done")
`,
        language: "python",
        theme: "vs-dark",
        automaticLayout: true
      }
    );

    const outputEl = document.getElementById("output-container");
    const lintEl   = document.getElementById("lint-container");
    const plotEl   = document.getElementById("plot-container");

    function clearMarkers() {
      monaco.editor.setModelMarkers(editor.getModel(), "python", []);
    }

    function setErrorMarker(line, message) {
      const ln = Math.max(1, Number(line) || 1);
      monaco.editor.setModelMarkers(editor.getModel(), "python", [
        {
          severity: monaco.MarkerSeverity.Error,
          message,
          startLineNumber: ln,
          endLineNumber: ln,
          startColumn: 1,
          endColumn: 2
        }
      ]);
    }

function setLintOk() {
  lintEl.innerHTML =
    `<span style="color:#000;font-weight:600;">
       Syntaxcheck <span style="color:#22c55e;font-weight:700;">✓</span>
     </span>`;
}



    function setLintError(line, msg) {
      lintEl.innerText = `Zeile ${line}: ${msg}`;
    }

    /* -------- Live Syntax Bouncing -------- */
    let liveTimer = null;
    let liveSeq = 0;

    async function runLiveSyntaxCheck() {
      const seq = ++liveSeq;
      const code = editor.getValue();

      try {
        await pyodide.runPythonAsync(`
code = ${JSON.stringify(code)}
compile(code, "<usercode>", "exec")
`);
        if (seq !== liveSeq) return;
        clearMarkers();
        setLintOk();
      } catch (e) {
        if (seq !== liveSeq) return;
        const parsed = resolveErrorLine(e.message, code);
        setLintError(parsed.line, parsed.error);
        setErrorMarker(parsed.line, parsed.error);
      }
    }

    function scheduleLiveSyntaxCheck() {
      if (liveTimer) clearTimeout(liveTimer);
      liveTimer = setTimeout(runLiveSyntaxCheck, 300);
    }

    editor.onDidChangeModelContent(scheduleLiveSyntaxCheck);
    scheduleLiveSyntaxCheck();

    /* -------- Run -------- */
    document.getElementById("run-btn").addEventListener("click", async () => {
      outputEl.innerText = "";
      plotEl.innerHTML = "";

      liveSeq++;
      clearTimeout(liveTimer);

      await runLiveSyntaxCheck();
      if (monaco.editor.getModelMarkers({ resource: editor.getModel().uri }).length) {
        return;
      }

      const code = editor.getValue();

      try {
        await pyodide.runPythonAsync(`
from js import document
import sys, warnings

warnings.filterwarnings("ignore", message="FigureCanvasAgg is non-interactive")

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

from io import BytesIO
import base64

code = ${JSON.stringify(code)}

class JSOut:
    def __init__(self, el): self.el = document.getElementById(el)
    def write(self, s):
        s = str(s)
        if s.strip(): self.el.innerText += s + "\\n"
    def flush(self): pass

old_out, old_err = sys.stdout, sys.stderr
sys.stdout = JSOut("output-container")
sys.stderr = JSOut("lint-container")

try:
    g = {"__name__": "__main__"}
    exec(compile(code, "<usercode>", "exec"), g, g)

    for n in plt.get_fignums():
        fig = plt.figure(n)
        buf = BytesIO()
        fig.savefig(buf, format="png", bbox_inches="tight")
        buf.seek(0)

        data = "data:image/png;base64," + base64.b64encode(buf.read()).decode()
        card = document.createElement("div")
        card.className = "plot-card"

        h = document.createElement("div")
        h.className = "plot-card-header"
        h.innerHTML = "<strong>Figure " + str(n) + "</strong>"

        img = document.createElement("img")
        img.className = "plot-img"
        img.src = data

        card.appendChild(h)
        card.appendChild(img)
        document.getElementById("plot-container").appendChild(card)

    plt.close("all")

finally:
    sys.stdout, sys.stderr = old_out, old_err
`);
        setLintOk();
      } catch (e) {
        const parsed = resolveErrorLine(e.message, code);
        setLintError(parsed.line, parsed.error);
        setErrorMarker(parsed.line, parsed.error);
      }
    });
  });
}

initPyodideAndEditor();
