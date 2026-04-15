<?php
/**
 * Migration 035: Make zweiter_student regex type check order-independent.
 *
 * Required output shape for printed list:
 * - exactly 4 elements
 * - 1 string
 * - 2 integers
 * - 1 decimal number with dot
 * - order can vary
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 035: make regex validation order-independent...\n";

    $taskId = 195;

    $taskText = 'Lege eine Liste studenten an. Jeder Student soll durch eine innere Liste beschrieben werden: Name, Matrikelnummer, Durchschnittsnote und bestandene Pruefungen. Speichere Daten zu genau drei Studierenden. Speichere danach alle Werte des zweiten Studenten in zweiter_student und gib zweiter_student aus. Die Reihenfolge der vier Werte darf frei gewaehlt werden.';
    $description = 'Die Werte darfst du selbst waehlen. Wichtig ist die hierarchische Struktur: eine aeussere Liste mit drei inneren Listen. Jede innere Liste soll genau vier Werte enthalten: ein String, zwei ganze Zahlen und eine Kommazahl mit Punkt. Die Reihenfolge ist bewusst offen.';

    $orderOpenRegex = '^\\[\\s*(?:(?:[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\s*,\\s*\\d+\\s*,\\s*\\d+\\.\\d+)|(?:[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\s*,\\s*\\d+\\.\\d+\\s*,\\s*\\d+)|(?:[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\.\\d+\\s*,\\s*\\d+\\s*,\\s*\\d+)|(?:\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\s*,\\s*\\d+\\.\\d+)|(?:\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\.\\d+\\s*,\\s*\\d+)|(?:\\d+\\s*,\\s*\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\.\\d+)|(?:\\d+\\s*,\\s*\\d+\\.\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+)|(?:\\d+\\s*,\\s*\\d+\\.\\d+\\s*,\\s*\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"])|(?:\\d+\\.\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+\\s*,\\s*\\d+)|(?:\\d+\\.\\d+\\s*,\\s*\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]\\s*,\\s*\\d+)|(?:\\d+\\.\\d+\\s*,\\s*\\d+\\s*,\\s*\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"])|(?:\\d+\\s*,\\s*\\d+\\s*,\\s*\\d+\\.\\d+\\s*,\\s*[\'\"][^\'\"]+[\'\"]))\\s*\\]$';

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
            'expected' => $orderOpenRegex,
            'validation_mode' => 'strict'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $hint3 = 'Geprueft wird: genau vier Werte mit Typen String, ganze Zahl, Kommazahl mit Punkt, ganze Zahl. Die Reihenfolge darf variieren.';

    $stmt = $conn->prepare('UPDATE tasks SET task_text = ?, description = ?, test_cases = ?, hint3 = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ssssi', $taskText, $description, $testCases, $hint3, $taskId);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 1) {
        echo "⚠ Task #{$taskId} unchanged or not found.\n";
    } else {
        echo "✓ Updated task #{$taskId} with order-open regex validation\n";
    }

    $stmt->close();
    echo "\n✅ Migration 035: Success!\n";
} catch (Exception $e) {
    echo '❌ Migration 035 failed: ' . $e->getMessage() . "\n";
    exit(1);
}