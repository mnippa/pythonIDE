<?php
/**
 * List Tasks API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

// Check assignment access
$stmt = $conn->prepare(
    'SELECT a.is_active, ua.user_id AS assigned_user
     FROM assignments a
     LEFT JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
     WHERE a.id = ?'
);
$stmt->bind_param('ii', $user['id'], $assignmentId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
}

$canAccess = $user['role'] === 'admin' || (bool)$assignment['is_active'] || $assignment['assigned_user'] !== null;
if (!$canAccess) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

$includeExpected = $user['role'] === 'admin' && isset($_GET['include_expected']) && $_GET['include_expected'] === '1';

$sql = 'SELECT id, assignment_id, title, description, position, problem_type, code_template, hint, hint1, hint2, hint3, stoff, max_attempts, test_cases, validation_mode';
if ($includeExpected) {
    $sql .= ', expected_output, solution_code';
}
$sql .= ' FROM tasks WHERE assignment_id = ? ORDER BY position ASC';

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $task = [
        'id' => (int)$row['id'],
        'assignment_id' => (int)$row['assignment_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'position' => (int)$row['position'],
        'problem_type' => $row['problem_type'],
        'code_template' => $row['code_template'],
        'hint' => $row['hint'],
        'hint1' => $row['hint1'],
        'hint2' => $row['hint2'],
        'hint3' => $row['hint3'],
        'stoff' => $row['stoff'],
        'max_attempts' => (int)$row['max_attempts'],
        'test_cases' => $row['test_cases'],
        'validation_mode' => $row['validation_mode']
    ];
    if ($includeExpected) {
        $task['expected_output'] = $row['expected_output'];
        $task['solution_code'] = $row['solution_code'];
    }
    $tasks[] = $task;
}

jsonResponse([
    'ok' => true,
    'assignment_id' => $assignmentId,
    'tasks' => $tasks,
    'count' => count($tasks)
]);
