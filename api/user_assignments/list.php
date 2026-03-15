<?php
/**
 * List user assignments
 * Admin: list by user_id or assignment_id
 * User: list own assignments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filterAssignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

$allowedStatus = ['assigned', 'in_progress', 'submitted', 'passed', 'failed'];
if ($statusFilter !== null && !in_array($statusFilter, $allowedStatus, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid status filter'], 400);
}

if ($user['role'] !== 'admin' || !$showAll) {
    $filterUserId = $user['id'];
    $filterAssignmentId = null;
}

$sql = '
    SELECT 
        ua.id,
        ua.user_id,
        ua.assignment_id,
        ua.status,
        ua.attempts,
        ua.assigned_at,
        ua.submitted_at,
        a.title AS assignment_title,
        a.difficulty AS assignment_difficulty,
        u.email AS user_email,
        u.first_name,
        u.last_name
    FROM user_assignments ua
    JOIN assignments a ON a.id = ua.assignment_id
    JOIN users u ON u.id = ua.user_id
    WHERE 1 = 1
';

$params = [];
$types = '';

if ($filterUserId) {
    $sql .= ' AND ua.user_id = ?';
    $params[] = $filterUserId;
    $types .= 'i';
}

if ($filterAssignmentId) {
    $sql .= ' AND ua.assignment_id = ?';
    $params[] = $filterAssignmentId;
    $types .= 'i';
}

if ($statusFilter) {
    $sql .= ' AND ua.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= ' ORDER BY ua.assigned_at DESC';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'assignment_id' => (int)$row['assignment_id'],
        'status' => $row['status'],
        'attempts' => (int)$row['attempts'],
        'assigned_at' => $row['assigned_at'],
        'submitted_at' => $row['submitted_at'],
        'assignment_title' => $row['assignment_title'],
        'assignment_difficulty' => $row['assignment_difficulty'],
        'user_email' => $row['user_email'],
        'user_name' => trim($row['first_name'] . ' ' . $row['last_name'])
    ];
}

jsonResponse([
    'ok' => true,
    'items' => $items,
    'count' => count($items)
]);
