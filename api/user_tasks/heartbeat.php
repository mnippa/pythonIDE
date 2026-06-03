<?php
// api/user_tasks/heartbeat.php
// Receive activity heartbeat from client: accumulate active_seconds for a task

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    error_log('heartbeat.php invalid JSON: ' . $rawInput);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

if (!isset($data['task_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task_id']);
    exit;
}

$task_id = (int)$data['task_id'];
$active_seconds_delta = isset($data['active_seconds_delta']) ? (int)$data['active_seconds_delta'] : 0;
$is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
$user_id = $_SESSION['user_id'];

if (isset($_GET['test_user_id'])) {
    $testUserId = (int)$_GET['test_user_id'];

    if (($_SESSION['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized: Admin access required for test_user_id']);
        exit;
    }

    if ($testUserId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid test_user_id']);
        exit;
    }
}

try {
    $conn = getDbConnection();

    if (isset($testUserId)) {
        $userCheckStmt = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $userCheckStmt->bind_param('i', $testUserId);
        $userCheckStmt->execute();
        $exists = $userCheckStmt->get_result()->fetch_assoc();

        if (!$exists) {
            http_response_code(404);
            echo json_encode(['error' => 'Test user not found']);
            exit;
        }

        $user_id = $testUserId;
    }

    // Check if active_seconds column exists
    $checkCol = $conn->query("SHOW COLUMNS FROM user_tasks WHERE Field = 'active_seconds'");
    $hasActiveSeconds = $checkCol && $checkCol->num_rows > 0;

    if (!$hasActiveSeconds) {
        // Column doesn't exist yet, silently ignore
        echo json_encode(['ok' => true]);
        $conn->close();
        exit;
    }

    // Insert or update activity time (creates row if missing)
    $stmt = $conn->prepare("
        INSERT INTO user_tasks (user_id, task_id, status, attempts, active_seconds, is_active, last_active_at, started_at)
        VALUES (?, ?, 'in-progress', 0, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            active_seconds = active_seconds + IF(status IN ('passed', 'failed'), 0, VALUES(active_seconds)),
            is_active = IF(status IN ('passed', 'failed'), 0, VALUES(is_active)),
            last_active_at = IF(status IN ('passed', 'failed'), last_active_at, NOW())
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('iiii', $user_id, $task_id, $active_seconds_delta, $is_active);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
    // Don't close $conn - it's a pooled connection from getDbConnection()

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
