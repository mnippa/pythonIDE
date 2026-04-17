<?php
/**
 * Migration 049: Update task #14 to use loop vars i/j and paper table i/j.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 049: update task #14 i/j...\n";

    $code = <<<'PY'
m = [[{a}, {b}], [{c}, {d}], [{e}, {f}]]
grenze = {grenze}

s = 0
counter = 0
for i in range(3):
    for j in range(2):
        v = m[i][j]
        counter = counter + 1
        if v < grenze:
            s = s + (i + 1) * (j + 2)

antwort = s
PY;

    $stoff = "Vorgehen auf Papier (pro Schritt eine Zeile ausfuellen):\n"
        . "| Schritt | i | j | v | counter | s |\n"
        . "|---|---:|---:|---:|---:|---:|\n"
        . "| 1 |   |   |   |   |   |\n"
        . "| 2 |   |   |   |   |   |\n"
        . "| 3 |   |   |   |   |   |\n"
        . "| 4 |   |   |   |   |   |\n"
        . "| 5 |   |   |   |   |   |\n"
        . "| 6 |   |   |   |   |   |\n"
        . "\nHinweis: counter steigt bei jedem v-Wert. s steigt nur, wenn v < grenze.";

    $hint3 = 'Die Addition nutzt die Position: (i + 1) * (j + 2).';

    $assignmentTitle = 'C: Bedingungen und Schleifen';
    $taskTitle = '#14 Code Reading verschachtelte Schleife 3x2';

    $stmt = $conn->prepare(
        'UPDATE tasks t
         JOIN assignments a ON a.id = t.assignment_id
         SET t.code_template = ?,
             t.solution_code = ?,
             t.stoff = ?,
             t.hint3 = ?,
             t.updated_at = NOW()
         WHERE a.title = ? AND t.title = ?'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ssssss', $code, $code, $stoff, $hint3, $assignmentTitle, $taskTitle);
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

    echo "\n✅ Migration 049: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 049 failed: " . $e->getMessage() . "\n";
    exit(1);
}
