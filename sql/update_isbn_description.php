<?php
require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

// Update description to show actual pattern
$description = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>' .
               '<table class="test-requirements-table">' .
               '<thead><tr><th>Aspekt</th><th>Details</th></tr></thead>' .
               '<tbody>' .
               '<tr><td>OUTPUT</td><td>Regex Pattern Match</td></tr>' .
               '<tr><td>Pattern</td><td><code>^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$</code></td></tr>' .
               '</tbody>' .
               '</table></div>';

$stmt = $conn->prepare("UPDATE tasks SET description = ? WHERE id = 140");
$stmt->bind_param("s", $description);

if ($stmt->execute()) {
    echo "✅ Description für Task #140 aktualisiert!\n\n";
    echo "Das Pattern wird jetzt in der Tabelle angezeigt:\n";
    echo "  Pattern: ^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$\n";
} else {
    echo "❌ Fehler: " . $stmt->error . "\n";
}
