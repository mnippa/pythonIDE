<?php
/**
 * Test the team-matrix-export API endpoint
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== Team Matrix Export API Test ===\n\n";

// Simulate the request
$_GET['team_id'] = 2;
ob_start();

try {
    // Call the API directly
    include(__DIR__ . '/api/admin/evaluation/team-matrix-export.php');
    
    // Get the output (which should be binary ZIP data)
    $output = ob_get_clean();
    
    // Check if output is a valid ZIP
    if (strlen($output) > 0) {
        echo "✓ API generated output: " . strlen($output) . " bytes\n";
        
        // Check for ZIP file signature (PK - 0x504B)
        if (substr($output, 0, 2) === 'PK') {
            echo "✓ Valid ZIP file signature detected\n";
            
            // Save to temp file to verify structure
            $tempFile = sys_get_temp_dir() . '/test_export_' . time() . '.zip';
            file_put_contents($tempFile, $output);
            
            $zip = new ZipArchive();
            if ($zip->open($tempFile) === true) {
                echo "✓ ZIP file is readable\n";
                echo "✓ ZIP contains " . $zip->numFiles . " files:\n";
                
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    echo "  - " . $stat['name'] . " (" . $stat['size'] . " bytes)\n";
                }
                
                $zip->close();
                unlink($tempFile);
                
                echo "\n=== API Test PASSED ===\n";
                echo "✓ All components verified\n";
                echo "✓ ZIP export ready for browser download\n";
            } else {
                echo "✗ ZIP file could not be opened\n";
            }
        } else {
            echo "✗ Invalid ZIP file signature\n";
            echo "First 50 bytes: " . substr($output, 0, 50) . "\n";
        }
    } else {
        echo "✗ API generated no output\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ API Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
?>
