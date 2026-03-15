<?php
/**
 * Delete Task API (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$taskId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }
    $taskId = isset($input['id']) ? (int)$input['id'] : null;
}

if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

requireAdminOwnedTask($conn, $taskId, $user);

$stmt = $conn->prepare('DELETE FROM tasks WHERE id = ?');
$stmt->bind_param('i', $taskId);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'Task deleted']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to delete task'], 500);
}
