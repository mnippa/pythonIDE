<?php
/**
 * Fix indentation bug in existing grid_redraw_demo projects.
 *
 * Usage:
 *   php scripts/fix_grid_redraw_demo_indent.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running fix: grid_redraw_demo init.py indentation...\n";

    $sql = "
        UPDATE project_files
        SET content = REGEXP_REPLACE(content, '^\\s+import time', 'import time')
        WHERE name = 'init.py'
          AND content LIKE '%outputClear()%'
          AND content LIKE '%Steuerung: w/a/s/d, q = Ende%'
          AND content REGEXP '^\\s+import time'
    ";

    if (!$conn->query($sql)) {
        throw new Exception('Update failed: ' . $conn->error);
    }

    $affected = $conn->affected_rows;
    echo "Updated files: {$affected}\n";
    echo "Done.\n";

    $conn->close();
} catch (Exception $e) {
    echo "Fix failed: " . $e->getMessage() . "\n";
    exit(1);
}
