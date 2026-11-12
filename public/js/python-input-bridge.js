export function pythonInputBridgeSource(asyncBridge, syncBridge = null) {
  return `
import inspect as _pyide_inspect
from pyodide.ffi import can_run_sync as _pyide_can_run_sync, run_sync as _pyide_run_sync, jsnull as _pyide_jsnull

_original_input = builtins.input
def _custom_input(prompt=''):
    prompt_str = str(prompt)
    if _pyide_can_run_sync():
        result = ${asyncBridge}(prompt_str)
        if _pyide_inspect.isawaitable(result) or hasattr(result, 'then'):
            result = _pyide_run_sync(result)
    else:
        ${syncBridge ? `result = ${syncBridge}(prompt_str)` : `raise RuntimeError('Dieser Browser unterstützt keine wartende Worker-Eingabe. Bitte den Hauptthread-Eingabedialog verwenden.')`}
    if result is None or result is _pyide_jsnull:
        raise EOFError('Eingabe abgebrochen.')
    if _pyide_inspect.isawaitable(result) or hasattr(result, 'then'):
        raise RuntimeError('Die Eingabebrücke hat keinen abgeschlossenen Eingabewert geliefert.')
    return str(result)

builtins.input = _custom_input
`;
}

export function supportsWorkerInput() {
  return typeof WebAssembly.Suspending === 'function' && typeof WebAssembly.promising === 'function';
}
