// public/js/editor-completions.config.js
// Pure configuration (arrays + snippet definitions + hover docs). No Monaco logic.

/* ============================================================
   METHOD COMPLETIONS (no snippets)
   ============================================================ */

export const NUMPY_COMPLETIONS = [
  "array",
  "arange",
  "zeros",
  "ones",
  "empty",
  "eye",
  "linspace",
  "logspace",
  "reshape",
  "transpose",
  "concatenate",
  "sin",
  "cos",
  "exp",
  "log",
  "sqrt",
  "abs",
  "sum",
  "mean",
  "min",
  "max",
  "std",
  "var",
  "where",
  "clip",
  "unique",
  "argsort",
  "random",
];

export const PLT_COMPLETIONS = [
  "figure",
  "subplots",
  "plot",
  "scatter",
  "bar",
  "hist",
  "imshow",
  "title",
  "xlabel",
  "ylabel",
  "legend",
  "grid",
  "xlim",
  "ylim",
  "tight_layout",
  "savefig",
  "show",
  "close",
  "clf",
  "cla",
  "colorbar",
];

export const AX_COMPLETIONS = [
  "plot",
  "scatter",
  "bar",
  "hist",
  "imshow",
  "set_title",
  "set_xlabel",
  "set_ylabel",
  "legend",
  "grid",
  "set_xlim",
  "set_ylim",
  "axhline",
  "axvline",
];

export const BUILTIN_COMPLETIONS = [
  "print",
  "len",
  "range",
  "enumerate",
  "zip",
  "list",
  "dict",
  "set",
  "tuple",
  "int",
  "float",
  "str",
  "bool",
  "sum",
  "min",
  "max",
  "abs",
  "round",
  "sorted",
  "map",
  "filter",
  "any",
  "all",
];

/* ============================================================
   SNIPPETS
   - doc is Markdown rendered by Monaco in the completion tooltip
   ============================================================ */

export const NP_SNIPPETS = [
  {
    label: "linspace(start, stop, num)",
    insert: "linspace(${1:start}, ${2:stop}, ${3:num})",
    doc: `
**np.linspace(start, stop, num)**

Return evenly spaced numbers over a specified interval.

**Parameters**
- \`start\` – start value of the sequence
- \`stop\` – end value of the sequence
- \`num\` – number of samples to generate

**Returns**
- \`ndarray\` of evenly spaced values

**Example**
\`\`\`python
x = np.linspace(0, 10, 100)
\`\`\`
`,
  },
  {
    label: "arange(start, stop, step)",
    insert: "arange(${1:start}, ${2:stop}, ${3:step})",
    doc: `
**np.arange(start, stop, step)**

Return evenly spaced values within a given interval.

**Example**
\`\`\`python
x = np.arange(0, 10, 0.1)
\`\`\`
`,
  },
  {
    label: "zeros(shape)",
    insert: "zeros(${1:shape})",
    doc: `
**np.zeros(shape)**

Return a new array of given shape, filled with zeros.

**Example**
\`\`\`python
z = np.zeros((3, 3))
\`\`\`
`,
  },
  {
    label: "ones(shape)",
    insert: "ones(${1:shape})",
    doc: `
**np.ones(shape)**

Return a new array of given shape, filled with ones.

**Example**
\`\`\`python
o = np.ones(10)
\`\`\`
`,
  },
  {
    label: "array(obj)",
    insert: "array(${1:obj})",
    doc: `
**np.array(obj)**

Create an array from an object (list, tuple, nested lists, ...).

**Example**
\`\`\`python
a = np.array([1, 2, 3])
\`\`\`
`,
  },
  {
    label: "where(condition, x, y)",
    insert: "where(${1:condition}, ${2:x}, ${3:y})",
    doc: `
**np.where(condition, x, y)**

Return elements chosen from \`x\` or \`y\` depending on \`condition\`.

**Example**
\`\`\`python
y = np.where(x > 0, 1, -1)
\`\`\`
`,
  },
];

