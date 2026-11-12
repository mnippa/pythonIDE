const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'assignments.js'), 'utf8');
const start = source.indexOf('async function resetAdminUserTaskUsage(');
const code = source.slice(start, source.indexOf('\nfunction bindAssignmentsEvents()', start));

test('student test toolbar exposes and wires both distinct reset buttons', () => {
  const page = fs.readFileSync(path.join(__dirname, '..', 'public', 'editor_assignment_user_test.php'), 'utf8');
  assert.match(page, /id="admin-reset-task-btn"[^>]*>↺ Checks<\/button>/);
  assert.match(page, /id="admin-reset-detective-btn"[^>]*>↺ Detektiv<\/button>/);
  assert.match(source, /adminResetDetectiveBtn\?\.addEventListener\('click', \(\) => resetAdminUserTaskUsage\('detective', adminResetDetectiveBtn\)\)/);
  assert.match(source, /adminResetTaskBtn\?\.addEventListener\('click', async \(\) => \{\s*if \(isAdminUserTestMode\(\)\) \{\s*await resetAdminUserTaskUsage\('checks', adminResetTaskBtn\);\s*return;/);
});

for (const usage of ['checks', 'detective']) {
  test(`${usage} reset affects only selected student task and keeps source and status`, async () => {
    const requests = [];
    const alerts = [];
    const state = {
      currentTask: { id: 42, title: 'Task' }, currentAssignmentId: 49,
      taskAttempts: { 42: 5, 43: 3 }, taskStatuses: { 42: 'failed' }
    };
    const button = { disabled: false };
    const sandbox = {
      isAdminUserTestMode: () => true, assignmentState: state,
      window: { TEST_USER_ID: 7, confirm: () => true },
      alert: text => alerts.push(text),
      requestJson: async (url, options) => requests.push(JSON.parse(options.body)),
      updateAttemptsCounter() {}, updateSubmittedMeta() {}, refreshCurrentTaskToolbarForStatus() {}
    };
    vm.createContext(sandbox);
    vm.runInContext(code, sandbox);
    await sandbox.resetAdminUserTaskUsage(usage, button);
    assert.deepEqual(requests, [{ assignment_id: 49, user_id: 7, task_id: 42, usage }]);
    assert.equal(state.taskAttempts[42], usage === 'checks' ? 0 : 5);
    assert.equal(state.taskAttempts[43], 3);
    assert.equal(state.taskStatuses[42], 'failed');
    assert.equal(button.disabled, false);
    assert.equal(alerts.length, 1);
    sandbox.window.confirm = () => false;
    await sandbox.resetAdminUserTaskUsage(usage, button);
    assert.equal(requests.length, 1);
    sandbox.window.confirm = () => true;
    sandbox.requestJson = async () => { throw new Error('Unavailable'); };
    await sandbox.resetAdminUserTaskUsage(usage, button);
    assert.match(alerts.at(-1), /Reset fehlgeschlagen.*Unavailable/);
    assert.equal(button.disabled, false);
  });
}
