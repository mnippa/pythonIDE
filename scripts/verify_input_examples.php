<?php
/**
 * Verify Input-Based Testing Examples
 * Zeigt die erstellten Tasks mit ihren Test Cases
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "INPUT-TESTING BEISPIELE ÜBERPRÜFEN\n";
echo "========================================\n\n";

// Find the assignment
$stmt = $conn->prepare("
    SELECT id, title, description 
    FROM assignments 
    WHERE title = 'Funktionen mit verschiedenen Eingaben'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ Assignment nicht gefunden!\n";
    echo "Bitte führen Sie zuerst 'php scripts/create_input_examples.php' aus.\n";
    exit(1);
}

$assignment = $result->fetch_assoc();
echo "✓ Assignment gefunden: {$assignment['title']} (ID: {$assignment['id']})\n\n";

// Get all tasks for this assignment
$taskStmt = $conn->prepare("
    SELECT id, title, description, test_cases, validation_mode, max_attempts
    FROM tasks 
    WHERE assignment_id = ?
    ORDER BY position ASC
");
$taskStmt->bind_param('i', $assignment['id']);
$taskStmt->execute();
$tasks = $taskStmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "========================================\n";
echo "AUFGABEN MIT INPUT-TESTS\n";
echo "========================================\n\n";

foreach ($tasks as $idx => $task) {
    $testCases = json_decode($task['test_cases'], true);
    
    echo "TASK " . ($idx + 1) . ": {$task['title']}\n";
    echo str_repeat('-', 50) . "\n";
    echo "ID: {$task['id']}\n";
    echo "Validation Mode: {$task['validation_mode']}\n";
    echo "Max Attempts: {$task['max_attempts']}\n";
    echo "Anzahl Tests: " . count($testCases) . "\n\n";
    
    echo "Test Cases:\n";
    foreach ($testCases as $i => $tc) {
        echo "  Test " . ($i + 1) . ":\n";
        
        // Show input
        if (isset($tc['input']) && $tc['input'] !== '') {
            echo "    Input:    {$tc['input']}\n";
        } else {
            echo "    Input:    (kein Input - direkter Code-Run)\n";
        }
        
        // Show expected
        if (is_array($tc['expected'])) {
            echo "    Expected: (MEHRERE OPTIONEN)\n";
            foreach ($tc['expected'] as $opt) {
echo "              - $opt\n";
            }
        } else {
            echo "    Expected: {$tc['expected']}\n";
        }
        echo "\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "WIE FUNKTIONIERT INPUT-TESTING?\n";
echo "========================================\n\n";

echo "1. OHNE INPUT (Legacy Mode):\n";
echo "   {\"input\": \"\", \"expected\": \"Ergebnis\"}\n";
echo "   → Der gesamte Code wird ausgeführt\n";
echo "   → Output wird direkt verglichen\n";
echo "   → Ideal für Code mit print()\n\n";

echo "2. MIT INPUT (Function Testing Mode):\n";
echo "   {\"input\": \"5\", \"expected\": \"25\"}\n";
echo "   → Funktion im Code wird mit input=5 aufgerufen\n";
echo "   → Return-Wert oder print() wird erfasst\n";
echo "   → Ideal für Funktionen mit Parametern\n\n";

echo "3. MEHRERE PARAMETER:\n";
echo "   {\"input\": \"5,1,10\", \"expected\": \"True\"}\n";
echo "   → Komma-separierte Werte werden als Parameter übergeben\n";
echo "   → funktion(5, 1, 10) wird aufgerufen\n\n";

echo "4. STRING-PARAMETER:\n";
echo "   {\"input\": \"Hallo\", \"expected\": \"ollaH\"}\n";
echo "   → Strings werden automatisch erkannt\n";
echo "   → funktion(\"Hallo\") wird aufgerufen\n\n";

echo "========================================\n";
echo "TEST IN DER IDE\n";
echo "========================================\n\n";

echo "1. Öffnen Sie: http://localhost/pythonIDE/public/assignments.php\n";
echo "2. Wählen Sie das Assignment: '{$assignment['title']}'\n";
echo "3. Probieren Sie die Tasks aus:\n\n";

foreach ($tasks as $idx => $task) {
    echo "   Task " . ($idx + 1) . ": {$task['title']}\n";
    echo "   - Vervollständigen Sie den Code\n";
    echo "   - Klicken Sie auf 'Lösung prüfen'\n";
    echo "   - Sehen Sie die Ergebnisse für jeden Test\n\n";
}

echo "========================================\n";
echo "BEISPIELE FÜR LÖSUNGEN\n";
echo "========================================\n\n";

echo "Task 1: Quadrat berechnen\n";
echo "--------------------------\n";
echo "def quadrat(x):\n";
echo "    return x * x\n\n";

echo "Task 2: String umkehren\n";
echo "------------------------\n";
echo "def umkehren(text):\n";
echo "    return text[::-1]\n\n";

echo "Task 3: Zahl in Bereich prüfen\n";
echo "-------------------------------\n";
echo "def im_bereich(zahl, minimum, maximum):\n";
echo "    return minimum <= zahl <= maximum\n\n";

echo "========================================\n";
echo "VORTEILE VON INPUT-TESTING\n";
echo "========================================\n\n";

echo "✓ Funktionen werden mit echten Test-Daten aufgerufen\n";
echo "✓ Mehrere Test-Fälle für verschiedene Inputs\n";
echo "✓ Klare Trennung zwischen Tests\n";
echo "✓ Besseres Feedback für Studenten\n";
echo "✓ Return-Werte und Print-Ausgaben werden unterstützt\n\n";

$conn->close();
