<?php
/**
 * Migration 047: Update solution_code for task #13 Schachbrettfelder
 * to direct print in nested loops (no temporary row list / append).
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 047: update solution code for #13...\n";

    $solutionCode = <<<'PY'
spalten = ["A", "B", "C", "D", "E", "F", "G", "H"]
reihen = [8, 7, 6, 5, 4, 3, 2, 1]

for reihe in reihen:
    for spalte in spalten:
        feld = spalte + str(reihe)
        if spalte != "H":
            print(feld, end=" ")
        else:
            print(feld, end="")
    print()
PY;

    $assignmentTitle = 'C: Bedingungen und Schleifen';
    $taskTitle = '#13 Schachbrettfelder';

    $stmt = $conn->prepare(
        'UPDATE tasks t
         JOIN assignments a ON a.id = t.assignment_id
         SET t.solution_code = ?, t.updated_at = NOW()
         WHERE a.title = ? AND t.title = ?'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('sss', $solutionCode, $assignmentTitle, $taskTitle);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        echo "⚠ No rows updated (task not found or already identical).\n";
    } else {
        echo "✓ Updated rows: {$affected}\n";
    }

    echo "\n✅ Migration 047: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 047 failed: " . $e->getMessage() . "\n";
    exit(1);
}
