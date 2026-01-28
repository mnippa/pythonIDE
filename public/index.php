<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python IDE mit Pyodide</title>

    <!-- Monaco Loader -->
    <script src="monaco/min/vs/loader.js"></script>
    <script>
        require.config({ paths: { 'vs': 'monaco/min/vs' } });
    </script>

    <style>
        html, body { margin:0; height:100%; font-family:sans-serif; display:flex; flex-direction:column; }
        #buttons { padding:5px; height:40px; }
        #buttons button { margin-right:10px; }
        #container { display:flex; flex:2; height:60%; overflow:hidden; }
        #editor-container { flex:2; height:100%; }
        #output-container { flex:1; height:100%; padding:10px; overflow:auto; font-family: monospace; white-space: pre; background:#f5f5f5; border-left:1px solid #ccc;}
        #lint-container { flex:1; height:20%; padding:10px; overflow:auto; font-family: monospace; white-space: pre; background:#f0f0f0; border-top:1px solid #ccc;}
    </style>
</head>
<body>

<div id="buttons">
    <button id="run-btn">Run</button>
    <button id="format-btn">Format Code</button>
    <button id="toggle-live">Live Output: OFF</button>
</div>

<div id="container">
    <div id="editor-container"></div>
    <div id="output-container"></div>
</div>
<div id="lint-container"></div>

<!-- Pyodide lokal -->
<script src="pyodide/pyodide.js"></script>
<!-- Editor Setup -->
<script type="module" src="js/editor-setup.js"></script>

</body>
</html>
