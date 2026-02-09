<?php
/**
 * Demo: Update String-Formatierung task to accept both versions (with/without period)
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "Demo: Multiple Solutions aktivieren\n";
echo "========================================\n\n";

// Task 15: String-Formatierung - beide Varianten akzeptieren
echo "Task 15: String-Formatierung\n";
echo "-----------------------------\n";
echo "Aktuelles Problem: Nur eine Variante wird akzeptiert.\n";
echo "Neue Lösung: Beide Varianten (mit/ohne Punkt) sind korrekt.\n\n";

$newTestCases = json_encode([
    [
        'input' => '',
        'expected' => [
            'Ich bin Max und 25 Jahre alt.',  // MIT Punkt
            'Ich bin Max und 25 Jahre alt'    // OHNE Punkt
        ]
    ]
]);

echo "JSON:\n";
echo json_encode(json_decode($newTestCases), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Update the task
$stmt = $conn->prepare("UPDATE tasks SET test_cases = ? WHERE title = 'String-Formatierung'");
$stmt->bind_param('s', $newTestCases);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "✓ Task aktualisiert!\n\n";
    echo "Jetzt akzeptiert:\n";
    echo "  ✓ \"Ich bin Max und 25 Jahre alt.\"  (mit Punkt)\n";
    echo "  ✓ \"Ich bin Max und 25 Jahre alt\"   (ohne Punkt)\n\n";
    echo "Der Test besteht, wenn EINE der beiden Varianten ausgegeben wird.\n";
} else {
    echo "✗ Fehler oder Task nicht gefunden\n";
}

echo "\n========================================\n";
echo "Weitere Einsatzmöglichkeiten:\n";
echo "========================================\n\n";

echo "1. Reihenfolge egal:\n";
echo "   expected: ['Max Anna Tom', 'Anna Max Tom', 'Tom Max Anna']\n\n";

echo "2. Formatierung flexibel:\n";
echo "   expected: ['[1, 2, 3]', '[1,2,3]', '1 2 3']\n\n";

echo "3. Boolean-Varianten:\n";
echo "   expected: ['True', 'true', '1', 'wahr']\n\n";

echo "4. Float-Genauigkeit:\n";
echo "   expected: ['3.14', '3.1416', '3.141592']\n\n";

$conn->close();
