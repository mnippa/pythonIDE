const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { once } = require('node:events');

async function main() {
  const root = path.join(__dirname, '..');
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'pythonide-session-'));
  const source = fs.readFileSync(path.join(root, 'config', 'session.php'), 'utf8');
  // Override only the clock, so exact timeout boundaries do not need real waiting.
  fs.writeFileSync(path.join(fixture, 'session.php'), source.replace('<?php', `<?php
namespace SessionLifetimeTest;
use RuntimeException;
function time(): int { return (int) $_SERVER['HTTP_X_TEST_TIME']; }
`));
  fs.writeFileSync(path.join(fixture, 'router.php'), `<?php
namespace SessionLifetimeTest;
require __DIR__ . '/session.php';
session_save_path(__DIR__);
set_error_handler(function ($severity, $message) { throw new \\RuntimeException($message); });
$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($route === '/seed') {
    configureApplicationSession();
    session_start();
    $_SESSION = ['user_id' => 42, 'role' => 'admin', 'payload' => 'preserved'];
    if (!isset($_GET['legacy'])) {
        $_SESSION['_application_last_activity'] = (int) $_GET['last'];
    }
} else {
    if ($route === '/prestarted') {
        configureApplicationSession();
        session_start();
    }
    startApplicationSession();
    startApplicationSession();
    configureApplicationSession();
    if ($route === '/logout') {
        session_unset();
        session_destroy();
    } elseif ($route === '/login') {
        $_SESSION['user_id'] = 42;
        renewApplicationSessionActivity();
    }
}
header('Content-Type: application/json');
echo json_encode([
    'logged_in' => isset($_SESSION['user_id']),
    'last' => $_SESSION['_application_last_activity'] ?? null,
    'payload' => $_SESSION['payload'] ?? null,
    'gc' => (int) ini_get('session.gc_maxlifetime'),
    'cookie_lifetime' => session_get_cookie_params()['lifetime']
]);
`);

  let server;
  let logs = '';
  try {
    const reservation = net.createServer();
    reservation.listen(0, '127.0.0.1');
    await once(reservation, 'listening');
    const port = reservation.address().port;
    await new Promise(resolve => reservation.close(resolve));
    server = spawn('php', [
      '-d', 'session.gc_probability=0',
      '-d', 'display_errors=stderr',
      '-S', `127.0.0.1:${port}`, path.join(fixture, 'router.php')
    ], { stdio: ['ignore', 'pipe', 'pipe'] });
    server.stdout.on('data', data => { logs += data; });
    server.stderr.on('data', data => { logs += data; });
    const baseTime = Math.floor(Date.now() / 1000);
    async function request(route, now, cookie = '') {
      const response = await fetch(`http://127.0.0.1:${port}${route}`, {
        headers: { 'X-Test-Time': String(now), Cookie: cookie }
      });
      assert.equal(response.status, 200);
      const text = await response.text();
      const data = JSON.parse(text);
      const cookies = response.headers.getSetCookie();
      const sessionCookie = cookies.findLast(value => value.startsWith('PHPSESSID='));
      return { data, cookies, cookie: sessionCookie ? sessionCookie.split(';')[0] : cookie };
    }
    await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('PHP server startup timeout')), 10000);
      server.once('error', error => { clearTimeout(timeout); reject(error); });
      server.once('exit', code => { clearTimeout(timeout); reject(new Error(`PHP exited: ${code}`)); });
      server.stderr.on('data', data => {
        if (String(data).includes('Development Server')) {
          clearTimeout(timeout);
          resolve();
        }
      });
    });

    const seeded = await request(`/seed?last=${baseTime}`, baseTime);
    const active = await request('/check', baseTime + 7199, seeded.cookie);
    assert.equal(active.data.logged_in, true, 'Must remain logged in at 7199 seconds');
    assert.equal(active.data.last, baseTime + 7199);
    assert.equal(active.data.payload, 'preserved');
    assert.equal(active.data.gc, 7200);
    assert.equal(active.data.cookie_lifetime, 7200);
    const renewedCookie = active.cookies.findLast(value => value.startsWith('PHPSESSID='));
    assert.ok(renewedCookie);
    const expires = /expires=([^;]+)/i.exec(renewedCookie)[1];
    assert.equal(Date.parse(expires) / 1000, baseTime + 7199 + 7200);
    assert.match(renewedCookie, /path=\//i);
    assert.match(renewedCookie, /httponly/i);
    assert.match(renewedCookie, /samesite=lax/i);
    const continued = await request('/prestarted', baseTime + 7201, active.cookie);
    assert.equal(continued.data.logged_in, true, 'Activity must extend the original deadline');
    assert.equal(continued.data.last, baseTime + 7201);

    const idle = await request(`/seed?last=${baseTime}`, baseTime);
    const expired = await request('/check', baseTime + 7200, idle.cookie);
    assert.equal(expired.data.logged_in, false, 'Must expire exactly at 7200 seconds');
    assert.equal(expired.data.payload, null);
    assert.notEqual(expired.cookie, idle.cookie);

    const legacy = await request('/seed?legacy=1', baseTime);
    const adopted = await request('/check', baseTime + 1, legacy.cookie);
    assert.equal(adopted.data.logged_in, true);
    assert.equal(adopted.data.payload, 'preserved');
    assert.equal(adopted.data.last, baseTime + 1);
    const loggedOut = await request('/logout', baseTime + 2, adopted.cookie);
    assert.equal(loggedOut.data.logged_in, false);
    const afterLogout = await request('/check', baseTime + 3, loggedOut.cookie);
    assert.equal(afterLogout.data.logged_in, false);
    assert.equal(afterLogout.data.last, null);
    const login = await request('/login', baseTime + 4, afterLogout.cookie);
    assert.equal(login.data.logged_in, true);
    assert.equal(login.data.last, baseTime + 4);
    assert.ok(login.cookies.some(value => value.startsWith('PHPSESSID=')));

    for (const directory of ['api', 'public']) {
      for (const entry of fs.readdirSync(path.join(root, directory), { recursive: true })) {
        const file = path.join(root, directory, entry);
        if (!file.endsWith('.php') || !fs.statSync(file).isFile()) continue;
        const content = fs.readFileSync(file, 'utf8');
        assert.doesNotMatch(content, /\bsession_start\s*\(/, `Direct session start: ${file}`);
      }
    }
    console.log('PASS: 7199/7200-second boundaries, sliding cookie expiry, active/legacy sessions, logout and centralized entry points');
  } catch (error) {
    throw new Error(`${error.message}\nPHP logs:\n${logs}`, { cause: error });
  } finally {
    if (server && server.exitCode === null) {
      const stopped = once(server, 'exit');
      server.kill();
      await stopped;
    }
    for (const entry of fs.readdirSync(fixture)) {
      fs.unlinkSync(path.join(fixture, entry));
    }
    fs.rmdirSync(fixture);
  }
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
