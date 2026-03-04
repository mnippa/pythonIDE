<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Tasks in Assignment #21 ===\n";
$stmt = $pdo->query("SELECT id, title, position FROM tasks WHERE assignment_id = 21 ORDER BY position");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("Task %d (pos %d): %s\n", $row['id'], $row['position'], $row['title']);
}

echo "\n=== Next available task ID ===\n";
$stmt = $pdo->query("SELECT MAX(id) as max_id FROM tasks");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Next task ID: " . ($row['max_id'] + 1) . "\n";
