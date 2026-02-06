// public/js/editor-completions.js
import * as C from "./editor-completions.config.js";

export function registerPythonCompletions(monaco, editor) {
  // Safe config reads
  const NUMPY_COMPLETIONS   = Array.isArray(C.NUMPY_COMPLETIONS) ? C.NUMPY_COMPLETIONS : [];
  const PLT_COMPLETIONS     = Array.isArray(C.PLT_COMPLETIONS) ? C.PLT_COMPLETIONS : [];
  const AX_COMPLETIONS      = Array.isArray(C.AX_COMPLETIONS) ? C.AX_COMPLETIONS : [];
  const BUILTIN_COMPLETIONS = Array.isArray(C.BUILTIN_COMPLETIONS) ? C.BUILTIN_COMPLETIONS : [];

  const NP_SNIPPETS         = Array.isArray(C.NP_SNIPPETS) ? C.NP_SNIPPETS : [];
  const PLT_SNIPPETS        = Array.isArray(C.PLT_SNIPPETS) ? C.PLT_SNIPPETS : [];
  const AX_SNIPPETS         = Array.isArray(C.AX_SNIPPETS) ? C.AX_SNIPPETS : [];
  const BUILTIN_SNIPPETS    = Array.isArray(C.BUILTIN_SNIPPETS) ? C.BUILTIN_SNIPPETS : [];

  const STRING_HOVER_DOCS   = C.STRING_HOVER_DOCS && typeof C.STRING_HOVER_DOCS === "object" ? C.STRING_HOVER_DOCS : {};
  const LIST_HOVER_DOCS     = C.LIST_HOVER_DOCS && typeof C.LIST_HOVER_DOCS === "object" ? C.LIST_HOVER_DOCS : {};
  const DICT_HOVER_DOCS     = C.DICT_HOVER_DOCS && typeof C.DICT_HOVER_DOCS === "object" ? C.DICT_HOVER_DOCS : {};

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

      // NP completions (like before)
      if (/\bnp\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...NP_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "NumPy (snippet)", s.doc)),
            ...NUMPY_COMPLETIONS.map((n) => mkFunctionSuggestion(n, "NumPy")),
          ],
        };
      }

      // PLT completions
      if (/\bplt\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...PLT_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "matplotlib.pyplot (snippet)", s.doc)),
            ...PLT_COMPLETIONS.map((n) => mkFunctionSuggestion(n, "matplotlib.pyplot")),
          ],
        };
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
    const parts = src.split(/```/);
    let out = "";
    for (let i = 0; i < parts.length; i++) {
      const chunk = parts[i];
      if (i % 2 === 1) {
        // Code block: remove lang identifier and format
        const cleaned = chunk.replace(/^\s*\w+\s*\n/, "");
        out += `<pre><code>${escapeHtml(cleaned.trim())}</code></pre>`;
      } else {
        // Regular text
        let html = escapeHtml(chunk);
        // Inline code (backticks)
        html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
        // Bold (**text**)
        html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        // Line breaks
        html = html.replace(/\n/g, "<br>");
        out += html;
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

    if (canon === "math" || best.left === "math") {
      return { kind: "module", module: "math", name: best.name };
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
    const m = txt.match(/[A-Za-z_]\w*/);
    return m ? m[0] : null;
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

    // module math
    if (canon === "math" || left === "math") {
      const key = `math.${name}`;
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
