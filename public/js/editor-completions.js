// public/js/editor-completions.js
import * as C from "./editor-completions.config.js";

export function registerPythonCompletions(monaco, editor) {
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

  // ---------------- Completion Provider ----------------
  const completionDisposable = monaco.languages.registerCompletionItemProvider("python", {
    triggerCharacters: [".", "_", "("],
    provideCompletionItems(model, position) {
      const line = model.getLineContent(position.lineNumber);
      const prefix = line.slice(0, position.column - 1);
      const fullText = model.getValue();

      const hasNp = /\bimport\s+numpy\s+as\s+np\b/.test(fullText);
      const hasPlt = /\bimport\s+matplotlib\.pyplot\s+as\s+plt\b/.test(fullText);
      const hasAx =
        /\bfig\s*,\s*ax\s*=\s*plt\.subplots\s*\(/.test(fullText) ||
        /\bax\s*=\s*plt\.subplots\s*\(/.test(fullText);

      if (hasNp && /\bnp\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...NP_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "NumPy (snippet)", s.doc)),
            ...NUMPY_COMPLETIONS.map((n) => mkMethodSuggestion(n, "NumPy")),
          ],
        };
      }

      if (hasPlt && /\bplt\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...PLT_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "matplotlib.pyplot (snippet)", s.doc)),
            ...PLT_COMPLETIONS.map((n) => mkMethodSuggestion(n, "matplotlib.pyplot")),
          ],
        };
      }

      if (hasAx && /\bax\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...AX_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Axes (snippet)", s.doc)),
            ...AX_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Axes")),
          ],
        };
      }

      const m = prefix.match(/\b([A-Za-z_]\w*)\.\w*$/);
      const varName = m ? m[1] : null;
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
          ...BUILTIN_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Python builtin")),
        ],
      };
    },
  });

  // ---------------- Help rendering ----------------
  function escapeHtml(s) {
    return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
  }
  function mdToHtml(md) {
    const src = String(md || "");
    const parts = src.split(/```/);
    let out = "";
    for (let i = 0; i < parts.length; i++) {
      const chunk = parts[i];
      if (i % 2 === 1) {
        const cleaned = chunk.replace(/^\s*\w+\s*\n/, "");
        out += `<pre><code>${escapeHtml(cleaned.trim())}</code></pre>`;
      } else {
        let html = escapeHtml(chunk);
        html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
        html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
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

  // ---------------- Local help fetch ----------------
  const helpCache = new Map();
  let lastKey = null;

  function buildHelpKey(type, method) {
    if (!type || !method) return null;
    if (type === "str") return `str.${method}`;
    if (type === "list") return `list.${method}`;
    if (type === "dict") return `dict.${method}`;
    return null;
  }

  async function fetchHelp(key) {
    if (!key) return null;
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

  function getVarMethodNearCursor(model, position) {
    const line = model.getLineContent(position.lineNumber);
    const col0 = Math.max(0, position.column - 1);
    const start = Math.max(0, col0 - 80);
    const end = Math.min(line.length, col0 + 80);
    const windowText = line.slice(start, end);

    const re = /\b([A-Za-z_]\w*)\.(\w+)\b/g;
    let best = null;
    let m;
    while ((m = re.exec(windowText)) !== null) {
      const absStart = start + m.index;
      const absEnd = absStart + m[0].length;
      const dist = Math.min(Math.abs(col0 - absStart), Math.abs(col0 - absEnd));
      if (!best || dist < best.dist) best = { varName: m[1], method: m[2], dist };
    }
    return best ? { varName: best.varName, method: best.method } : null;
  }

  function debounce(fn, ms) {
    let t = null;
    return (...args) => { if (t) clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  const updateHelp = debounce(async () => {
    const model = editor?.getModel?.();
    const pos = editor?.getPosition?.();
    if (!model || !pos) return;

    const hit = getVarMethodNearCursor(model, pos);
    if (!hit) return;

    const upToLine = getTextUpToLine(model, pos.lineNumber);
    const t = inferVarType(hit.varName, upToLine);
    const key = buildHelpKey(t, hit.method);
    if (!key || key === lastKey) return;

    const remote = await fetchHelp(key);
    if (remote?.ok && remote.md) {
      lastKey = key;
      setHelpPanel(remote.md, { isMd: true });
      return;
    }

    let doc = null;
    if (t === "str") doc = STRING_HOVER_DOCS?.[hit.method];
    else if (t === "list") doc = LIST_HOVER_DOCS?.[hit.method];
    else if (t === "dict") doc = DICT_HOVER_DOCS?.[hit.method];

    if (doc) {
      lastKey = key;
      setHelpPanel(doc, { isMd: true });
      return;
    }

    lastKey = key;
    setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
  }, 120);

  const cursorDisposable = editor.onDidChangeCursorPosition(() => updateHelp());
  const changeDisposable = editor.onDidChangeModelContent(() => updateHelp());

  // Suggest-widget navigation (ArrowUp/Down)
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

    const nameEl = focusedRow.querySelector(".label-name") || focusedRow.querySelector(".monaco-highlighted-label") || focusedRow;
    const txt = (nameEl.textContent || "").trim();
    const m = txt.match(/[A-Za-z_]\w*/);
    return m ? m[0] : null;
  }

  function getVarNameBeforeDot(model, position) {
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

    const method = getFocusedSuggestLabel();
    if (!method) return;

    const varName = getVarNameBeforeDot(model, pos);
    if (!varName) return;

    const upToLine = getTextUpToLine(model, pos.lineNumber);
    const t = inferVarType(varName, upToLine);
    const key = buildHelpKey(t, method);
    if (!key || key === lastKey) return;

    const remote = await fetchHelp(key);
    if (remote?.ok && remote.md) {
      lastKey = key;
      setHelpPanel(remote.md, { isMd:true });
      return;
    }

    let doc = null;
    if (t === "str") doc = STRING_HOVER_DOCS?.[method];
    else if (t === "list") doc = LIST_HOVER_DOCS?.[method];
    else if (t === "dict") doc = DICT_HOVER_DOCS?.[method];

    if (doc) {
      lastKey = key;
      setHelpPanel(doc, { isMd:true });
      return;
    }

    lastKey = key;
    setHelpPanel(`<div class="help-muted">Keine lokale Hilfe für <code>${escapeHtml(key)}</code>.</div>`, { isMd:false });
  }

  const keyDisposable = editor.onKeyDown((e) => {
    const k = e.browserEvent?.key || "";
    if (!["ArrowUp","ArrowDown","PageUp","PageDown"].includes(k)) return;
    setTimeout(() => { updateHelpFromSuggestSelection(); }, 0);
  });

  // Disable overloaded hover
  const hoverDisposable = monaco.languages.registerHoverProvider("python", {
    provideHover() { return null; }
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
