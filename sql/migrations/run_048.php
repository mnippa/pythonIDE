<?php
/**
 * Migration 048: Update solution_code for task #13 Schachbrettfelder
 * to dynamic string building per row (no list append).
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 048: update solution code for #13...\n";

    $solutionCode = <<<'PY'
spalten = ["A", "B", "C", "D", "E", "F", "G", "H"]
reihen = [8, 7, 6, 5, 4, 3, 2, 1]

for reihe in reihen:
    zeile_text = ""
    for spalte in spalten:
        feld = spalte + str(reihe)
        if spalte != "H":
            zeile_text = zeile_text + feld + " "
        else:
            zeile_text = zeile_text + feld
    print(zeile_text)
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

    echo "\n✅ Migration 048: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 048 failed: " . $e->getMessage() . "\n";
    exit(1);
}
