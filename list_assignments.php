<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();
$result = $conn->query('SELECT id, title FROM assignments ORDER BY id');
while($row = $result->fetch_assoc()) {
    echo 'ID ' . $row['id'] . ': ' . $row['title'] . "\n";
}
$conn->close();
