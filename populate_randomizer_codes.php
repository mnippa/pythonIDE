<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

echo "=== POPULATING RANDOMIZER_CODE FOR code_random_complex TASKS ===\n\n";

$updates = [
    69 => "import random\nbinary = format(random.randint(0, 255), '08b')\nvalues = {\"binary\": binary}",
    70 => "import random\ndecimal = random.randint(100, 255)\nvalues = {\"decimal\": decimal}",
    73 => "import random\nbinary = format(random.randint(0, 255), '08b')\nvalues = {\"binary\": binary}"
];

foreach ($updates as $taskId => $randomCode) {
    $taskId = intval($taskId);
    $randomCode = $conn->real_escape_string($randomCode);
    
    $sql = "UPDATE tasks SET randomizer_code = '$randomCode' WHERE id = $taskId";
    
    if ($conn->query($sql)) {
        echo "✓ Task ID $taskId updated with randomizer_code\n";
    } else {
        echo "✗ Task ID $taskId failed: " . $conn->error . "\n";
    }
}

echo "\n✓ Done!\n";
$conn->close();
