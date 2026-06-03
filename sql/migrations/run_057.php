<?php
/**
 * Migration 057: Add db_model + file_submission task types and file submission config fields.
 *
 * Includes:
 * - tasks.task_type enum: add db_model, file_submission
 * - tasks.file_submission_allowed_types VARCHAR(255) NULL
 * - tasks.file_submission_max_size_bytes INT UNSIGNED NOT NULL DEFAULT 102400
 *
 * Idempotent and safe to run multiple times.
 *
 * Run local:
 *   php sql/migrations/run_057.php
 * Run live:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_057.php
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

function taskTypeIncludes(mysqli $conn, string $taskType): bool
{
    $res = $conn->query("SHOW COLUMNS FROM tasks LIKE 'task_type'");
    if (!($res instanceof mysqli_result) || $res->num_rows === 0) {
        return false;
    }
    $row = $res->fetch_assoc();
    $typeDef = strtolower((string)($row['Type'] ?? ''));
    return strpos($typeDef, strtolower($taskType)) !== false;
}

try {
    echo "Starting migration 057...\n";

    $conn->begin_transaction();

    $needsEnumUpdate = !taskTypeIncludes($conn, 'db_model') || !taskTypeIncludes($conn, 'file_submission');
    if ($needsEnumUpdate) {
        $sql = "ALTER TABLE tasks MODIFY COLUMN task_type ENUM('code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex', 'db_model', 'file_submission') NOT NULL DEFAULT 'code'";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed updating tasks.task_type enum: ' . $conn->error);
        }
        echo "✓ Updated tasks.task_type enum\n";
    } else {
        echo "✓ tasks.task_type enum already contains db_model/file_submission\n";
    }

    if (!columnExists($conn, 'tasks', 'file_submission_allowed_types')) {
        $sql = "ALTER TABLE tasks ADD COLUMN file_submission_allowed_types VARCHAR(255) NULL AFTER randomizer_code";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding tasks.file_submission_allowed_types: ' . $conn->error);
        }
        echo "✓ Added tasks.file_submission_allowed_types\n";
    } else {
        echo "✓ tasks.file_submission_allowed_types already exists\n";
    }

    if (!columnExists($conn, 'tasks', 'file_submission_max_size_bytes')) {
        $sql = "ALTER TABLE tasks ADD COLUMN file_submission_max_size_bytes INT UNSIGNED NOT NULL DEFAULT 102400 AFTER file_submission_allowed_types";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding tasks.file_submission_max_size_bytes: ' . $conn->error);
        }
        echo "✓ Added tasks.file_submission_max_size_bytes\n";
    } else {
        echo "✓ tasks.file_submission_max_size_bytes already exists\n";
    }

    $conn->commit();
    echo "\n✅ Migration 057 completed successfully.\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo "\n❌ Migration 057 failed: " . $e->getMessage() . "\n";
    exit(1);
}
