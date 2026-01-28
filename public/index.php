<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python IDE</title>
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="./monaco/min/vs/editor/editor.main.css">
    <style>
        body { margin: 0; height: 100vh; display: flex; flex-direction: column; }
        #editor { flex: 1; }
        #output { height: 150px; border-top: 1px solid #ccc; padding: 5px; overflow-y: auto; background: #f5f5f5; font-family: monospace; }
        #runButton { padding: 5px 10px; margin: 5px; }
    </style>
</head>
<body>
    <div id="editor"></div>
    <button id="runButton">Run</button>
    <div id="output"></div>

    <!-- IDE-Skript -->
    <script type="module" src="./js/ide.js"></script>
</body>
</html>
