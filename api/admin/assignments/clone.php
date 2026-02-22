<?php
/**
 * Clone Assignment with all Tasks
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Config load failed: ' . $e->getMessage()]);
    exit;
}

// Get assignment_id from query parameter
$assignmentId = $_GET['id'] ?? null;

if (!$assignmentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Assignment ID required. Use ?id=X']);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get original assignment
    $stmt = $conn->prepare('SELECT * FROM assignments WHERE id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        throw new Exception('Assignment not found');
    }
    
    // Create new assignment with "(Copy)" suffix
    $newTitle = $assignment['title'] . ' (Copy)';
    $stmt = $conn->prepare('INSERT INTO assignments (title, description, difficulty, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $userId = $_SESSION['user_id'];
    $stmt->bind_param('sssii', $newTitle, $assignment['description'], $assignment['difficulty'], $assignment['is_active'], $userId);
    if (!$stmt->execute()) {
        throw new Exception('Insert assignment failed: ' . $stmt->error);
    }
    
    $newAssignmentId = $conn->insert_id;
    if ($newAssignmentId === 0) {
        throw new Exception('Failed to get new assignment ID');
    }
    
    $stmt->close();
    
    // Get all tasks from original assignment
    $stmt = $conn->prepare('SELECT * FROM tasks WHERE assignment_id = ? ORDER BY position ASC');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $tasksResult = $stmt->get_result();
    $tasks = [];
    while ($row = $tasksResult->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
    
    // Clone each task
    $position = 1;
    foreach ($tasks as $task) {
        $stmt = $conn->prepare('INSERT INTO tasks (
            assignment_id, title, description, problem_type, code_template, 
            hint1, hint2, hint3, stoff, 
            expected_output, solution_code, max_attempts, 
            test_cases, position, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        
        $stmt->bind_param(
            'isssssssssisi',
            $newAssignmentId,
            $task['title'],
            $task['description'],
            $task['problem_type'],
            $task['code_template'],
            $task['hint1'],
            $task['hint2'],
            $task['hint3'],
            $task['stoff'],
            $task['expected_output'],
            $task['solution_code'],
            $task['max_attempts'],
            $task['test_cases'],
            $position
        );
        
        $stmt->execute();
        $newTaskId = $conn->insert_id;
        $stmt->close();
        
        // Clone test cases if they exist
        if (!empty($task['test_cases'])) {
            // Test cases are stored as JSON in the tasks table, so they're already copied
        }
        
        $position++;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Assignment cloned successfully',
        'new_assignment_id' => $newAssignmentId,
        'task_count' => count($tasks)
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
