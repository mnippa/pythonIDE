<?php
/**
 * Create Team API
 * POST api/admin/teams/create.php
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || trim($data['name']) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Team name required']);
        exit;
    }
    
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
    
    $name = trim($data['name']);
    $description = $data['description'] ?? '';
    $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    
    $inviteToken = $hasInviteToken ? bin2hex(random_bytes(16)) : null;

    if ($hasInviteToken) {
        $stmt = $conn->prepare('INSERT INTO teams (name, description, is_active, invite_token) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssis', $name, $description, $isActive, $inviteToken);
    } else {
        $stmt = $conn->prepare('INSERT INTO teams (name, description, is_active) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $name, $description, $isActive);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create team: ' . $stmt->error);
    }
    
    $teamId = $conn->insert_id;
    
    echo json_encode([
        'ok' => true,
        'message' => 'Team created successfully',
        'team_id' => $teamId,
        'invite_token' => $inviteToken
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
