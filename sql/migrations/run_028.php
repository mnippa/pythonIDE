<?php
/**
 * Migration 028: Rewrite task 191 to intelligent vars validation.
 *
 * Task: Assignment 23 / "Arithmetik: Addition, Subtraktion, Multiplikation und Division"
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 028: rewrite task 191 to intelligent vars...\n";

    $taskId = 191;

    $testCases = json_encode([
        'mode' => 'vars',
        'tests' => 4,
        'inputs' => ['a', 'b'],
        'outputs' => ['summe', 'differenz', 'produkt', 'quotient']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $randomizerCode = <<<'PY'
import random

values = {
    "a": random.randint(1, 50),
    "b": random.randint(1, 50)
}
PY;

    $solutionCode = <<<'PY'
#INIT START
a = 0
b = 0
#INIT END

summe = a + b
differenz = a - b
produkt = a * b
quotient = a / b
PY;

    $codeTemplate = <<<'PY'
#INIT START
a = 0
b = 0
#INIT END

summe = a + b
differenz = a - b
produkt = a * b
quotient = a / b

print(f"Zahlen: a={a}, b={b}")
print(f"Summe={summe}")
print(f"Differenz={differenz}")
print(f"Produkt={produkt}")
print(f"Quotient={quotient:.4f}")
PY;

    $stmtCheck = $conn->prepare('SELECT id, title, assignment_id FROM tasks WHERE id = ? LIMIT 1');
    $stmtCheck->bind_param('i', $taskId);
    $stmtCheck->execute();
    $task = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$task) {
        throw new Exception('Task 191 not found. Migration cannot continue.');
    }

    $stmt = $conn->prepare('UPDATE tasks SET test_cases = ?, randomizer_code = ?, solution_code = ?, code_template = ? WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ssssi', $testCases, $randomizerCode, $solutionCode, $codeTemplate, $taskId);

    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }

    echo "✓ Updated task #{$taskId}: {$task['title']} (assignment {$task['assignment_id']})\n";
    echo "✓ test_cases set to intelligent vars mode\n";
    echo "✓ randomizer_code, solution_code and code_template updated\n";

    $stmt->close();
    echo "\n✅ Migration 028: Success!\n";
} catch (Exception $e) {
    echo "❌ Migration 028 failed: " . $e->getMessage() . "\n";
    exit(1);
}
