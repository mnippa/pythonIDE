<?php
/**
 * Admin: Delete single user
 * POST/DELETE api/admin/users/delete.php?id=X
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

$admin = requireAdmin();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'User ID required'], 400);
}

if ((int)$admin['id'] === $userId) {
    jsonResponse(['ok' => false, 'error' => 'Eigenen Account kannst du nicht löschen'], 400);
}

$findStmt = $conn->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
$findStmt->bind_param('i', $userId);
$findStmt->execute();
$userRow = $findStmt->get_result()->fetch_assoc();

if (!$userRow) {
    jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
}

if (($userRow['role'] ?? '') === 'admin') {
    $countAdminsResult = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
    $adminCount = (int)($countAdminsResult->fetch_assoc()['cnt'] ?? 0);
    if ($adminCount <= 1) {
        jsonResponse(['ok' => false, 'error' => 'Der letzte Admin kann nicht gelöscht werden'], 400);
    }
}

$deleteStmt = $conn->prepare('DELETE FROM users WHERE id = ?');
$deleteStmt->bind_param('i', $userId);

if (!$deleteStmt->execute()) {
    jsonResponse(['ok' => false, 'error' => 'Failed to delete user'], 500);
}

if ($deleteStmt->affected_rows <= 0) {
    jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
}

jsonResponse([
    'ok' => true,
    'message' => 'User deleted',
    'deleted_user_id' => $userId
]);
