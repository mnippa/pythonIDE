<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, title, validation_mode, LENGTH(solution_code) as sol_len FROM tasks WHERE id BETWEEN 36 AND 41');
$stmt->execute();
$res = $stmt->get_result();

echo "=== Tasks 36-41 (Intelligent Assignment) ===\n\n";
while($row = $res->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['title']}\n";
    echo "  validation_mode: {$row['validation_mode']}\n";
    echo "  solution_code length: {$row['sol_len']} bytes\n\n";
}
