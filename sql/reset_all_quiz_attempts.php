<?php
/**
 * Quick script to reset all quiz attempts
 * Run this via: php sql/reset_all_quiz_attempts.php
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "=== Resetting all quiz attempts ===\n\n";

// First, show what we're about to reset
$result = $conn->query("
    SELECT COUNT(*) as cnt
    FROM user_tasks ut
    INNER JOIN tasks t ON t.id = ut.task_id
    WHERE t.task_type IN ('single_choice', 'multiple_choice', 'free_text', 'code_reading')
");
$row = $result->fetch_assoc();
echo "Found " . $row['cnt'] . " quiz task entries to reset.\n\n";

$sql = "UPDATE user_tasks ut
        INNER JOIN tasks t ON t.id = ut.task_id
        SET ut.status = 'unbearbeitet',
            ut.attempts = 0,
            ut.selected_options = NULL,
            ut.text_answer = NULL,
            ut.variable_values = NULL
        WHERE t.task_type IN ('single_choice', 'multiple_choice', 'free_text', 'code_reading')";

if ($conn->query($sql)) {
    $affectedRows = $conn->affected_rows;
    echo "✓ Erfolg! $affectedRows Einträge zurückgesetzt.\n\n";
    
    // Verify
    $result = $conn->query("
        SELECT COUNT(*) as cnt
        FROM user_tasks ut
        INNER JOIN tasks t ON t.id = ut.task_id
        WHERE t.task_type IN ('single_choice', 'multiple_choice', 'free_text', 'code_reading')
        AND (ut.status != 'unbearbeitet' OR ut.attempts > 0)
    ");
    $row = $result->fetch_assoc();
    
    if ($row['cnt'] == 0) {
        echo "✓ Verification passed: All quiz tasks are now 'unbearbeitet' with 0 attempts.\n";
    } else {
        echo "⚠ Warning: " . $row['cnt'] . " entries still have attempts or wrong status.\n";
    }
} else {
    echo "✗ Fehler: " . $conn->error . "\n";
}
