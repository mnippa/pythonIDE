const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const { loadPyodide } = require('../public/pyodide/pyodide.js');

const root = path.resolve(__dirname, '..');
const assignments = fs.readFileSync(path.join(root, 'public', 'js', 'assignments.js'), 'utf8');
const detective = fs.readFileSync(path.join(root, 'public', 'js', 'solution-detective.js'), 'utf8');
const names = [
  'migrateLegacyTestCases', 'runOutputTests', 'runFunctionTests', 'runVariableTests',
  'runIntelligentTests', 'runCodeCheck', 'removePythonComments', 'compareVariableValue',
  'compareValues', 'compareTestOutput', 'matchesPattern', 'patternToRegex'
];
function functionSource(name) {
  const start = assignments.search(new RegExp(`^(?:async )?function ${name}\\(`, 'm'));
  assert.notEqual(start, -1, name);
  const next = assignments.slice(start + 1).search(/^(?:async )?function \w+\(/m);
  return assignments.slice(start, next < 0 ? undefined : start + 1 + next);
}
const quiet = { log() {}, warn() {}, error() {} };
const sandbox = { console: quiet };
vm.createContext(sandbox);
vm.runInContext(detective.replace(/^export /gm, ''), sandbox);
for (const name of names) vm.runInContext(functionSource(name), sandbox);
let pyodide;
async function diagnose(code, specs, extra = {}) {
  pyodide ||= await loadPyodide({
    indexURL: path.join(root, 'public', 'pyodide') + path.sep,
    stdout() {}, stderr() {}
  });
  let response;
  sandbox.importScripts = () => {};
  sandbox.loadPyodide = async () => pyodide;
  sandbox.self = { postMessage: value => { response = JSON.parse(JSON.stringify(value)); } };
  await sandbox.detectiveWorker({
    data: {
      code, indexURL: '',
      task: { id: 1, task_type: 'code', test_cases: JSON.stringify(specs), ...extra }
    }
  });
  return response;
}

const assignment49Tasks = [
  {
    title: '#1 EVA: Wie heisst Du?',
    testCases: { mode: 'vars', tests: 5, inputs: ['name'], outputs: ['antwort'] },
    solution: '#INIT START\nname = input("Wie heisst du? ")\n#INIT END\n\nantwort = "Hallo " + name\nprint(antwort)',
    randomizer: 'values = {"INPUT_01": "Anna", "name": "Anna"}'
  },
  {
    title: '#10 Rechnung I',
    testCases: { mode: 'vars', tests: 6, inputs: ['anzahl_positionen'], outputs: ['summe'] },
    solution: 'anzahl_positionen = int(input("Anzahl Positionen: "))\n\nsumme = 0.0\nfor i in range(anzahl_positionen):\n    betrag = float(input(f"Betrag {i + 1}: "))\n    summe = summe + betrag\n\nprint(summe)',
    randomizer: 'values = {"INPUT_01": 3, "INPUT_02": 10.0, "INPUT_03": 4.5, "INPUT_04": 2.25, "INPUT_05": 100.0, "anzahl_positionen": 3}'
  },
  {
    title: '#11 Rechnung II',
    testCases: [
      { type: 'intelligent', mode: 'vars', tests: 6, inputs: ['INPUT_01', 'INPUT_02', 'INPUT_03', 'INPUT_04'], outputs: ['summe'] },
      { type: 'code_check', keywords: ['input', 'while', 'print'], operator: 'AND', feedback: 'Bitte Inputs und while-Schleife verwenden.' }
    ],
    solution: 'summe = 0.0\nwert = input("Betrag (leer = Ende): ")\nwhile wert != "":\n    summe = summe + float(wert)\n    wert = input("Betrag (leer = Ende): ")\nprint(summe)',
    randomizer: 'values = {"INPUT_01": 5.5, "INPUT_02": 3.25, "INPUT_03": 2.0, "INPUT_04": ""}'
  },
  {
    title: '#12 Rechnung III',
    testCases: [
      { type: 'intelligent', mode: 'vars', tests: 5, inputs: ['INPUT_01', 'INPUT_02', 'INPUT_03', 'INPUT_04', 'INPUT_05', 'INPUT_06', 'INPUT_07', 'INPUT_08', 'INPUT_09'], outputs: ['rechnung', 'gesamt_summe'], value_comparison: 'structural' },
      { type: 'code_check', keywords: ['print', 'while', 'gesamt_summe'], operator: 'AND', feedback: '' }
    ],
    solution: 'rechnung = []\ngesamt_summe = 0.0\npos = 1\n\nname = input("Name der Position (leer = Ende): ")\nwhile name != "":\n    art = input("Art (Getraenk/Speise): ")\n    anzahl = int(input("Anzahl: "))\n    einzelpreis = float(input("Einzelpreis: "))\n    positionssumme = anzahl * einzelpreis\n    eintrag = {"position": pos, "name": name, "art": art, "anzahl": anzahl, "einzelpreis": einzelpreis, "positionssumme": positionssumme}\n    rechnung.append(eintrag)\n    gesamt_summe = gesamt_summe + positionssumme\n    pos = pos + 1\n    name = input("Name der Position (leer = Ende): ")\nprint(rechnung)\nprint(gesamt_summe)',
    randomizer: 'values = {"INPUT_01": "Pizza", "INPUT_02": "Speise", "INPUT_03": 2, "INPUT_04": 10.0, "INPUT_05": "Tee", "INPUT_06": "Getraenk", "INPUT_07": 3, "INPUT_08": 2.0, "INPUT_09": ""}'
  }
];

const assignment49Task580 = {
  testCases: [
    { type: 'intelligent', mode: 'vars', tests: 8, inputs: ['zufalls_index', 'versuch1', 'versuch2', 'versuch3'], outputs: ['antwort'] },
    { type: 'code_check', keywords: ['for', 'while'], operator: 'NOT', feedback: 'Bitte keine Schleife' },
    { type: 'code_check', keywords: ['if', 'elif', 'else'], operator: 'AND', feedback: 'Bitte auch else verwenden' }
  ],
  solution: `#INIT START
passwoerter = ["apfel", "banane", "sonne", "katze", "hallo"]
zufalls_index = int(input("Zufallszahl 0-4: "))
ziel_passwort = passwoerter[zufalls_index]
#INIT END

versuch1 = input("1. Versuch: ")
versuch2 = input("2. Versuch: ")
versuch3 = input("3. Versuch: ")

if versuch1 == ziel_passwort:
    antwort = "Du hast gewonnen"
elif versuch2 == ziel_passwort:
    antwort = "Du hast gewonnen"
elif versuch3 == ziel_passwort:
    antwort = "Du hast gewonnen"
else:
    antwort = "Du hast verloren"
print(antwort)`,
  randomizer: `import random
passwoerter = ["apfel", "banane", "sonne", "katze", "hallo"]
idx = random.randint(0, 4)
ziel = passwoerter[idx]
modus = random.choice(["win1", "win2", "win3", "lose"])
falsch = [p for p in passwoerter if p != ziel]

if modus == "win1":
    v1, v2, v3 = ziel, random.choice(falsch), random.choice(falsch)
elif modus == "win2":
    v1, v2, v3 = random.choice(falsch), ziel, random.choice(falsch)
elif modus == "win3":
    v1, v2, v3 = random.choice(falsch), random.choice(falsch), ziel
else:
    v1, v2, v3 = random.choice(falsch), random.choice(falsch), random.choice(falsch)

values = {
    "INPUT_01": idx, "INPUT_02": v1, "INPUT_03": v2, "INPUT_04": v3,
    "zufalls_index": idx,
    "versuch1": v1, "versuch2": v2, "versuch3": v3
}`
};

async function diagnoseAssignment49Task(taskIndex, code) {
  const task = assignment49Tasks[taskIndex];
  const specs = Array.isArray(task.testCases)
    ? task.testCases : [{ type: 'intelligent', ...task.testCases }];
  return diagnose(code, specs, { solution_code: task.solution, randomizer_code: task.randomizer });
}

test('only supported code tasks expose a detective', () => {
  assert.equal(sandbox.detectiveSupported({ task_type: 'code', test_cases: '[]' }), true);
  for (const task of [
    { task_type: 'code_ui', test_cases: '[]' },
    { task_type: 'code', test_cases: null },
    { task_type: 'code', test_cases: '[]', folderstructure: '1' },
    { task_type: 'code_random_complex', test_cases: '[]' }
  ]) assert.equal(sandbox.detectiveSupported(task), false);
});

test('assignment 49 reference solutions pass their existing functional and structural checks', async () => {
  pyodide ||= await loadPyodide({
    indexURL: path.join(root, 'public', 'pyodide') + path.sep,
    stdout() {}, stderr() {}
  });
  for (const task of assignment49Tasks) {
    const specs = Array.isArray(task.testCases)
      ? task.testCases : [{ type: 'intelligent', ...task.testCases }];
    const intelligent = specs.filter(spec => spec.type === 'intelligent');
    const codeChecks = specs.filter(spec => spec.type === 'code_check');
    const results = await sandbox.runIntelligentTests(
      pyodide, task.solution, intelligent, task.solution, task.randomizer
    );
    assert.ok(results.length > 0 && results.every(result => result.passed), `${task.title}: ${JSON.stringify(results)}`);
    assert.ok(sandbox.runCodeCheck(task.solution, codeChecks).every(result => result.passed), `${task.title}: code_check`);
  }
});

test('task 577 explains an empty output variable and a similar populated typo', async () => {
  const code = [
    'spalten = ["A", "B", "C", "D", "E", "F", "G", "H"]',
    'reihen = [8, 7, 6, 5, 4, 3, 2, 1]',
    'for reihe in reihen:',
    '    zeile_text = ""',
    '    for spalte in spalten:',
    '        feld = spalte + str(reihe)',
    '        ziele_text = zeile_text + feld+" "',
    '    print(zeile_text)'
  ].join('\n');
  const expectedBoard = reihen => reihen.map(reihe =>
    ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'].map(spalte => `${spalte}${reihe}`).join(' ')
  ).join('\n');
  const expected = expectedBoard([8, 7, 6, 5, 4, 3, 2, 1]);
  const specs = [
    { type: 'code_check', keywords: ['for\\s+'], operator: 'AND', feedback: 'Verwende mindestens eine for-Schleife (hier sollen es zwei verschachtelte sein).' },
    { type: 'output', expected_type: 'exact', value: expected, feedback: 'Die Ausgabe muss exakt die Felder von A8 bis H1 zeilenweise enthalten.', expected: [expected] }
  ];
  pyodide ||= await loadPyodide({
    indexURL: path.join(root, 'public', 'pyodide') + path.sep,
    stdout() {}, stderr() {}
  });
  const outputResult = await sandbox.runOutputTests(pyodide, code, [specs[1]]);
  assert.equal(outputResult[0].output.trim(), '');
  assert.deepEqual(Array.from(outputResult[0].expected), [expected]);
  const result = await diagnose(code, specs);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Die Ausgabe ist leer.*ausgegebene Variable „zeile_text“ bleibt leer.*ähnlich lautende Variable „ziele_text“.*Tippfehler/);

  const unrelatedName = await diagnose(code.replace('ziele_text', 'berechneter_text'), specs);
  assert.equal(unrelatedName.ok, true, unrelatedName.error);
  assert.match(unrelatedName.report.messages.join(' '), /Ausgabe ist leer/);
  assert.doesNotMatch(unrelatedName.report.messages.join(' '), /ähnlich lautende Variable|Tippfehler/);
});

