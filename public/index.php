<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python IDE</title>
    <style>
        body { margin:0; font-family: sans-serif; }
        #editor-container { width: 70%; height: 80vh; float: left; }
        #output-container { width: 30%; height: 80vh; float: left; background:#f0f0f0; padding:10px; overflow:auto; }
        #run-btn { clear:both; margin:10px; padding:5px 10px; }
    </style>
    <style>
    body { margin:0; font-family:sans-serif; display:flex; height:100vh; }
    #editor-container { flex: 2; }
    #output-container { flex: 1; background:#f0f0f0; padding:10px; overflow:auto; }
    #run-btn { position:absolute; bottom:10px; left:10px; padding:5px 10px; }
</style>

</head>
<body>
    <div id="editor-container"></div>
    <div id="output-container"></div>
    <button id="run-btn">Run</button>

    <!-- Monaco AMD Loader -->
    <script src="/pythonIDE/public/monaco/min/vs/loader.js"></script>

    <script>
        // Konfiguration für AMD
        require.config({ paths: { 'vs': '/pythonIDE/public/monaco/min/vs' } });

        // Monaco Editor laden
        require(['vs/editor/editor.main'], function() {
            window.editor = monaco.editor.create(document.getElementById('editor-container'), {
                value: 'print("Hello Pyodide")',
                language: 'python',
                automaticLayout: true
            });
        });
    </script>

    <!-- Pyodide -->
    <script type="module">
        import { loadPyodide } from '/pythonIDE/public/pyodide/pyodide.mjs';

        let pyodide;
        async function initPy() {
            pyodide = await loadPyodide({
                indexURL: '/pythonIDE/public/pyodide/'
            });
            console.log("Pyodide ready");
        }
        initPy();

        // Run-Button
        document.getElementById('run-btn').addEventListener('click', async () => {
            const code = window.editor.getValue();
            try {
                const output = await pyodide.runPythonAsync(code);
                document.getElementById('output-container').textContent = output;
            } catch (err) {
                document.getElementById('output-container').textContent = err;
            }
        });
    </script>
</body>
</html>
