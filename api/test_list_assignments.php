<?php
/**
 * Test list API with admin session
 */

session_start();

// Manually set admin session
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@pythonide.local';
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    $user = ['id' => 1, 'role' => 'admin'];
    $showAll = true;
    
    $sql = '
        SELECT 
            a.id,
            a.title,
            a.description,
            a.created_by,
            a.created_at,
            a.updated_at,
            a.is_active,
            a.difficulty,
            u.first_name,
            u.last_name,
            u.email,
            (SELECT COUNT(*) FROM tasks t WHERE t.assignment_id = a.id) AS task_count,
            ua.status AS user_status
        FROM assignments a
        LEFT JOIN users u ON a.created_by = u.id
        LEFT JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
    ';
    
    $params = [$user['id']];
    $types = 'i';
    
    $sql .= ' ORDER BY a.created_at DESC';
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'created_by' => (int)$row['created_by'],
            'created_by_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'created_by_email' => $row['email'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'is_active' => (bool)$row['is_active'],
            'difficulty' => $row['difficulty'],
            'task_count' => (int)$row['task_count'],
            'user_status' => $row['user_status']
        ];
    }
    
    echo json_encode([
        'ok' => true,
        'assignments' => $assignments,
        'count' => count($assignments)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
