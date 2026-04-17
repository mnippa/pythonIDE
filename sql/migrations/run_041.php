<?php
/**
 * Migration 041: Cleanup duplicated recap tasks inserted by migration 040.
 *
 * Removes cloned tasks #251-#273 from assignments 25/26,
 * because those assignments already had original recap tasks.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 041: cleanup duplicated recap tasks...\n";

    $minId = 251;
    $maxId = 273;

    $stmt = $conn->prepare('DELETE FROM tasks WHERE id BETWEEN ? AND ? AND assignment_id IN (25, 26)');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $minId, $maxId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $deleted = $stmt->affected_rows;
    $stmt->close();

    echo "✓ Deleted duplicated tasks: {$deleted}\n";
    echo "\n✅ Migration 041: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 041 failed: " . $e->getMessage() . "\n";
    exit(1);
}
