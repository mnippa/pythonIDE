<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Tasks table structure ===\n";
$stmt = $pdo->query("DESCRIBE tasks");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-30s %-20s %s\n", $row['Field'], $row['Type'], $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
}

echo "\n=== Sample task (Task 169) ===\n";
$stmt = $pdo->query("SELECT * FROM tasks WHERE id = 169");
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if ($task) {
    foreach ($task as $key => $value) {
        if (in_array($key, ['code_template', 'description', 'solution_code', 'initial_code'])) {
            echo "$key: " . (strlen($value ?? '') > 50 ? substr($value, 0, 50) . '...' : $value) . "\n";
        } else {
            echo "$key: $value\n";
        }
    }
}
