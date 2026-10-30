<?php
/**
 * Teams List API
 * GET api/admin/teams/list.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/session.php';

startApplicationSession();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

try {
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

    if ($hasInviteToken) {
        $missingTokenResult = $conn->query("SELECT id FROM teams WHERE invite_token IS NULL OR invite_token = ''");
        if ($missingTokenResult) {
            $updateTokenStmt = $conn->prepare('UPDATE teams SET invite_token = ? WHERE id = ?');
            if (!$updateTokenStmt) {
                throw new RuntimeException('Failed to prepare invite token update: ' . $conn->error);
            }
            while ($tokenRow = $missingTokenResult->fetch_assoc()) {
                $token = bin2hex(random_bytes(16));
                $teamId = (int)$tokenRow['id'];
                $updateTokenStmt->bind_param('si', $token, $teamId);
                $updateTokenStmt->execute();
            }
        }
    }
    
    // Subquery instead of GROUP BY: compatible with ONLY_FULL_GROUP_BY (MariaDB)
    $sql = "SELECT 
                t.id, 
                t.name, 
                t.description, 
                t.is_active,
                t.created_at,
                " . ($hasInviteToken ? "t.invite_token," : "NULL as invite_token,") . "
                (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id) as user_count
            FROM teams t
            ORDER BY t.created_at DESC";
    
    $result = $conn->query($sql);
    if ($result === false) {
        throw new RuntimeException('Failed to load teams: ' . $conn->error);
    }
    $teams = [];
    
    while ($row = $result->fetch_assoc()) {
        $teams[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'user_count' => (int)$row['user_count'],
            'invite_token' => $row['invite_token'] ?: null
        ];
    }
    
    echo json_encode(['ok' => true, 'teams' => $teams]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
