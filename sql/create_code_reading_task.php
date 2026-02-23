<?php
/**
 * Create CODE_READING Task (AUTO-Mode) in Assignment #21
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Get next position
$result = $conn->query("SELECT COALESCE(MAX(position), 0) + 1 as next_position FROM tasks WHERE assignment_id = 21");
$row = $result->fetch_assoc();
$nextPosition = $row['next_position'];

echo "Creating CODE_READING task at position $nextPosition\n";

$assignmentId = 21;
$title = 'Code Reading: Summen-Algorithmus mit Schleife (AUTO-Mode Demo)';
$description = 'Analysiere einen Algorithmus mit verschachtelter Berechnung';
$taskText = 'Analysiere den Code und bestimme den Wert der Variablen "summe" am Ende der Ausführung.';
$position = $nextPosition;
$taskType = 'code_reading';
$maxAttempts = 3;
$iterationsCount = 3;

$codeTemplate = 'summe = 1

for n in range({a}, {b}):
    summe = summe + n * summe

# Was ist der Wert von "summe" am Ende?';

$solutionCode = 'summe = 1

for n in range({a}, {b}):
    summe = summe + n * summe';

$correctAnswer = 'summe';

$variableOverrides = json_encode([
    ['inputs' => ['a' => 1, 'b' => 5], 'expected_output' => ''],
    ['inputs' => ['a' => 2, 'b' => 6], 'expected_output' => ''],
    ['inputs' => ['a' => 5, 'b' => 10], 'expected_output' => '']
]);

// Simple INSERT without bind_param to avoid type issues
$sql = sprintf(
    "INSERT INTO tasks (assignment_id, title, description, position, max_attempts, iterations_count, show_solution, show_solution_code, problem_type, code_template, solution_code, task_type, task_text, correct_answer, variable_overrides) VALUES (%d, '%s', '%s', %d, %d, %d, 0, 0, 'code_completion', '%s', '%s', 'code_reading', '%s', '%s', '%s')",
    $assignmentId,
    $conn->real_escape_string($title),
    $conn->real_escape_string($description),
    $position,
    $maxAttempts,
    $iterationsCount,
    $conn->real_escape_string($codeTemplate),
    $conn->real_escape_string($solutionCode),
    $conn->real_escape_string($taskText),
    $conn->real_escape_string($correctAnswer),
    $conn->real_escape_string($variableOverrides)
);

if ($conn->query($sql)) {
    $taskId = $conn->insert_id;
    echo "✅ Task created successfully! Task ID: $taskId\n\n";
    
    echo "=== Task Details ===\n";
    echo "Title: $title\n";
    echo "Type: $taskType\n";
    echo "Position: $position\n";
    echo "Iterations: $iterationsCount\n\n";
    
    echo "=== Code Template ===\n";
    echo $codeTemplate . "\n\n";
    
    echo "=== Solution Code ===\n";
    echo $solutionCode . "\n\n";
    
    echo "=== Variable Overrides (3 Iterationen, AUTO-Mode) ===\n";
    echo "Iteration 1: a=1, b=5   (expected_output=\"\" → AUTO)\n";
    echo "Iteration 2: a=2, b=6   (expected_output=\"\" → AUTO)\n";
    echo "Iteration 3: a=5, b=10  (expected_output=\"\" → AUTO)\n\n";
    
    echo "=== Wie AUTO funktioniert ===\n";
    echo "1. Backend liest iteration[0]: {inputs: {a: 1, b: 5}, expected_output: \"\"}\n";
    echo "2. Da expected_output leer ist, AUTO-Mode\n";
    echo "3. Nimmt solution_code: 'summe = 1; for n in range({a}, {b}): summe = summe + n * summe'\n";
    echo "4. Ersetzt {a} und {b}: 'summe = 1; for n in range(1, 5): summe = summe + n * summe'\n";
    echo "5. Führt mit Pyodide aus\n";
    echo "6. Liest Variable 'summe' → expected_output = 120\n";
    echo "7. Vergleicht: student_answer vs 120\n\n";
    
    // Show all tasks
    echo "=== Alle Tasks in Assignment #21 ===\n";
    $r = $conn->query("SELECT id, position, title, task_type FROM tasks WHERE assignment_id = 21 ORDER BY position");
    while ($row = $r->fetch_assoc()) {
        echo "  [{$row['position']}] Task #{$row['id']}: {$row['title']} ({$row['task_type']})\n";
    }
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

$conn->close();
?>
