<?php
/**
 * Create code_random_complex tasks for Assignment 12 with algorithmic solutions
 * Two tasks: Binary to Decimal (with algorithm), Decimal to Binary (with algorithm)
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
$assignmentId = 12;

// Get next position
$stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM tasks WHERE assignment_id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$nextPos = (int)$result['next_pos'];

echo "Creating code_random_complex tasks with algorithmic solutions in Assignment $assignmentId...\n\n";

// Task 1: 8-bit Binary to Decimal (with algorithm)
$task1 = [
    'assignment_id' => $assignmentId,
    'title' => 'Binärzahl in Dezimal (Algorithmus)',
    'description' => 'Wandle die gegebene 8-stellige Binärzahl mit einem Algorithmus in eine Dezimalzahl um.',
    'position' => $nextPos,
    'task_type' => 'code_random_complex',
    'question_text' => 'Schreibe einen Algorithmus, der diese Binärzahl in Dezimal umwandelt:',
    'code_template' => <<<'PYTHON'
import random
binary = format(random.randint(0, 255), '08b')
values = {"binary": binary}
PYTHON,
    'solution_code' => <<<'PYTHON'
# Algorithmus: Von rechts nach links, jede Stelle mit 2^Position multiplizieren
result = 0
for i, bit in enumerate(reversed(values["binary"])):
    if bit == '1':
        result += 2 ** i
PYTHON,
    'correct_answer' => 'result',
    'max_attempts' => 3,
    'show_solution' => 1,
];

// Task 2: Decimal to Binary (with algorithm)
$task2 = [
    'assignment_id' => $assignmentId,
    'title' => 'Dezimalzahl in Binär (Algorithmus)',
    'description' => 'Wandle die gegebene Dezimalzahl mit einem Algorithmus in eine 8-stellige Binärzahl um.',
    'position' => $nextPos + 1,
    'task_type' => 'code_random_complex',
    'question_text' => 'Schreibe einen Algorithmus, der diese Dezimalzahl in Binär umwandelt:',
    'code_template' => <<<'PYTHON'
import random
decimal = random.randint(100, 255)
values = {"decimal": decimal}
PYTHON,
    'solution_code' => <<<'PYTHON'
# Algorithmus: Wiederholte Division durch 2 und Reste sammeln
result = ""
num = values["decimal"]
while num > 0:
    result = str(num % 2) + result
    num = num // 2

# Mit Nullen auf 8 Stellen auffüllen
result = result.zfill(8)
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
    
    $stmt->bind_param(
        'ississsssii',
        $task['assignment_id'],
        $task['title'],
        $task['description'],
        $task['position'],
        $task['task_type'],
        $task['question_text'],
        $task['code_template'],
        $task['solution_code'],
        $task['correct_answer'],
        $task['max_attempts'],
        $task['show_solution']
    );
    
    if ($stmt->execute()) {
        echo "✓ Created: {$task['title']} (Task ID: {$conn->insert_id}, Position: {$task['position']})\n";
    } else {
        echo "✗ Creation failed for '{$task['title']}': " . $stmt->error . "\n";
    }
    
    $stmt->close();
}

echo "\n✅ Algorithmic tasks created successfully for Assignment $assignmentId!\n";
$conn->close();
