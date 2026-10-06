<?php
/**
 * Admin: Set labor evaluation status for a team member.
 * POST { user_id, status }
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

require_once __DIR__ . '/../../../../config/database.php';

function ensureLabEvaluationTable(mysqli $conn): void {
    $res = $conn->query("SHOW TABLES LIKE 'team_member_lab_evaluations'");
    if ($res instanceof mysqli_result && $res->num_rows > 0) {
        return;
    }

    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS team_member_lab_evaluations (
    user_id INT(10) UNSIGNED NOT NULL,
  status ENUM('durchfuehrung','bewertung','bestanden','nachpruefung','nicht_bestanden','nicht_teilgenommen') NOT NULL DEFAULT 'durchfuehrung',
    updated_by INT(10) UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_tmlab_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmlab_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed to ensure lab evaluation table: ' . $conn->error);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    $status = isset($payload['status']) ? (string)$payload['status'] : '';

    $allowed = ['durchfuehrung', 'bewertung', 'bestanden', 'nachpruefung', 'nicht_bestanden', 'nicht_teilgenommen'];
    if ($userId <= 0 || !in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'user_id and valid status required']);
        exit;
    }

    $conn = getDbConnection();
    ensureLabEvaluationTable($conn);

    // Restrict to team members only.
    $teamCheck = $conn->prepare('SELECT team_id FROM users WHERE id = ? LIMIT 1');
    $teamCheck->bind_param('i', $userId);
    $teamCheck->execute();
    $userRow = $teamCheck->get_result()->fetch_assoc();
    if (!$userRow || empty($userRow['team_id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Laborbewertung ist nur fuer Teammitglieder verfuegbar']);
        exit;
    }

    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare(
        'INSERT INTO team_member_lab_evaluations (user_id, status, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('isi', $userId, $status, $adminId);

    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to save lab evaluation: ' . $stmt->error);
    }

    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'status' => $status
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
