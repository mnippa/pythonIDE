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

    $columnCheck = $conn->query("SHOW COLUMNS FROM teams LIKE 'invite_token'");
    $hasInviteToken = $columnCheck && $columnCheck->num_rows > 0;
    if (!$hasInviteToken) {
        $conn->query("ALTER TABLE teams ADD COLUMN invite_token VARCHAR(64) NULL");
        $conn->query("CREATE UNIQUE INDEX idx_teams_invite_token ON teams(invite_token)");
        $columnCheck = $conn->query("SHOW COLUMNS FROM teams LIKE 'invite_token'");
        $hasInviteToken = $columnCheck && $columnCheck->num_rows > 0;
    }
    
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

    if ($hasInviteToken && !empty($data['regenerate_invite'])) {
        $updates[] = 'invite_token = ?';
        $types .= 's';
        $params[] = bin2hex(random_bytes(16));
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
    
    $inviteToken = null;
    if ($hasInviteToken) {
        $readStmt = $conn->prepare('SELECT invite_token FROM teams WHERE id = ? LIMIT 1');
        $readStmt->bind_param('i', $teamId);
        $readStmt->execute();
        $inviteRow = $readStmt->get_result()->fetch_assoc();
        $inviteToken = $inviteRow['invite_token'] ?? null;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Team updated successfully',
        'invite_token' => $inviteToken
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
