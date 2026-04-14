<?php
/**
 * Migration 030: Fix task 193 intelligent vars solution_code.
 *
 * No platform logic changes. Only task data fix:
 * ensure INIT placeholder is index-safe for existing vars validation flow.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 030: fix task 193 solution_code...\n";

    $taskId = 193;

    $solutionCode = <<<'PY'
#INIT START
faecher=["MAthe","Deutsch","Englisch","Informatik"]
#INIT END

drittes_fach=faecher[2]
PY;

    $stmt = $conn->prepare('UPDATE tasks SET solution_code = ? WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('si', $solutionCode, $taskId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 1) {
        echo "⚠ Task #{$taskId} unchanged (already had same content or not found).\n";
    } else {
        echo "✓ Updated task #{$taskId} solution_code\n";
    }

    $stmt->close();
    echo "\n✅ Migration 030: Success!\n";
} catch (Exception $e) {
    echo "❌ Migration 030 failed: " . $e->getMessage() . "\n";
    exit(1);
}
