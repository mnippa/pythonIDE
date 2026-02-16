<?php
require_once 'config/database.php';
$conn = getDbConnection();

// First, delete if it exists from before
$conn->query('DELETE FROM tasks WHERE assignment_id = 13');
$conn->query('DELETE FROM assignments WHERE id = 13');

// Create assignment
$assignmentTitle = 'Funktionen und Schleifen: Binär';
$assignmentDesc = 'Kombinieren Sie Funktionen und Schleifen zur Verarbeitung von Binärzahlen.';
$createdBy = 1;

$stmt = $conn->prepare('INSERT INTO assignments (title, description, created_by) VALUES (?, ?, ?)');
$stmt->bind_param('ssi', $assignmentTitle, $assignmentDesc, $createdBy);
if (!$stmt->execute()) {
    die("Error: " . $stmt->error);
}
$assignmentId = $conn->insert_id;
$stmt->close();

echo "Created Assignment ID: $assignmentId\n";

// Test single task
$position = 1;
$taskType = 'single_choice';
$title = 'Test Single Choice';
$description = 'Test Description';
$question = 'What is 1101 in decimal?';
$codeTemplate = 'binary = "1101"';
$solutionCode = '# Solution here';
$correctAnswer = '13';
$tests = null;
$showSolution = 1;

$stmt = $conn->prepare(
    'INSERT INTO tasks (
        assignment_id, title, description, position, task_type,
        question_text, code_template, solution_code, correct_answer,
        test_cases, show_solution
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    die("Prepare error: " . $conn->error);
}

$stmt->bind_param(
    'ississssssi',
    $assignmentId,
    $title,
    $description,
    $position,
    $taskType,
    $question,
    $codeTemplate,
    $solutionCode,
    $correctAnswer,
    $tests,
    $showSolution
);

if (!$stmt->execute()) {
    die("Execute error: " . $stmt->error);
}
$taskId = $conn->insert_id;
$stmt->close();

echo "Created Task ID: $taskId\n";

// Add some options
$options = [
    ['text' => '8', 'is_correct' => 0],
    ['text' => '12', 'is_correct' => 0],
    ['text' => '13', 'is_correct' => 1],
    ['text' => '15', 'is_correct' => 0],
];

foreach ($options as $idx => $opt) {
    $optStmt = $conn->prepare('INSERT INTO task_options (task_id, option_text, is_correct, order_num) VALUES (?, ?, ?, ?)');
    $orderNum = $idx + 1;
    $isCorrect = $opt['is_correct'] ? 1 : 0;
    $optStmt->bind_param('isii', $taskId, $opt['text'], $isCorrect, $orderNum);
    $optStmt->execute();
    $optStmt->close();
    echo "  Added option: {$opt['text']}\n";
}

echo "\nSuccess! Assignment " . $assignmentId . " with Task " . $taskId . " created.\n";
?>
