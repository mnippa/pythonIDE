<?php
/**
 * Download/view endpoint for file_submission tasks.
 * Allows owner (student) and admin test view (test_user_id) to access latest uploaded file.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

$user = requireAuth();
$conn = getDbConnection();

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($taskId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

$effectiveUserId = (int)$user['id'];
if (isset($_GET['test_user_id'])) {
    $testUserId = (int)$_GET['test_user_id'];
    if (($user['role'] ?? '') !== 'admin') {
        jsonResponse(['ok' => false, 'error' => 'Unauthorized: Admin access required for test_user_id'], 403);
    }
    if ($testUserId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid test_user_id'], 400);
    }

    $checkUserStmt = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $checkUserStmt->bind_param('i', $testUserId);
    $checkUserStmt->execute();
    $checkUser = $checkUserStmt->get_result()->fetch_assoc();
    if (!$checkUser) {
        jsonResponse(['ok' => false, 'error' => 'Test user not found'], 404);
    }
    $effectiveUserId = $testUserId;
}

$taskStmt = $conn->prepare('SELECT id, task_type FROM tasks WHERE id = ? LIMIT 1');
$taskStmt->bind_param('i', $taskId);
$taskStmt->execute();
$task = $taskStmt->get_result()->fetch_assoc();
if (!$task) {
    jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
}
if (($task['task_type'] ?? '') !== 'file_submission') {
    jsonResponse(['ok' => false, 'error' => 'Task is not a file_submission task'], 400);
}

$utStmt = $conn->prepare('SELECT variable_values FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');
$utStmt->bind_param('ii', $effectiveUserId, $taskId);
$utStmt->execute();
$ut = $utStmt->get_result()->fetch_assoc();
if (!$ut) {
    jsonResponse(['ok' => false, 'error' => 'No submission found'], 404);
}

$values = [];
if (!empty($ut['variable_values'])) {
    $decoded = json_decode((string)$ut['variable_values'], true);
    if (is_array($decoded)) {
        $values = $decoded;
    }
}

$submission = $values['file_submission'] ?? null;
if (!is_array($submission)) {
    jsonResponse(['ok' => false, 'error' => 'No uploaded file metadata found'], 404);
}

$storedName = (string)($submission['stored_name'] ?? '');
$relativePath = (string)($submission['relative_path'] ?? '');
$originalName = (string)($submission['original_name'] ?? 'submission.bin');
$mimeType = (string)($submission['mime_type'] ?? 'application/octet-stream');

if ($storedName === '' || $relativePath === '') {
    jsonResponse(['ok' => false, 'error' => 'Invalid upload metadata'], 500);
}
if (strpos($relativePath, '..') !== false || strpos($relativePath, '\\') !== false) {
    jsonResponse(['ok' => false, 'error' => 'Invalid file path'], 500);
}

$storageRoot = realpath(__DIR__ . '/../../storage/submissions/file_submission');
if ($storageRoot === false) {
    jsonResponse(['ok' => false, 'error' => 'Storage root not found'], 404);
}

$absolutePath = realpath($storageRoot . '/' . $relativePath);
if ($absolutePath === false || strpos($absolutePath, $storageRoot) !== 0 || !is_file($absolutePath)) {
    jsonResponse(['ok' => false, 'error' => 'Uploaded file not found'], 404);
}

$disposition = strtolower((string)($_GET['disposition'] ?? 'attachment'));
if ($disposition !== 'inline') {
    $disposition = 'attachment';
}

if (!headers_sent()) {
    header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');

    $safeName = preg_replace('/[\r\n"]+/', '_', $originalName);
    if ($safeName === '' || $safeName === null) {
        $safeName = $storedName;
    }
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
}

readfile($absolutePath);
exit;
