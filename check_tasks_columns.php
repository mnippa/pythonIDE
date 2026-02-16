<?php
require 'config/database.php';
$conn = getDbConnection();

$result = $conn->query('DESCRIBE tasks');
echo "Tasks Table Columns:\n";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