export const PLT_SNIPPETS = [
  {
    label: "plot(x, y)",
    insert: "plot(${1:x}, ${2:y})",
    doc: `
**plt.plot(x, y)**

Plot *y* versus *x* as lines and/or markers.

**Parameters**
- \`x\` – x coordinates
- \`y\` – y coordinates

**Example**
\`\`\`python
plt.plot(x, y, label="signal")
plt.legend()
\`\`\`
`,
  },
  {
    label: "scatter(x, y)",
    insert: "scatter(${1:x}, ${2:y})",
    doc: `
**plt.scatter(x, y)**

Make a scatter plot of \`x\` vs \`y\`.

**Example**
\`\`\`python
plt.scatter(x, y, label="points")
plt.legend()
\`\`\`
`,
  },
  {
    label: "subplots(nrows, ncols)",
    insert: "subplots(${1:nrows}, ${2:ncols})",
    doc: `
**plt.subplots(nrows=1, ncols=1)**

Create a figure and a set of subplots.

**Returns**
- \`fig\` – matplotlib Figure
- \`ax\` or \`axes\` – Axes object(s)

**Typical usage**
\`\`\`python
fig, ax = plt.subplots()
ax.plot(x, y)
\`\`\`
`,
  },
  {
    label: "figure()",
    insert: "figure()",
    doc: `
**plt.figure()**

Create a new figure.

**Example**
\`\`\`python
plt.figure()
plt.plot(x, y)
\`\`\`
`,
  },
  {
    label: "title(text)",
    insert: 'title("${1:title}")',
    doc: `
**plt.title(text)**

Set a title for the current axes.

**Example**
\`\`\`python
plt.title("My plot")
\`\`\`
`,
  },
  {
    label: "xlabel(text)",
    insert: 'xlabel("${1:xlabel}")',
    doc: `
**plt.xlabel(text)**

Set the label for the x-axis.

**Example**
\`\`\`python
plt.xlabel("time (s)")
\`\`\`
`,
  },
  {
    label: "ylabel(text)",
    insert: 'ylabel("${1:ylabel}")',
    doc: `
**plt.ylabel(text)**

Set the label for the y-axis.

**Example**
\`\`\`python
plt.ylabel("amplitude")
\`\`\`
`,
  },
  {
    label: "savefig(filename)",
    insert: 'savefig("${1:figure}.png")',
    doc: `
**plt.savefig(filename)**

Save the current figure.

**Example**
\`\`\`python
plt.savefig("plot.png", dpi=150, bbox_inches="tight")
\`\`\`
`,
  },
  {
    label: "show()",
    insert: "show()",
    doc: `
**plt.show()**

Display all open figures (in your IDE this triggers PNG export).

**Example**
\`\`\`python
plt.show()
\`\`\`
`,
  },
];

export const AX_SNIPPETS = [
  {
    label: "ax.plot(x, y)",
    insert: "ax.plot(${1:x}, ${2:y})",
    doc: `
**ax.plot(x, y)**

Plot data on a specific Axes object.

**Example**
\`\`\`python
fig, ax = plt.subplots()
ax.plot(x, y)
\`\`\`
`,
  },
  {
    label: "ax.plot(..., label=...) + ax.legend()",
    insert:
      "ax.plot(${1:x}, ${2:y}, label='${3:label}')\n" +
      "ax.legend()\n" +
      "ax.grid(True)\n" +
      "fig.tight_layout()",
    doc: `
**ax.plot(..., label=...) + legend**

Convenience block for the most common plot pattern.

Includes:
- labeled plot
- legend
- grid
- tight layout
`,
  },
  {
    label: "ax.scatter(..., label=...) + ax.legend()",
    insert:
      "ax.scatter(${1:x}, ${2:y}, label='${3:label}')\n" +
      "ax.legend()\n" +
      "ax.grid(True)\n" +
      "fig.tight_layout()",
    doc: `
**ax.scatter(..., label=...) + legend**

Convenience block for scatter plots.

Includes:
- labeled scatter
- legend
- grid
- tight layout
`,
  },
  {
    label: "ax.set_title(text)",
    insert: 'ax.set_title("${1:title}")',
    doc: `
**ax.set_title(text)**

Set the title for this axes.
`,
  },
  {
    label: "ax.set_xlabel(text)",
    insert: 'ax.set_xlabel("${1:xlabel}")',
    doc: `
**ax.set_xlabel(text)**

Set x-axis label on this axes.
`,
  },
  {
    label: "ax.set_ylabel(text)",
    insert: 'ax.set_ylabel("${1:ylabel}")',
    doc: `
**ax.set_ylabel(text)**

Set y-axis label on this axes.
`,
  },
  {
    label: "ax.grid(True)",
    insert: "ax.grid(True)",
    doc: `
**ax.grid(True)**

Enable grid for this axes.
`,
  },
  {
    label: "ax.legend()",
    insert: "ax.legend()",
    doc: `
**ax.legend()**

Show legend for labeled artists.
`,
  },
  {
    label: "fig.tight_layout()",
    insert: "fig.tight_layout()",
    doc: `
**fig.tight_layout()**

Automatically adjust subplot parameters.
`,
  },
  {
    label: "fig.savefig(filename)",
    insert: 'fig.savefig("${1:figure}.png", dpi=${2:150}, bbox_inches="tight")',
    doc: `
**fig.savefig(filename)**

Save the figure to disk.

**Example**
\`\`\`python
fig.savefig("plot.png", dpi=150, bbox_inches="tight")
\`\`\`
`,
  },
  {
    label: "ax.set_xlim(min, max)",
    insert: "ax.set_xlim(${1:xmin}, ${2:xmax})",
    doc: `
**ax.set_xlim(xmin, xmax)**

Set x-axis limits.
`,
  },
  {
    label: "ax.set_ylim(min, max)",
    insert: "ax.set_ylim(${1:ymin}, ${2:ymax})",
    doc: `
**ax.set_ylim(ymin, ymax)**

Set y-axis limits.
`,
  },
  {
    label: "ax.axhline(y=...)",
    insert: "ax.axhline(y=${1:y}, linestyle='${2:--}', linewidth=${3:1})",
    doc: `
**ax.axhline(y=...)**

Add a horizontal line across the axes.
`,
  },
  {
    label: "ax.axvline(x=...)",
    insert: "ax.axvline(x=${1:x}, linestyle='${2:--}', linewidth=${3:1})",
    doc: `
**ax.axvline(x=...)**

Add a vertical line across the axes.
`,
  },
];

