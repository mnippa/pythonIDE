<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate session
session_start();
$_SESSION['user'] = [
    'id' => 2,
    'email' => 'max.mueller@example.com',
    'first_name' => 'Max',
    'last_name' => 'Müller',
    'role' => 'user'
];

echo "=== Testing user_tasks API directly ===\n\n";
echo "Logged in as: " . $_SESSION['user']['email'] . " (ID: " . $_SESSION['user']['id'] . ")\n\n";

// Test data
$testData = [
    'task_id' => 1,
    'current_code' => "print('Test code')\nprint('Hello World')",
    'status' => 'in-progress',
    'hints_revealed' => [1],
    'attempts' => 3,
    'started_at' => date('Y-m-d H:i:s')
];

echo "Test Data:\n";
print_r($testData);
echo "\n";

// Simulate POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Set the raw input
$input = json_encode($testData);
echo "JSON Input: $input\n\n";

// Save original php://input (can't override it directly, so we'll use file_put_contents)
file_put_contents('php://memory', $input);

// Now let's test the update logic manually
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    
    $data = $testData; // Simulate the decoded JSON
    
    $userId = $_SESSION['user']['id'];
    $taskId = $data['task_id'];
    $currentCode = $data['current_code'] ?? null;
    $status = $data['status'] ?? 'in-progress';
    $attempts = $data['attempts'] ?? 0;
    $hintsRevealed = isset($data['hints_revealed']) ? json_encode($data['hints_revealed']) : '[]';
    $startedAt = $data['started_at'] ?? date('Y-m-d H:i:s');
    
    echo "=== Attempting Database Insert/Update ===\n";
    echo "User ID: $userId\n";
    echo "Task ID: $taskId\n";
    echo "Status: $status\n";
    echo "Attempts: $attempts\n";
    echo "Code length: " . strlen($currentCode) . " chars\n";
    echo "Hints: $hintsRevealed\n";
    echo "Started: $startedAt\n\n";
    
    // Check if record exists
    $checkStmt = $conn->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?");
    $checkStmt->bind_param("ii", $userId, $taskId);
    $checkStmt->execute();
    $existingResult = $checkStmt->get_result();
    
    if ($existingResult->num_rows > 0) {
        echo "✓ Record exists - updating...\n";
        
        $stmt = $conn->prepare("
            UPDATE user_tasks 
            SET current_code = ?, 
                status = ?, 
                attempts = ?, 
                hints_revealed = ?,
                updated_at = NOW()
            WHERE user_id = ? AND task_id = ?
        ");
        $stmt->bind_param("ssissi", $currentCode, $status, $attempts, $hintsRevealed, $userId, $taskId);
        
    } else {
        echo "✗ Record does not exist - inserting new...\n";
        
        $stmt = $conn->prepare("
            INSERT INTO user_tasks 
            (user_id, task_id, current_code, status, attempts, hints_revealed, started_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iississ", $userId, $taskId, $currentCode, $status, $attempts, $hintsRevealed, $startedAt);
    }
    
    if ($stmt->execute()) {
        echo "✓ Database operation successful!\n";
        echo "  Affected rows: " . $stmt->affected_rows . "\n";
        $insertId = $stmt->insert_id ?: ($existingResult->num_rows > 0 ? $existingResult->fetch_assoc()['id'] : 0);
        echo "  Record ID: $insertId\n\n";
        
        // Verify the data was saved
        echo "=== Verification ===\n";
        $verifyStmt = $conn->prepare("SELECT * FROM user_tasks WHERE user_id = ? AND task_id = ?");
        $verifyStmt->bind_param("ii", $userId, $taskId);
        $verifyStmt->execute();
        $result = $verifyStmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "✓ Data retrieved successfully:\n";
            echo "  ID: " . $row['id'] . "\n";
            echo "  Status: " . $row['status'] . "\n";
            echo "  Attempts: " . $row['attempts'] . "\n";
            echo "  Code length: " . strlen($row['current_code']) . " chars\n";
            echo "  Hints revealed: " . $row['hints_revealed'] . "\n";
            echo "  Started at: " . $row['started_at'] . "\n";
            echo "  Updated at: " . $row['updated_at'] . "\n";
        } else {
            echo "✗ Failed to retrieve saved data\n";
        }
        
    } else {
        echo "✗ Database operation failed!\n";
        echo "  Error: " . $stmt->error . "\n";
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
