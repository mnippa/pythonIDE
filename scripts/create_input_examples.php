<?php
/**
 * Create Example Tasks with INPUT-based testing
 * Zeigt wie man verschiedene Inputs für Tests nutzt
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "Beispiel-Tasks mit INPUT-Testing\n";
echo "========================================\n\n";

// Get or create an assignment for these examples
$stmt = $conn->prepare("
    INSERT INTO assignments (title, description, difficulty, is_active, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
");

$title = 'Funktionen mit verschiedenen Eingaben';
$description = 'Übungen zu Funktionen, die mit verschiedenen Inputs getestet werden';
$difficulty = 'beginner';
$isActive = 1;
$createdBy = 1;

$stmt->bind_param('sssii', $title, $description, $difficulty, $isActive, $createdBy);
$stmt->execute();
$assignmentId = $conn->insert_id;

echo "✓ Assignment erstellt: $title (ID: $assignmentId)\n\n";

// ============================================================
// BEISPIEL 1: Quadrat-Funktion (Einfacher numerischer Input)
// ============================================================
$task1 = [
    'title' => 'Quadrat berechnen',
    'description' => 'Schreiben Sie eine Funktion `quadrat(x)`, die das Quadrat einer Zahl berechnet.

**Die Funktion wird mit verschiedenen Werten getestet:**
- quadrat(5) → 25
- quadrat(10) → 100
- quadrat(-3) → 9
- quadrat(0) → 0',
    'position' => 1,
    'problem_type' => 'code_completion',
    'code_template' => 'def quadrat(x):
    """Berechnet das Quadrat von x"""
    return x * ___

# Die Funktion wird automatisch getestet
# Vervollständige die Funktion oben',
    'hint' => 'x * x oder x ** 2',
    'expected_output' => '',
    'test_cases' => json_encode([
        ['input' => '5', 'expected' => '25'],
        ['input' => '10', 'expected' => '100'],
        ['input' => '-3', 'expected' => '9'],
        ['input' => '0', 'expected' => '0']
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => 'def quadrat(x):
    """Berechnet das Quadrat von x"""
    return x * x'
];

// ============================================================
// BEISPIEL 2: String-Umkehrung (String-Input)
// ============================================================
$task2 = [
    'title' => 'String umkehren',
    'description' => 'Schreiben Sie eine Funktion `umkehren(text)`, die einen String umdreht.

**Beispiele:**
- umkehren("Hallo") → "ollaH"
- umkehren("Python") → "nohtyP"
- umkehren("Test") → "tseT"
- umkehren("A") → "A"',
    'position' => 2,
    'problem_type' => 'code_completion',
    'code_template' => 'def umkehren(text):
    """Kehrt einen String um"""
    return text[___]

# Die Funktion wird automatisch mit verschiedenen Texten getestet',
    'hint' => 'Nutze String-Slicing: text[::-1]',
    'expected_output' => '',
    'test_cases' => json_encode([
        ['input' => 'Hallo', 'expected' => 'ollaH'],
        ['input' => 'Python', 'expected' => 'nohtyP'],
        ['input' => 'Test', 'expected' => 'tseT'],
        ['input' => 'A', 'expected' => 'A']
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => 'def umkehren(text):
    """Kehrt einen String um"""
    return text[::-1]'
];

// ============================================================
// BEISPIEL 3: Bereichsprüfung (Kombinierter Input)
// ============================================================
$task3 = [
    'title' => 'Zahl in Bereich prüfen',
    'description' => 'Schreiben Sie eine Funktion `im_bereich(zahl, minimum, maximum)`, die prüft ob eine Zahl in einem Bereich liegt.

**Beispiele:**
- im_bereich(5, 1, 10) → True
- im_bereich(15, 1, 10) → False
- im_bereich(0, -5, 5) → True
- im_bereich(10, 10, 20) → True (Randwerte zählen)',
    'position' => 3,
    'problem_type' => 'code_completion',
    'code_template' => 'def im_bereich(zahl, minimum, maximum):
    """Prüft ob zahl zwischen minimum und maximum liegt (inklusive)"""
    return ___ <= zahl <= ___

# Tests mit verschiedenen Werten',
    'hint' => 'Nutze: minimum <= zahl <= maximum',
    'expected_output' => '',
    'test_cases' => json_encode([
        ['input' => '5,1,10', 'expected' => 'True'],
        ['input' => '15,1,10', 'expected' => 'False'],
        ['input' => '0,-5,5', 'expected' => 'True'],
        ['input' => '10,10,20', 'expected' => 'True']
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => 'def im_bereich(zahl, minimum, maximum):
    """Prüft ob zahl zwischen minimum und maximum liegt (inklusive)"""
    return minimum <= zahl <= maximum'
];

// Insert all tasks
$tasks = [$task1, $task2, $task3];

$taskStmt = $conn->prepare('
    INSERT INTO tasks (
        assignment_id, title, description, position, problem_type,
        code_template, hint, expected_output, test_cases, 
        validation_mode, max_attempts, solution_code, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
');

foreach ($tasks as $task) {
    $taskStmt->bind_param(
        'issssssssiss',
        $assignmentId,
        $task['title'],
        $task['description'],
        $task['position'],
        $task['problem_type'],
        $task['code_template'],
        $task['hint'],
        $task['expected_output'],
        $task['test_cases'],
        $task['validation_mode'],
        $task['max_attempts'],
        $task['solution_code']
    );
    
    if ($taskStmt->execute()) {
        echo "✓ Task {$task['position']}: {$task['title']}\n";
    } else {
        echo "✗ Error: " . $taskStmt->error . "\n";
    }
}

echo "\n========================================\n";
echo "✓ 3 Beispiel-Tasks mit INPUT erstellt!\n";
echo "========================================\n\n";

// Show detailed examples
echo "BEISPIEL 1: Quadrat-Funktion\n";
echo "----------------------------\n";
echo "Test Cases:\n";
$tc1 = json_decode($task1['test_cases'], true);
foreach ($tc1 as $i => $tc) {
    echo "  Test " . ($i+1) . ": quadrat({$tc['input']}) → {$tc['expected']}\n";
}
echo "\n";

echo "BEISPIEL 2: String umkehren\n";
echo "---------------------------\n";
echo "Test Cases:\n";
$tc2 = json_decode($task2['test_cases'], true);
foreach ($tc2 as $i => $tc) {
    echo "  Test " . ($i+1) . ": umkehren(\"{$tc['input']}\") → \"{$tc['expected']}\"\n";
}
echo "\n";

echo "BEISPIEL 3: Bereichsprüfung\n";
echo "----------------------------\n";
echo "Test Cases:\n";
$tc3 = json_decode($task3['test_cases'], true);
foreach ($tc3 as $i => $tc) {
    $params = explode(',', $tc['input']);
    echo "  Test " . ($i+1) . ": im_bereich(" . implode(', ', $params) . ") → {$tc['expected']}\n";
}
echo "\n";

echo "========================================\n";
echo "Wie Input-Testing funktioniert:\n";
echo "========================================\n\n";

echo "1. EINFACHER INPUT (ein Parameter):\n";
echo "   {\"input\": \"5\", \"expected\": \"25\"}\n";
echo "   → Die Funktion wird mit input=5 aufgerufen\n\n";

echo "2. STRING INPUT:\n";
echo "   {\"input\": \"Hallo\", \"expected\": \"ollaH\"}\n";
echo "   → Die Funktion wird mit input=\"Hallo\" aufgerufen\n\n";

echo "3. MEHRERE PARAMETER (mit Komma getrennt):\n";
echo "   {\"input\": \"5,1,10\", \"expected\": \"True\"}\n";
echo "   → Die Funktion wird mit input=(5, 1, 10) aufgerufen\n\n";

echo "4. LEERER INPUT:\n";
echo "   {\"input\": \"\", \"expected\": \"Ergebnis\"}\n";
echo "   → Der Code wird ohne Funktionsaufruf ausgeführt\n";
echo "   → Ideal für Code der direkt print() nutzt\n\n";

echo "========================================\n";
echo "Format-Regeln:\n";
echo "========================================\n\n";

echo "✓ Input ist immer ein STRING\n";
echo "✓ Mehrere Parameter mit Komma trennen: \"5,10,15\"\n";
echo "✓ Strings in Input: einfach hinschreiben \"Hallo\"\n";
echo "✓ Leerer Input \"\" = kein Funktionsaufruf\n\n";

$conn->close();
