// public/js/editor-setup.js (ES module)

// Imports
import { initOutputPlotTabs } from './output-plot-tabs.js';
import { guiBridge } from './gui-bridge.js';

function ensureInputModalStyles() {
  if (document.getElementById('pyide-input-modal-styles')) {
    return;
  }

  const style = document.createElement('style');
  style.id = 'pyide-input-modal-styles';
  style.textContent = `
    #help-container.pyide-input-host,
    #editor-container.pyide-input-host,
    .editor-area.pyide-input-host,
    .editor-quiz-wrapper.pyide-input-host {
      position: relative;
    }

    .pyide-editor-inactive {
      position: relative;
      pointer-events: none;
      opacity: 0.42;
      filter: grayscale(0.15);
      transition: opacity 0.18s ease, filter 0.18s ease;
    }

    .pyide-editor-inactive::after {
      content: 'Eingabe aktiv';
      position: absolute;
      top: 12px;
      right: 12px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.78);
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.02em;
      pointer-events: none;
      z-index: 3;
    }

    .pyide-input-help-modal {
      position: absolute;
      inset: 0;
      display: none;
      align-items: stretch;
      justify-content: stretch;
      z-index: 1002;
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.08), rgba(15, 23, 42, 0.16));
      padding: 10px;
      animation: fadeIn 0.2s;
    }

    .pyide-input-help-modal .pyide-input-dialog {
      width: 100%;
      height: 100%;
      max-width: none;
      border-radius: 10px;
      border: 1px solid var(--border, #e5e7eb);
      box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
      background: var(--bg, #fff);
      color: var(--text-primary, #000);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .pyide-input-help-modal .pyide-input-dialog-body {
      flex: 1 1 auto;
      overflow: auto;
    }
  `;
  document.head.appendChild(style);
}

function getInputModalHost() {
  ensureInputModalStyles();
  const host = document.getElementById('help-container')
    || document.getElementById('editor-container')
    || document.querySelector('.editor-area')
    || document.querySelector('.editor-quiz-wrapper')
    || document.body;
  if (host && host !== document.body) {
    host.classList.add('pyide-input-host');
  }
  return host;
}

function getInputModalPositionStyle(host) {
  if (host === document.body) {
    return 'position: fixed; top: 0; left: 0; right: 0; bottom: 0;';
  }
  return 'position: absolute; inset: 0;';
}

function setInputUiState(active) {
  const editorShell = document.querySelector('.editor-quiz-wrapper')
    || document.getElementById('editor-container')
    || document.querySelector('.editor-area');
  if (!editorShell) {
    return;
  }
  editorShell.classList.toggle('pyide-editor-inactive', !!active);
}

/* ================ INPUT MODAL ================ */
/**
 * Show input modal and wait for user input
 * @param {string} prompt - The prompt message to display
 * @returns {Promise<string>} The user input
 */
