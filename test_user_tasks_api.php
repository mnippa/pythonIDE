<?php
session_start();

// Simulate logged-in user (for testing)
if (!isset($_SESSION['user'])) {
    // Login as test user (ID 2 = Max Müller)
    $_SESSION['user'] = [
        'id' => 2,
        'email' => 'max.mueller@example.com',
        'first_name' => 'Max',
        'last_name' => 'Müller',
        'role' => 'user'
    ];
}

// Test data
$testData = [
    'task_id' => 1,  // Adjust if needed
    'current_code' => "print('Test code')\nprint('Hello World')",
    'status' => 'in-progress',
    'hints_revealed' => [1],
    'attempts' => 3,
    'started_at' => date('Y-m-d H:i:s')
];

echo "Testing user_tasks/update.php API...\n\n";
echo "Test Data:\n";
print_r($testData);
echo "\n";

// Make API call
$ch = curl_init('http://localhost/pythonIDE/api/user_tasks/update.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: ' . session_name() . '=' . session_id()
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";
echo $response . "\n\n";

// Decode and pretty print
$json = json_decode($response, true);
if ($json) {
    echo "Parsed Response:\n";
    print_r($json);
} else {
    echo "Failed to parse JSON response\n";
    echo "Raw response: $response\n";
}

// Now test GET
echo "\n\n=== Testing GET ===\n";
$ch = curl_init('http://localhost/pythonIDE/api/user_tasks/get.php?task_id=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cookie: ' . session_name() . '=' . session_id()
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";
$json = json_decode($response, true);
if ($json) {
    print_r($json);
} else {
    echo "Raw: $response\n";
}
