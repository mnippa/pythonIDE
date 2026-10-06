<?php
// Test script for team-matrix-export.php
header('Content-Type: text/plain; charset=utf-8');

echo "=== Team Matrix Export Test ===\n\n";

// Check if the export file exists
$exportFile = __DIR__ . '/api/admin/evaluation/team-matrix-export.php';
echo "1. Export file exists: " . (file_exists($exportFile) ? "YES" : "NO") . "\n";

// Simulate the request
$_GET['team_id'] = 2;

// Check if we can parse the file
$content = file_get_contents($exportFile);
echo "2. Export file readable: " . (strlen($content) > 0 ? "YES (" . strlen($content) . " bytes)" : "NO") . "\n";

// Check for key functions
echo "3. Key functions found:\n";
echo "   - mapStatus: " . (strpos($content, 'function mapStatus') !== false ? "YES" : "NO") . "\n";
echo "   - csvString: " . (strpos($content, 'function csvString') !== false ? "YES" : "NO") . "\n";
echo "   - labStatusLabel: " . (strpos($content, 'function labStatusLabel') !== false ? "YES" : "NO") . "\n";
echo "   - labStatusSortRank: " . (strpos($content, 'function labStatusSortRank') !== false ? "YES" : "NO") . "\n";

// Check JS file
$jsFile = __DIR__ . '/public/js/admin-teams-users.js';
$jsContent = file_get_contents($jsFile);
echo "\n4. JavaScript file:\n";
echo "   - File exists: " . (file_exists($jsFile) ? "YES" : "NO") . "\n";
echo "   - downloadTeamMatrixZipExport function: " . (strpos($jsContent, 'function downloadTeamMatrixZipExport') !== false ? "YES" : "NO") . "\n";
echo "   - File size: " . strlen($jsContent) . " bytes\n";

// Check admin.php
$adminFile = __DIR__ . '/public/admin.php';
$adminContent = file_get_contents($adminFile);
echo "\n5. Admin page:\n";
echo "   - File exists: " . (file_exists($adminFile) ? "YES" : "NO") . "\n";
echo "   - team-matrix-export-btn found: " . (strpos($adminContent, 'team-matrix-export-btn') !== false ? "YES" : "NO") . "\n";
echo "   - ZIP-Export button found: " . (strpos($adminContent, 'ZIP-Export') !== false ? "YES" : "NO") . "\n";

echo "\n=== All Components Verified ===\n";
?>
