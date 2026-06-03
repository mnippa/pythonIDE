<?php
/**
 * Upload endpoint for file_submission tasks.
 * Stores uploaded file on disk and metadata in user_tasks.variable_values.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
if ($taskId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['ok' => false, 'error' => 'file required'], 400);
}

$uploaded = $_FILES['file'];
if (!is_array($uploaded) || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonResponse(['ok' => false, 'error' => 'Upload failed'], 400);
}

$effectiveUserId = (int)$user['id'];
if (isset($_POST['test_user_id'])) {
    $testUserId = (int)$_POST['test_user_id'];
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

$taskStmt = $conn->prepare(
    'SELECT id, task_type, file_submission_allowed_types, file_submission_max_size_bytes
     FROM tasks WHERE id = ? LIMIT 1'
);
$taskStmt->bind_param('i', $taskId);
$taskStmt->execute();
$task = $taskStmt->get_result()->fetch_assoc();
if (!$task) {
    jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
}

if (($task['task_type'] ?? '') !== 'file_submission') {
    jsonResponse(['ok' => false, 'error' => 'Task is not a file_submission task'], 400);
}

$allowedRaw = trim((string)($task['file_submission_allowed_types'] ?? ''));
$allowedTypes = [];
if ($allowedRaw !== '') {
    foreach (explode(',', $allowedRaw) as $ext) {
        $normalized = strtolower(trim($ext));
        $normalized = ltrim($normalized, '.');
        if ($normalized !== '' && preg_match('/^[a-z0-9]+$/', $normalized)) {
            $allowedTypes[] = $normalized;
        }
    }
}
if (count($allowedTypes) === 0) {
    $allowedTypes = ['zip', 'png', 'jpg', 'jpeg'];
}
$allowedTypes = array_values(array_unique($allowedTypes));

$allowedSizes = [51200, 102400, 256000, 1048576, 2097152, 5242880];
$maxSize = (int)($task['file_submission_max_size_bytes'] ?? 102400);
if (!in_array($maxSize, $allowedSizes, true)) {
    $maxSize = 102400;
}

$originalName = (string)($uploaded['name'] ?? 'upload.bin');
$originalName = preg_replace('/[\r\n]+/', ' ', $originalName);
$originalName = trim($originalName);
if ($originalName === '') {
    $originalName = 'upload.bin';
}

$sizeBytes = (int)($uploaded['size'] ?? 0);
if ($sizeBytes <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Uploaded file is empty'], 400);
}
if ($sizeBytes > $maxSize) {
    jsonResponse(['ok' => false, 'error' => 'File exceeds max size'], 400);
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension === '' || !in_array($extension, $allowedTypes, true)) {
    jsonResponse(['ok' => false, 'error' => 'File type not allowed'], 400);
}

$baseName = pathinfo($originalName, PATHINFO_FILENAME);
$baseName = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName);
if ($baseName === '' || $baseName === null) {
    $baseName = 'upload';
}

$uploadRoot = __DIR__ . '/../../storage/submissions/file_submission';
$targetDir = $uploadRoot . '/task_' . $taskId . '/user_' . $effectiveUserId;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    jsonResponse(['ok' => false, 'error' => 'Could not create upload directory'], 500);
}

$storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $baseName . '.' . $extension;
$targetPath = $targetDir . '/' . $storedName;

if (!move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
    jsonResponse(['ok' => false, 'error' => 'Could not move uploaded file'], 500);
}

$mimeType = '';
if (function_exists('mime_content_type')) {
    $mimeDetected = @mime_content_type($targetPath);
    if (is_string($mimeDetected)) {
        $mimeType = $mimeDetected;
    }
}
if ($mimeType === '') {
    $mimeType = (string)($uploaded['type'] ?? 'application/octet-stream');
}

$columnExists = function (mysqli $db, string $table, string $column): bool {
    $safeTable = $db->real_escape_string($table);
    $safeColumn = $db->real_escape_string($column);
    $check = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $check && $check->num_rows > 0;
};

$hasRunCount = $columnExists($conn, 'user_tasks', 'run_count');
$hasCurrentIteration = $columnExists($conn, 'user_tasks', 'current_iteration');

$existingStmt = $conn->prepare('SELECT id, status, variable_values FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');
$existingStmt->bind_param('ii', $effectiveUserId, $taskId);
$existingStmt->execute();
$existing = $existingStmt->get_result()->fetch_assoc();

$now = date('Y-m-d H:i:s');
$relativePath = 'task_' . $taskId . '/user_' . $effectiveUserId . '/' . $storedName;
$submissionPayload = [
    'stored_name' => $storedName,
    'original_name' => $originalName,
    'relative_path' => $relativePath,
    'size_bytes' => $sizeBytes,
    'mime_type' => $mimeType,
    'uploaded_at' => $now
];

$existingValues = [];
if (!empty($existing['variable_values'])) {
    $decoded = json_decode((string)$existing['variable_values'], true);
    if (is_array($decoded)) {
        $existingValues = $decoded;
    }
}
$existingValues['file_submission'] = $submissionPayload;
$variableValuesJson = json_encode($existingValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($existing) {
    $status = (string)($existing['status'] ?? 'in-progress');
    if ($status === '' || $status === 'unbearbeitet') {
        $status = 'in-progress';
    }

    $updateStmt = $conn->prepare('UPDATE user_tasks SET status = ?, variable_values = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $userTaskId = (int)$existing['id'];
    $updateStmt->bind_param('ssi', $status, $variableValuesJson, $userTaskId);
    if (!$updateStmt->execute()) {
        jsonResponse(['ok' => false, 'error' => 'Failed to update user task'], 500);
    }

    jsonResponse([
        'ok' => true,
        'status' => $status,
        'submission' => $submissionPayload
    ]);
}

$status = 'in-progress';
$attempts = 0;
$currentCode = null;
$hintsRevealed = '[]';
$startedAt = $now;

$columns = ['user_id', 'task_id', 'status', 'attempts', 'current_code', 'hints_revealed', 'variable_values', 'started_at'];
$placeholders = ['?', '?', '?', '?', '?', '?', '?', '?'];
$types = 'iisissss';
$values = [$effectiveUserId, $taskId, $status, $attempts, $currentCode, $hintsRevealed, $variableValuesJson, $startedAt];

if ($hasCurrentIteration) {
    $columns[] = 'current_iteration';
    $placeholders[] = '?';
    $types .= 'i';
    $values[] = 1;
}
if ($hasRunCount) {
    $columns[] = 'run_count';
    $placeholders[] = '?';
    $types .= 'i';
    $values[] = 0;
}

$insertSql = 'INSERT INTO user_tasks (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param($types, ...$values);
if (!$insertStmt->execute()) {
    jsonResponse(['ok' => false, 'error' => 'Failed to create user task'], 500);
}

jsonResponse([
    'ok' => true,
    'status' => $status,
    'submission' => $submissionPayload
]);
