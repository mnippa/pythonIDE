<?php
/**
 * Create example code_random_complex tasks
 * Two tasks: Binary to Decimal, Decimal to Binary
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
$assignmentId = 7;

// Get next position
$stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM tasks WHERE assignment_id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$nextPos = (int)$result['next_pos'];

echo "Creating example code_random_complex tasks in Assignment $assignmentId...\n\n";

// Task 1: 8-bit Binary to Decimal
$task1 = [
    'assignment_id' => $assignmentId,
    'title' => 'Binärzahl in Dezimal umwandeln',
    'description' => 'Wandle die gegebene 8-stellige Binärzahl in eine Dezimalzahl um.',
    'position' => $nextPos,
    'task_type' => 'code_random_complex',
    'question_text' => 'Wandle die folgende Binärzahl in eine Dezimalzahl um:',
    'code_template' => <<<'PYTHON'
import random
binary = format(random.randint(0, 255), '08b')
values = {"binary": binary}
PYTHON,
    'solution_code' => <<<'PYTHON'
result = int(values["binary"], 2)
PYTHON,
    'correct_answer' => 'result',
    'max_attempts' => 3,
    'show_solution' => 1,
];

// Task 2: Decimal to Binary
$task2 = [
    'assignment_id' => $assignmentId,
    'title' => 'Dezimalzahl in Binär umwandeln',
    'description' => 'Wandle die gegebene Dezimalzahl (100-255) in eine 8-stellige Binärzahl um.',
    'position' => $nextPos + 1,
    'task_type' => 'code_random_complex',
    'question_text' => 'Wandle die folgende Dezimalzahl in eine 8-stellige Binärzahl um:',
    'code_template' => <<<'PYTHON'
import random
decimal = random.randint(100, 255)
values = {"decimal": decimal}
PYTHON,
    'solution_code' => <<<'PYTHON'
result = format(values["decimal"], '08b')
PYTHON,
    'correct_answer' => 'result',
    'max_attempts' => 3,
    'show_solution' => 1,
];

foreach ([$task1, $task2] as $task) {
    $stmt = $conn->prepare(
        'INSERT INTO tasks (assignment_id, title, description, position, task_type, question_text, code_template, solution_code, correct_answer, max_attempts, show_solution)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    if (!$stmt) {
        echo "✗ Statement prepare failed: " . $conn->error . "\n";
        continue;
    }
    
    $assignmentId = $task['assignment_id'];
    $title = $task['title'];
    $description = $task['description'];
    $position = $task['position'];
    $taskType = $task['task_type'];
    $questionText = $task['question_text'];
    $codeTemplate = $task['code_template'];
    $solutionCode = $task['solution_code'];
    $correctAnswer = $task['correct_answer'];
    $maxAttempts = (int)$task['max_attempts'];
    $showSolution = (int)$task['show_solution'];
    
    // Bind: i=int, s=string
    // Types: i(assignmentId), s(title), s(description), i(position), s(taskType), 
    //        s(questionText), s(codeTemplate), s(solutionCode), s(correctAnswer), 
    //        i(maxAttempts), i(showSolution)
    $typeStr = 'ississsssii';
    
    if (!$stmt->bind_param($typeStr, 
        $assignmentId, $title, $description, $position, $taskType,
        $questionText, $codeTemplate, $solutionCode, $correctAnswer,
        $maxAttempts, $showSolution)) {
        echo "✗ Bind failed for " . $task['title'] . ": " . $stmt->error . "\n";
        $stmt->close();
        continue;
    }
    
    if ($stmt->execute()) {
        echo "✓ Created: " . $task['title'] . " (Task ID: " . $conn->insert_id . ")\n";
    } else {
        echo "✗ Failed: " . $task['title'] . " - " . $stmt->error . "\n";
    }
    
    $stmt->close();
}

echo "\n✅ Example tasks created successfully!\n";
