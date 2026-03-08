<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting script...\n";

require_once __DIR__ . '/config/database.php';

echo "Getting DB connection...\n";
$conn = getDbConnection();

echo "DB connected!\n";

// Check current state
$stmt = $conn->prepare("SELECT id, title, task_type, folderstructure FROM tasks WHERE id IN (?, ?)");
$id173 = 173;
$id174 = 174;
$stmt->bind_param('ii', $id173, $id174);
$stmt->execute();
$result = $stmt->get_result();

echo "Current state:\n";
$found = 0;
while ($row = $result->fetch_assoc()) {
    $found++;
    echo "Task #{$row['id']}: {$row['title']} | Type: {$row['task_type']} | Folder: {$row['folderstructure']}\n";
}

if ($found === 0) {
    echo "No tasks found with IDs 173, 174\n";
    exit;
}

// Update to code_ui
echo "\nUpdating to task_type='code_ui'...\n";
$stmt = $conn->prepare("UPDATE tasks SET task_type = 'code_ui' WHERE id IN (?, ?)");
$stmt->bind_param('ii', $id173, $id174);
if ($stmt->execute()) {
    echo "✓ Successfully updated " . $stmt->affected_rows . " tasks\n";
} else {
    echo "❌ Update failed: " . $conn->error . "\n";
}

// Verify
$stmt = $conn->prepare("SELECT id, title, task_type, folderstructure FROM tasks WHERE id IN (?, ?)");
$stmt->bind_param('ii', $id173, $id174);
$stmt->execute();
$result = $stmt->get_result();

echo "\nNew state:\n";
while ($row = $result->fetch_assoc()) {
    echo "Task #{$row['id']}: {$row['title']} | Type: {$row['task_type']} | Folder: {$row['folderstructure']}\n";
}

$conn->close();
echo "\nDone.\n";

