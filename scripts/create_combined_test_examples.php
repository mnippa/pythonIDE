<?php
/**
 * Beispiel: Test-Typen kombinieren
 * 
 * Demonstriert wie verschiedene Test-Typen in einer Task kombiniert werden können
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "═══════════════════════════════════════════════════\n";
echo "  TEST-TYPEN KOMBINIEREN - BEISPIEL\n";
echo "═══════════════════════════════════════════════════\n\n";

// Assignment erstellen
$assignmentTitle = "Kombinierte Test-Typen Demo";
$stmt = $conn->prepare("INSERT INTO assignments (title, description) VALUES (?, ?)");
$description = "Demonstriert wie OUTPUT, FUNCTION und VARIABLE Tests kombiniert werden können";
$stmt->bind_param('ss', $assignmentTitle, $description);
$stmt->execute();
$assignmentId = $stmt->insert_id;

echo "✓ Assignment erstellt (ID: $assignmentId)\n\n";

// ============================================
// TASK 1: Einfache Funktion mit gemischten Tests
// ============================================
$task1 = [
    'title' => 'Verdoppeln-Funktion (gemischte Tests)',
    'description' => '**KOMBINIERTE TEST-TYPEN**

Implementieren Sie eine Funktion `verdoppeln(x)` die eine Zahl verdoppelt.
Verwenden Sie die Funktion dann um eine Variable zu berechnen und geben Sie das Ergebnis aus.

**Diese Task testet:**
1. ✅ **FUNCTION** - Ist die Funktion korrekt?
2. ✅ **VARIABLE** - Wird die Variable richtig berechnet?
3. ✅ **OUTPUT** - Wird das Ergebnis korrekt ausgegeben?

📝 **INIT-Block:** Testwerte für RUN, werden bei CHECK ignoriert!',
    'position' => 1,
    'problem_type' => 'code_completion',
    'code_template' => '#INIT Start#
x = 5  # Testwert für RUN
#INIT End#

def verdoppeln(x):
    return x * ___

result = verdoppeln(x)
print(f"Ergebnis: {___}")',
    'hint' => 'Multiplizieren Sie mit 2',
    'expected_output' => '',
    'test_cases' => json_encode([
        // Test 1: Funktion testen
        [
            'type' => 'function',
            'function_name' => 'verdoppeln',
            'args' => [7],
            'expected' => 14
        ],
        [
            'type' => 'function',
            'function_name' => 'verdoppeln',
            'args' => [10],
            'expected' => 20
        ],
        // Test 2: Variable testen
        [
            'type' => 'variable',
            'init_vars' => ['x' => 10],
            'expected_vars' => ['result' => 20]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['x' => 15],
            'expected_vars' => ['result' => 30]
        ],
        // Test 3: Output testen
        [
            'type' => 'output',
            'input' => '',
            'expected' => ['Ergebnis: 10', 'Ergebnis: 20', 'Ergebnis: 30']
        ]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => '#INIT Start#
x = 5
#INIT End#

def verdoppeln(x):
    return x * 2

result = verdoppeln(x)
print(f"Ergebnis: {result}")'
];

// ============================================
// TASK 2: Komplexere Berechnung mit allen Test-Typen
// ============================================
$task2 = [
    'title' => 'Kreis-Berechnungen (alle Test-Typen)',
    'description' => '**VOLLSTÄNDIGE VALIDIERUNG**

Implementieren Sie zwei Funktionen:
- `flaeche(radius)` - Berechnet Kreisfläche (π * r²)
- `umfang(radius)` - Berechnet Kreisumfang (2 * π * r)

Nutzen Sie beide Funktionen um Fläche und Umfang zu berechnen und geben Sie die Ergebnisse aus.

**Getestet wird:**
1. ✅ Funktion `flaeche()`
2. ✅ Funktion `umfang()`
3. ✅ Variablen `kreis_flaeche` und `kreis_umfang`
4. ✅ Korrekter Output

**Tipp:** Verwenden Sie `math.pi` für π',
    'position' => 2,
    'problem_type' => 'code_completion',
    'code_template' => 'import math

#INIT Start#
radius = 5  # Testwert
#INIT End#

def flaeche(r):
    return ___ * r ** 2

def umfang(r):
    return ___ * math.pi * r

kreis_flaeche = flaeche(___)
kreis_umfang = umfang(___)

print(f"Fläche: {kreis_flaeche:.2f}")
print(f"Umfang: {kreis_umfang:.2f}")',
    'hint' => 'Fläche = π*r², Umfang = 2*π*r',
    'expected_output' => '',
    'test_cases' => json_encode([
        // Funktion flaeche() testen
        [
            'type' => 'function',
            'function_name' => 'flaeche',
            'args' => [5],
            'expected' => 78.54  // Ungefähr, wird mit Toleranz verglichen
        ],
        [
            'type' => 'function',
            'function_name' => 'flaeche',
            'args' => [10],
            'expected' => 314.16
        ],
        // Funktion umfang() testen
        [
            'type' => 'function',
            'function_name' => 'umfang',
            'args' => [5],
            'expected' => 31.42
        ],
        [
            'type' => 'function',
            'function_name' => 'umfang',
            'args' => [10],
            'expected' => 62.83
        ],
        // Variablen testen
        [
            'type' => 'variable',
            'init_vars' => ['radius' => 5],
            'expected_vars' => [
                'kreis_flaeche' => 78.54,
                'kreis_umfang' => 31.42
            ]
        ],
        // Output testen
        [
            'type' => 'output',
            'input' => '',
            'expected' => ['Fläche: 78.54', 'Umfang: 31.42']
        ]
    ]),
    'validation_mode' => 'loose', // Wegen Float-Vergleichen
    'max_attempts' => 10,
    'solution_code' => 'import math

#INIT Start#
radius = 5
#INIT End#

def flaeche(r):
    return math.pi * r ** 2

def umfang(r):
    return 2 * math.pi * r

kreis_flaeche = flaeche(radius)
kreis_umfang = umfang(radius)

print(f"Fläche: {kreis_flaeche:.2f}")
print(f"Umfang: {kreis_umfang:.2f}")'
];

// Tasks einfügen
$tasks = [$task1, $task2];

$taskStmt = $conn->prepare('
    INSERT INTO tasks (
        assignment_id, title, description, position, problem_type,
        code_template, hint, expected_output, test_cases, 
        validation_mode, max_attempts, solution_code
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

foreach ($tasks as $i => $task) {
    $taskStmt->bind_param(
        'issississsis',
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
        $taskId = $taskStmt->insert_id;
        echo "✓ Task " . ($i + 1) . ": {$task['title']} (ID: $taskId)\n";
        
        // Zeige Test-Count
        $tests = json_decode($task['test_cases'], true);
        $functionTests = count(array_filter($tests, fn($t) => $t['type'] === 'function'));
        $variableTests = count(array_filter($tests, fn($t) => $t['type'] === 'variable'));
        $outputTests = count(array_filter($tests, fn($t) => $t['type'] === 'output'));
        
        echo "  → " . count($tests) . " Tests: ";
        echo "FUNCTION=$functionTests, VARIABLE=$variableTests, OUTPUT=$outputTests\n\n";
    }
}

echo "═══════════════════════════════════════════════════\n";
echo "✓ Assignment mit kombinierten Test-Typen erstellt!\n";
echo "═══════════════════════════════════════════════════\n\n";

echo "VORTEILE DER KOMBINATION:\n";
echo "─────────────────────────\n\n";

echo "1. UMFASSENDE VALIDIERUNG:\n";
echo "   → Funktions-Logik korrekt? (FUNCTION)\n";
echo "   → Wird Funktion richtig genutzt? (VARIABLE)\n";
echo "   → Stimmt der Output? (OUTPUT)\n\n";

echo "2. PRÄZISES FEEDBACK:\n";
echo "   → Student sieht genau welcher Aspekt falsch ist\n";
echo "   → \"Funktion korrekt, aber Variable falsch berechnet\"\n";
echo "   → \"Variable korrekt, aber falscher Output\"\n\n";

echo "3. REALISTISCHE SZENARIEN:\n";
echo "   → Nicht nur isolierte Tests\n";
echo "   → Gesamter Workflow wird geprüft\n";
echo "   → Wie in echter Programmierung\n\n";

echo "BEISPIEL TASK 1:\n";
echo "────────────────\n";
echo "def verdoppeln(x):     ← FUNCTION-Test\n";
echo "    return x * 2\n\n";
echo "result = verdoppeln(x) ← VARIABLE-Test\n";
echo "print(f\"...\")          ← OUTPUT-Test\n\n";

echo "BEISPIEL TASK 2:\n";
echo "────────────────\n";
echo "def flaeche(r): ...    ← FUNCTION-Tests (2x)\n";
echo "def umfang(r): ...\n\n";
echo "kreis_flaeche = ...    ← VARIABLE-Test\n";
echo "kreis_umfang = ...\n\n";
echo "print(...)             ← OUTPUT-Test\n\n";

echo "EMPFEHLUNG:\n";
echo "───────────\n";
echo "✓ Einfache Tasks: 1 Test-Typ\n";
echo "✓ Mittlere Tasks: 2 Test-Typen (z.B. FUNCTION + VARIABLE)\n";
echo "✓ Komplexe Tasks: Alle 3 Typen (vollständige Validierung)\n\n";

$conn->close();
