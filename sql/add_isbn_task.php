<?php
/**
 * Add ISBN Validation Task to Assignment #21
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Get the next position for assignment #21
$result = $conn->query("SELECT COALESCE(MAX(position), 0) + 1 as next_position FROM tasks WHERE assignment_id = 21");
$nextPosition = $result->fetch_assoc()['next_position'];

// Prepare the test cases JSON
$testCases = json_encode([
    [
        'type' => 'output',
        'validation_mode' => 'pattern',
        'pattern' => '^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$',
        'description' => 'Gültige ISBN-13 im Format: ISBN 978-X-XX-XXXXXX-X'
    ]
]);

// Prepare the description HTML
$description = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>' .
               '<table class="test-requirements-table">' .
               '<thead><tr><th>Aspekt</th><th>Details</th></tr></thead>' .
               '<tbody><tr><td>OUTPUT</td><td>Pattern Match (Regex)</td></tr></tbody>' .
               '</table></div>';

// Prepare the task_text
$taskText = "Schreibe ein Python-Programm, das eine gültige ISBN-13 ausgibt. Die ISBN muss folgendes Format haben:\n\n" .
            "**Format:** `ISBN 978-X-XX-XXXXXX-X` oder `ISBN 979-X-XX-XXXXXX-X`\n\n" .
            "**Beispiele:**\n" .
            "- `ISBN 978-3-16-148410-0`\n" .
            "- `ISBN 979-1-23-456789-5`\n\n" .
            "**Hinweise:**\n" .
            "- Die ISBN muss mit \"ISBN \" (mit Leerzeichen) beginnen\n" .
            "- Die Präfix-Gruppe ist entweder 978 oder 979\n" .
            "- Die Zahlengruppen sind durch Bindestriche getrennt";

// Insert the task
$stmt = $conn->prepare("
    INSERT INTO tasks (
        assignment_id, title, description, position, max_attempts, iterations_count,
        show_solution, show_solution_code, min_keywords_required, problem_type,
        code_template, hint1, hint2, hint3, stoff, expected_output, test_cases,
        solution_code, task_type, task_text, question_text, image_url,
        correct_answer, variable_overrides, randomizer_code
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?
    )
");

$assignmentId = 21;
$title = "ISBN Validierung";
$maxAttempts = 3;
$iterationsCount = 1;
$showSolution = 1;
$showSolutionCode = 1;
$minKeywordsRequired = 0;  // Changed from null to 0
$problemType = "code_completion";
$codeTemplate = "# Gib eine gültige ISBN aus\n# Format: ISBN 978-3-16-148410-0\n\nprint(\"ISBN \")";
$hint1 = "Eine ISBN-13 beginnt mit 978 oder 979";
$hint2 = "Das Format ist: ISBN XXX-X-XX-XXXXXX-X";
$hint3 = "Verwende print() für die Ausgabe";
$stoff = "ISBN (International Standard Book Number) ist eine weltweit eindeutige Produktkennzeichnung für Bücher.";
$expectedOutput = "";  // not used for regex validation
$solutionCode = "# Musterlösung\nprint(\"ISBN 978-3-16-148410-0\")";
$taskType = "code";
$questionText = "";
$imageUrl = "";  // Changed from null to empty string
$correctAnswer = "";  // Changed from null to empty string
$variableOverrides = "";  // Changed from null to empty string
$randomizerCode = "";  // Changed from null to empty string

$stmt->bind_param(
    "issiiiiiisssssssssssssss",  // 25 parameters: i, s, s, i, i, i, i, i, i, s, s, s, s, s, s, s, s, s, s, s, s, s, s, s, s
    $assignmentId,           // 1
    $title,                  // 2
    $description,            // 3
    $nextPosition,           // 4
    $maxAttempts,            // 5
    $iterationsCount,        // 6
    $showSolution,           // 7
    $showSolutionCode,       // 8
    $minKeywordsRequired,    // 9
    $problemType,            // 10
    $codeTemplate,           // 11
    $hint1,                  // 12
    $hint2,                  // 13
    $hint3,                  // 14
    $stoff,                  // 15
    $expectedOutput,         // 16
    $testCases,              // 17
    $solutionCode,           // 18
    $taskType,               // 19
    $taskText,               // 20
    $questionText,           // 21
    $imageUrl,               // 22
    $correctAnswer,          // 23
    $variableOverrides,      // 24
    $randomizerCode          // 25
);

if ($stmt->execute()) {
    $taskId = $conn->insert_id;
    
    echo "✅ Task erfolgreich erstellt!\n\n";
    echo "Task ID: $taskId\n";
    echo "Title: $title\n";
    echo "Position: $nextPosition\n";
    echo "Assignment: #21\n\n";
    
    // Show all tasks in assignment #21
    echo "Alle Tasks in Assignment #21:\n";
    echo str_repeat("-", 80) . "\n";
    
    $result = $conn->query("
        SELECT id, position, title, task_type, created_at
        FROM tasks
        WHERE assignment_id = 21
        ORDER BY position
    ");
    
    while ($row = $result->fetch_assoc()) {
        printf(
            "ID: %-4d | Pos: %-2d | Type: %-15s | %s\n",
            $row['id'],
            $row['position'],
            $row['task_type'],
            $row['title']
        );
    }
    
} else {
    echo "❌ Fehler beim Erstellen der Task:\n";
    echo $stmt->error . "\n";
    exit(1);
}

$conn->close();
