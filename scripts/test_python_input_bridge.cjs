const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const { chromium } = require('playwright');
const { loadPyodide } = require('../public/pyodide/pyodide.js');

const root = path.resolve(__dirname, '..', 'public');
const source = fs.readFileSync(path.join(root, 'js', 'editor-setup.js'), 'utf8').replace(/\r\n/g, '\n');
const bridgeSource = fs.readFileSync(path.join(root, 'js', 'python-input-bridge.js'), 'utf8');
const bridge = {};
vm.createContext(bridge);
vm.runInContext(bridgeSource.replace(/^export /gm, ''), bridge);
const example = `zahlen = []
eingabe = (input("Zahl eingeben (0 für ende):"))
while int(eingabe) != 0:
    zahlen.append(eingabe)
    eingabe = (input("Zahl eingeben(0 für Ende): "))
print("Erfasste Zahlen:", zahlen)`;
const runStart = source.indexOf('        await pyodide.runPythonAsync(`\nfrom js import document, window as js_window');
assert.ok(runStart >= 0);
const templateStart = source.indexOf('`', runStart);
const templateEnd = source.indexOf('`);', templateStart);
const mainTemplate = source.slice(templateStart, templateEnd + 1);
const inputFunctions = source.slice(source.indexOf('    let preparedInputQueue = [];'),
  source.indexOf('    async function collectOverlayInputsForCode'));
const modalFunctions = source.slice(0, source.indexOf('async function initPyodideAndEditor()'))
  .replace(/^import .*;\r?$/gm, '');
const guiStart = source.indexOf('    async function ensureIdeGuiModule()');
const guiEnd = source.indexOf('\n    await ensureIdeGuiModule();', guiStart);
const guiInstaller = source.slice(guiStart, guiEnd);
const assignmentsSource = fs.readFileSync(path.join(root, 'js', 'assignments.js'), 'utf8').replace(/\r\n/g, '\n');
const projectsSource = fs.readFileSync(path.join(root, 'js', 'projects-editor.js'), 'utf8').replace(/\r\n/g, '\n');
function extractFunction(text, name, nextName) {
  const start = text.indexOf(`async function ${name}(`);
  const end = text.indexOf(`\nfunction ${nextName}(`, start);
  assert.ok(start >= 0 && end > start);
  return text.slice(start, end);
}
const taskCallback = extractFunction(assignmentsSource, 'triggerCodeUiFunctionCall', 'setCodeUiTriggerContext');
const projectCallback = extractFunction(projectsSource, 'triggerProjectFunctionCall', 'ensureProjectCodeUiRunTriggers');

test('old bridge reproduces the reported pending Future; replacement returns only completed strings', async () => {
  const py = await loadPyodide({ stdout() {}, stderr() {} });
  let asyncCalls = 0;
  const values = ['5', '12', '0'];
  py.registerJsModule('input_probe', {
    asyncInput: () => { asyncCalls++; return Promise.resolve('unexpected'); },
    syncInput: () => values.shift() ?? null
  });
  const result = await py.runPythonAsync(`
import asyncio, json, builtins
from input_probe import asyncInput, syncInput
from pyodide_js._api import config
config.enableRunUntilComplete = False
old_value = str(asyncio.get_event_loop().run_until_complete(asyncInput()))
json.dumps({"old": old_value})
`);
  assert.match(JSON.parse(result).old, /PyodideFuture pending/);
  const before = asyncCalls;
  await py.runPythonAsync(bridge.pythonInputBridgeSource('asyncInput', 'syncInput'));
  await py.runPythonAsync(example);
  assert.equal(await py.runPythonAsync('json.dumps(zahlen)'), '["5", "12"]');
  assert.equal(asyncCalls, before, 'unsupported runtimes must never open an asynchronous overlay');
  await assert.rejects(py.runPythonAsync('input("cancel")'), /EOFError: Eingabe abgebrochen/);
  values.push('');
  assert.equal(await py.runPythonAsync('input("empty")'), '');
});

