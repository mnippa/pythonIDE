<?php
/**
 * Migration 058: Add submitted to user_tasks.status enum.
 *
 * Run local:
 *   php sql/migrations/run_058.php
 * Run live:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_058.php
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

function userTaskStatusIncludes(mysqli $conn, string $status): bool
{
    $res = $conn->query("SHOW COLUMNS FROM user_tasks LIKE 'status'");
    if (!($res instanceof mysqli_result) || $res->num_rows === 0) {
        return false;
    }
    $row = $res->fetch_assoc();
    $typeDef = strtolower((string)($row['Type'] ?? ''));
    return strpos($typeDef, strtolower($status)) !== false;
}

try {
    echo "Starting migration 058...\n";

    if (userTaskStatusIncludes($conn, 'submitted')) {
        echo "✓ user_tasks.status enum already contains submitted\n";
        exit(0);
    }

    $sql = "ALTER TABLE user_tasks MODIFY COLUMN status ENUM('unbearbeitet', 'in-progress', 'submitted', 'passed', 'failed') NULL DEFAULT 'unbearbeitet'";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed updating user_tasks.status enum: ' . $conn->error);
    }

    echo "✓ Updated user_tasks.status enum\n";
    echo "\n✅ Migration 058 completed successfully.\n";
} catch (Throwable $e) {
    echo "\n❌ Migration 058 failed: " . $e->getMessage() . "\n";
    exit(1);
}
