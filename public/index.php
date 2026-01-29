<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python IDE mit Pyodide</title>

    <script src="monaco/min/vs/loader.js"></script>
    <script>
        require.config({ paths: { 'vs': 'monaco/min/vs' } });
    </script>

    <style>
        html, body {
            margin: 0;
            height: 100%;
            font-family: sans-serif;
            display: flex;
            flex-direction: column;
        }

        #topbar {
            display: flex;
            align-items: center;
            padding: 6px;
            gap: 15px;
            border-bottom: 1px solid #ccc;
            background: #f7f7f7;
        }

        #packages label {
            margin-right: 10px;
            white-space: nowrap;
        }

        #container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        #editor-container {
            flex: 2;
        }

        #output-container {
            flex: 1;
            padding: 10px;
            background: #f5f5f5;
            border-left: 1px solid #ccc;
            font-family: monospace;
            white-space: pre;
            overflow: auto;
        }

        #lint-container {
            height: 120px;
            padding: 10px;
            border-top: 1px solid #ccc;
            background: #f0f0f0;
            font-family: monospace;
            overflow: auto;
        }
    </style>
</head>
<body>

<div id="topbar">
    <button id="run-btn">Run</button>
    <button id="undo-btn">Undo</button>
    <button id="redo-btn">Redo</button>

    <div id="packages">
        <strong>Pakete:</strong>
        <label><input type="checkbox" value="numpy"> NumPy</label>
        <label><input type="checkbox" value="pandas"> Pandas</label>
        <label><input type="checkbox" value="matplotlib"> Matplotlib</label>
        <label><input type="checkbox" value="plotly"> Plotly</label>
        <label><input type="checkbox" value="math" checked> math</label>
    </div>
</div>

<div id="container">
    <div id="editor-container"></div>
    <div id="output-container"></div>
</div>

<div id="lint-container"></div>

<script src="pyodide/pyodide.js"></script>
<script type="module" src="js/editor-setup.js"></script>

</body>
</html>
