<?php
/**
 * Migration 059: Add user_tasks.admin_feedback_comment.
 *
 * Run local:
 *   php sql/migrations/run_059.php
 * Run live:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_059.php
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
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

try {
    echo "Starting migration 059...\n";

    if (columnExists($conn, 'user_tasks', 'admin_feedback_comment')) {
        echo "✓ user_tasks.admin_feedback_comment already exists\n";
        echo "\n✅ Migration 059 completed successfully.\n";
        exit(0);
    }

    $sql = 'ALTER TABLE user_tasks ADD COLUMN admin_feedback_comment TEXT NULL AFTER submission_comment';
    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed adding user_tasks.admin_feedback_comment: ' . $conn->error);
    }

    echo "✓ Added user_tasks.admin_feedback_comment\n";
    echo "\n✅ Migration 059 completed successfully.\n";
} catch (Throwable $e) {
    echo "\n❌ Migration 059 failed: " . $e->getMessage() . "\n";
    exit(1);
}
