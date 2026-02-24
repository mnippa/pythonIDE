<?php
require_once 'config/database.php';
$conn = getDbConnection();

// Find all CODE_RANDOM_COMPLEX and related tasks to delete
$result = $conn->query("
    SELECT DISTINCT task.assignment_id, assignment.title
    FROM tasks task
    LEFT JOIN assignments assignment ON task.assignment_id = assignment.id
    WHERE task.task_type = 'code_random_complex'
    AND task.assignment_id < 21
    ORDER BY task.assignment_id
");

$assignmentsToDelete = [];
while ($row = $result->fetch_assoc()) {
    $assignmentsToDelete[] = $row['assignment_id'];
    echo "Assignment {$row['assignment_id']}: {$row['title']}\n";
}

if (empty($assignmentsToDelete)) {
    echo "Keine Assignments < 21 mit CODE_RANDOM_COMPLEX found\n";
    exit(0);
}

echo "\n---\n";
echo "Lösche folgende Assignments und ihre Tasks:\n";

foreach ($assignmentsToDelete as $assignId) {
    // Get task count
    $taskResult = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE assignment_id = $assignId");
    $taskRow = $taskResult->fetch_assoc();
    
    // Delete tasks
    $stmt = $conn->prepare('DELETE FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignId);
    $stmt->execute();
    
    // Delete assignment
    $stmt = $conn->prepare('DELETE FROM assignments WHERE id = ?');
    $stmt->bind_param('i', $assignId);
    $stmt->execute();
    
    echo "✓ Assignment $assignId gelöscht ({$taskRow['cnt']} Tasks)\n";
}

echo "\n✓ Cleanup abgeschlossen!\n";
?>
