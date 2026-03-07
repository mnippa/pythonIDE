<?php
require_once 'c:/xampp/htdocs/pythonIDE/config/database.php';

$conn = getDbConnection();

// Update Blackjack to html (has GUI)
$stmt = $conn->prepare("UPDATE projects SET project_type = ? WHERE id = ?");
$type = 'html';
$id = 10;
$stmt->bind_param('si', $type, $id);
$stmt->execute();
echo "✓ Blackjack (ID 10) updated to 'html'\n";

// Update Kniffel to html (has GUI)
$id = 11;
$stmt->bind_param('si', $type, $id);
$stmt->execute();
echo "✓ Kniffel (ID 11) updated to 'html'\n";

$stmt->close();

// Verify updates
echo "\nVerification:\n";
$result = $conn->query("SELECT id, name, project_type FROM projects WHERE id IN (10, 11)");
while($row = $result->fetch_assoc()) {
    printf("  ID %d: %s -> %s\n", $row['id'], $row['name'], $row['project_type']);
}

$conn->close();
