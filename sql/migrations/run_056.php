<?php
/**
 * Migration 056: Apply schema additions for manual review + submission comment.
 *
 * Includes:
 * - tasks.manual_review_required TINYINT(1) NOT NULL DEFAULT 0
 * - user_tasks.submission_comment TEXT NULL
 *
 * Idempotent and safe to run multiple times.
 *
 * Run local:
 *   php sql/migrations/run_056.php
 * Run live:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_056.php
 */

declare(strict_types=1);

$useLive = getenv('USE_BETA_LIVE_DB') === '1';

if ($useLive) {
    if (!defined('BETA_LIVE_ALLOW_WRITE')) {
        define('BETA_LIVE_ALLOW_WRITE', true);
    }
    require_once __DIR__ . '/../../config/database.beta_live.local.php';
    $conn = getBetaLiveDbConnection();
    echo "Target DB: LIVE (beta_live helper)\n";
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
    echo "Starting migration 056...\n";

    $conn->begin_transaction();

    if (!columnExists($conn, 'tasks', 'manual_review_required')) {
        $sql = "ALTER TABLE tasks ADD COLUMN manual_review_required TINYINT(1) NOT NULL DEFAULT 0";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding tasks.manual_review_required: ' . $conn->error);
        }
        echo "✓ Added tasks.manual_review_required\n";
    } else {
        echo "✓ tasks.manual_review_required already exists\n";
    }

    if (!columnExists($conn, 'user_tasks', 'submission_comment')) {
        $sql = "ALTER TABLE user_tasks ADD COLUMN submission_comment TEXT NULL";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding user_tasks.submission_comment: ' . $conn->error);
        }
        echo "✓ Added user_tasks.submission_comment\n";
    } else {
        echo "✓ user_tasks.submission_comment already exists\n";
    }

    $conn->commit();
    echo "\n✅ Migration 056 completed successfully.\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo "\n❌ Migration 056 failed: " . $e->getMessage() . "\n";
    exit(1);
}
