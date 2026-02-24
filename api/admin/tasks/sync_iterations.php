<?php
/**
 * Sync Tasks #147 and #155 to be exactly identical
 * This endpoint ensures imported tasks match their originals exactly
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/auth/middleware.php';

header('Content-Type: application/json');

// Require admin access
$user = requireAdmin();
$pdo = getPdoConnection();

try {
    // Get both tasks
    $stmt = $pdo->query('SELECT id, iterations_count FROM tasks WHERE id IN (147, 155)');
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($tasks) < 2) {
        jsonResponse(['ok' => false, 'error' => 'One or both tasks not found'], 404);
    }
    
    $taskMap = [];
    foreach ($tasks as $task) {
        $taskMap[$task['id']] = $task;
    }
    
    $task147 = $taskMap[147] ?? null;
    $task155 = $taskMap[155] ?? null;
    
    if (!$task147 || !$task155) {
        jsonResponse(['ok' => false, 'error' => 'Tasks not found'], 404);
    }
    
    $before147 = $task147['iterations_count'];
    $before155 = $task155['iterations_count'];
    
    // If #147 has a real value but #155 doesn't, sync it
    if ($task147['iterations_count'] !== null && $task147['iterations_count'] > 0) {
        $updateStmt = $pdo->prepare('UPDATE tasks SET iterations_count = ? WHERE id = 155');
        $updateStmt->execute([$task147['iterations_count']]);
        
        jsonResponse([
            'ok' => true,
            'message' => 'Synced Task #155 iterations_count to match Task #147',
            'before' => [
                'task_147' => $before147,
                'task_155' => $before155
            ],
            'after' => [
                'task_147' => $task147['iterations_count'],
                'task_155' => $task147['iterations_count']
            ]
        ]);
    } elseif ($task147['iterations_count'] === null && $task155['iterations_count'] !== null) {
        // Reverse sync - #147 is null but #155 has a value
        $updateStmt = $pdo->prepare('UPDATE tasks SET iterations_count = ? WHERE id = 147');
        $updateStmt->execute([$task155['iterations_count']]);
        
        jsonResponse([
            'ok' => true,
            'message' => 'Synced Task #147 iterations_count to match Task #155',
            'before' => ['task_147' => $before147, 'task_155' => $before155],
            'after' => ['task_147' => $task155['iterations_count'], 'task_155' => $task155['iterations_count']]
        ]);
    } else {
        jsonResponse([
            'ok' => true,
            'message' => 'Tasks are already synchronized',
            'current' => [
                'task_147' => $task147['iterations_count'],
                'task_155' => $task155['iterations_count']
            ]
        ]);
    }
    
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
