// public/js/editor-completions.js
import * as C from "./editor-completions.config.js?t=1770415900";

let helpJson = null; // Cached help.json data
let NUMPY_COMPLETIONS = [];
let PLT_COMPLETIONS = [];
let AX_COMPLETIONS = [];
let BUILTIN_COMPLETIONS = [];
let NP_SNIPPETS = [];
let PLT_SNIPPETS = [];
let AX_SNIPPETS = [];
let BUILTIN_SNIPPETS = [];
let STRING_HOVER_DOCS = {};
let LIST_HOVER_DOCS = {};
let DICT_HOVER_DOCS = {};

export async function registerPythonCompletions(monaco, editor) {
  // Load help.json to extract numpy and matplotlib functions
  if (!helpJson) {
    try {
      const res = await fetch("index.php?api=help&key=__list__", { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        if (data.ok && Array.isArray(data.keys)) {
          // Convert array of keys to object for compatibility
          helpJson = {};
          data.keys.forEach(key => { helpJson[key] = true; });
          console.log("[editor-completions] Loaded help.json with " + data.keys.length + " keys");
        } else {
          console.warn("[editor-completions] Invalid help.json response", data);
          helpJson = {};
        }
      }
    } catch (e) {
      console.error("[editor-completions] Failed to load help keys", e);
      helpJson = {};
    }
  }

  // Extract numpy and matplotlib functions from help.json (if available)
  let foundNumpyKeys = [];
  let foundPltKeys = [];
  
  if (helpJson && typeof helpJson === 'object' && Object.keys(helpJson).length > 0) {
    foundNumpyKeys = Object.keys(helpJson)
      .filter(k => k.startsWith('np.'))
      .map(k => k.substring(3)); // Remove 'np.' prefix
    
    foundPltKeys = Object.keys(helpJson)
      .filter(k => k.startsWith('plt.'))
      .map(k => k.substring(4)); // Remove 'plt.' prefix
  }

  // Use config defaults (curated, main functions only) rather than all help.json entries
  // This avoids overwhelming the user with 100+ numpy functions
  // Help.json is still available for the help panel via API
  NUMPY_COMPLETIONS = Array.isArray(C.NUMPY_COMPLETIONS) ? C.NUMPY_COMPLETIONS : [];
  PLT_COMPLETIONS = Array.isArray(C.PLT_COMPLETIONS) ? C.PLT_COMPLETIONS : [];

  AX_COMPLETIONS      = Array.isArray(C.AX_COMPLETIONS) ? C.AX_COMPLETIONS : [];
  BUILTIN_COMPLETIONS = Array.isArray(C.BUILTIN_COMPLETIONS) ? C.BUILTIN_COMPLETIONS : [];

  console.log("[editor-completions] Loaded arrays:", {
    numpy: NUMPY_COMPLETIONS.length,
    plt: PLT_COMPLETIONS.length,
    ax: AX_COMPLETIONS.length,
    builtin: BUILTIN_COMPLETIONS.length,
  });

  NP_SNIPPETS         = Array.isArray(C.NP_SNIPPETS) ? C.NP_SNIPPETS : [];
  PLT_SNIPPETS        = Array.isArray(C.PLT_SNIPPETS) ? C.PLT_SNIPPETS : [];
  AX_SNIPPETS         = Array.isArray(C.AX_SNIPPETS) ? C.AX_SNIPPETS : [];
  BUILTIN_SNIPPETS    = Array.isArray(C.BUILTIN_SNIPPETS) ? C.BUILTIN_SNIPPETS : [];

  STRING_HOVER_DOCS   = C.STRING_HOVER_DOCS && typeof C.STRING_HOVER_DOCS === "object" ? C.STRING_HOVER_DOCS : {};
  LIST_HOVER_DOCS     = C.LIST_HOVER_DOCS && typeof C.LIST_HOVER_DOCS === "object" ? C.LIST_HOVER_DOCS : {};
  DICT_HOVER_DOCS     = C.DICT_HOVER_DOCS && typeof C.DICT_HOVER_DOCS === "object" ? C.DICT_HOVER_DOCS : {};

  // Math function list (matches scraper targets)
  const MATH_FUNCS = [
    "ceil","floor","fabs","factorial","fmod","frexp","fsum","gcd","isfinite","isinf","isnan",
    "ldexp","modf","prod","remainder","trunc",
    "exp","expm1","log","log1p","log2","log10","pow","sqrt",
    "acos","asin","atan","atan2","cos","sin","tan","degrees","radians","hypot",
    "copysign","isclose"
  ];

  // Method lists (str/list/dict)
  const STRING_METHODS = [
    "lower","upper","title","capitalize","swapcase","casefold",
    "strip","lstrip","rstrip","removeprefix","removesuffix",
    "ljust","rjust","center","zfill",
    "find","rfind","index","rindex","count","startswith","endswith",
    "isalpha","isalnum","isdigit","isdecimal","isnumeric","isspace",
    "islower","isupper","istitle",
    "split","rsplit","splitlines","join","partition","rpartition",
    "replace","translate","maketrans","format","format_map","encode",
  ];
  const LIST_METHODS = ["append","extend","insert","remove","pop","clear","index","count","sort","reverse","copy"];
  const DICT_METHODS = ["get","setdefault","update","pop","popitem","clear","keys","values","items","copy","fromkeys"];

  const InsertAsSnippet = monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet;

  function mkMethodSuggestion(name, detail) {
    return { label: name, kind: monaco.languages.CompletionItemKind.Method, insertText: name, detail };
  }
  function mkFunctionSuggestion(name, detail) {
    return { label: name, kind: monaco.languages.CompletionItemKind.Function, insertText: name, detail };
  }
  function mkPrefixedFunctionSuggestion(module, name, detail) {
    return {
      label: `${module}.${name}`,
      kind: monaco.languages.CompletionItemKind.Function,
      insertText: `${module}.${name}`,
      filterText: `${module}.${name}`,
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

  function escapeRegExp(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); }

  function inferVarType(varName, textUpToCursor) {
    const reAssign = new RegExp(`\\b${escapeRegExp(varName)}\\s*=`);
    const lines = String(textUpToCursor).split("\n");
    for (let i = lines.length - 1; i >= 0; i--) {
      const line = lines[i];
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;
      if (!reAssign.test(line)) continue;

      const rhsTrim = (line.split("=", 2)[1] ?? "").trim();

      if (/^(?:[rubf]|rb|br|rf|fr|urf|fur|r|u|b|f){0,3}(['"])/i.test(rhsTrim)) return "str";
      if (/^(?:[rubf]|rb|br|rf|fr){0,3}("""|''')/i.test(rhsTrim)) return "str";
      if (/^str\s*\(/.test(rhsTrim)) return "str";
      if (/^input\s*\(/.test(rhsTrim)) return "str";

      if (/^\[\s*.*\s*\]$/.test(rhsTrim)) return "list";
      if (/^list\s*\(/.test(rhsTrim)) return "list";

      if (/^\{\s*.*\s*\}$/.test(rhsTrim)) return "dict";
      if (/^dict\s*\(/.test(rhsTrim)) return "dict";

      return null;
    }
    return null;
  }

  function getTextUpToLine(model, lineNumber) {
    return model.getValueInRange({
      startLineNumber: 1,
      startColumn: 1,
      endLineNumber: lineNumber,
      endColumn: model.getLineMaxColumn(lineNumber),
    });
  }

  // Aliases: also recognize "from math import ..."
  function parseModuleAliases(fullText) {
    const map = new Map();

    const lines = String(fullText).split("\n");
    for (const ln of lines) {
      const s = ln.trim();
      if (!s || s.startsWith("#")) continue;

      // import math / import math as m
      let m = s.match(/^import\s+math(?:\s+as\s+([A-Za-z_]\w*))?\b/);
      if (m) { map.set(m[1] || "math", "math"); continue; }

      // from math import sqrt, sin ...
      m = s.match(/^from\s+math\s+import\b/);
      if (m) { map.set("math", "math"); continue; }

      // import numpy as np
      m = s.match(/^import\s+numpy\s+as\s+([A-Za-z_]\w*)\b/);
      if (m) { map.set(m[1], "numpy"); continue; }

      // import matplotlib.pyplot as plt
      m = s.match(/^import\s+matplotlib\.pyplot\s+as\s+([A-Za-z_]\w*)\b/);
      if (m) { map.set(m[1], "matplotlib.pyplot"); continue; }
    }
    return map;
  }

  // ---------------- Completion Provider ----------------
  console.log("[editor-completions] Registering completion provider for python...");
  const completionDisposable = monaco.languages.registerCompletionItemProvider("python", {
    triggerCharacters: [".", "_", "("],
    provideCompletionItems(model, position) {
      const line = model.getLineContent(position.lineNumber);
      const prefix = line.slice(0, position.column - 1);
      const fullText = model.getValue();

      const aliases = parseModuleAliases(fullText);
      console.log('[editor-completions] aliases:', Array.from(aliases.entries()));

      // NP completions (like before)
      if (/\bnp\.\w*$/.test(prefix)) {
       console.log("[NP] Matched! prefix:", prefix, "suggestions:", NUMPY_COMPLETIONS.length, 'first:', NUMPY_COMPLETIONS.slice(0,5));
        const match = prefix.match(/\bnp\.\w*$/);
        const matchStartIndex = match ? (prefix.length - match[0].length) : 0;
        const matchStartColumn = matchStartIndex + 1; // Convert to 1-indexed
        
        // Show only plain functions, not snippets (avoids filtering issues and double suggestions)
        let sugs = [
          ...NUMPY_COMPLETIONS.map((n) => mkPrefixedFunctionSuggestion('np', n, "NumPy")),
        ];
        
        // Set range to replace from start of 'np' to cursor (so np.<typed> gets replaced by full suggestion)
        try {
          const range = new monaco.Range(position.lineNumber, matchStartColumn, position.lineNumber, position.column);
          sugs = sugs.map(s => ({ ...s, range }));
          console.log('[NP] range:', { startColumn: matchStartColumn, endColumn: position.column });
        } catch (e) {}
        
        try { console.log('[NP] suggestions JSON:', JSON.stringify(sugs.slice(0,8), null, 2)); } catch(e){ console.log('[NP] suggestions objects sample (raw):', sugs.slice(0,8)); }
        return { suggestions: sugs };
      }

      // PLT completions
      if (/\bplt\.\w*$/.test(prefix)) {
       console.log("[PLT] Matched! prefix:", prefix, "suggestions:", PLT_COMPLETIONS.length, 'first:', PLT_COMPLETIONS.slice(0,5));
        const match = prefix.match(/\bplt\.\w*$/);
        const matchStartIndex = match ? (prefix.length - match[0].length) : 0;
        const matchStartColumn = matchStartIndex + 1; // Convert to 1-indexed
        
        // Show only plain functions, not snippets
        let sugs = [
          ...PLT_COMPLETIONS.map((n) => mkPrefixedFunctionSuggestion('plt', n, "matplotlib.pyplot")),
        ];
        
        // Set range to replace from start of 'plt' to cursor
        try {
          const range = new monaco.Range(position.lineNumber, matchStartColumn, position.lineNumber, position.column);
          sugs = sugs.map(s => ({ ...s, range }));
          console.log('[PLT] range:', { startColumn: matchStartColumn, endColumn: position.column });
        } catch (e) {}
        
        try { console.log('[PLT] suggestions JSON:', JSON.stringify(sugs.slice(0,8), null, 2)); } catch(e){ console.log('[PLT] suggestions objects sample (raw):', sugs.slice(0,8)); }
        return { suggestions: sugs };
      }

       if (/\bnp\.|plt\./.test(prefix)) {
         console.log("[DEBUG] Prefix contains np. or plt. but didn't match:", prefix);
       }
      // AX completions
      if (/\bax\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...AX_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Axes (snippet)", s.doc)),
            ...AX_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Axes")),
          ],
        };
      }

      // ✅ MATH completions: DO NOT depend on import
      // - match "math." always
      // - match alias if "import math as m"
      // Fast path: explicit `math.` typed -> always suggest math functions
      if (/\bmath\.\w*$/.test(prefix)) {
        return { suggestions: MATH_FUNCS.map((fn) => mkFunctionSuggestion(fn, "math")) };
      }

      // Also accept aliases or other left-hand identifiers that map to math
      const dm = prefix.match(/\b([A-Za-z_]\w*)\.\w*$/);
      if (dm) {
        const left = dm[1];
        const canon = aliases.get(left) || left;
        if (canon === "math" || left === "math") {
          return { suggestions: MATH_FUNCS.map((fn) => mkFunctionSuggestion(fn, "math")) };
        }
      }

      // <var>. context (str/list/dict)
      const vm = prefix.match(/\b([A-Za-z_]\w*)\.\w*$/);
      const varName = vm ? vm[1] : null;
      if (varName) {
        const upToCursor = model.getValueInRange({
          startLineNumber: 1,
          startColumn: 1,
          endLineNumber: position.lineNumber,
          endColumn: position.column,
        });
        const t = inferVarType(varName, upToCursor);
        if (t === "str") return { suggestions: STRING_METHODS.map((x) => mkMethodSuggestion(x, "str")) };
        if (t === "list") return { suggestions: LIST_METHODS.map((x) => mkMethodSuggestion(x, "list")) };
        if (t === "dict") return { suggestions: DICT_METHODS.map((x) => mkMethodSuggestion(x, "dict")) };
      }

      return {
        suggestions: [
          ...BUILTIN_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Python (snippet)", s.doc)),
          ...BUILTIN_COMPLETIONS.map((n) => mkFunctionSuggestion(n, "Python builtin")),
        ],
      };
    },
  });

  // ---------------- Help rendering ----------------
  function escapeHtml(s) {
    return String(s)
      .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
      .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
  }

  function mdToHtml(md) {
    const src = String(md || "");
    
    // Strip W3Schools promotional boilerplate and common navigation text
    let cleaned = src
      .replace(/W3Schools offers.*?skills\.\s*/gi, "")
      .replace(/Learn by examples.*?\s*/gi, "")
      .replace(/Try it Yourself.*?\s*/gi, "")
      .replace(/^(Advertisement|Share|Report Error).*$/gmi, "");
    
    // Collapse multiple consecutive line breaks
    cleaned = cleaned.replace(/\n{3,}/g, "\n\n");
    
    const parts = cleaned.split(/```/);
    let out = "";
    for (let i = 0; i < parts.length; i++) {
      const chunk = parts[i];
      if (i % 2 === 1) {
        // Code block: remove lang identifier and format
        const codeContent = chunk.replace(/^\s*\w+\s*\n/, "").trim();
        if (codeContent) {
          out += `<pre><code>${escapeHtml(codeContent)}</code></pre>`;
        }
      } else {
        // Regular text: preserve paragraph structure
        const text = chunk.trim();
        if (!text) continue;
        
        let html = escapeHtml(text);
        // Inline code (backticks)
        html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
        // Bold (**text**)
        html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        // Headers (# text)
        html = html.replace(/^#+\s+(.+?)$/gm, "<strong>$1</strong>");
        // URLs as clickable links (but not inside tags)
        html = html.replace(/(?<![">])(https?:\/\/[^\s<>]+)/g, '<a href="$1" target="_blank">$1</a>');
        // Multiple newlines -> paragraph break
        const paragraphs = html.split(/\n\s*\n/);
        out += paragraphs.map(p => `<p>${p.replace(/\n/g, "<br>")}</p>`).join("");
      }
    }
    return out;
  }

  function setHelpPanel(content, { isMd = true } = {}) {
    const el = document.getElementById("help-container");
    if (!el) return;
    el.innerHTML = isMd ? mdToHtml(content) : String(content);
  }

  // Local help fetch
  const helpCache = new Map();
  let lastKey = null;

  // Build set of known help keys to avoid 404 spam
  const knownHelpKeys = new Set();
  MATH_FUNCS.forEach(fn => knownHelpKeys.add(`math.${fn}`));
  NUMPY_COMPLETIONS.forEach(fn => knownHelpKeys.add(`np.${fn}`));
  PLT_COMPLETIONS.forEach(fn => knownHelpKeys.add(`plt.${fn}`));
  AX_COMPLETIONS.forEach(fn => knownHelpKeys.add(`ax.${fn}`));
  STRING_METHODS.forEach(m => knownHelpKeys.add(`str.${m}`));
  LIST_METHODS.forEach(m => knownHelpKeys.add(`list.${m}`));
  DICT_METHODS.forEach(m => knownHelpKeys.add(`dict.${m}`));

  function isKnownHelpKey(key) {
    return knownHelpKeys.has(key);
  }

  async function fetchHelp(key) {
    if (!key) return null;
    // Skip API call if key is not in our known list
    if (!isKnownHelpKey(key)) {
      const miss = { ok: false, md: null };
      helpCache.set(key, miss);
      return miss;
    }
    if (helpCache.has(key)) return helpCache.get(key);
    try {
      const res = await fetch(`index.php?api=help&key=${encodeURIComponent(key)}`, { cache: "no-store" });
      if (!res.ok) { const miss = { ok:false, md:null }; helpCache.set(key, miss); return miss; }
      const data = await res.json();
      const out = { ok: !!data?.ok, md: data?.md || null };
      helpCache.set(key, out);
      return out;
    } catch {
      const err = { ok:false, md:null }; helpCache.set(key, err); return err;
    }
  }

  function buildHelpKey(ctx) {
    if (!ctx) return null;
    if (ctx.kind === "method") {
      if (ctx.type === "str") return `str.${ctx.name}`;
      if (ctx.type === "list") return `list.${ctx.name}`;
      if (ctx.type === "dict") return `dict.${ctx.name}`;
    }
    if (ctx.kind === "module") {
      return `${ctx.module}.${ctx.name}`;
    }
    return null;
  }

  // Detect dotted expression near cursor: var.method OR module.func (math + alias)
  function getContextNearCursor(model, position) {
    const fullText = model.getValue();
    const aliases = parseModuleAliases(fullText);

    const line = model.getLineContent(position.lineNumber);
    const col0 = Math.max(0, position.column - 1);
    const start = Math.max(0, col0 - 120);
    const end = Math.min(line.length, col0 + 120);
    const windowText = line.slice(start, end);

    const re = /\b([A-Za-z_]\w*)\.(\w+)\b/g;
    let best = null;
    let m;
    while ((m = re.exec(windowText)) !== null) {
      const left = m[1];
      const name = m[2];
      const absStart = start + m.index;
      const absEnd = absStart + m[0].length;
      const dist = Math.min(Math.abs(col0 - absStart), Math.abs(col0 - absEnd));
      if (!best || dist < best.dist) best = { left, name, dist };
    }
    if (!best) return null;

    const canon = aliases.get(best.left) || best.left;

    // Handle math module
    if (canon === "math" || best.left === "math") {
      return { kind: "module", module: "math", name: best.name };
    }

    // Handle numpy as np
    if (canon === "numpy") {
      return { kind: "module", module: "np", name: best.name };
    }

    // Handle matplotlib.pyplot as plt
    if (canon === "matplotlib.pyplot") {
      return { kind: "module", module: "plt", name: best.name };
    }

    const upToLine = getTextUpToLine(model, position.lineNumber);
    const t = inferVarType(best.left, upToLine);
    if (!t) return null;
    return { kind: "method", type: t, name: best.name };
  }

  function debounce(fn, ms) {
    let t = null;
    return (...args) => { if (t) clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  const updateHelp = debounce(async () => {
    const model = editor?.getModel?.();
    const pos = editor?.getPosition?.();
    if (!model || !pos) return;

    const ctx = getContextNearCursor(model, pos);
    if (!ctx) return;

    const key = buildHelpKey(ctx);
    if (!key || key === lastKey) return;

    const remote = await fetchHelp(key);
    if (remote?.ok && remote.md) {
      lastKey = key;
      setHelpPanel(remote.md, { isMd:true });
      return;
    }

    if (ctx.kind === "method") {
      let doc = null;
      if (ctx.type === "str") doc = STRING_HOVER_DOCS?.[ctx.name];
      else if (ctx.type === "list") doc = LIST_HOVER_DOCS?.[ctx.name];
      else if (ctx.type === "dict") doc = DICT_HOVER_DOCS?.[ctx.name];
      if (doc) {
        lastKey = key;
        setHelpPanel(doc, { isMd:true });
        return;
      }
    }

    lastKey = key;
    setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
  }, 120);

  const cursorDisposable = editor.onDidChangeCursorPosition(() => updateHelp());
  const changeDisposable = editor.onDidChangeModelContent(() => updateHelp());

  // Suggest-widget navigation: also update help for current selection
  function getFocusedSuggestLabel() {
    const widget = document.querySelector(".suggest-widget");
    if (!widget) return null;
    const style = getComputedStyle(widget);
    if (style.display === "none" || style.visibility === "hidden") return null;

    const focusedRow =
      widget.querySelector(".monaco-list-row.focused") ||
      widget.querySelector(".monaco-list-row.selected") ||
      widget.querySelector(".monaco-list-row[aria-selected='true']");
    if (!focusedRow) return null;

    const nameEl =
      focusedRow.querySelector(".label-name") ||
      focusedRow.querySelector(".monaco-highlighted-label") ||
      focusedRow;

    const txt = (nameEl.textContent || "").trim();
    // Extract the part after the last dot, or the whole identifier if no dot
    const m = txt.match(/(?:\.)?([A-Za-z_]\w*)(?:\(|$)/);
    return m ? m[1] : null;
  }

  function getLeftBeforeDot(model, position) {
    const line = model.getLineContent(position.lineNumber);
    const col0 = Math.max(0, position.column - 1);
    const left = line.slice(0, col0);
    const m = left.match(/\b([A-Za-z_]\w*)\.\w*$/) || left.match(/\b([A-Za-z_]\w*)\.$/);
    return m ? m[1] : null;
  }

  async function updateHelpFromSuggestSelection() {
    const model = editor?.getModel?.();
    const pos = editor?.getPosition?.();
    if (!model || !pos) return;

    const name = getFocusedSuggestLabel();
    if (!name) return;

    const left = getLeftBeforeDot(model, pos);
    if (!left) return;

    const fullText = model.getValue();
    const aliases = parseModuleAliases(fullText);
    const canon = aliases.get(left) || left;

    // Handle math module
    if (canon === "math" || left === "math") {
      const key = `math.${name}`;
      if (key === lastKey) return;

      const remote = await fetchHelp(key);
      lastKey = key;
      if (remote?.ok && remote.md) setHelpPanel(remote.md, { isMd:true });
      else setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
      return;
    }

    // Handle numpy as np
    if (canon === "numpy") {
      const key = `np.${name}`;
      if (key === lastKey) return;

      const remote = await fetchHelp(key);
      lastKey = key;
      if (remote?.ok && remote.md) setHelpPanel(remote.md, { isMd:true });
      else setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
      return;
    }

    // Handle matplotlib.pyplot as plt
    if (canon === "matplotlib.pyplot") {
      const key = `plt.${name}`;
      if (key === lastKey) return;

      const remote = await fetchHelp(key);
      lastKey = key;
      if (remote?.ok && remote.md) setHelpPanel(remote.md, { isMd:true });
      else setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
      return;
    }

    // var method
    const upToLine = getTextUpToLine(model, pos.lineNumber);
    const t = inferVarType(left, upToLine);
    if (!t) return;

    const key = (t === "str") ? `str.${name}` : (t === "list") ? `list.${name}` : (t === "dict") ? `dict.${name}` : null;
    if (!key || key === lastKey) return;

    const remote = await fetchHelp(key);
    lastKey = key;
    if (remote?.ok && remote.md) setHelpPanel(remote.md, { isMd:true });
    else setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
  }

  const keyDisposable = editor.onKeyDown((e) => {
    const k = e.browserEvent?.key || "";
    if (!["ArrowUp","ArrowDown","PageUp","PageDown"].includes(k)) return;
    setTimeout(() => { updateHelpFromSuggestSelection(); }, 0);
  });

  // Minimal tooltip: show title only (avoid overload) BUT now exists for math too
  const hoverDisposable = monaco.languages.registerHoverProvider("python", {
    provideHover: async (model, position) => {
      const ctx = getContextNearCursor(model, position);
      if (!ctx) return null;

      const key = buildHelpKey(ctx);
      if (!key) return null;

      const remote = await fetchHelp(key);
      const title = remote?.ok && remote.md
        ? (String(remote.md).split("\n").map(x => x.trim()).filter(Boolean)[0] || key)
        : key;

      return {
        range: new monaco.Range(position.lineNumber, Math.max(1, position.column - 1), position.lineNumber, position.column + 1),
        contents: [{ value: `**${title}**` }],
      };
    }
  });

  updateHelp();

  return {
    dispose() {
      try { completionDisposable?.dispose?.(); } catch {}
      try { hoverDisposable?.dispose?.(); } catch {}
      try { cursorDisposable?.dispose?.(); } catch {}
      try { changeDisposable?.dispose?.(); } catch {}
      try { keyDisposable?.dispose?.(); } catch {}
    }
  };
}
