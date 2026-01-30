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
            font-family: sans-serif;
        }

        /* App-Wrapper */
        #app {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Toolbar */
        #toolbar {
            height: 44px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 10px;
            border-bottom: 1px solid #ccc;
            background: #f5f5f5;
            flex: 0 0 auto;
        }

        /* Hauptbereich: zwei Spalten */
        #main {
            flex: 1 1 auto;
            display: flex;
            overflow: hidden;
            min-height: 0; /* 🔥 wichtig */
        }

        /* Linke Spalte: Editor (75%) + Lint (25%) */
        #left-col {
            flex: 2;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0; /* 🔥 wichtig */
        }

        #editor-container {
            flex: 3;      /* 75% */
            min-height: 0; /* 🔥 wichtig */
        }

        #lint-container {
            flex: 1;      /* 25% */
            min-height: 0; /* 🔥 wichtig */
            border-top: 1px solid #ccc;
            background: #f0f0f0;
            font-family: monospace;
            padding: 8px;
            overflow: auto;
            white-space: pre;
        }

        /* Rechte Spalte: Output (50%) + Plot (50%) */
        #right-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0; /* 🔥 wichtig */
            border-left: 1px solid #ccc;
        }

        #output-container {
            flex: 1;       /* 50% */
            min-height: 0; /* 🔥 wichtig */
            padding: 10px;
            overflow: auto;
            font-family: monospace;
            white-space: pre;
            background: #fafafa;
            border-bottom: 1px solid #ccc;
        }

        #plot-container {
            flex: 1;        /* 50% */
            min-height: 0;  /* 🔥 wichtig für Scroll in Flexbox */
            overflow-y: auto;
            padding: 10px;
            background: #fff;
        }

        /* Plot-Bilder untereinander */
        #plot-container img {
            display: block;
            width: 100%;
            max-width: 100%;
            margin-bottom: 12px;
            border: 1px solid #ddd;
            background: #fff;
        }
        .lint-ok {
  color: #000;
  font-weight: 600;
}

.lint-ok-check {
  color: #22c55e !important; /* erzwingt grün */
}

    </style>
</head>

<body>
<div id="app">

    <div id="toolbar">
        <button id="run-btn">Run</button>
       
        <label><input type="checkbox" id="lib-numpy" checked> NumPy</label>
        <label><input type="checkbox" id="lib-matplotlib" checked> Matplotlib</label>
       
    </div>

    <div id="main">
        <div id="left-col">
            <div id="editor-container"></div>
            <div id="lint-container"></div>
        </div>

        <div id="right-col">
            <div id="output-container"></div>
            <div id="plot-container"></div>
        </div>
    </div>

</div>

<!-- Pyodide -->
<script src="pyodide/pyodide.js"></script>
<!-- Editor Setup -->
<script type="module" src="js/editor-setup.js"></script>

</body>
</html>
