<?php
/**
 * Migration 061: Add team_member_lab_evaluations table.
 *
 * Run local:
 *   php sql/migrations/run_061.php
 * Run live:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_061.php
 */

declare(strict_types=1);

$useBetaLiveDb = getenv('USE_BETA_LIVE_DB') === '1';

if ($useBetaLiveDb) {
    if (!defined('BETA_LIVE_ALLOW_WRITE')) {
        define('BETA_LIVE_ALLOW_WRITE', true);
    }
    require_once __DIR__ . '/../../config/database.beta_live.local.php';
    $conn = getBetaLiveDbConnection();
    echo "Target DB: BETA/LIVE\n";
} else {
    require_once __DIR__ . '/../../config/database.php';
    $conn = getDbConnection();
    echo "Target DB: LOCAL\n";
}

function tableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

try {
    echo "Starting migration 061...\n";

    if (tableExists($conn, 'team_member_lab_evaluations')) {
        echo "✓ team_member_lab_evaluations already exists\n";
        echo "\n✅ Migration 061 completed successfully.\n";
        exit(0);
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
        throw new RuntimeException('Failed creating team_member_lab_evaluations: ' . $conn->error);
    }

    echo "✓ Created team_member_lab_evaluations\n";
    echo "\n✅ Migration 061 completed successfully.\n";
} catch (Throwable $e) {
    echo "\n❌ Migration 061 failed: " . $e->getMessage() . "\n";
    exit(1);
}
