<?php
/**
 * Create support ticket
 * POST /api/support_tickets/create.php
 * Required: assignment_id
 * Returns: token (to share with admins)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

try {
    $user = requireAuth();
    $conn = getDbConnection();
    
    $assignmentId = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : null;
    
    if (!$assignmentId) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id required'], 400);
    }
    
    // Check if assignment exists
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Assignment lookup prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
    }
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    
    // Optional description
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    if ($description && strlen($description) > 500) {
        $description = substr($description, 0, 500);
    }
    
    // Check token uniqueness (ultra-rare collision guard)
    $checkStmt = $conn->prepare('SELECT id FROM support_tickets WHERE token = ? LIMIT 1');
    if (!$checkStmt) {
        throw new Exception('Token check prepare failed: ' . $conn->error);
    }
    for ($i = 0; $i < 3; $i++) {
        $checkStmt->bind_param('s', $token);
        $checkStmt->execute();
        if (!$checkStmt->get_result()->fetch_assoc()) {
            break; // Token is unique
        }
        $token = bin2hex(random_bytes(32)); // Regenerate
    }
    
    // Insert ticket
    $insertStmt = $conn->prepare(
        'INSERT INTO support_tickets (user_id, assignment_id, token, description) VALUES (?, ?, ?, ?)'
    );
    if (!$insertStmt) {
        throw new Exception('Insert prepare failed: ' . $conn->error);
    }
    $insertStmt->bind_param('iiss', $user['id'], $assignmentId, $token, $description);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Insert failed: ' . $conn->error);
    }
    
    jsonResponse([
        'ok' => true,
        'token' => $token,
        'message' => 'Ticket created. Share the token with admins.'
    ]);
    
} catch (Exception $e) {
    error_log('Support ticket create error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to create ticket: ' . $e->getMessage()], 500);
}
