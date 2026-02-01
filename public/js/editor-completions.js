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
 * - global -> Python builtins + common snippets
 *
 * @param {any} monaco
 * @param {any} editor
 * @returns {any} disposable from monaco.languages.registerCompletionItemProvider
 */
export function registerPythonCompletions(monaco, editor) {
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

  // Register once; keep disposable in case you want to disable later
  const disposable = monaco.languages.registerCompletionItemProvider("python", {
    triggerCharacters: [".", "_", "("],

    provideCompletionItems(model, position) {
      const line = model.getLineContent(position.lineNumber);
      const prefix = line.slice(0, position.column - 1);

      const fullText = model.getValue();

      // import-aware
      const hasNp = /\bimport\s+numpy\s+as\s+np\b/.test(fullText);
      const hasPlt = /\bimport\s+matplotlib\.pyplot\s+as\s+plt\b/.test(fullText);

      // IMPORTANT: ax only if actually defined as variable (avoid stealing other completions)
      const hasAx =
        /\bfig\s*,\s*ax\s*=\s*plt\.subplots\s*\(/.test(fullText) ||
        /\bax\s*=\s*plt\.subplots\s*\(/.test(fullText);

      // np. context
      if (hasNp && /\bnp\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...NP_SNIPPETS.map((s) =>
              mkSnippetSuggestion(s.label, s.insert, "NumPy (snippet)", s.doc)
            ),
            ...NUMPY_COMPLETIONS.map((n) => mkMethodSuggestion(n, "NumPy")),
          ],
        };
      }

      // plt. context
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

      // ax. context (only when ax. typed)
      if (hasAx && /\bax\.\w*$/.test(prefix)) {
        return {
          suggestions: [
            ...AX_SNIPPETS.map((s) =>
              mkSnippetSuggestion(s.label, s.insert, "Axes (snippet)", s.doc)
            ),
            ...AX_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Axes")),
          ],
        };
      }

      // global builtins
      return {
        suggestions: [
          ...BUILTIN_SNIPPETS.map((s) =>
            mkSnippetSuggestion(s.label, s.insert, "Python (snippet)", s.doc)
          ),
          ...BUILTIN_COMPLETIONS.map((n) => mkMethodSuggestion(n, "Python builtin")),
        ],
      };
    },
  });

  return disposable;
}
