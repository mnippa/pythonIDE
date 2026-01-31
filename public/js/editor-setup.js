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
      .map((x) => x.trim())
      .filter(Boolean);

    for (let i = lines.length - 1; i >= 0; i--) {
      if (lines[i].includes("Error") || lines[i].includes("Exception")) return lines[i];
    }
    return lines[lines.length - 1] || "Python error";
  }

  function extractNameErrorToken(errLine) {
    const m = String(errLine || "").match(/NameError:\s*name '([^']+)' is not defined/);
    return m ? m[1] : null;
  }

  function extractSearchToken(errLine) {
    let m = String(errLine || "").match(/name '([^']+)'/);
    if (m) return m[1];

    m = String(errLine || "").match(/No module named '([^']+)'/);
    if (m) return m[1];

    m = String(errLine || "").match(/KeyError:\s*'([^']+)'/);
    if (m) return m[1];

    m = String(errLine || "").match(/attribute '([^']+)'/);
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

  // ---- Levenshtein + builtin suggestion (pint -> print) ----
  function levenshtein(a, b) {
    a = String(a);
    b = String(b);
    const m = a.length,
      n = b.length;
    const dp = Array.from({ length: m + 1 }, () => Array(n + 1).fill(0));
    for (let i = 0; i <= m; i++) dp[i][0] = i;
    for (let j = 0; j <= n; j++) dp[0][j] = j;
    for (let i = 1; i <= m; i++) {
      for (let j = 1; j <= n; j++) {
        const cost = a[i - 1] === b[j - 1] ? 0 : 1;
        dp[i][j] = Math.min(dp[i - 1][j] + 1, dp[i][j - 1] + 1, dp[i - 1][j - 1] + cost);
      }
    }
    return dp[m][n];
  }

  function suggestBuiltinName(token) {
    if (!token) return null;
    const builtins = [
      "print",
      "range",
      "len",
      "list",
      "dict",
      "set",
      "str",
      "int",
      "float",
      "sum",
      "min",
      "max",
      "abs",
      "sorted",
      "enumerate",
      "zip",
      "map",
      "filter",
      "any",
      "all",
      "round",
    ];

    let best = null;
    let bestDist = Infinity;

    for (const b of builtins) {
      const d = levenshtein(token, b);
      if (d < bestDist) {
        bestDist = d;
        best = b;
      }
    }
    return bestDist <= 2 ? best : null;
  }

  // ✅ Always returns: line + error + token + suggestion + hint
  function resolveErrorLine(message, code) {
    const s = String(message || "");
    const errLine = bestErrorLine(s);

    const nameToken = extractNameErrorToken(errLine) || extractSearchToken(errLine);
    const suggestion = suggestBuiltinName(nameToken);
    const hint =
      suggestion && nameToken && suggestion !== nameToken ? `Meintest du: ${suggestion} ?` : null;

    let m = s.match(/File "<usercode>", line (\d+)/);
    if (m) {
      return {
        line: parseInt(m[1], 10),
        error: errLine,
        token: nameToken,
        suggestion,
        hint,
        mode: "usercode",
      };
    }

    m = s.match(/File "", line (\d+)/);
    if (m) {
      return {
        line: parseInt(m[1], 10),
        error: errLine,
        token: nameToken,
        suggestion,
        hint,
        mode: "emptyfile",
      };
    }

    const foundLine = nameToken ? findLineByToken(code, nameToken) : 1;
    return {
      line: foundLine,
      error: errLine,
      token: nameToken,
      suggestion,
      hint,
      mode: "search",
    };
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /* ============================================================
     ✨ Static Context Suggestions + Snippets (np. / plt. / ax. / builtins)
     ============================================================ */

  // Basic method lists (non-snippet)
  const NUMPY_COMPLETIONS = [
    "array",
    "arange",
    "zeros",
    "ones",
    "empty",
    "eye",
    "linspace",
    "logspace",
    "reshape",
    "transpose",
    "concatenate",
    "sin",
    "cos",
    "exp",
    "log",
    "sqrt",
    "abs",
    "sum",
    "mean",
    "min",
    "max",
    "std",
    "var",
    "where",
    "clip",
    "unique",
    "argsort",
    "random",
  ];

  const PLT_COMPLETIONS = [
    "figure",
    "subplots",
    "plot",
    "scatter",
    "bar",
    "hist",
    "imshow",
    "title",
    "xlabel",
    "ylabel",
    "legend",
    "grid",
    "xlim",
    "ylim",
    "tight_layout",
    "savefig",
    "show",
    "close",
    "clf",
    "cla",
    "colorbar",
  ];

  const AX_COMPLETIONS = [
    "plot",
    "scatter",
    "bar",
    "hist",
    "imshow",
    "set_title",
    "set_xlabel",
    "set_ylabel",
    "legend",
    "grid",
    "set_xlim",
    "set_ylim",
  ];

  const BUILTIN_COMPLETIONS = [
    "print",
    "len",
    "range",
    "enumerate",
    "zip",
    "list",
    "dict",
    "set",
    "tuple",
    "int",
    "float",
    "str",
    "bool",
    "sum",
    "min",
    "max",
    "abs",
    "round",
    "sorted",
    "map",
    "filter",
    "any",
    "all",
  ];

  // Snippet entries
  const NP_SNIPPETS = [
    { label: "linspace(start, stop, num)", insert: "linspace(${1:start}, ${2:stop}, ${3:num})", doc: "Evenly spaced numbers." },
    { label: "arange(start, stop, step)", insert: "arange(${1:start}, ${2:stop}, ${3:step})", doc: "Evenly spaced values." },
    { label: "zeros(shape)", insert: "zeros(${1:shape})", doc: "Array of zeros." },
    { label: "ones(shape)", insert: "ones(${1:shape})", doc: "Array of ones." },
    { label: "array(obj)", insert: "array(${1:obj})", doc: "Create an array." },
    { label: "where(condition, x, y)", insert: "where(${1:condition}, ${2:x}, ${3:y})", doc: "Choose x/y by condition." },
  ];

  const PLT_SNIPPETS = [
    { label: "plot(x, y)", insert: "plot(${1:x}, ${2:y})", doc: "Plot y versus x." },
    { label: "scatter(x, y)", insert: "scatter(${1:x}, ${2:y})", doc: "Scatter plot." },
    { label: "subplots(nrows, ncols)", insert: "subplots(${1:nrows}, ${2:ncols})", doc: "Create figure + axes." },
    { label: "figure()", insert: "figure()", doc: "Create a new figure." },
    { label: "title(text)", insert: 'title("${1:title}")', doc: "Set title." },
    { label: "xlabel(text)", insert: 'xlabel("${1:xlabel}")', doc: "Set x label." },
    { label: "ylabel(text)", insert: 'ylabel("${1:ylabel}")', doc: "Set y label." },
    { label: "savefig(filename)", insert: 'savefig("${1:figure}.png")', doc: "Save current figure." },
    { label: "show()", insert: "show()", doc: "Display figures." },
  ];

  // ✅ NEW: ax-snippets (most useful patterns)
  const AX_SNIPPETS = [
    {
      label: "fig, ax = plt.subplots()",
      insert: "fig, ax = plt.subplots(${1:nrows}, ${2:ncols})",
      doc: "Create fig/ax with subplots.",
    },
    {
      label: "ax.plot(x, y)",
      insert: "ax.plot(${1:x}, ${2:y})",
      doc: "Plot on axes.",
    },
    {
      label: "ax.scatter(x, y)",
      insert: "ax.scatter(${1:x}, ${2:y})",
      doc: "Scatter on axes.",
    },
    {
      label: "ax.set_title(text)",
      insert: 'ax.set_title("${1:title}")',
      doc: "Set axes title.",
    },
    {
      label: "ax.set_xlabel(text)",
      insert: 'ax.set_xlabel("${1:xlabel}")',
      doc: "Set x label.",
    },
    {
      label: "ax.set_ylabel(text)",
      insert: 'ax.set_ylabel("${1:ylabel}")',
      doc: "Set y label.",
    },
    {
      label: "ax.grid(True)",
      insert: "ax.grid(True)",
      doc: "Enable grid.",
    },
    {
      label: "ax.legend()",
      insert: "ax.legend()",
      doc: "Show legend.",
    },
    {
      label: "fig.tight_layout()",
      insert: "fig.tight_layout()",
      doc: "Tight layout on figure.",
    },
    {
      label: "fig.savefig(filename)",
      insert: 'fig.savefig("${1:figure}.png", dpi=${2:150}, bbox_inches="tight")',
      doc: "Save figure with dpi and tight bbox.",
    },
    {
      label: "ax.set_xlim(min, max)",
      insert: "ax.set_xlim(${1:xmin}, ${2:xmax})",
      doc: "Set x limits.",
    },
    {
      label: "ax.set_ylim(min, max)",
      insert: "ax.set_ylim(${1:ymin}, ${2:ymax})",
      doc: "Set y limits.",
    },
    {
      label: "ax.axhline(y=...)",
      insert: "ax.axhline(y=${1:y}, linestyle='${2:--}', linewidth=${3:1})",
      doc: "Horizontal reference line.",
    },
    {
      label: "ax.axvline(x=...)",
      insert: "ax.axvline(x=${1:x}, linestyle='${2:--}', linewidth=${3:1})",
      doc: "Vertical reference line.",
    },
  ];

  const BUILTIN_SNIPPETS = [
    { label: "print(x)", insert: "print(${1:x})", doc: "Print objects." },
    { label: "len(obj)", insert: "len(${1:obj})", doc: "Length." },
    { label: "range(stop)", insert: "range(${1:stop})", doc: "Range iterator." },
    { label: "for i in range(n)", insert: "for ${1:i} in range(${2:n}):\n\t${3:pass}", doc: "For-loop." },
    { label: "if condition:", insert: "if ${1:condition}:\n\t${2:pass}", doc: "If statement." },
    { label: "def func():", insert: "def ${1:func}(${2:args}):\n\t${3:pass}", doc: "Function definition." },
  ];

  /* ---------------- Monaco ---------------- */
  require(["vs/editor/editor.main"], function () {
    const editor = monaco.editor.create(document.getElementById("editor-container"), {
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

# runtime typo test:
# pint("hello")
`,
      language: "python",
      theme: "vs-dark",
      automaticLayout: true,
      lightbulb: { enabled: false },
    });

    const outputEl = document.getElementById("output-container");
    const lintEl = document.getElementById("lint-container");
    const plotEl = document.getElementById("plot-container");

    function clearMarkers() {
      monaco.editor.setModelMarkers(editor.getModel(), "python", []);
    }

    function hasAnyMarkers() {
      const model = editor.getModel();
      const markers = monaco.editor.getModelMarkers({ resource: model.uri });
      return markers && markers.length > 0;
    }

    function findTokenRangeInModel(model, lineNumber, token) {
      if (!token) return null;
      const lineText = model.getLineContent(lineNumber);
      const re = new RegExp(`\\b${escapeRegExp(token)}\\b`);
      const m = re.exec(lineText);
      if (!m) return null;
      const start = (m.index ?? 0) + 1;
      const end = start + token.length;
      return new monaco.Range(lineNumber, start, lineNumber, end);
    }

    /* ============================================================
       ✅ Context Suggestions Provider (with Snippets + ax.)
       ============================================================ */
    const InsertAsSnippet = monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet;

    function mkMethodSuggestion(name, detail) {
      return {
        label: name,
        kind: monaco.languages.CompletionItemKind.Function,
        insertText: name,
        detail,
      };
    }

    function mkSnippetSuggestion(label, snippet, detail, documentation) {
      return {
        label,
        kind: monaco.languages.CompletionItemKind.Snippet,
        insertText: snippet,
        insertTextRules: InsertAsSnippet,
        detail,
        documentation: documentation ? { value: documentation } : undefined,
      };
    }

    monaco.languages.registerCompletionItemProvider("python", {
      triggerCharacters: [".", "_", "("],

      provideCompletionItems(model, position) {
        const line = model.getLineContent(position.lineNumber);
        const prefix = line.slice(0, position.column - 1);

        const fullText = model.getValue();
        const hasNp = /\bimport\s+numpy\s+as\s+np\b/.test(fullText);
        const hasPlt = /\bimport\s+matplotlib\.pyplot\s+as\s+plt\b/.test(fullText);

        // "ax-aware": offer ax completions if user uses ax variable (common)
        const hasAx =
          /\bax\s*=\s*plt\.subplots\b/.test(fullText) ||
          /\bfig\s*,\s*ax\s*=\s*plt\.subplots\b/.test(fullText) ||
          /\bax\b/.test(fullText);

        let suggestions = [];

        if (hasNp && /\bnp\.\w*$/.test(prefix)) {
          suggestions = [
            ...NP_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "NumPy (snippet)", s.doc)),
            ...NUMPY_COMPLETIONS.map((n) => mkMethodSuggestion(n, "NumPy")),
          ];
        } else if (hasPlt && /\bplt\.\w*$/.test(prefix)) {
          suggestions = [
            ...PLT_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "matplotlib.pyplot (snippet)", s.doc)),
            ...PLT_COMPLETIONS.map((n) => mkMethodSuggestion(n, "matplotlib.pyplot")),
          ];
        } else if (hasAx && /\bax\.\w*$/.test(prefix)) {
          suggestions = [
            ...AX_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Axes (snippet)", s.doc)),
            ...AX_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Axes")),
          ];
        } else {
          suggestions = [
            ...BUILTIN_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Python (snippet)", s.doc)),
            ...BUILTIN_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Python builtin")),
          ];
        }

        return { suggestions };
      },
    });

    /* ---------------- QuickFix cache (Lint click + Ctrl+.) ---------------- */
    const quickFixState = { line: null, token: null, suggestion: null };

    function clearQuickFixState() {
      quickFixState.line = null;
      quickFixState.token = null;
      quickFixState.suggestion = null;
    }

    function setQuickFixState(line, token, suggestion) {
      if (line && token && suggestion && suggestion !== token) {
        quickFixState.line = line;
        quickFixState.token = token;
        quickFixState.suggestion = suggestion;
      } else {
        clearQuickFixState();
      }
    }

    function setErrorMarker(line, message) {
      const model = editor.getModel();
      const ln = Math.max(1, Number(line) || 1);

      monaco.editor.setModelMarkers(model, "python", [
        {
          severity: monaco.MarkerSeverity.Error,
          message: message || "Error",
          startLineNumber: ln,
          endLineNumber: ln,
          startColumn: 1,
          endColumn: Math.min(2, model.getLineMaxColumn(ln)),
        },
      ]);
    }

    /* ---------------- Lint UI helpers ---------------- */
    function setLintChecking() {
      lintEl.innerHTML = `<span style="color:#6b7280;font-weight:500;">Prüfe…</span>`;
    }

    function setLintOk() {
      lintEl.innerHTML = `<span style="color:#000;font-weight:600;">
        Syntaxcheck <span style="color:#22c55e;font-weight:700;">✓</span>
      </span>`;
    }

    function setLintError(line, msg, hint = null, token = null, suggestion = null) {
      const hasFix = !!(token && suggestion && suggestion !== token);

      lintEl.innerHTML =
        `<div style="white-space:pre-wrap;">Zeile ${line}: ${escapeHtml(msg || "")}</div>` +
        (hint ? `<div style="margin-top:6px; white-space:pre-wrap;">${escapeHtml(hint)}</div>` : "") +
        (hasFix
          ? `<div style="margin-top:8px;">
               <span style="color:#111;font-weight:600;">Quick Fix:</span>
               <span id="lint-fix"
                     title="Klick/Doppelklick oder Ctrl+."
                     style="cursor:pointer; text-decoration:underline; color:#2563eb;">
                 Replace '${escapeHtml(token)}' → '${escapeHtml(suggestion)}'
               </span>
             </div>`
          : "");
    }

    function applyQuickFix() {
      const { line, token, suggestion } = quickFixState;
      if (!line || !token || !suggestion) return;

      const model = editor.getModel();
      const range = findTokenRangeInModel(model, line, token);
      if (!range) return;

      model.pushEditOperations([], [{ range, text: suggestion }], () => null);
      scheduleLiveSyntaxCheck();
    }

    // Ctrl+. robust (Layouts)
    editor.onKeyDown((e) => {
      const be = e.browserEvent;
      const ctrl = be.ctrlKey || be.metaKey;

      const isDot =
        be.key === "." ||
        be.code === "Period" ||
        e.keyCode === monaco.KeyCode.Period ||
        e.keyCode === monaco.KeyCode.OEM_PERIOD;

      if (ctrl && isDot && quickFixState.suggestion) {
        e.preventDefault();
        e.stopPropagation();
        applyQuickFix();
      }
    });

    lintEl.addEventListener("click", (ev) => {
      const t = ev.target;
      if (t && t.id === "lint-fix" && quickFixState.suggestion) applyQuickFix();
    });
    lintEl.addEventListener("dblclick", (ev) => {
      const t = ev.target;
      if (t && t.id === "lint-fix" && quickFixState.suggestion) applyQuickFix();
    });

    /* ---------------- Live Syntax Bouncing ---------------- */
    let liveTimer = null;
    let liveSeq = 0;

    async function runLiveSyntaxCheck({ quietOk = false } = {}) {
      const seq = ++liveSeq;
      const code = editor.getValue();

      try {
        await pyodide.runPythonAsync(`
code = ${JSON.stringify(code)}
compile(code, "<usercode>", "exec")
`);
        if (seq !== liveSeq) return { ok: true };

        clearMarkers();
        clearQuickFixState();

        if (!quietOk) setLintOk();
        return { ok: true };
      } catch (e) {
        if (seq !== liveSeq) return { ok: false };

        const parsed = resolveErrorLine(e.message || String(e), code);

        clearQuickFixState();
        setLintError(parsed.line, parsed.error, null, null, null);
        setErrorMarker(parsed.line, parsed.error);

        return { ok: false, ...parsed };
      }
    }

    function scheduleLiveSyntaxCheck() {
      if (liveTimer) clearTimeout(liveTimer);
      setLintChecking();
      liveTimer = setTimeout(() => runLiveSyntaxCheck({ quietOk: false }), 300);
    }

    editor.onDidChangeModelContent(scheduleLiveSyntaxCheck);
    scheduleLiveSyntaxCheck();

    /* ---------------- Run ---------------- */
    document.getElementById("run-btn").addEventListener("click", async () => {
      outputEl.innerText = "";
      plotEl.innerHTML = "";
      setLintChecking();

      liveSeq++;
      clearTimeout(liveTimer);

      const syntax = await runLiveSyntaxCheck({ quietOk: false });
      if (!syntax.ok || hasAnyMarkers()) return;

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
    def __init__(self, el):
        self.el = document.getElementById(el)
    def write(self, s):
        s = str(s)
        if s.strip():
            self.el.innerText += s + "\\n"
    def flush(self):
        pass

old_out, old_err = sys.stdout, sys.stderr
sys.stdout = JSOut("output-container")
sys.stderr = JSOut("lint-container")

try:
    g = {"__name__": "__main__"}
    exec(compile(code, "<usercode>", "exec"), g, g)

    fignums = list(plt.get_fignums())
    if fignums:
        container = document.getElementById("plot-container")

        for n in fignums:
            fig = plt.figure(n)
            buf = BytesIO()
            fig.savefig(buf, format="png", bbox_inches="tight")
            buf.seek(0)
            b64 = base64.b64encode(buf.read()).decode("ascii")
            data_url = "data:image/png;base64," + b64

            card = document.createElement("div")
            card.className = "plot-card"

            header = document.createElement("div")
            header.className = "plot-card-header"
            header.innerHTML = "<strong>Figure " + str(n) + "</strong>"

            img = document.createElement("img")
            img.className = "plot-img"
            img.src = data_url

            card.appendChild(header)
            card.appendChild(img)
            container.appendChild(card)

        plt.close("all")

finally:
    sys.stdout, sys.stderr = old_out, old_err
`);
        clearQuickFixState();
        setLintOk();
      } catch (e) {
        const parsed = resolveErrorLine(e.message || String(e), code);

        setQuickFixState(parsed.line, parsed.token, parsed.suggestion);
        setLintError(parsed.line, parsed.error, parsed.hint, parsed.token, parsed.suggestion);
        setErrorMarker(parsed.line, parsed.error);
      }
    });
  });
}

initPyodideAndEditor();
