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
} from "./editor-completions.config.js";

/**
 * Register static (import-aware) completion & snippet suggestions for Python.
 *
 * - np.  -> NumPy
 * - plt. -> matplotlib.pyplot
 * - ax.  -> matplotlib Axes (only if ax was actually defined via plt.subplots)
 * - <var>. -> heuristic type inference (str + list + dict)
 * - global -> Python builtins + common snippets
 */
export function registerPythonCompletions(monaco, editor) {
  /* ============================================================
     Built-in object methods (static lists)
     ============================================================ */
  const STRING_METHODS = [
    "lower",
    "upper",
    "title",
    "capitalize",
    "swapcase",
    "casefold",
    "strip",
    "lstrip",
    "rstrip",
    "removeprefix",
    "removesuffix",
    "ljust",
    "rjust",
    "center",
    "zfill",
    "find",
    "rfind",
    "index",
    "rindex",
    "count",
    "startswith",
    "endswith",
    "isalpha",
    "isalnum",
    "isdigit",
    "isdecimal",
    "isnumeric",
    "isspace",
    "islower",
    "isupper",
    "istitle",
    "split",
    "rsplit",
    "splitlines",
    "join",
    "partition",
    "rpartition",
    "replace",
    "translate",
    "maketrans",
    "format",
    "format_map",
    "encode",
  ];

  const LIST_METHODS = [
    "append",
    "extend",
    "insert",
    "remove",
    "pop",
    "clear",
    "index",
    "count",
    "sort",
    "reverse",
    "copy",
  ];

  const DICT_METHODS = [
    "get",
    "setdefault",
    "update",
    "pop",
    "popitem",
    "clear",
    "keys",
    "values",
    "items",
    "copy",
    "fromkeys",
  ];

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

  /**
   * Heuristic inference: determine "str" | "list" | "dict" (or null)
   * based on the most recent assignment to varName in textUpToCursor.
   *
   * We intentionally keep this simple, fast, and predictable.
   */
  function inferVarType(varName, textUpToCursor) {
    const reAssign = new RegExp(`\\b${escapeRegExp(varName)}\\s*=`);
    const lines = String(textUpToCursor).split("\n");

    for (let i = lines.length - 1; i >= 0; i--) {
      const line = lines[i];
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;

      if (!reAssign.test(line)) continue;

      // only look at RHS
      const rhs = line.split("=", 2)[1] ?? "";
      const rhsTrim = rhs.trim();

      // ---- str rules ----
      // direct literal: "..." / '...' / f"..." / r"..." etc (best effort)
      if (/^(?:[rubf]|rb|br|rf|fr|urf|fur|r|u|b|f){0,3}(['"])/i.test(rhsTrim)) {
        return "str";
      }
      // triple quotes one-liner
      if (/^(?:[rubf]|rb|br|rf|fr){0,3}("""|''')/i.test(rhsTrim)) {
        return "str";
      }
      // constructors / sources
      if (/^str\s*\(/.test(rhsTrim)) return "str";
      if (/^input\s*\(/.test(rhsTrim)) return "str";

      // ---- list rules ----
      // list literal (including [1,2,3] one-liner)
      if (/^\[\s*.*\s*\]$/.test(rhsTrim)) return "list";
      // empty list literal
      if (/^\[\s*\]$/.test(rhsTrim)) return "list";
      // constructor
      if (/^list\s*\(/.test(rhsTrim)) return "list";

      // ---- dict rules ----
      // dict literal (including {"a":1} one-liner)
      if (/^\{\s*.*\s*\}$/.test(rhsTrim)) return "dict";
      // empty dict literal
      if (/^\{\s*\}$/.test(rhsTrim)) return "dict";
      // constructor
      if (/^dict\s*\(/.test(rhsTrim)) return "dict";

      // last assignment exists but unknown type -> stop
      return null;
    }

    return null;
  }

  /**
   * Detect "<var>." completion context at cursor.
   * Returns varName or null.
   */
  function getVarDotContext(prefix) {
    const m = prefix.match(/\b([A-Za-z_]\w*)\.\w*$/);
    return m ? m[1] : null;
  }

  const disposable = monaco.languages.registerCompletionItemProvider("python", {
    triggerCharacters: [".", "_", "("],

    provideCompletionItems(model, position) {
      const line = model.getLineContent(position.lineNumber);
      const prefix = line.slice(0, position.column - 1);
      const fullText = model.getValue();

      // import-aware
      const hasNp = /\bimport\s+numpy\s+as\s+np\b/.test(fullText);
      const hasPlt = /\bimport\s+matplotlib\.pyplot\s+as\s+plt\b/.test(fullText);

      // IMPORTANT: ax only if actually defined as variable
      const hasAx =
        /\bfig\s*,\s*ax\s*=\s*plt\.subplots\s*\(/.test(fullText) ||
        /\bax\s*=\s*plt\.subplots\s*\(/.test(fullText);

      // 1) np.
      if (hasNp && /\bnp\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...NP_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "NumPy (snippet)", s.doc)),
            ...NUMPY_COMPLETIONS.map((n) => mkMethodSuggestion(n, "NumPy")),
          ],
        };
      }

      // 2) plt.
      if (hasPlt && /\bplt\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...PLT_SNIPPETS.map((s) =>
              mkSnippetSuggestion(s.label, s.insert, "matplotlib.pyplot (snippet)", s.doc)
            ),
            ...PLT_COMPLETIONS.map((n) => mkMethodSuggestion(n, "matplotlib.pyplot")),
          ],
        };
      }

      // 3) ax.
      if (hasAx && /\bax\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...AX_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Axes (snippet)", s.doc)),
            ...AX_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Axes")),
          ],
        };
      }

      // 4) <var>. heuristic (str/list/dict)
      const varName = getVarDotContext(prefix);
      if (varName) {
        const upToCursor = getTextUpToCursor(model, position);
        const t = inferVarType(varName, upToCursor);

        if (t === "str") {
          return {
            suggestions: STRING_METHODS.map((m) => mkMethodSuggestion(m, "str", `**str.${m}**\n\nString method.`)),
          };
        }
        if (t === "list") {
          return {
            suggestions: LIST_METHODS.map((m) => mkMethodSuggestion(m, "list", `**list.${m}**\n\nList method.`)),
          };
        }
        if (t === "dict") {
          return {
            suggestions: DICT_METHODS.map((m) => mkMethodSuggestion(m, "dict", `**dict.${m}**\n\nDict method.`)),
          };
        }
      }

      // 5) global builtins
      return {
        suggestions: [
          ...BUILTIN_SNIPPETS.map((s) => mkSnippetSuggestion(s.label, s.insert, "Python (snippet)", s.doc)),
          ...BUILTIN_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Python builtin")),
        ],
      };
    },
  });

  return disposable;
}
