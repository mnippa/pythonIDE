<?php
/**
 * Migration 036: Add dictionary intro + list-of-dictionaries tasks.
 */

require_once __DIR__ . '/../../config/database.php';

function taskExistsByTitle(mysqli $conn, int $assignmentId, string $title): bool {
    $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (taskExistsByTitle): ' . $conn->error);
    }

    $stmt->bind_param('is', $assignmentId, $title);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = (bool)$result->fetch_assoc();
    $stmt->close();

    return $exists;
}

function insertTaskRow(mysqli $conn, array $task): int {
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
        throw new Exception('Prepare failed (insertTaskRow): ' . $conn->error);
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
        throw new Exception('Execute failed (insertTaskRow): ' . $stmt->error);
    }

    $taskId = (int)$conn->insert_id;
    $stmt->close();

    return $taskId;
}

try {
    $conn = getDbConnection();
    echo "Running Migration 036: add dictionary tasks...\n";

    $assignmentId = 23;
    $posResult = $conn->query("SELECT MAX(position) AS max_pos FROM tasks WHERE assignment_id = {$assignmentId}");
    if (!$posResult) {
        throw new Exception('Failed to determine next position: ' . $conn->error);
    }

    $posRow = $posResult->fetch_assoc();
    $nextPos = ((int)($posRow['max_pos'] ?? 0)) + 1;

    $tasks = [
        [
            'assignment_id' => $assignmentId,
            'title' => 'Dictionary Einstieg: Key-Value',
            'task_text' => 'Lege ein Dictionary student mit den Schluesseln name, matrikelnummer und note an. Gib danach die note aus.',
            'description' => 'Das ist die Einfuehrungsaufgabe zu Dictionaries. Nutze ein Dictionary als strukturierte Alternative zu einer flachen Liste und greife ueber den Schluessel note auf den Wert zu.',
            'stoff' => 'Dictionaries, Key-Value Prinzip, Zugriff per Schluessel',
            'position' => $nextPos++,
            'task_type' => 'code',
            'problem_type' => 'code_completion',
            'code_template' => <<<'PY'
# Erstelle hier ein Dictionary
student = {}

# Lies die Note ueber den Schluessel aus
note = 0

print(note)
PY,
            'solution_code' => <<<'PY'
student = {
    "name": "Anna Meyer",
    "matrikelnummer": 12345,
    "note": 2.1
}

note = student["note"]

print(note)
PY,
            'test_cases' => json_encode([
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'student\\s*=\\s*\\{',
                        '[\"\']name[\"\']\\s*:',
                        '[\"\']matrikelnummer[\"\']\\s*:',
                        '[\"\']note[\"\']\\s*:'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Erstelle ein Dictionary student mit den Schluesseln name, matrikelnummer und note.'
                ],
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'note\\s*=\\s*student\\s*\\[\\s*[\"\']note[\"\']\\s*\\]'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Lies den Wert ueber den Schluessel note aus.'
                ],
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'print\\s*\\(\\s*note\\s*\\)'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Gib note aus.'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'hint1' => 'Ein Dictionary nutzt geschweifte Klammern und Schluessel:Wert-Paare.',
            'hint2' => 'Auf einen Wert greifst du ueber den Schluessel zu, z.B. dict["note"].',
            'hint3' => 'Die Schluessel muessen name, matrikelnummer und note heissen.',
            'max_attempts' => 10
        ],
        [
            'assignment_id' => $assignmentId,
            'title' => 'Studierendenliste: Dictionaries',
            'task_text' => 'Speichere drei Studierende als Dictionaries in der Liste studenten. Speichere den zweiten Eintrag in zweiter_student und gib ihn aus.',
            'description' => 'Jeder Listeneintrag ist ein Dictionary mit den Schluesseln name, matrikelnummer, durchschnittsnote und bestandene_pruefungen. Die Werte koennen frei gewaehlt werden. Ziel ist die saubere Kombination aus Liste und strukturierten Dict-Eintraegen.',
            'stoff' => 'Liste von Dictionaries, Datenmodellierung, Zugriff auf Listeneintraege',
            'position' => $nextPos++,
            'task_type' => 'code',
            'problem_type' => 'code_completion',
            'code_template' => <<<'PY'
# Lege hier eine Liste mit drei Dictionary-Eintraegen an
studenten = []

# Speichere den zweiten Dictionary-Eintrag
zweiter_student = {}

print(zweiter_student)
PY,
            'solution_code' => <<<'PY'
studenten = [
    {
        "name": "Anna Meyer",
        "matrikelnummer": 12345,
        "durchschnittsnote": 2.1,
        "bestandene_pruefungen": 4
    },
    {
        "name": "Ben Schulz",
        "matrikelnummer": 23456,
        "durchschnittsnote": 1.9,
        "bestandene_pruefungen": 5
    },
    {
        "name": "Cem Yildiz",
        "matrikelnummer": 34567,
        "durchschnittsnote": 2.7,
        "bestandene_pruefungen": 3
    }
]

zweiter_student = studenten[1]

print(zweiter_student)
PY,
            'test_cases' => json_encode([
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'studenten\\s*=\\s*\\[',
                        '\\{[\\s\\S]*?\\}\\s*,\\s*\\{[\\s\\S]*?\\}\\s*,\\s*\\{[\\s\\S]*?\\}'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Lege studenten als Liste mit genau drei Dictionary-Eintraegen an.'
                ],
                [
                    'type' => 'code_check',
                    'keywords' => [
                        '[\"\']name[\"\']\\s*:',
                        '[\"\']matrikelnummer[\"\']\\s*:',
                        '[\"\']durchschnittsnote[\"\']\\s*:',
                        '[\"\']bestandene_pruefungen[\"\']\\s*:'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Jeder Eintrag soll mit den Schluesseln name, matrikelnummer, durchschnittsnote, bestandene_pruefungen strukturiert sein.'
                ],
                [
                    'type' => 'code_check',
                    'keywords' => [
                        'zweiter_student\\s*=\\s*studenten\\s*\\[\\s*1\\s*\\]',
                        'print\\s*\\(\\s*zweiter_student\\s*\\)'
                    ],
                    'operator' => 'AND',
                    'feedback' => 'Speichere den zweiten Listeneintrag in zweiter_student und gib ihn aus.'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'hint1' => 'Kombiniere zwei Ebenen: aeussere Liste und innere Dictionary-Eintraege.',
            'hint2' => 'Der zweite Eintrag hat den Index 1.',
            'hint3' => 'Nutze in jedem Dictionary dieselben Schluessel, damit die Struktur einheitlich bleibt.',
            'max_attempts' => 10
        ]
    ];

    foreach ($tasks as $task) {
        if (taskExistsByTitle($conn, $task['assignment_id'], $task['title'])) {
            echo "⚠ Task '{$task['title']}' exists already, skipping.\n";
            continue;
        }

        $newTaskId = insertTaskRow($conn, $task);
        echo "✓ Created task #{$newTaskId}: {$task['title']}\n";
    }

    echo "\n✅ Migration 036: Success!\n";
} catch (Exception $e) {
    echo '❌ Migration 036 failed: ' . $e->getMessage() . "\n";
    exit(1);
}