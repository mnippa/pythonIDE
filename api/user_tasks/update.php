<?php
/**
 * Update or create user task progress
 * Users can update their own task progress
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$taskId = isset($input['task_id']) ? (int)$input['task_id'] : null;
if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

$userId = (int)$user['id'];

// Check if user_task entry exists
$stmt = $conn->prepare('SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?');
$stmt->bind_param('ii', $userId, $taskId);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();

$updates = [];
$params = [];
$types = '';

// Status
if (isset($input['status'])) {
    $allowedStatus = ['unbearbeitet', 'in-progress', 'passed', 'failed'];
    if (!in_array($input['status'], $allowedStatus, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }
    $updates[] = 'status = ?';
    $params[] = $input['status'];
    $types .= 's';
    
    // Set completed_at if status is passed or failed
    if (in_array($input['status'], ['passed', 'failed'])) {
        $updates[] = 'completed_at = ?';
        // Use client-provided completed_at if available, otherwise use server time
        $completedAt = isset($input['completed_at']) ? $input['completed_at'] : date('Y-m-d H:i:s');
        $params[] = $completedAt;
        $types .= 's';
    }
}

// Attempts
if (isset($input['attempts'])) {
    $attempts = (int)$input['attempts'];
    if ($attempts < 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid attempts'], 400);
    }
    $updates[] = 'attempts = ?';
    $params[] = $attempts;
    $types .= 'i';
}

// Current code
if (array_key_exists('current_code', $input)) {
    $updates[] = 'current_code = ?';
    $params[] = $input['current_code'];
    $types .= 's';
}

// Hints revealed
if (isset($input['hints_revealed']) && is_array($input['hints_revealed'])) {
    $updates[] = 'hints_revealed = ?';
    $params[] = json_encode($input['hints_revealed']);
    $types .= 's';
}

// Start time - only set if not already set
if (!$existing && isset($input['started_at'])) {
    $updates[] = 'started_at = ?';
    $params[] = $input['started_at'];
    $types .= 's';
}

if ($existing) {
    // Update existing record
    if (empty($updates)) {
        jsonResponse(['ok' => true, 'message' => 'No changes'], 200);
    }
    
    $params[] = $existing['id'];
    $types .= 'i';
    
    $sql = 'UPDATE user_tasks SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        jsonResponse(['ok' => true, 'message' => 'User task updated', 'id' => $existing['id']]);
    } else {
        jsonResponse(['ok' => false, 'error' => 'Failed to update user task'], 500);
    }
} else {
    // Create new record
    $status = isset($input['status']) ? $input['status'] : 'in-progress';
    $attempts = isset($input['attempts']) ? (int)$input['attempts'] : 0;
    $currentCode = isset($input['current_code']) ? $input['current_code'] : null;
    $hintsRevealed = isset($input['hints_revealed']) ? json_encode($input['hints_revealed']) : '[]';
    $startedAt = isset($input['started_at']) ? $input['started_at'] : date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare(
        'INSERT INTO user_tasks (user_id, task_id, status, attempts, current_code, hints_revealed, started_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iisisss', $userId, $taskId, $status, $attempts, $currentCode, $hintsRevealed, $startedAt);
    
    if ($stmt->execute()) {
        jsonResponse(['ok' => true, 'message' => 'User task created', 'id' => $conn->insert_id]);
    } else {
        jsonResponse(['ok' => false, 'error' => 'Failed to create user task'], 500);
    }
}
