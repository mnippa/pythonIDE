<?php
/**
 * Migration 029: Create task "Fächerliste - drittes Element"
 * 
 * Student-Anforderung:
 * 1. Defininiere eine Liste mit 4 Fächern (beliebiger Inhalt)
 * 2. Gib das 3. Element (Index 2) aus
 * 
 * Prüfung (Intelligent Vars):
 * - Randomizer generiert 4 verschiedene Fächerlisten
 * - Solution Code zeigt: drittes_fach = faecher[2]
 * - Schüler-Code wird mit gleichen Fächerlisten getestet
 * - Ausgabe automatisch verglichen (egal welche Fächernamen)
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 029: create task 'Fächerliste - drittes Element'...\n";

    $assignmentId = 23;
    
    // Get next position
    $posResult = $conn->query("SELECT MAX(position) as max_pos FROM tasks WHERE assignment_id = $assignmentId");
    $posRow = $posResult->fetch_assoc();
    $nextPos = ($posRow['max_pos'] ?? 0) + 1;

    $title = 'Fächerliste: drittes Element';
    $description = 'Der Student definiert eine Liste mit 4 Fächernamen (beliebig wählbar) und gibt das dritte Fach aus.';

    $testCases = json_encode([
        'mode' => 'vars',
        'tests' => 4,
        'inputs' => ['faecher'],
        'outputs' => ['drittes_fach']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $randomizerCode = <<<'PY'
import random

# Verschiedene möglich Fächerlisten
faecher_sets = [
    ["Deutsch", "Englisch", "Mathematik", "Biologie"],
    ["Chemie", "Physik", "Geschichte", "Kunst"],
    ["Sport", "Musik", "Informatik", "Latein"],
    ["Geographie", "Ethik", "Wirtschaft", "Psychologie"]
]

values = {
    "faecher": random.choice(faecher_sets)
}
PY;

    $solutionCode = <<<'PY'
#INIT START
faecher = []
#INIT END

drittes_fach = faecher[2]
PY;

    $codeTemplate = <<<'PY'
# Definiere eine Liste mit 4 Fächernamen (beliebig)
faecher = ["Deutsch", "Englisch", "Mathematik", "Biologie"]

# Extrahiere das dritte Fach (Index 2)
drittes_fach = faecher[2]

# Gib es aus
print(drittes_fach)
PY;

    // Use direct inserted SQL to avoid bind_param complications
    // All strings are escaped for safety
    $assignmentIdSafe = (int)$assignmentId;
    $nextPosSafe = (int)$nextPos;
    $titleSafe = $conn->real_escape_string($title);
    $descriptionSafe = $conn->real_escape_string($description);
    $taskTypeSafe = $conn->real_escape_string('code');
    $codeTemplateSafe = $conn->real_escape_string($codeTemplate);
    $solutionCodeSafe = $conn->real_escape_string($solutionCode);
    $randomizerCodeSafe = $conn->real_escape_string($randomizerCode);
    $testCasesSafe = $conn->real_escape_string($testCases);
    $maxAttemptsSafe = (int)10;

    $insertSql = "INSERT INTO tasks (
        assignment_id, title, description, position, 
        task_type, code_template, solution_code, randomizer_code,
        test_cases, max_attempts, 
        created_at, updated_at
    ) VALUES (
        {$assignmentIdSafe}, 
        '{$titleSafe}', 
        '{$descriptionSafe}', 
        {$nextPosSafe},
        '{$taskTypeSafe}', 
        '{$codeTemplateSafe}', 
        '{$solutionCodeSafe}', 
        '{$randomizerCodeSafe}',
        '{$testCasesSafe}', 
        {$maxAttemptsSafe}, 
        NOW(), 
        NOW()
    )";

    if (!$conn->query($insertSql)) {
        throw new Exception('Insert failed: ' . $conn->error);
    }

    $taskId = $conn->insert_id;

    echo "✓ Task #$taskId created\n";
    echo "  Title: $title\n";
    echo "  Assignment: $assignmentId\n";
    echo "  Position: $nextPos\n";
    echo "  Type: Intelligent Vars (4 randomized tests)\n";
    echo "  Inputs: faecher (any 4-element list)\n";
    echo "  Outputs: drittes_fach (must equal faecher[2])\n";

    echo "\n✅ Migration 029: Success!\n";
} catch (Exception $e) {
    echo "❌ Migration 029 failed: " . $e->getMessage() . "\n";
    exit(1);
}
