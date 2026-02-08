<?php
/**
 * Update user assignment
 * Admin: update any fields
 * User: update status (assigned/in_progress/submitted) and current_code
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$id = isset($input['id']) ? (int)$input['id'] : null;
if (!$id) {
    jsonResponse(['ok' => false, 'error' => 'ID required'], 400);
}

$stmt = $conn->prepare('SELECT * FROM user_assignments WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'User assignment not found'], 404);
}

$current = $result->fetch_assoc();
$isOwner = (int)$current['user_id'] === (int)$user['id'];
$isAdmin = $user['role'] === 'admin';

if (!$isAdmin && !$isOwner) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

$allowedStatusAll = ['assigned', 'in_progress', 'submitted', 'passed', 'failed'];
$allowedStatusUser = ['assigned', 'in_progress', 'submitted'];

$updates = [];
$params = [];
$types = '';

if (isset($input['status'])) {
    $status = $input['status'];
    if (!in_array($status, $allowedStatusAll, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }
    if (!$isAdmin && !in_array($status, $allowedStatusUser, true)) {
        jsonResponse(['ok' => false, 'error' => 'Status not allowed'], 403);
    }
    $updates[] = 'status = ?';
    $params[] = $status;
    $types .= 's';

    if (in_array($status, ['submitted', 'passed', 'failed'], true)) {
        $updates[] = 'submitted_at = ?';
        $params[] = date('Y-m-d H:i:s');
        $types .= 's';
    }
}

if (array_key_exists('current_code', $input)) {
    if (!$isAdmin && !$isOwner) {
        jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
    }
    $updates[] = 'current_code = ?';
    $params[] = $input['current_code'];
    $types .= 's';
}

if ($isAdmin && array_key_exists('test_results', $input)) {
    $updates[] = 'test_results = ?';
    $params[] = $input['test_results'];
    $types .= 's';
}

if ($isAdmin && array_key_exists('attempts', $input)) {
    $attempts = (int)$input['attempts'];
    if ($attempts < 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid attempts'], 400);
    }
    $updates[] = 'attempts = ?';
    $params[] = $attempts;
    $types .= 'i';
}

if (empty($updates)) {
    jsonResponse(['ok' => false, 'error' => 'No fields to update'], 400);
}

$params[] = $id;
$types .= 'i';

$sql = 'UPDATE user_assignments SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'User assignment updated']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to update user assignment'], 500);
}
