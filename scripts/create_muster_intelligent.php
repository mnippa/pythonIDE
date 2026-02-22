<?php
/**
 * Script to create "Musteraufgaben 2" Assignment with 2 Intelligent Test Tasks
 * Run: php scripts/create_muster_intelligent.php
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

try {
    // 1. Create Assignment "Musteraufgaben 2"
    echo "Creating Assignment...\n";
    
    $stmt = $conn->prepare(
        'INSERT INTO assignments (title, description, created_by, is_active, difficulty, time_limit_minutes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    
    $assignmentTitle = 'Musteraufgaben 2 - Intelligent Tests';
    $assignmentDesc = 'Beispielaufgaben für Intelligent Function und Vars Mode Tests';
    $createdBy = 1; // Admin user
    $isActive = 1;
    $difficulty = 'intermediate';
    $timeLimit = 30;
    
    $stmt->bind_param('ssiisi', $assignmentTitle, $assignmentDesc, $createdBy, $isActive, $difficulty, $timeLimit);
    $stmt->execute();
    $assignmentId = $conn->insert_id;
    
    echo "✓ Assignment created with ID: $assignmentId\n\n";

    // 2. Create Task 1: Intelligent Function Mode
    echo "Creating Task 1: Intelligent Function Mode...\n";
    
    $task1 = [
        'assignment_id' => $assignmentId,
        'title' => 'Zahlen verdoppeln (Intelligent Function)',
        'description' => "Implementiere die Funktion `verdoppeln(x)`, die eine Zahl verdoppelt.\n\n**Beispiel:**\n- verdoppeln(5) → 10\n- verdoppeln(42) → 84\n\n**Hinweis:** Die Funktion wird 4x mit verschiedenen Zufallswerten getestet.",
        'task_type' => 'code',
        'position' => 1,
        'max_attempts' => 3,
        'show_solution' => 1,
        'code_template' => "def verdoppeln(x):\n    # Dein Code hier\n    pass",
        'solution_code' => "def verdoppeln(x):\n    return x * 2",
        'randomizer_code' => "import random\n\nvalues = {\n    \"x\": random.randint(1, 100)\n}",
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'function',
                'tests' => 4,
                'function' => [
                    'name' => 'verdoppeln',
                    'params' => ['x']
                ]
            ]
        ]),
        'hint1' => 'Die Funktion soll einfach den Parameter mit 2 multiplizieren.',
        'hint2' => 'Verwende den * Operator für Multiplikation.',
        'hint3' => 'return x * 2'
    ];
    
    $stmt = $conn->prepare(
        'INSERT INTO tasks (
            assignment_id, title, description, task_type, position, max_attempts, 
            show_solution, code_template, solution_code, randomizer_code, 
            test_cases, hint1, hint2, hint3
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    $stmt->bind_param(
        'isssiissssssss',
        $task1['assignment_id'],
        $task1['title'],
        $task1['description'],
        $task1['task_type'],
        $task1['position'],
        $task1['max_attempts'],
        $task1['show_solution'],
        $task1['code_template'],
        $task1['solution_code'],
        $task1['randomizer_code'],
        $task1['test_cases'],
        $task1['hint1'],
        $task1['hint2'],
        $task1['hint3']
    );
    
    $stmt->execute();
    $task1Id = $conn->insert_id;
    
    echo "✓ Task 1 created with ID: $task1Id\n";
    echo "  Function: verdoppeln(x)\n";
    echo "  Tests: 4 random values\n\n";

    // 3. Create Task 2: Intelligent Vars Mode
    echo "Creating Task 2: Intelligent Vars Mode...\n";
    
    $task2 = [
        'assignment_id' => $assignmentId,
        'title' => 'Rechnen mit Variablen (Intelligent Vars)',
        'description' => "Berechne aus den gegebenen Zahlen `a` und `b`:\n- **summe**: Die Summe von a und b\n- **produkt**: Das Produkt von a und b\n- **durchschnitt**: Der Durchschnitt von a und b\n\n**Beispiel:**\n- Wenn a=10, b=20 → summe=30, produkt=200, durchschnitt=15.0\n\n**Hinweis:** Die Variablen a und b werden automatisch mit Zufallswerten gefüllt (4 verschiedene Tests).",
        'task_type' => 'code',
        'position' => 2,
        'max_attempts' => 3,
        'show_solution' => 1,
        'code_template' => "#INIT START\na = 0\nb = 0\n#INIT END\n\n# Berechne die geforderten Werte\nsumme = 0\nprodukt = 0\ndurchschnitt = 0.0",
        'solution_code' => "#INIT START\na = 0\nb = 0\n#INIT END\n\nsumme = a + b\nprodukt = a * b\ndurchschnitt = (a + b) / 2",
        'randomizer_code' => "import random\n\nvalues = {\n    \"a\": random.randint(1, 50),\n    \"b\": random.randint(1, 50)\n}",
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'vars',
                'tests' => 4,
                'inputs' => ['a', 'b'],
                'outputs' => ['summe', 'produkt', 'durchschnitt']
            ]
        ]),
        'hint1' => 'Die Init-Block Variablen a und b werden automatisch mit Zufallswerten gefüllt.',
        'hint2' => 'Summe: verwende +, Produkt: verwende *, Durchschnitt: verwende / und Klammern',
        'hint3' => 'durchschnitt = (a + b) / 2'
    ];
    
    $stmt = $conn->prepare(
        'INSERT INTO tasks (
            assignment_id, title, description, task_type, position, max_attempts, 
            show_solution, code_template, solution_code, randomizer_code, 
            test_cases, hint1, hint2, hint3
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    $stmt->bind_param(
        'isssiissssssss',
        $task2['assignment_id'],
        $task2['title'],
        $task2['description'],
        $task2['task_type'],
        $task2['position'],
        $task2['max_attempts'],
        $task2['show_solution'],
        $task2['code_template'],
        $task2['solution_code'],
        $task2['randomizer_code'],
        $task2['test_cases'],
        $task2['hint1'],
        $task2['hint2'],
        $task2['hint3']
    );
    
    $stmt->execute();
    $task2Id = $conn->insert_id;
    
    echo "✓ Task 2 created with ID: $task2Id\n";
    echo "  Inputs: a, b\n";
    echo "  Outputs: summe, produkt, durchschnitt\n";
    echo "  Tests: 4 random value combinations\n\n";

    echo "========================================\n";
    echo "✓ SUCCESS!\n";
    echo "========================================\n";
    echo "Assignment: $assignmentTitle\n";
    echo "Assignment ID: $assignmentId\n";
    echo "Task 1 (Function): $task1Id\n";
    echo "Task 2 (Vars): $task2Id\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
