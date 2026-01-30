<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python IDE (Pyodide)</title>

    <!-- Monaco -->
    <script src="monaco/min/vs/loader.js"></script>
    <script>
        require.config({ paths: { 'vs': 'monaco/min/vs' } });
    </script>

    <style>
        html, body {
            margin: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            font-family: sans-serif;
        }

        #toolbar {
            display: flex;
            gap: 15px;
            padding: 6px 10px;
            border-bottom: 1px solid #ccc;
            align-items: center;
            background: #f5f5f5;
        }

        #main {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        #editor-container {
            flex: 2;
        }
#container { display:flex; flex:2; height:50%; overflow:hidden; }
#editor-container { flex:2; height:100%; }
#output-container { flex:1; height:100%; padding:10px; overflow:auto; font-family: monospace; white-space: pre; background:#f5f5f5; border-left:1px solid #ccc;}
#plot-container { height:300px; padding:10px; overflow:auto; background:#fff; border-top:1px solid #ccc; }
#lint-container { height:150px; padding:10px; overflow:auto; background:#f0f0f0; border-top:1px solid #ccc; }

  
    </style>
</head>
<body>

<div id="toolbar">
    <button id="run-btn">Run</button>

    <label><input type="checkbox" id="lib-numpy" checked> NumPy</label>
    <label><input type="checkbox" id="lib-math"> math</label>
    <label><input type="checkbox" id="lib-matplotlib"> Matplotlib</label>
</div>

<div id="main">
   <div id="container">
    <div id="editor-container"></div>
    <div id="output-container"></div>
</div>

<div id="plot-container"></div> <!-- NEU: Plots hier -->
<div id="lint-container"></div>

<!-- Pyodide -->
<script src="pyodide/pyodide.js"></script>

<!-- Editor -->
<script type="module" src="js/editor-setup.js"></script>

</body>
</html>
