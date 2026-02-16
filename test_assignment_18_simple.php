<?php
/**
 * Simple test script for Assignment 18
 */

require_once 'config/database.php';

$conn = getDbConnection();
$assignmentId = 18;

echo "\n" . str_repeat("=", 70) . "\n";
echo "TESTING ASSIGNMENT #18: 'Funktionen und Schleifen: Binär'\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Check Assignment
$query = "SELECT id, title, description FROM assignments WHERE id = $assignmentId";
$result = $conn->query($query);
if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    echo "✓ ASSIGNMENT FOUND\n";
    echo "  ID: {$row['id']}\n";
    echo "  Title: {$row['title']}\n";
    echo "  Description: " . substr($row['description'], 0, 70) . "...\n\n";
}

// 2. Check Tasks
$query = "SELECT id, position, title, task_type FROM tasks WHERE assignment_id = $assignmentId ORDER BY position";
$result = $conn->query($query);
echo "✓ TASKS (" . $result->num_rows . " total):\n";
while ($row = $result->fetch_assoc()) {
    echo "  #{$row['position']} - Task {$row['id']}: {$row['title']} ({$row['task_type']})\n";
}
echo "\n";

// 3. Check Task Options
$query = "SELECT task_id, COUNT(*) as opt_count, SUM(is_correct) as correct FROM task_options WHERE task_id IN (SELECT id FROM tasks WHERE assignment_id = $assignmentId) GROUP BY task_id ORDER BY task_id";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    echo "✓ TASK OPTIONS:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  Task {$row['task_id']}: {$row['opt_count']} options ({$row['correct']} correct)\n";
    }
    echo "\n";
}

// 4. Detailed Task Info
echo "✓ DETAILED TASK INFORMATION:\n\n";

// Single Choice
echo "  [SINGLE CHOICE]\n";
$query = "SELECT id, title, question_text FROM tasks WHERE assignment_id = $assignmentId AND task_type = 'single_choice' ORDER BY position";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "    Task {$row['id']}: {$row['title']}\n";
    echo "      Question: {$row['question_text']}\n";
}
echo "\n";

// Multiple Choice
echo "  [MULTIPLE CHOICE]\n";
$query = "SELECT id, title, question_text FROM tasks WHERE assignment_id = $assignmentId AND task_type = 'multiple_choice' ORDER BY position";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "    Task {$row['id']}: {$row['title']}\n";
    echo "      Question: {$row['question_text']}\n";
}
echo "\n";

// Free Text
echo "  [FREE TEXT]\n";
$query = "SELECT id, title, question_text FROM tasks WHERE assignment_id = $assignmentId AND task_type = 'free_text' ORDER BY position";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "    Task {$row['id']}: {$row['title']}\n";
    echo "      Question: " . substr($row['question_text'], 0, 50) . "...\n";
}
echo "\n";

// Code
echo "  [CODE]\n";
$query = "SELECT id, title, question_text, (code_template IS NOT NULL AND code_template != '') as has_template FROM tasks WHERE assignment_id = $assignmentId AND task_type = 'code' ORDER BY position";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "    Task {$row['id']}: {$row['title']}\n";
    echo "      Question: " . substr($row['question_text'], 0, 50) . "...\n";
    echo "      Has Template: " . ($row['has_template'] ? 'Yes' : 'No') . "\n";
}
echo "\n";

// Code Reading
echo "  [CODE READING]\n";
$query = "SELECT id, title, (code_template IS NOT NULL AND code_template != '') as has_template FROM tasks WHERE assignment_id = $assignmentId AND task_type = 'code_reading' ORDER BY position";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "    Task {$row['id']}: {$row['title']}\n";
    echo "      Has Template: " . ($row['has_template'] ? 'Yes' : 'No') . "\n";
}
echo "\n";

// 5. Summary
echo str_repeat("=", 70) . "\n";
echo "✓ ASSIGNMENT #18 READY FOR TESTING\n";
echo "✓ Access at: http://localhost/pythonIDE/public/assignments.php?id=18\n";
echo str_repeat("=", 70) . "\n\n";

$conn->close();
?>
