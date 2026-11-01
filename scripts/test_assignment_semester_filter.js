const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'admin-dashboard.js'), 'utf8');
const elements = new Map();
const stored = new Map();
function element(id) {
  if (!elements.has(id)) {
    elements.set(id, {
      innerHTML: '', value: '', options: [], children: [],
      add(option) { this.options.push(option); },
      appendChild(child) { this.children.push(child); }
    });
  }
  return elements.get(id);
}
const context = vm.createContext({
  console, Date,
  sessionStorage: { getItem: key => stored.get(key), setItem: (key, value) => stored.set(key, value) },
  document: { getElementById: element, createElement: () => ({}), querySelectorAll: () => [] },
  Option: function(text, value) { this.text = text; this.value = value; },
  requestJson: async url => {
    assert.equal(url, '../api/admin/semesters/list.php');
    return { semesters: [{ code: 'WS2627' }, { code: 'SS26' }] };
  },
  escapeHtml: value => String(value),
  formatCompactDate: () => ''
});
vm.runInContext(source.slice(0, source.indexOf('const ALLOWED_FILE_SUBMISSION_MAX_SIZES')), context);
vm.runInContext(source.slice(source.indexOf('async function loadAssignmentSemesters()'), source.indexOf('async function loadTasks(')), context);
const eventStart = source.indexOf("  const semesterFilter = $('assignments-semester-filter');");
const eventEnd = source.indexOf('  // Assignments pagination', eventStart);
element('assignments-semester-filter').addEventListener = (name, handler) => {
  assert.equal(name, 'change');
  element('assignments-semester-filter').change = handler;
};
vm.runInContext(source.slice(eventStart, eventEnd), context);

async function main() {
  assert.equal(vm.runInContext('getSemesterForDate(new Date(2026, 0, 15))', context), 'WS2526');
  assert.equal(vm.runInContext('getSemesterForDate(new Date(2026, 2, 1))', context), 'SS26');
  assert.equal(vm.runInContext('getSemesterForDate(new Date(2026, 9, 1))', context), 'WS2627');
  vm.runInContext("state.assignmentsSemesterFilter = 'all'", context);
  await vm.runInContext('loadAssignmentSemesters()', context);
  assert.deepEqual(element('assignments-semester-filter').options.map(o => o.value), ['WS2627', 'SS26']);
  // Live behavior: a stored 'all' falls back to the current semester when it is available.
  assert.equal(element('assignments-semester-filter').value, 'WS2627');
  assert.equal(vm.runInContext('state.assignmentsSemesterFilter', context), 'WS2627');
  vm.runInContext("state.assignmentsSemesterFilter = 'all'", context);
  assert.match(element('assignment-semester-options').innerHTML, /SS26/);

  vm.runInContext(`state.assignments = [
    {id: 2, title: 'Winter', difficulty: 'beginner', semester: 'WS2627'},
    {id: 1, title: 'Summer', difficulty: 'beginner', semester: 'SS26'}
  ]`, context);
  const render = () => {
    element('assignments-body').children = [];
    vm.runInContext('renderAssignments()', context);
    return element('assignments-body').children.map(row => row.innerHTML);
  };
  assert.equal(render().length, 2);
  vm.runInContext('state.assignmentsPage = 3', context);
  element('assignments-semester-filter').change({ target: { value: 'SS26' } });
  assert.equal(vm.runInContext('state.assignmentsPage', context), 1);
  assert.equal(stored.get('admin.assignments.semesterFilter'), 'SS26');
  assert.equal(vm.runInContext('getStoredAssignmentSemesterFilter()', context), 'SS26');
  assert.equal(render().length, 1);
  assert.match(render()[0], /Summer/);
  vm.runInContext("state.assignmentsFilter = 'Winter'", context);
  assert.equal(render().length, 0);
  element('assignments-semester-filter').change({ target: { value: 'all' } });
  assert.equal(render().length, 1);
  assert.match(render()[0], /Winter/);
  vm.runInContext("state.assignmentsSemesterFilter = 'WS9999'", context);
  await vm.runInContext('loadAssignmentSemesters()', context);
  assert.equal(vm.runInContext('state.assignmentsSemesterFilter', context), 'WS2627');
  assert.match(source, /await loadAssignmentSemesters\(\);\s+await loadAssignments\(\);/);
  console.log('PASS: semester options, all/current/stored selection, filtering, search and page reset');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
