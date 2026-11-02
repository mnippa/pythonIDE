const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'admin-dashboard.js'), 'utf8');
const elements = new Map();
const requests = [];
const alerts = [];
function element(id) {
  if (!elements.has(id)) {
    elements.set(id, {
      value: '', checked: false, textContent: '', disabled: false, style: {},
      files: [], classList: { add() {}, remove() {} }, reset() {}, focus() {},
      querySelector() { return null; }, querySelectorAll() { return []; }
    });
  }
  return elements.get(id);
}
const context = vm.createContext({
  console, Date, FormData, Blob,
  document: { getElementById: element, querySelectorAll: () => [] },
  sessionStorage: { getItem: () => 'all' },
  state: { assignments: [{ id: 1, locked: false }], tasks: [], currentAssignmentId: 1 },
  alert: message => alerts.push(message),
  window: {}, tinymce: { get: () => null },
  mapTaskTypeToProblemType: () => undefined,
  taskTypeSupportsTestCases: () => false,
  loadAssignments: async () => {}, loadAssignmentSemesters: async () => {},
  loadTasks: async () => {},
  requestJson: async (url, options) => {
    requests.push({ url, body: JSON.parse(options.body) });
    return { ok: true, task_count: 2, semester: 'SS27' };
  }
});
function loadFunction(name) {
  const start = source.search(new RegExp(`(?:async )?function ${name.replace('$', '\\$')}\\(`));
  assert.ok(start >= 0, `Missing function ${name}`);
  const end = source.indexOf('\n}', start) + 2;
  vm.runInContext(source.slice(start, end), context);
}
for (const name of [
  '$', 'getSemesterForDate', 'getAssignmentById', 'isAssignmentLockedById',
  'isCurrentAssignmentLocked', 'isTaskEffectivelyLocked', 'updateTasksLockUi',
  'openCloneAssignmentModal', 'closeCloneAssignmentModal', 'handleCloneAssignmentSubmit',
  'handleEditTaskSubmit', 'applyDeltaPackageUi', 'setDeltaPackageStatus',
  'setDeltaPackageOutput', 'runDeltaPackage', 'setDeltaCompareBusy', 'setDeltaCompareStatus',
  'runDeltaCompare', 'formatDeltaCompareOutput',
  'runCreateManifest', 'bindDeltaPackageEvents'
]) loadFunction(name);
const configStart = source.indexOf('const ALLOWED_FILE_SUBMISSION_MAX_SIZES');
const configEnd = source.indexOf('function inferTestCaseType', configStart);
vm.runInContext(source.slice(configStart, configEnd), context);