test('task 580 intelligent checks validate each guess position and losing all three attempts', async () => {
  pyodide ||= await loadPyodide({
    indexURL: path.join(root, 'public', 'pyodide') + path.sep,
    stdout() {}, stderr() {}
  });
  const scenarios = [
    { INPUT_01: 0, INPUT_02: 'apfel', INPUT_03: 'banane', INPUT_04: 'katze', zufalls_index: 0, versuch1: 'apfel', versuch2: 'banane', versuch3: 'katze' },
    { INPUT_01: 0, INPUT_02: 'banane', INPUT_03: 'apfel', INPUT_04: 'katze', zufalls_index: 0, versuch1: 'banane', versuch2: 'apfel', versuch3: 'katze' },
    { INPUT_01: 0, INPUT_02: 'banane', INPUT_03: 'katze', INPUT_04: 'apfel', zufalls_index: 0, versuch1: 'banane', versuch2: 'katze', versuch3: 'apfel' },
    { INPUT_01: 0, INPUT_02: 'banane', INPUT_03: 'katze', INPUT_04: 'hallo', zufalls_index: 0, versuch1: 'banane', versuch2: 'katze', versuch3: 'hallo' },
    { INPUT_01: 1, INPUT_02: 'banane', INPUT_03: 'sonne', INPUT_04: 'katze', zufalls_index: 1, versuch1: 'banane', versuch2: 'sonne', versuch3: 'katze' },
    { INPUT_01: 2, INPUT_02: 'sonne', INPUT_03: 'apfel', INPUT_04: 'banane', zufalls_index: 2, versuch1: 'sonne', versuch2: 'apfel', versuch3: 'banane' },
    { INPUT_01: 3, INPUT_02: 'katze', INPUT_03: 'hallo', INPUT_04: 'sonne', zufalls_index: 3, versuch1: 'katze', versuch2: 'hallo', versuch3: 'sonne' },
    { INPUT_01: 4, INPUT_02: 'hallo', INPUT_03: 'katze', INPUT_04: 'sonne', zufalls_index: 4, versuch1: 'hallo', versuch2: 'katze', versuch3: 'sonne' }
  ];
  const intelligentTests = assignment49Task580.testCases.filter(spec => spec.type === 'intelligent');
  const solutionResults = await sandbox.runIntelligentTests(
    pyodide, assignment49Task580.solution, intelligentTests,
    assignment49Task580.solution, assignment49Task580.randomizer, structuredClone(scenarios)
  );
  assert.equal(solutionResults.length, 8);
  assert.ok(solutionResults.every(result => result.passed), JSON.stringify(solutionResults));
  assert.ok(sandbox.runCodeCheck(
    assignment49Task580.solution,
    assignment49Task580.testCases.filter(spec => spec.type === 'code_check')
  ).every(result => result.passed));

  const randomizedResults = await sandbox.runIntelligentTests(
    pyodide, assignment49Task580.solution, intelligentTests,
    assignment49Task580.solution, assignment49Task580.randomizer
  );
  assert.equal(randomizedResults.length, 8);
  assert.ok(randomizedResults.every(result => result.passed), JSON.stringify(randomizedResults));

  const onlyChecksFirstAttempt = `#INIT START
ziel_passwort = "apfel"
#INIT END
if input("1. Versuch: ") == ziel_passwort:
    antwort = "Du hast gewonnen"
else:
    antwort = "Du hast verloren"
print(antwort)`;
  const incorrectResults = await sandbox.runIntelligentTests(
    pyodide, onlyChecksFirstAttempt, intelligentTests,
    assignment49Task580.solution, assignment49Task580.randomizer, structuredClone(scenarios)
  );
  assert.ok(incorrectResults.some(result => !result.passed), JSON.stringify(incorrectResults));
});

