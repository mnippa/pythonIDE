<?php
/**
 * Migration 032: Add list-organization tasks with nested lists.
 *
 * Goal:
 * - strengthen list organization skills
 * - introduce two-level lists
 * - combine exact automatic validation with open keyword-based validation
 */

require_once __DIR__ . '/../../config/database.php';

function taskExists(mysqli $conn, int $assignmentId, string $title): bool {
    $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (taskExists): ' . $conn->error);
    }

    $stmt->bind_param('is', $assignmentId, $title);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = (bool)$result->fetch_assoc();
    $stmt->close();

    return $exists;
}

function insertTask(mysqli $conn, array $task): int {
    $sql = 'INSERT INTO tasks (
        assignment_id,
        title,
        task_text,
        description,
        stoff,
        position,
        task_type,
        problem_type,
        code_template,
        solution_code,
        test_cases,
        hint1,
        hint2,
        hint3,
        max_attempts,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed (insertTask): ' . $conn->error);
    }

    $assignmentId = $task['assignment_id'];
    $title = $task['title'];
    $taskText = $task['task_text'];
    $description = $task['description'];
    $stoff = $task['stoff'];
    $position = $task['position'];
    $taskType = $task['task_type'];
    $problemType = $task['problem_type'];
    $codeTemplate = $task['code_template'];
    $solutionCode = $task['solution_code'];
    $testCases = $task['test_cases'];
    $hint1 = $task['hint1'];
    $hint2 = $task['hint2'];
    $hint3 = $task['hint3'];
    $maxAttempts = $task['max_attempts'];

    $stmt->bind_param(
        'issssissssssssi',
        $assignmentId,
        $title,
        $taskText,
        $description,
        $stoff,
        $position,
        $taskType,
        $problemType,
        $codeTemplate,
        $solutionCode,
        $testCases,
        $hint1,
        $hint2,
        $hint3,
        $maxAttempts
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed (insertTask): ' . $stmt->error);
    }

    $taskId = (int)$conn->insert_id;
    $stmt->close();

    return $taskId;
}

try {
    $conn = getDbConnection();
    echo "Running Migration 032: add nested-list organization tasks...\n";

    $assignmentId = 23;
    $posResult = $conn->query("SELECT MAX(position) AS max_pos FROM tasks WHERE assignment_id = {$assignmentId}");
    if (!$posResult) {
        throw new Exception('Failed to determine next task position: ' . $conn->error);
    }

    $posRow = $posResult->fetch_assoc();
    $nextPos = ((int)($posRow['max_pos'] ?? 0)) + 1;

    $tasks = [
        [
            'assignment_id' => $assignmentId,
            'title' => 'Stundenplan: zwei Ebenen',
            'task_text' => 'Lege eine Variable stundenplan an. Darin soll es zwei innere Listen geben. In der ersten Liste stehen Mathe, Deutsch, Englisch. In der zweiten Liste stehen Informatik, Biologie, Kunst. Speichere Biologie in der Variable fach und gib fach aus.',
            'description' => 'Die Aufgabe soll als verschachtelte Liste geloest werden: eine aeussere Liste enthaelt zwei innere Listen. Anschliessend greifst du ueber zwei Indizes auf ein Fach zu.',
            'stoff' => 'Verschachtelte Listen, Indexzugriff, Listen strukturieren',
            'position' => $nextPos++,
            'task_type' => 'code',
            'problem_type' => 'code_completion',
            'code_template' => <<<'PY'
# Erstelle hier eine aeussere Liste mit zwei inneren Listen
stundenplan = []

# Speichere das Fach "Biologie" aus der Struktur in fach
fach = ""

print(fach)
PY,
            'solution_code' => <<<'PY'
stundenplan = [
    ["Mathe", "Deutsch", "Englisch"],
    ["Informatik", "Biologie", "Kunst"]
]

fach = stundenplan[1][1]

print(fach)
PY,
            'test_cases' => json_encode([
                [
                    'type' => 'variable',
                    'init_vars' => new stdClass(),
                    'expected_vars' => [
                        'stundenplan' => [
                            ['Mathe', 'Deutsch', 'Englisch'],
                            ['Informatik', 'Biologie', 'Kunst']
                        ],
                        'fach' => 'Biologie'
                    ]
                ],
                [
                    'type' => 'output',
                    'input' => '',
                    'expected' => 'Biologie',
                    'validation_mode' => 'strict'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'hint1' => 'Eine Liste kann selbst wieder Listen enthalten.',
            'hint2' => 'Biologie liegt in der zweiten inneren Liste.',
            'hint3' => 'Bei zwei Ebenen brauchst du zwei Indizes, zum Beispiel liste[1][1].',
            'max_attempts' => 10
        ],
        [
            'assignment_id' => $assignmentId,
            'title' => 'Regal: eigene Struktur finden',
            'task_text' => 'Organisiere mindestens zwei Kategorien von Gegenstaenden in einer gemeinsamen Liste regal. Jede Kategorie soll selbst wieder eine Liste sein. Speichere einen Gegenstand aus der zweiten Kategorie in fundstueck und gib fundstueck aus.',
            'description' => 'Die Inhalte darfst du frei waehlen. Wichtig ist die Organisation: eine aeussere Liste, darin mehrere innere Listen. Die Pruefung achtet vor allem auf diese Struktur.',
            'stoff' => 'Listen organisieren, verschachtelte Listen, Zugriff auf innere Listen',
            'position' => $nextPos++,
            'task_type' => 'code',
            'problem_type' => 'code_completion',
            'code_template' => <<<'PY'
# Lege hier deine gemeinsame Liste an
regal = []

# Speichere einen Gegenstand aus der zweiten Kategorie in fundstueck
fundstueck = ""

print(fundstueck)
PY,
            'solution_code' => <<<'PY'
regal = [
    ["Heft", "Ordner"],
    ["Schere", "Kleber"]
]

fundstueck = regal[1][0]

print(fundstueck)
PY,
            'test_cases' => json_encode([
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'regal\\s*=\\s*\\[',
                        '\\[\\s*\\[',
                        '\\]\\s*,\\s*\\['
                    ],
                    'operator' => 'AND',
                    'feedback' => 'regal soll als aeussere Liste mit mindestens zwei inneren Listen angelegt werden.'
                ],
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'fundstueck\\s*=',
                        'regal\\s*\\[\\s*1\\s*\\]\\s*\\[\\s*\\d+\\s*\\]'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'fundstueck soll ueber die zweite innere Liste aus regal gelesen werden.'
                ],
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'print\\s*\\(\\s*fundstueck\\s*\\)'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Gib fundstueck am Ende aus.'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'hint1' => 'Die aeussere Liste enthaelt Kategorien, nicht direkt alle einzelnen Gegenstaende.',
            'hint2' => 'Die zweite Kategorie erreichst du ueber Index 1.',
            'hint3' => 'Wenn du einen Gegenstand aus einer inneren Liste lesen willst, brauchst du zwei eckige Klammerpaare.',
            'max_attempts' => 10
        ]
    ];

    foreach ($tasks as $task) {
        if (taskExists($conn, $task['assignment_id'], $task['title'])) {
            echo "⚠ Task '{$task['title']}' exists already, skipping.\n";
            continue;
        }

        $taskId = insertTask($conn, $task);
        echo "✓ Created task #{$taskId}: {$task['title']}\n";
    }

    echo "\n✅ Migration 032: Success!\n";
} catch (Exception $e) {
    echo '❌ Migration 032 failed: ' . $e->getMessage() . "\n";
    exit(1);
}