<?php
/**
 * Test script for Assignment 18: "Funktionen und Schleifen: Binär"
 * Verifies all tasks are correctly created and loaded
 */

require_once 'config/database.php';

$conn = getDbConnection();

$assignmentId = 18;

echo "\n" . str_repeat("=", 70) . "\n";
echo "TEST ASSIGNMENT #18: 'Funktionen und Schleifen: Binär'\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Assignment exists
echo "1. ASSIGNMENT CHECK\n";
$result = $conn->query("SELECT id, title, description FROM assignments WHERE id = $assignmentId");
if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    echo "   ✓ Assignment {$row['id']}: {$row['title']}\n";
    echo "   ✓ Description: " . substr($row['description'], 0, 60) . "...\n\n";
} else {
    die("   ✗ Assignment not found!\n");
}

// 2. Task count
echo "2. TASK COUNT CHECK\n";
$result = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE assignment_id = $assignmentId");
$cnt = $result->fetch_assoc()['cnt'];
echo "   ✓ Total Tasks: $cnt/10\n";
if ($cnt !== 10) {
    echo "   ✗ ERROR: Expected 10 tasks but found $cnt\n";
}
echo "\n";

// 3. Task types distribution
echo "3. TASK TYPES DISTRIBUTION\n";
$result = $conn->query(
    "SELECT task_type, COUNT(*) as cnt 
     FROM tasks 
     WHERE assignment_id = $assignmentId 
     GROUP BY task_type 
     ORDER BY task_type"
);
$typeCount = [];
while ($row = $result->fetch_assoc()) {
    $typeCount[$row['task_type']] = $row['cnt'];
    echo "   ✓ {$row['task_type']}: {$row['cnt']} tasks\n";
}
echo "\n";

// Expected: single_choice: 2, multiple_choice: 2, free_text: 2, code: 2, code_reading: 2
$expected = ['single_choice' => 2, 'multiple_choice' => 2, 'free_text' => 2, 'code' => 2, 'code_reading' => 2];
foreach ($expected as $type => $count) {
    if (($typeCount[$type] ?? 0) === $count) {
        echo "   ✓ {$type}: $count tasks ✓\n";
    } else {
        echo "   ✗ {$type}: expected $count, found " . ($typeCount[$type] ?? 0) . "\n";
    }
}
echo "\n";

// 4. Single Choice Options
echo "4. SINGLE_CHOICE OPTIONS CHECK\n";
$result = $conn->query(
    "SELECT t.id, t.title, COUNT(to.id) as opt_count, SUM(to.is_correct) as correct_count
     FROM tasks t
     LEFT JOIN task_options to ON t.id = to.task_id
     WHERE t.assignment_id = $assignmentId AND t.task_type = 'single_choice'
     GROUP BY t.id, t.title, t.position
     ORDER BY t.position"
);
if (!$result) {
    echo "   ✗ Query error: " . $conn->error . "\n";
} else {
    while ($row = $result->fetch_assoc()) {
        $symbol = ($row['opt_count'] === 4 && $row['correct_count'] === 1) ? '✓' : '✗';
        echo "   $symbol Task {$row['id']}: {$row['title']} ({$row['opt_count']} options, {$row['correct_count']} correct)\n";
    }
}
echo "\n";

// 5. Multiple Choice Options
echo "5. MULTIPLE_CHOICE OPTIONS CHECK\n";
$result = $conn->query(
    "SELECT t.id, t.title, COUNT(to.id) as opt_count, SUM(to.is_correct) as correct_count
     FROM tasks t
     LEFT JOIN task_options to ON t.id = to.task_id
     WHERE t.assignment_id = $assignmentId AND t.task_type = 'multiple_choice'
     GROUP BY t.id, t.title, t.position
     ORDER BY t.position"
);
if (!$result) {
    echo "   ✗ Query error: " . $conn->error . "\n";
} else {
    while ($row = $result->fetch_assoc()) {
        $symbol = ($row['opt_count'] === 4 && $row['correct_count'] === 2) ? '✓' : '✗';
        echo "   $symbol Task {$row['id']}: {$row['title']} ({$row['opt_count']} options, {$row['correct_count']} correct)\n";
    }
}
echo "\n";

// 6. Code Tasks Have Templates
echo "6. CODE TEMPLATES CHECK\n";
$result = $conn->query(
    "SELECT id, title, 
            (code_template IS NOT NULL AND code_template != '') as has_template,
            (solution_code IS NOT NULL AND solution_code != '') as has_solution,
            CHAR_LENGTH(code_template) as tmpl_len
     FROM tasks 
     WHERE assignment_id = $assignmentId AND task_type = 'code'
     ORDER BY position"
);
while ($row = $result->fetch_assoc()) {
    $symbol = ($row['has_template'] && $row['has_solution']) ? '✓' : '✗';
    echo "   $symbol Task {$row['id']}: {$row['title']} (Template: {$row['tmpl_len']} chars)\n";
}
echo "\n";

// 7. Free Text Tasks
echo "7. FREE_TEXT TASKS CHECK\n";
$result = $conn->query(
    "SELECT id, title, 
            (question_text IS NOT NULL AND question_text != '') as has_question,
            (code_template IS NOT NULL AND code_template != '') as has_template,
            CHAR_LENGTH(question_text) as q_len
     FROM tasks 
     WHERE assignment_id = $assignmentId AND task_type = 'free_text'
     ORDER BY position"
);
while ($row = $result->fetch_assoc()) {
    $symbol = ($row['has_question'] && $row['has_template']) ? '✓' : '✗';
    echo "   $symbol Task {$row['id']}: {$row['title']} (Question: {$row['q_len']} chars)\n";
}
echo "\n";

// 8. Code Reading Tasks
echo "8. CODE_READING TASKS CHECK\n";
$result = $conn->query(
    "SELECT id, title, 
            (code_template IS NOT NULL AND code_template != '') as has_template,
            (solution_code IS NOT NULL AND solution_code != '') as has_solution
     FROM tasks 
     WHERE assignment_id = $assignmentId AND task_type = 'code_reading'
     ORDER BY position"
);
while ($row = $result->fetch_assoc()) {
    $symbol = ($row['has_template'] && $row['has_solution']) ? '✓' : '✗';
    echo "   $symbol Task {$row['id']}: {$row['title']}\n";
}
echo "\n";

// 9. Summary
echo "9. SUMMARY\n";
$totalTasks = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE assignment_id = $assignmentId")->fetch_assoc()['cnt'];
$tasksWithOptions = $conn->query(
    "SELECT COUNT(DISTINCT t.id) as cnt 
     FROM tasks t 
     LEFT JOIN task_options to ON t.id = to.task_id 
     WHERE t.assignment_id = $assignmentId AND to.id IS NOT NULL"
)->fetch_assoc()['cnt'];
$tasksWithTemplates = $conn->query(
    "SELECT COUNT(*) as cnt FROM tasks WHERE assignment_id = $assignmentId AND code_template IS NOT NULL AND code_template != ''"
)->fetch_assoc()['cnt'];

echo "   ✓ Total tasks created: $totalTasks\n";
echo "   ✓ Tasks with options: $tasksWithOptions\n";
echo "   ✓ Tasks with templates: $tasksWithTemplates\n";
echo "\n";

echo str_repeat("=", 70) . "\n";
echo "✓ ASSIGNMENT READY FOR TESTING!\n";
echo "Assignment ID: $assignmentId\n";
echo "URL: http://localhost/pythonIDE/public/assignments.php?id=$assignmentId\n";
echo str_repeat("=", 70) . "\n\n";

$conn->close();
?>