test('task 580 detective identifies missing input calls from intelligent test failures', async () => {
  const result = await diagnose(
    'antwort = "Du hast verloren"\nprint(antwort)',
    assignment49Task580.testCases,
    {
      solution_code: assignment49Task580.solution,
      randomizer_code: assignment49Task580.randomizer
    }
  );
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /soll Benutzereingaben einlesen.*keinen Aufruf von input\(\)/);
});

test('assignment 49 #1 identifies a wrong greeting result with the actual test value', async () => {
  const result = await diagnoseAssignment49Task(0, '#INIT START\nname = input("Name: ")\n#INIT END\nantwort = name\nprint(antwort)');
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /antwort: erhalten "Anna", erwartet "Hallo Anna".*Textanfang "Hallo " fehlt/);
  assert.doesNotMatch(text, /ruft input\(\) nicht auf/);
  assert.doesNotMatch(text, /Prüfe die Berechnung|Codekopie/);
});

test('task 573 unchanged live template reports empty output and result without revealing the solution', async () => {
  const template = '#INIT START\nname = "" #EINGABE HIER\n#INIT END\n\nantwort = "" # hier die lösung\n#Ausgabe antwort\nprint(antwort)';
  const result = await diagnose(template, [{ type: 'intelligent', mode: 'vars', tests: 5, inputs: ['name'], outputs: ['antwort'] }], {
    solution_code: assignment49Tasks[0].solution,
    randomizer_code: 'import random\nnamen = ["Anna", "Ben", "Cem", "Dina", "Emil", "Farah"]\nname = random.choice(namen)\nvalues = {"INPUT_01": name, "name": name}'
  });
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Die Ausgabe ist leer/);
  assert.match(text, /Die Prüfvariable „antwort“ ist leer/);
  assert.match(text, /Das Programm soll Benutzereingaben einlesen.*keinen Aufruf von input\(\)/);
  assert.doesNotMatch(text, /Hallo|erhalten|erwartet|Prüfeingaben|INPUT_01|Schlüsselwort|Codevorgabe|Der Test/);
});

test('INIT variable injection does not require an input call', async () => {
  const result = await diagnoseAssignment49Task(0, '#INIT START\nname = ""\n#INIT END\nantwort = "Hallo " + name\nprint(antwort)');
  assert.equal(result.ok, true, result.error);
  assert.doesNotMatch(result.report.messages.join(' '), /input\(\)|Ausgabe ist leer|Prüfvariable.*leer/);
});

test('empty result and missing code requirements remain separate observations', async () => {
  const result = await diagnose('antwort = ""\nprint(antwort)', [
    { type: 'variable', expected_vars: { antwort: 'Hallo Cem' } },
    { type: 'output', expected: ['Hallo Cem'] },
    { type: 'code_check', keywords: ['input', '\\+'], operator: 'AND' }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.deepEqual(result.report.messages, [
    'Die Ausgabe ist leer.',
    'Die Prüfvariable „antwort“ ist leer.',
    'Die Schlüsselwortprüfung legt nahe, dass geforderte Codeelemente fehlen. Gleiche deinen Code mit den Vorgaben in der Beschreibung ab.'
  ]);
});

test('an empty result does not imply an empty output when something else is printed', async () => {
  const result = await diagnoseAssignment49Task(0, '#INIT START\nname = ""\n#INIT END\nantwort = ""\nprint("Fertig")');
  assert.equal(result.ok, true, result.error);
  assert.deepEqual(result.report.messages, [
    'Das Programm soll Benutzereingaben einlesen. Dein Code enthält bisher keinen Aufruf von input().',
    'Die Prüfvariable „antwort“ ist leer.'
  ]);
});

test('missing print is observed independently of the wrong nonempty result', async () => {
  const result = await diagnoseAssignment49Task(0, '#INIT START\nname = ""\n#INIT END\nantwort = name');
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /^Die Ausgabe ist leer/);
  assert.match(text, /antwort: erhalten "Anna", erwartet "Hallo Anna"/);
  assert.doesNotMatch(text, /Prüfvariable.*leer/);
});

test('a silent reference solution does not create an output requirement', async () => {
  const result = await diagnose('antwort = ""', [{
    type: 'intelligent', mode: 'vars', tests: 2, inputs: ['name'], outputs: ['antwort']
  }], { solution_code: 'antwort = "Hallo"', randomizer_code: 'values = {"name": "Cem"}' });
  assert.equal(result.ok, true, result.error);
  assert.deepEqual(result.report.messages, ['Die Prüfvariable „antwort“ ist leer.']);
});

test('runtime errors do not become an empty-output or empty-result diagnosis', async () => {
  const result = await diagnoseAssignment49Task(0, '#INIT START\nname = ""\n#INIT END\nantwort = ""\nprint(unbekannt)');
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Python kennt die Variable „unbekannt“/);
  assert.doesNotMatch(text, /Ausgabe ist leer|Prüfvariable.*leer|erwartet "Hallo/);
});

test('empty text results do not hide other wrong results or classify zero and empty lists as empty text', async () => {
  const result = await diagnose('antwort = ""\nsumme = 0\nliste = []', [{
    type: 'variable', expected_vars: { antwort: 'Hallo Cem', summe: 4, liste: [1] }
  }]);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Die Prüfvariable „antwort“ ist leer/);
  assert.match(text, /summe: erhalten 0, erwartet 4/);
  assert.doesNotMatch(text, /Hallo|Prüfeingaben|„summe“ ist leer|„liste“ ist leer/);
});

