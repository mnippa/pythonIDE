<?php
/**
 * Migration 065: Add tasks.locked column.
 *
 * Run local:
 *   php sql/migrations/run_065.php
 * Run live:
 *   set USE_BETA_LIVE_DB=1 ; php sql/migrations/run_065.php
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

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

try {
    echo "Starting migration 065...\n";

    if (columnExists($conn, 'tasks', 'locked')) {
        echo "✓ tasks.locked already exists\n";
        echo "\n✅ Migration 065 completed successfully.\n";
        exit(0);
    }

    $sql = "ALTER TABLE tasks ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 1 AFTER position";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed adding tasks.locked: ' . $conn->error);
    }

    echo "✓ Added tasks.locked with default=1\n";
    echo "\n✅ Migration 065 completed successfully.\n";
} catch (Throwable $e) {
    echo "\n❌ Migration 065 failed: " . $e->getMessage() . "\n";
    exit(1);
}
