<?php
/**
 * Migration 024: Rename show_generator_code to show_solution_code
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Running Migration 024: Rename show_generator_code to show_solution_code...\n";

    $hasOld = $conn->query("SHOW COLUMNS FROM tasks LIKE 'show_generator_code'");
    $hasNew = $conn->query("SHOW COLUMNS FROM tasks LIKE 'show_solution_code'");

    if ($hasNew && $hasNew->num_rows > 0) {
        echo "⚠ Column 'show_solution_code' already exists. Skipping.\n";
        exit(0);
    }

    if (!$hasOld || $hasOld->num_rows === 0) {
        echo "⚠ Column 'show_generator_code' does not exist. Nothing to rename.\n";
        exit(0);
    }

    $sql = "ALTER TABLE tasks CHANGE COLUMN show_generator_code show_solution_code TINYINT(1) NOT NULL DEFAULT 0";

    if ($conn->query($sql)) {
        echo "✓ Renamed column to show_solution_code\n";
        echo "\n✅ Migration 024: Success!\n";
    } else {
        throw new Exception("Failed to rename column: " . $conn->error);
    }

    $conn->close();

} catch (Exception $e) {
    echo "❌ Migration 024 failed: " . $e->getMessage() . "\n";
    exit(1);
}
