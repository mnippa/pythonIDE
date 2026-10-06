<?php
/**
 * Temporary DB/schema script for UML task mode support.
 *
 * Purpose:
 * - add 'uml' to tasks.task_type enum
 * - add optional UML-specific columns for future renderer persistence
 *
 * Usage:
 *   php tmp_add_uml_task_mode.php
 *   USE_BETA_LIVE_DB=1 php tmp_add_uml_task_mode.php
 *
 * IMPORTANT:
 * This is temporary and should later be replaced by a proper migration file
 * in sql/migrations/ once the feature is validated.
 */

require_once __DIR__ . '/config/database.php';

$useBetaLiveDb = getenv('USE_BETA_LIVE_DB') === '1';

if ($useBetaLiveDb) {
    if (!defined('BETA_LIVE_ALLOW_WRITE')) {
        define('BETA_LIVE_ALLOW_WRITE', true);
    }
    require_once __DIR__ . '/config/database.beta_live.local.php';
    $conn = getBetaLiveDbConnection();
    echo "Target DB: BETA/LIVE\n";
} else {
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
    $typeDef = strtolower((string) ($row['Type'] ?? ''));
    return strpos($typeDef, strtolower($taskType)) !== false;
}

try {
    echo "Starting temporary UML task mode schema update...\n";
    $conn->begin_transaction();

    if (!taskTypeIncludes($conn, 'uml')) {
        $sql = "ALTER TABLE tasks MODIFY COLUMN task_type ENUM('code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex', 'db_model', 'file_submission', 'uml') NOT NULL DEFAULT 'code'";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed updating tasks.task_type enum: ' . $conn->error);
        }
        echo "✓ Updated tasks.task_type enum to include uml\n";
    } else {
        echo "✓ tasks.task_type already contains uml\n";
    }

    if (!columnExists($conn, 'tasks', 'uml_model')) {
        $sql = "ALTER TABLE tasks ADD COLUMN uml_model JSON NULL AFTER file_submission_max_size_bytes";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding tasks.uml_model: ' . $conn->error);
        }
        echo "✓ Added tasks.uml_model\n";
    } else {
        echo "✓ tasks.uml_model already exists\n";
    }

    if (!columnExists($conn, 'tasks', 'uml_solution')) {
        $sql = "ALTER TABLE tasks ADD COLUMN uml_solution JSON NULL AFTER uml_model";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding tasks.uml_solution: ' . $conn->error);
        }
        echo "✓ Added tasks.uml_solution\n";
    } else {
        echo "✓ tasks.uml_solution already exists\n";
    }

    if (!columnExists($conn, 'tasks', 'uml_template')) {
        $sql = "ALTER TABLE tasks ADD COLUMN uml_template JSON NULL AFTER uml_solution";
        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed adding tasks.uml_template: ' . $conn->error);
        }
        echo "✓ Added tasks.uml_template\n";
    } else {
        echo "✓ tasks.uml_template already exists\n";
    }

    $conn->commit();
    echo "\nTemporary schema update completed successfully.\n";
    echo "Next step: move this logic into sql/migrations/ as a formal migration once validated.\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo "\nSchema update failed: " . $e->getMessage() . "\n";
    exit(1);
}
