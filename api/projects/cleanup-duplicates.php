<?php
/**
 * Cleanup Script: Remove duplicate files in project_files table
 * Keeps only the oldest entry (lowest ID) for each duplicate
 * 
 * Run once via: http://localhost/pythonIDE/api/projects/cleanup-duplicates.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();

// Only allow admins to run cleanup
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

$conn = getDbConnection();

try {
    // Find duplicates grouped by project_id, folder_id, and name
    // We want to keep the oldest (MIN(id)) and delete the rest
    
    $findDuplicatesQuery = "
        SELECT 
            project_id, 
            COALESCE(folder_id, 0) as folder_id_norm, 
            name, 
            COUNT(*) as count,
            MIN(id) as keep_id,
            GROUP_CONCAT(id ORDER BY id) as all_ids
        FROM project_files
        GROUP BY project_id, COALESCE(folder_id, 0), name
        HAVING count > 1
    ";
    
    $result = $conn->query($findDuplicatesQuery);
    
    $duplicatesFound = [];
    $deletedCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $allIds = explode(',', $row['all_ids']);
        $keepId = (int)$row['keep_id'];
        $toDelete = array_filter($allIds, function($id) use ($keepId) {
            return (int)$id !== $keepId;
        });
        
        if (count($toDelete) > 0) {
            $duplicatesFound[] = [
                'project_id' => $row['project_id'],
                'folder_id' => $row['folder_id_norm'] == 0 ? null : $row['folder_id_norm'],
                'name' => $row['name'],
                'total_count' => $row['count'],
                'keeping_id' => $keepId,
                'deleting_ids' => array_map('intval', $toDelete)
            ];
            
            // Delete duplicates
            foreach ($toDelete as $deleteId) {
                $stmt = $conn->prepare('DELETE FROM project_files WHERE id = ?');
                $stmt->bind_param('i', $deleteId);
                if ($stmt->execute()) {
                    $deletedCount++;
                }
            }
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'duplicates_found' => count($duplicatesFound),
        'files_deleted' => $deletedCount,
        'details' => $duplicatesFound
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
