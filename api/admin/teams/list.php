<?php
/**
 * Teams List API
 * GET api/admin/teams/list.php
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    $conn = getDbConnection();
    
    // Get all teams with user count
    $sql = "SELECT 
                t.id, 
                t.name, 
                t.description, 
                t.is_active,
                t.created_at,
                COUNT(u.id) as user_count
            FROM teams t
            LEFT JOIN users u ON u.team_id = t.id
            GROUP BY t.id
            ORDER BY t.created_at DESC";
    
    $result = $conn->query($sql);
    $teams = [];
    
    while ($row = $result->fetch_assoc()) {
        $teams[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'user_count' => (int)$row['user_count']
        ];
    }
    
    echo json_encode(['ok' => true, 'teams' => $teams]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
