<?php
require 'config/database.php';
$conn = getDbConnection();
$result = $conn->query('DESCRIBE tasks');
echo "Columns in 'tasks' table:\n";
while ($col = $result->fetch_assoc()) {
    echo '  - ' . $col['Field'] . " (" . $col['Type'] . ")\n";
}
?>
