<?php
/**
 * Persist user's last opened project.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$user = requireAuth();
$conn = getDbConnection();

$payload = json_decode(file_get_contents('php://input'), true);
$projectId = isset($payload['project_id']) ? (int)$payload['project_id'] : 0;

if ($projectId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid project_id'], 400);
}

$isAdmin = ($user['role'] ?? '') === 'admin';

if ($isAdmin) {
    $checkStmt = $conn->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
    $checkStmt->bind_param('i', $projectId);
} else {
    $checkStmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
    $checkStmt->bind_param('ii', $projectId, $user['id']);
}

$checkStmt->execute();
$exists = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$exists) {
    jsonResponse(['ok' => false, 'error' => 'Project not found'], 404);
}

try {
    $updateStmt = $conn->prepare('UPDATE users SET last_opened_project_id = ? WHERE id = ?');
    $updateStmt->bind_param('ii', $projectId, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => 'Could not persist last opened project'], 500);
}

jsonResponse([
    'ok' => true,
    'last_opened_project_id' => $projectId
]);
