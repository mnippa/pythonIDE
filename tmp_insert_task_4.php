<?php
// Insert Task 4: Nested Loop with Counter
require_once 'config/database.php';

// Task 4: Nested Loops (i, j, k) with Counter
$task_data = [
    'assignment_id' => 29,
    'task_number' => 4,
    'title' => 'Verschachtelte Schleife mit Zähler',
    'problem_description' => 'Schreibe ein Programm mit drei verschachtelten Schleifen (i, j, k). Die Ranges für die drei Schleifen werden zufällig generiert (jeweils 1-5). In der innersten Schleife soll ein Counter 3 Mal inkrementiert werden pro Iteration.',
    'problem_type' => 'code_random_complex',
    'code_template' => '#INIT START
import random
range_i = random.randint(1, 5)
range_j = random.randint(1, 5)
range_k = random.randint(1, 5)
#INIT END

counter = 0
for i in range(range_i):
    for j in range(range_j):
        for k in range(range_k):
            # Schreibe hier den Code um den Counter zu inkrementieren
            pass
',
    'solution_code' => '#INIT START
import random
range_i = random.randint(1, 5)
range_j = random.randint(1, 5)
range_k = random.randint(1, 5)
#INIT END

counter = 0
for i in range(range_i):
    for j in range(range_j):
        for k in range(range_k):
            counter += 1
            counter += 1
            counter += 1
',
    'randomizer_code' => 'import random
range_i = random.randint(1, 5)
range_j = random.randint(1, 5)
range_k = random.randint(1, 5)
values = {
    "range_i": range_i,
    "range_j": range_j,
    "range_k": range_k
}',
    'test_cases' => json_encode([
        'mode' => 'intelligent',
        'tests' => [
            [
                'inputs' => ['range_i', 'range_j', 'range_k'],
                'outputs' => ['counter']
            ]
        ]
    ]),
    'difficulty' => 'medium',
    'hints' => 'Denke an die Struktur: Drei verschachtelte for-Schleifen. Der Counter wird in der innersten Schleife inkrementiert.'
];

// Insert Task 4
$sql = "INSERT INTO tasks (
    assignment_id, task_number, title, problem_description, problem_type,
    code_template, solution_code, randomizer_code, test_cases, difficulty, hints
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    'iisssssssss',
    $task_data['assignment_id'],
    $task_data['task_number'],
    $task_data['title'],
    $task_data['problem_description'],
    $task_data['problem_type'],
    $task_data['code_template'],
    $task_data['solution_code'],
    $task_data['randomizer_code'],
    $task_data['test_cases'],
    $task_data['difficulty'],
    $task_data['hints']
);

if ($stmt->execute()) {
    $task_id = $conn->insert_id;
    echo "✓ Task 4 erfolgreich erstellt! Task ID: " . $task_id . "\n";
    echo "Test URL: http://localhost/pythonIDE/public/editor_assignment_test.php?assignment_id=29&task_id=" . $task_id . "\n";
} else {
    echo "✗ Fehler beim Einfügen: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
