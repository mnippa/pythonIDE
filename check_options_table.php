<?php
require 'config/database.php';
$conn = getDbConnection();

$result = $conn->query('DESCRIBE task_options');
echo "task_options columns:\n";
while($row = $result->fetch_assoc()) {
    echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
