<?php
/**
 * Delete Assignment API (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$assignmentId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }
    $assignmentId = isset($input['id']) ? (int)$input['id'] : null;
}

if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

requireAdminOwnedAssignment($conn, $assignmentId, $user);

$stmt = $conn->prepare('DELETE FROM assignments WHERE id = ?');
$stmt->bind_param('i', $assignmentId);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'Assignment deleted']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to delete assignment'], 500);
}
