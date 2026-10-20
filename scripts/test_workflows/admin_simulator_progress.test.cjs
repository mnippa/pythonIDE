const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..', '..');
const source = fs.readFileSync(path.join(root, 'public', 'js', 'assignments.js'), 'utf8');

function functionSource(name) {
  const start = source.search(new RegExp(`^(?:async )?function ${name}\\(`, 'm'));
  assert.notEqual(start, -1, `Missing function: ${name}`);
  const rest = source.slice(start + 1);
  const next = rest.search(/^(?:async )?function \w+\(/m);
  return next === -1 ? source.slice(start) : source.slice(start, start + 1 + next);
}

function context(flags = {}) {
  const requests = [];
  const task = { id: 500, task_type: 'code', test_cases: '[{"type":"output","expected":"Hallo Python!"}]' };
  const state = {
    assignmentDetails: {}, tasksByAssignment: {}, taskStatuses: {}, taskAttempts: {},
    taskIterations: {}, taskUserAnswers: {}, taskRuns: {}, taskCompletedAt: {},
    taskSubmissionComments: {}, hintsRevealed: {}, taskStartTimes: {},
    currentTask: task, solutionMode: false
  };
  const output = { innerHTML: '' };
  const buttons = {};
  for (const id of ['save-task-btn', 'undo-btn', 'redo-btn', 'check-btn']) {
    buttons[id] = { disabled: false, style: {}, title: '' };
  }
  const editorOptions = {};
  const sandbox = {
    window: {
      location: { pathname: '/pythonIDE/public/editor_assignment_test.php' },
      ADMIN_TASK_LAB_VIEW: true, testMode: true,
      editorInstance: { getValue: () => 'print("Hallo Python!")', updateOptions(options) { Object.assign(editorOptions, options); } },
      TestMode: { initializeTask() {} },
      ...flags
    },
    assignmentState: state,
    console: { log() {}, warn() {}, error() {} },
    $: id => id === 'output-container' ? output : (buttons[id] || null),
    Date, JSON, URL, setTimeout() {},
    fetch: async (url, options = {}) => {
      requests.push({ url, options });
      let data = { ok: true };
      if (url.includes('/assignments/get.php')) data.assignment = { id: 45 };
      if (url.includes('/tasks/list.php')) data.tasks = [task];
      if (url.includes('/user_tasks/get.php')) {
        const progress = { task_id: 500, status: 'passed', attempts: 2, run_count: 4, current_code: 'old answer' };
        data.tasks = [progress];
        data.task = progress;
      }
      return { ok: true, status: 200, text: async () => JSON.stringify(data), json: async () => data };
    },
    getStudentViewQueryParam: () => '',
    getTestUserQueryParam: () => '',
    getUserTasksUpdateUrl: () => '/pythonIDE/api/user_tasks/update.php',
    updateTaskStatusDisplay() {},
    logActivityDebug() {},
    stopActivityTracking() {},
    flushHeartbeat() {},
    showSuccessModal() {},
    getCurrentSubmissionComment: () => '',
    toMysqlLocalDateTime: () => '2026-10-18 21:00:00',
    normalizeLegacyEscapedCode: text => text,
    refreshCurrentTaskFromAPI: async () => true,
    isTaskEffectivelyLockedForEdit: () => false,
    confirmTemplateSaveImpact: () => true,
    confirmIfTemplateAndSolutionIdentical: () => true,
    setTaskSavedSnapshotForScope() {},
    setTaskDraftContentForScope() {},
    updateTaskLockButton() {},
    pyodideSyncQueue: { drain: async () => {} },
    isManualReviewTask: current => current.manual_review_required === true,
    switchAssignmentOutputTab() {},
    detectEditorFileType: () => 'py',
    waitForPyodideWithStatus: async () => ({}),
    ensureAssignmentPackages: async () => {},
    prepareAssignmentCheckRuntime: async () => {},
    cleanupAssignmentMatplotlibState: async () => {},
    migrateLegacyTestCases: cases => cases,
    runOutputTests: async () => [{ passed: true }],
    displayTestResults() {},
    updateAttemptsCounter() {},
    updateSubmittedMeta() {},
    refreshCurrentTaskToolbarForStatus() {},
    confirm: () => true,
    alert: message => { throw new Error(message); },
    escapeHtml: text => text
  };
  vm.createContext(sandbox);
  for (const name of [
    'isAdminTaskLabMode', 'isAdminUserTestMode', 'shouldPersistTaskProgress',
    'isAdminAssignmentTestMode', 'requestJson', 'loadSingleAssignment', 'loadSavedCode',
    'incrementRunCount', 'startActivityTracking', 'sendActivityHeartbeat',
    'processValidationResult', 'saveCode', 'checkTask', 'submitTask',
    'isTaskEffectivelyLockedForEdit', 'applyTaskLockStateInAdminTest', 'updateAttemptsCounter'
  ]) {
    vm.runInContext(functionSource(name), sandbox);
  }
  return { sandbox, requests, state, task, output, buttons, editorOptions };
}

for (const taskId of [null, 500]) {
  test(`simulator entry ${taskId === null ? 'assignment' : 'task'} ignores saved progress`, async () => {
    const { sandbox, state, requests } = context({ TASK_ID: taskId });
    assert.equal(await sandbox.loadSingleAssignment(45), true);
    assert.equal(state.taskStatuses[500], 'unbearbeitet');
    assert.equal(state.taskAttempts[500], 0);
    assert.equal(state.taskUserAnswers[500], undefined);
    assert.equal(requests.some(r => r.url.includes('/user_tasks/')), false);
    assert.equal(await sandbox.loadSavedCode(500), null);
    assert.equal(requests.some(r => r.url.includes('/user_tasks/')), false);
  });
}

test('Run updates simulator state but never writes participant progress', async () => {
  const { sandbox, state, requests } = context();
  await sandbox.incrementRunCount(500);
  assert.equal(state.taskRuns[500], 1);
  assert.equal(state.taskStatuses[500], 'in-progress');
  assert.equal(requests.length, 0);
  sandbox.startActivityTracking(500);
  await sandbox.sendActivityHeartbeat(500, 10, true);
  assert.equal(requests.length, 0);
});

test('simulator keeps in-memory code without loading participant answers', async () => {
  const { sandbox, state, requests } = context();
  state.taskUserAnswers[500] = { current_code: 'print("local draft")' };
  assert.equal(await sandbox.loadSavedCode(500), 'print("local draft")');
  assert.equal(requests.length, 0);
});

test('actual Check flow counts a simulated check without database requests', async () => {
  const { sandbox, state, requests, output } = context();
  state.taskStatuses[500] = 'in-progress';
  await sandbox.checkTask();
  assert.equal(state.taskAttempts[500], 1);
  assert.equal(state.taskStatuses[500], 'in-progress');
  assert.equal(requests.length, 0);
  assert.equal(output.innerHTML.includes('Fehler:'), false, output.innerHTML);
});

for (const reviewMode of ['automated', 'manual', 'no-tests']) {
  test(`actual ${reviewMode} Submit flow stays in simulator`, async () => {
    const { sandbox, state, requests, task, output } = context();
    if (reviewMode === 'manual') task.manual_review_required = true;
    if (reviewMode === 'no-tests') task.test_cases = null;
    await sandbox.submitTask();
    assert.equal(state.taskStatuses[500], reviewMode === 'automated' ? 'passed' : 'submitted');
    assert.equal(requests.length, 0);
    assert.equal(output.innerHTML.includes('Fehler:'), false, output.innerHTML);
  });
}

test('simulated check and progress writes cannot escape the request boundary', async () => {
  const { sandbox, requests } = context();
  for (const url of [
    '../api/user_tasks/update.php',
    '/pythonIDE/api/user_tasks/update.php',
    '../api/user_tasks/heartbeat.php'
  ]) {
    const result = await sandbox.requestJson(url, {
      method: 'POST', body: JSON.stringify({ task_id: 500, attempts: 1 })
    });
    assert.equal(result.ok, true);
    assert.equal(result.simulated, true);
  }
  assert.equal(requests.length, 0);
});

for (const passed of [true, false]) {
  test(`simulated ${passed ? 'successful' : 'failed'} submission stays local`, async () => {
    const { sandbox, state, requests, task } = context();
    state.taskAttempts[500] = 1;
    await sandbox.processValidationResult({ passed }, task, null, true);
    assert.equal(state.taskStatuses[500], passed ? 'passed' : 'failed');
    assert.equal(requests.length, 0);
    const fresh = context();
    await fresh.sandbox.loadSingleAssignment(45);
    assert.equal(fresh.state.taskStatuses[500], 'unbearbeitet');
  });
}

test('Check feedback alone does not finalize a simulated task', async () => {
  const { sandbox, state, requests, task } = context();
  state.taskStatuses[500] = 'in-progress';
  await sandbox.processValidationResult({ passed: true }, task, null, false);
  assert.equal(state.taskStatuses[500], 'in-progress');
  assert.equal(requests.length, 0);
});

for (const solutionMode of [false, true]) {
  test(`explicit ${solutionMode ? 'solution' : 'template'} saves remain persistent`, async () => {
    const { sandbox, state, requests } = context();
    state.solutionMode = solutionMode;
    assert.equal(await sandbox.saveCode(), true);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '../api/tasks/update.php');
    const payload = JSON.parse(requests[0].options.body);
    assert.equal(payload.id, 500);
    assert.equal(payload[solutionMode ? 'solution_code' : 'code_template'], 'print("Hallo Python!")');
  });
}

