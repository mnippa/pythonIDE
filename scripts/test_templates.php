<?php
/**
 * Test Template System
 * Verifies that all templates can be loaded correctly
 */

require_once __DIR__ . '/../api/projects/templates.php';

echo "=== Template System Test ===\n\n";

$templates = [
    'empty_python',
    'empty_python_html',
    'python_logic',
    'event_logic',
    'kniffel_demo',
    'blackjack_demo'
];

foreach ($templates as $templateName) {
    echo "Testing template: $templateName\n";
    
    try {
        $template = ProjectTemplates::getTemplate($templateName);
        
        // Check structure
        if (!isset($template['project_type'])) {
            echo "  ❌ Missing project_type\n";
            continue;
        }
        
        if (!isset($template['files']) || !is_array($template['files'])) {
            echo "  ❌ Missing or invalid files array\n";
            continue;
        }
        
        // Check files
        $fileCount = count($template['files']);
        echo "  ✓ Project type: {$template['project_type']}\n";
        echo "  ✓ Files: $fileCount\n";
        
        foreach ($template['files'] as $fileName => $fileData) {
            $size = strlen($fileData['content']);
            $mime = $fileData['mime_type'];
            echo "    - $fileName ($mime, {$size} bytes)\n";
        }
        
        echo "  ✅ Template OK\n\n";
        
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Test Complete ===\n";
