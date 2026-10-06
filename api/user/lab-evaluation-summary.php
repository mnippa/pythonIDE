<?php
/**
 * Current user labor evaluation summary for dashboard card.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

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
    $user = requireAuth();
    $conn = getDbConnection();

    ensureLabEvaluationTable($conn);

    $userId = (int)$user['id'];

    $teamStmt = $conn->prepare('SELECT team_id FROM users WHERE id = ? LIMIT 1');
    $teamStmt->bind_param('i', $userId);
    $teamStmt->execute();
    $teamRow = $teamStmt->get_result()->fetch_assoc();
    $teamId = isset($teamRow['team_id']) ? (int)$teamRow['team_id'] : 0;

    $stmt = $conn->prepare('SELECT status FROM team_member_lab_evaluations WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $status = $row['status'] ?? null;
    if ($status === null && $teamId > 0) {
      // Team assignment implies at least "Durchfuehrung".
      $status = 'durchfuehrung';
    }

    $countSql = <<<'SQL'
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN status IN ('passed', 'submitted') THEN 1 ELSE 0 END) AS success,
  SUM(CASE WHEN status = 'rework' THEN 1 ELSE 0 END) AS rework,
  SUM(CASE WHEN status IN ('failed', 'missed') THEN 1 ELSE 0 END) AS failed
FROM user_assignments
WHERE user_id = ?
SQL;
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc() ?: [];

    jsonResponse([
      'ok' => true,
      'status' => $status,
      'counts' => [
        'total' => (int)($counts['total'] ?? 0),
        'success' => (int)($counts['success'] ?? 0),
        'rework' => (int)($counts['rework'] ?? 0),
        'failed' => (int)($counts['failed'] ?? 0),
      ],
      'has_team' => $teamId > 0
    ]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => 'Failed to load labor evaluation summary'], 500);
}
