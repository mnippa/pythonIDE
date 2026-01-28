<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Monaco AMD IDE</title>
  <style>
    body, html { margin: 0; height: 100%; }
    #editor { width: 100%; height: 100%; }
  </style>
  <!-- Monaco CSS -->
  <link rel="stylesheet" href="./monaco/min/vs/editor/editor.main.css">
</head>
<body>
  <div id="editor"></div>

  <!-- AMD Loader -->
  <script src="./monaco/min/vs/loader.js"></script>
  <script>
    // Konfiguration für AMD
    require.config({ paths: { vs: './monaco/min/vs' }});

    require(['vs/editor/editor.main'], function() {
      // Editor erstellen
      monaco.editor.create(document.getElementById('editor'), {
        value: `print("Hello, Monaco!")`,
        language: 'python',       // Sprache
        theme: 'vs-dark',         // Theme
        automaticLayout: true     // Auto-Resize
      });
    });
  </script>
</body>
</html>
