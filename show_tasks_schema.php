<?php
// Check tasks table structure
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123');

// Get column information
$sql = "SHOW COLUMNS FROM tasks";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Tasks Table Columns:\n\n";
foreach ($columns as $col) {
    $type = $col['Type'] ?? '';
    $null = $col['Null'] ?? 'NO';
    $default = $col['Default'] ?? 'N/A';
    $required = ($null === 'NO' && $default === 'N/A') ? '(REQUIRED)' : '';
    printf("%-30s %-20s NULL:%3s DEFAULT:%20s %s\n", $col['Field'], $type, $null, $default, $required);
}
echo "</pre>";
?>
