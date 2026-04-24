<?php
/**
 * Admin endpoint to run LIVE<->BETA sync script from browser.
 *
 * POST JSON body:
 * {
 *   "mode": "hydrate-beta" | "promote-live",
 *   "dry_run": true|false,
 *   "sync_db": true|false,   // only hydrate-beta
 *   "delete": true|false     // only promote-live
 * }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$__deploySyncFinished = false;
register_shutdown_function(static function () use (&$__deploySyncFinished): void {
    if ($__deploySyncFinished) {
        return;
    }

    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    $message = 'Fatal error: ' . ($error['message'] ?? 'Unknown fatal error');
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
});

require_once __DIR__ . '/../../auth/middleware.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'ok' => false,
        'error' => 'Method not allowed. Use POST JSON.',
    ], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    jsonResponse([
        'ok' => false,
        'error' => 'Invalid JSON payload',
    ], 400);
}

$mode = (string)($data['mode'] ?? '');
$allowedModes = ['hydrate-beta', 'promote-live'];
if (!in_array($mode, $allowedModes, true)) {
    jsonResponse([
        'ok' => false,
        'error' => 'Invalid mode. Allowed: hydrate-beta, promote-live',
    ], 400);
}

$dryRun = toBool($data['dry_run'] ?? true);
$syncDb = toBool($data['sync_db'] ?? false);
$delete = toBool($data['delete'] ?? false);

$appRoot = realpath(__DIR__ . '/../../..');
if ($appRoot === false) {
    jsonResponse(['ok' => false, 'error' => 'Could not resolve app root'], 500);
}

$script = $appRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'sync_live_beta.php';
if (!is_file($script)) {
    jsonResponse(['ok' => false, 'error' => 'sync script not found'], 500);
}

// Keep endpoint anchored to live app root.
$liveRoot = $appRoot;
$betaRoot = dirname($appRoot) . DIRECTORY_SEPARATOR . 'pythonIDEBeta';

if (!is_dir($betaRoot)) {
    jsonResponse([
        'ok' => false,
        'error' => 'Beta directory does not exist: ' . $betaRoot,
    ], 400);
}

// Simple lock to avoid parallel deploy runs.
$lockPath = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sync-live-beta.lock';
$lockDir = dirname($lockPath);
if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    jsonResponse(['ok' => false, 'error' => 'Could not create lock directory'], 500);
}

$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false) {
    jsonResponse(['ok' => false, 'error' => 'Could not create lock file'], 500);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    jsonResponse([
        'ok' => false,
        'error' => 'Another sync process is currently running',
    ], 409);
}

try {
    $phpCli = resolvePhpCliBinary();
    if ($phpCli === null) {
        jsonResponse([
            'ok' => false,
            'error' => 'Could not locate a PHP CLI binary. Set DEPLOY_PHP_CLI or install php-cli.',
        ], 500);
    }

    $args = [];
    $args[] = '--mode=' . $mode;
    $args[] = '--live-root=' . $liveRoot;
    $args[] = '--beta-root=' . $betaRoot;
    if ($dryRun) {
        $args[] = '--dry-run=1';
    }
    if ($mode === 'hydrate-beta' && $syncDb) {
        $args[] = '--sync-db=1';
    }
    if ($mode === 'promote-live' && $delete) {
        $args[] = '--delete=1';
    }

    $cmdParts = [escapeshellarg($phpCli), '-n', escapeshellarg($script)];
    foreach ($args as $arg) {
        $cmdParts[] = escapeshellarg($arg);
    }

    $cmd = implode(' ', $cmdParts) . ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    $joinedOutput = implode("\n", $output);
    $joinedOutput = preg_replace("/MYSQL_PWD='[^']*'/", "MYSQL_PWD='***'", $joinedOutput) ?? $joinedOutput;

    $__deploySyncFinished = true;

    jsonResponse([
        'ok' => $exitCode === 0,
        'mode' => $mode,
        'dry_run' => $dryRun,
        'sync_db' => $mode === 'hydrate-beta' ? $syncDb : false,
        'delete' => $mode === 'promote-live' ? $delete : false,
        'executed_by' => [
            'id' => $admin['id'] ?? null,
            'email' => $admin['email'] ?? null,
        ],
        'exit_code' => $exitCode,
        'output' => $joinedOutput,
    ], $exitCode === 0 ? 200 : 500);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

function toBool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
}

function resolvePhpCliBinary()
{
    $envPhp = getenv('DEPLOY_PHP_CLI');
    if (is_string($envPhp) && $envPhp !== '' && is_executable($envPhp)) {
        return $envPhp;
    }

    $candidates = [];

    if (defined('PHP_BINDIR')) {
        $candidates[] = rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';
    }

    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
        $binaryName = basename(PHP_BINARY);
        if (stripos($binaryName, 'php-fpm') === false && stripos($binaryName, 'php-cgi') === false) {
            $candidates[] = PHP_BINARY;
        }
    }

    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';
    $candidates[] = '/bin/php';

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_executable($candidate)) {
            return $candidate;
        }
    }

    return null;
}
