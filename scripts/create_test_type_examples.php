<?php
/**
 * Create Example Tasks with all 3 Test Types
 * OUTPUT, FUNCTION, VARIABLE
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "Beispiel-Tasks mit 3 Test-Typen\n";
echo "========================================\n\n";

// Create assignment
$stmt = $conn->prepare("
    INSERT INTO assignments (title, description, difficulty, is_active, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
");

$title = 'Test-Typen: Output, Function, Variable';
$description = 'Beispiele für alle 3 Test-Typen mit klarer Struktur';
$difficulty = 'beginner';
$isActive = 1;
$createdBy = 1;

$stmt->bind_param('sssii', $title, $description, $difficulty, $isActive, $createdBy);
$stmt->execute();
$assignmentId = $conn->insert_id;

echo "✓ Assignment erstellt: $title (ID: $assignmentId)\n\n";

// ============================================================
// BEISPIEL 1: OUTPUT-Testing
// ============================================================
$task1 = [
    'title' => 'Begrüßung ausgeben (OUTPUT)',
    'description' => '**TEST-TYP: OUTPUT**

Schreiben Sie Code der eine Begrüßung ausgibt.

Das System prüft die **Ausgabe** des Programms.
Mehrere Varianten sind akzeptiert (mit/ohne Ausrufezeichen).

**Test-Typ Struktur:**
```json
{
  "type": "output",
  "input": "",
  "expected": ["Pattern1", "Pattern2"]
}
```',
    'position' => 1,
    'problem_type' => 'code_completion',
    'code_template' => 'name = "Alice"
print(f"Hallo {name}___")',
    'hint' => 'Mit oder ohne ! am Ende',
    'expected_output' => '',
    'test_cases' => json_encode([
        [
            'type' => 'output',
            'input' => '',
            'expected' => [
                'Hallo Alice!',
                'Hallo Alice'
            ]
        ]
    ]),
    'validation_mode' => 'loose',
    'max_attempts' => 10,
    'solution_code' => 'name = "Alice"
print(f"Hallo {name}!")'
];

// ============================================================
// BEISPIEL 2: FUNCTION-Testing (Single Arg)
// ============================================================
$task2 = [
    'title' => 'Quadrat-Funktion (FUNCTION)',
    'description' => '**TEST-TYP: FUNCTION**

Schreiben Sie eine Funktion `quadrat(x)` die das Quadrat berechnet.

Das System ruft die Funktion mit verschiedenen Argumenten auf
und prüft den **Return-Wert**.

**Test-Typ Struktur:**
```json
{
  "type": "function",
  "function_name": "quadrat",
  "args": [5],
  "expected": 25
}
```',
    'position' => 2,
    'problem_type' => 'code_completion',
    'code_template' => 'def quadrat(x):
    """Berechnet x²"""
    return x * ___',
    'hint' => 'x * x oder x ** 2',
    'expected_output' => '',
    'test_cases' => json_encode([
        [
            'type' => 'function',
            'function_name' => 'quadrat',
            'args' => [5],
            'expected' => 25
        ],
        [
            'type' => 'function',
            'function_name' => 'quadrat',
            'args' => [10],
            'expected' => 100
        ],
        [
            'type' => 'function',
            'function_name' => 'quadrat',
            'args' => [-3],
            'expected' => 9
        ],
        [
            'type' => 'function',
            'function_name' => 'quadrat',
            'args' => [0],
            'expected' => 0
        ]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => 'def quadrat(x):
    """Berechnet x²"""
    return x * x'
];

// ============================================================
// BEISPIEL 3: FUNCTION-Testing (Multiple Args)
// ============================================================
$task3 = [
    'title' => 'Bereichsprüfung (FUNCTION, mehrere Args)',
    'description' => '**TEST-TYP: FUNCTION (mehrere Argumente)**

Schreiben Sie eine Funktion `im_bereich(zahl, min, max)` die prüft
ob eine Zahl im Bereich liegt.

**Test-Typ Struktur:**
```json
{
  "type": "function",
  "function_name": "im_bereich",
  "args": [5, 1, 10],
  "expected": true
}
```

**Mehrere Argumente** werden als Array übergeben.',
    'position' => 3,
    'problem_type' => 'code_completion',
    'code_template' => 'def im_bereich(zahl, minimum, maximum):
    """Prüft ob zahl zwischen min und max liegt"""
    return ___ <= zahl <= ___',
    'hint' => 'minimum <= zahl <= maximum',
    'expected_output' => '',
    'test_cases' => json_encode([
        [
            'type' => 'function',
            'function_name' => 'im_bereich',
            'args' => [5, 1, 10],
            'expected' => true
        ],
        [
            'type' => 'function',
            'function_name' => 'im_bereich',
            'args' => [15, 1, 10],
            'expected' => false
        ],
        [
            'type' => 'function',
            'function_name' => 'im_bereich',
            'args' => [10, 10, 20],
            'expected' => true
        ]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => 'def im_bereich(zahl, minimum, maximum):
    """Prüft ob zahl zwischen min und max liegt"""
    return minimum <= zahl <= maximum'
];

// ============================================================
// BEISPIEL 4: VARIABLE-Testing (Single Init Var)
// ============================================================
$task4 = [
    'title' => 'Quadrat berechnen (VARIABLE)',
    'description' => '**TEST-TYP: VARIABLE**

Berechnen Sie das Quadrat der Variablen `x` und speichern Sie es in `quadrat`.

**So arbeiten Sie:**

📝 **INIT-Block:** Der Code zwischen `#INIT Start#` und `#INIT End#` wird bei CHECK ignoriert!

1. **▶ RUN (Entwickeln):**
   - Ändern Sie `x = 7` im INIT-Block auf andere Werte
   - Testen Sie Ihren Code

2. **✓ CHECK (Abgeben):**
   - Lassen Sie den INIT-Block unverändert
   - System ignoriert ihn und testet mit eigenen Werten

**Vorteil:** Sie müssen nichts löschen! Der INIT-Block hilft beim Testen und wird automatisch bei CHECK ignoriert.',
    'position' => 4,
    'problem_type' => 'code_completion',
    'code_template' => '#INIT Start#
x = 7  # Testwert für RUN - wird bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * ___',
    'hint' => 'x * x',
    'expected_output' => '',
    'test_cases' => json_encode([
        [
            'type' => 'variable',
            'init_vars' => ['x' => 5],
            'expected_vars' => ['quadrat' => 25]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['x' => 10],
            'expected_vars' => ['quadrat' => 100]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['x' => -3],
            'expected_vars' => ['quadrat' => 9]
        ]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => '# x wird vom System gesetzt
quadrat = x * x'
];

// ============================================================
// BEISPIEL 5: VARIABLE-Testing (Multiple Init Vars)
// ============================================================
$task5 = [
    'title' => 'Summe und Produkt (VARIABLE, mehrere)',
    'description' => '**TEST-TYP: VARIABLE (mehrere Variablen)**

Berechnen Sie Summe und Produkt von `a` und `b`.

**So arbeiten Sie:**

📝 **INIT-Block:** Der Code zwischen `#INIT Start#` und `#INIT End#` wird bei CHECK ignoriert!

1. **▶ RUN:** Ändern Sie die Werte im INIT-Block zum Testen
2. **✓ CHECK:** System ignoriert INIT-Block automatisch

**Vorteil:** Kein Löschen nötig!',
    'position' => 5,
    'problem_type' => 'code_completion',
    'code_template' => '#INIT Start#
a = 8   # Testwerte für RUN
b = 12
#INIT End#

# Lösung:
summe = a ___ b
produkt = a ___ b',
    'hint' => '+ und *',
    'expected_output' => '',
    'test_cases' => json_encode([
        [
            'type' => 'variable',
            'init_vars' => ['a' => 5, 'b' => 10],
            'expected_vars' => ['summe' => 15, 'produkt' => 50]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['a' => 3, 'b' => 7],
            'expected_vars' => ['summe' => 10, 'produkt' => 21]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['a' => 0, 'b' => 100],
            'expected_vars' => ['summe' => 100, 'produkt' => 0]
        ]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => '# a und b werden vom System gesetzt
summe = a + b
produkt = a * b'
];

// ============================================================
// BEISPIEL 6: VARIABLE-Testing (List Processing)
// ============================================================
$task6 = [
    'title' => 'Gerade Zahlen filtern (VARIABLE)',
    'description' => '**TEST-TYP: VARIABLE (mit Listen)**

Filtern Sie aus der Liste `zahlen` alle geraden Zahlen in `gerade`.

**So arbeiten Sie:**

📝 **INIT-Block:** Werte im INIT-Block werden bei CHECK ignoriert!

1. **▶ RUN:** Test mit eigenen Listen im INIT-Block
2. **✓ CHECK:** System verwendet eigene Test-Listen

**Vorteil:** Nichts löschen, einfach CHECK klicken!',
    'position' => 6,
    'problem_type' => 'code_completion',
    'code_template' => '#INIT Start#
zahlen = [13, 14, 15, 16]  # Testwerte für RUN
#INIT End#

# Lösung:
gerade = [x for x in zahlen if x % 2 ___ 0]',
    'hint' => '== für Gleichheit',
    'expected_output' => '',
    'test_cases' => json_encode([
        [
            'type' => 'variable',
            'init_vars' => ['zahlen' => [1, 2, 3, 4, 5]],
            'expected_vars' => ['gerade' => [2, 4]]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['zahlen' => [10, 15, 20, 25, 30]],
            'expected_vars' => ['gerade' => [10, 20, 30]]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['zahlen' => [1, 3, 5, 7]],
            'expected_vars' => ['gerade' => []]
        ]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 10,
    'solution_code' => '#INIT Start#
zahlen = [13, 14, 15, 16]
#INIT End#

gerade = [x for x in zahlen if x % 2 == 0]'
];

// Insert all tasks
$tasks = [$task1, $task2, $task3, $task4, $task5, $task6];

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
        $taskId = $taskStmt->insert_id;
        echo "✓ Task {$task['position']}: {$task['title']} (ID: $taskId)\n";
    } else {
        echo "✗ Error: " . $taskStmt->error . "\n";
    }
}

echo "\n========================================\n";
echo "✓ 6 Beispiel-Tasks erstellt!\n";
echo "========================================\n\n";

echo "TEST-TYPEN ÜBERSICHT:\n";
echo "---------------------\n\n";

echo "1. OUTPUT-Testing:\n";
echo "   - Task 1: Begrüßung ausgeben\n";
echo "   - Prüft: Print-Ausgabe\n";
echo "   - Erlaubt: Mehrere Matching-Patterns\n\n";

echo "2. FUNCTION-Testing:\n";
echo "   - Task 2: Quadrat (1 Argument)\n";
echo "   - Task 3: Bereichsprüfung (3 Argumente)\n";
echo "   - Prüft: Return-Wert der Funktion\n";
echo "   - Explizit: Funktionsname + Args\n\n";

echo "3. VARIABLE-Testing:\n";
echo "   - Task 4: Quadrat (1 Init-Var, 1 Expected-Var)\n";
echo "   - Task 5: Summe/Produkt (2 Init-Vars, 2 Expected-Vars)\n";
echo "   - Task 6: Listen filtern (Liste als Var)\n";
echo "   - Prüft: Variablenwerte nach Ausführung\n";
echo "   - System setzt Init-Vars, prüft Expected-Vars\n\n";

echo "========================================\n";
echo "Test in der IDE:\n";
echo "========================================\n\n";
echo "http://localhost/pythonIDE/public/assignments.php\n";
echo "→ Assignment: '$title'\n\n";

$conn->close();
