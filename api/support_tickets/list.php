<?php
/**
 * List support tickets for admin
 * GET /api/support_tickets/list.php
 * Optional: token (filter by token prefix or exact match)
 * Returns: list of tickets, newest first
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();
    
    $token = isset($_GET['token']) ? trim($_GET['token']) : null;
    
    $sql = '
        SELECT
            st.id,
            st.user_id,
            st.assignment_id,
            st.token,
            st.description,
            st.created_at,
            u.email,
            u.first_name,
            u.last_name,
            a.title AS assignment_title
        FROM support_tickets st
        INNER JOIN users u ON u.id = st.user_id
        INNER JOIN assignments a ON a.id = st.assignment_id
    ';
    
    if ($token) {
        // Support both exact token match and prefix search
        $sql .= ' WHERE st.token LIKE ?';
        $searchToken = strlen($token) < 64 ? $token . '%' : $token;
    } else {
        $searchToken = null;
    }
    
    $sql .= ' ORDER BY st.created_at DESC LIMIT 100';
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('List prepare failed: ' . $conn->error);
    }
    if ($searchToken) {
        $stmt->bind_param('s', $searchToken);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'assignment_id' => (int)$row['assignment_id'],
            'token' => $row['token'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'user_email' => $row['email'],
            'user_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'assignment_title' => $row['assignment_title']
        ];
    }
    
    jsonResponse([
        'ok' => true,
        'tickets' => $tickets,
        'count' => count($tickets)
    ]);
    
} catch (Exception $e) {
    error_log('Support ticket list error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load tickets: ' . $e->getMessage()], 500);
}
