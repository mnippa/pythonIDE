<?php
/**
 * Create Assignment 3: "Funktionen und Prüfszenarien"
 * Various validation scenarios with solution codes
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "Creating Assignment 3: Funktionen\n";
echo "========================================\n\n";

// Create Assignment 3
$stmt = $conn->prepare('
    INSERT INTO assignments (title, description, difficulty, is_active, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
');

$title = 'Funktionen und Prüfszenarien';
$description = 'Verschiedene Aufgaben mit unterschiedlichen Test-Methoden: Output-Vergleich, Funktionen mit Parametern, und komplexe Validierung';
$difficulty = 'intermediate';
$isActive = 1;
$createdBy = 1; // Admin user

$stmt->bind_param('sssii', $title, $description, $difficulty, $isActive, $createdBy);
$stmt->execute();
$assignmentId = $conn->insert_id;

echo "✓ Created Assignment: $title (ID: $assignmentId)\n\n";

// ============================================================
// TASK 1: Einfacher Output-Vergleich
// ============================================================
$tasks = [
    [
        'title' => 'Liste von Zahlen ausgeben',
        'description' => 'Schreiben Sie Code, der die Zahlen 1 bis 5 ausgibt (jede Zahl in einer neuen Zeile).',
        'position' => 1,
        'problem_type' => 'code_completion',
        'code_template' => '# Geben Sie die Zahlen 1 bis 5 aus
for i in range(1, ___):
    print(___)',
        'hint' => 'range(1, 6) erzeugt Zahlen von 1 bis 5',
        'expected_output' => '1\n2\n3\n4\n5',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => "1\n2\n3\n4\n5"]
        ]),
        'validation_mode' => 'strict',
        'solution_code' => 'for i in range(1, 6):
    print(i)'
    ],
    
    // ============================================================
    // TASK 2: Mehrere Testfälle (loose mode)
    // ============================================================
    [
        'title' => 'String-Formatierung',
        'description' => 'Erstellen Sie einen String mit Ihrem Namen und Alter. Format: "Ich bin [Name] und [Alter] Jahre alt."',
        'position' => 2,
        'problem_type' => 'code_completion',
        'code_template' => 'name = "Max"
alter = 25

# Erstellen Sie den String
ausgabe = ___
print(ausgabe)',
        'hint' => 'Nutzen Sie f-Strings: f"Text {variable}"',
        'expected_output' => 'Ich bin Max und 25 Jahre alt.',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => 'Ich bin Max und 25 Jahre alt.']
        ]),
        'validation_mode' => 'loose',
        'solution_code' => 'name = "Max"
alter = 25
ausgabe = f"Ich bin {name} und {alter} Jahre alt."
print(ausgabe)'
    ],
    
    // ============================================================
    // TASK 3: Mehrwertsteuer-Berechnung (Funktionen)
    // ============================================================
    [
        'title' => 'Mehrwertsteuer berechnen',
        'description' => 'Schreiben Sie eine Funktion `berechne_mwst(netto, steuersatz=19)`, die den Bruttobetrag berechnet.

**Parameter:**
- `netto`: Nettobetrag (float)
- `steuersatz`: Steuersatz in Prozent (Standard: 19%)

**Rückgabe:** Bruttobetrag (float)

**Formel:** brutto = netto * (1 + steuersatz/100)

**Beispiele:**
- berechne_mwst(100) → 119.0
- berechne_mwst(100, 7) → 107.0',
        'position' => 3,
        'problem_type' => 'code_fix',
        'code_template' => 'def berechne_mwst(netto, steuersatz=___):
    """
    Berechnet den Bruttobetrag aus Netto + MwSt
    """
    brutto = netto * (1 + ___ / 100)
    return brutto

# Test
print(berechne_mwst(100))
print(berechne_mwst(100, 7))
print(berechne_mwst(50, 19))',
        'hint' => 'Default-Parameter: def func(param=wert). Formel: netto * (1 + steuersatz/100)',
        'expected_output' => '119.0\n107.0\n59.5',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => "119.0\n107.0\n59.5"]
        ]),
        'validation_mode' => 'loose',
        'solution_code' => 'def berechne_mwst(netto, steuersatz=19):
    """
    Berechnet den Bruttobetrag aus Netto + MwSt
    """
    brutto = netto * (1 + steuersatz / 100)
    return brutto

# Test
print(berechne_mwst(100))
print(berechne_mwst(100, 7))
print(berechne_mwst(50, 19))'
    ],
    
    // ============================================================
    // TASK 4: Liste filtern (komplexer)
    // ============================================================
    [
        'title' => 'Gerade Zahlen filtern',
        'description' => 'Schreiben Sie eine Funktion `nur_gerade(liste)`, die nur gerade Zahlen aus einer Liste zurückgibt.

**Beispiele:**
- nur_gerade([1,2,3,4,5,6]) → [2, 4, 6]
- nur_gerade([10, 15, 20, 25]) → [10, 20]',
        'position' => 4,
        'problem_type' => 'code_fix',
        'code_template' => 'def nur_gerade(liste):
    """
    Gibt nur gerade Zahlen zurück
    """
    ergebnis = []
    for zahl in liste:
        if ___:  # Prüfung ob gerade
            ergebnis.append(___)
    return ergebnis

# Tests
print(nur_gerade([1,2,3,4,5,6]))
print(nur_gerade([10, 15, 20, 25]))',
        'hint' => 'Eine Zahl ist gerade wenn: zahl % 2 == 0',
        'expected_output' => '[2, 4, 6]\n[10, 20]',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => "[2, 4, 6]\n[10, 20]"]
        ]),
        'validation_mode' => 'loose',
        'solution_code' => 'def nur_gerade(liste):
    """
    Gibt nur gerade Zahlen zurück
    """
    ergebnis = []
    for zahl in liste:
        if zahl % 2 == 0:
            ergebnis.append(zahl)
    return ergebnis

# Tests
print(nur_gerade([1,2,3,4,5,6]))
print(nur_gerade([10, 15, 20, 25]))'
    ],
    
    // ============================================================
    // TASK 5: String-Methoden (multiple tests)
    // ============================================================
    [
        'title' => 'Wort-Zähler',
        'description' => 'Schreiben Sie eine Funktion `zaehle_woerter(text)`, die die Anzahl der Wörter im Text zählt.

**Hinweis:** Nutzen Sie `text.split()` um den Text in Wörter zu teilen.

**Beispiele:**
- zaehle_woerter("Hallo Welt") → 2
- zaehle_woerter("Python ist toll") → 3',
        'position' => 5,
        'problem_type' => 'code_completion',
        'code_template' => 'def zaehle_woerter(text):
    """
    Zählt Wörter im Text
    """
    woerter = text.split()
    return len(___)

# Tests
print(zaehle_woerter("Hallo Welt"))
print(zaehle_woerter("Python ist toll"))
print(zaehle_woerter("Ein Satz mit fuenf Woertern"))',
        'hint' => 'split() teilt String bei Leerzeichen, len() gibt Länge zurück',
        'expected_output' => '2\n3\n5',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => "2\n3\n5"]
        ]),
        'validation_mode' => 'strict',
        'solution_code' => 'def zaehle_woerter(text):
    """
    Zählt Wörter im Text
    """
    woerter = text.split()
    return len(woerter)

# Tests
print(zaehle_woerter("Hallo Welt"))
print(zaehle_woerter("Python ist toll"))
print(zaehle_woerter("Ein Satz mit fuenf Woertern"))'
    ]
];

// Insert tasks
$taskStmt = $conn->prepare('
    INSERT INTO tasks (
        assignment_id, title, description, position, problem_type,
        code_template, hint, expected_output, test_cases, 
        validation_mode, solution_code, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
');

foreach ($tasks as $task) {
    $taskStmt->bind_param(
        'issssssssss',
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
        $task['solution_code']
    );
    
    if ($taskStmt->execute()) {
        echo "✓ Task {$task['position']}: {$task['title']}\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
}

echo "\n========================================\n";
echo "✓ Assignment 3 created with 5 tasks!\n";
echo "========================================\n";

// Show summary
echo "\nTest Scenarios:\n";
echo "- Task 1: Strict output comparison\n";
echo "- Task 2: Loose mode (whitespace tolerant)\n";
echo "- Task 3: Function with default parameter (Mehrwertsteuer)\n";
echo "- Task 4: List filtering function\n";
echo "- Task 5: Multiple test cases (3 tests)\n";

?>
