<?php
/**
 * Example: Update task with multiple acceptable solutions
 * Beispiel: Aufgabe mit mehreren korrekten Lösungen
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "Beispiel: Mehrere korrekte Lösungen\n";
echo "========================================\n\n";

// BEISPIEL 1: String-Formatierung - Mit ODER ohne Punkt akzeptieren
echo "Beispiel 1: String mit/ohne Punkt am Ende\n";
echo "-------------------------------------------\n";

$multipleOptions1 = json_encode([
    [
        'input' => '', 
        'expected' => [
            'Ich bin Max und 25 Jahre alt.',   // Option 1: mit Punkt
            'Ich bin Max und 25 Jahre alt'     // Option 2: ohne Punkt
        ]
    ]
]);

echo "Format:\n";
echo json_encode(json_decode($multipleOptions1), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// BEISPIEL 2: Verschiedene Ausgabeformate für Listen
echo "Beispiel 2: Listen-Ausgabe - verschiedene Formate\n";
echo "---------------------------------------------------\n";

$multipleOptions2 = json_encode([
    [
        'input' => '', 
        'expected' => [
            '[2, 4, 6]',          // Python list format
            '2 4 6',              // Space separated
            '2, 4, 6',            // Comma separated
            '[2,4,6]'             // Compact format
        ]
    ]
]);

echo "Format:\n";
echo json_encode(json_decode($multipleOptions2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// BEISPIEL 3: Boolean-Ausgaben - verschiedene Schreibweisen
echo "Beispiel 3: Boolean - verschiedene Schreibweisen\n";
echo "--------------------------------------------------\n";

$multipleOptions3 = json_encode([
    [
        'input' => '', 
        'expected' => [
            'True',               // Python style
            'true',               // lowercase
            '1',                  // numeric
            'Wahr'                // German
        ]
    ]
]);

echo "Format:\n";
echo json_encode(json_decode($multipleOptions3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Optional: Update eine echte Aufgabe (auskommentiert)
/*
$stmt = $conn->prepare("UPDATE tasks SET test_cases = ? WHERE title = 'String-Formatierung'");
$stmt->bind_param('s', $multipleOptions1);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "✓ Updated Task: String-Formatierung mit mehreren Lösungen\n";
} else {
    echo "✗ Task nicht gefunden oder bereits aktualisiert\n";
}
*/

echo "========================================\n";
echo "Verwendung:\n";
echo "========================================\n\n";

echo "Im Aufgaben-Script:\n\n";
echo "'test_cases' => json_encode([\n";
echo "    ['input' => '', 'expected' => [\n";
echo "        'Lösung 1',\n";
echo "        'Lösung 2',\n";
echo "        'Lösung 3'\n";
echo "    ]]\n";
echo "]),\n\n";

echo "Validierung:\n";
echo "- Test besteht, wenn Output EINER der Lösungen entspricht\n";
echo "- Ideal für: verschiedene Formatierungen, Reihenfolgen, Schreibweisen\n";
echo "- Funktioniert mit 'loose' und 'strict' validation mode\n\n";

echo "Vorteile:\n";
echo "✓ Akzeptiert mehrere korrekte Ansätze\n";
echo "✓ Flexibler für Studenten\n";
echo "✓ Einfach zu definieren (Array statt String)\n";
echo "✓ Zeigt welche Lösung gewählt wurde\n\n";

$conn->close();
