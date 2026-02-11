<?php
/**
 * Delete Team API
 * POST api/admin/teams/delete.php?id=X
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
    $teamId = $_GET['id'] ?? null;
    if (!$teamId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Team ID required']);
        exit;
    }
    
    require_once __DIR__ . '/../../../config/database.php';
    $conn = getDbConnection();
    
    // Check if team has users
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE team_id = ?');
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false, 
            'error' => 'Cannot delete team with assigned users. Please reassign users first.'
        ]);
        exit;
    }
    
    // Delete team
    $stmt = $conn->prepare('DELETE FROM teams WHERE id = ?');
    $stmt->bind_param('i', $teamId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete team: ' . $stmt->error);
    }
    
    echo json_encode(['ok' => true, 'message' => 'Team deleted successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
