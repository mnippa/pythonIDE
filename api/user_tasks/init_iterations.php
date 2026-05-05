<?php
/**
 * Initialize iteration_values for a task when the student first enters it.
 * Generates and persists all iteration input sets (client-side randomizer results).
 * Only writes if iteration_values is currently NULL — never overwrites existing data.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$conn = getDbConnection();
$user = requireAuth();
$userId = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
$iterationValues = $input['iteration_values'] ?? null;

if ($taskId <= 0 || !is_array($iterationValues) || empty($iterationValues)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid task_id / iteration_values']);
    exit;
}

// Validate structure: each entry must have iteration (int) and inputs (object)
foreach ($iterationValues as $entry) {
    if (!isset($entry['iteration']) || !isset($entry['inputs']) || !is_array($entry['inputs'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid iteration_values structure']);
        exit;
    }
}

$iterationValuesJson = json_encode($iterationValues);

// INSERT row if not exists; on duplicate key: only update iteration_values if it was NULL
$stmt = $conn->prepare(
    'INSERT INTO user_tasks (user_id, task_id, iteration_values, started_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
         iteration_values = IF(iteration_values IS NULL, VALUES(iteration_values), iteration_values)'
);

$stmt->bind_param('iis', $userId, $taskId, $iterationValuesJson);

if ($stmt->execute()) {
    // Return the current iteration_values (may have been kept if already set)
    $readStmt = $conn->prepare('SELECT iteration_values FROM user_tasks WHERE user_id = ? AND task_id = ?');
    $readStmt->bind_param('ii', $userId, $taskId);
    $readStmt->execute();
    $row = $readStmt->get_result()->fetch_assoc();

    $storedValues = null;
    if ($row && $row['iteration_values']) {
        $storedValues = json_decode($row['iteration_values'], true);
    }

    echo json_encode(['ok' => true, 'iteration_values' => $storedValues]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $conn->error]);
}
