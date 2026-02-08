<?php
/**
 * List Projects API (user's own projects)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

// Get user's projects
$stmt = $conn->prepare('
    SELECT id, name, description, visibility, share_token, created_at, updated_at 
    FROM projects 
    WHERE user_id = ? 
    ORDER BY updated_at DESC
');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'visibility' => $row['visibility'],
        'share_token' => $row['share_token'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

jsonResponse([
    'ok' => true,
    'projects' => $projects,
    'count' => count($projects)
]);
