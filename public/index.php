<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python IDE mit Pyodide</title>

    <!-- AMD-Monaco Loader -->
    <script src="monaco/min/vs/loader.js"></script>

    <script>
        require.config({ paths: { 'vs': 'monaco/min/vs' } });
    </script>

    <style>
        html, body { margin:0; height:100%; font-family:sans-serif; display:flex; flex-direction:column; }
        #container { display:flex; flex:1; height:100%; overflow:hidden; }
        #editor-container { flex:2; height:100%; }
        #output-container { flex:1; height:100%; background:#f5f5f5; padding:10px; overflow:auto; font-family: monospace; }
        #run-btn { position:absolute; bottom:10px; left:10px; padding:5px 10px; z-index:10; }
    </style>
</head>
<body>

<button id="run-btn">Run</button>
<div id="container">
    <div id="editor-container"></div>
    <div id="output-container"></div>
</div>
<button id="format-btn">Format Code</button>
<div id="editor" style="width:100%; height:600px;"></div>

<!-- IDE-Skripte -->
<script type="module" src="js/ide.js"></script>
<!-- IDE-Skripte -->

    <script type="module" src="js/editor-setup.js"></script>
</body>
</html>
