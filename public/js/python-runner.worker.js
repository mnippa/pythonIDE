import { loadPyodide } from '../pyodide/pyodide.mjs';

let pyodide = null;
const loadedPackages = new Set();
let workerInputRequestSeq = 0;
const pendingWorkerInputs = new Map();

async function ensurePyodide() {
  if (pyodide) return pyodide;

  pyodide = await loadPyodide({ indexURL: '../pyodide/' });

  pyodide.setStdout({
    batched: (text) => {
      self.postMessage({ type: 'stdout', text: String(text ?? ''), token: currentToken });
    }
  });

  pyodide.setStderr({
    batched: (text) => {
      self.postMessage({ type: 'stderr', text: String(text ?? ''), token: currentToken });
    }
  });

  self.postMessage({ type: 'ready' });
  return pyodide;
}

let currentToken = 0;

async function ensurePackages(packages) {
  const target = Array.isArray(packages) ? packages : [];
  const toLoad = target.filter((pkg) => pkg && !loadedPackages.has(pkg));
  if (!toLoad.length) return { loaded: 0 };
  await pyodide.loadPackage(toLoad);
  toLoad.forEach((pkg) => loadedPackages.add(pkg));
  return { loaded: toLoad.length };
}

function normalizeProjectRuntimePayload(payload) {
  if (!payload || typeof payload !== 'object') return null;
  return {
    root: typeof payload.root === 'string' ? payload.root : '/project',
    mainPath: typeof payload.mainPath === 'string' ? payload.mainPath : '',
    files: Array.isArray(payload.files) ? payload.files : [],
  };
}

function clearPendingWorkerInputsForToken(token) {
  for (const [requestId, entry] of pendingWorkerInputs.entries()) {
    if (entry?.token === token) {
      try {
        entry.resolve('');
      } catch (_err) {
        // Ignore resolve errors during cleanup.
      }
      pendingWorkerInputs.delete(requestId);
    }
  }
}

self.__pyideWorkerInput = function __pyideWorkerInput(promptText = '') {
  const requestId = ++workerInputRequestSeq;
  return new Promise((resolve) => {
    pendingWorkerInputs.set(requestId, { token: currentToken, resolve });
    self.postMessage({
      type: 'input-request',
      token: currentToken,
      requestId,
      prompt: String(promptText || ''),
    });
  });
};

self.__pyideWorkerClear = function __pyideWorkerClear() {
  self.postMessage({
    type: 'clear',
    token: currentToken,
  });
};

