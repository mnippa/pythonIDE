// public/js/editor-completions.config.js
// Pure data exports (arrays/objects), no Monaco dependency.

export const NUMPY_COMPLETIONS = [
  "array","arange","linspace","zeros","ones","empty","reshape","random","mean","median","std","sum","min","max","argmin","argmax",
];

export const PLT_COMPLETIONS = [
  "plot","scatter","hist","bar","barh","imshow","title","xlabel","ylabel","legend","grid","xlim","ylim","figure","subplots","show","savefig",
];

export const AX_COMPLETIONS = [
  "plot","scatter","hist","bar","barh","imshow","set_title","set_xlabel","set_ylabel","legend","grid","set_xlim","set_ylim",
];

export const BUILTIN_COMPLETIONS = [
  "print","len","range","enumerate","sum","min","max","sorted","reversed","list","dict","set","tuple","str","int","float","input",
];

export const NP_SNIPPETS = [
  { label: "np.arange()", insert: "np.arange(${1:0}, ${2:10}, ${3:1})", doc: "**np.arange**\\n\\n```python\\nnp.arange(0, 10, 1)\\n```" },
  { label: "np.linspace()", insert: "np.linspace(${1:0}, ${2:1}, ${3:100})", doc: "**np.linspace**\\n\\n```python\\nnp.linspace(0, 1, 100)\\n```" },
];

export const PLT_SNIPPETS = [
  { label: "plt.plot()", insert: "plt.plot(${1:x}, ${2:y})", doc: "**plt.plot**\\n\\n```python\\nplt.plot(x, y)\\n```" },
  { label: "plt.legend()", insert: "plt.legend()", doc: "**plt.legend**\\n\\n```python\\nplt.legend()\\n```" },
];

export const AX_SNIPPETS = [
  { label: "ax.plot()", insert: "ax.plot(${1:x}, ${2:y})", doc: "**ax.plot**\\n\\n```python\\nax.plot(x, y)\\n```" },
];

export const BUILTIN_SNIPPETS = [
  { label: "for i in range", insert: "for ${1:i} in range(${2:n}):\n\t${3:pass}", doc: "**for-loop**\\n\\n```python\\nfor i in range(n):\n    pass\\n```" },
  { label: "if", insert: "if ${1:condition}:\n\t${2:pass}", doc: "**if**\\n\\n```python\\nif condition:\n    pass\\n```" },
  { label: "def", insert: "def ${1:name}(${2:args}):\n\t${3:pass}", doc: "**def**\\n\\n```python\\ndef f(x):\n    pass\\n```" },
];

// Optional handwritten fallback docs (used when help.json lacks an entry)
export const STRING_HOVER_DOCS = {
  split: "**str.split(sep)**\\n\\nTeilt einen String in Teile.\\n\\n```python\\n'a,b'.split(',')\\n```",
};

export const LIST_HOVER_DOCS = {
  append: "**list.append(x)**\\n\\nFügt ein Element ans Ende an.\\n\\n```python\\na=[]\na.append(1)\\n```",
};

export const DICT_HOVER_DOCS = {
  get: "**dict.get(key, default=None)**\\n\\nLiest einen Wert; gibt default zurück, wenn key fehlt.\\n\\n```python\\nd={'a':1}\nd.get('b',0)\\n```",
};
