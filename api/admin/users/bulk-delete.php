<?php
/**
 * Admin: Bulk delete users
 * POST api/admin/users/bulk-delete.php
 * Body: { "user_ids": [1,2,3] }
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

$admin = requireAdmin();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$userIds = $input['user_ids'] ?? [];
if (!is_array($userIds) || count($userIds) === 0) {
    jsonResponse(['ok' => false, 'error' => 'user_ids required'], 400);
}

$userIds = array_values(array_unique(array_map(static function ($id) {
    return (int)$id;
}, $userIds)));
$userIds = array_values(array_filter($userIds, static function ($id) {
    return $id > 0;
}));

if (count($userIds) === 0) {
    jsonResponse(['ok' => false, 'error' => 'No valid user IDs'], 400);
}

$blocked = [];
$deletable = [];

$placeholders = implode(',', array_fill(0, count($userIds), '?'));
$types = str_repeat('i', count($userIds));
$query = "SELECT id, role, email FROM users WHERE id IN ($placeholders)";
$findStmt = $conn->prepare($query);
$findStmt->bind_param($types, ...$userIds);
$findStmt->execute();
$result = $findStmt->get_result();

$foundById = [];
while ($row = $result->fetch_assoc()) {
    $foundById[(int)$row['id']] = $row;
}

$missingIds = [];
foreach ($userIds as $id) {
    if (!isset($foundById[$id])) {
        $missingIds[] = $id;
    }
}

$countAdminsResult = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
$currentAdminCount = (int)($countAdminsResult->fetch_assoc()['cnt'] ?? 0);
$adminDeletesRequested = 0;

foreach ($userIds as $id) {
    if ($id === (int)$admin['id']) {
        $blocked[] = ['id' => $id, 'reason' => 'self'];
        continue;
    }

    if (!isset($foundById[$id])) {
        continue;
    }

    $row = $foundById[$id];
    if (($row['role'] ?? '') === 'admin') {
        $adminDeletesRequested++;
    }

    $deletable[] = $id;
}

if ($currentAdminCount - $adminDeletesRequested < 1) {
    $deletable = array_values(array_filter($deletable, function ($id) use (&$blocked, $foundById) {
        $row = $foundById[$id] ?? null;
        if ($row && ($row['role'] ?? '') === 'admin') {
            $blocked[] = ['id' => $id, 'reason' => 'last_admin_protection'];
            return false;
        }
        return true;
    }));
}

$deletedCount = 0;
$deletedIds = [];

if (count($deletable) > 0) {
    $deletePlaceholders = implode(',', array_fill(0, count($deletable), '?'));
    $deleteTypes = str_repeat('i', count($deletable));
    $deleteSql = "DELETE FROM users WHERE id IN ($deletePlaceholders)";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param($deleteTypes, ...$deletable);
    $deleteStmt->execute();

    $deletedCount = (int)$deleteStmt->affected_rows;
    $deletedIds = $deletable;
}

jsonResponse([
    'ok' => true,
    'deleted_count' => $deletedCount,
    'deleted_ids' => $deletedIds,
    'blocked' => $blocked,
    'missing_ids' => $missingIds
]);
