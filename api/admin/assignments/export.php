<?php
/**
 * Export Assignment with all Tasks as JSON
 */

// Start output buffering to prevent any premature output
ob_start();

// Ensure no errors are displayed as HTML
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';

// Manual authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

$assignmentId = $_GET['id'] ?? null;

if (!$assignmentId) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Assignment ID required']);
    exit;
}

try {
    $pdo = getPdoConnection();
    
    // Get assignment
    $stmt = $pdo->prepare('SELECT * FROM assignments WHERE id = ?');
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Assignment not found']);
        exit;
    }
    
    // Get all test cases for this assignment
    $stmt = $pdo->prepare('SELECT * FROM test_cases WHERE assignment_id = ? ORDER BY order_num ASC');
    $stmt->execute([$assignmentId]);
    $testCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build export structure
    $export = [
        'version' => '1.0',
        'exported_at' => date('Y-m-d H:i:s'),
        'title' => $assignment['title'],
        'description' => $assignment['description'],
        'code_template' => $assignment['code_template'],
        'difficulty' => $assignment['difficulty'],
        'is_active' => (bool)$assignment['is_active'],
        'time_limit_minutes' => $assignment['time_limit_minutes'],
        'test_cases' => []
    ];
    
    // Add test cases
    foreach ($testCases as $testCase) {
        $testCaseData = [
            'description' => $testCase['description'],
            'test_input' => json_decode($testCase['test_input'], true) ?: [],
            'expected_output' => $testCase['expected_output'],
            'is_hidden' => (bool)$testCase['is_hidden'],
            'order_num' => (int)$testCase['order_num']
        ];
        
        $export['test_cases'][] = $testCaseData;
    }
    
    ob_end_clean();
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Export failed: ' . $e->getMessage()]);
}
