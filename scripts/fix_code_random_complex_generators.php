<?php
/**
 * Fix generator code for specific code_random_complex tasks (IDs 75-81).
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$updates = [
    75 => [
        'code_template' => "import random\nvalues = {\"binary\": format(random.randint(0, 255), '08b')}",
        'solution_code' => "result = int(values[\"binary\"], 2)"
    ],
    76 => [
        'code_template' => "import random\nvalues = {\"decimal\": random.randint(100, 255)}",
        'solution_code' => "result = format(values[\"decimal\"], '08b')"
    ],
    77 => [
        'code_template' => "import random\nvalues = {\"hex\": format(random.randint(0, 255), '02x')}",
        'solution_code' => "result = int(values[\"hex\"], 16)"
    ],
    79 => [
        'code_template' => "import random\nvalues = {\"binary\": format(random.randint(0, 255), '08b')}",
        'solution_code' => "result = int(values[\"binary\"], 2)"
    ],
    80 => [
        'code_template' => "import random\nvalues = {\"decimal\": random.randint(100, 255)}",
        'solution_code' => "result = format(values[\"decimal\"], '08b')"
    ],
    81 => [
        'code_template' => "import random\nvalues = {\"hex\": format(random.randint(0, 255), '02x')}",
        'solution_code' => "result = int(values[\"hex\"], 16)"
    ]
];

$stmt = $conn->prepare('UPDATE tasks SET code_template = ?, solution_code = ? WHERE id = ? AND task_type = "code_random_complex"');
if (!$stmt) {
    echo "Prepare failed: {$conn->error}\n";
    exit(1);
}

$updated = 0;
foreach ($updates as $id => $data) {
    $stmt->bind_param('ssi', $data['code_template'], $data['solution_code'], $id);
    if ($stmt->execute()) {
        $updated += $stmt->affected_rows;
        echo "Updated task {$id}\n";
    } else {
        echo "Failed task {$id}: {$stmt->error}\n";
    }
}

$stmt->close();
$conn->close();

echo "Done. Updated rows: {$updated}\n";
