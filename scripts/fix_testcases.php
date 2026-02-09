<?php
/**
 * Fix problematic test cases in database
 * Korrigiert fehlerhafte Test-Definitionen
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "Fixing Problematic Test Cases\n";
echo "========================================\n\n";

// Fix Task 15: String-Formatierung
// Problem: 2 Tests mit unterschiedlichem erwarteten Output (mit/ohne Punkt)
// Lösung: Nur ein Test mit Punkt (wie im Code-Template)
$task15Fix = json_encode([
    ['input' => '', 'expected' => 'Ich bin Max und 25 Jahre alt.']
]);

$stmt = $conn->prepare("UPDATE tasks SET test_cases = ? WHERE title = 'String-Formatierung'");
$stmt->bind_param('s', $task15Fix);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "✓ Fixed Task 15: String-Formatierung\n";
    echo "  Changed from: 2 tests (one with '.', one without)\n";
    echo "  Changed to: 1 test with '.'\n\n";
} else {
    echo "✗ Task 15 not found or already correct\n\n";
}

// Fix Task 18: Wort-Zähler
// Problem: 3 separate Tests mit jeweils 2, 3, 5 (unmöglich alle zu erfüllen)
// Lösung: Ein Test mit der kompletten Ausgabe "2\n3\n5"
$task18Fix = json_encode([
    ['input' => '', 'expected' => "2\n3\n5"]
]);

$stmt = $conn->prepare("UPDATE tasks SET test_cases = ? WHERE title = 'Wort-Zähler'");
$stmt->bind_param('s', $task18Fix);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "✓ Fixed Task 18: Wort-Zähler\n";
    echo "  Changed from: 3 tests (2, 3, 5 separately)\n";
    echo "  Changed to: 1 test with complete output '2\\n3\\n5'\n\n";
} else {
    echo "✗ Task 18 not found or already correct\n\n";
}

echo "========================================\n";
echo "✓ Test Cases Fixed!\n";
echo "========================================\n\n";

// Verify the fixes
echo "Verification:\n\n";

$result = $conn->query("
    SELECT id, title, test_cases, validation_mode
    FROM tasks 
    WHERE title IN ('String-Formatierung', 'Wort-Zähler')
    ORDER BY id
");

while ($row = $result->fetch_assoc()) {
    echo "Task {$row['id']}: {$row['title']}\n";
    echo "Validation Mode: {$row['validation_mode']}\n";
    
    $testCases = json_decode($row['test_cases'], true);
    if ($testCases && is_array($testCases)) {
        echo "Test Cases (" . count($testCases) . "):\n";
        foreach ($testCases as $idx => $tc) {
            echo "  Test " . ($idx + 1) . ":\n";
            echo "    Expected: '" . ($tc['expected'] ?? 'N/A') . "'\n";
        }
    }
    echo "\n";
}

$conn->close();
