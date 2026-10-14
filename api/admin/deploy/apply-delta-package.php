<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../../components/delta_package_deployer.php';
require_once __DIR__ . '/../../../components/admin_version.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$target = strtolower(trim((string)($_POST['target'] ?? 'beta')));
$dryRun = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
$confirmation = trim((string)($_POST['confirmation'] ?? ''));
if (!in_array($target, ['beta', 'live'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Target must be beta or live.'], 400);
}
if ($target === 'live' && !$dryRun && $confirmation !== 'APPLY LIVE HOTFIX') {
    jsonResponse(['ok' => false, 'error' => 'Live deployment requires APPLY LIVE HOTFIX confirmation.'], 400);
}

$upload = $_FILES['package'] ?? null;
if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonResponse(['ok' => false, 'error' => 'ZIP package upload failed.'], 400);
}
if ((int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > 50 * 1024 * 1024) {
    jsonResponse(['ok' => false, 'error' => 'ZIP package must be between 1 byte and 50 MB.'], 400);
}

$originalName = (string)($upload['name'] ?? '');
if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
    jsonResponse(['ok' => false, 'error' => 'Only ZIP packages are accepted.'], 400);
}

$appRoot = realpath(__DIR__ . '/../../..');
if ($appRoot === false) {
    jsonResponse(['ok' => false, 'error' => 'Could not resolve application root.'], 500);
}
$isBetaInstance = strcasecmp(basename($appRoot), 'pythonIDEBeta') === 0;
if ($target === 'live' && $isBetaInstance) {
    jsonResponse(['ok' => false, 'error' => 'Live packages must be applied from the Live Admin instance.'], 400);
}
$targetRoot = $target === 'live'
    ? $appRoot
    : dirname($appRoot) . DIRECTORY_SEPARATOR . 'pythonIDEBeta';
if (!is_dir($targetRoot)) {
    jsonResponse(['ok' => false, 'error' => 'Target directory does not exist.'], 400);
}

$lockPath = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'delta-package.lock';
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false) {
    jsonResponse(['ok' => false, 'error' => 'Could not create deployment lock.'], 500);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    jsonResponse(['ok' => false, 'error' => 'Another delta deployment is currently running.'], 409);
}

try {
    $result = inspectAndApplyDeltaPackage(
        (string)$upload['tmp_name'],
        $targetRoot,
        $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'deploy-backups',
        $dryRun
    );
    if (!$dryRun) {
        $packageId = isset($result['package_id']) ? (string)$result['package_id'] : '';
        $historyLabel = $packageId !== '' ? ('Delta Package ' . $packageId) : 'Delta Package';
        appendAdminDeployHistory($targetRoot, [
            'kind' => 'delta-package',
            'label' => $historyLabel,
            'target' => $target,
            'package_id' => $packageId !== '' ? $packageId : null,
            'actor' => $admin,
            'details' => [
                'dry_run' => false,
                'counts' => $result['counts'] ?? null,
            ],
        ]);
    }
    $response = [
        'ok' => true,
        'target' => $target,
        'executed_by' => [
            'id' => $admin['id'] ?? null,
            'email' => $admin['email'] ?? null,
        ],
    ] + $result;
    $statusCode = 200;
} catch (Throwable $exception) {
    error_log('Delta package deployment failed: ' . $exception->getMessage());
    $response = ['ok' => false, 'error' => $exception->getMessage()];
    $statusCode = 400;
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

jsonResponse($response, $statusCode);
