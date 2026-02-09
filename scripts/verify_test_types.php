<?php
/**
 * Verify Test Type Examples
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "TEST-TYPEN VERIFIZIERUNG\n";
echo "========================================\n\n";

// Find the assignment
$stmt = $conn->prepare("
    SELECT id, title FROM assignments 
    WHERE title = 'Test-Typen: Output, Function, Variable'
    ORDER BY id DESC LIMIT 1
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ Assignment nicht gefunden!\n";
    echo "Führen Sie zuerst aus: php scripts/create_test_type_examples.php\n";
    exit(1);
}

$assignment = $result->fetch_assoc();
echo "✓ Assignment: {$assignment['title']} (ID: {$assignment['id']})\n\n";

// Get tasks
$taskStmt = $conn->prepare("
    SELECT id, title, test_cases FROM tasks 
    WHERE assignment_id = ?
    ORDER BY position ASC
");
$taskStmt->bind_param('i', $assignment['id']);
$taskStmt->execute();
$tasks = $taskStmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "========================================\n";
echo "ANALYSE DER TEST-TYPEN\n";
echo "========================================\n\n";

foreach ($tasks as $task) {
    $testCases = json_decode($task['test_cases'], true);
    $firstTest = $testCases[0];
    
    echo "Task {$task['id']}: {$task['title']}\n";
    echo str_repeat('-', 70) . "\n";
    
    // Detect type
    if (isset($firstTest['type'])) {
        $type = strtoupper($firstTest['type']);
        echo "✓ Expliziter Typ: $type\n";
    } else {
        echo "❌ Kein expliziter Typ angegeben\n";
        $type = 'UNBEKANNT';
    }
    
    echo "Anzahl Tests: " . count($testCases) . "\n\n";
    
    // Show structure based on type
    foreach ($testCases as $idx => $tc) {
        echo "  Test " . ($idx + 1) . ":\n";
        
        if ($type === 'OUTPUT') {
            echo "    Type: OUTPUT\n";
            if (is_array($tc['expected'])) {
                echo "    Expected: MEHRERE PATTERNS (" . count($tc['expected']) . " Optionen)\n";
                foreach ($tc['expected'] as $i => $opt) {
                    echo "      [$i] $opt\n";
                }
            } else {
                echo "    Expected: {$tc['expected']}\n";
            }
            
        } elseif ($type === 'FUNCTION') {
            echo "    Type: FUNCTION\n";
            echo "    Function: {$tc['function_name']}\n";
            echo "    Args: " . json_encode($tc['args']) . "\n";
            echo "    Expected: " . json_encode($tc['expected']) . "\n";
            
        } elseif ($type === 'VARIABLE') {
            echo "    Type: VARIABLE\n";
            echo "    Init Vars:\n";
            foreach ($tc['init_vars'] as $var => $val) {
                echo "      $var = " . json_encode($val) . "\n";
            }
            echo "    Expected Vars:\n";
            foreach ($tc['expected_vars'] as $var => $val) {
                echo "      $var = " . json_encode($val) . "\n";
            }
        }
        
        echo "\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "ZUSAMMENFASSUNG\n";
echo "========================================\n\n";

$typeStats = ['OUTPUT' => 0, 'FUNCTION' => 0, 'VARIABLE' => 0];

foreach ($tasks as $task) {
    $testCases = json_decode($task['test_cases'], true);
    $firstTest = $testCases[0];
    
    if (isset($firstTest['type'])) {
        $type = strtoupper($firstTest['type']);
        $typeStats[$type]++;
    }
}

echo "Tasks pro Typ:\n";
echo "  OUTPUT:   {$typeStats['OUTPUT']}\n";
echo "  FUNCTION: {$typeStats['FUNCTION']}\n";
echo "  VARIABLE: {$typeStats['VARIABLE']}\n";
echo "  TOTAL:    " . array_sum($typeStats) . "\n\n";

echo "========================================\n";
echo "FRONTEND-INTEGRATION\n";
echo "========================================\n\n";

echo "Die JavaScript-Integration (assignments.js) unterstützt:\n\n";

echo "1. detectTestType(testCases)\n";
echo "   → Erkennt Test-Typ (explizit oder auto-detect)\n\n";

echo "2. runOutputTests(pyodide, code, testCases, mode)\n";
echo "   → Führt Code aus\n";
echo "   → Erfasst stdout\n";
echo "   → Vergleicht mit expected (String oder Array)\n\n";

echo "3. runFunctionTests(pyodide, code, testCases, mode)\n";
echo "   → Definiert Funktionen\n";
echo "   → Ruft function_name(*args) auf\n";
echo "   → Vergleicht Return-Wert mit expected\n\n";

echo "4. runVariableTests(pyodide, code, testCases, mode)\n";
echo "   → Setzt init_vars\n";
echo "   → Führt Code aus\n";
echo "   → Prüft expected_vars\n\n";

echo "5. displayTestResults(results, testCases, outputEl)\n";
echo "   → Typ-spezifische UI\n";
echo "   → Zeigt Input/Output/Expected\n";
echo "   → Granulare Fehlerbehandlung\n\n";

echo "========================================\n";
echo "TESTEN IN DER IDE\n";
echo "========================================\n\n";

echo "URL: http://localhost/pythonIDE/public/assignments.php\n";
echo "Assignment: '{$assignment['title']}'\n\n";

echo "Probieren Sie alle 6 Tasks aus:\n";
foreach ($tasks as $idx => $task) {
    echo "  " . ($idx + 1) . ". {$task['title']}\n";
}

echo "\n";

$conn->close();