export const BUILTIN_SNIPPETS = [
  {
    label: "print(x)",
    insert: "print(${1:x})",
    doc: `
**print(x)**

Print objects to the output.

**Example**
\`\`\`python
print("hello")
\`\`\`
`,
  },
  {
    label: "len(obj)",
    insert: "len(${1:obj})",
    doc: `
**len(obj)**

Return the number of items in a container.

**Example**
\`\`\`python
n = len([1, 2, 3])
\`\`\`
`,
  },
  {
    label: "range(stop)",
    insert: "range(${1:stop})",
    doc: `
**range(stop)**

Create an iterator of integers from 0 up to \`stop\`.
`,
  },
  {
    label: "for i in range(n)",
    insert: "for ${1:i} in range(${2:n}):\n\t${3:pass}",
    doc: `
**for i in range(n):**

Classic for-loop template.

Use Tab to jump through placeholders.
`,
  },
  {
    label: "if condition:",
    insert: "if ${1:condition}:\n\t${2:pass}",
    doc: `
**if condition:**

If statement template.
`,
  },
  {
    label: "def func():",
    insert: "def ${1:func}(${2:args}):\n\t${3:pass}",
    doc: `
**def func(...):**

Function template.
`,
  },
];

/* ============================================================
   HOVER DOCS (Markdown)
   Used by hover provider and also as fallback docs for method completions.
   ============================================================ */

export const STRING_HOVER_DOCS = {
  upper: `
**str.upper()**

Return a copy of the string converted to uppercase.

**Example**
\`\`\`python
"hello".upper()  # "HELLO"
\`\`\`
`,
  lower: `
**str.lower()**

Return a copy of the string converted to lowercase.

**Example**
\`\`\`python
"Hello".lower()  # "hello"
\`\`\`
`,
  strip: `
**str.strip()**

Return a copy of the string with leading and trailing whitespace removed.

**Example**
\`\`\`python
"  hi  ".strip()  # "hi"
\`\`\`
`,
  split: `
**str.split(sep=None)**

Split the string into a list of substrings.

**Example**
\`\`\`python
"a,b,c".split(",")  # ["a","b","c"]
\`\`\`
`,
  replace: `
**str.replace(old, new)**

Return a copy of the string with all occurrences of \`old\` replaced by \`new\`.

**Example**
\`\`\`python
"aa".replace("a","b")  # "bb"
\`\`\`
`,
  join: `
**str.join(iterable)**

Join an iterable of strings into one string, using this string as separator.

**Example**
\`\`\`python
",".join(["a","b","c"])  # "a,b,c"
\`\`\`
`,
  startswith: `
**str.startswith(prefix)**

Return True if the string starts with the specified prefix.
`,
  endswith: `
**str.endswith(suffix)**

Return True if the string ends with the specified suffix.
`,
  find: `
**str.find(sub)**

Return the lowest index of \`sub\` or -1 if not found.
`,
  format: `
**str.format(...)**

Format the string using replacement fields.

**Example**
\`\`\`python
"{} + {}".format(1,2)  # "1 + 2"
\`\`\`
`,
};

export const LIST_HOVER_DOCS = {
  append: `
**list.append(x)**

Add an item to the end of the list.

**Example**
\`\`\`python
a = []
a.append(1)
\`\`\`
`,
  extend: `
**list.extend(iterable)**

Extend the list by appending all items from the iterable.

**Example**
\`\`\`python
a = [1]
a.extend([2,3])  # [1,2,3]
\`\`\`
`,
  insert: `
**list.insert(i, x)**

Insert an item at a given position.
`,
  pop: `
**list.pop([i])**

Remove and return item at position \`i\` (default last).
`,
  remove: `
**list.remove(x)**

Remove first occurrence of \`x\`.
`,
  sort: `
**list.sort(...)**

Sort the list in place.
`,
  reverse: `
**list.reverse()**

Reverse the list in place.
`,
  clear: `
**list.clear()**

Remove all items from the list.
`,
};

export const DICT_HOVER_DOCS = {
  get: `
**dict.get(key, default=None)**

Return the value for \`key\` if key is in the dictionary, else \`default\`.

**Example**
\`\`\`python
d = {"a": 1}
d.get("a")      # 1
d.get("b", 0)   # 0
\`\`\`
`,
  keys: `
**dict.keys()**

Return a view of the dictionary’s keys.
`,
  values: `
**dict.values()**

Return a view of the dictionary’s values.
`,
  items: `
**dict.items()**

Return a view of the dictionary’s items.
`,
  update: `
**dict.update(other)**

Update the dictionary with key/value pairs from \`other\`.
`,
  pop: `
**dict.pop(key[, default])**

Remove specified key and return the corresponding value.
`,
  setdefault: `
**dict.setdefault(key, default=None)**

Get value for \`key\`; if missing, insert \`default\` and return it.
`,
  clear: `
**dict.clear()**

Remove all items from the dictionary.
`,
};
