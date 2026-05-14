<?php
/**
 * Delete support ticket
 * POST /api/support_tickets/delete.php
 * Required: ticket_id or token
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();
    
    $deleteAll = isset($_POST['all']) && $_POST['all'] === '1';
    $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : null;
    $token = isset($_POST['token']) ? trim($_POST['token']) : null;
    
    if ($deleteAll) {
        $stmt = $conn->prepare('DELETE FROM support_tickets');
        if (!$stmt) throw new Exception('Bulk delete prepare failed: ' . $conn->error);
        if (!$stmt->execute()) throw new Exception('Bulk delete failed: ' . $conn->error);
        jsonResponse(['ok' => true, 'deleted' => $conn->affected_rows]);
    }
    
    if (!$ticketId && !$token) {
        jsonResponse(['ok' => false, 'error' => 'ticket_id or token required'], 400);
    }
    
    $sql = 'DELETE FROM support_tickets WHERE ';
    if ($ticketId) {
        $sql .= 'id = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Delete by id prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('i', $ticketId);
    } else {
        $sql .= 'token = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Delete by token prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $token);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Delete failed: ' . $conn->error);
    }
    
    $affectedRows = $conn->affected_rows;
    
    jsonResponse([
        'ok' => true,
        'deleted' => $affectedRows > 0,
        'message' => $affectedRows > 0 ? 'Ticket deleted' : 'Ticket not found'
    ]);
    
} catch (Exception $e) {
    error_log('Support ticket delete error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to delete ticket: ' . $e->getMessage()], 500);
}
