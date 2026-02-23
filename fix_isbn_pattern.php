<?php
/**
 * Fix ISBN Regex Pattern in Task #140
 * 
 * Problem: Pattern ends with \d{1} (only 1 digit for check digit)
 * But ISBN-13 check digit can be 0-9 or 10 (2 digits)
 * 
 * Fix: Change \d{1}$ to \d{1,2}$
 */

require_once __DIR__ . '/config/database.php';

echo "=== Fixing ISBN Pattern in Task #140 ===\n\n";

// Get database connection
$conn = getDbConnection();

// Find Task #140
$stmt = $conn->prepare('SELECT id, title, test_cases FROM tasks WHERE id = 140');
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ Task #140 not found!\n");
}

$task = $result->fetch_assoc();
echo "✓ Found Task: {$task['title']}\n";

// Parse test_cases
$testCases = json_decode($task['test_cases'], true);
if (!$testCases) {
    die("❌ Failed to parse test_cases JSON\n");
}

echo "✓ Parsed test_cases: " . count($testCases) . " test(s)\n\n";

// Find and fix the pattern
$fixed = false;
foreach ($testCases as $idx => &$test) {
    if ($test['type'] === 'output' && isset($test['expected'])) {
        $oldPattern = $test['expected'];
        
        // Check if this is the faulty pattern
        if (strpos($oldPattern, '-\d{1}$') !== false) {
            echo "❌ FOUND FAULTY PATTERN:\n";
            echo "   {$oldPattern}\n\n";
            
            // Fix the pattern
            $newPattern = str_replace('-\d{1}$', '-\d{1,2}$', $oldPattern);
            $test['expected'] = $newPattern;
            
            echo "✅ FIXED PATTERN:\n";
            echo "   {$newPattern}\n\n";
            
            $fixed = true;
        }
    }
}

if (!$fixed) {
    die("⚠️  No faulty pattern found. Task might already be fixed.\n");
}

// Update database
$newTestCases = json_encode($testCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$updateStmt = $conn->prepare('UPDATE tasks SET test_cases = ? WHERE id = 140');
$updateStmt->bind_param('s', $newTestCases);

if ($updateStmt->execute()) {
    echo "✅ Successfully updated Task #140 in database\n\n";
    
    // Verify the fix
    $verifyStmt = $conn->prepare('SELECT test_cases FROM tasks WHERE id = 140');
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $verifiedTask = $verifyResult->fetch_assoc();
    $verifiedCases = json_decode($verifiedTask['test_cases'], true);
    
    echo "=== VERIFICATION ===\n";
    foreach ($verifiedCases as $test) {
        if ($test['type'] === 'output') {
            echo "Pattern: {$test['expected']}\n";
            echo "Contains \\d{1,2}: " . (strpos($test['expected'], '\d{1,2}') !== false ? "✅ YES" : "❌ NO") . "\n";
        }
    }
    
    echo "\n✅ FIX COMPLETE!\n";
    echo "\nTest with: ISBN 978-3-16-148410-10\n";
    
} else {
    echo "❌ Failed to update database: " . $updateStmt->error . "\n";
}

$stmt->close();
$updateStmt->close();
$conn->close();