self.onmessage = async (event) => {
  const message = event.data || {};

  if (message.type === 'input-response') {
    const token = Number(message.token || 0);
    const requestId = Number(message.requestId || 0);
    const pending = pendingWorkerInputs.get(requestId);
    if (!pending || pending.token !== token) {
      return;
    }
    pendingWorkerInputs.delete(requestId);
    pending.resolve(String(message.value ?? ''));
    return;
  }

  if (message.type === 'prewarm') {
    try {
      const start = (self.performance && typeof self.performance.now === 'function')
        ? self.performance.now()
        : Date.now();
      await ensurePyodide();
      const end = (self.performance && typeof self.performance.now === 'function')
        ? self.performance.now()
        : Date.now();
      const ms = Math.round((end - start) * 10) / 10;
      self.postMessage({ type: 'prewarmed', ms });
    } catch (error) {
      const messageText = String(error?.message || error || 'Worker prewarm failed');
      self.postMessage({ type: 'prewarm-error', error: messageText });
    }
    return;
  }

  if (message.type === 'stop') {
    clearPendingWorkerInputsForToken(currentToken);
    self.postMessage({ type: 'stopped', token: currentToken });
    return;
  }

  if (message.type !== 'run') {
    return;
  }

  const token = Number(message.token || 0);
  currentToken = token;
  clearPendingWorkerInputsForToken(token);

  const payload = message.payload || {};
  const code = String(payload.code || '');
  const enableMatplotlib = payload.enableMatplotlib === true;
  const packages = Array.isArray(payload.packages) ? payload.packages : [];
  const projectRuntime = normalizeProjectRuntimePayload(payload.projectRuntime);

  try {
    const t0 = (self.performance && typeof self.performance.now === 'function')
      ? self.performance.now()
      : Date.now();
    await ensurePyodide();
    const t1 = (self.performance && typeof self.performance.now === 'function')
      ? self.performance.now()
      : Date.now();
    const pkgInfo = await ensurePackages(packages);
    const t2 = (self.performance && typeof self.performance.now === 'function')
      ? self.performance.now()
      : Date.now();

    const result = await pyodide.runPythonAsync(`
import sys
import os
import json
import builtins
import types
from js import __pyideWorkerInput

enable_matplotlib = ${enableMatplotlib ? 'True' : 'False'}
if enable_matplotlib:
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt

from io import BytesIO
import base64

code = ${JSON.stringify(code)}
project_runtime = json.loads(${JSON.stringify(JSON.stringify(projectRuntime ?? null))})

_original_input = builtins.input

def _worker_input(prompt=''):
    prompt_str = str(prompt) if prompt else ''
    try:
      result = __pyideWorkerInput(prompt_str)
      if result is not None and hasattr(result, 'then'):
        import asyncio
        result = asyncio.get_event_loop().run_until_complete(result)
      return '' if result is None else str(result)
    except Exception:
      # Keep deterministic fallback on bridge failures.
      return ''

builtins.input = _worker_input

def outputClear():
  # Call JavaScript function to clear DOM output
  try:
    js_window.__pyideWorkerClear()
  except Exception:
    pass

def outputWrite(value=''):
  text = '' if value is None else str(value)
  if text:
    sys.stdout.write(text)

def outputFlush():
  try:
    sys.stdout.flush()
  except Exception:
    pass

def clear_output():
  outputClear()

def redraw(value=''):
  outputClear()
  outputWrite(value)

pyide_module = sys.modules.get('pyide')
if pyide_module is None:
  pyide_module = types.ModuleType('pyide')

pyide_module.outputClear = outputClear
pyide_module.outputWrite = outputWrite
pyide_module.outputFlush = outputFlush
pyide_module.clear_output = clear_output
pyide_module.redraw = redraw
pyide_module.output_clear = outputClear
pyide_module.output_write = outputWrite
pyide_module.output_flush = outputFlush
pyide_module.__all__ = [
  'outputClear',
  'outputWrite',
  'outputFlush',
  'clear_output',
  'redraw',
  'output_clear',
  'output_write',
  'output_flush',
]
sys.modules['pyide'] = pyide_module

if isinstance(project_runtime, dict):
    runtime_root = str(project_runtime.get('root') or '/project')
    runtime_files = project_runtime.get('files') or []

    if runtime_files:
        try:
            os.makedirs(runtime_root, exist_ok=True)
        except Exception:
            pass

        for entry in runtime_files:
            if not isinstance(entry, dict):
                continue
            rel_path = str(entry.get('path') or '').replace('\\\\', '/').strip('/')
            if not rel_path:
                continue

            abs_path = runtime_root.rstrip('/') + '/' + rel_path
            parent_dir = os.path.dirname(abs_path)
            if parent_dir:
                os.makedirs(parent_dir, exist_ok=True)

            with open(abs_path, 'w', encoding='utf-8') as fh:
                fh.write(str(entry.get('content') or ''))

        main_rel = str(project_runtime.get('mainPath') or '').replace('\\\\', '/').strip('/')
        if main_rel:
            project_main_path = runtime_root.rstrip('/') + '/' + main_rel
            project_main_dir = os.path.dirname(project_main_path)
            if project_main_dir and project_main_dir not in sys.path:
                sys.path.insert(0, project_main_dir)
            if project_main_dir:
                try:
                    os.chdir(project_main_dir)
                except Exception:
                    pass

        if runtime_root not in sys.path:
            sys.path.insert(0, runtime_root)

g = {
  '__name__': '__main__',
  'outputClear': outputClear,
  'outputWrite': outputWrite,
  'outputFlush': outputFlush,
  'clear_output': clear_output,
  'redraw': redraw,
}
exec(compile(code, '<usercode>', 'exec'), g, g)

plots = []
if enable_matplotlib:
    fignums = list(plt.get_fignums())
    for n in fignums:
        fig = plt.figure(n)
        buf = BytesIO()
        fig.savefig(buf, format='png', bbox_inches='tight')
        buf.seek(0)
        b64 = base64.b64encode(buf.read()).decode('ascii')
        plots.append({'title': f'Figure {n}', 'dataUrl': 'data:image/png;base64,' + b64})
    plt.close('all')

plots
`);
    const t3 = (self.performance && typeof self.performance.now === 'function')
      ? self.performance.now()
      : Date.now();

    const plots = typeof result?.toJs === 'function' ? result.toJs() : [];
    if (typeof result?.destroy === 'function') {
      result.destroy();
    }

    if (Array.isArray(plots)) {
      for (const plot of plots) {
        self.postMessage({ type: 'plot', token, title: plot?.title || 'Figure', dataUrl: plot?.dataUrl || '' });
      }
    }

    clearPendingWorkerInputsForToken(token);

    self.postMessage({
      type: 'done',
      token,
      timings: {
        initMs: Math.round((t1 - t0) * 10) / 10,
        packagesMs: Math.round((t2 - t1) * 10) / 10,
        execMs: Math.round((t3 - t2) * 10) / 10,
        totalMs: Math.round((t3 - t0) * 10) / 10,
        packagesLoaded: Number(pkgInfo?.loaded || 0),
      },
    });
  } catch (error) {
    clearPendingWorkerInputsForToken(token);
    const messageText = String(error?.message || error || 'Worker execution failed');
    self.postMessage({ type: 'error', token, error: messageText });
  }
};
