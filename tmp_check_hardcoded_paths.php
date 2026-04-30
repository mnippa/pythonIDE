<?php
require 'config/database.php';

$db = getDbConnection();
$r = $db->query('SELECT id, title, code_template, solution_code FROM tasks WHERE id IN (276, 277, 278) ORDER BY id');

while ($row = $r->fetch_assoc()) {
    echo "Task " . $row['id'] . ": " . $row['title'] . "\n";
    
    if (strpos($row['code_template'], 'pythonIDEbeta') !== false || strpos($row['solution_code'], 'pythonIDEbeta') !== false) {
        echo "  ⚠️  Found pythonIDEbeta reference!\n";
    }
    
    if (strpos($row['code_template'], 'import') !== false) {
        echo "  code_template imports: " . substr($row['code_template'], 0, 60) . "...\n";
    }
    
    if (strpos($row['solution_code'], 'import') !== false) {
        echo "  solution_code imports: " . substr($row['solution_code'], 0, 60) . "...\n";
    }
    
    echo "\n";
}
