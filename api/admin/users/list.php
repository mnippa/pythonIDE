<?php
/**
 * Admin: List users
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

$stmt = $conn->prepare('
    SELECT id, email, first_name, last_name, role, status, registration_date, created_at, last_login
    FROM users
    ORDER BY created_at DESC
');
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'email' => $row['email'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'role' => $row['role'],
        'status' => $row['status'] ?? 'aktiv',
        'registration_date' => $row['registration_date'],
        'created_at' => $row['created_at'],
        'last_login' => $row['last_login']
    ];
}

jsonResponse([
    'ok' => true,
    'users' => $items,
    'count' => count($items)
]);
