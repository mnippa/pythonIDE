<?php
/**
 * Migration 038: Rework task 202 into randomized scope-reading task.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 038: update task 202 scope reading...\n";

    $taskId = 202;

    $title = 'Code Lesen: Scope mit Rueckgabe';
    $taskText = 'Was ist der Endwert von v nach dem letzten Befehl?';
    $description = 'Lies den angezeigten Code. Ausserhalb der Funktion gibt es eine globale Variable v. Innerhalb der Funktion gibt es ebenfalls eine Variable v, diese ist aber lokal und aendert die globale Variable ausserhalb der Funktion nicht direkt. Bestimme den Wert von v nach der letzten Zeile.';
    $stoff = "Lokale und globale Variablen (Scope)\n\n"
        . "Eine Variable ausserhalb einer Funktion ist global.\n"
        . "Eine Variable mit demselben Namen innerhalb einer Funktion ist lokal.\n"
        . "Diese lokale Variable ersetzt die globale nicht, sondern existiert nur innerhalb der Funktion.\n\n"
        . "Wichtig:\n"
        . "- v ausserhalb der Funktion bleibt unveraendert, solange in der Funktion nicht mit global gearbeitet wird.\n"
        . "- return gibt nur den Rueckgabewert an den Aufrufer zurueck.\n"
        . "- In der letzten Zeile wird also der alte globale Wert von v mit dem Rueckgabewert der Funktion addiert.\n\n"
        . "Denke den Ablauf in zwei Schritten:\n"
        . "1. Welchen Wert liefert meineFunktion() zurueck?\n"
        . "2. Welchen Wert hat das globale v davor, und was ergibt die Addition?";
    $taskType = 'code_random_complex';
    $problemType = 'code_completion';
    $correctAnswer = 'v';
    $codeTemplate = <<<'PY'
v = {start_v}

def meineFunktion():
    v = {local_v}
    return v

v = v + meineFunktion()
PY;
    $solutionCode = <<<'PY'
v = {start_v}

def meineFunktion():
    v = {local_v}
    return v

v = v + meineFunktion()
PY;
    $randomizerCode = <<<'PY'
import random

start_v = random.randint(2, 12)
local_v = random.randint(2, 12)
PY;
    $variableOverrides = json_encode([
        [
            'inputs' => [
                'start_v' => '<random>',
                'local_v' => '<random>'
            ],
            'expected' => [
                'variable' => 'v'
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hint1 = 'Die Variable v innerhalb der Funktion ist lokal und ersetzt das globale v nicht.';
    $hint2 = 'meineFunktion() gibt den inneren Wert von v zurueck.';
    $hint3 = 'In der letzten Zeile wird gerechnet: globales v + Rueckgabewert der Funktion.';
    $iterationsCount = 5;
    $showSolutionCode = 1;

    $sql = 'UPDATE tasks
        SET title = ?,
            task_text = ?,
            description = ?,
            stoff = ?,
            task_type = ?,
            problem_type = ?,
            correct_answer = ?,
            code_template = ?,
            solution_code = ?,
            randomizer_code = ?,
            variable_overrides = ?,
            test_cases = NULL,
            generator_code = NULL,
            hint1 = ?,
            hint2 = ?,
            hint3 = ?,
            iterations_count = ?,
            show_solution_code = ?,
            updated_at = NOW()
        WHERE id = ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        'ssssssssssssssiii',
        $title,
        $taskText,
        $description,
        $stoff,
        $taskType,
        $problemType,
        $correctAnswer,
        $codeTemplate,
        $solutionCode,
        $randomizerCode,
        $variableOverrides,
        $hint1,
        $hint2,
        $hint3,
        $iterationsCount,
        $showSolutionCode,
        $taskId
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        echo "⚠ No rows changed. Task #202 may already have the target values.\n";
    } else {
        echo "✓ Updated task #202: {$title}\n";
    }

    $stmt->close();
    $conn->close();

    echo "\n✅ Migration 038: Success!\n";
} catch (Exception $e) {
    echo "❌ Migration 038 failed: " . $e->getMessage() . "\n";
    exit(1);
}