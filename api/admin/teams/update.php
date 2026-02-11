<?php
/**
 * Update Team API
 * POST api/admin/teams/update.php?id=X
 * Body: { "name": "...", "description": "...", "is_active": true }
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
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    require_once __DIR__ . '/../../../config/database.php';
    $conn = getDbConnection();
    
    $updates = [];
    $types = '';
    $params = [];
    
    if (isset($data['name']) && trim($data['name']) !== '') {
        $updates[] = 'name = ?';
        $types .= 's';
        $params[] = trim($data['name']);
    }
    
    if (isset($data['description'])) {
        $updates[] = 'description = ?';
        $types .= 's';
        $params[] = $data['description'];
    }
    
    if (isset($data['is_active'])) {
        $updates[] = 'is_active = ?';
        $types .= 'i';
        $params[] = (int)$data['is_active'];
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No fields to update']);
        exit;
    }
    
    $sql = 'UPDATE teams SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $types .= 'i';
    $params[] = $teamId;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update team: ' . $stmt->error);
    }
    
    echo json_encode(['ok' => true, 'message' => 'Team updated successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