test('browser main runner, GUI/HTML callbacks and worker preserve dynamic input and state', { timeout: 120000 }, async t => {
  const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/fixture') {
      res.setHeader('Content-Type', 'text/html');
      res.end('<!doctype html><html><head></head><body><div id="output-container"></div><div id="lint-container"></div><div id="plot-container"></div><div id="gui-container"></div><script src="/pyodide/pyodide.js"></script></body></html>');
      return;
    }
    const file = path.resolve(root, '.' + url.pathname);
    if (!file.startsWith(root + path.sep)) { res.writeHead(404).end(); return; }
    res.setHeader('Content-Type', file.endsWith('.js') || file.endsWith('.mjs') ? 'text/javascript'
      : file.endsWith('.wasm') ? 'application/wasm' : 'application/octet-stream');
    const stream = fs.createReadStream(file);
    stream.on('error', () => res.writeHead(404).end());
    stream.pipe(res);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({ executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', headless: true });
    for (const asyncSupported of [true, false]) {
      const page = await browser.newPage();
      await page.goto(`http://127.0.0.1:${server.address().port}/fixture`);
      await page.evaluate(async ({ asyncSupported, mainTemplate, inputFunctions, modalFunctions, guiInstaller, taskCallback, projectCallback }) => {
        if (!asyncSupported) {
          delete WebAssembly.Suspending;
          delete WebAssembly.promising;
        }
        const { pythonInputBridgeSource, supportsWorkerInput } = await import('/js/python-input-bridge.js');
        if (supportsWorkerInput() !== asyncSupported) throw new Error('Unexpected browser stack-switching capability');
        window.py = await loadPyodide({ indexURL: '/pyodide/', stdout() {}, stderr() {} });
        window.pyodide = window.py;
        window.eval(modalFunctions);
        window.installInputs = new Function(inputFunctions + `
          window.__pyideConsumeInput = consumePreparedInput;
          window.__pyideConsumeSyncInput = prompt => consumePreparedInput(prompt, true);
          window.setPreparedInputQueue = setPreparedInputQueue;
        `);
        window.installInputs();
        window.__pyideOutputWrite = text => { document.getElementById('output-container').textContent += text; };
        window.__pyideOutputFlush = () => {};
        window.__pyideOutputClear = () => { document.getElementById('output-container').textContent = ''; };
        const build = new Function('pythonInputBridgeSource', 'code', 'projectRuntimePayload', 'enableMatplotlib', 'isCodeUiMode', 'usePreservedGlobals', `return ${mainTemplate};`);
        window.runMain = (code, project = null, gui = false) => {
          window.__pyideOutputClear();
          return window.py.runPythonAsync(build(pythonInputBridgeSource, code, project, false, gui, gui));
        };
        window.guiBridge = {
          showGUI() {},
          getGUIContainer: () => document.getElementById('gui-container'),
          clearGUI: () => { document.getElementById('gui-container').replaceChildren(); }
        };
        window.installGui = new Function('pyodide', guiInstaller + '\nreturn ensureIdeGuiModule();');
        window.eval(taskCallback);
        window.eval(projectCallback);
      }, { asyncSupported, mainTemplate, inputFunctions, modalFunctions, guiInstaller, taskCallback, projectCallback });
      const answers = ['5', '12', '8', '0'];
      let prompts = 0;
      page.on('dialog', async dialog => {
        prompts++;
        const answer = answers.shift();
        if (answer === undefined) await dialog.dismiss(); else await dialog.accept(answer);
      });
      await page.evaluate(code => {
        window.result = window.runMain(code).then(() => ({ ok: true }), error => ({ error: String(error) }));
      }, example);
      if (asyncSupported) {
        for (const value of ['5', '12', '8', '0']) {
          await page.waitForSelector('#python-input-modal', { state: 'visible' });
          await page.locator('#python-input-field').fill(value);
          await page.locator('#python-input-modal button').filter({ hasText: 'OK' }).click();
        }
      }
      const result = await page.evaluate(() => window.result);
      assert.equal(result.ok, true, result.error);
      assert.match(await page.locator('#output-container').textContent(), /Erfasste Zahlen: \['5', '12', '8'\]/);
      assert.equal(prompts, asyncSupported ? 0 : 4);
      assert.doesNotMatch(await page.locator('#output-container').textContent(), /Future|Promise/);
      await page.evaluate(() => window.runMain('wert = 7 + 5\nprint(wert)'));
      assert.equal(await page.locator('#output-container').textContent(), '12\n');
      await page.evaluate(() => window.setPreparedInputQueue(['21', '0']));
      await page.evaluate(() => window.runMain('from helper import erfasse\nprint(erfasse())', {
        root: '/input-project', mainPath: 'init.py',
        files: [{ path: 'helper.py', content: 'def erfasse():\n    wert = input("Modul:")\n    return int(wert)' }]
      }));
      assert.match(await page.locator('#output-container').textContent(), /21/);
      answers.length = 0;
      async function answerOverlay(value) {
        await page.waitForSelector('#python-input-modal', { state: 'visible' });
        await page.locator('#python-input-field').fill(value);
        await page.locator('#python-input-submit').click();
      }
      async function startGuiAction(action, value) {
        if (!asyncSupported) answers.push(value);
        await page.evaluate(action => {
          window.guiAction = Promise.resolve().then(() => window.eval(action))
            .then(() => ({ ok: true }), error => ({ error: String(error) }));
        }, action);
        if (asyncSupported) {
          const first = await Promise.race([
            page.waitForSelector('#python-input-modal', { state: 'visible' }).then(() => null),
            page.evaluate(() => window.guiAction)
          ]);
          assert.equal(first, null, `GUI action completed without expected input: ${JSON.stringify(first)}`);
          await answerOverlay(value);
        }
        const outcome = await page.evaluate(() => window.guiAction);
        assert.equal(outcome.ok, true, outcome.error);
      }
      await page.evaluate(async () => {
        delete window.__codeUiGlobals;
        window.setPreparedInputQueue([]);
        await window.py.runPythonAsync('import sys\nsys.modules.pop("idegui", None)\nNone');
        await window.installGui(window.py);
      });
      const emptyGuiFailure = await page.evaluate(async () => {
        try {
          await window.runMain('import idegui as ui\nui.title("Ohne Eingabe")', null, true);
          return null;
        } catch (error) {
          return String(error);
        }
      });
      assert.match(emptyGuiFailure, /JsNull.*querySelector/, 'existing empty-GUI initialization issue is independent of input');
      t.diagnostic(`Existing empty-GUI limitation reproduced without input (stack switching ${asyncSupported ? 'enabled' : 'disabled'}); callback tests use an initialized container.`);
      const guiCode = `import idegui as ui
ui.title("Eingabetest")
eingabefeld = ui.input("Name", "Start")
anzahl = globals().get("anzahl", 0)
def auswerten(event=None):
    global anzahl
    anzahl += 1
    zahl = int(input("GUI-Zahl:"))
    ui.set("ergebnis", str(zahl + anzahl))
    ui.set("zaehler", str(anzahl))
def ohne_input(event=None):
    global anzahl
    anzahl += 1
    ui.set("ergebnis", ui.get("name"))
    ui.set("zaehler", str(anzahl))
button = ui.button("GUI-Klick")
button.on_click(auswerten)`;
      await page.evaluate(async code => {
        // Isolate input behavior from the independently reproduced empty-GUI defect.
        document.getElementById('gui-container').innerHTML = '<div id="idegui-root"><h3 data-idegui-title="true"></h3></div><div id="idegui-output"></div><input data-element="name" value="HTML-Wert"><output data-element="ergebnis"></output><output data-element="zaehler"></output><button id="html-callback" data-function="auswerten">HTML</button>';
        window.__codeUiTrigger = { name: '', value: '' };
        await window.runMain(code, null, true);
      }, guiCode);
      assert.equal(await page.locator('#gui-container h3').textContent(), 'Eingabetest');
      assert.equal(await page.locator('#idegui-root input').inputValue(), 'Start', 'ui.input remains a GUI widget, not a blocking builtins.input');
      // Traditional data-run dispatch occurs inside runPythonAsync.
      await page.evaluate(() => { window.__codeUiTrigger = { name: 'auswerten', value: '' }; });
      await startGuiAction(`window.runMain(${JSON.stringify(guiCode)}, null, true)`, '10');
      assert.equal(await page.locator('[data-element="ergebnis"]').textContent(), '11');
      assert.equal(await page.locator('[data-element="zaehler"]').textContent(), '1');
      // Both real HTML callback entry points reuse preserved Python globals.
      for (const [handler, count] of [['triggerCodeUiFunctionCall', 2], ['triggerProjectFunctionCall', 3]]) {
        await page.evaluate(() => { window.__codeUiEventDrivenMode = true; window.__codeUiLastFunctionTrigger = null; });
        await startGuiAction(`${handler}(document.getElementById("html-callback"))`, '20');
        assert.equal(await page.locator('[data-element="ergebnis"]').textContent(), String(20 + count));
        assert.equal(await page.locator('[data-element="zaehler"]').textContent(), String(count));
      }
      // A direct idegui callback is a synchronous DOM-to-Python call, even on a JSPI browser.
      answers.push('30');
      const nativeBefore = prompts;
      await page.locator('#idegui-root button').last().click();
      assert.equal(await page.locator('[data-element="ergebnis"]').textContent(), '34');
      assert.equal(prompts, nativeBefore + 1);
      await page.evaluate(async () => {
        const button = document.getElementById('html-callback');
        button.setAttribute('data-function', 'ohne_input');
        window.__codeUiLastFunctionTrigger = null;
        await triggerProjectFunctionCall(button);
      });
      assert.equal(await page.locator('[data-element="ergebnis"]').textContent(), 'HTML-Wert');
      assert.equal(await page.locator('[data-element="zaehler"]').textContent(), '5');
      assert.doesNotMatch(await page.locator('#gui-container').textContent(), /Future|Promise|Error/);
      for (const projectType of ['html', 'mixed']) {
        await page.evaluate(async projectType => {
          window.currentProject = { id: 1, project_type: projectType };
          window.__codeUiTrigger = { name: '', value: '' };
          window.__codeUiEventDrivenMode = true;
          await window.runMain('import idegui as ui\ndef projekt_eingabe(event=None):\n    ui.set("ergebnis", str(int(input("Projekt:")) * 2))', {
            root: '/interactive-project', mainPath: 'init.py', files: []
          }, true);
          document.getElementById('html-callback').setAttribute('data-function', 'projekt_eingabe');
          window.__codeUiLastFunctionTrigger = null;
        }, projectType);
        await startGuiAction('triggerProjectFunctionCall(document.getElementById("html-callback"))', '14');
        assert.equal(await page.locator('[data-element="ergebnis"]').textContent(), '28', `${projectType} project callback input`);
      }
      if (!asyncSupported) {
        const cancellation = await page.evaluate(async () => {
          window.setPreparedInputQueue([]);
          try { await window.runMain('input("Abbrechen")'); return 'unexpected success'; }
          catch (error) { return String(error); }
        });
        assert.match(cancellation, /EOFError: Eingabe abgebrochen/);
        assert.equal(await page.locator('#python-input-modal').count(), 0, 'native fallback must not open an orphaned overlay');
      }
      if (asyncSupported) {
        const workerResult = await page.evaluate(async code => {
          const worker = new Worker('/js/python-runner.worker.js', { type: 'module' });
          try {
            return await new Promise((resolve, reject) => {
              const values = ['5', '12', '8', '0'];
              const output = [];
              worker.onmessage = ({ data }) => {
                if (data.type === 'input-request') worker.postMessage({ type: 'input-response', token: data.token, requestId: data.requestId, value: values.shift() });
                if (data.type === 'stdout') output.push(data.text);
                if (data.type === 'error') reject(new Error(data.error));
                if (data.type === 'done') resolve(output.join('\n'));
              };
              worker.onerror = event => reject(new Error(event.message));
              worker.postMessage({ type: 'run', token: 1, payload: { code, packages: [], enableMatplotlib: false } });
            });
          } finally {
            worker.terminate();
          }
        }, example);
        assert.match(workerResult, /Erfasste Zahlen: \['5', '12', '8'\]/);
      }
      await page.close();
    }
  } finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
  }
});
