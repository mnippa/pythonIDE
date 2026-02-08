<?php
/**
 * Delete user assignment (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }
    $id = isset($input['id']) ? (int)$input['id'] : null;
}

if (!$id) {
    jsonResponse(['ok' => false, 'error' => 'ID required'], 400);
}

$stmt = $conn->prepare('SELECT id FROM user_assignments WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'User assignment not found'], 404);
}

$stmt = $conn->prepare('DELETE FROM user_assignments WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'User assignment deleted']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to delete user assignment'], 500);
}
