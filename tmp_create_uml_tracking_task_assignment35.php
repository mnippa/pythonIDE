<?php
/**
 * Temporary script to create a tracking task for UML feature progress in assignment 35.
 *
 * This creates a task that can be used to follow the implementation status.
 * It is intentionally simple and can be adapted later.
 */

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

$assignmentId = 35;

$taskTitle = 'UML-Editor-Entwicklung: Fortschrittstracking';
$taskText = "Diese Aufgabe dient als Tracker für die Implementierung eines grafischen UML-Editors.\n\nZiel: Ein grafischer UML-Editor mit Klassen, Attributen, Datentypen, Assoziationen und Enumerationen soll schrittweise umgesetzt werden.";
$description = 'Tracking-Aufgabe für die UML-Editor-Entwicklung im Assignment 35.';

$stmt = $conn->prepare(
    "SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1"
);
$stmt->bind_param('is', $assignmentId, $taskTitle);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Task already exists with id {$row['id']}\n";
    exit(0);
}

$positionSql = "SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?";
$posStmt = $conn->prepare($positionSql);
$posStmt->bind_param('i', $assignmentId);
$posStmt->execute();
$posResult = $posStmt->get_result();
$posRow = $posResult->fetch_assoc();
$nextPosition = (int) $posRow['next_pos'];

$insertSql = "
    INSERT INTO tasks (
        assignment_id, position, title, description, task_text, task_type,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, 'uml', NOW(), NOW())
";

$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param('iisss', $assignmentId, $nextPosition, $taskTitle, $description, $taskText);

if ($insertStmt->execute()) {
    $taskId = $conn->insert_id;
    echo "Created tracking task with id {$taskId} in assignment {$assignmentId}\n";
} else {
    echo "Failed to create task: " . $insertStmt->error . "\n";
    exit(1);
}
