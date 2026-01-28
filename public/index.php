<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Python IDE</title>
  <style>
    #editor { width: 100%; height: 500px; border: 1px solid #333; }
  </style>
</head>
<body>
  <div id="editor"></div>

  <!-- Monaco Standalone -->
  <script src="monaco/min/vs/loader.js"></script>
  <script>
    require.config({ paths: { 'vs': 'monaco/min/vs' }});

    require(['vs/editor/editor.main'], function() {
      window.editor = monaco.editor.create(document.getElementById('editor'), {
        value: '# Schreibe hier Python-Code',
        language: 'python',
        theme: 'vs-dark',
        automaticLayout: true
      });
    });
  </script>

  <!-- Pyodide -->
  <script src="pyodide/pyodide.js"></script>
  <script>
    async function initPyodideEnv() {
      const pyodide = await loadPyodide({ indexURL: './pyodide/' });
      console.log('Pyodide ready');
      const result = await pyodide.runPythonAsync('import sys; sys.version');
      console.log('Python version:', result);
    }
    initPyodideEnv();
  </script>
</body>
</html>
