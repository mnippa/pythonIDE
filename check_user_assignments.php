<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

echo "=== user_assignments table structure ===\n";
$result = $conn->query('DESCRIBE user_assignments');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . ($row['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
}

$conn->close();
