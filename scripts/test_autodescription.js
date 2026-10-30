const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'admin-dashboard.js'), 'utf8');
const functions = ['escapeHtml', 'generateAutoDescription'].map(name => {
  const start = source.indexOf(`function ${name}(`);
  assert.ok(start >= 0, `Missing ${name}`);
  return source.slice(start, source.indexOf('\n}', start) + 2);
}).join('\n');

function generate(tests, { richText = false, manualReview = false, fieldId = 'task-description' } = {}) {
  const field = { value: '<p>Eigene Beschreibung</p>' };
  let content = field.value;
  const alerts = [];
  const context = {
    document: {
      getElementById: id => id === fieldId ? field : { checked: manualReview }
    },
    tinymce: {
      get: () => richText ? {
        getContent: () => content,
        setContent: value => { content = value; }
      } : null
    },
    alert: message => alerts.push(message)
  };
  vm.createContext(context);
  vm.runInContext(functions, context);
  context.generateAutoDescription(tests, fieldId);
  return { html: richText ? content : field.value, alerts };
}

const intelligent = { type: 'intelligent', mode: 'vars', inputs: ['artikel'], outputs: ['bestand'], tests: 8 };
const variable = { type: 'variable', init_var_names: ['artikel'], expected_var_names: ['bestand'] };
const output = { type: 'output', expected_type: 'regex' };
const count = (html, text) => html.split(text).length - 1;
for (const tests of [[intelligent, variable, output], [variable, intelligent, output]]) {
  for (const richText of [false, true]) {
    const { html } = generate(tests, { richText, fieldId: richText ? 'edit-task-description' : 'task-description' });
    assert.ok(html.startsWith('<p>Eigene Beschreibung</p>'));
    assert.equal(count(html, '<td>Input-Variablen</td>'), 1);
    assert.equal(count(html, '<td>Checking</td>'), 1);
    assert.equal(count(html, '<td>INPUTS erwartet</td>'), 1);
    assert.equal(count(html, '<td>OUTPUT</td>'), 1);
    assert.ok(html.includes('Regex Pattern'));
  }
}
const different = generate([intelligent, { ...variable, expected_var_names: ['preis'] }]).html;
assert.equal(count(different, '<td>Checking</td>'), 2);
assert.ok(different.includes('<td>preis</td>'));
const functionsHtml = generate([
  { type: 'function', function_name: 'summe', test_cases: [{ args: [1, 2] }] },
  { type: 'intelligent', mode: 'function', function: { name: 'summe', params: ['a', 'b'] } }
]).html;
assert.equal(count(functionsHtml, '<td>Funktionsname</td>'), 1);
assert.equal(count(functionsHtml, '<td>Parameter</td>'), 1);
assert.ok(generate([], { manualReview: true }).html.includes('manuell durch Admin'));
assert.equal(generate([]).html, '<p>Eigene Beschreibung</p>');
assert.ok(generate([{ ...variable, init_var_names: ['<eingabe>'] }]).html.includes('&lt;eingabe&gt;'));

const tasks = JSON.parse(execFileSync('php', [
  path.join(__dirname, 'create_assignment48_dictionary_tasks.php'), '--definitions'
], { encoding: 'utf8' }));
for (const task of tasks.slice(3)) {
  const generated = generate(task.test_cases).html.replace('<p>Eigene Beschreibung</p>', '');
  assert.ok(task.description.endsWith(generated), `AutoDescr table mismatch: ${task.title}`);
}

const assignments = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'assignments.js'), 'utf8');
assert.equal(assignments.includes('buildAutoValidationSummary'), false);
assert.equal(assignments.includes('task-auto-validation'), false);
assert.ok(assignments.includes('${task.description}'));
console.log('PASS: AutoDescr deduplication, distinct requirements, both editors, manual review and removed runtime summary');
