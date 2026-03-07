<?php
require_once 'c:/xampp/htdocs/pythonIDE/config/database.php';

$conn = getDbConnection();
$result = $conn->query('SELECT id, name, project_type FROM projects ORDER BY id');

echo "ID | Project Name | Type\n";
echo str_repeat('-', 60) . "\n";

while($row = $result->fetch_assoc()) {
    printf("%3d | %-30s | %s\n", $row['id'], $row['name'], $row['project_type']);
}

$conn->close();
