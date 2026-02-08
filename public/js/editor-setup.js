// public/js/editor-setup.js (ES module)

async function initPyodideAndEditor() {
  /* ---------------- Pyodide ---------------- */
  const pyodide = await loadPyodide({ indexURL: "pyodide/" });
  console.log("Pyodide ready");

  const loadedPackages = new Set();
  const moduleCheckboxes = {
    numpy: document.getElementById("pkg-numpy"),
    matplotlib: document.getElementById("pkg-matplotlib"),
    pandas: document.getElementById("pkg-pandas"),
    panel: document.getElementById("pkg-panel"),
    seaborn: document.getElementById("pkg-seaborn"),
  };
  const availablePackages = new Set(["numpy", "matplotlib", "pandas"]);

  function applyPackageAvailability() {
    Object.entries(moduleCheckboxes).forEach(([name, checkbox]) => {
      if (!checkbox) return;
      if (!availablePackages.has(name)) {
        checkbox.checked = false;
        checkbox.disabled = true;
      }
    });
  }

  function getSelectedPackages() {
    const packages = [];
    if (moduleCheckboxes.numpy?.checked) packages.push("numpy");
    if (moduleCheckboxes.matplotlib?.checked) packages.push("matplotlib");
    if (moduleCheckboxes.pandas?.checked) packages.push("pandas");
    if (moduleCheckboxes.panel?.checked) packages.push("panel");
    if (moduleCheckboxes.seaborn?.checked) packages.push("seaborn");
    return packages;
  }

  async function ensurePackages(packages) {
    const toLoad = (packages || []).filter((pkg) => !loadedPackages.has(pkg));
    if (!toLoad.length) return;
    await pyodide.loadPackage(toLoad);
    toLoad.forEach((pkg) => loadedPackages.add(pkg));
  }

  const settingsToggle = document.getElementById("settings-toggle");
  const settingsPanel = document.getElementById("settings-panel");

  function closeSettingsPanel() {
    if (!settingsPanel) return;
    settingsPanel.classList.remove("open");
    settingsPanel.setAttribute("aria-hidden", "true");
  }

  settingsToggle?.addEventListener("click", (event) => {
    event.stopPropagation();
    if (!settingsPanel) return;
    const isOpen = settingsPanel.classList.toggle("open");
    settingsPanel.setAttribute("aria-hidden", String(!isOpen));
  });

  document.addEventListener("click", (event) => {
    if (!settingsPanel || !settingsToggle) return;
    if (settingsPanel.contains(event.target) || settingsToggle.contains(event.target)) return;
    closeSettingsPanel();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeSettingsPanel();
  });

  applyPackageAvailability();
  await ensurePackages(getSelectedPackages());

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

  /* Store reference for theme listener */
  let editorInstance = null;

  /* ---------------- Monaco ---------------- */
  require(["vs/editor/editor.main"], async function () {
    // Define custom light theme
    monaco.editor.defineTheme('ide-light', {
      base: 'vs',
      inherit: true,
      rules: [],
      colors: {
        'editor.background': '#ffffff',
        'editor.foreground': '#333333',
        'editor.lineNumbersBackground': '#f5f5f5',
        'editor.lineNumbersForeground': '#999999',
        'editorCursor.foreground': '#000000',
        'editor.selectionBackground': '#add6ff',
        'editor.inactiveSelectionBackground': '#e5ebf1',
      }
    });
    
    // Define custom theme for better dark mode integration
    monaco.editor.defineTheme('ide-dark', {
      base: 'vs-dark',
      inherit: true,
      rules: [
        { token: '', foreground: '9cdcfe' }
      ],
      colors: {
        'editor.background': '#1e1e1e',
        'editor.foreground': '#d4d4d4',
        'editor.lineNumbersBackground': '#1e1e1e',
        'editor.lineNumbersForeground': '#858585',
        'editorCursor.foreground': '#aeafad',
        'editor.selectionBackground': '#264f78',
        'editor.inactiveSelectionBackground': '#3f3f46',
      }
    });

    // Setup theme helpers
    const isDarkMode = () => document.documentElement.classList.contains('dark-mode');
    const getEditorTheme = () => isDarkMode() ? 'ide-dark' : 'ide-light';
    
    const editor = monaco.editor.create(document.getElementById("editor-container"), {
      value: `# Hier Python Code`,
      language: "python",
      theme: getEditorTheme(),
      automaticLayout: true,
      lightbulb: { enabled: false }, // kein "No quick fixes available"
    });
    
    // Store reference globally for theme changes AND external modules
    editorInstance = editor;
    window.editorInstance = editor;
    console.log('Editor instance created and stored globally');

    // Check for project_id in URL and load project if present
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    if (projectId) {
      console.log('Project ID detected in URL:', projectId);
      // Load project dynamically
      try {
        const projectsModule = await import('./projects.js?t=' + Date.now());
        if (projectsModule && projectsModule.loadProjectById) {
          console.log('Loading project via projects module...');
          await projectsModule.loadProjectById(projectId);
        } else {
          console.log('Projects module loaded, calling global loadProject if available');
          // Fallback: Wait a bit and try window.loadProject
          setTimeout(async () => {
            if (window.loadProject) {
              await window.loadProject(projectId);
            }
          }, 500);
        }
      } catch (e) {
        console.error('Error loading project:', e);
      }
    }

    // Setup theme listener AFTER editor is created
    const themeObserver = new MutationObserver(() => {
      if (!editorInstance) return;
      const newTheme = isDarkMode() ? 'ide-dark' : 'ide-light';
      monaco.editor.setTheme(newTheme);
      editorInstance.layout();
    });
    
    themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class'],
      attributeOldValue: true
    });

    const outputEl = document.getElementById("output-container");
    const lintEl = document.getElementById("lint-container");
    const plotEl = document.getElementById("plot-container");

    /* ============================================================
       ✅ Autocomplete / Snippets ausgelagert
       Datei: public/js/editor-completions.js
       ============================================================ */
    try {
      const ts = new Date().getTime();
      const mod = await import(`./editor-completions.js?t=${ts}`);
      await mod.registerPythonCompletions(monaco, editor);
    } catch (e) {
      console.error("Failed to load ./editor-completions.js", e);
    }

    /* ---------------- Markers / helpers ---------------- */
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
      lintEl.innerHTML = `<span class="lint-checking">Prüfe…</span>`;
    }

    function setLintOk() {
      lintEl.innerHTML = `<span class="lint-ok">
        Syntaxcheck <span class="lint-checkmark">✓</span>
      </span>`;
    }

    function setLintError(line, msg, hint = null, token = null, suggestion = null) {
      const hasFix = !!(token && suggestion && suggestion !== token);

      lintEl.innerHTML =
        `<div style="white-space:pre-wrap;">Zeile ${line}: ${escapeHtml(msg || "")}</div>` +
        (hint ? `<div style="margin-top:6px; white-space:pre-wrap;">${escapeHtml(hint)}</div>` : "") +
        (hasFix
          ? `<div style="margin-top:8px;">
               <span class="lint-fix-label">Quick Fix:</span>
               <span id="lint-fix"
                     title="Klick/Doppelklick oder Ctrl+."
                     class="lint-fix-link">
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

    // Klick & Doppelklick im Lint
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

      // harte Syntaxprüfung
      const syntax = await runLiveSyntaxCheck({ quietOk: false });
      if (!syntax.ok || hasAnyMarkers()) return;

      const code = editor.getValue();
      const selectedPackages = getSelectedPackages();
      await ensurePackages(selectedPackages);
      const enableMatplotlib = selectedPackages.includes("matplotlib");

      try {
        await pyodide.runPythonAsync(`
from js import document
import sys, warnings

warnings.filterwarnings("ignore", message="FigureCanvasAgg is non-interactive")

enable_matplotlib = ${enableMatplotlib ? "True" : "False"}
if enable_matplotlib:
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

    if enable_matplotlib:
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
