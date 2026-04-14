<?php
/**
 * Create Task: Arithmetic Operations with Variables
 * Assignment 23 - Code Random Complex
 * 
 * Task: Add two numbers a and b, calculate sum, difference, product, quotient
 * using intermediate variables that are validated.
 */

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

// Find next position in assignment 23
$result = $conn->query("SELECT MAX(position) as max_pos FROM tasks WHERE assignment_id=23");
$row = $result->fetch_assoc();
$nextPos = ($row['max_pos'] ?? 0) + 1;

$title = 'Arithmetische Operationen (Zwischenvariablen)';
$description = 'Addiere, subtrahiere, multipliziere und dividiere zwei Zahlen a und b. Speichere die Ergebnisse in Zwischenvariablen (summe, differenz, produkt, quotient).';
$taskType = 'code_random_complex';
$assignmentId = 23;

// Randomizer Code - generates two different numbers
$randomizer = <<<'PYTHON'
import random
a = random.randint(2, 15)
b = random.randint(2, 12)
PYTHON;

// Variable Overrides - multiple iterations with different values
$overrides = <<<'XML'
<iterations>
  <random>
    <variable name="a" value="5"/>
    <variable name="b" value="3"/>
  </random>
  <random>
    <variable name="a" value="10"/>
    <variable name="b" value="4"/>
  </random>
  <random>
    <variable name="a" value="8"/>
    <variable name="b" value="2"/>
  </random>
</iterations>
XML;

// Solution Code - uses intermediate variables
$solution = <<<'PYTHON'
# Intermediate variables
summe = a + b
differenz = a - b
produkt = a * b
quotient = a / b

# Output
print(f"Zahlen: a={a}, b={b}")
print(f"Summe={summe}")
print(f"Differenz={differenz}")
print(f"Produkt={produkt}")
print(f"Quotient={quotient:.2f}")
PYTHON;

// Insert into database
$sql = "INSERT INTO tasks 
        (assignment_id, title, description, task_type, position, randomizer_code, 
         variable_overrides, solution_code, iterations_count, 
         created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare error: " . $conn->error);
}

$iterCount = 3;

// Bind parameters: 9 parameters total
// Types: i=int, s=string, s=string, s=string, i=int, s=string, s=string, s=string, i=int
$types = 'isssissi';  // i(1) s s s(4) i(5) s s s(8) i(9) = 9 total

if (!$stmt->bind_param($types, 
    $assignmentId, $title, $description, $taskType, $nextPos,
    $randomizer, $overrides, $solution, $iterCount)) {
    die("Bind error: " . $stmt->error);
}

if ($stmt->execute()) {
    $taskId = $conn->insert_id;
    echo "✓ Task erstellt!\n";
    echo "  Task ID: {$taskId}\n";
    echo "  Assignment: {$assignmentId}\n";
    echo "  Position: {$nextPos}\n";
    echo "  Titel: {$title}\n";
    echo "  Typ: {$taskType}\n";
    echo "  Iterationen: {$iterCount}\n";
} else {
    echo "✗ Fehler beim Einfügen: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
