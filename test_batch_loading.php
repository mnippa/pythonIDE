<?php
/**
 * Direct Performance Test - Measures optimized batch-loading query performance
 * This bypasses authentication to test the core optimization
 */

require_once 'config/database.php';

$conn = getDbConnection();
$assignmentId = 18;  // Test with assignment 18 (has 4 choice tasks out of 10)

echo "=== Batch-Loading Performance Test ===\n";
echo "Assignment ID: $assignmentId\n\n";

// Get all tasks
$startTotal = microtime(true);

// ====== OPTIMIZED VERSION (Using Batch Loading) ======
echo "Testing OPTIMIZED version (Batch Loading):\n";

$startMain = microtime(true);
$sql = "SELECT id, task_type FROM tasks WHERE assignment_id = ? ORDER BY position ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

$taskIds = [];
$rawTasks = [];
while ($row = $result->fetch_assoc()) {
    $taskIds[] = (int)$row['id'];
    $rawTasks[(int)$row['id']] = $row;
}
$timeMainQuery = round((microtime(true) - $startMain) * 1000, 2);

// Batch load options
$choiceTaskIds = [];
foreach ($rawTasks as $taskId => $row) {
    if (in_array($row['task_type'], ['single_choice', 'multiple_choice'])) {
        $choiceTaskIds[] = $taskId;
    }
}

$optionsMap = [];
$timeOptionsBatch = 0;

if (!empty($choiceTaskIds)) {
    $startOptions = microtime(true);
    $placeholders = implode(',', array_fill(0, count($choiceTaskIds), '?'));
    $optionsStmt = $conn->prepare(
        "SELECT task_id, id, option_text FROM task_options WHERE task_id IN ($placeholders)"
    );
    $optionsStmt->bind_param(str_repeat('i', count($choiceTaskIds)), ...$choiceTaskIds);
    $optionsStmt->execute();
    $optionsResult = $optionsStmt->get_result();
    
    while ($optionRow = $optionsResult->fetch_assoc()) {
        $taskId = (int)$optionRow['task_id'];
        if (!isset($optionsMap[$taskId])) {
            $optionsMap[$taskId] = [];
        }
        $optionsMap[$taskId][] = $optionRow;
    }
    $optionsStmt->close();
    $timeOptionsBatch = round((microtime(true) - $startOptions) * 1000, 2);
}

$timeTotalOptimized = round((microtime(true) - $startTotal) * 1000, 2);

echo "  Main query (all tasks): {$timeMainQuery}ms\n";
echo "  Batch-load options: {$timeOptionsBatch}ms\n";
echo "  Total time: {$timeTotalOptimized}ms\n";
echo "  Queries executed: 2\n\n";

// ====== UNOPTIMIZED VERSION (Per-task queries) ======
echo "Testing UNOPTIMIZED version (Per-task queries):\n";

$startTotal2 = microtime(true);

$sql = "SELECT id, task_type FROM tasks WHERE assignment_id = ? ORDER BY position ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

$queryCount = 1;
$timeSumUnoptimized = 0;
$choiceCount = 0;

while ($row = $result->fetch_assoc()) {
    $taskId = (int)$row['id'];
    if (in_array($row['task_type'], ['single_choice', 'multiple_choice'])) {
        // Per-task option query
        $startSub = microtime(true);
        $optStmt = $conn->prepare("SELECT id, option_text FROM task_options WHERE task_id = ? ORDER BY order_num");
        $optStmt->bind_param('i', $taskId);
        $optStmt->execute();
        $optStmt->get_result();
        $optStmt->close();
        $queryCount++;
        $choiceCount++;
        $timeSumUnoptimized += (microtime(true) - $startSub) * 1000;
    }
}

$timeTotalUnoptimized = round((microtime(true) - $startTotal2) * 1000, 2);

echo "  Main query (all tasks): {$timeMainQuery}ms\n";
echo "  Per-task option queries ({$choiceCount}): " . round($timeSumUnoptimized, 2) . "ms\n";
echo "  Total time: {$timeTotalUnoptimized}ms\n";
echo "  Queries executed: {$queryCount}\n\n";

// ====== COMPARISON ======
echo "=== Performance Improvement ===\n";
$improvementPct = round(((($timeTotalUnoptimized - $timeTotalOptimized) / $timeTotalUnoptimized) * 100), 1);
$improvementMs = round($timeTotalUnoptimized - $timeTotalOptimized, 2);

echo "Unoptimized: {$timeTotalUnoptimized}ms ({$queryCount} queries)\n";
echo "Optimized:   {$timeTotalOptimized}ms (2 queries)\n";
echo "Improvement: {$improvementMs}ms ({$improvementPct}% faster)\n";
echo "\nQuery reduction: {$queryCount} → 2 queries (reduction: " . ((($queryCount - 2) / $queryCount) * 100) . "%)\n";

echo "\n✓ Performance test completed.\n";
?>
