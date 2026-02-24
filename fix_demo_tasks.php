<?php
require_once 'config/database.php';
$conn = getDbConnection();

// Fix Task 147
$id = 147;
$randomizerCode = "import random\nbinary = format(random.randint(0, 255), '08b')";
$solutionCode = "result = int({binary}, 2)";

$stmt = $conn->prepare('UPDATE tasks SET randomizer_code = ?, solution_code = ? WHERE id = ?');
$stmt->bind_param('ssi', $randomizerCode, $solutionCode, $id);
if ($stmt->execute()) {
    echo "✓ Task 147 updated\n";
}

// Fix Task 148
$id = 148;
$randomizerCode = "import random\ncelsius = random.randint(-50, 50)";
$solutionCode = "fahrenheit = ({celsius} * 9/5) + 32";

$stmt = $conn->prepare('UPDATE tasks SET randomizer_code = ?, solution_code = ? WHERE id = ?');
$stmt->bind_param('ssi', $randomizerCode, $solutionCode, $id);
if ($stmt->execute()) {
    echo "✓ Task 148 updated\n";
}

echo "\n✓ All demo tasks fixed to new unified format!\n";
?>
