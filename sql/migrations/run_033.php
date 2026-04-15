<?php
/**
 * Migration 033: Replace task 195 with a more concrete nested student list task.
 *
 * Goal:
 * - keep the task open in values
 * - make the structure more concrete
 * - prepare the idea of later dictionary-based modeling
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 033: update task 'Regal: eigene Struktur finden'...\n";

    $assignmentId = 23;
    $oldTitle = 'Regal: eigene Struktur finden';

    $newTitle = 'Studierendendaten: zweiter Student';
    $taskText = 'Lege eine Liste studenten an. Jeder Student soll durch eine innere Liste beschrieben werden: Name, Matrikelnummer, bestandene Pruefungen und Durchschnittsnote. Speichere Daten zu genau drei Studierenden. Speichere danach alle Werte des zweiten Studenten in zweiter_student und gib zweiter_student aus.';
    $description = 'Die Werte darfst du selbst waehlen. Wichtig ist die hierarchische Struktur: eine aeussere Liste mit drei inneren Listen. So sieht man den Unterschied zwischen einer flachen Sammlung und einer geordneten Struktur. Spaeter laesst sich dieselbe Idee noch besser mit Dictionaries ausdruecken.';
    $stoff = 'Verschachtelte Listen, Daten strukturieren, Modellierung von Datensaetzen';

    $codeTemplate = <<<'PY'
# Lege hier eine verschachtelte Liste mit drei Studierenden an
studenten = []

# Speichere alle Werte des zweiten Studenten in zweiter_student
zweiter_student = []

print(zweiter_student)
PY;

    $solutionCode = <<<'PY'
studenten = [
    ["Anna Meyer", 12345, 4, 2.1],
    ["Ben Schulz", 23456, 5, 1.9],
    ["Cem Yildiz", 34567, 3, 2.7]
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
            'type' => 'code_check',
            'keywords' => [
                'print\\s*\\(\\s*zweiter_student\\s*\\)'
            ],
            'operator' => 'AND',
            'feedback' => 'Gib am Ende die komplette Liste zweiter_student aus.'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $hint1 = 'Jeder Studierende ist ein zusammengehoeriger Datensatz und sollte deshalb in einer eigenen inneren Liste stehen.';
    $hint2 = 'Die aeussere Liste enthaelt drei Studierende. Der zweite Studierende hat den Index 1.';
    $hint3 = 'Wenn du alle Werte des zweiten Studenten willst, nimm die komplette innere Liste statt einzelner Elemente.';
    $maxAttempts = 10;

    $stmt = $conn->prepare('UPDATE tasks SET title = ?, task_text = ?, description = ?, stoff = ?, code_template = ?, solution_code = ?, test_cases = ?, hint1 = ?, hint2 = ?, hint3 = ?, max_attempts = ?, updated_at = NOW() WHERE assignment_id = ? AND title = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        'ssssssssssiis',
        $newTitle,
        $taskText,
        $description,
        $stoff,
        $codeTemplate,
        $solutionCode,
        $testCases,
        $hint1,
        $hint2,
        $hint3,
        $maxAttempts,
        $assignmentId,
        $oldTitle
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 1) {
        echo "⚠ No task updated. Task may already be renamed or missing.\n";
    } else {
        echo "✓ Updated task '{$oldTitle}' to '{$newTitle}'\n";
    }

    $stmt->close();
    echo "\n✅ Migration 033: Success!\n";
} catch (Exception $e) {
    echo '❌ Migration 033 failed: ' . $e->getMessage() . "\n";
    exit(1);
}