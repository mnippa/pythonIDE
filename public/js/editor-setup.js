// public/js/editor-setup.js (ES module)

// Imports
import { initOutputPlotTabs } from './output-plot-tabs.js';
import { guiBridge } from './gui-bridge.js';

/* ================ INPUT MODAL ================ */
/**
 * Show input modal and wait for user input
 * @param {string} prompt - The prompt message to display
 * @returns {Promise<string>} The user input
 */
window.pythonInput = function(prompt) {
  return new Promise((resolve) => {
    // Create modal if it doesn't exist
    let modal = document.getElementById('python-input-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'python-input-modal';
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5); display: none;
        align-items: center; justify-content: center; z-index: 1001;
        animation: fadeIn 0.2s;
      `;
      
      modal.innerHTML = `
        <div style="
          background: var(--bg, #fff); color: var(--text-primary, #000);
          border-radius: 12px; max-width: 400px; width: 90%;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
          animation: slideUp 0.3s;
        ">
          <div style="padding: 20px; border-bottom: 1px solid var(--border, #e5e7eb);">
            <h3 style="margin: 0; font-size: 18px;" id="python-input-prompt">Eingabe</h3>
          </div>
          <div style="padding: 20px;">
            <input type="text" id="python-input-field" style="
              width: 100%; padding: 10px; font-size: 14px;
              border: 1px solid var(--border, #e5e7eb); border-radius: 6px;
              background: var(--bg, #fff); color: var(--text-primary, #000);
              font-family: monospace;
            " autocomplete="off">
          </div>
          <div style="padding: 0 20px 20px; display: flex; gap: 10px; justify-content: flex-end;">
            <button id="python-input-cancel" style="
              padding: 8px 16px; border: 1px solid var(--border, #e5e7eb);
              border-radius: 6px; background: var(--panel, #f5f5f5);
              color: var(--text-primary, #000); cursor: pointer;
            ">Abbrechen</button>
            <button id="python-input-submit" style="
              padding: 8px 16px; border: none; border-radius: 6px;
              background: #667eea; color: #fff; cursor: pointer; font-weight: 500;
            ">OK</button>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
    }
    
    // Set prompt and show modal
    const promptEl = document.getElementById('python-input-prompt');
    const inputField = document.getElementById('python-input-field');
    const submitBtn = document.getElementById('python-input-submit');
    const cancelBtn = document.getElementById('python-input-cancel');
    
    promptEl.textContent = prompt || 'Eingabe:';
    inputField.value = '';
    modal.style.display = 'flex';
    
    // Focus input field
    setTimeout(() => inputField.focus(), 100);
    
    // Handle submit
    const handleSubmit = () => {
      const value = inputField.value;
      modal.style.display = 'none';
      resolve(value);
    };
    
    // Handle cancel
    const handleCancel = () => {
      modal.style.display = 'none';
      resolve(''); // Return empty string on cancel
    };
    
    // Bind events (remove old listeners first)
    submitBtn.onclick = handleSubmit;
    cancelBtn.onclick = handleCancel;
    
    // Enter key submits
    inputField.onkeypress = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleSubmit();
      }
    };
    
    // Escape key cancels
    inputField.onkeydown = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        handleCancel();
      }
    };
  });
};

async function initPyodideAndEditor() {
  // Initialize Output/Plot tab navigation
  initOutputPlotTabs();
  
  // Make GUI bridge globally available
  window.guiBridge = guiBridge;
  
  /* ---------------- Pyodide ---------------- */
  const pyodide = await loadPyodide({ indexURL: "pyodide/" });
  window.pyodide = pyodide; // Make globally available for assignments.js
  window.pyodideReady = true; // Set flag for solution computation
  console.log("Pyodide ready");

    async function ensureIdeGuiModule() {
    await pyodide.runPythonAsync(`
  import sys
  import types
  from js import document, window as js_window
  from pyodide.ffi import create_proxy

  if "idegui" not in sys.modules:
    idegui = types.ModuleType("idegui")

    def _container():
      bridge = getattr(js_window, "guiBridge", None)
      if bridge:
        bridge.showGUI()
        return bridge.getGUIContainer()
      return document.getElementById("gui-container")

    def _ensure_layout():
      container = _container()
      if container is None:
        return None, None

      root = container.querySelector("#idegui-root")
      if root is None:
        root = document.createElement("div")
        root.id = "idegui-root"
        root.setAttribute("data-idegui-root", "true")
        root.style.display = "grid"
        root.style.gap = "8px"
        root.style.padding = "8px"
        container.appendChild(root)

      out = container.querySelector("#idegui-output")
      if out is None:
        out = document.createElement("div")
        out.id = "idegui-output"
        out.setAttribute("data-idegui-output", "true")
        out.style.marginTop = "8px"
        out.style.padding = "8px"
        out.style.border = "1px solid var(--border, #e5e7eb)"
        out.style.borderRadius = "8px"
        out.style.whiteSpace = "pre-wrap"
        container.appendChild(out)

      return root, out

    class _Widget:
      def __init__(self, element=None, value=None):
        self.element = element
        self.value = value

    class _Button(_Widget):
      def on_click(self, callback):
        if self.element is None or callback is None:
          return self

        def _handler(_event=None):
          callback()

        self._handler = create_proxy(_handler)
        self.element.addEventListener("click", self._handler)
        return self

    class _Output(_Widget):
      def write(self, value):
        _, out = _ensure_layout()
        if out is None:
          return
        text = "" if value is None else str(value)
        if text:
          out.textContent += text + "\\n"

      def clear(self):
        _, out = _ensure_layout()
        if out is not None:
          out.textContent = ""

    def title(text=""):
      root, _ = _ensure_layout()
      if root is None:
        return _Widget(None, str(text or ""))

      heading = root.querySelector("[data-idegui-title='true']")
      if heading is None:
        heading = document.createElement("h3")
        heading.setAttribute("data-idegui-title", "true")
        heading.style.margin = "0 0 8px 0"
        root.prepend(heading)

      heading.textContent = str(text or "")
      return _Widget(heading, heading.textContent)

    def text(label="", value=""):
      root, _ = _ensure_layout()
      if root is None:
        return _Widget(None, str(value or ""))

      wrap = document.createElement("div")
      if label:
        lbl = document.createElement("label")
        lbl.textContent = str(label)
        lbl.style.display = "block"
        lbl.style.marginBottom = "4px"
        wrap.appendChild(lbl)

      inp = document.createElement("input")
      inp.type = "text"
      inp.value = str(value or "")
      inp.style.width = "100%"
      inp.style.padding = "6px 8px"
      wrap.appendChild(inp)
      root.appendChild(wrap)

      widget = _Widget(inp, inp.value)

      def _sync(_event=None):
        widget.value = inp.value

      widget._sync = create_proxy(_sync)
      inp.addEventListener("input", widget._sync)
      return widget

    def number(label="", value=0):
      root, _ = _ensure_layout()
      if root is None:
        return _Widget(None, value)

      wrap = document.createElement("div")
      if label:
        lbl = document.createElement("label")
        lbl.textContent = str(label)
        lbl.style.display = "block"
        lbl.style.marginBottom = "4px"
        wrap.appendChild(lbl)

      inp = document.createElement("input")
      inp.type = "number"
      inp.value = str(value)
      inp.style.width = "100%"
      inp.style.padding = "6px 8px"
      wrap.appendChild(inp)
      root.appendChild(wrap)

      widget = _Widget(inp, float(value))

      def _sync(_event=None):
        try:
          widget.value = float(inp.value)
        except Exception:
          widget.value = inp.value

      widget._sync = create_proxy(_sync)
      inp.addEventListener("input", widget._sync)
      return widget

    def select(label="", options=None, value=None):
      root, _ = _ensure_layout()
      options = list(options or [])
      if root is None:
        start_value = value if value is not None else (options[0] if options else None)
        return _Widget(None, start_value)

      wrap = document.createElement("div")
      if label:
        lbl = document.createElement("label")
        lbl.textContent = str(label)
        lbl.style.display = "block"
        lbl.style.marginBottom = "4px"
        wrap.appendChild(lbl)

      sel = document.createElement("select")
      sel.style.width = "100%"
      sel.style.padding = "6px 8px"

      for opt in options:
        option = document.createElement("option")
        option.value = str(opt)
        option.textContent = str(opt)
        sel.appendChild(option)

      if value is not None:
        sel.value = str(value)
      elif options:
        sel.value = str(options[0])

      wrap.appendChild(sel)
      root.appendChild(wrap)

      widget = _Widget(sel, sel.value)

      def _sync(_event=None):
        widget.value = sel.value

      widget._sync = create_proxy(_sync)
      sel.addEventListener("change", widget._sync)
      return widget

    def button(label="Button"):
      root, _ = _ensure_layout()
      if root is None:
        return _Button(None, None)

      btn = document.createElement("button")
      btn.textContent = str(label)
      btn.style.padding = "8px 10px"
      btn.style.cursor = "pointer"
      root.appendChild(btn)
      return _Button(btn, None)

    def output():
      _ensure_layout()
      return _Output(None, None)

    def get_input_value(name, default=""):
      container = _container()
      if container is None:
        return default

      selector = f'[data-input="{name}"]'
      element = container.querySelector(selector)
      if element is None:
        return default

      value = getattr(element, "value", None)
      if value is None:
        value = getattr(element, "textContent", default)
      return "" if value is None else str(value)

    def set_output(name, value):
      container = _container()
      if container is None:
        return None

      selector = f'[data-output="{name}"]'
      element = container.querySelector(selector)
      if element is None:
        return None

      text = "" if value is None else str(value)

      tag_name = (getattr(element, "tagName", "") or "").lower()
      if tag_name in ["input", "textarea", "select"]:
        element.value = text
      else:
        element.textContent = text

      if element.hasAttribute("hidden"):
        element.removeAttribute("hidden")

      parent = getattr(element, "parentElement", None)
      if parent is not None and parent.hasAttribute("hidden"):
        parent.removeAttribute("hidden")

      return text

    def show():
      container = _container()
      if container is not None:
        _ensure_layout()

    def clear():
      bridge = getattr(js_window, "guiBridge", None)
      if bridge:
        bridge.clearGUI()
      _ensure_layout()

    idegui.text = text
    idegui.title = title
    idegui.number = number
    idegui.select = select
    idegui.button = button
    idegui.output = output
    idegui.get_input_value = get_input_value
    idegui.set_output = set_output
    idegui.show = show
    idegui.clear = clear
    idegui.__all__ = ["title", "text", "number", "select", "button", "output", "get_input_value", "set_output", "show", "clear"]

    sys.modules["idegui"] = idegui
  `);
    }

    await ensureIdeGuiModule();

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
    
    // Allow context menu (copy/paste) only in projects.php
    const isProjectsPage = window.location.pathname.includes('projects.php');
    
    const editor = monaco.editor.create(document.getElementById("editor-container"), {
      value: `# Hier Python Code`,
      language: "python",
      theme: getEditorTheme(),
      automaticLayout: true,
      lightbulb: { enabled: false }, // kein "No quick fixes available"
      contextmenu: isProjectsPage, // Allow context menu in projects.php only
    });
    
    // Block copy/paste only in assignment_editor (student checking)
    // Allow copy/paste in projects.php (personal projects)
    const isTestMode = window.testMode === true;
    
    if (!isProjectsPage && !isTestMode) {
      const editorDomNode = editor.getDomNode();
      if (editorDomNode) {
        // Block paste only - copy/cut still work
        editorDomNode.addEventListener('paste', (e) => {
          e.preventDefault();
          e.stopPropagation();
        }, true);
      }
      
      // Also prevent paste via Monaco API
      editor.onDidPaste(() => {
        const model = editor.getModel();
        if (model) {
          editor.trigger('keyboard', 'undo', null);
        }
      });
    }
    // Copy/paste fully allowed in projects.php and test mode
    
    // Store reference globally for theme changes AND external modules
    editorInstance = editor;
    window.editorInstance = editor;
    console.log('Editor instance created and stored globally');

    // Initialize file tree if not in project view
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    
    // Initialize file tree for project mode or in projects.php
    const treeWrapper = document.getElementById('file-tree-wrapper');
    if (treeWrapper && window.FileTreeManager) {
      // In projects.php: start expanded, in assignment_editor: start collapsed
      const isProjectsPage = window.location.pathname.includes('projects.php');
      const treeManager = new window.FileTreeManager('file-tree-wrapper', isProjectsPage);
      window.fileTreeManager = treeManager;
    }

    // Initialize validator
    if (window.CodeValidator) {
      window.validator = new window.CodeValidator();
    }

    // Check for project_id in URL and load project if present
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
              const guiContainer = document.getElementById('gui-container');
              const alreadyRenderedForTask = guiContainer
                && guiContainer.dataset.codeUiTaskId === String(currentTask.id)
                && guiContainer.querySelector('[data-input], [data-output]');

              if (!alreadyRenderedForTask) {
                await window.renderCodeUiHtml(currentTask.id);
              }
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
      if (window.incrementTaskRunCount && window.assignmentState?.currentTask?.id) {
        await window.incrementTaskRunCount(window.assignmentState.currentTask.id);
      }

      outputEl.innerText = "";
      plotEl.innerHTML = "";
      setLintChecking();

      liveSeq++;
      clearTimeout(liveTimer);

      const currentTask = window.assignmentState?.currentTask;
      const hasFolderStructure = !!currentTask && (
        currentTask.folderstructure === 1 ||
        currentTask.folderstructure === true ||
        currentTask.folderstructure === '1'
      );
      const runInitOnly = window.testMode !== true && hasFolderStructure;

      let code = editor.getValue();
      const activePath = String(window.currentFile?.path || '');
      const activeIsPython = activePath.toLowerCase().endsWith('.py');

      if (typeof window.cacheCurrentEditorDraft === 'function') {
        window.cacheCurrentEditorDraft();
      }

      if (runInitOnly) {
        try {
          if (activeIsPython) {
            code = editor.getValue();
          } else {
            const draftInit = typeof window.getTaskDraftContent === 'function'
              ? window.getTaskDraftContent(currentTask.id, 'init.py')
              : null;

            if (draftInit !== null && draftInit !== undefined) {
              code = String(draftInit || '');
            } else {
              const testUserParam = window.TEST_USER_ID ? `&test_user_id=${window.TEST_USER_ID}` : '';
              const initResponse = await fetch(`/pythonIDE/api/user_tasks/folder-files.php?action=read&task_id=${currentTask.id}&path=${encodeURIComponent('init.py')}${testUserParam}`, {
                credentials: 'include'
              });
              const initData = await initResponse.json();

              if (!initResponse.ok || (initData && initData.ok === false)) {
                throw new Error(initData?.error || initResponse.statusText || 'init.py konnte nicht geladen werden');
              }

              code = initData.content || '';
            }
          }

          const seq = ++liveSeq;
          await pyodide.runPythonAsync(`
code = ${JSON.stringify(code)}
compile(code, "<usercode>", "exec")
`);
          if (seq !== liveSeq) return;

          clearMarkers();
          clearQuickFixState();
          setLintOk();
        } catch (e) {
          const parsed = resolveErrorLine(e.message || String(e), code);
          clearQuickFixState();
          setLintError(parsed.line, parsed.error, null, null, null);
          setErrorMarker(parsed.line, parsed.error);
          return;
        }
      } else {
        // harte Syntaxprüfung
        const syntax = await runLiveSyntaxCheck({ quietOk: false });
        if (!syntax.ok || hasAnyMarkers()) return;
        code = editor.getValue();
      }

      const selectedPackages = getSelectedPackages();
      await ensurePackages(selectedPackages);
      const enableMatplotlib = selectedPackages.includes("matplotlib");

      try {
        const wantsIdeGui = /(^|\n)\s*(import\s+idegui\b|from\s+idegui\s+import\b)/m.test(code);
        const currentTask = window.assignmentState?.currentTask || null;
        const isCodeUiTask = currentTask?.task_type === 'code_ui';

        if (window.guiBridge) {
          if (wantsIdeGui) {
            if (isCodeUiTask && typeof window.renderCodeUiHtml === 'function' && currentTask?.id) {
              await window.renderCodeUiHtml(currentTask.id);
            } else {
              window.guiBridge.clearGUI();
              window.guiBridge.showGUI();
            }
          } else {
            if (!isCodeUiTask) {
              window.guiBridge.hideGUI();
              window.guiBridge.clearGUI();
            }
          }
        }

        await ensureIdeGuiModule();

        await pyodide.runPythonAsync(`
from js import document, window as js_window
import sys, warnings
import builtins

warnings.filterwarnings("ignore", message="FigureCanvasAgg is non-interactive")

# Override input() to use JavaScript modal
_original_input = builtins.input
def _custom_input(prompt=''):
    """Custom input() that uses JavaScript for user interaction"""
    prompt_str = str(prompt) if prompt else ''
    # Use synchronous JS prompt for now (TODO: async modal later)
    result = js_window.prompt(prompt_str)
    if result is None:  # User cancelled
        return ''
    return str(result)

builtins.input = _custom_input

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
