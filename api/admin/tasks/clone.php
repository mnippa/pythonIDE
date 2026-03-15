<?php
/**
 * Clone Task within the same assignment
 */

header('Content-Type: application/json; charset=utf-8');

// Manual authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../auth/middleware.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Config load failed: ' . $e->getMessage()]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$taskId = $input['task_id'] ?? null;
$assignmentId = $input['assignment_id'] ?? null;

if (!$taskId || !$assignmentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'task_id and assignment_id required']);
    exit;
}

try {
    $admin = requireAdmin();
    $conn = getDbConnection();
    $ownedTask = requireAdminOwnedTask($conn, (int)$taskId, $admin);
    requireAdminOwnedAssignment($conn, (int)$assignmentId, $admin);
    if ((int)$ownedTask['assignment_id'] !== (int)$assignmentId) {
        throw new Exception('Task does not belong to this assignment');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get original task
    $stmt = $conn->prepare('SELECT * FROM tasks WHERE id = ? AND assignment_id = ?');
    $stmt->bind_param('ii', $taskId, $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();
    
    if (!$task) {
        throw new Exception('Task not found');
    }
    
    // Get max position for this assignment
    $stmt = $conn->prepare('SELECT MAX(position) as max_pos FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nextPosition = ($row['max_pos'] ?? 0) + 1;
    $stmt->close();
    
    // Create new task with "(Copy)" suffix
    $newTitle = $task['title'] . ' (Copy)';
    
    // Check if task has new task_type field, use it if available, otherwise use problem_type
    $taskType = $task['task_type'] ?? $task['problem_type'] ?? 'code';
    
    // Ensure numeric fields are properly typed
    $maxAttempts = $task['max_attempts'] ?? null;
    $iterationsCount = $task['iterations_count'] ?? null;
    $showSolution = isset($task['show_solution']) ? (int)$task['show_solution'] : 0;
    $showSolutionCode = isset($task['show_solution_code']) ? (int)$task['show_solution_code'] : 0;
    $minKeywordsRequired = $task['min_keywords_required'] ?? null;
    
    // Insert cloned task with correct field names from create.php
    $stmt = $conn->prepare('INSERT INTO tasks (
        assignment_id, title, description, position, 
        max_attempts, iterations_count, show_solution, show_solution_code, min_keywords_required, 
        problem_type, code_template, 
        hint1, hint2, hint3, stoff, 
        expected_output, test_cases, solution_code, 
        task_type, task_text, question_text, 
        image_url, correct_answer, variable_overrides, randomizer_code, 
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    // Type string: i ss i iii ii s s sss s s s s s s s s s s s
    // Total: 25 parameters
    $stmt->bind_param(
        'issiiiiisssssssssssssssss',
        $assignmentId,                  // i
        $newTitle,                      // s
        $task['description'],           // s
        $nextPosition,                  // i
        $maxAttempts,                   // i
        $iterationsCount,               // i
        $showSolution,                  // i
        $showSolutionCode,              // i
        $minKeywordsRequired,           // i
        $task['problem_type'],          // s
        $task['code_template'],         // s
        $task['hint1'],                 // s
        $task['hint2'],                 // s
        $task['hint3'],                 // s
        $task['stoff'],                 // s
        $task['expected_output'],       // s
        $task['test_cases'],            // s
        $task['solution_code'],         // s
        $taskType,                      // s
        $task['task_text'],             // s
        $task['question_text'],         // s
        $task['image_url'],             // s
        $task['correct_answer'],        // s
        $task['variable_overrides'],    // s
        $task['randomizer_code']        // s
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Insert task failed: ' . $stmt->error);
    }
    
    $newTaskId = $conn->insert_id;
    if ($newTaskId === 0) {
        throw new Exception('Failed to get new task ID');
    }
    
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Task cloned successfully',
        'new_task_id' => $newTaskId,
        'position' => $nextPosition
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log detailed error for debugging
    error_log('Task clone error: ' . $e->getMessage());
    error_log('Task ID: ' . ($taskId ?? 'null'));
    error_log('Assignment ID: ' . ($assignmentId ?? 'null'));
    
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
