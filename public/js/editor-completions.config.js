// public/js/editor-completions.config.js

/* ============================================================
   METHOD COMPLETIONS (no snippets)
   ============================================================ */

export const NUMPY_COMPLETIONS = [
  "array","arange","zeros","ones","empty","eye",
  "linspace","logspace","reshape","transpose","concatenate",
  "sin","cos","exp","log","sqrt","abs",
  "sum","mean","min","max","std","var",
  "where","clip","unique","argsort","random"
];

export const PLT_COMPLETIONS = [
  "figure","subplots","plot","scatter","bar","hist","imshow",
  "title","xlabel","ylabel","legend","grid",
  "xlim","ylim","tight_layout","savefig",
  "show","close","clf","cla","colorbar"
];

export const AX_COMPLETIONS = [
  "plot","scatter","bar","hist","imshow",
  "set_title","set_xlabel","set_ylabel",
  "legend","grid","set_xlim","set_ylim",
  "axhline","axvline"
];

export const BUILTIN_COMPLETIONS = [
  "print","len","range","enumerate","zip",
  "list","dict","set","tuple",
  "int","float","str","bool",
  "sum","min","max","abs","round",
  "sorted","map","filter","any","all"
];

/* ============================================================
   SNIPPETS
   ============================================================ */

export const NP_SNIPPETS = [
  { label: "linspace(start, stop, num)", insert: "linspace(${1:start}, ${2:stop}, ${3:num})", doc: "Evenly spaced numbers." },
  { label: "arange(start, stop, step)", insert: "arange(${1:start}, ${2:stop}, ${3:step})", doc: "Evenly spaced values." },
  { label: "zeros(shape)", insert: "zeros(${1:shape})", doc: "Array of zeros." },
  { label: "ones(shape)", insert: "ones(${1:shape})", doc: "Array of ones." },
  { label: "array(obj)", insert: "array(${1:obj})", doc: "Create an array." },
  { label: "where(condition, x, y)", insert: "where(${1:condition}, ${2:x}, ${3:y})", doc: "Conditional selection." },
];

export const PLT_SNIPPETS = [
  { label: "plot(x, y)", insert: "plot(${1:x}, ${2:y})", doc: "Plot y versus x." },
  { label: "scatter(x, y)", insert: "scatter(${1:x}, ${2:y})", doc: "Scatter plot." },
  { label: "subplots()", insert: "subplots(${1:nrows}, ${2:ncols})", doc: "Create figure + axes." },
  { label: "figure()", insert: "figure()", doc: "Create a new figure." },
  { label: "title(text)", insert: 'title("${1:title}")', doc: "Set title." },
  { label: "xlabel(text)", insert: 'xlabel("${1:xlabel}")', doc: "Set x label." },
  { label: "ylabel(text)", insert: 'ylabel("${1:ylabel}")', doc: "Set y label." },
  { label: "savefig(filename)", insert: 'savefig("${1:figure}.png")', doc: "Save current figure." },
  { label: "show()", insert: "show()", doc: "Display figures." },
];

export const AX_SNIPPETS = [
  {
    label: "ax.plot + legend (block)",
    insert:
      "ax.plot(${1:x}, ${2:y}, label='${3:label}')\n" +
      "ax.legend()\n" +
      "ax.grid(True)\n" +
      "fig.tight_layout()",
    doc: "Plot with legend and layout helpers.",
  },
  {
    label: "ax.scatter + legend (block)",
    insert:
      "ax.scatter(${1:x}, ${2:y}, label='${3:label}')\n" +
      "ax.legend()\n" +
      "ax.grid(True)\n" +
      "fig.tight_layout()",
    doc: "Scatter with legend and layout helpers.",
  },
  { label: "ax.plot(x, y)", insert: "ax.plot(${1:x}, ${2:y})", doc: "Plot on axes." },
  { label: "ax.set_title(text)", insert: 'ax.set_title("${1:title}")', doc: "Axes title." },
  { label: "ax.set_xlabel(text)", insert: 'ax.set_xlabel("${1:xlabel}")', doc: "X label." },
  { label: "ax.set_ylabel(text)", insert: 'ax.set_ylabel("${1:ylabel}")', doc: "Y label." },
  { label: "ax.legend()", insert: "ax.legend()", doc: "Show legend." },
  { label: "ax.grid(True)", insert: "ax.grid(True)", doc: "Enable grid." },
  { label: "fig.savefig()", insert: 'fig.savefig("${1:file}.png", dpi=150, bbox_inches="tight")', doc: "Save figure." },
];

export const BUILTIN_SNIPPETS = [
  { label: "print(x)", insert: "print(${1:x})", doc: "Print objects." },
  { label: "for i in range(n)", insert: "for ${1:i} in range(${2:n}):\n\t${3:pass}", doc: "For-loop." },
  { label: "if condition:", insert: "if ${1:condition}:\n\t${2:pass}", doc: "If statement." },
  { label: "def func():", insert: "def ${1:func}(${2:args}):\n\t${3:pass}", doc: "Function definition." },
];
