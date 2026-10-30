const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const root = path.join(__dirname, '..');
const tasks = JSON.parse(execFileSync('php', [
  path.join(__dirname, 'revise_assignment48_dictionary_basics.php'), '--definitions'
], { encoding: 'utf8' }));
const source = fs.readFileSync(path.join(root, 'public', 'js', 'assignments.js'), 'utf8');
const functions = [
  'runIntelligentTests', 'runOutputTests', 'runVariableTests',
  'compareVariableValue', 'compareValues', 'compareTestOutput'
].map(name => {
  const start = source.search(new RegExp(`^(?:async )?function ${name}\\(`, 'm'));
  assert.ok(start >= 0);
  return source.slice(start, source.indexOf('\n}', start) + 2);
}).join('\n\n');
assert.equal(tasks.length, 4);
assert.equal(tasks[0].id, 569);
assert.equal(tasks[1].id, 570);
assert.equal(tasks[1].code_template, '#raum1={}\n#raum2={}\n');
assert.deepEqual(tasks[1].outputs, ['raum1', 'raum2']);
assert.match(tasks[1].description, /<code>True<\/code>/);
assert.match(tasks[1].description, /<code>False<\/code>/);
const orderExample = tasks[3].cases[0].init_values[0];
assert.ok(Array.isArray(orderExample));
assert.equal(typeof orderExample[0], 'string');
assert.equal(orderExample[1].kunde, 'Eva');
assert.ok(Array.isArray(orderExample[2]));
assert.equal(orderExample[2].length, 2);
assert.equal(orderExample[2][1].artikel, 'Y');
for (const task of tasks) {
  assert.doesNotMatch(task.body, /\b(for|while|map|filter)\b/);
  assert.ok(task.body.includes('print('));
}
const hiddenKeys = [
  ['bezeichnung', 'preis', 'bestand'],
  ['bezeichnung', 'plaetze', 'beamer'],
  ['einkauf', 'verkauf', 'leitung', 'telefon']
];
for (const [index, keys] of hiddenKeys.entries()) {
  const task = tasks[index];
  assert.match(task.description, /zunächst ohne Hinweis/);
  assert.match(task.description, /vor dem Check/);
  for (const key of keys) {
    assert.ok(task.hint1.includes(key));
    assert.ok(!task.description.includes(`<code>${key}</code>`), `Schema key exposed: ${key}`);
  }
}

