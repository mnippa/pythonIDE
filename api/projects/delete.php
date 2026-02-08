<?php
/**
 * Delete Project API
 * Access: Owner or Admin only
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

// Allow POST or DELETE
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Also check JSON body
if (!$projectId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $projectId = isset($input['id']) ? (int)$input['id'] : null;
}

if (!$projectId) {
    jsonResponse(['ok' => false, 'error' => 'Project ID required'], 400);
}

// Load project to check ownership
$stmt = $conn->prepare('SELECT user_id FROM projects WHERE id = ?');
$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Project not found'], 404);
}

$project = $result->fetch_assoc();

// Check access (owner or admin)
$isOwner = $user['id'] == $project['user_id'];
$isAdmin = $user['role'] === 'admin';

if (!$isOwner && !$isAdmin) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

// Delete project
$stmt = $conn->prepare('DELETE FROM projects WHERE id = ?');
$stmt->bind_param('i', $projectId);

if ($stmt->execute()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Project deleted successfully'
    ]);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to delete project'], 500);
}