window.pythonInput = function(prompt) {
  return new Promise((resolve) => {
    const host = getInputModalHost();
    // Create modal if it doesn't exist
    let modal = document.getElementById('python-input-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'python-input-modal';
      modal.className = host !== document.body ? 'pyide-input-help-modal' : '';
      modal.style.cssText = host === document.body ? `
        ${getInputModalPositionStyle(host)}
        background: rgba(0, 0, 0, 0.38); display: none;
        align-items: center; justify-content: center; z-index: 1001;
        animation: fadeIn 0.2s;
        padding: 16px;
      ` : '';
      
      modal.innerHTML = `
        <div class="pyide-input-dialog" style="
          background: var(--bg, #fff); color: var(--text-primary, #000);
          border-radius: 12px; max-width: 400px; width: 90%;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
          animation: slideUp 0.3s;
        ">
          <div style="padding: 20px; border-bottom: 1px solid var(--border, #e5e7eb);">
            <h3 style="margin: 0; font-size: 18px;" id="python-input-prompt">Eingabe</h3>
          </div>
          <div class="pyide-input-dialog-body" style="padding: 20px;">
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
      
    }

    if (modal.parentElement !== host) {
      host.appendChild(modal);
    }
    if (host === document.body) {
      modal.className = '';
      modal.style.cssText = `
        ${getInputModalPositionStyle(host)}
        background: rgba(0, 0, 0, 0.38); display: none;
        align-items: center; justify-content: center; z-index: 1001;
        animation: fadeIn 0.2s;
        padding: 16px;
      `;
    } else {
      modal.className = 'pyide-input-help-modal';
      modal.style.cssText = '';
    }
    
    // Set prompt and show modal
    const promptEl = document.getElementById('python-input-prompt');
    const inputField = document.getElementById('python-input-field');
    const submitBtn = document.getElementById('python-input-submit');
    const cancelBtn = document.getElementById('python-input-cancel');
    
    promptEl.textContent = prompt || 'Eingabe:';
    inputField.value = '';
    modal.style.display = 'flex';
    setInputUiState(true);
    
    // Focus input field
    setTimeout(() => inputField.focus(), 100);
    
    // Handle submit
    const handleSubmit = () => {
      const value = inputField.value;
      modal.style.display = 'none';
      setInputUiState(false);
      resolve(value);
    };
    
    // Handle cancel
    const handleCancel = () => {
      modal.style.display = 'none';
      setInputUiState(false);
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

/**
 * Show textarea modal to collect multiple input() values at once (one line per value)
 * @param {string} prompt - The prompt message to display
 * @param {string} hint - Optional hint text shown above textarea
 * @returns {Promise<string>} The raw textarea content
 */
window.pythonInputBatch = function(prompt, hint) {
  return new Promise((resolve) => {
    const host = getInputModalHost();
    let modal = document.getElementById('python-input-batch-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'python-input-batch-modal';
      modal.className = host !== document.body ? 'pyide-input-help-modal' : '';
      modal.style.cssText = host === document.body ? `
        ${getInputModalPositionStyle(host)}
        background: rgba(0, 0, 0, 0.38); display: none;
        align-items: center; justify-content: center; z-index: 1002;
        animation: fadeIn 0.2s;
        padding: 16px;
      ` : '';

      modal.innerHTML = `
        <div class="pyide-input-dialog" style="
          background: var(--bg, #fff); color: var(--text-primary, #000);
          border-radius: 12px; max-width: 560px; width: 92%;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
          animation: slideUp 0.3s;
        ">
          <div style="padding: 20px; border-bottom: 1px solid var(--border, #e5e7eb);">
            <h3 style="margin: 0; font-size: 18px;" id="python-input-batch-prompt">Mehrfacheingabe</h3>
          </div>
          <div class="pyide-input-dialog-body" style="padding: 20px;">
            <p id="python-input-batch-hint" style="margin: 0 0 10px; color: var(--text-secondary, #4b5563); font-size: 13px;">Eine Zeile pro input()-Aufruf.</p>
            <textarea id="python-input-batch-field" style="
              width: 100%; min-height: 160px; padding: 10px; font-size: 14px;
              border: 1px solid var(--border, #e5e7eb); border-radius: 6px;
              background: var(--bg, #fff); color: var(--text-primary, #000);
              font-family: monospace; resize: vertical;
            " spellcheck="false"></textarea>
          </div>
          <div style="padding: 0 20px 20px; display: flex; gap: 10px; justify-content: flex-end;">
            <button id="python-input-batch-cancel" style="
              padding: 8px 16px; border: 1px solid var(--border, #e5e7eb);
              border-radius: 6px; background: var(--panel, #f5f5f5);
              color: var(--text-primary, #000); cursor: pointer;
            ">Abbrechen</button>
            <button id="python-input-batch-submit" style="
              padding: 8px 16px; border: none; border-radius: 6px;
              background: #667eea; color: #fff; cursor: pointer; font-weight: 500;
            ">Uebernehmen</button>
          </div>
        </div>
      `;

    }

    if (modal.parentElement !== host) {
      host.appendChild(modal);
    }
    if (host === document.body) {
      modal.className = '';
      modal.style.cssText = `
        ${getInputModalPositionStyle(host)}
        background: rgba(0, 0, 0, 0.38); display: none;
        align-items: center; justify-content: center; z-index: 1002;
        animation: fadeIn 0.2s;
        padding: 16px;
      `;
    } else {
      modal.className = 'pyide-input-help-modal';
      modal.style.cssText = '';
    }

    const promptEl = document.getElementById('python-input-batch-prompt');
    const hintEl = document.getElementById('python-input-batch-hint');
    const fieldEl = document.getElementById('python-input-batch-field');
    const submitBtn = document.getElementById('python-input-batch-submit');
    const cancelBtn = document.getElementById('python-input-batch-cancel');

    promptEl.textContent = prompt || 'Mehrfacheingabe';
    hintEl.textContent = hint || 'Eine Zeile pro input()-Aufruf.';
    fieldEl.value = '';
    modal.style.display = 'flex';
    setInputUiState(true);

    setTimeout(() => fieldEl.focus(), 50);

    const handleSubmit = () => {
      const value = fieldEl.value;
      modal.style.display = 'none';
      setInputUiState(false);
      resolve(value);
    };

    const handleCancel = () => {
      modal.style.display = 'none';
      setInputUiState(false);
      resolve('');
    };

    submitBtn.onclick = handleSubmit;
    cancelBtn.onclick = handleCancel;
    fieldEl.onkeydown = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        handleCancel();
      }
    };
  });
};

async function initPyodideAndEditor() {
  if (window.__pyideEditorSetupInitStarted) {
    return;
  }
  window.__pyideEditorSetupInitStarted = true;

  // Initialize Output/Plot tab navigation
  initOutputPlotTabs();
  
  // Make GUI bridge globally available
  window.guiBridge = guiBridge;
  
  /* ---------------- Pyodide ---------------- */
  const pyodide = await loadPyodide({ indexURL: "pyodide/" });
  window.pyodide = pyodide; // Make globally available for assignments.js
  window.pyodideReady = true; // Set flag for solution computation
  console.log("Pyodide ready");

  const workerRunnerEnabled =
    window.PYIDE_WORKER_RUNNER === true
    || new URLSearchParams(window.location.search).get('worker_runner') === '1';

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

  class _Trigger:
    def __init__(self, name="", value=""):
      self.name = "" if name is None else str(name)
      self.value = "" if value is None else str(value)

  def _safe_js_prop(obj, key, default=""):
    if obj is None:
      return default
    try:
      val = getattr(obj, key)
      if val is None:
        return default
      return "" if val is None else str(val)
    except Exception:
      return default

  def _refresh_trigger():
    data = getattr(js_window, "__codeUiTrigger", None)
    if data is not None:
      name = _safe_js_prop(data, "name", "")
      value = _safe_js_prop(data, "value", "")
      idegui.trigger = _Trigger(name, value)
      return idegui.trigger

    container = _container()
    if container is None:
      idegui.trigger = _Trigger("", "")
      return idegui.trigger

    name = ""
    value = ""

    trigger_element = container.querySelector('[data-element="__trigger__"]')
    if trigger_element is not None:
      trigger_name = getattr(trigger_element, "value", None)
      if trigger_name is None:
        trigger_name = getattr(trigger_element, "textContent", "")
      name = "" if trigger_name is None else str(trigger_name)

    trigger_value_element = container.querySelector('[data-element="__trigger_value__"]')
    if trigger_value_element is not None:
      trigger_value = getattr(trigger_value_element, "value", None)
      if trigger_value is None:
        trigger_value = getattr(trigger_value_element, "textContent", "")
      value = "" if trigger_value is None else str(trigger_value)

    idegui.trigger = _Trigger(name, value)
    return idegui.trigger

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

  def get(name, default=""):
    """Read value from HTML element with data-element attribute"""
    container = _container()
    if container is None:
      return default

    selector = f'[data-element="{name}"]'
    element = container.querySelector(selector)
    if element is None:
      return default

    value = getattr(element, "value", None)
    if value is None:
      value = getattr(element, "textContent", default)
    return "" if value is None else str(value)

  def set(name, value):
    """Write value to HTML element with data-element attribute"""
    container = _container()
    if container is None:
      return None

    selector = f'[data-element="{name}"]'
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

  def print_to(container_name, *args, sep=" ", end="\\n"):
    """Print text to a container element (appends like Python print)"""
    container = _container()
    if container is None:
      return None

    selector = f'[data-element="{container_name}"]'
    element = container.querySelector(selector)
    if element is None:
      return None

    # Format text like Python print()
    text_parts = [str(arg) for arg in args]
    text = sep.join(text_parts) + end

    # Append to container (not replace)
    tag_name = (getattr(element, "tagName", "") or "").lower()
    if tag_name in ["input", "textarea"]:
      current = getattr(element, "value", "")
      element.value = current + text
    else:
      current = getattr(element, "textContent", "")
      element.textContent = current + text

    if element.hasAttribute("hidden"):
      element.removeAttribute("hidden")

    parent = getattr(element, "parentElement", None)
    if parent is not None and parent.hasAttribute("hidden"):
      parent.removeAttribute("hidden")

    return text

  def reset(container_name=""):
    """Clear content of a container element"""
    container = _container()
    if container is None:
      return None

    selector = f'[data-element="{container_name}"]'
    element = container.querySelector(selector)
    if element is None:
      return None

    tag_name = (getattr(element, "tagName", "") or "").lower()
    if tag_name in ["input", "textarea", "select"]:
      element.value = ""
    else:
      element.textContent = ""

    return True

  def show():
    container = _container()
    if container is not None:
      _ensure_layout()

  def clear():
    bridge = getattr(js_window, "guiBridge", None)
    if bridge:
      bridge.clearGUI()
    _ensure_layout()

  # Alias: input() is the same as text()
  input = text

  idegui.title = title
  idegui.text = text
  idegui.input = input
  idegui.number = number
  idegui.select = select
  idegui.button = button
  idegui.output = output
  idegui.get = get
  idegui.set = set
  idegui.print = print_to
  idegui.reset = reset
  idegui.trigger = _Trigger("", "")
  idegui._refresh_trigger = _refresh_trigger
  idegui.show = show
  idegui.clear = clear
  idegui.__all__ = ["title", "text", "input", "number", "select", "button", "output", "get", "set", "print", "reset", "trigger", "show", "clear"]

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

  function inferPackagesFromCode(code) {
    const text = String(code || '');
    const inferred = new Set();

    // Infer common scientific packages from import statements.
    if (/^\s*(import\s+matplotlib\b|from\s+matplotlib\b)/m.test(text)) {
      inferred.add('matplotlib');
    }
    if (/^\s*(import\s+numpy\b|from\s+numpy\b)/m.test(text)) {
      inferred.add('numpy');
    }
    if (/^\s*(import\s+pandas\b|from\s+pandas\b)/m.test(text)) {
      inferred.add('pandas');
    }

    return Array.from(inferred).filter((pkg) => availablePackages.has(pkg));
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
    const runButton = document.getElementById("run-btn");

    let preparedInputQueue = [];
    let outputBuffer = '';

    function clearPreparedInputQueue() {
      preparedInputQueue = [];
    }

    function setPreparedInputQueue(values) {
      if (Array.isArray(values)) {
        preparedInputQueue = values.map((value) => String(value ?? ''));
      } else {
        preparedInputQueue = [];
      }
      return preparedInputQueue.length;
    }

    function requestOverlayInput(promptText = '') {
      const overlayEnabled = window.PYIDE_INPUT_OVERLAY !== false;
      const overlayInput = window.pythonInput;
      if (!overlayEnabled || typeof overlayInput !== 'function') {
        return '';
      }

      const promptLabel = String(promptText || 'Eingabe:');
      const finalize = (rawValue) => {
        const value = String(rawValue ?? '');
        window.__pyideLastConsumedInput = {
          source: 'overlay-live',
          prompt: promptLabel,
          value,
          remaining: preparedInputQueue.length,
          ts: Date.now(),
        };
        return value;
      };

      try {
        const maybePromise = overlayInput(promptLabel);
        if (maybePromise && typeof maybePromise.then === 'function') {
          return maybePromise
            .then((value) => finalize(value))
            .catch(() => finalize(''));
        }
        return finalize(maybePromise);
      } catch (err) {
        console.warn('[Run] live input overlay failed; returning empty input', err);
        return '';
      }
    }

    function consumePreparedInput(promptText = '') {
      if (preparedInputQueue.length > 0) {
        const value = String(preparedInputQueue.shift() ?? '');
        window.__pyideLastConsumedInput = {
          source: 'queue',
          prompt: String(promptText || ''),
          value,
          remaining: preparedInputQueue.length,
          ts: Date.now(),
        };
        return value;
      }

      return requestOverlayInput(promptText);
    }

    async function collectOverlayInputsForCode(codeText) {
      const source = String(codeText || '');
      const inputMatches = source.match(/\binput\s*\(/g);
      const totalInputCalls = inputMatches ? inputMatches.length : 0;
      if (!totalInputCalls) return;

      const overlayEnabled = window.PYIDE_INPUT_OVERLAY !== false;
      const overlayInput = window.pythonInput;
      const overlayBatchInput = window.pythonInputBatch;
      const hasOverlay = overlayEnabled && typeof overlayInput === 'function';
      if (!hasOverlay) return;

      const maxPrefillConfig = Number(window.PYIDE_INPUT_PREFILL_MAX);
      const maxPrefill = Number.isFinite(maxPrefillConfig) && maxPrefillConfig > 0
        ? Math.floor(maxPrefillConfig)
        : 3;
      const prefillCount = Math.min(totalInputCalls, maxPrefill);

      for (let index = 0; index < prefillCount; index += 1) {
        const label = `Input ${index + 1}/${totalInputCalls}`;
        const value = await overlayInput(label);
        preparedInputQueue.push(String(value ?? ''));
      }

      if (totalInputCalls > prefillCount) {
        const remainingCount = totalInputCalls - prefillCount;
        if (typeof overlayBatchInput === 'function') {
          const batchRaw = await overlayBatchInput(
            `Weitere ${remainingCount} Eingaben erwartet`,
            `Bitte ${remainingCount} Zeilen eingeben (eine Zeile pro input()).`
          );
          const lines = String(batchRaw ?? '').split(/\r?\n/);
          for (let i = 0; i < remainingCount; i += 1) {
            preparedInputQueue.push(String(lines[i] ?? ''));
          }
        }

        if (preparedInputQueue.length < totalInputCalls) {
          console.warn('[Run] input queue shorter than detected input() calls; remaining input() returns empty string', {
            queued: preparedInputQueue.length,
            total: totalInputCalls,
          });
        }
      }
    }

    async function buildFolderTaskRuntimePayload(taskId, preferredMainPath = 'init.py') {
      const safeTaskId = Number(taskId || 0);
      if (!safeTaskId) return null;

      const testUserParam = window.TEST_USER_ID ? `&test_user_id=${window.TEST_USER_ID}` : '';
      const listUrl = `/pythonIDE/api/user_tasks/folder-files.php?action=list&task_id=${safeTaskId}${testUserParam}`;
      const listResponse = await fetch(listUrl, { credentials: 'include' });
      const listData = await listResponse.json();

      if (!listResponse.ok || (listData && listData.ok === false)) {
        throw new Error(listData?.error || 'Task-Dateiliste konnte nicht geladen werden');
      }

      const fileEntries = [];
      const walk = (items) => {
        if (!Array.isArray(items)) return;
        for (const item of items) {
          if (!item || typeof item !== 'object') continue;
          if (item.type === 'folder') {
            walk(item.children || []);
            continue;
          }
          if (item.type === 'file' && item.virtual !== true && item.is_text !== false) {
            const relPath = String(item.path || '').replace(/\\/g, '/').replace(/^\/+/, '');
            if (relPath) fileEntries.push(relPath);
          }
        }
      };

      walk(listData.files || []);

      const files = [];
      for (const relPath of fileEntries) {
        let content = null;

        if (typeof window.getTaskDraftContent === 'function') {
          const draftContent = window.getTaskDraftContent(safeTaskId, relPath);
          if (draftContent !== null && draftContent !== undefined) {
            content = String(draftContent || '');
          }
        }

        if (content === null) {
          const readUrl = `/pythonIDE/api/user_tasks/folder-files.php?action=read&task_id=${safeTaskId}&path=${encodeURIComponent(relPath)}${testUserParam}`;
          const readResponse = await fetch(readUrl, { credentials: 'include' });
          const readData = await readResponse.json();

          if (!readResponse.ok || (readData && readData.ok === false)) {
            throw new Error(readData?.error || `Datei konnte nicht gelesen werden: ${relPath}`);
          }

          content = String(readData.content || '');
        }

        files.push({ path: relPath, content });
      }

      if (files.length === 0) return null;

      const mainPath = String(preferredMainPath || 'init.py').replace(/\\/g, '/').replace(/^\/+/, '') || 'init.py';
      return {
        root: '/task_runtime',
        mainPath,
        files,
      };
    }

    function outputClear() {
      outputBuffer = '';
      if (outputEl) {
        outputEl.innerText = '';
      }
    }

    function outputWrite(text) {
      const chunk = String(text ?? '');
      if (!chunk) return;
      outputBuffer += chunk;

      let newlineIndex = outputBuffer.indexOf('\n');
      while (newlineIndex !== -1) {
        const completeLine = outputBuffer.slice(0, newlineIndex + 1);
        if (outputEl) {
          outputEl.innerText += completeLine;
        }
        outputBuffer = outputBuffer.slice(newlineIndex + 1);
        newlineIndex = outputBuffer.indexOf('\n');
      }
    }

    function outputFlush() {
      if (!outputBuffer) return;
      if (outputEl) {
        outputEl.innerText += outputBuffer;
      }
      outputBuffer = '';
    }

    function outputCheckpoint() {
      outputFlush();
      // Force style/layout work before the next frame where possible.
      if (outputEl) {
        outputEl.getBoundingClientRect();
      }
      if (typeof window.requestAnimationFrame === 'function') {
        return new Promise((resolve) => {
          window.requestAnimationFrame(() => resolve(true));
        });
      }
      return Promise.resolve(true);
    }

    window.setPreparedInputQueue = setPreparedInputQueue;
    window.clearPreparedInputQueue = clearPreparedInputQueue;
    window.getPreparedInputQueue = () => preparedInputQueue.slice();
    window.__pyideConsumeInput = consumePreparedInput;
    window.__pyideOutputClear = outputClear;
    window.__pyideOutputWrite = outputWrite;
    window.__pyideOutputFlush = outputFlush;
    window.__pyideOutputCheckpoint = outputCheckpoint;

    let workerRunner = null;
    let workerRunToken = 0;
    let activeWorkerRun = null;
    let workerPrewarmStarted = false;
    let workerPrewarmDone = false;
    let workerPrewarmStartTs = 0;
    let workerPrewarmDurationMs = 0;
        let workerFailureStreak = 0;
        let workerBlockedUntilTs = 0;
    let workerFallbackCount = 0;
    let workerTimeoutCount = 0;
    let workerErrorCount = 0;
    let workerLastErrorType = '';
    let workerLastErrorMessage = '';
    let workerLastErrorTs = 0;
    let workerLastSuccessTs = 0;
    const workerRunTimeoutMs = Number(window.PYIDE_WORKER_RUN_TIMEOUT_MS || 25000);

        function nowMs() {
          return Date.now();
        }

        function isWorkerCircuitOpen() {
          return workerBlockedUntilTs > nowMs();
        }

        function recordWorkerRunSuccess() {
          workerFailureStreak = 0;
          workerBlockedUntilTs = 0;
          workerLastSuccessTs = nowMs();
        }

        function recordWorkerRunFailure(kind = 'error', message = '') {
          if (kind === 'timeout') {
            workerTimeoutCount += 1;
          } else {
            workerErrorCount += 1;
          }
          workerLastErrorType = kind;
          workerLastErrorMessage = String(message || '');
          workerLastErrorTs = nowMs();

          workerFailureStreak += 1;
          if (workerFailureStreak >= 2) {
            workerBlockedUntilTs = nowMs() + 30000;
            workerFailureStreak = 0;
            console.warn('[Run] worker circuit opened for 30s due to repeated errors');
          }
        }

    function setRunButtonState(running, mode = 'main') {
      if (!runButton) return;
      runButton.disabled = false;
      if (running && mode === 'worker') {
        runButton.textContent = 'Stop';
        runButton.dataset.mode = 'stop';
      } else {
        runButton.textContent = 'Run';
        runButton.dataset.mode = 'run';
      }
    }

    function resetOutputPanels() {
      outputClear();
      plotEl.innerHTML = '';
    }

    function appendPlotCard(dataUrl, title = 'Figure') {
      if (!plotEl || !dataUrl) return;
      const card = document.createElement('div');
      card.className = 'plot-card';

      const header = document.createElement('div');
      header.className = 'plot-card-header';
      header.innerHTML = `<strong>${title}</strong>`;

      const img = document.createElement('img');
      img.className = 'plot-img';
      img.src = dataUrl;

      card.appendChild(header);
      card.appendChild(img);
      plotEl.appendChild(card);
    }

        function appendWorkerStreamLine(targetEl, rawText) {
          if (!targetEl) return;
          const text = String(rawText ?? '');
          if (!text.trim()) return;
          // Keep parity with main-thread output path.
          outputWrite(text + '\n');
        }

    function ensureWorkerRunner() {
      if (!workerRunner) {
        const workerUrl = new URL('./python-runner.worker.js', import.meta.url);
        workerRunner = new Worker(workerUrl, { type: 'module' });
      }
      return workerRunner;
    }

        function resetWorkerRunnerAndState({ rewarm = false } = {}) {
          if (workerRunner) {
            try {
              workerRunner.terminate();
            } catch (_terminateError) {
              // Ignore terminate errors during recovery.
            }
            workerRunner = null;
          }

          workerPrewarmStarted = false;
          workerPrewarmDone = false;
          workerPrewarmStartTs = 0;
              workerPrewarmDurationMs = 0;

          if (rewarm && workerRunnerEnabled) {
            setTimeout(() => prewarmWorkerRunner(), 0);
          }
        }

        function prewarmWorkerRunner() {
          if (!workerRunnerEnabled || workerPrewarmStarted || workerPrewarmDone) return;
          workerPrewarmStarted = true;
          workerPrewarmStartTs = (window.performance && typeof window.performance.now === 'function')
            ? window.performance.now()
            : Date.now();

          const worker = ensureWorkerRunner();
          const onMessage = (event) => {
            const message = event.data || {};
            if (message.type === 'prewarmed') {
              workerPrewarmDone = true;
              worker.removeEventListener('message', onMessage);
                  const msgMs = Number(message.ms);
                  if (Number.isFinite(msgMs) && msgMs >= 0) {
                    workerPrewarmDurationMs = msgMs;
                  } else {
                    const endTs = (window.performance && typeof window.performance.now === 'function')
                      ? window.performance.now()
                      : Date.now();
                    workerPrewarmDurationMs = Math.max(0, Math.round((endTs - workerPrewarmStartTs) * 10) / 10);
                  }
                  console.info('[Run] worker prewarmed', { ms: workerPrewarmDurationMs });
              return;
            }

            if (message.type === 'prewarm-error') {
              worker.removeEventListener('message', onMessage);
              workerPrewarmStarted = false;
              console.warn('[Run] worker prewarm failed:', message.error || 'unknown');
            }
          };

          worker.addEventListener('message', onMessage);
          worker.postMessage({ type: 'prewarm' });
        }

    function stopWorkerRun() {
      if (activeWorkerRun) {
            const { worker, onMessage, resolve, token } = activeWorkerRun;
            try {
              worker?.postMessage({ type: 'stop', token });
            } catch (_stopSignalError) {
              // Worker may already be terminating; local stop handling below is authoritative.
            }
        if (worker && onMessage) {
          worker.removeEventListener('message', onMessage);
        }
            resolve({ type: 'stopped', token });
        activeWorkerRun = null;
      }

          resetWorkerRunnerAndState();
    }

    async function runCodeWithWorker(payload) {
      const worker = ensureWorkerRunner();
      const token = ++workerRunToken;

      return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
              worker.removeEventListener('message', onMessage);
              activeWorkerRun = null;
                  resetWorkerRunnerAndState({ rewarm: true });
              const timeoutError = new Error(`Worker run timeout after ${workerRunTimeoutMs}ms`);
              timeoutError.code = 'WORKER_TIMEOUT';
              reject(timeoutError);
            }, workerRunTimeoutMs);

        const onMessage = async (event) => {
          const message = event.data || {};
          if (message.token !== token) return;

          switch (message.type) {
            case 'input-request': {
              const promptText = String(message.prompt || 'Eingabe:');
              const requestId = Number(message.requestId || 0);
              let value = '';
              try {
                value = await Promise.resolve(requestOverlayInput(promptText));
              } catch (_inputError) {
                value = '';
              }
              try {
                worker.postMessage({
                  type: 'input-response',
                  token,
                  requestId,
                  value: String(value ?? ''),
                });
              } catch (_postInputResponseError) {
                // Worker may already be stopped/terminated.
              }
              break;
            }
            case 'stdout':
                  appendWorkerStreamLine(outputEl, message.text || '');
              break;
            case 'stderr':
                  appendWorkerStreamLine(lintEl, message.text || '');
              break;
            case 'plot':
              appendPlotCard(message.dataUrl, message.title || 'Figure');
              break;
            case 'done':
                  clearTimeout(timeoutId);
              worker.removeEventListener('message', onMessage);
              activeWorkerRun = null;
              resolve(message);
              break;
            case 'stopped':
                  clearTimeout(timeoutId);
              worker.removeEventListener('message', onMessage);
              activeWorkerRun = null;
              resolve(message);
              break;
            case 'error':
                  clearTimeout(timeoutId);
              worker.removeEventListener('message', onMessage);
              activeWorkerRun = null;
                  const workerError = new Error(message.error || 'Worker run failed');
                  workerError.code = 'WORKER_ERROR';
                  reject(workerError);
              break;
            default:
              break;
          }
        };

        activeWorkerRun = { token, worker, onMessage, resolve, reject };
        worker.addEventListener('message', onMessage);
        worker.postMessage({ type: 'run', token, payload });
      });
    }

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
      const maxLine = model.getLineCount();
      const safeLine = Math.min(ln, maxLine);

      monaco.editor.setModelMarkers(model, "python", [
        {
          severity: monaco.MarkerSeverity.Error,
          message: message || "Error",
          startLineNumber: safeLine,
          endLineNumber: safeLine,
          startColumn: 1,
          endColumn: Math.min(2, model.getLineMaxColumn(safeLine)),
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

    function setLintSkipped() {
      lintEl.innerHTML = `<span class="lint-checking">Syntaxcheck nur fuer Python-Dateien (.py).</span>`;
    }

    function getActiveSyntaxFilePath() {
      const explicitPath = String(window.currentFile?.path || window.currentFile?.fileName || '').trim();
      if (explicitPath) return explicitPath;

      if (typeof window.getCurrentProjectOpenFileName === 'function') {
        const projectFile = String(window.getCurrentProjectOpenFileName() || '').trim();
        if (projectFile) return projectFile;
      }

      return '';
    }

    function shouldRunPythonSyntaxCheck() {
      const activePath = getActiveSyntaxFilePath().toLowerCase();
      if (!activePath) return true;
      return activePath.endsWith('.py');
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

      if (!shouldRunPythonSyntaxCheck()) {
        if (seq !== liveSeq) return { ok: true, skipped: true };
        clearMarkers();
        clearQuickFixState();
        if (!quietOk) setLintSkipped();
        return { ok: true, skipped: true };
      }

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

      if (!shouldRunPythonSyntaxCheck()) {
        clearMarkers();
        clearQuickFixState();
        setLintSkipped();
        return;
      }

      setLintChecking();
      liveTimer = setTimeout(() => runLiveSyntaxCheck({ quietOk: false }), 300);
    }

    editor.onDidChangeModelContent(scheduleLiveSyntaxCheck);
    scheduleLiveSyntaxCheck();

    /* ---------------- Run ---------------- */
    let runInProgress = false;
    let activeRunMode = 'main';
    let runPerfCounter = 0;
        const runTimingOverlayEnabled =
          window.PYIDE_RUN_TIMING_OVERLAY === true
          || new URLSearchParams(window.location.search).get('run_timing_overlay') === '1';

        function ensureRunTimingOverlay() {
          if (!runTimingOverlayEnabled) return null;
          let panel = document.getElementById('run-timing-overlay');
          if (panel) return panel;

          panel = document.createElement('div');
          panel.id = 'run-timing-overlay';
          panel.style.position = 'fixed';
          panel.style.right = '12px';
          panel.style.bottom = '12px';
          panel.style.zIndex = '9999';
          panel.style.maxWidth = '300px';
          panel.style.padding = '8px 10px';
          panel.style.borderRadius = '8px';
          panel.style.fontSize = '12px';
          panel.style.lineHeight = '1.35';
          panel.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
          panel.style.background = 'rgba(17, 24, 39, 0.9)';
          panel.style.color = '#e5e7eb';
          panel.style.border = '1px solid rgba(148, 163, 184, 0.35)';
          panel.style.boxShadow = '0 4px 16px rgba(0, 0, 0, 0.35)';
          panel.style.pointerEvents = 'none';
          panel.textContent = 'RunTiming: waiting for first run';
          document.body.appendChild(panel);
          return panel;
        }

        if (workerRunnerEnabled) {
          setTimeout(() => prewarmWorkerRunner(), 0);
        }

        function updateRunTimingOverlay(payload) {
          const panel = ensureRunTimingOverlay();
          if (!panel || !payload) return;

          const totals = payload.totals || {};
          const avgMain = totals.avgMainMs ?? '-';
          const avgWorker = totals.avgWorkerMs ?? '-';
          const runs = totals.runs ?? 0;

          panel.textContent = [
            `Run #${payload.id} ${payload.mode}/${payload.outcome}`,
            `reason=${payload.reason} ms=${payload.ms}`,
            `runs=${runs} avgMain=${avgMain} avgWorker=${avgWorker}`,
          ].join(' | ');
        }

        window.getRunTimingStats = function () {
          const stats = window.__pyideRunTimingStats;
          if (!stats) return null;
          return JSON.parse(JSON.stringify(stats));
        };

            window.getWorkerRunnerHealth = function () {
              return {
                enabled: workerRunnerEnabled,
                runTimeoutMs: workerRunTimeoutMs,
                prewarmStarted: workerPrewarmStarted,
                prewarmDone: workerPrewarmDone,
                failureStreak: workerFailureStreak,
                blockedUntilTs: workerBlockedUntilTs,
                blockedMsRemaining: Math.max(0, workerBlockedUntilTs - nowMs()),
                circuitOpen: isWorkerCircuitOpen(),
                fallbackCount: workerFallbackCount,
                timeoutCount: workerTimeoutCount,
                errorCount: workerErrorCount,
                lastErrorType: workerLastErrorType,
                lastErrorMessage: workerLastErrorMessage,
                lastErrorTs: workerLastErrorTs,
                lastSuccessTs: workerLastSuccessTs,
                    prewarmDurationMs: workerPrewarmDurationMs,
              };
            };

            window.resetWorkerRunner = function () {
              resetWorkerRunnerAndState({ rewarm: true });
              return window.getWorkerRunnerHealth();
            };

            window.clearWorkerCircuit = function () {
              workerFailureStreak = 0;
              workerBlockedUntilTs = 0;
              if (workerRunnerEnabled) {
                prewarmWorkerRunner();
              }
              return window.getWorkerRunnerHealth();
            };

        window.resetRunTimingStats = function () {
          window.__pyideRunTimingStats = {
            totalRuns: 0,
            totalMs: 0,
            byMode: {
              main: { runs: 0, ms: 0 },
              worker: { runs: 0, ms: 0 },
            },
            byOutcome: {},
                byReason: {},
                fallbackCount: 0,
                byFallbackType: {},
          };

          const panel = document.getElementById('run-timing-overlay');
          if (panel) {
            panel.textContent = 'RunTiming: reset';
          }
        };

    runButton?.addEventListener("click", async () => {
      if (runInProgress) {
        if (activeRunMode === 'worker') {
          stopWorkerRun();
        }
        return;
      }

      runInProgress = true;
      activeRunMode = 'main';
      setRunButtonState(true, 'main');

          const runPerfId = ++runPerfCounter;
          const runPerfStart = (window.performance && typeof window.performance.now === 'function')
            ? window.performance.now()
            : Date.now();
          let runPerfMode = 'main';
          let runPerfOutcome = 'ok';
          let runPerfReason = 'main-default';
            let runPerfWorkerTimings = null;

      let code = '';

      try {
          if (window.incrementTaskRunCount && window.assignmentState?.currentTask?.id) {
            Promise.resolve(window.incrementTaskRunCount(window.assignmentState.currentTask.id)).catch((runCountError) => {
              console.warn('[Run] incrementTaskRunCount failed:', runCountError);
            });
          }

      if (typeof window.beforeRunExecution === 'function') {
        try {
          const preRunResult = await window.beforeRunExecution();
          if (preRunResult === false) {
            runPerfOutcome = 'stopped';
            runPerfReason = 'before-run-cancelled';
            lintEl.innerHTML = '<span class="lint-checking">Ausfuehrung abgebrochen.</span>';
            return;
          }
        } catch (preRunError) {
          runPerfOutcome = 'error';
          runPerfReason = 'before-run-failed';
          console.warn('[Run] beforeRunExecution failed:', preRunError);
          lintEl.innerHTML = '<span class="lint-error">Speichern vor RUN fehlgeschlagen.</span>';
          return;
        }
      }

      resetOutputPanels();
      clearPreparedInputQueue();
      setLintChecking();

      liveSeq++;
      clearTimeout(liveTimer);

      const currentTask = window.assignmentState?.currentTask;
      const currentProject = window.currentProject || null;
      const hasFolderStructure = !!currentTask && (
        currentTask.folderstructure === 1 ||
        currentTask.folderstructure === true ||
        currentTask.folderstructure === '1'
      );
      const runInitOnly = window.testMode !== true && hasFolderStructure;

      const projectRunContext = currentProject && typeof window.getProjectRunContext === 'function'
        ? await window.getProjectRunContext()
        : null;
      const hasProjectRunCode = !!projectRunContext && typeof projectRunContext.code === 'string';
      const runWithProvidedCode = runInitOnly || hasProjectRunCode;

          code = editor.getValue();
      const activePath = String(window.currentFile?.path || '');
      const activeIsPython = activePath.toLowerCase().endsWith('.py');

      if (typeof window.cacheCurrentEditorDraft === 'function') {
        window.cacheCurrentEditorDraft();
      }

      if (runWithProvidedCode) {
        try {
          if (runInitOnly) {
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
          } else {
            code = String(projectRunContext.code || '');
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

      const projectType = String(currentProject?.project_type || '').toLowerCase();
      const isCodeUiTask = currentTask?.task_type === 'code_ui';
      const isProjectCodeUiMode = projectType === 'html' || projectType === 'mixed';
      const isCodeUiMode = isCodeUiTask || isProjectCodeUiMode;
          const wantsIdeGui = /(^|\n)\s*(import\s+idegui\b|from\s+idegui\s+import\b)/m.test(code);
          const usesBlockingInput = /(^|\n)\s*[^#\n]*\binput\s*\(/m.test(code);
          const usesJsDomInterop = /(^|\n)\s*from\s+js\s+import\b[^#\n]*(document|window|navigator|localStorage|sessionStorage)\b/m.test(code);
          const usesGeneralJsInterop = /(^|\n)\s*(from\s+js\s+import\b|import\s+js\b)/m.test(code);
          const workerRiskReason = usesJsDomInterop
            ? 'js-dom-interop-detected'
            : (usesGeneralJsInterop ? 'js-interop-detected' : '');
              const workerCircuitOpen = isWorkerCircuitOpen();
              const canUseWorkerRunner = workerRunnerEnabled && !workerCircuitOpen && !isCodeUiMode && !wantsIdeGui && !workerRiskReason;

          if (!canUseWorkerRunner) {
            if (!workerRunnerEnabled) {
              runPerfReason = 'worker-disabled';
                } else if (workerCircuitOpen) {
                  runPerfReason = 'worker-circuit-open';
            } else if (isCodeUiMode) {
              runPerfReason = 'code-ui-mode';
            } else if (wantsIdeGui) {
              runPerfReason = 'idegui-detected';
            } else if (workerRiskReason) {
              runPerfReason = workerRiskReason;
            }
          }

          if (workerRunnerEnabled && !isCodeUiMode) {
            if (usesBlockingInput) {
              console.info('[Run] input() detected - worker input bridge active');
            }
            if (wantsIdeGui) {
              console.info('[Run] idegui import detected - using main-thread runner for compatibility');
                } else if (workerCircuitOpen) {
                  console.info('[Run] worker circuit open - temporarily using main-thread runner');
            } else if (workerRiskReason) {
              console.info(`[Run] ${workerRiskReason} - using main-thread runner for compatibility`);
            }
          }

      const precollectInputsBeforeRun = window.PYIDE_INPUT_COLLECT_BEFORE_RUN === true;
      if (usesBlockingInput && !canUseWorkerRunner && precollectInputsBeforeRun) {
        await collectOverlayInputsForCode(code);
      }

      let projectRuntimePayload = null;
      if (currentProject && typeof window.getProjectPythonRuntimePayload === 'function') {
        try {
          projectRuntimePayload = await window.getProjectPythonRuntimePayload();
        } catch (projectRuntimeError) {
          console.warn('[Run] project runtime payload fallback to null:', projectRuntimeError);
        }
      }

      if (!projectRuntimePayload && hasFolderStructure && currentTask?.id) {
        try {
          projectRuntimePayload = await buildFolderTaskRuntimePayload(
            currentTask.id,
            activeIsPython ? activePath : 'init.py'
          );
        } catch (taskRuntimeError) {
          console.warn('[Run] task runtime payload fallback to null:', taskRuntimeError);
        }
      }

      const selectedPackages = Array.from(new Set([
        ...getSelectedPackages(),
        ...inferPackagesFromCode(code),
      ]));
      const enableMatplotlib = selectedPackages.includes("matplotlib");

      if (canUseWorkerRunner) {
            try {
              runPerfMode = 'worker';
              runPerfReason = workerPrewarmDone
                ? 'worker-prewarmed'
                : (workerPrewarmStarted ? 'worker-warming' : 'worker-coldstart');
              activeRunMode = 'worker';
              setRunButtonState(true, 'worker');

              const result = await runCodeWithWorker({
                code,
                packages: selectedPackages,
                enableMatplotlib,
                projectRuntime: projectRuntimePayload,
              });
                  runPerfWorkerTimings = result?.timings || null;

              clearQuickFixState();
              if (result?.type === 'stopped') {
                runPerfOutcome = 'stopped';
                lintEl.innerHTML = '<span class="lint-checking">Ausfuehrung gestoppt.</span>';
              } else {
                    recordWorkerRunSuccess();
                setLintOk();
              }

              const currentPath = String(window.currentFile?.path || '');
              if (currentTask?.id && currentPath && typeof window.setTaskSavedSnapshot === 'function') {
                window.setTaskSavedSnapshot(currentTask.id, currentPath, code);
              }
              return;
            } catch (workerRunError) {
                  const workerErrorCode = String(workerRunError?.code || 'WORKER_ERROR');
                  const workerErrorMessage = String(workerRunError?.message || workerRunError || '');
                  if (workerErrorCode === 'WORKER_TIMEOUT') {
                    recordWorkerRunFailure('timeout', workerErrorMessage);
                  } else {
                    recordWorkerRunFailure('error', workerErrorMessage);
                  }
                  workerFallbackCount += 1;
              console.warn('[Run] worker execution failed, fallback to main-thread:', workerRunError);
              resetOutputPanels();
              clearQuickFixState();
              activeRunMode = 'main';
              setRunButtonState(true, 'main');
              runPerfMode = 'main';
                  const workerErrorText = String(workerRunError?.message || workerRunError || '').toLowerCase();
                  runPerfReason = workerErrorText.includes('timeout')
                    ? 'worker-timeout-fallback-main'
                    : 'worker-error-fallback-main';
            }
      }

      await ensurePackages(selectedPackages);

        // Only ensure idegui module for code_ui tasks or html/mixed projects (not for regular code tasks)
        if ((isCodeUiTask || isProjectCodeUiMode) && wantsIdeGui) {
          await ensureIdeGuiModule();
        }

        // Preserve globals between RUN and trigger calls for code_ui tasks and HTML/mixed projects
        const usePreservedGlobals = isCodeUiMode;

        await pyodide.runPythonAsync(`
from js import document, window as js_window
import sys, warnings
import builtins
import re
import os
import json
import types

warnings.filterwarnings("ignore", message="FigureCanvasAgg is non-interactive")

_original_input = builtins.input
def _custom_input(prompt=''):
    prompt_str = str(prompt) if prompt else ''
    result = js_window.__pyideConsumeInput(prompt_str)
    try:
        if result is not None and hasattr(result, "then"):
            import asyncio
            result = asyncio.get_event_loop().run_until_complete(result)
    except Exception:
        # Keep input() robust if promise bridging is unavailable.
        pass
    return '' if result is None else str(result)

builtins.input = _custom_input

def outputClear():
    js_window.__pyideOutputClear()

def outputWrite(value=''):
    text = '' if value is None else str(value)
    js_window.__pyideOutputWrite(text)

def outputFlush():
    js_window.__pyideOutputFlush()
    try:
        import asyncio
        checkpoint = js_window.__pyideOutputCheckpoint
        promise = checkpoint()
        if promise is not None and hasattr(promise, "then"):
            asyncio.get_event_loop().run_until_complete(promise)
    except Exception:
        # Keep outputFlush robust even when no loop/frame integration is available.
        pass

def clear_output():
    outputClear()

def redraw(value=''):
    outputClear()
    outputWrite(value)

pyide_module = sys.modules.get('pyide')
if pyide_module is None:
  pyide_module = types.ModuleType('pyide')

pyide_module.outputClear = outputClear
pyide_module.outputWrite = outputWrite
pyide_module.outputFlush = outputFlush
pyide_module.clear_output = clear_output
pyide_module.redraw = redraw
pyide_module.output_clear = outputClear
pyide_module.output_write = outputWrite
pyide_module.output_flush = outputFlush
pyide_module.__all__ = [
  'outputClear',
  'outputWrite',
  'outputFlush',
  'clear_output',
  'redraw',
  'output_clear',
  'output_write',
  'output_flush',
]
sys.modules['pyide'] = pyide_module

enable_matplotlib = ${enableMatplotlib ? "True" : "False"}
if enable_matplotlib:
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

from io import BytesIO
import base64

code = ${JSON.stringify(code)}
project_runtime = json.loads(${JSON.stringify(JSON.stringify(projectRuntimePayload ?? null))})

if isinstance(project_runtime, dict):
  runtime_root = str(project_runtime.get('root') or '/project')
  runtime_files = project_runtime.get('files') or []

  if runtime_files:
    try:
      os.makedirs(runtime_root, exist_ok=True)
    except Exception:
      pass

    for entry in runtime_files:
      if not isinstance(entry, dict):
        continue
      rel_path = str(entry.get('path') or '').replace('\\\\', '/').strip('/')
      if not rel_path:
        continue

      abs_path = runtime_root.rstrip('/') + '/' + rel_path
      parent_dir = os.path.dirname(abs_path)
      if parent_dir:
        os.makedirs(parent_dir, exist_ok=True)

      with open(abs_path, 'w', encoding='utf-8') as fh:
        fh.write(str(entry.get('content') or ''))

    main_rel = str(project_runtime.get('mainPath') or '').replace('\\\\', '/').strip('/')
    if main_rel:
      project_main_path = runtime_root.rstrip('/') + '/' + main_rel
      project_main_dir = os.path.dirname(project_main_path)
      if project_main_dir and project_main_dir not in sys.path:
        sys.path.insert(0, project_main_dir)
      if project_main_dir:
        try:
          os.chdir(project_main_dir)
        except Exception:
          pass

    if runtime_root not in sys.path:
      sys.path.insert(0, runtime_root)

_js_output_write = js_window.__pyideOutputWrite
_js_output_flush = js_window.__pyideOutputFlush

class JSOut:
    def __init__(self, el):
        self.el = document.getElementById(el)
    def write(self, s):
        text = str(s)
        if self.el is not None and getattr(self.el, "id", "") == "output-container":
            outputWrite(text)
        elif self.el is not None and text.strip():
            self.el.innerText += text + "\\n"
    def flush(self):
        if self.el is not None and getattr(self.el, "id", "") == "output-container":
            outputFlush()

old_out, old_err = sys.stdout, sys.stderr
sys.stdout = JSOut("output-container")
sys.stderr = JSOut("lint-container")

try:
    # Block idegui import for non-code_ui tasks (prevent GUI activation for regular code tasks)
    is_code_ui_mode = ${isCodeUiMode ? "True" : "False"}
    if not is_code_ui_mode:
        # Create a dummy idegui module that blocks real imports
        class DummyIdeguiModule:
            def __getattr__(self, name):
                raise ImportError("idegui is only available for code_ui tasks")
        sys.modules['idegui'] = DummyIdeguiModule()
    
    # For Code-UI tasks: use persistent globals to preserve state between triggers
    use_preserved = ${usePreservedGlobals ? "True" : "False"}
    if use_preserved:
        # Try to restore from window.__codeUiGlobals if it exists
        if hasattr(js_window, '__codeUiGlobals'):
            g = js_window.__codeUiGlobals
            g.setdefault('outputClear', outputClear)
            g.setdefault('outputWrite', outputWrite)
            g.setdefault('outputFlush', outputFlush)
            g.setdefault('clear_output', clear_output)
            g.setdefault('redraw', redraw)
        else:
            g = {
                "__name__": "__main__",
                "outputClear": outputClear,
                "outputWrite": outputWrite,
                "outputFlush": outputFlush,
                "clear_output": clear_output,
                "redraw": redraw,
            }
            js_window.__codeUiGlobals = g
    else:
        g = {
            "__name__": "__main__",
            "outputClear": outputClear,
            "outputWrite": outputWrite,
            "outputFlush": outputFlush,
            "clear_output": clear_output,
            "redraw": redraw,
        }
    
    # Execute user code (user code imports idegui itself if needed)
    exec(compile(code, "<usercode>", "exec"), g, g)
    
    # Store globals back to window for next trigger call
    if use_preserved:
        js_window.__codeUiGlobals = g

    # Traditional mode ONLY: Auto-dispatch trigger if set (only for code_ui tasks)
    if is_code_ui_mode:
        # Check if user code imported idegui
        ui = g.get('ui') or sys.modules.get('idegui')
        if ui:
            if hasattr(ui, '_refresh_trigger'):
                ui._refresh_trigger()
            event_driven_mode = getattr(js_window, '__codeUiEventDrivenMode', False)
            trigger_name = str(getattr(getattr(ui, "trigger", object()), "name", "") or "")

            # Only dispatch in traditional mode (data-run-python), not in event-driven mode
            if trigger_name and not event_driven_mode:
              def _as_identifier(name):
                return re.sub(r"\\W|^(?=\\d)", "_", str(name or ""))

              direct = trigger_name
              ident = _as_identifier(trigger_name)
              candidates = []
              for candidate in [direct, f"run_{direct}", ident, f"run_{ident}"]:
                if candidate and candidate not in candidates:
                  candidates.append(candidate)

              callback = None
              for candidate in candidates:
                func = g.get(candidate)
                if callable(func):
                  callback = func
                  break

              if callback is not None:
                try:
                  callback(ui.trigger)
                except TypeError:
                  callback()

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
    js_window.__pyideOutputFlush()
`);
        clearQuickFixState();
        setLintOk();

        // After successful RUN: sync Draft to Snapshot to prevent false "unsaved changes" warnings
        // RUN doesn't modify code, so the draft state should match the snapshot (saved) state
        const currentPath = String(window.currentFile?.path || '');
        if (currentTask?.id && currentPath && typeof window.setTaskSavedSnapshot === 'function') {
          window.setTaskSavedSnapshot(currentTask.id, currentPath, code);
        }
      } catch (e) {
            runPerfOutcome = 'error';
        const parsed = resolveErrorLine(e.message || String(e), code);

        setQuickFixState(parsed.line, parsed.token, parsed.suggestion);
        setLintError(parsed.line, parsed.error, parsed.hint, parsed.token, parsed.suggestion);
        setErrorMarker(parsed.line, parsed.error);
      } finally {
            const runPerfEnd = (window.performance && typeof window.performance.now === 'function')
              ? window.performance.now()
              : Date.now();
            const runPerfMs = Math.round((runPerfEnd - runPerfStart) * 10) / 10;
                const timingStats = window.__pyideRunTimingStats || {
                  totalRuns: 0,
                  totalMs: 0,
                  byMode: {
                    main: { runs: 0, ms: 0 },
                    worker: { runs: 0, ms: 0 },
                  },
                  byOutcome: {},
                      byReason: {},
                  fallbackCount: 0,
                  byFallbackType: {},
                };
                timingStats.totalRuns += 1;
                timingStats.totalMs += runPerfMs;
                if (!timingStats.byMode[runPerfMode]) {
                  timingStats.byMode[runPerfMode] = { runs: 0, ms: 0 };
                }
                timingStats.byMode[runPerfMode].runs += 1;
                timingStats.byMode[runPerfMode].ms += runPerfMs;
                timingStats.byOutcome[runPerfOutcome] = (timingStats.byOutcome[runPerfOutcome] || 0) + 1;
                    timingStats.byReason[runPerfReason] = (timingStats.byReason[runPerfReason] || 0) + 1;
                const fallbackType = runPerfReason.startsWith('worker-') && runPerfReason.includes('-fallback-main')
                  ? runPerfReason
                  : '';
                if (fallbackType) {
                  timingStats.fallbackCount += 1;
                  timingStats.byFallbackType[fallbackType] = (timingStats.byFallbackType[fallbackType] || 0) + 1;
                }
                window.__pyideRunTimingStats = timingStats;

                    const reasonEntries = Object.entries(timingStats.byReason || {});
                    const topReasonEntry = reasonEntries.sort((a, b) => (b[1] || 0) - (a[1] || 0))[0] || null;
                    const topReason = topReasonEntry ? `${topReasonEntry[0]}:${topReasonEntry[1]}` : null;

                const avgMainMs = timingStats.byMode.main.runs > 0
                  ? Math.round((timingStats.byMode.main.ms / timingStats.byMode.main.runs) * 10) / 10
                  : null;
                const avgWorkerMs = timingStats.byMode.worker.runs > 0
                  ? Math.round((timingStats.byMode.worker.ms / timingStats.byMode.worker.runs) * 10) / 10
                  : null;

            if (window.PYIDE_RUN_TIMING !== false) {
                  const runTimingPayload = {
                id: runPerfId,
                mode: runPerfMode,
                outcome: runPerfOutcome,
                reason: runPerfReason,
                ms: runPerfMs,
                codeLength: String(code || '').length,
                    totals: {
                      runs: timingStats.totalRuns,
                      avgMainMs,
                      avgWorkerMs,
                          currentReasonCount: timingStats.byReason[runPerfReason] || 0,
                          topReason,
                      fallbackCount: timingStats.fallbackCount || 0,
                    },
                        workerTimings: runPerfWorkerTimings,
                  };
                  console.info('[RunTiming]', runTimingPayload);
                  updateRunTimingOverlay(runTimingPayload);
            }

        runInProgress = false;
        activeRunMode = 'main';
        setRunButtonState(false, 'main');
      }
    });
  });
}

initPyodideAndEditor();