async function main() {
  context.openCloneAssignmentModal({ id: 1, title: 'Source', semester: 'SS26' });
  assert.equal(element('clone-assignment-semester').value, 'SS26');
  element('clone-assignment-semester').value = 'ss27';
  await context.handleCloneAssignmentSubmit({ preventDefault() {} });
  assert.deepEqual(requests.pop(), {
    url: '../api/admin/assignments/clone.php', body: { id: 1, semester: 'SS27' }
  });

  const originalTemplate = { version: 1, custom_setting: 'preserve', type_config: { simple_types: ['String'] } };
  context.state.tasks = [{ id: 7, locked: false, uml_template: originalTemplate }];
  element('edit-task-id').value = '7';
  element('edit-task-title').value = 'UML fixture';
  element('edit-task-type').value = 'uml';
  element('edit-task-text').value = 'Modellieren Sie den Zusammenhang.';
  const form = element('task-edit-form');
  const primitiveTypesInput = { value: 'String; Integer' };
  const groups = [
    { group: 'primitive', checked: true },
    { group: 'complex', checked: false },
    { group: 'enumeration', checked: true }
  ].map(item => ({ ...item, getAttribute: () => item.group }));
  form.querySelector = selector => selector === '[data-uml-string-length]' ? { value: '45' }
    : selector === '[data-uml-starter-model]' ? { value: '{"nodes":[],"relationships":[]}' }
      : selector === '[data-uml-primitive-types]' ? primitiveTypesInput : null;
  form.querySelectorAll = selector => selector === '[data-uml-type-group]' ? groups : [];
  await context.handleEditTaskSubmit({ preventDefault() {} });
  const saved = requests.pop();
  assert.equal(saved.url, '../api/tasks/update.php');
  assert.equal(saved.body.uml_template.custom_setting, 'preserve');
  assert.equal(saved.body.uml_template.type_config.string_length, 45);
  assert.deepEqual(saved.body.uml_template.type_config.simple_types, ['String', 'Integer']);
  assert.equal(saved.body.uml_template.type_config.primitive_types_enabled, true);
  assert.equal(saved.body.uml_template.type_config.complex_types_enabled, false);
  assert.equal(saved.body.uml_template.type_config.enumeration_types_enabled, true);
  assert.deepEqual(saved.body.uml_template.starter_model.nodes, []);
  assert.equal(saved.body.manual_review_required, 1);
  assert.equal('problem_type' in saved.body, false);
  context.state.tasks[0].locked = true;
  await context.handleEditTaskSubmit({ preventDefault() {} });
  assert.equal(requests.length, 0, 'Locked task must not save');
  assert.match(alerts.pop(), /gesperrt/);
  form.querySelector = () => null;

  element('delta-package-target').value = 'live';
  element('delta-package-dry-run').checked = false;
  context.applyDeltaPackageUi();
  assert.equal(element('delta-package-confirm-field').style.display, 'block');
  element('delta-package-file').files = [new Blob(['package'])];
  await context.runDeltaPackage();
  assert.match(element('delta-package-status').textContent, /APPLY LIVE HOTFIX/);
  assert.equal(requests.length, 0, 'Live deploy requires confirmation');
  const uploads = [];
  context.fetch = async (url, options) => {
    uploads.push({ url, options });
    return { ok: true, text: async () => JSON.stringify({ ok: true, dry_run: true, counts: { replace: 2 } }) };
  };
  element('delta-package-dry-run').checked = true;
  await context.runDeltaPackage();
  assert.equal(uploads[0].options.body.get('dry_run'), '1');
  assert.equal(uploads[0].options.body.get('target'), 'live');
  assert.equal(context.state.deltaPackageBusy, false);
  assert.match(element('delta-package-status').textContent, /Dry-Run.*erfolgreich/);
  const handlers = [];
  for (const id of ['delta-package-target', 'delta-package-dry-run', 'delta-package-trigger', 'delta-package-compare-trigger', 'manifest-create-trigger']) {
    element(id).addEventListener = (event, handler) => handlers.push({ id, event, handler });
  }
  context.bindDeltaPackageEvents();
  assert.equal(handlers.length, 5, 'All deploy controls must be wired');
  assert.ok(handlers.some(item => item.handler === context.runCreateManifest));
  assert.ok(handlers.some(item => item.handler === context.runDeltaCompare));
  context.fetch = async (url, options) => {
    assert.equal(url, '../api/admin/deploy/compare-file-state.php');
    assert.equal(options.body.get('package').size, 7);
    return { ok: true, status: 200, text: async () => JSON.stringify({ available: true, different_files: 1, files: [{ path: 'public/admin.php', status: 'changed' }] }) };
  };
  await context.runDeltaCompare();
  assert.match(element('delta-package-output').textContent, /public\/admin.php/);
  assert.equal(context.state.deltaCompareBusy, false);
  const choices = ['Gegen lokalen Stand', 'Entwicklungs-Scope'];
  context.showChoiceModal = async () => choices.shift();
  context.fetch = async (url, options) => {
    assert.equal(url, '../api/admin/deploy/create-manifest.php');
    assert.deepEqual(JSON.parse(options.body), { compare_source: 'Gegen lokalen Stand', scope: 'Entwicklungs-Scope' });
    return { ok: true, json: async () => ({ filename: 'fixture.json', path: 'fixture.json' }) };
  };
  await context.runCreateManifest();
  assert.match(element('delta-package-status').textContent, /Manifest erzeugt: fixture.json/);
  context.showChoiceModal = async () => null;
  context.fetch = async () => { throw new Error('Cancellation must not call the API'); };
  await context.runCreateManifest();
  assert.ok(source.includes("bindDeltaPackageEvents();"));
  assert.ok(source.includes("data-action=\"toggle-assignment-lock\""));
  assert.ok(source.includes("data-action=\"toggle-task-lock\""));
  const toggleStart = source.indexOf("    if (action === 'toggle-task-lock') {");
  const toggleEnd = source.indexOf("    if (action === 'view-task') {", toggleStart);
  vm.runInContext(`async function toggleTaskLock() {
    const action = 'toggle-task-lock';
    const id = 7;
    ${source.slice(toggleStart, toggleEnd)}
  }`, context);
  context.confirm = () => true;
  context.requestJson = async (url, options) => {
    assert.equal(url, '../api/tasks/update.php');
    assert.deepEqual(JSON.parse(options.body), { id: 7, locked: false });
    throw new Error('Fixture API failure');
  };
  const previousConsole = context.console;
  context.console = { ...console, error: () => {} };
  await context.toggleTaskLock();
  context.console = previousConsole;
  assert.match(alerts.pop(), /Task-Sperre.*Fixture API failure/);
  console.log('PASS: clone semester payload, UML template preservation, locked edits, deploy safety and control wiring');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
