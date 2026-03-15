<?php
/**
 * List Projects API
 * - Teilnehmer: eigene Projekte
 * - Admin: alle Projekte
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$isAdmin = ($user['role'] ?? '') === 'admin';
$showAll = isset($_GET['all']) && $_GET['all'] === '1';
$canSeeAll = $isAdmin && $showAll;
$lastOpenedProjectId = null;

try {
    $lastStmt = $conn->prepare('SELECT last_opened_project_id FROM users WHERE id = ? LIMIT 1');
    if ($lastStmt) {
        $lastStmt->bind_param('i', $user['id']);
        $lastStmt->execute();
        $lastResult = $lastStmt->get_result();
        if ($lastRow = $lastResult->fetch_assoc()) {
            $lastOpenedProjectId = isset($lastRow['last_opened_project_id']) ? (int)$lastRow['last_opened_project_id'] : null;
            if ($lastOpenedProjectId <= 0) {
                $lastOpenedProjectId = null;
            }
        }
        $lastStmt->close();
    }
} catch (Throwable $e) {
    // Backward compatible when column does not exist yet.
    $lastOpenedProjectId = null;
}

if ($canSeeAll) {
    $stmt = $conn->prepare('
        SELECT p.id, p.name, p.description, p.project_type, p.visibility, p.share_token, p.created_at, p.updated_at,
               p.user_id, u.email as owner_email, u.first_name as owner_first_name, u.last_name as owner_last_name
        FROM projects p
        JOIN users u ON u.id = p.user_id
        ORDER BY p.updated_at DESC
    ');
} else {
    $stmt = $conn->prepare('
        SELECT p.id, p.name, p.description, p.project_type, p.visibility, p.share_token, p.created_at, p.updated_at,
               p.user_id, u.email as owner_email, u.first_name as owner_first_name, u.last_name as owner_last_name
        FROM projects p
        JOIN users u ON u.id = p.user_id
        WHERE p.user_id = ?
        ORDER BY p.updated_at DESC
    ');
    $stmt->bind_param('i', $user['id']);
}
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $ownerName = trim(($row['owner_first_name'] ?? '') . ' ' . ($row['owner_last_name'] ?? ''));
    if ($ownerName === '') {
        $ownerName = $row['owner_email'] ?? '';
    }

    $projects[] = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'project_type' => $row['project_type'],
        'visibility' => $row['visibility'],
        'share_token' => $row['share_token'],
        'owner_name' => $ownerName,
        'owner_email' => $row['owner_email'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

jsonResponse([
    'ok' => true,
    'projects' => $projects,
    'last_opened_project_id' => $lastOpenedProjectId,
    'count' => count($projects),
    'scope' => $canSeeAll ? 'all' : 'own'
]);
