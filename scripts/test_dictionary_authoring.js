const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public', 'js', 'assignments.js'), 'utf8');
const names = [
  'runIntelligentTests', 'runOutputTests', 'runVariableTests',
  'compareVariableValue', 'compareValues', 'compareTestOutput'
];
const functions = names.map(name => {
  const start = source.search(new RegExp(`^(?:async )?function ${name}\\(`, 'm'));
  if (start < 0) throw new Error(`Missing function ${name}`);
  const end = source.indexOf('\n}', start) + 2;
  return source.slice(start, end);
}).join('\n\n');
const context = { console };
vm.createContext(context);
vm.runInContext(functions, context);

assert.equal(context.compareValues({ a: 1, b: { c: 2 } }, { b: { c: 2 }, a: 1 }), true);
assert.equal(context.compareValues([1, 2], [2, 1]), false);
assert.equal(context.compareValues({ a: 1 }, { b: 1 }), false);
assert.equal(context.compareValues({ a: '1' }, { a: 1 }), false);
assert.equal(context.compareValues([], {}, true), false);
assert.equal(context.compareValues({ a: [] }, { a: {} }, true), false);
assert.equal(context.compareValues([], {}), true);

const tasks = JSON.parse(execFileSync('php', [
  path.join(__dirname, 'create_assignment48_dictionary_tasks.php'), '--definitions'
], { encoding: 'utf8' }));
for (const task of tasks) {
  assert.ok(task.solution_code.startsWith(task.code_template));
  assert.ok(task.code_template.includes('#INIT END'));
  assert.equal(task.test_cases.length, 3);
  assert.equal(task.test_cases[0].value_comparison, 'structural');
  assert.equal(task.test_cases[0].stdout_comparison, undefined);
}

async function verifyDictionaryRuntime(pyodide) {
  const summary = [];
  for (const task of window.dictionaryAuthoringTasks) {
    const results = [
      ...await runIntelligentTests(pyodide, task.solution_code, [task.test_cases[0]], task.solution_code, task.randomizer),
      ...await runVariableTests(pyodide, task.solution_code, [task.test_cases[1]]),
      ...await runOutputTests(pyodide, task.solution_code, [task.test_cases[2]], task)
    ];
    const failures = results.filter(result => !result.passed);
    if (failures.length) throw new Error(JSON.stringify({ title: task.title, failures }));
    let withoutPrint = task.solution_code.split('\n').filter(line => !line.trim().startsWith('print(')).join('\n');
    withoutPrint = withoutPrint.replace(/for artikelnummer, menge in gesamtmengen.items\(\):\s*$/, '');
    const rejected = await runOutputTests(pyodide, withoutPrint, [task.test_cases[2]], task);
    if (rejected.every(result => result.passed)) throw new Error(`Missing output accepted: ${task.title}`);
    summary.push({ title: task.title, tests: results.length, passed: true, missingPrintRejected: true });
  }
  const last = window.dictionaryAuthoringTasks[4];
  const reordered = last.solution_code.replace(
    'for artikelnummer, menge in gesamtmengen.items():',
    'gesamtmengen = dict(sorted(gesamtmengen.items(), reverse=True))\nfor artikelnummer, menge in gesamtmengen.items():'
  );
  const reorderResults = [
    ...await runIntelligentTests(pyodide, reordered, [last.test_cases[0]], last.solution_code, last.randomizer),
    ...await runOutputTests(pyodide, reordered, [last.test_cases[2]], last)
  ];
  if (!reorderResults.every(result => result.passed)) throw new Error('Dictionary/output order rejected');
  const wrongContainer = last.code_template + 'gesamtmengen = []';
  const emptyResults = await runIntelligentTests(
    pyodide, wrongContainer, [last.test_cases[0]], last.solution_code, 'values = {"positionen": []}'
  );
  if (emptyResults.some(result => result.passed)) throw new Error('List accepted instead of empty dictionary');
  const missingZero = last.solution_code.replace(
    'gesamtmengen = {}',
    'positionen = [p for p in positionen if p["menge"] != 0]\ngesamtmengen = {}'
  );
  const zeroResults = await runVariableTests(pyodide, missingZero, [last.test_cases[1]]);
  if (zeroResults.every(result => result.passed)) throw new Error('Missing zero-quantity article accepted');
  const first = window.dictionaryAuthoringTasks[0];
  const hardcoded = first.code_template + 'bezeichnung = "Ordner"\nbestandswert = 42\nprint(bezeichnung)\nprint(bestandswert)';
  const hardcodedResults = await runIntelligentTests(
    pyodide, hardcoded, [first.test_cases[0]], first.solution_code,
    'values = {"artikel": {"bezeichnung": "Heft", "preis": 2, "bestand": 3}}'
  );
  if (hardcodedResults.some(result => result.passed)) throw new Error('Hardcoded starter results accepted');
  const legacySpec = { type: 'intelligent', mode: 'vars', tests: 2, inputs: ['a'], outputs: ['d'] };
  const legacySolution = '#INIT START\na=1\n#INIT END\nd={"a":a,"b":2}';
  const legacyUser = '#INIT START\na=1\n#INIT END\nd={"b":2,"a":a}';
  const legacyResults = await runIntelligentTests(
    pyodide, legacyUser, [legacySpec], legacySolution, 'values={"a":3}'
  );
  if (legacyResults.some(result => result.passed)) throw new Error('Legacy comparison changed');
  const legacyNoPrint = await runIntelligentTests(
    pyodide, legacySolution, [legacySpec], legacySolution, 'values={"a":3}'
  );
  if (!legacyNoPrint.every(result => result.passed)) throw new Error('Legacy stdout behavior changed');
  return { status: 'PASS', tasks: summary, dictionaryOrder: 'ignored', legacyBehavior: 'unchanged' };
}

const bundleIndex = process.argv.indexOf('--browser-bundle');
if (bundleIndex >= 0) {
  const target = process.argv[bundleIndex + 1];
  if (!target) throw new Error('Missing browser bundle path');
  fs.writeFileSync(target, `${functions}\nwindow.dictionaryAuthoringTasks = ${JSON.stringify(tasks)};\nwindow.verifyDictionaryRuntime = ${verifyDictionaryRuntime.toString()};\n`);
}
console.log('PASS: structural dictionary comparison, legacy opt-out and five task definitions with existing output tests');