async function verifyDictionaryBasics(pyodide) {
  const summaries = [];
  async function run(task, code) {
    const results = [];
    for (const spec of task.test_cases) {
      if (spec.type === 'intelligent') results.push(...await runIntelligentTests(pyodide, code, [spec], task.solution_code, task.randomizer));
      if (spec.type === 'variable') results.push(...await runVariableTests(pyodide, code, [spec]));
      if (spec.type === 'output') results.push(...await runOutputTests(pyodide, code, [spec], task));
    }
    return results;
  }
  for (const task of window.dictionaryBasicsTasks) {
    pyodide.globals.set('_dictionary_solution', task.solution_code);
    await pyodide.runPythonAsync(`
import ast
_tree = ast.parse(_dictionary_solution)
assert not any(isinstance(n, (ast.For, ast.While, ast.AsyncFor, ast.ListComp, ast.DictComp, ast.SetComp, ast.GeneratorExp)) for n in ast.walk(_tree))
`);
    const results = await run(task, task.solution_code);
    if (!results.length || results.some(result => !result.passed)) throw new Error(JSON.stringify({ title: task.title, results }));
    const noPrint = task.solution_code.split('\n').filter(line => !line.trim().startsWith('print(')).join('\n');
    const outputSpec = task.test_cases.find(spec => spec.type === 'output');
    const noOutput = await runOutputTests(pyodide, noPrint, [outputSpec], task);
    if (noOutput.every(result => result.passed)) throw new Error('Missing output accepted');
    if (task.randomizer) {
      const hardcoded = task.code_template + '\n' + task.body
        .replaceAll('artikeldaten[0]', '"Ordner"').replaceAll('artikeldaten[1]', '3.5').replaceAll('artikeldaten[2]', '12')
        .replaceAll('einkauf_leitung,', '"Mara Wolf",')
        .replaceAll('leitung_verkauf = abteilungen["verkauf"]["leitung"]', 'leitung_verkauf = "Ali Kaya"')
        .replaceAll('kundenname = bestellung[1]["kunde"]', 'kundenname = "Mara Wolf"');
      const fixed = task.test_cases.find(spec => spec.type === 'variable');
      const negatives = await runVariableTests(pyodide, hardcoded, [fixed]);
      if (negatives.every(result => result.passed)) throw new Error('Hardcoded example accepted: ' + task.title);
    }
    summaries.push({ title: task.title, tests: results.length, passed: true, noLoops: true, missingOutputRejected: true });
  }
  const business = window.dictionaryBasicsTasks[1];
  const secondRoom = '\nraum2 = {"plaetze": 20, "beamer": False, "bezeichnung": "T1.3.08"}';
  const roomOutput = '\nprint(raum1["bezeichnung"])\nprint(raum1["plaetze"])\nprint(raum2["bezeichnung"])\nprint(raum2["plaetze"])';
  const alternatives = [
    'raum1 = {"beamer": True, "plaetze": 80, "bezeichnung": "THD"}' + secondRoom + roomOutput,
    'raum1 = {}\nraum1["plaetze"] = 80\nraum1["beamer"] = True\nraum1["bezeichnung"] = "THD"' + secondRoom + roomOutput
  ];
  for (const code of alternatives) {
    if ((await run(business, code)).some(result => !result.passed)) throw new Error('Alternative dictionary construction rejected');
  }
  for (const wrong of [
    'raum1 = {"bezeichnung":"THD", "plaetze":80, "beamer":"True"}' + secondRoom,
    'raum1 = {"bezeichnung":"THD", "plaetze":80}' + secondRoom,
    'raum1 = {"bezeichnung":"THD", "plaetze":80, "beamer":True, "extra":1}' + secondRoom,
    'raum1 = ["THD", 80, True]' + secondRoom,
    business.body.replace('"beamer": False', '"beamer": True'),
    business.body.replace('"beamer": False', '"beamer": "False"'),
    business.body.replace('"beamer": True', '"beamer": False'),
    business.body.split('\n').filter(line => !line.startsWith('raum2 =')).join('\n')
  ]) {
    const results = await runVariableTests(pyodide, wrong, [business.test_cases[0]]);
    if (results.every(result => result.passed)) throw new Error('Invalid structure accepted: ' + wrong);
  }
  const manual = window.dictionaryBasicsTasks[0];
  const reordered = manual.solution_code.replace(
    'artikel = {"bezeichnung": artikeldaten[0], "preis": artikeldaten[1], "bestand": artikeldaten[2]}',
    'artikel = {"bestand": artikeldaten[2], "preis": artikeldaten[1], "bezeichnung": artikeldaten[0]}'
  );
  if ((await run(manual, reordered)).some(result => !result.passed)) throw new Error('Reordered keys rejected');
  const nested = window.dictionaryBasicsTasks[2];
  const reorderedNested = nested.solution_code
    .replace('{"leitung": einkauf_leitung, "telefon": einkauf_telefon}', '{"telefon": einkauf_telefon, "leitung": einkauf_leitung}')
    .replace('{"leitung": verkauf_leitung, "telefon": verkauf_telefon}', '{"telefon": verkauf_telefon, "leitung": verkauf_leitung}');
  if ((await run(nested, reorderedNested)).some(result => !result.passed)) throw new Error('Nested key order rejected');
  const access = window.dictionaryBasicsTasks[3];
  const wrongIndex = access.solution_code.replace(
    'zweiter_artikel = bestellung[2][1]["artikel"]',
    'zweiter_artikel = bestellung[2][0]["artikel"]'
  );
  const fixed = access.test_cases.find(spec => spec.type === 'variable');
  if ((await runVariableTests(pyodide, wrongIndex, [fixed])).every(result => result.passed)) throw new Error('Wrong list index accepted');
  const mutatedInput = access.solution_code + '\nbestellung[0] = "geaendert"';
  if ((await runVariableTests(pyodide, mutatedInput, [fixed])).every(result => result.passed)) throw new Error('Changed input accepted');
  return { status: 'PASS', tasks: summaries, alternativeConstructions: true, incorrectStructuresRejected: true };
}

const index = process.argv.indexOf('--browser-bundle');
if (index >= 0) {
  fs.writeFileSync(process.argv[index + 1], `${functions}\nwindow.dictionaryBasicsTasks=${JSON.stringify(tasks)};\nwindow.verifyDictionaryBasics=${verifyDictionaryBasics.toString()};\n`);
}
console.log('PASS: four loop-free task definitions and existing runtime functions');