test('an empty intelligent function return is not confused with an empty variable or output', async () => {
  const result = await diagnose('def begruessen(name):\n    return ""', [{
    type: 'intelligent', mode: 'function', tests: 2, function: { name: 'begruessen', params: ['name'] }
  }], {
    solution_code: 'def begruessen(name):\n    return "Hallo " + name',
    randomizer_code: 'values = {"name": "Cem"}'
  });
  assert.equal(result.ok, true, result.error);
  assert.deepEqual(result.report.messages, ['Der Rückgabewert von „begruessen“ ist leer.']);
});

test('assignment 49 #1 warns when queued input without INIT is never requested', async () => {
  const result = await diagnoseAssignment49Task(0, 'name = ""\nantwort = "Hallo " + name\nprint(antwort)');
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /Das Programm soll Benutzereingaben einlesen.*keinen Aufruf von input\(\)/);
});

test('generated INPUT keys alone do not imply a user-input requirement', async () => {
  const result = await diagnose('antwort = ""', [{
    type: 'intelligent', mode: 'vars', tests: 2, inputs: ['name'], outputs: ['antwort']
  }], {
    solution_code: '#INIT START\nname = ""\n#INIT END\nantwort = "Hallo " + name',
    randomizer_code: 'values = {"INPUT_01": "Cem", "name": "Cem"}'
  });
  assert.equal(result.ok, true, result.error);
  assert.deepEqual(result.report.messages, ['Die Prüfvariable „antwort“ ist leer.']);
});

test('assignment 49 #1 explains an undefined input-variable typo', async () => {
  const result = await diagnoseAssignment49Task(0, '#INIT START\nname = input("Name: ")\n#INIT END\nantwort = "Hallo " + naem\nprint(antwort)');
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /Variable „naem“.*Prüfe die Schreibweise und ob du ihr vor der Verwendung einen Wert zuweist/);
});

test('assignment 49 #10 identifies an incomplete sum and shows the tested inputs', async () => {
  const result = await diagnoseAssignment49Task(1, 'anzahl_positionen = int(input("Anzahl: "))\nsumme = 0.0\nfor _ in range(anzahl_positionen):\n    betrag = float(input("Betrag: "))\n    summe = betrag\nprint(summe)');
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Prüfeingaben/);
  assert.match(text, /summe: erhalten 2\.25, erwartet 16\.75/);
  assert.match(text, /Prüfe die Schritte, mit denen du diesen Wert berechnest/);
  assert.doesNotMatch(text, /jeder Betrag zur laufenden Summe addiert/);
  assert.doesNotMatch(text, /Prüfe die Berechnung|Codekopie/);
});

test('same numeric mismatch receives a cause-neutral prompt for another plausible bug', async () => {
  const result = await diagnoseAssignment49Task(1, 'anzahl_positionen = int(input("Anzahl: "))\nsumme = 0.0\nfor _ in range(anzahl_positionen - 1):\n    betrag = float(input("Betrag: "))\n    summe = summe + betrag\nprint(summe)');
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /summe: erhalten 14\.5, erwartet 16\.75/);
  assert.match(text, /Prüfe die Schritte, mit denen du diesen Wert berechnest/);
  assert.doesNotMatch(text, /jeder Betrag zur laufenden Summe addiert/);
});

test('assignment 49 #10 points out that input text must be converted before arithmetic', async () => {
  const result = await diagnoseAssignment49Task(1, 'anzahl_positionen = int(input("Anzahl: "))\nsumme = 0.0\nfor _ in range(anzahl_positionen):\n    betrag = input("Betrag: ")\n    summe = summe + betrag\nprint(summe)');
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /Text.*Datentypen.*wandle.*um/);
});

test('assignment 49 #11 explains string arithmetic and exhausted input', async () => {
  const stringArithmetic = await diagnoseAssignment49Task(2, 'summe = 0.0\nwert = input("Betrag: ")\nwhile wert != "":\n    summe = summe + wert\n    wert = input("Betrag: ")\nprint(summe)');
  assert.equal(stringArithmetic.ok, true, stringArithmetic.error);
  assert.match(stringArithmetic.report.messages.join(' '), /Text.*Datentypen.*wandle.*um/);
  assert.doesNotMatch(stringArithmetic.report.messages.join(' '), /unsupported operand type/);

  const noSentinel = await diagnoseAssignment49Task(2, 'summe = 0.0\nwhile True:\n    wert = input("Betrag: ")\n    summe = summe + float(wert)\nprint(summe)');
  assert.equal(noSentinel.ok, true, noSentinel.error);
  assert.match(noSentinel.report.messages.join(' '), /leerer Text.*nicht in eine Zahl umgewandelt/);
  assert.doesNotMatch(noSentinel.report.messages.join(' '), /could not convert string to float/);
});

test('assignment 49 #12 identifies omitted invoice append as a missing list entry', async () => {
  const code = assignment49Tasks[3].solution.replace('    rechnung.append(eintrag)\n', '');
  const result = await diagnoseAssignment49Task(3, code);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /rechnung: Die Liste enthält 0 statt 2 Elemente.*jedes erwartete Element zur Liste hinzugefügt/);
  assert.doesNotMatch(text, /Prüfe die Berechnung|Codekopie/);
});

