<?php
$db = new PDO('mysql:host=localhost;dbname=pythonide', 'root', '');

$stmt147 = $db->query('SELECT * FROM tasks WHERE id = 147');
$task147 = $stmt147->fetch(PDO::FETCH_ASSOC);

$stmt155 = $db->query('SELECT * FROM tasks WHERE id = 155');
$task155 = $stmt155->fetch(PDO::FETCH_ASSOC);

echo "=== VERGLEICH TASK #147 vs #155 ===\n\n";

// Vergleiche alle Felder
$fields = array_keys($task147);
$diffs = [];

foreach ($fields as $field) {
    $val147 = $task147[$field];
    $val155 = $task155[$field];
    
    if ($val147 !== $val155) {
        $diffs[$field] = [
            '147' => $val147,
            '155' => $val155
        ];
    }
}

if (empty($diffs)) {
    echo "✅ IDENTISCH - keine Unterschiede gefunden.\n";
} else {
    echo "❌ UNTERSCHIEDE GEFUNDEN:\n\n";
    foreach ($diffs as $field => $values) {
        echo "FELD: $field\n";
        echo "  #147: " . (is_string($values['147']) && strlen($values['147']) > 200 ? substr($values['147'], 0, 200) . "..." : var_export($values['147'], true)) . "\n";
        echo "  #155: " . (is_string($values['155']) && strlen($values['155']) > 200 ? substr($values['155'], 0, 200) . "..." : var_export($values['155'], true)) . "\n";
        echo "\n";
    }
}

// Extra: Zeige die variable_overrides als JSON
echo "\n=== variable_overrides (JSON Decode) ===\n";
$vo147 = json_decode($task147['variable_overrides'], true);
$vo155 = json_decode($task155['variable_overrides'], true);
echo "Task 147:\n" . json_encode($vo147, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
echo "Task 155:\n" . json_encode($vo155, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($vo147 === $vo155) {
    echo "✅ variable_overrides sind JSON-identisch\n";
} else {
    echo "❌ variable_overrides unterscheiden sich\n";
}
