<?php
/**
 * Migration 034: Strengthen regex validation for task 195.
 *
 * Goal:
 * - validate zweiter_student via regex on printed output
 * - require exactly 4 elements
 * - required order: string, integer, decimal with dot, integer
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 034: strengthen task 195 regex validation...\n";

    $taskId = 195;
    $title = 'Studierendendaten: zweiter Student';
    $taskText = 'Lege eine Liste studenten an. Jeder Student soll durch eine innere Liste beschrieben werden: Name, Matrikelnummer, Durchschnittsnote und bestandene Pruefungen. Speichere Daten zu genau drei Studierenden. Speichere danach alle Werte des zweiten Studenten in zweiter_student und gib zweiter_student aus.';
    $description = 'Die Werte darfst du selbst waehlen. Wichtig ist die hierarchische Struktur: eine aeussere Liste mit drei inneren Listen. Jede innere Liste soll genau vier Werte enthalten, und zwar in dieser Reihenfolge: String, Zahl, Kommazahl mit Punkt, Zahl. Spaeter laesst sich dieselbe Idee noch besser mit Dictionaries ausdruecken.';

    $solutionCode = <<<'PY'
studenten = [
    ["Anna Meyer", 12345, 2.1, 4],
    ["Ben Schulz", 23456, 1.9, 5],
    ["Cem Yildiz", 34567, 2.7, 3]
]

zweiter_student = studenten[1]

print(zweiter_student)
PY;

    $testCases = json_encode([
        [
            'type' => 'code_check',
            'keywords' => [
                'studenten\\s*=\\s*\\[\\s*\\[[\\s\\S]*?\\]\\s*,\\s*\\[[\\s\\S]*?\\]\\s*,\\s*\\[[\\s\\S]*?\\]\\s*\\]',
                '\\[[^\\]]*,[^\\]]*,[^\\]]*,[^\\]]*\\]'
            ],
            'operator' => 'AND',
            'feedback' => 'studenten soll eine aeussere Liste mit genau drei inneren Listen sein. Jede innere Liste soll vier Werte enthalten.'
        ],
        [
            'type' => 'code_check',
            'keywords' => [
                'zweiter_student\\s*=\\s*studenten\\s*\\[\\s*1\\s*\\]'
            ],
            'operator' => 'AND',
            'feedback' => 'Speichere alle Werte des zweiten Studenten als komplette innere Liste in zweiter_student.'
        ],
        [
            'type' => 'output',
            'input' => '',
            'expected_type' => 'regex',
            'expected' => '^\\[\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\s*,\\s*\\d+\\.\\d+\\s*,\\s*\\d+\\s*\\]$',
            'validation_mode' => 'strict'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $hint1 = 'Jeder Studierende ist ein zusammengehoeriger Datensatz und sollte deshalb in einer eigenen inneren Liste stehen.';
    $hint2 = 'Der zweite Studierende hat den Index 1 und seine komplette Liste soll in zweiter_student landen.';
    $hint3 = 'Die vier Werte sollen in dieser Reihenfolge stehen: Text, ganze Zahl, Kommazahl mit Punkt, ganze Zahl.';

    $stmt = $conn->prepare('UPDATE tasks SET title = ?, task_text = ?, description = ?, solution_code = ?, test_cases = ?, hint1 = ?, hint2 = ?, hint3 = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        'ssssssssi',
        $title,
        $taskText,
        $description,
        $solutionCode,
        $testCases,
        $hint1,
        $hint2,
        $hint3,
        $taskId
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 1) {
        echo "⚠ Task #{$taskId} unchanged or not found.\n";
    } else {
        echo "✓ Updated task #{$taskId} with stronger regex validation for zweiter_student\n";
    }

    $stmt->close();
    echo "\n✅ Migration 034: Success!\n";
} catch (Exception $e) {
    echo '❌ Migration 034 failed: ' . $e->getMessage() . "\n";
    exit(1);
}