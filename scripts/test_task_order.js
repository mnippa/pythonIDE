const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn, execFileSync } = require('node:child_process');
const { once } = require('node:events');
const vm = require('node:vm');

async function main() {
  const root = path.join(__dirname, '..');
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'pythonide-task-order-'));
  const database = `pythonide_order_test_${process.pid}_${Date.now()}`;
  const php = code => execFileSync('php', [], {
    input: `<?php\n${code}`, encoding: 'utf8',
    env: { ...process.env, ORDER_TEST_ROOT: root, ORDER_TEST_FIXTURE: fixture, ORDER_TEST_DB: database }
  });
  let server;
  let databaseCreated = false;
  let logs = '';
  try {
    for (const relative of [
      'api/tasks/create.php', 'api/tasks/update.php', 'api/tasks/delete.php',
      'api/admin/tasks/clone.php', 'api/admin/tasks/delete.php',
      'api/admin/assignments/import.php', 'api/admin/assignments/clone.php',
      'api/auth/middleware.php', 'config/session.php',
      'components/task_order.php', 'components/assignment_semesters.php'
    ]) {
      const target = path.join(fixture, ...relative.split('/'));
      fs.mkdirSync(path.dirname(target), { recursive: true });
      fs.copyFileSync(path.join(root, ...relative.split('/')), target);
    }
    databaseCreated = true;
    php(`
require getenv('ORDER_TEST_ROOT') . '/config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$c = getDbConnection();
$db = getenv('ORDER_TEST_DB');
$c->query('CREATE DATABASE ' . $db);
foreach ($c->query('SHOW TABLES')->fetch_all(MYSQLI_NUM) as $row) {
    $table = str_replace('\`', '\`\`', $row[0]);
    $c->query('CREATE TABLE ' . $db . '.\`' . $table . '\` LIKE \`' . $table . '\`');
}
$config = '<?php' . PHP_EOL;
foreach (['DB_HOST', 'DB_USER', 'DB_PASS'] as $name) {
    $config .= 'define(' . var_export($name, true) . ', ' . var_export(constant($name), true) . ');' . PHP_EOL;
}
$config .= 'define("DB_NAME", ' . var_export($db, true) . ');' . PHP_EOL;
$config .= <<<'CONFIG'
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function getDbConnection() {
    static $c;
    if (!$c) { $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); $c->set_charset('utf8mb4'); }
    return $c;
}
function jsonResponse($data, $status = 200) { http_response_code($status); header('Content-Type: application/json'); echo json_encode($data); exit; }
function dbTableExists(mysqli $c, string $table): bool {
    return $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($table) . "'")->num_rows > 0;
}
CONFIG;
file_put_contents(getenv('ORDER_TEST_FIXTURE') . '/config/database.php', $config);
$c->select_db($db);
$c->query("INSERT INTO assignments (id, title, created_by, locked) VALUES (1, 'Order fixture', 1, 0), (2, 'Other fixture', 1, 0)");
$c->query("INSERT INTO tasks (id, assignment_id, title, position, locked, code_template, task_type) VALUES
(1, 1, 'A', 10, 1, 'original', 'code'),
(2, 1, 'B', 10, 1, 'original', 'code'),
(3, 1, 'C', 30, 1, 'original', 'code'),
(4, 2, 'Other', 99, 1, 'untouched', 'code')");
`);
    fs.writeFileSync(path.join(fixture, 'router.php'), `<?php
session_save_path(__DIR__);
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/login') {
    require __DIR__ . '/config/session.php';
    startApplicationSession();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    renewApplicationSessionActivity();
    echo '{}';
    return;
}
return false;
`);
    const reservation = net.createServer();
    reservation.listen(0, '127.0.0.1');
    await once(reservation, 'listening');
    const port = reservation.address().port;
    await new Promise(resolve => reservation.close(resolve));
    server = spawn('php', ['-d', 'display_errors=stderr', '-S', `127.0.0.1:${port}`, '-t', fixture, path.join(fixture, 'router.php')]);
    server.stderr.on('data', data => { logs += data; });
    server.stdout.on('data', data => { logs += data; });
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('PHP startup timeout')), 10000);
      server.once('error', error => { clearTimeout(timer); reject(error); });
      server.stderr.on('data', data => {
        if (String(data).includes('Development Server')) { clearTimeout(timer); resolve(); }
      });
    });
    const base = `http://127.0.0.1:${port}`;
    const login = await fetch(`${base}/login`);
    await login.text();
    const cookie = login.headers.getSetCookie().find(value => value.startsWith('PHPSESSID=')).split(';')[0];
    async function post(route, input, status = 200) {
      const response = await fetch(`${base}/${route}`, {
        method: 'POST', headers: { Cookie: cookie, 'Content-Type': 'application/json' },
        body: JSON.stringify(input)
      });
      const text = await response.text();
      assert.equal(response.status, status, `${route}: ${text}`);
      return JSON.parse(text);
    }
    function sql(code) {
      return php(`require getenv('ORDER_TEST_FIXTURE') . '/config/database.php'; $c=getDbConnection(); ${code}`);
    }
    function rows(assignment = 1) {
      return JSON.parse(sql(`echo json_encode($c->query('SELECT id,position,title,locked,code_template FROM tasks WHERE assignment_id=${assignment} ORDER BY position,id')->fetch_all(MYSQLI_ASSOC));`));
    }
    function order(expected, assignment = 1) {
      const actual = rows(assignment);
      assert.deepEqual(actual.map(row => Number(row.id)), expected);
      assert.deepEqual(actual.map(row => Number(row.position)), expected.map((_, i) => i + 1));
    }
    const move = (id, position, status) => post('api/tasks/update.php', { id, position }, status);
    await move(3, 1);
    order([3, 1, 2]);
    assert.ok(rows().every(row => Number(row.locked) === 1 && row.code_template === 'original'));
    await move(3, 99);
    order([1, 2, 3]);
    await move(1, 2);
    order([2, 1, 3]);
    sql(`$c->query("CREATE TRIGGER fail_order BEFORE UPDATE ON tasks FOR EACH ROW BEGIN IF NEW.id=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='order test failure'; END IF; END");`);
    await move(3, 1, 500);
    order([2, 1, 3]);
    sql("$c->query('DROP TRIGGER fail_order');");
    await move(1, 2);
    order([2, 1, 3]);
    await move(1, 0, 400);
    await move(1, 1.5, 400);
    await post('api/tasks/update.php', { id: 1, position: 1, title: 'Forbidden' }, 423);
    order([2, 1, 3]);
    sql("$c->query('UPDATE assignments SET locked=1 WHERE id=1');");
    await move(1, 1, 423);
    order([2, 1, 3]);
    sql("$c->query('UPDATE assignments SET locked=0 WHERE id=1');");
    const created = await post('api/tasks/create.php', {
      assignment_id: 1, title: 'Inserted', task_type: 'code', position: 2
    }, 201);
    order([2, created.id, 1, 3]);
    sql("$c->query('UPDATE tasks SET locked=0 WHERE id=1');");
    await post('api/tasks/update.php', { id: 1, title: 'A renamed', position: 1 });
    order([1, 2, created.id, 3]);
    const clone = await post('api/admin/tasks/clone.php', { task_id: 2, assignment_id: 1 });
    order([1, 2, created.id, 3, clone.new_task_id]);
    // Import repairs legacy gaps as well as appending the new task.
    sql("$c->query('UPDATE tasks SET position=position*10 WHERE assignment_id=1');");
    const imported = await post('api/admin/assignments/import.php?assignment_id=1', {
      version: '1.0', title: 'Imported', position: 99
    });
    order([1, 2, created.id, 3, clone.new_task_id, imported.task_id]);
    await post('api/tasks/delete.php', { id: created.id });
    order([1, 2, 3, clone.new_task_id, imported.task_id]);
    await post('api/admin/tasks/delete.php', { id: clone.new_task_id });
    order([1, 2, 3, imported.task_id]);
    sql("$c->query('UPDATE tasks SET position=position*10 WHERE assignment_id=1');");
    const assignmentClone = await post('api/admin/assignments/clone.php?id=1', {});
    const clonedRows = rows(assignmentClone.new_assignment_id);
    assert.deepEqual(clonedRows.map(row => row.title), rows().map(row => row.title));
    order(clonedRows.map(row => Number(row.id)), assignmentClone.new_assignment_id);
    assert.equal(Number(rows(2)[0].position), 99, 'Other assignments must stay unchanged');

    const js = fs.readFileSync(path.join(root, 'public', 'js', 'admin-dashboard.js'), 'utf8');
    const start = js.indexOf('async function moveTask(');
    const end = js.indexOf('\n}', start) + 2;
    const requests = [];
    const context = {
      state: { tasks: [{ id: 1, position: 10, locked: true }, { id: 2, position: 10, locked: true }], currentAssignmentId: 1 },
      requestJson: async (route, options) => requests.push({ route, data: JSON.parse(options.body) }),
      loadTasks: async () => {}, loadAssignments: async () => {}
    };
    vm.createContext(context);
    vm.runInContext(js.slice(start, end), context);
    await context.moveTask(2, 'up');
    assert.equal(requests.length, 1);
    assert.deepEqual(requests[0].data, { id: 2, position: 1 });
    const buttons = new Map(context.state.tasks.map(task => [task.id, [
      { dataset: { action: 'move-task-up' }, style: {} },
      { dataset: { action: 'move-task-down' }, style: {} },
      { dataset: { action: 'edit-task' }, style: {} }
    ]]));
    context.state.assignments = [{ id: 1, locked: false }];
    context.document = {
      getElementById: () => null,
      querySelectorAll: selector => buttons.get(Number(/data-task-id="(\d+)"/.exec(selector)[1]))
    };
    for (const name of ['$', 'getAssignmentById', 'isAssignmentLockedById', 'isCurrentAssignmentLocked', 'isTaskEffectivelyLocked', 'updateTasksLockUi']) {
      const start = js.indexOf(`function ${name}(`);
      vm.runInContext(js.slice(start, js.indexOf('\n}', start) + 2), context);
    }
    context.updateTasksLockUi();
    assert.equal(buttons.get(1)[0].disabled, true, 'First task cannot move up');
    assert.equal(buttons.get(1)[1].disabled, false, 'Locked task can move down');
    assert.equal(buttons.get(2)[0].disabled, false, 'Locked task can move up');
    assert.equal(buttons.get(2)[1].disabled, true, 'Last task cannot move down');
    assert.equal(buttons.get(1)[2].disabled, true, 'Content stays locked');
    context.state.assignments[0].locked = true;
    context.updateTasksLockUi();
    assert.ok([...buttons.values()].flat().every(button => button.disabled));
    sql(`
require getenv('ORDER_TEST_FIXTURE') . '/components/task_order.php';
foreach ([[], [9], [0, -2, 0, 100, 1], [1, 1, 1, 1], [30, 20, 10]] as $positions) {
    $c->begin_transaction();
    $c->query("INSERT INTO assignments (id,title,created_by) VALUES (99,'edge case',1)");
    foreach ($positions as $position) {
        $c->query("INSERT INTO tasks (assignment_id,title,position) VALUES (99,'edge task'," . $position . ")");
    }
    $before = $c->query('SELECT id FROM tasks WHERE assignment_id=99 ORDER BY position,id')->fetch_all(MYSQLI_ASSOC);
    lockAssignmentTaskOrder($c, 99);
    normalizeTaskPositions($c, 99);
    $after = $c->query('SELECT id,position FROM tasks WHERE assignment_id=99 ORDER BY position,id')->fetch_all(MYSQLI_ASSOC);
    if (array_column($before, 'id') !== array_column($after, 'id')) throw new RuntimeException('Relative order changed');
    foreach ($after as $i => $row) {
        if ((int)$row['position'] !== $i+1) throw new RuntimeException('Non-consecutive positions');
    }
    $c->rollback();
}
`);
    console.log('PASS: locked-task moves, assignment/content locks, ties/gaps, create/import/clones/deletes, isolation and single-request UI');
  } catch (error) {
    throw new Error(`${error.message}\nPHP logs:\n${logs}`, { cause: error });
  } finally {
    if (server && server.exitCode === null) {
      const stopped = once(server, 'exit');
      server.kill();
      await stopped;
    }
    if (databaseCreated) {
      php("require getenv('ORDER_TEST_ROOT') . '/config/database.php'; getDbConnection()->query('DROP DATABASE IF EXISTS ' . getenv('ORDER_TEST_DB'));");
    }
    for (const entry of fs.readdirSync(fixture, { recursive: true }).reverse()) {
      const target = path.join(fixture, entry);
      if (fs.statSync(target).isDirectory()) fs.rmdirSync(target);
      else fs.unlinkSync(target);
    }
    fs.rmdirSync(fixture);
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
