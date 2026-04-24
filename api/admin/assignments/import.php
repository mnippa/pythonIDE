<?php
/**
 * Import Assignment with all Tasks from JSON
 */

// Start output buffering to prevent any premature output
ob_start();

// Log errors to file instead of displaying
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../storage/php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../config/database.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Config load failed: ' . $e->getMessage()]);
    exit;
}

// Manual authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

// Get assignment_id from query parameter
$assignmentId = $_GET['assignment_id'] ?? null;

if (!$assignmentId) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Assignment ID required. Use ?assignment_id=X']);
    exit;
}

// Get JSON from request body
$json = file_get_contents('php://input');

if (empty($json)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No data received']);
    exit;
}

$data = json_decode($json, true);

if (!$data) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

// Validate structure
if (!isset($data['version']) || !isset($data['title'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid import format. Required: version, title']);
    exit;
}

// Extract task fields
$position = $data['position'] ?? 1;
$title = $data['title'];
$description = $data['description'] ?? '';
$problemType = $data['problem_type'] ?? 'code_completion';
$codeTemplate = $data['code_template'] ?? '';
$hint1 = $data['hint1'] ?? null;
$hint2 = $data['hint2'] ?? null;
$hint3 = $data['hint3'] ?? null;
$stoff = $data['stoff'] ?? null;
$expectedOutput = $data['expected_output'] ?? null;
$solutionCode = $data['solution_code'] ?? null;
$maxAttempts = $data['max_attempts'] ?? null;
$testCasesJson = isset($data['test_cases']) ? json_encode($data['test_cases']) : null;

// Validate task fields
if (empty($title)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Task title is required']);
    exit;
}

try {
    // Get mysqli connection
    $conn = getDbConnection();
    
    if (!$conn) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Verify assignment exists
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        $conn->rollback();
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Assignment not found']);
        exit;
    }

    // Always append task at end
    $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) AS max_pos FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = (int)($row['max_pos'] ?? 0) + 1;
    
    // Insert task
    $stmt = $conn->prepare('
        INSERT INTO tasks 
        (assignment_id, position, title, description, problem_type, code_template, hint1, hint2, hint3, stoff, expected_output, test_cases, solution_code, max_attempts, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ');
    
    if (!$stmt) {
        $conn->rollback();
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('iisssssssssssi',
        $assignmentId,
        $position,
        $title,
        $description,
        $problemType,
        $codeTemplate,
        $hint1,
        $hint2,
        $hint3,
        $stoff,
        $expectedOutput,
        $testCasesJson,
        $solutionCode,
        $maxAttempts
    );
    
    if (!$stmt->execute()) {
        $conn->rollback();
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    
    $taskId = $conn->insert_id;
    
    if ($taskId == 0) {
        $conn->rollback();
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Task insert failed: insert_id is 0']);
        exit;
    }
    
    $conn->commit();
    
    // Clear buffer and send JSON
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'task_id' => $taskId,
        'message' => 'Task imported successfully'
    ]);
    exit;
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
    exit;
}
