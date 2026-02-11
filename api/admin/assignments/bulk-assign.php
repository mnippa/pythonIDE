<?php
/**
 * Bulk Assign Users/Team to Assignment
 * POST api/admin/assignments/bulk-assign.php
 * Body: {
 *   "assignment_id": 1,
 *   "team_id": 2,           // ODER
 *   "user_ids": [1,2,3],    // Array von User IDs
 *   "due_date": "2025-12-31 23:59:59" // Optional
 * }
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['assignment_id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Assignment ID required']);
        exit;
    }
    
    $assignmentId = (int)$data['assignment_id'];
    $assignedBy = $_SESSION['user_id'];
    $dueDate = $data['due_date'] ?? null;
    
    require_once __DIR__ . '/../../../config/database.php';
    $conn = getDbConnection();
    
    $conn->begin_transaction();
    
    $assignedCount = 0;
    
    // Option 1: Assign to entire team
    if (isset($data['team_id']) && $data['team_id']) {
        $teamId = (int)$data['team_id'];
        
        // Check if already assigned
        $check = $conn->prepare('SELECT id FROM user_assignments WHERE assignment_id = ? AND team_id = ?');
        $check->bind_param('ii', $assignmentId, $teamId);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        
        if (!$exists) {
            $stmt = $conn->prepare('INSERT INTO user_assignments (assignment_id, team_id, assigned_by, due_date) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiis', $assignmentId, $teamId, $assignedBy, $dueDate);
            $stmt->execute();
            $assignedCount = 1;
        }
    }
    // Option 2: Assign to individual users
    elseif (isset($data['user_ids']) && is_array($data['user_ids'])) {
        $stmt = $conn->prepare('INSERT IGNORE INTO user_assignments (assignment_id, user_id, assigned_by, due_date) VALUES (?, ?, ?, ?)');
        
        foreach ($data['user_ids'] as $userId) {
            $userId = (int)$userId;
            $stmt->bind_param('iiis', $assignmentId, $userId, $assignedBy, $dueDate);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $assignedCount++;
            }
        }
    } else {
        throw new Exception('Either team_id or user_ids[] required');
    }
    
    $conn->commit();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Assignment assigned successfully',
        'assigned_count' => $assignedCount
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
