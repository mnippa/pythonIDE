<?php
/**
 * Migration 031: Extend task 193 intelligent vars checks.
 *
 * Goal with existing platform logic:
 * - keep intelligent vars mode
 * - additionally validate list length is 4 via output variable `anzahl_faecher`
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 031: strengthen task 193 vars checks...\n";

    $taskId = 193;

    $testCases = json_encode([
        'mode' => 'vars',
        'tests' => 4,
        'inputs' => ['faecher'],
        'outputs' => ['anzahl_faecher', 'drittes_fach']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $solutionCode = <<<'PY'
#INIT START
faecher=["MAthe","Deutsch","Englisch","Informatik"]
#INIT END

anzahl_faecher = len(faecher)
drittes_fach=faecher[2]
PY;

    $codeTemplate = <<<'PY'
#INIT START
faecher=["MAthe","Deutsch","Englisch","Informatik"]
#INIT END

anzahl_faecher = len(faecher)
drittes_fach=faecher[2]
PY;

    $stmt = $conn->prepare('UPDATE tasks SET test_cases = ?, solution_code = ?, code_template = ? WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('sssi', $testCases, $solutionCode, $codeTemplate, $taskId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 1) {
        echo "⚠ Task #{$taskId} unchanged (already had same content or not found).\n";
    } else {
        echo "✓ Updated task #{$taskId}: outputs now include anzahl_faecher + drittes_fach\n";
    }

    $stmt->close();
    echo "\n✅ Migration 031: Success!\n";
} catch (Exception $e) {
    echo "❌ Migration 031 failed: " . $e->getMessage() . "\n";
    exit(1);
}
