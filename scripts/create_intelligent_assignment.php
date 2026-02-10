<?php
/**
 * Script: Create Intelligent Assignment with 6 Tasks
 * Purpose: Creates a new assignment with intelligent test cases and assigns to all users
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Step 1: Create Assignment
echo "=== Creating Assignment ===\n";

$assignmentData = [
    'title' => 'Python Grundlagen - Intelligente Tests',
    'description' => 'Eine Sammlung von 6 Aufgaben mit intelligenten Tests, die deine Lösung gegen eine Musterlösung prüfen.',
    'code_template' => '# Deine Lösung hier...\n',
    'created_by' => 1, // Admin user
    'is_active' => 1,
    'difficulty' => 'intermediate',
    'time_limit_minutes' => 60
];

$stmt = $conn->prepare(
    'INSERT INTO assignments (title, description, code_template, created_by, is_active, difficulty, time_limit_minutes)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param(
    'sssiisi',
    $assignmentData['title'],
    $assignmentData['description'],
    $assignmentData['code_template'],
    $assignmentData['created_by'],
    $assignmentData['is_active'],
    $assignmentData['difficulty'],
    $assignmentData['time_limit_minutes']
);

if (!$stmt->execute()) {
    die("Failed to create assignment: " . $stmt->error . "\n");
}

$assignmentId = $conn->insert_id;
echo "✓ Assignment created with ID: $assignmentId\n\n";

// Step 2: Create 6 Intelligent Tasks
echo "=== Creating 6 Intelligent Tasks ===\n";

$tasks = [
    // Task 1: Simple addition function
    [
        'title' => 'Addiere zwei Zahlen',
        'description' => 'Schreibe eine Funktion `addiere(a, b)`, die zwei Zahlen addiert und das Ergebnis zurückgibt.',
        'code_template' => "def addiere(a, b):\n    # Dein Code hier\n    pass\n",
        'hint1' => 'Verwende den + Operator',
        'hint2' => 'Die Funktion sollte a + b zurückgeben',
        'hint3' => 'return a + b',
        'stoff' => 'Funktionen, Arithmetische Operationen',
        'validation_mode' => 'intelligent',
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => 5,
            'seed' => 12345,
            'tolerance' => 0.001,
            'function' => [
                'name' => 'addiere',
                'inputs' => [
                    ['name' => 'a', 'type' => 'int', 'min' => -100, 'max' => 100],
                    ['name' => 'b', 'type' => 'int', 'min' => -100, 'max' => 100]
                ],
                'output' => ['type' => 'int']
            ]
        ]),
        'solution_code' => "def addiere(a, b):\n    return a + b\n"
    ],
    
    // Task 2: List sum
    [
        'title' => 'Summiere eine Liste',
        'description' => 'Schreibe eine Funktion `liste_summieren(zahlen)`, die eine Liste von Zahlen summiert.',
        'code_template' => "def liste_summieren(zahlen):\n    # Dein Code hier\n    pass\n",
        'hint1' => 'Verwende eine Schleife oder die sum() Funktion',
        'hint2' => 'sum(zahlen) gibt die Summe zurück',
        'hint3' => 'return sum(zahlen)',
        'stoff' => 'Listen, Schleifen, Built-in Funktionen',
        'validation_mode' => 'intelligent',
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => 5,
            'seed' => 54321,
            'tolerance' => 0.001,
            'function' => [
                'name' => 'liste_summieren',
                'inputs' => [
                    [
                        'name' => 'zahlen',
                        'type' => 'list',
                        'length' => 5,
                        'element' => ['type' => 'int', 'min' => 1, 'max' => 50]
                    ]
                ],
                'output' => ['type' => 'int']
            ]
        ]),
        'solution_code' => "def liste_summieren(zahlen):\n    return sum(zahlen)\n"
    ],
    
    // Task 3: String reversal
    [
        'title' => 'Drehe einen String um',
        'description' => 'Schreibe eine Funktion `umdrehen(text)`, die einen String umkehrt.',
        'code_template' => "def umdrehen(text):\n    # Dein Code hier\n    pass\n",
        'hint1' => 'Verwende Slicing mit [::-1]',
        'hint2' => 'text[::-1] kehrt den String um',
        'hint3' => 'return text[::-1]',
        'stoff' => 'Strings, Slicing',
        'validation_mode' => 'intelligent',
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => 5,
            'seed' => 99999,
            'tolerance' => 0.001,
            'function' => [
                'name' => 'umdrehen',
                'inputs' => [
                    ['name' => 'text', 'type' => 'string', 'min' => 5, 'max' => 15]
                ],
                'output' => ['type' => 'string']
            ]
        ]),
        'solution_code' => "def umdrehen(text):\n    return text[::-1]\n"
    ],
    
    // Task 4: Even numbers filter
    [
        'title' => 'Filtere gerade Zahlen',
        'description' => 'Schreibe eine Funktion `gerade_zahlen(zahlen)`, die nur die geraden Zahlen aus einer Liste zurückgibt.',
        'code_template' => "def gerade_zahlen(zahlen):\n    # Dein Code hier\n    pass\n",
        'hint1' => 'Verwende den Modulo-Operator % um zu prüfen, ob eine Zahl gerade ist',
        'hint2' => 'Eine Zahl ist gerade, wenn zahl % 2 == 0',
        'hint3' => 'return [z for z in zahlen if z % 2 == 0]',
        'stoff' => 'Listen, List Comprehensions, Modulo-Operator',
        'validation_mode' => 'intelligent',
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => 5,
            'seed' => 11111,
            'tolerance' => 0.001,
            'function' => [
                'name' => 'gerade_zahlen',
                'inputs' => [
                    [
                        'name' => 'zahlen',
                        'type' => 'list',
                        'length' => 8,
                        'element' => ['type' => 'int', 'min' => 1, 'max' => 100]
                    ]
                ],
                'output' => ['type' => 'list']
            ]
        ]),
        'solution_code' => "def gerade_zahlen(zahlen):\n    return [z for z in zahlen if z % 2 == 0]\n"
    ],
    
    // Task 5: Calculate average
    [
        'title' => 'Berechne den Durchschnitt',
        'description' => 'Schreibe eine Funktion `durchschnitt(zahlen)`, die den Durchschnitt einer Liste berechnet.',
        'code_template' => "def durchschnitt(zahlen):\n    # Dein Code hier\n    pass\n",
        'hint1' => 'Durchschnitt = Summe / Anzahl',
        'hint2' => 'Verwende sum(zahlen) und len(zahlen)',
        'hint3' => 'return sum(zahlen) / len(zahlen)',
        'stoff' => 'Listen, Arithmetik, Built-in Funktionen',
        'validation_mode' => 'intelligent',
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => 5,
            'seed' => 77777,
            'tolerance' => 0.01, // Higher tolerance for float division
            'function' => [
                'name' => 'durchschnitt',
                'inputs' => [
                    [
                        'name' => 'zahlen',
                        'type' => 'list',
                        'length' => 6,
                        'element' => ['type' => 'float', 'min' => 1.0, 'max' => 100.0]
                    ]
                ],
                'output' => ['type' => 'float']
            ]
        ]),
        'solution_code' => "def durchschnitt(zahlen):\n    return sum(zahlen) / len(zahlen)\n"
    ],
    
    // Task 6: Object manipulation
    [
        'title' => 'Verarbeite Schüler-Daten',
        'description' => 'Schreibe eine Funktion `berechne_notendurchschnitt(schueler)`, die den Notendurchschnitt eines Schülers berechnet. Der Schüler ist ein Dictionary mit dem Schlüssel "noten" (Liste von Zahlen).',
        'code_template' => "def berechne_notendurchschnitt(schueler):\n    # Dein Code hier\n    pass\n",
        'hint1' => 'Greife mit schueler["noten"] auf die Liste zu',
        'hint2' => 'Berechne den Durchschnitt der Noten',
        'hint3' => 'return sum(schueler["noten"]) / len(schueler["noten"])',
        'stoff' => 'Dictionaries, Listen, Arithmetik',
        'validation_mode' => 'intelligent',
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => 5,
            'seed' => 33333,
            'tolerance' => 0.01,
            'function' => [
                'name' => 'berechne_notendurchschnitt',
                'inputs' => [
                    [
                        'name' => 'schueler',
                        'type' => 'object',
                        'fields' => [
                            ['name' => 'name', 'type' => 'string', 'min' => 3, 'max' => 10],
                            [
                                'name' => 'noten',
                                'type' => 'list',
                                'length' => 5,
                                'element' => ['type' => 'float', 'min' => 1.0, 'max' => 6.0]
                            ]
                        ]
                    ]
                ],
                'output' => ['type' => 'float']
            ]
        ]),
        'solution_code' => "def berechne_notendurchschnitt(schueler):\n    return sum(schueler[\"noten\"]) / len(schueler[\"noten\"])\n"
    ]
];

foreach ($tasks as $index => $task) {
    $position = $index + 1;
    
    $stmt = $conn->prepare(
        'INSERT INTO tasks (assignment_id, title, description, position, problem_type, code_template, hint1, hint2, hint3, stoff, validation_mode, test_cases, solution_code)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    $problemType = 'code_completion';
    
    $stmt->bind_param(
        'isissssssssss',
        $assignmentId,
        $task['title'],
        $task['description'],
        $position,
        $problemType,
        $task['code_template'],
        $task['hint1'],
        $task['hint2'],
        $task['hint3'],
        $task['stoff'],
        $task['validation_mode'],
        $task['test_cases'],
        $task['solution_code']
    );
    
    if (!$stmt->execute()) {
        die("Failed to create task $position: " . $stmt->error . "\n");
    }
    
    $taskId = $conn->insert_id;
    echo "✓ Task $position created: {$task['title']} (ID: $taskId)\n";
}

echo "\n=== Assigning to All Users ===\n";

// Step 3: Assign to all users (except admins)
$stmt = $conn->prepare(
    "INSERT INTO user_assignments (user_id, assignment_id, status, assigned_at)
     SELECT u.id, ?, 'assigned', NOW()
     FROM users u
     WHERE u.role = 'user'"
);
$stmt->bind_param('i', $assignmentId);

if (!$stmt->execute()) {
    die("Failed to assign to users: " . $stmt->error . "\n");
}

$affectedRows = $stmt->affected_rows;
echo "✓ Assigned to $affectedRows users\n\n";

// Summary
$stmt = $conn->prepare('SELECT COUNT(*) as task_count FROM tasks WHERE assignment_id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$taskCount = $stmt->get_result()->fetch_assoc()['task_count'];

$stmt = $conn->prepare('SELECT COUNT(*) as user_count FROM user_assignments WHERE assignment_id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$userCount = $stmt->get_result()->fetch_assoc()['user_count'];

echo "=== SUMMARY ===\n";
echo "Assignment ID: $assignmentId\n";
echo "Title: {$assignmentData['title']}\n";
echo "Tasks created: $taskCount\n";
echo "Users assigned: $userCount\n";
echo "\n✅ Assignment with intelligent tests successfully created and assigned!\n";

$conn->close();