for (const flags of [
  { ADMIN_TASK_LAB_VIEW: false, testMode: false, role: 'admin' },
  { ADMIN_TASK_LAB_VIEW: false, testMode: false, role: 'user' },
  { ADMIN_TASK_LAB_VIEW: false, ADMIN_USER_TEST_VIEW: true, testMode: true, TEST_USER_ID: 7 }
]) {
  test(`real progress stays enabled: ${flags.ADMIN_USER_TEST_VIEW ? 'selected user view' : flags.role}`, async () => {
    const { sandbox, state, requests } = context(flags);
    assert.equal(sandbox.shouldPersistTaskProgress(), true);
    await sandbox.loadSingleAssignment(45);
    assert.equal(state.taskStatuses[500], 'passed');
    assert.equal(state.taskAttempts[500], 2);
    assert.equal(await sandbox.loadSavedCode(500), 'old answer');
    await sandbox.incrementRunCount(500);
    assert.equal(requests.filter(r => r.url.includes('/user_tasks/update.php')).length, 1);
    assert.equal(state.taskStatuses[500], 'passed');
  });
}

for (const lockField of ['locked', 'assignment_locked', 'effective_locked']) {
  for (const solutionMode of [false, true]) {
    test(`${lockField}: ${solutionMode ? 'solution' : 'template'} stays editable but cannot save`, async () => {
      const { sandbox, state, task, buttons, editorOptions, requests } = context();
      task[lockField] = true;
      state.solutionMode = solutionMode;
      const alerts = [];
      sandbox.alert = message => alerts.push(message);
      sandbox.applyTaskLockStateInAdminTest(task);
      assert.equal(editorOptions.readOnly, false);
      assert.equal(buttons['save-task-btn'].disabled, true);
      assert.equal(buttons['undo-btn'].disabled, false);
      assert.equal(buttons['redo-btn'].disabled, false);
      assert.equal(buttons['check-btn'].disabled, false);
      assert.equal(await sandbox.saveCode(), false);
      assert.equal(alerts.length, 1);
      assert.equal(requests.length, 0);
      await sandbox.checkTask();
      assert.equal(state.taskAttempts[500], 1);
      assert.equal(requests.length, 0);
    });
  }
}

