<?php
/**
 * Recreate CODE_READING Demo Task with NEW Schema
 * NEW expected format: {inputs: {...}, expected: {variable: "..." | value: ...}}
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
if (!$conn) {
    die("DB Connection failed\n");
}

// First, delete old Task #145 if it exists
$stmt = $conn->prepare('DELETE FROM tasks WHERE id = 145');
if (!$stmt) {
    die("Delete prepare failed: " . $conn->error . "\n");
}
$stmt->execute();
echo "✓ Old Task #145 deleted (if existed)\n\n";

// Get last position in Assignment #21
$stmt = $conn->prepare('SELECT MAX(position) as max_pos FROM tasks WHERE assignment_id = 21');
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$nextPosition = ($result['max_pos'] ?? 0) + 1;

// Define task data
$taskId = 145;
$assignmentId = 21;
$position = $nextPosition;
$title = "Code Reading: Summen-Algorithmus mit Schleife (Neue expected Struktur)";
$description = "Analysiere den Algorithmus um die resultierende Summe zu berechnen. Die Variable 'summe' wird am Ende des Scripts gelesen.";
$taskText = "Analysiere den folgenden Code und berechne den endwertigen Wert der Variable **summe** nach der Schleife:\n\n```python\nsumme = 1\nfor n in range({a}, {b}):\n    summe = summe + n * summe\n```\n\nGib den Wert von **summe** ein.";
$taskType = "code_reading";
$problemType = "code_completion";

$codeTemplate = <<<'CODE'
summe = 1
for n in range({a}, {b}):
    summe = summe + n * summe
CODE;

$solutionCode = <<<'SOLUTION'
summe = 1
for n in range({a}, {b}):
    summe = summe + n * summe
SOLUTION;

$correctAnswer = "summe";
$showSolutionCode = true;
$maxIterations = 3;

// NEW SCHEMA: {inputs: {...}, expected: {variable: "summe"}}
$variableOverrides = [
    [
        "inputs" => ["a" => 1, "b" => 5],
        "expected" => ["variable" => "summe"]  // Will read 'summe' variable value from solution_code
    ],
    [
        "inputs" => ["a" => 2, "b" => 6],
        "expected" => ["variable" => "summe"]
    ],
    [
        "inputs" => ["a" => 5, "b" => 10],
        "expected" => ["variable" => "summe"]
    ]
];

$overridesJson = json_encode($variableOverrides, JSON_PRETTY_PRINT);

// Insert task
$stmt = $conn->prepare(
    'INSERT INTO tasks (id, assignment_id, position, title, description, task_text, task_type, problem_type, 
                       code_template, solution_code, correct_answer, show_solution_code, 
                       iterations_count, variable_overrides)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

// Type string: i=int, s=string
// 1=taskId(i), 2=assignmentId(i), 3=position(i), 4=title(s), 5=description(s), 6=taskText(s),
// 7=taskType(s), 8=problemType(s), 9=codeTemplate(s), 10=solutionCode(s), 11=correctAnswer(s),
// 12=showSolutionCode(i), 13=maxIterations(i), 14=overridesJson(s)
$typeStr = "iiissssssssiis";
$stmt->bind_param(
    $typeStr,
    $taskId,
    $assignmentId,
    $position,
    $title,
    $description,
    $taskText,
    $taskType,
    $problemType,
    $codeTemplate,
    $solutionCode,
    $correctAnswer,
    $showSolutionCode,
    $maxIterations,
    $overridesJson
);

if ($stmt->execute()) {
    echo "✅ Task created successfully! Task ID: 145\n\n";
    echo "=== Task Details ===\n";
    echo "Title: $title\n";
    echo "Type: $taskType\n";
    echo "Position: $position in Assignment #21\n";
    echo "Iterations: " . count($variableOverrides) . "\n\n";
    
    echo "=== Variable Overrides (NEW Schema) ===\n";
    echo "Iteration 1: a=1, b=5  → expected: {\"variable\": \"summe\"}\n";
    echo "Iteration 2: a=2, b=6  → expected: {\"variable\": \"summe\"}\n";
    echo "Iteration 3: a=5, b=10 → expected: {\"variable\": \"summe\"}\n\n";
    
    echo "Variable to extract (all iterations): summe\n\n";
    
    echo "=== Explanation ===\n";
    echo "When expected.variable is set:\n";
    echo "1. Client executes solution_code with inputs\n";
    echo "2. Client extracts the variable specified (summe)\n";
    echo "3. Client sends computed_value to backend\n";
    echo "4. Backend compares student answer with computed_value\n\n";
    
    // Show all tasks in assignment
    echo "=== Alle Tasks in Assignment #21 ===\n";
    $stmt = $conn->prepare('SELECT id, position, title, task_type FROM tasks WHERE assignment_id = 21 ORDER BY position');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        echo "  [" . $row['position'] . "] Task #" . $row['id'] . ": " . $row['title'] . " (" . $row['task_type'] . ")\n";
    }
} else {
    die("Execute failed: " . $stmt->error . "\n");
}

$conn->close();