test('assignment 49 #12 identifies a misspelled item key and a missing total update', async () => {
  const wrongKey = assignment49Tasks[3].solution.replaceAll('"einzelpreis"', '"einzel_preis"');
  const keyResult = await diagnoseAssignment49Task(3, wrongKey);
  assert.equal(keyResult.ok, true, keyResult.error);
  assert.match(keyResult.report.messages.join(' '), /Prüfung erwartet den Schlüssel "einzelpreis".*Zusätzlich vorhanden: "einzel_preis"/);

  const wrongTotal = assignment49Tasks[3].solution.replace('gesamt_summe = gesamt_summe + positionssumme', 'gesamt_summe = 0.0');
  const totalResult = await diagnoseAssignment49Task(3, wrongTotal);
  assert.equal(totalResult.ok, true, totalResult.error);
  assert.match(totalResult.report.messages.join(' '), /gesamt_summe: erhalten 0, erwartet 26/);
  assert.match(totalResult.report.messages.join(' '), /Prüfe die Schritte, mit denen du diesen Wert berechnest/);
  assert.doesNotMatch(totalResult.report.messages.join(' '), /jeder Betrag zur laufenden Summe addiert/);
});

test('AND / OR failures report missing requirements; NOT stays distinct', () => {
  const checks = sandbox.runCodeCheck('print(1)\n# forbidden', [
    { type: 'code_check', keywords: ['for', 'while'], operator: 'OR' },
    { type: 'code_check', keywords: ['def', 'return'], operator: 'AND' },
    { type: 'code_check', keywords: ['print'], operator: 'NOT' },
    { type: 'code_check', keywords: ['forbidden'], operator: 'NOT' }
  ]);
  const report = sandbox.summarizeDetective(checks);
  assert.match(report.messages[0], /^Die Schlüsselwortprüfung legt nahe/);
  assert.match(report.messages[1], /^1 Verbotsregel/);
  assert.ok(report.messages.every(text => !text.includes('while') && !text.includes('return')));
});

test('candidate must pass all functional tests without regressing any passed rule', () => {
  const original = [{ type: 'variable', passed: false }, { type: 'code_check', passed: true }];
  assert.equal(sandbox.detectiveRepairPasses(original, [{ type: 'variable', passed: true }, { type: 'code_check', passed: false }]), false);
  assert.equal(sandbox.detectiveRepairPasses(original, [{ type: 'variable', passed: true }, { type: 'code_check', passed: true }]), true);
  assert.equal(sandbox.detectiveRepairPasses([{ type: 'code_check', passed: false }], [{ type: 'code_check', passed: true }]), false);
});

