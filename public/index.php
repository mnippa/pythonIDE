<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Python IDE</title>

<!-- Monaco Loader (AMD) -->
<script src="monaco/min/vs/loader.js"></script>

<style>
  body { margin: 0; display: flex; flex-direction: column; height: 100vh; font-family: sans-serif; }
  #editor { flex: 1; }
  #runBtn { padding: 8px 16px; margin: 4px; }
  #output { height: 150px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 8px; font-family: monospace; }
</style>
</head>
<body>

<div id="editor"></div>
<button id="runBtn">Run</button>
<pre id="output"></pre>

<script>
  // --- Monaco Editor Setup ---
  require.config({ paths: { 'vs': 'monaco/min/vs' }});
  require(['vs/editor/editor.main'], function() {
    window.editor = monaco.editor.create(document.getElementById('editor'), {
      value: 'print("Hello Pyodide")',
      language: 'python',
      theme: 'vs-dark',
      automaticLayout: true
    });
  });
</script>

<!-- Pyodide Setup -->
<script type="module">
  import { loadPyodide } from './pyodide/pyodide.mjs'; // pyodide.mjs im public/pyodide Verzeichnis

  let pyodide = null;

  async function initPyodideEnv() {
    pyodide = await loadPyodide({
      indexURL: './pyodide/'  // zeigt auf public/pyodide/
    });
    console.log("Pyodide ready!");
  }

  initPyodideEnv();

  // --- Run Button ---
  const runBtn = document.getElementById('runBtn');
  const output = document.getElementById('output');

  runBtn.addEventListener('click', async () => {
    if (!pyodide) {
      output.textContent = "Pyodide is still loading...";
      return;
    }

    const code = window.editor.getValue();
    try {
      const result = await pyodide.runPythonAsync(code);
      output.textContent = result ?? '';
    } catch(err) {
      output.textContent = err;
    }
  });
</script>

</body>
</html>
