<?php
/**
 * Run with PHP CLI. Uses an isolated fixture without database session helpers.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pythonide-clone-session-' . bin2hex(random_bytes(8));
$files = [
    'api/admin/assignments/clone.php',
    'api/admin/tasks/clone.php',
    'api/auth/middleware.php',
    'components/assignment_semesters.php',
    'components/task_order.php',
    'config/session.php',
];
$createdFiles = [];
$createdDirectories = [];

function writeCloneSessionFixture(string $path, string $content): void
{
    global $createdFiles, $createdDirectories;
    $directory = dirname($path);
    $missing = [];
    while (!is_dir($directory)) {
        $missing[] = $directory;
        $directory = dirname($directory);
    }
    foreach (array_reverse($missing) as $directory) {
        if (!mkdir($directory)) {
            throw new RuntimeException('Could not create fixture directory: ' . $directory);
        }
        $createdDirectories[] = $directory;
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Could not write fixture file: ' . $path);
    }
    $createdFiles[] = $path;
}

try {
    foreach ($files as $file) {
        $content = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
        if ($content === false) {
            throw new RuntimeException('Could not read fixture source: ' . $file);
        }
        writeCloneSessionFixture($fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file), $content);
    }
    // Live database.php is protected from deployments and need not define session helpers.
    writeCloneSessionFixture($fixture . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', '<?php');

    foreach (['assignments', 'tasks'] as $type) {
        foreach ([false, true] as $admin) {
            $endpoint = $fixture . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'admin'
                . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . 'clone.php';
            $runner = $fixture . DIRECTORY_SEPARATOR . 'runner.php';
            $setup = $admin
                ? "require " . var_export($fixture . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'session.php', true)
                    . "; configureApplicationSession(); session_start(); \$_SESSION = ['user_id' => 1, 'role' => 'admin'];"
                : '';
            writeCloneSessionFixture($runner, "<?php\n"
                . "register_shutdown_function(function () { fwrite(STDERR, 'STATUS:' . http_response_code()); });\n"
                . $setup . "\nrequire " . var_export($endpoint, true) . ";\n");
            $process = proc_open(
                [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-d', 'session.use_cookies=0', $runner],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start fixture PHP process');
            }
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $expectedStatus = $admin ? 400 : 403;
            $expectedError = $admin
                ? ($type === 'assignments' ? 'Assignment ID required. Use ?id=X' : 'task_id and assignment_id required')
                : 'Admin access required';
            $data = json_decode($output, true);
            if ($exitCode !== 0 || $errors !== 'STATUS:' . $expectedStatus
                || $data !== ['ok' => false, 'error' => $expectedError]) {
                throw new RuntimeException($type . ' bootstrap failed: ' . $output . ' ' . $errors);
            }
            echo 'PASS ' . $type . ($admin ? ' admin input validation' : ' anonymous access') . PHP_EOL;
        }
    }
} finally {
    foreach (array_unique(array_reverse($createdFiles)) as $file) {
        if (!unlink($file)) {
            throw new RuntimeException('Could not remove fixture file: ' . $file);
        }
    }
    foreach (array_reverse($createdDirectories) as $directory) {
        if (!rmdir($directory)) {
            throw new RuntimeException('Could not remove fixture directory: ' . $directory);
        }
    }
}
