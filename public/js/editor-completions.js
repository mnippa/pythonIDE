// public/js/editor-completions.js (ES module)

import {
  NUMPY_COMPLETIONS,
  PLT_COMPLETIONS,
  AX_COMPLETIONS,
  BUILTIN_COMPLETIONS,
  NP_SNIPPETS,
  PLT_SNIPPETS,
  AX_SNIPPETS,
  BUILTIN_SNIPPETS,
  // optional fallback docs
  STRING_HOVER_DOCS,
  LIST_HOVER_DOCS,
  DICT_HOVER_DOCS,
} from "./editor-completions.config.js";

export function registerPythonCompletions(monaco, editor) {
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

  function mkMethodSuggestion(name, detail, documentation) {
    return {
      label: name,
      kind: monaco.languages.CompletionItemKind.Method,
      insertText: name,
      detail,
      documentation: documentation ? { value: documentation } : undefined,
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

  function escapeRegExp(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function getTextUpToCursor(model, position) {
    return model.getValueInRange({
      startLineNumber: 1,
      startColumn: 1,
      endLineNumber: position.lineNumber,
      endColumn: position.column,
    });
  }

  function inferVarType(varName, textUpToCursor) {
    const reAssign = new RegExp(`\\b${escapeRegExp(varName)}\\s*=`);
    const lines = String(textUpToCursor).split("\n");

    for (let i = lines.length - 1; i >= 0; i--) {
      const line = lines[i];
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;
      if (!reAssign.test(line)) continue;

      const rhsTrim = (line.split("=", 2)[1] ?? "").trim();

      // str
      if (/^(?:[rubf]|rb|br|rf|fr|urf|fur|r|u|b|f){0,3}(['"])/i.test(rhsTrim)) return "str";
      if (/^(?:[rubf]|rb|br|rf|fr){0,3}("""|''')/i.test(rhsTrim)) return "str";
      if (/^str\s*\(/.test(rhsTrim)) return "str";
      if (/^input\s*\(/.test(rhsTrim)) return "str";

      // list
      if (/^\[\s*.*\s*\]$/.test(rhsTrim)) return "list";
      if (/^list\s*\(/.test(rhsTrim)) return "list";

      // dict
      if (/^\{\s*.*\s*\}$/.test(rhsTrim)) return "dict";
      if (/^dict\s*\(/.test(rhsTrim)) return "dict";

      return null;
    }
    return null;
  }

  function getVarDotContext(prefix) {
    const m = prefix.match(/\b([A-Za-z_]\w*)\.\w*$/);
    return m ? m[1] : null;
  }

  // -----------------------------
  // Completion Provider
  // -----------------------------
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

      const varName = getVarDotContext(prefix);
      if (varName) {
        const upToCursor = getTextUpToCursor(model, position);
        const t = inferVarType(varName, upToCursor);

        if (t === "str") return { suggestions: STRING_METHODS.map((m) => mkMethodSuggestion(m, "str")) };
        if (t === "list") return { suggestions: LIST_METHODS.map((m) => mkMethodSuggestion(m, "list")) };
        if (t === "dict") return { suggestions: DICT_METHODS.map((m) => mkMethodSuggestion(m, "dict")) };
      }

      return {
        suggestions: [
          ...BUILTIN_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Python (snippet)", s.doc)),
          ...BUILTIN_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Python builtin")),
        ],
      };
    },
  });

  // -----------------------------
  // Help panel rendering (simple + safe)
  // -----------------------------
  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // very small markdown renderer: **bold**, `inline`, ```code fences```
  function mdToHtml(md) {
    const src = String(md || "");
    // code fences
    const parts = src.split(/```/);
    let out = "";
    for (let i = 0; i < parts.length; i++) {
      const chunk = parts[i];
      if (i % 2 === 1) {
        // fenced block: may start with "python\n..."
        const cleaned = chunk.replace(/^\s*\w+\s*\n/, "");
        out += `<pre><code>${escapeHtml(cleaned.trim())}</code></pre>`;
      } else {
        let html = escapeHtml(chunk);

        // inline code
        html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
        // bold
        html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        // newlines -> <br>
        html = html.replace(/\n/g, "<br>");
        out += html;
      }
    }
    return out;
  }

  function setHelpPanel(md, { autoTab = true } = {}) {
    const el = document.getElementById("help-container");
    if (!el) return;
    el.innerHTML = mdToHtml(md);

    if (autoTab && typeof window.__setHelpTab === "function") {
      window.__setHelpTab();
    }
  }

  // -----------------------------
  // Hover Provider: write help to right panel
  // -----------------------------
  const helpCache = new Map(); // key -> { ok, md }

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

    const url = `index.php?api=help&key=${encodeURIComponent(key)}`;
    try {
      const res = await fetch(url, { cache: "no-store" });
      if (!res.ok) {
        const miss = { ok: false, md: null };
        helpCache.set(key, miss);
        return miss;
      }
      const data = await res.json();
      const out = { ok: !!data?.ok, md: data?.md || null };
      helpCache.set(key, out);
      return out;
    } catch {
      const err = { ok: false, md: null };
      helpCache.set(key, err);
      return err;
    }
  }

  function getDotCallAtPosition(model, position) {
    const line = model.getLineContent(position.lineNumber);
    const re = /\b([A-Za-z_]\w*)\.(\w+)/g;
    let m;
    while ((m = re.exec(line)) !== null) {
      const varName = m[1];
      const method = m[2];
      const dotIndex = m.index + varName.length;
      const methodStartCol = dotIndex + 2;
      const methodEndCol = methodStartCol + method.length;
      const col = position.column;
      if (col >= methodStartCol && col <= methodEndCol) return { varName, method };
    }
    return null;
  }

  const hoverDisposable = monaco.languages.registerHoverProvider("python", {
    async provideHover(model, position) {
      const hit = getDotCallAtPosition(model, position);
      if (!hit) return null;

      const upToLine = model.getValueInRange({
        startLineNumber: 1,
        startColumn: 1,
        endLineNumber: position.lineNumber,
        endColumn: model.getLineMaxColumn(position.lineNumber),
      });

      const t = inferVarType(hit.varName, upToLine);
      const helpKey = buildHelpKey(t, hit.method);
      if (!helpKey) return null;

      // 1) scraped local help
      const remote = await fetchHelp(helpKey);
      if (remote?.ok && remote.md) {
        setHelpPanel(remote.md, { autoTab: true });
        // optional: still return hover tooltip; keep minimal
        return { contents: [{ value: `**Hilfe** → siehe Panel rechts (${helpKey})` }] };
      }

      // 2) fallback local docs from config (if present)
      let doc = null;
      if (t === "str") doc = STRING_HOVER_DOCS?.[hit.method];
      else if (t === "list") doc = LIST_HOVER_DOCS?.[hit.method];
      else if (t === "dict") doc = DICT_HOVER_DOCS?.[hit.method];

      if (doc) {
        setHelpPanel(doc, { autoTab: true });
        return { contents: [{ value: `**Hilfe** → siehe Panel rechts (${helpKey})` }] };
      }

      return null;
    },
  });

  return {
    dispose() {
      try { completionDisposable?.dispose?.(); } catch {}
      try { hoverDisposable?.dispose?.(); } catch {}
    },
  };
}
