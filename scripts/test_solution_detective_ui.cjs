const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { test } = require('node:test');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..', 'public');
const assignments = fs.readFileSync(path.join(root, 'js', 'assignments.js'), 'utf8');
const names = [
  'migrateLegacyTestCases', 'runOutputTests', 'runFunctionTests', 'runVariableTests',
  'runIntelligentTests', 'runCodeCheck', 'removePythonComments', 'compareVariableValue',
  'compareValues', 'compareTestOutput', 'matchesPattern', 'patternToRegex'
];
const runtimeSource = names.map(name => {
  const start = assignments.search(new RegExp(`^(?:async )?function ${name}\\(`, 'm'));
  const next = assignments.slice(start + 1).search(/^(?:async )?function \w+\(/m);
  return assignments.slice(start, next < 0 ? undefined : start + 1 + next);
}).join('\n');

test('browser worker, quota UI, code gating, replay, cancellation and both admin views', { timeout: 120000 }, async () => {
  const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/public/fixture') {
      res.setHeader('Content-Type', 'text/html');
      res.end('<!doctype html><html><head></head><body><button id="check-btn">Check</button></body></html>');
      return;
    }
    const file = path.resolve(root, '.' + url.pathname.replace(/^\/public/, ''));
    if (!file.startsWith(root + path.sep)) { res.writeHead(404).end(); return; }
    res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.wasm') ? 'application/wasm' : 'application/octet-stream');
    const stream = fs.createReadStream(file);
    stream.on('error', () => res.writeHead(404).end());
    stream.pipe(res);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({ executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', headless: true });
    const page = await browser.newPage();
    const reports = [];
    let admin = false;
    let posts = 0;
    await page.route('**/api/user_tasks/detective.php*', async route => {
      const request = route.request();
      const requestInput = request.method() === 'POST'
        ? request.postDataJSON() : Object.fromEntries(new URL(request.url()).searchParams);
      assert.equal(Number(requestInput.admin_test), admin ? 1 : 0);
      let report = null;
      if (request.method() === 'POST') {
        posts++;
        const payload = request.postDataJSON();
        report = payload.report;
        if (!admin && !reports.some(r => r.fingerprint === payload.fingerprint)) {
          if (reports.length >= 2) {
            await route.fulfill({ status: 409, json: { ok: false, error: 'Keine Nutzungen mehr.' } });
            return;
          }
          reports.push({ fingerprint: payload.fingerprint, report });
        }
      }
      await route.fulfill({
        json: { ok: true, unlimited: admin, remaining: admin ? null : 2 - reports.length, reports: admin ? [] : reports, report }
      });
    });
    async function setup(view = null) {
      await page.goto(`http://127.0.0.1:${server.address().port}/public/fixture`);
      await page.evaluate(async ({ runtime, view }) => {
        const { installSolutionDetective } = await import('./js/solution-detective.js');
        window.code = 'gesamt_preis = 42';
        if (view) window[view] = true;
        const task = {
          id: 42, task_type: 'code',
          test_cases: JSON.stringify([{ type: 'variable', expected_var_names: ['gesamtpreis'], test_cases: [{ expected_values: [42] }] }])
        };
        window.task = null;
        let changed;
        window.editor = { getValue: () => window.code, onDidChangeModelContent: fn => { changed = fn; return { dispose() {} }; } };
        window.changeCode = code => { window.code = code; changed(); };
        window.detective = installSolutionDetective({
          getTask: () => window.task, getEditor: () => window.editor, isSolution: () => false,
          isAdminTest: () => window.ADMIN_TASK_LAB_VIEW === true || window.ADMIN_USER_TEST_VIEW === true,
          runtimeSource: () => runtime
        });
        window.task = task;
        window.detective.refresh();
      }, { runtime: runtimeSource, view });
      await page.waitForFunction(() => /(?:[012]\/2|∞)/.test(document.getElementById('detective-btn').textContent));
    }
    async function openAndDiagnose() {
      await page.evaluate(() => window.detective.completed(window.task.id, window.code));
      await page.locator('#detective-btn').click();
      await page.waitForFunction(() => document.querySelectorAll('#detective-dialog article').length > 0
        && !document.querySelector('#detective-dialog [role=status]').textContent.includes('Untersuche'), { timeout: 60000 });
    }
    await setup();
    assert.match(await page.locator('#detective-btn').innerText(), /0\/2/);
    assert.equal(await page.locator('#detective-btn').isDisabled(), true);
    await openAndDiagnose();
    assert.equal(reports.length, 1);
    assert.match(await page.locator('#detective-dialog').innerText(), /erwartet „gesamtpreis“; vorhanden ist „gesamt_preis“/);
    assert.equal(await page.evaluate(() => window.code), 'gesamt_preis = 42');
    assert.match(await page.locator('#detective-btn').innerText(), /1\/2/);
    assert.match(await page.locator('#detective-dialog [role=status]').innerText(), /1 von 2 Analysen verbraucht/);
    assert.equal(await page.locator('#detective-dialog button').count(), 1, 'no explanation or confirmation step');
    await page.locator('#detective-dialog button').filter({ hasText: 'Schließen' }).click();
    await openAndDiagnose();
    assert.equal(posts, 1, 'replaying the same code does not contact the save endpoint');
    await page.locator('#detective-dialog button').filter({ hasText: 'Schließen' }).click();
    await page.evaluate(() => window.changeCode('gesamt_preis = 41'));
    // History stays separate; it must not enable a new analysis of unexecuted code.
    assert.equal(await page.locator('#detective-btn').isDisabled(), true);
    await page.locator('#detective-history-btn').click();
    assert.equal(await page.locator('#detective-dialog article').count(), 1);
    await page.locator('#detective-dialog button').filter({ hasText: 'Schließen' }).click();
    await openAndDiagnose();
    await page.waitForFunction(() => document.getElementById('detective-btn').textContent.includes('2/2'));
    assert.equal(reports.length, 2);
    assert.match(await page.locator('#detective-dialog [role=status]').innerText(), /2 von 2 Analysen verbraucht/);
    await page.locator('#detective-dialog button').filter({ hasText: 'Schließen' }).click();
    await page.evaluate(() => window.changeCode('gesamt_preis = 40'));
    await openAndDiagnose();
    assert.match(await page.locator('#detective-dialog [role=status]').innerText(), /verbraucht/);
    assert.equal(posts, 2, 'exhausted quota does not run/save another diagnosis');
    await page.reload();
    await setup();
    assert.match(await page.locator('#detective-btn').innerText(), /2\/2/);
    assert.equal(await page.locator('#detective-btn').isDisabled(), true);
    await page.locator('#detective-history-btn').click();
    assert.equal(await page.locator('#detective-dialog article, #detective-dialog details').count(), 2, 'reports survive reload');

    admin = true;
    for (const view of ['ADMIN_TASK_LAB_VIEW', 'ADMIN_USER_TEST_VIEW']) {
      await setup(view);
      assert.doesNotMatch(await page.locator('#detective-btn').getAttribute('title'), /Admin|unbegrenzt/);
      await openAndDiagnose();
      assert.match(await page.locator('#detective-btn').innerText(), /∞/);
      assert.match(await page.locator('#detective-dialog').innerText(), /unbegrenzt/);
      assert.equal(reports.length, 2, 'admin never touches the student quota');
    }

    await page.locator('#detective-dialog button').filter({ hasText: 'Schließen' }).click();
    await page.evaluate(() => {
      const solution = 'studenten = [["Anna Meyer", 12345, 2.1, 4], ["Ben Schulz", 23456, 1.9, 5], ["Cem Yildiz", 34567, 2.7, 3]]\nzweiter_student = studenten[1]\nprint(zweiter_student)';
      window.task = {
        id: 545, task_type: 'code', solution_code: solution,
        test_cases: JSON.stringify([
          { type: 'code_check', operator: 'AND', keywords: ['zweiter_student\\s*=\\s*studenten\\[1\\]'],
            feedback: 'Speichere alle Werte des zweiten Studenten als komplette innere Liste in zweiter_student.' },
          { type: 'output', expected_type: 'regex', expected: '^\\[.*\\]$' }
        ])
      };
      window.changeCode(solution.replace('studenten[1]', 'studenten[2]'));
      window.detective.refresh();
    });
    await page.waitForFunction(() => document.getElementById('detective-btn').textContent.includes('∞'));
    await openAndDiagnose();
    const indexReport = await page.locator('#detective-dialog').innerText();
    assert.match(indexReport, /Nicht erfüllte Vorgabe: Speichere alle Werte des zweiten Studenten/);
    assert.match(indexReport, /Wertabweichung bei „zweiter_student“/);
    assert.match(indexReport, /erhalten "Cem Yildiz", erwartet "Ben Schulz"/);
    assert.doesNotMatch(indexReport, /verlangte Codevorgabe|lässt sich daraus allein/);
    assert.equal(reports.length, 2, 'supplemental value evidence never writes student progress or quota');
    await page.locator('#detective-dialog button').filter({ hasText: 'Schließen' }).click();
    await page.evaluate(() => {
      window.changeCode('while True:\n    pass');
      window.detective.completed(window.task.id, window.code);
    });
    const before = posts;
    await page.locator('#detective-btn').click();
    await page.evaluate(() => {
      window.task = { ...window.task, id: 43 };
      window.detective.refresh();
    });
    assert.equal(await page.locator('#detective-dialog').isVisible(), false);
    assert.equal(posts, before, 'switching tasks aborts the disposable worker before charging');
  } finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
  }
});