test('actual Python execution confirms a variable typo and keeps source strings intact', async () => {
  const result = await diagnose('gesamt_preis = 42\ntext = "gesamt_preis"\nassert text == "gesamt_preis"', [
    { type: 'variable', expected_var_names: ['gesamtpreis'], test_cases: [{ expected_values: [42] }] }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /erwartet „gesamtpreis“; vorhanden ist „gesamt_preis“/);
});

test('one typo cannot hide a wrong calculation', async () => {
  const result = await diagnose('gesamt_preis = 41', [
    { type: 'variable', expected_var_names: ['gesamtpreis'], test_cases: [{ expected_values: [42] }] }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /erhalten 41, erwartet 42/);
  assert.match(result.report.messages.join(' '), /Nach probeweiser Namenszuordnung/);
  assert.doesNotMatch(result.report.messages.join(' '), /besteht die aktuellen Ergebnistests/);
});

test('ambiguous names are not guessed', async () => {
  const result = await diagnose('summ = 3\nsume = 3', [
    { type: 'variable', expected_var_names: ['summe'], test_cases: [{ expected_values: [3] }] }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.doesNotMatch(result.report.messages.join(' '), /Benennungsfehler:/);
});

test('a different correct algorithm is accepted without source similarity', async () => {
  const result = await diagnose('summe = sum([1, 2, 3])', [
    { type: 'variable', expected_var_names: ['summe'], test_cases: [{ expected_values: [6] }] }
  ], { solution_code: 'summe = 1 + 2 + 3' });
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages[0], /Keine konkrete Abweichung/);
});

test('function name repairs are verified by actual function checks', async () => {
  const result = await diagnose('def verdoppelln(x):\n    return x * 2', [
    { type: 'function', function_name: 'verdoppeln', test_cases: [{ args: [3], expected: '6' }, { args: [4], expected: '8' }] }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /erwartet „verdoppeln“; vorhanden ist „verdoppelln“/);
});

test('task 546: wrong multiplier reports a concrete result, not a blanket judgement', async () => {
  const result = await diagnose(
    'def verdreifachen(zahl):\n    ergebnis = zahl * 2\n    return ergebnis\n\nresultat = verdreifachen(5)\nprint(resultat)',
    [{ type: 'function', function_name: 'verdreifachen', test_cases: [
      { args: [5], expected: 15 }, { args: [7], expected: 21 },
      { args: [0], expected: 0 }, { args: [-3], expected: -9 }
    ] }]
  );
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.doesNotMatch(text, /Ergebnistests bestanden/);
  assert.match(text, /verdreifachen\(5\): erhalten 10, erwartet 15/);
  assert.doesNotMatch(text, /unvollständig|unbrauchbar|vollständige Aufgabenstellung/);
  assert.doesNotMatch(text, /\* 3/);
});

test('task 545: rule-bound name typo is confirmed against all checks', async () => {
  const solution = 'studenten = [["Anna Meyer", 12345, 2.1, 4], ["Ben Schulz", 23456, 1.9, 5], ["Cem Yildiz", 34567, 2.7, 3]]\nzweiter_student = studenten[1]\nprint(zweiter_student)';
  const specs = [
    { type: 'code_check', keywords: ['studenten\\s*=\\s*\\['], operator: 'AND' },
    { type: 'code_check', keywords: ['zweiter_student\\s*=\\s*studenten\\s*\\[\\s*1\\s*\\]'], operator: 'AND' },
    { type: 'output', expected: "['Ben Schulz', 23456, 1.9, 5]" }
  ];
  const code = solution.replaceAll('zweiter_student', 'zwieter_student');
  const result = await diagnose(code, specs, { solution_code: solution });
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /erwartet „zweiter_student“; vorhanden ist „zwieter_student“/);
  assert.doesNotMatch(text, /alle aktuellen Prüfungen/);
  assert.doesNotMatch(text, /Lies die Beschreibung|Deine Ergebnisse bestehen/);

  const wrongAccess = await diagnose(code.replace('studenten[1]', 'studenten[0]'), specs, { solution_code: solution });
  assert.equal(wrongAccess.ok, true, wrongAccess.error);
  assert.doesNotMatch(wrongAccess.report.messages.join(' '), /Benennungsfehler:/);

  const codeOnly = await diagnose(code, specs.slice(0, 2), { solution_code: solution });
  assert.equal(codeOnly.ok, true, codeOnly.error);
  assert.match(codeOnly.report.messages.join(' '), /erwartet „zweiter_student“; vorhanden ist „zwieter_student“/);
});

test('solution names not requested by rules never constrain another correct approach', async () => {
  const result = await diagnose('summe = 3\nprint(summe)', [
    { type: 'code_check', keywords: ['while'], operator: 'AND' },
    { type: 'output', expected: '3' }
  ], { solution_code: 'sume = 3\nprint(sume)' });
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.doesNotMatch(text, /Benennungsfehler:/);
  assert.match(text, /inhaltlich richtig.*nicht sicher/);
});

test('intelligent repairs reuse original generated values, even with an unseeded generator', async () => {
  const result = await diagnose('zahl = 0\ngesamt_preis = 0\n#INIT END\ngesamt_preis = zahl * 2', {
    mode: 'vars', inputs: ['zahl'], outputs: ['gesamtpreis'], tests: 3
  }, {
    solution_code: 'zahl = 0\ngesamtpreis = 0\n#INIT END\ngesamtpreis = zahl * 2',
    randomizer_code: 'import random\nvalues = {"zahl": random.SystemRandom().randint(1, 1000000)}'
  });
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /erwartet „gesamtpreis“; vorhanden ist „gesamt_preis“/);
});

test('simple output reordering and separator repairs are confirmed, not merely suggested', async () => {
  let result = await diagnose('a = 1\nb = 2\nprint(b)\nprint(a)', [{ type: 'output', expected: '1\n2', validation_mode: 'strict' }]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /Ausgabereihenfolge/);
  result = await diagnose('print(1, 2, sep=";")', [{ type: 'output', expected: '1 2', validation_mode: 'strict' }]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /sep\/end/);
  result = await diagnose('print(1, 3, sep=";")', [{ type: 'output', expected: '1 2' }]);
  assert.equal(result.ok, true, result.error);
  assert.doesNotMatch(result.report.messages.join(' '), /Codekopie/);
});

test('output candidate edits preserve INIT markers, comments and Unicode source positions', async () => {
  const code = '#INIT START\ntext = "Größe"\n#INIT END\nprint("Höhe", 2, sep=";") # behalten';
  const candidates = JSON.parse(await pyodide.runPythonAsync(sandbox.detectiveOutputCandidates(code)));
  assert.ok(candidates.length > 0);
  for (const candidate of candidates) {
    assert.ok(candidate.code.includes('#INIT START'));
    assert.ok(candidate.code.includes('#INIT END'));
    assert.ok(candidate.code.includes('# behalten'));
    assert.ok(candidate.code.includes('"Höhe"'));
  }
});

test('repairs cannot invalidate a previously passed keyword requirement', async () => {
  const result = await diagnose('print(1, 2, sep=";")', [
    { type: 'output', expected: '1 2', validation_mode: 'strict' },
    { type: 'code_check', keywords: [';'], operator: 'AND' }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.doesNotMatch(result.report.messages.join(' '), /Codekopie/);
});

test('a default intelligent check and explicit frozen inputs yield identical grading', async () => {
  const cases = [{ type: 'intelligent', mode: 'function', function: { name: 'doppelt', params: ['x'] }, tests: 2 }];
  const code = 'def doppelt(x):\n    return x * 2';
  const randomizer = 'values = {"x": 7}';
  const regular = await sandbox.runIntelligentTests(pyodide, code, cases, code, randomizer);
  const fixed = await sandbox.runIntelligentTests(pyodide, code, cases, code, randomizer, [{ x: 7 }, { x: 7 }]);
  assert.deepEqual(JSON.parse(JSON.stringify(regular)), JSON.parse(JSON.stringify(fixed)));
  assert.ok(regular.every(result => result.passed));
});

test('technical configuration / syntax errors return no chargeable report', async () => {
  for (const [code, specs] of [
    ['if', [{ type: 'output', expected: '1' }]],
    ['x = 1', { mode: 'vars', inputs: ['x'], outputs: ['y'], tests: 1 }],
    ['print(1)', [{ type: 'unsupported' }]],
    ['print(1)', [{ type: 'output', expected_type: 'regex', expected: '[' }]],
    ['print(99)', [{ type: 'output', expected_type: 'regex', pattern: '^[1-6]$' }]],
    ['from js import window\nprint(1)', [{ type: 'output', expected: '1' }]]
  ]) {
    const result = await diagnose(code, specs);
    assert.equal(result.ok, false);
    assert.equal(result.report, undefined);
  }
});

test('function checks distinguish no function, another name, and a possible typo', async () => {
  const specs = [{ type: 'function', function_name: 'verdreifachen', test_cases: [{ args: [5], expected: 15 }] }];
  for (const [code, required, excluded] of [
    ['zahl = 15\nprint(zahl)', /Du musst eine Funktion schreiben/, /Ausführung fehlgeschlagen|not found/],
    ['def berechnen(x):\n    return x * 3', /erwartet „verdreifachen“; vorhanden ist „berechnen“/, /Du musst eine Funktion schreiben|not found/],
    ['def berechnen(x):\n    return x * 2', /Vorhandene Funktionen: „berechnen“/, /Du musst eine Funktion schreiben|not found/],
    ['def verdreifachne(x):\n    return x * 2', /verdreifachen\(5\): erhalten 10, erwartet 15/, /not found|Ergebnistests bestanden/]
  ]) {
    const result = await diagnose(code, specs);
    assert.equal(result.ok, true, result.error);
    assert.match(result.report.messages.join(' '), required);
    assert.doesNotMatch(result.report.messages.join(' '), excluded);
  }
});

test('a renamed function and its call sites are diagnosed without rewriting strings', async () => {
  const result = await diagnose('def rechnen(x):\n    return x * 3\nprint(rechnen(5))\nassert "rechnen" == "rechnen"', [
    { type: 'function', function_name: 'verdreifachen', test_cases: [{ args: [5], expected: 15 }] },
    { type: 'output', expected: '15' }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /vorhanden ist „rechnen“/);
});

test('combined variable typo and output ordering expose both findings', async () => {
  const result = await diagnose('bezeichnung = "Ordner"\nbestandswret = 42\nprint(bestandswret)\nprint(bezeichnung)', [
    { type: 'variable', expected_var_names: ['bezeichnung', 'bestandswert'], test_cases: [{ expected_values: ['Ordner', 42] }] },
    { type: 'output', expected: 'Ordner\n42', validation_mode: 'strict' }
  ]);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /vorhanden ist „bestandswret“/);
  assert.match(text, /Ausgabereihenfolge/);
  assert.match(text, /Nach der probeweisen Namenszuordnung/);
  assert.doesNotMatch(text, /not found|Prüfe die Berechnung|Ergebnistests bestanden/);
});

test('an additional output label is diagnosed independently of a wrong variable value', async () => {
  const result = await diagnose('wert = 41\nprint("Wert:", wert)', [
    { type: 'variable', expected_var_names: ['wert'], test_cases: [{ expected_values: [42] }] },
    { type: 'output', expected: '41' }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /erhalten 41, erwartet 42/);
  assert.match(result.report.messages.join(' '), /zusätzliche Beschriftung/);
});

test('dictionary diagnostics isolate keys, types, and values rather than dumping structures', async () => {
  const specs = [{ type: 'variable', expected_var_names: ['raum1'], test_cases: [
    { expected_values: [{ bezeichnung: 'THD', plaetze: 80, beamer: true }] }
  ] }];
  for (const [code, expected] of [
    ['raum1 = {"bezeichnung":"THD","sitze":80,"beamer":True}', /Schlüssel "plaetze".*Zusätzlich vorhanden: "sitze"/],
    ['raum1 = {"bezeichnung":"THD","plaetze":80,"beamer":"True"}', /raum1\["beamer"\]: Text statt Wahrheitswert/],
    ['raum1 = {"bezeichnung":"THD","plaetze":81,"beamer":True}', /raum1\["plaetze"\]: erhalten 81, erwartet 80/]
  ]) {
    const result = await diagnose(code, specs);
    assert.equal(result.ok, true, result.error);
    const text = result.report.messages.join(' ');
    assert.match(text, expected);
    assert.doesNotMatch(text, /erhalten \{|Prüfe die Berechnung/);
  }
});

test('task 545: a wrong index is diagnosed despite a passing shape-only output test', async () => {
  const result = await diagnose('studenten = [["Anna", 1], ["Ben", 2]]\nzweiter_student = studenten[0]\nprint(zweiter_student)', [
    { type: 'code_check', operator: 'AND', keywords: ['zweiter_student\\s*=\\s*studenten\\[1\\]'],
      feedback: 'Speichere alle Werte des zweiten Studenten als komplette innere Liste in zweiter_student.' },
    { type: 'output', expected_type: 'regex', expected: '^\\[.*\\]$' }
  ], { solution_code: 'studenten = [["Anna", 1], ["Ben", 2]]\nzweiter_student = studenten[1]\nprint(zweiter_student)' });
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Nicht erfüllte Vorgabe: Speichere alle Werte des zweiten Studenten/);
  assert.match(text, /Wertabweichung bei „zweiter_student“/);
  assert.match(text, /zweiter_student\[0\]: erhalten "Anna", erwartet "Ben"/);
  assert.doesNotMatch(text, /verlangte Codevorgabe|inhaltlich richtig.*nicht sicher/);
  assert.doesNotMatch(text, /Ergebniswerte stimmen|Ergebniswerte passen/);
});

test('task 545: third student yields a concrete value diagnosis without changing the check', async () => {
  const solution = 'studenten = [["Anna Meyer", 12345, 2.1, 4], ["Ben Schulz", 23456, 1.9, 5], ["Cem Yildiz", 34567, 2.7, 3]]\nzweiter_student = studenten[1]\nprint(zweiter_student)';
  const specs = [
    { type: 'code_check', operator: 'AND', keywords: ['zweiter_student\\s*=\\s*studenten\\[1\\]'],
      feedback: 'Speichere alle Werte des zweiten Studenten als komplette innere Liste in zweiter_student.' },
    { type: 'output', expected_type: 'regex', expected: '^\\[.*\\]$' }
  ];
  const code = solution.replace('studenten[1]', 'studenten[2]');
  const before = sandbox.runCodeCheck(code, specs);
  const result = await diagnose(code, specs, { solution_code: solution });
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Wertabweichung bei „zweiter_student“/);
  assert.match(text, /erhalten "Cem Yildiz", erwartet "Ben Schulz"/);
  assert.doesNotMatch(text, /verlangte Codevorgabe|lässt sich daraus allein/);
  assert.deepEqual(sandbox.runCodeCheck(code, specs), before);

  const equivalent = await diagnose(solution.replace('studenten[1]', 'studenten[-2]'), specs, { solution_code: solution });
  assert.equal(equivalent.ok, true, equivalent.error);
  assert.match(equivalent.report.messages.join(' '), /Nicht erfüllte Vorgabe/);
  assert.doesNotMatch(equivalent.report.messages.join(' '), /Wertabweichung/);
});

test('concrete rule feedback is used for AND, OR and NOT instead of unexplained rule counts', () => {
  const results = sandbox.runCodeCheck('print(1)', [
    { type: 'code_check', operator: 'AND', keywords: ['def'], feedback: 'Definiere eine Funktion.' },
    { type: 'code_check', operator: 'OR', keywords: ['for', 'while'], feedback: 'Verwende eine Schleife.' },
    { type: 'code_check', operator: 'NOT', keywords: ['print'], feedback: 'Verwende keine print-Ausgabe.' }
  ]);
  const text = sandbox.summarizeDetective(results).messages.join(' ');
  assert.match(text, /Nicht erfüllte Vorgabe: Definiere eine Funktion/);
  assert.match(text, /Nicht erfüllte Vorgabe: Verwende eine Schleife/);
  assert.match(text, /Nicht erfüllte Einschränkung: Verwende keine print-Ausgabe/);
  assert.doesNotMatch(text, /verlangte Codevorgabe|Verbotsregel/);
});

test('supplemental value evidence never uses unrelated solution variables or external state', async () => {
  for (const [code, solution, keyword] of [
    ['summe = 3\nprint(summe)', 'intern = 99\nprint(intern)', 'while'],
    ['import random\nwert = random.randint(1, 10)\nprint(wert)', 'import random\nwert = random.randint(1, 10)\nprint(wert)', 'while|wert = 1'],
    ['daten = [1, 2]\nwert = daten.pop()\nprint(wert)', 'daten = [1, 2]\nwert = daten[0]\nprint(wert)', 'wert\\s*=\\s*daten\\[0\\]']
  ]) {
    const result = await diagnose(code, [
      { type: 'code_check', operator: 'AND', keywords: [keyword], feedback: 'Beachte die Zugriffsvorgabe.' },
      { type: 'output', expected_type: 'regex', expected: '.*' }
    ], { solution_code: solution });
    assert.equal(result.ok, true, result.error);
    const text = result.report.messages.join(' ');
    assert.match(text, /Nicht erfüllte Vorgabe: Beachte die Zugriffsvorgabe/);
    assert.doesNotMatch(text, /Wertabweichung|Ergebniswerte stimmen/);
  }
});

test('regex output failures do not expose regular expressions or blame calculations', async () => {
  const result = await diagnose('print("Wert: 42")', [
    { type: 'output', expected_type: 'regex', expected: '^42(?:\\.0+)?$' }
  ]);
  assert.equal(result.ok, true, result.error);
  assert.match(result.report.messages.join(' '), /Ausgabe weicht/);
  assert.doesNotMatch(result.report.messages.join(' '), /\\\.0|Prüfe die Berechnung|Ergebnistests bestanden/);
});

test('type errors do not invent input or a monetary amount', async () => {
  for (const code of ['wert = "3" + 1', 'if False:\n    input()\nwert = "3" + 1']) {
    const result = await diagnose(code, [
      { type: 'variable', expected_vars: { wert: 4 } }
    ]);
    assert.equal(result.ok, true, result.error);
    const text = result.report.messages.join(' ');
    assert.match(text, /Text|Zeichenkette/);
    assert.doesNotMatch(text, /Betrag|eingelesen|input\(\)/i);
  }
  const sequence = await diagnose('wert = [1, 2] * 1.5', [
    { type: 'variable', expected_vars: { wert: [1, 2] } }
  ]);
  assert.equal(sequence.ok, true, sequence.error);
  assert.match(sequence.report.messages.join(' '), /Folge.*Liste.*ganzen Zahl/);
  assert.doesNotMatch(sequence.report.messages.join(' '), /Betrag|eingelesen|input\(\)/i);
});

test('syntax and indentation errors produce concise student diagnostics with line numbers', async () => {
  const cases = [
    ['def verschiebe_rechts(liste):\n    # Implementierung\n\nresult = verschiebe_rechts([])', /Einrückung|eingerückt/, /Zeile 4/],
    ['x = (1 + 2', /Syntax|Klammer/, /Zeile 1/],
    ['if True:\n\tprint(1)\n    print(2)', /Einrückung|Tabulator/, /Zeile 3/],
    ['x = 1\nif x ==\n    print(x)', /Syntax/, /Zeile 2/]
  ];
  for (const [code, explanation, line] of cases) {
    const result = await diagnose(code, [{ type: 'output', expected: '1' }]);
    assert.equal(result.ok, false);
    assert.equal(result.report, undefined);
    const text = result.error;
    assert.match(text, explanation);
    assert.match(text, line);
    assert.doesNotMatch(text, /Traceback|pyodide|\/lib\/python/i);
  }
});

test('randomized mismatches include the actual generated input context', async () => {
  const result = await diagnose('zahl = 0\n#INIT END\nergebnis = zahl + 2', {
    mode: 'vars', inputs: ['zahl'], outputs: ['ergebnis'], tests: 2
  }, {
    solution_code: 'zahl = 0\n#INIT END\nergebnis = zahl * 2',
    randomizer_code: 'values = {"zahl": 7}'
  });
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /Prüfeingaben.*"zahl":7/);
  assert.match(text, /erhalten 9, erwartet 14/);
});

test('several missing names can be diagnosed progressively', async () => {
  const result = await diagnose('gesamt_preis = 42\nanzhal = 2', [
    { type: 'variable', expected_var_names: ['gesamtpreis', 'anzahl'], test_cases: [{ expected_values: [42, 2] }] }
  ]);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /erwartet „gesamtpreis“; vorhanden ist „gesamt_preis“/);
  assert.match(text, /erwartet „anzahl“; vorhanden ist „anzhal“/);
  assert.doesNotMatch(text, /not found/);
});

test('a same-named non-callable value is not mistaken for a function', async () => {
  const result = await diagnose('verdreifachen = 15', [
    { type: 'function', function_name: 'verdreifachen', test_cases: [{ args: [5], expected: 15 }] }
  ]);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /vorhanden, aber nicht als Funktion/);
  assert.match(text, /Du musst eine Funktion schreiben/);
  assert.doesNotMatch(text, /not callable/);
});

test('callable lambda assignments are not reported as missing functions', async () => {
  const result = await diagnose('verdreifachen = lambda x: x * 2', [
    { type: 'function', function_name: 'verdreifachen', test_cases: [{ args: [5], expected: 15 }] }
  ]);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /erhalten 10, erwartet 15/);
  assert.doesNotMatch(text, /Du musst eine Funktion schreiben/);
});

test('legacy automatic function checks also explain when no function exists', async () => {
  const result = await diagnose('zahl = 15', [{ type: 'function', args: [5], expected: 15 }]);
  assert.equal(result.ok, true, result.error);
  const text = result.report.messages.join(' ');
  assert.match(text, /musst du eine Funktion schreiben/);
  assert.doesNotMatch(text, /not found|None/);
});

test('intelligent function checks distinguish names from result errors too', async () => {
  for (const [code, required] of [
    ['zahl = 15', /Du musst eine Funktion schreiben/],
    ['def verdreifachne(x):\n    return x * 2', /erhalten 10, erwartet 15/]
  ]) {
    const result = await diagnose(code, {
      mode: 'function', function: { name: 'verdreifachen', params: ['x'] }, tests: 2
    }, {
      solution_code: 'def verdreifachen(x):\n    return x * 3',
      randomizer_code: 'values = {"x": 5}'
    });
    assert.equal(result.ok, true, result.error);
    assert.match(result.report.messages.join(' '), required);
    assert.doesNotMatch(result.report.messages.join(' '), /not found/);
  }
});