test('unlock restores saving and relock preserves code while disabling persistence', async () => {
  const { sandbox, task, buttons, editorOptions, requests } = context();
  task.locked = true;
  sandbox.applyTaskLockStateInAdminTest(task);
  task.locked = false;
  sandbox.applyTaskLockStateInAdminTest(task);
  assert.equal(buttons['save-task-btn'].disabled, false);
  assert.equal(editorOptions.readOnly, false);
  assert.equal(await sandbox.saveCode(), true);
  assert.equal(requests.length, 1);
  const draft = sandbox.window.editorInstance.getValue();
  task.locked = true;
  sandbox.applyTaskLockStateInAdminTest(task);
  assert.equal(buttons['save-task-btn'].disabled, true);
  assert.equal(sandbox.window.editorInstance.getValue(), draft);
});

test('fresh server lock blocks save even if the UI was previously unlocked', async () => {
  const { sandbox, task, requests } = context();
  sandbox.refreshCurrentTaskFromAPI = async () => { task.locked = true; return true; };
  sandbox.alert = () => {};
  assert.equal(await sandbox.saveCode(), false);
  assert.equal(requests.length, 0);
});

test('task lock does not override check limit or explicit read-only file policy', () => {
  const { sandbox, task, state, buttons, editorOptions } = context();
  task.locked = true;
  task.max_attempts = 2;
  state.taskAttempts[500] = 2;
  sandbox.window.currentFile = { taskId: 500, path: 'protected.py', readOnly: true };
  sandbox.applyTaskLockStateInAdminTest(task);
  assert.equal(editorOptions.readOnly, true);
  assert.equal(buttons['check-btn'].disabled, true);
  assert.match(buttons['check-btn'].title, /Maximale Anzahl/);
});

for (const flags of [
  { ADMIN_TASK_LAB_VIEW: false, testMode: false },
  { ADMIN_TASK_LAB_VIEW: false, ADMIN_USER_TEST_VIEW: true, testMode: true, TEST_USER_ID: 7 }
]) {
  test(`task lock UI leaves ${flags.ADMIN_USER_TEST_VIEW ? 'selected-user' : 'participant'} controls untouched`, () => {
    const { sandbox, task, buttons, editorOptions } = context(flags);
    task.locked = true;
    sandbox.window.editorInstance.updateOptions({ readOnly: true });
    for (const button of Object.values(buttons)) button.disabled = true;
    sandbox.applyTaskLockStateInAdminTest(task);
    assert.equal(editorOptions.readOnly, true);
    for (const button of Object.values(buttons)) assert.equal(button.disabled, true);
  });
}
