<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Checking for Task 170 ===\n";
$stmt = $pdo->query("SELECT id, title, position, assignment_id, type FROM tasks WHERE id = 170");
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if ($task) {
    echo "✓ Task 170 EXISTS\n";
    print_r($task);
} else {
    echo "✗ Task 170 NOT FOUND\n";
}

echo "\n=== All tasks in Assignment #21 (last 5) ===\n";
$stmt = $pdo->query("SELECT id, title, position FROM tasks WHERE assignment_id = 21 ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("Task %d (pos %d): %s\n", $row['id'], $row['position'], $row['title']);
}
