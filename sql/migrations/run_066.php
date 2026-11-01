<?php
/**
 * Migration 066: Add UML projects to projects.project_type.
 *
 * Run local:
 *   php sql/migrations/run_066.php
 * Run beta/live only after isolated beta acceptance:
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_066.php
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

function projectTypeIncludesUml(mysqli $conn): bool
{
    $result = $conn->query("SHOW COLUMNS FROM projects LIKE 'project_type'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        throw new RuntimeException('projects.project_type column was not found.');
    }
    $column = $result->fetch_assoc();
    return strpos(strtolower((string)($column['Type'] ?? '')), "'uml'") !== false;
}

try {
    echo "Starting migration 066...\n";
    if (projectTypeIncludesUml($conn)) {
        echo "✓ projects.project_type already contains uml\n";
        exit(0);
    }

    $sql = "ALTER TABLE projects MODIFY COLUMN project_type ENUM('python', 'html', 'mixed', 'db_small', 'uml') NOT NULL DEFAULT 'python'";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed updating projects.project_type: ' . $conn->error);
    }

    echo "✓ Added uml to projects.project_type\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Migration 066 failed: {$error->getMessage()}\n");
    exit(1);
}
