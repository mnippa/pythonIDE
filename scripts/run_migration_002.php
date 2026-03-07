<?php
/**
 * Run migration: Add project_type to projects table
 */

require_once 'c:/xampp/htdocs/pythonIDE/config/database.php';

$conn = getDbConnection();

echo "Running migration: 002_add_project_type.sql\n";
echo str_repeat('=', 50) . "\n";

$migrations = [
    "ALTER TABLE projects ADD COLUMN project_type ENUM('python', 'html', 'mixed') DEFAULT 'python' NOT NULL AFTER description",
    "ALTER TABLE projects ADD COLUMN visibility ENUM('private', 'public') DEFAULT 'private' NOT NULL AFTER project_type",
    "ALTER TABLE projects ADD COLUMN share_token VARCHAR(64) NULL DEFAULT NULL AFTER visibility",
    "ALTER TABLE projects ADD INDEX idx_project_type (project_type)",
    "ALTER TABLE projects ADD INDEX idx_visibility (visibility)",
    "ALTER TABLE projects ADD INDEX idx_share_token (share_token)"
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($migrations as $sql) {
    try {
        $conn->query($sql);
        echo "✓ " . substr($sql, 0, 70) . "...\n";
        $success++;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'Duplicate key') !== false) {
            echo "⊘ Already exists: " . substr($sql, 0, 50) . "...\n";
            $skipped++;
        } else {
            echo "✗ Error: " . $msg . "\n";
            $errors++;
        }
    }
}

echo str_repeat('=', 50) . "\n";
echo "Results: $success successful, $skipped skipped, $errors errors\n";
echo "\n✅ Migration completed\n";
