<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();
$res = $conn->query('DESCRIBE tasks');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' => ' . $row['Type'] . PHP_EOL;
}
