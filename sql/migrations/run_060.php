<?php
/**
 * Migration 060: Add db_small to projects.project_type enum.
 *
 * Run local:
 *   php sql/migrations/run_060.php
 * Run live:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_060.php
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

function projectTypeIncludes(mysqli $conn, string $type): bool
{
    $res = $conn->query("SHOW COLUMNS FROM projects LIKE 'project_type'");
    if (!($res instanceof mysqli_result) || $res->num_rows === 0) {
        return false;
    }
    $row = $res->fetch_assoc();
    $typeDef = strtolower((string)($row['Type'] ?? ''));
    return strpos($typeDef, strtolower($type)) !== false;
}

try {
    echo "Starting migration 060...\n";

    if (projectTypeIncludes($conn, 'db_small')) {
        echo "✓ projects.project_type enum already contains db_small\n";
        echo "\n✅ Migration 060 completed successfully.\n";
        exit(0);
    }

    $sql = "ALTER TABLE projects MODIFY COLUMN project_type ENUM('python', 'html', 'mixed', 'db_small') NOT NULL DEFAULT 'python'";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed updating projects.project_type enum: ' . $conn->error);
    }

    echo "✓ Updated projects.project_type enum\n";
    echo "\n✅ Migration 060 completed successfully.\n";
} catch (Throwable $e) {
    echo "\n❌ Migration 060 failed: " . $e->getMessage() . "\n";
    exit(1);
}
