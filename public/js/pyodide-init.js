// pyodide-init.js
export async function initPyodideEnv() {
  const pyodide = await loadPyodide({
    indexURL: './pyodide/'  // <--- Pfad zu deinem pyodide-Verzeichnis
  });

  console.log('Pyodide ready');

  // Beispiel: Python-Code ausführen
  const result = await pyodide.runPythonAsync(`
import sys
sys.version
  `);

  console.log('Python version:', result);

  return pyodide;
}
