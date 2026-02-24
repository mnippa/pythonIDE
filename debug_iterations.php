<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', '');

echo "=== VERGLEICH iterations_count ===\n\n";

$stmt = $pdo->query('SELECT id, task_type, title, iterations_count FROM tasks WHERE id IN (147, 155) ORDER BY id');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "❌ Tasks nicht gefunden!\n";
} else {
    foreach ($results as $row) {
        echo "Task #{$row['id']}: {$row['title']}\n";
        echo "  Type: {$row['task_type']}\n";
        echo "  iterations_count: " . ($row['iterations_count'] === null ? "NULL" : $row['iterations_count']) . "\n";
        echo "\n";
    }
    
    $task147 = $results[0] ?? null;
    $task155 = $results[1] ?? null;
    
    if ($task147 && $task155) {
        if ($task147['iterations_count'] === $task155['iterations_count']) {
            echo "✅ iterations_count sind IDENTISCH\n";
        } else {
            echo "❌ iterations_count sind UNTERSCHIEDLICH\n";
            echo "   #147: " . ($task147['iterations_count'] ?? "NULL") . "\n";
            echo "   #155: " . ($task155['iterations_count'] ?? "NULL") . "\n";
        }
    }
}
