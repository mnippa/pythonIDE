<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "Assignments table structure:\n";
echo "========================================\n";
$result = $conn->query('DESCRIBE assignments');
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']}\n";
}

echo "\n\nExisting assignments:\n";
echo "========================================\n";
$result = $conn->query('SELECT id, title, difficulty FROM assignments');
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | {$row['title']} | {$row['difficulty']}\n";
}
?>
