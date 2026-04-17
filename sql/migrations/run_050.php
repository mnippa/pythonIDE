<?php
/**
 * Migration 050: Update task #14 stoff to HTML trace table.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 050: update task #14 stoff HTML table...\n";

    $stoff = '<p>Vorgehen auf Papier (pro Schritt eine Zeile ausfuellen):</p>'
        . '<table class="stoff-trace-table" border="1" cellpadding="6" cellspacing="0">'
        . '<thead><tr><th>Schritt</th><th>i</th><th>j</th><th>v</th><th>counter</th><th>s</th></tr></thead>'
        . '<tbody>'
        . '<tr><td>1</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '<tr><td>2</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '<tr><td>3</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '<tr><td>4</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '<tr><td>5</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '<tr><td>6</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '</tbody></table>'
        . '<p>Hinweis: counter steigt bei jedem v-Wert. s steigt nur, wenn v &lt; grenze.</p>';

    $assignmentTitle = 'C: Bedingungen und Schleifen';
    $taskTitle = '#14 Code Reading verschachtelte Schleife 3x2';

    $stmt = $conn->prepare(
        'UPDATE tasks t
         JOIN assignments a ON a.id = t.assignment_id
         SET t.stoff = ?,
             t.updated_at = NOW()
         WHERE a.title = ? AND t.title = ?'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('sss', $stoff, $assignmentTitle, $taskTitle);
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

    echo "\n✅ Migration 050: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 050 failed: " . $e->getMessage() . "\n";
    exit(1);
}
