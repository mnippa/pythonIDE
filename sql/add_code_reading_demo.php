<?php
/**
 * Add CODE_READING Task (AUTO-Mode Demo) to Assignment #21
 * 
 * Task: Analyse einer Schleife mit Summen-Algorithmus
 * 3 Iterationen mit verschiedenen Werten für a und b
 * 
 * Algorithm:
 * summe = 1
 * for n in range(a, b):
 *     summe = summe + n * summe
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Get the next position for assignment #21
$result = $conn->query("SELECT COALESCE(MAX(position), 0) + 1 as next_position FROM tasks WHERE assignment_id = 21");
$row = $result->fetch_assoc();
$nextPosition = $row['next_position'];

echo "=== CODE_READING Task (AUTO-Mode) ===\n";
echo "Position: $nextPosition in Assignment #21\n\n";

$assignmentId = 21;
$title = 'Code Reading: Summen-Algorithmus mit Schleife';
$description = 'Analysiere einen Algorithmus mit verschachtelter Berechnung';
$taskText = 'Analysiere den Code und bestimme den Wert der Variablen "summe" am Ende der Ausführung.';
$position = $nextPosition;
$taskType = 'code_reading';
$maxAttempts = 3;
$iterationsCount = 3;

// Code Template - Student sieht dies (mit Platzhaltern)
$codeTemplate = <<<'EOT'
summe = 1

for n in range({a}, {b}):
    summe = summe + n * summe

# What is the value of "summe" at the end?
EOT;

// Solution Code - KOMPLETT, wird für AUTO-Mode benutzt
$solutionCode = <<<'EOT'
summe = 1

for n in range({a}, {b}):
    summe = summe + n * summe

# Nach der Schleife hat summe einen bestimmten Wert
EOT;

// Correct Answer - welche Variable wird gelesen
$correctAnswer = 'summe';

// Variable Overrides - 3 Iterationen mit unterschiedlichen Werten
// Format: {inputs: {a: ..., b: ...}, expected_output: ""}
// expected_output "" bedeutet: AUTO-Modus (System berechnet es)
$variableOverrides = [
    [
        'inputs' => ['a' => 1, 'b' => 5],
        'expected_output' => ''  // AUTO: System berechnet aus solution_code
    ],
    [
        'inputs' => ['a' => 2, 'b' => 6],
        'expected_output' => ''  // AUTO: System berechnet aus solution_code
    ],
    [
        'inputs' => ['a' => 5, 'b' => 10],
        'expected_output' => ''  // AUTO: System berechnet aus solution_code
    ]
];

$variableOverridesJson = json_encode($variableOverrides);

// Prepare the INSERT statement
$sql = "INSERT INTO tasks (
    assignment_id, title, description, position, max_attempts, iterations_count,
    show_solution, show_solution_code, problem_type, code_template, solution_code,
    task_type, task_text, correct_answer, variable_overrides
) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

$problemType = 'code_reading';
$stmt->bind_param(
    'isisssssssss',
    $assignmentId,
    $title,
    $description,
    $position,
    $maxAttempts,
    $iterationsCount,
    $problemType,
    $codeTemplate,
    $solutionCode,
    $taskType,
    $taskText,
    $correctAnswer,
    $variableOverridesJson
);

if (!$stmt->execute()) {
    echo "Execute failed: " . $stmt->error . "\n";
    exit(1);
}

$taskId = $stmt->insert_id;
$stmt->close();

echo "✅ Task created successfully!\n";
echo "Task ID: $taskId\n";
echo "Assignment: #21\n\n";

// Display the created task
echo "=== Task Details ===\n";
echo "Title: $title\n";
echo "Type: $taskType\n";
echo "Position: $position\n";
echo "Max Attempts: $maxAttempts\n";
echo "Iterations: $iterationsCount\n\n";

echo "=== Code Template (Student sieht) ===\n";
echo $codeTemplate . "\n\n";

echo "=== Solution Code (Für AUTO-Berechnung) ===\n";
echo $solutionCode . "\n\n";

echo "=== Variable Overrides (3 Iterationen) ===\n";
echo json_encode($variableOverrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "=== Was passiert beim Ausführen ===\n";
echo "Iteration 1: a=1, b=5\n";
echo "  Code: summe = 1; for n in range(1, 5): summe = summe + n * summe\n";
echo "  Schritt 1 (n=1): summe = 1 + 1*1 = 2\n";
echo "  Schritt 2 (n=2): summe = 2 + 2*2 = 6\n";
echo "  Schritt 3 (n=3): summe = 6 + 3*6 = 24\n";
echo "  Schritt 4 (n=4): summe = 24 + 4*24 = 120\n";
echo "  Expected (AUTO): 120\n\n";

echo "Iteration 2: a=2, b=6\n";
echo "  Code: summe = 1; for n in range(2, 6): summe = summe + n * summe\n";
echo "  Schritt 1 (n=2): summe = 1 + 2*1 = 3\n";
echo "  Schritt 2 (n=3): summe = 3 + 3*3 = 12\n";
echo "  Schritt 3 (n=4): summe = 12 + 4*12 = 60\n";
echo "  Schritt 4 (n=5): summe = 60 + 5*60 = 360\n";
echo "  Expected (AUTO): 360\n\n";

echo "Iteration 3: a=5, b=10\n";
echo "  Code: summe = 1; for n in range(5, 10): summe = summe + n * summe\n";
echo "  Schritt 1 (n=5): summe = 1 + 5*1 = 6\n";
echo "  Schritt 2 (n=6): summe = 6 + 6*6 = 42\n";
echo "  Schritt 3 (n=7): summe = 42 + 7*42 = 336\n";
echo "  Schritt 4 (n=8): summe = 336 + 8*336 = 3024\n";
echo "  Schritt 5 (n=9): summe = 3024 + 9*3024 = 30240\n";
echo "  Expected (AUTO): 30240\n\n";

echo "=== AUTO-Mode Erklärung ===\n";
echo "1. expected_output ist leer (\"\") für alle Iterationen\n";
echo "2. Das System berechnet den erwarteten Wert automatisch:\n";
echo "   - Nimmt solution_code\n";
echo "   - Ersetzt {a} und {b} mit den inputs-Werten\n";
echo "   - Führt den Code mit Pyodide aus\n";
echo "   - Liest die Variable 'summe' aus dem Namespace\n";
echo "   - Vergleicht: student_answer vs computed_value\n\n";

// Show all tasks in assignment #21
echo "=== Alle Tasks in Assignment #21 ===\n";
$r = $conn->query(
    "SELECT id, position, title, task_type FROM tasks WHERE assignment_id = 21 ORDER BY position"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo "  [{$row['position']}] Task #{$row['id']}: {$row['title']} ({$row['task_type']})\n";
    }
}

$conn->close();
echo "\n✅ Done!\n";
?>
