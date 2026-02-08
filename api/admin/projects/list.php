<?php
/**
 * Admin: List all projects
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

$stmt = $conn->prepare('
    SELECT p.id, p.name, p.description, p.visibility, p.created_at, p.updated_at,
           u.id AS user_id, u.email, u.first_name, u.last_name
    FROM projects p
    JOIN users u ON u.id = p.user_id
    ORDER BY p.updated_at DESC
');
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'visibility' => $row['visibility'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'user_id' => (int)$row['user_id'],
        'user_email' => $row['email'],
        'user_name' => trim($row['first_name'] . ' ' . $row['last_name'])
    ];
}

jsonResponse([
    'ok' => true,
    'projects' => $items,
    'count' => count($items)
]);
