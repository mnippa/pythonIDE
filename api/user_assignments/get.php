<?php
/**
 * Get single user assignment
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;

if (!$id && !$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'ID or assignment_id required'], 400);
}

if ($id) {
    $sql = '
        SELECT 
            ua.id,
            ua.user_id,
            ua.assignment_id,
            ua.status,
            ua.current_code,
            ua.test_results,
            ua.attempts,
            ua.assigned_at,
            ua.submitted_at,
            a.title AS assignment_title,
            a.difficulty AS assignment_difficulty
        FROM user_assignments ua
        JOIN assignments a ON a.id = ua.assignment_id
        WHERE ua.id = ?
    ';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
} else {
    $sql = '
        SELECT 
            ua.id,
            ua.user_id,
            ua.assignment_id,
            ua.status,
            ua.current_code,
            ua.test_results,
            ua.attempts,
            ua.assigned_at,
            ua.submitted_at,
            a.title AS assignment_title,
            a.difficulty AS assignment_difficulty
        FROM user_assignments ua
        JOIN assignments a ON a.id = ua.assignment_id
        WHERE ua.assignment_id = ? AND ua.user_id = ?
    ';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $assignmentId, $user['id']);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'User assignment not found'], 404);
}

$item = $result->fetch_assoc();

if ($user['role'] !== 'admin' && (int)$item['user_id'] !== (int)$user['id']) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

jsonResponse([
    'ok' => true,
    'item' => [
        'id' => (int)$item['id'],
        'user_id' => (int)$item['user_id'],
        'assignment_id' => (int)$item['assignment_id'],
        'status' => $item['status'],
        'current_code' => $item['current_code'],
        'test_results' => $item['test_results'],
        'attempts' => (int)$item['attempts'],
        'assigned_at' => $item['assigned_at'],
        'submitted_at' => $item['submitted_at'],
        'assignment_title' => $item['assignment_title'],
        'assignment_difficulty' => $item['assignment_difficulty']
    ]
]);
