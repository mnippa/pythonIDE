<?php
/**
 * Verify INIT-Blocks in Database
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "═══════════════════════════════════════════\n";
echo "VERIFY: INIT-Blocks in Database\n";
echo "═══════════════════════════════════════════\n\n";

// Get newest VARIABLE tasks
$stmt = $conn->prepare("
    SELECT t.id, t.title, t.code_template, t.solution_code, t.description,
           a.title as assignment_title
    FROM tasks t
    JOIN assignments a ON t.assignment_id = a.id
    WHERE t.title LIKE '%VARIABLE%'
    ORDER BY t.id DESC
    LIMIT 3
");
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

if (empty($tasks)) {
    echo "❌ Keine VARIABLE-Tasks gefunden!\n";
    exit(1);
}

echo "Gefunden: " . count($tasks) . " VARIABLE-Tasks\n\n";

foreach ($tasks as $i => $task) {
    echo str_repeat("═", 50) . "\n";
    echo "TASK " . ($i + 1) . ": {$task['title']} (ID: {$task['id']})\n";
    echo str_repeat("═", 50) . "\n\n";
    
    echo "Assignment: {$task['assignment_title']}\n\n";
    
    // Check for INIT blocks
    $hasInitInTemplate = strpos($task['code_template'], '#INIT Start#') !== false;
    $hasInitInSolution = strpos($task['solution_code'], '#INIT Start#') !== false;
    
    echo "INIT-Block Status:\n";
    echo "  code_template: " . ($hasInitInTemplate ? "✓ VORHANDEN" : "❌ FEHLT") . "\n";
    echo "  solution_code: " . ($hasInitInSolution ? "✓ VORHANDEN" : "❌ FEHLT") . "\n\n";
    
    // Show code_template
    echo "CODE TEMPLATE:\n";
    echo "┌" . str_repeat("─", 48) . "┐\n";
    foreach (explode("\n", $task['code_template']) as $line) {
        echo "│ " . str_pad($line, 46) . " │\n";
    }
    echo "└" . str_repeat("─", 48) . "┘\n\n";
    
    // Show solution_code
    echo "SOLUTION CODE:\n";
    echo "┌" . str_repeat("─", 48) . "┐\n";
    foreach (explode("\n", $task['solution_code']) as $line) {
        echo "│ " . str_pad($line, 46) . " │\n";
    }
    echo "└" . str_repeat("─", 48) . "┘\n\n";
    
    // Check description for INIT mention
    $hasInitInDesc = strpos($task['description'], 'INIT-Block') !== false;
    echo "DESCRIPTION:\n";
    echo "  Erwähnt INIT-Block: " . ($hasInitInDesc ? "✓ JA" : "❌ NEIN") . "\n";
    if ($hasInitInDesc) {
        // Show relevant excerpt
        $lines = explode("\n", $task['description']);
        foreach ($lines as $line) {
            if (stripos($line, 'INIT') !== false) {
                echo "  > " . trim($line) . "\n";
            }
        }
    }
    echo "\n";
    
    // Regex test
    echo "REGEX TEST (INIT-Block Entfernung):\n";
    $pattern = '/#INIT Start#.*?#INIT End#/s';
    $codeWithoutInit = preg_replace($pattern, '', $task['code_template']);
    $codeWithoutInit = trim(preg_replace('/\n\n+/', "\n\n", $codeWithoutInit));
    
    echo "├" . str_repeat("─", 48) . "┤\n";
    foreach (explode("\n", $codeWithoutInit) as $line) {
        echo "│ " . str_pad($line, 46) . " │\n";
    }
    echo "└" . str_repeat("─", 48) . "┘\n\n";
}

echo "═══════════════════════════════════════════\n";
echo "ZUSAMMENFASSUNG\n";
echo "═══════════════════════════════════════════\n\n";

$allHaveInit = true;
foreach ($tasks as $task) {
    $hasInit = strpos($task['code_template'], '#INIT Start#') !== false;
    if (!$hasInit) {
        $allHaveInit = false;
        echo "❌ Task {$task['id']}: {$task['title']} - KEIN INIT-Block\n";
    }
}

if ($allHaveInit) {
    echo "✅ ALLE VARIABLE-Tasks haben INIT-Blöcke!\n";
} else {
    echo "⚠️  Einige Tasks haben noch keine INIT-Blöcke\n";
}

echo "\nWIE ES FUNKTIONIERT:\n";
echo "────────────────────\n\n";

echo "1. STUDENT RUN:\n";
echo "   → Code MIT INIT-Block ausführen\n";
echo "   → Student sieht Output mit eigenen Werten\n\n";

echo "2. SYSTEM CHECK:\n";
echo "   → Regex entfernt INIT-Block:\n";
echo "     pattern = r'#INIT Start#.*?#INIT End#'\n";
echo "     flags = re.DOTALL\n";
echo "   → System setzt init_vars\n";
echo "   → Code ohne INIT-Block ausführen\n";
echo "   → Mehrere Tests mit verschiedenen Werten\n\n";

echo "VORTEILE:\n";
echo "─────────\n";
echo "✅ Student muss nichts löschen\n";
echo "✅ Python kennt Variablen-Typen\n";
echo "✅ IDE Autocomplete funktioniert\n";
echo "✅ Klare Trennung: Test vs. Lösung\n";
echo "✅ Weniger Fehler\n";
echo "✅ Bessere UX\n\n";

$conn->close();
