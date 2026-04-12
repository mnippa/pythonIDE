<?php
/**
 * Delete a team assignment default.
 * POST api/admin/teams/assignment-defaults/delete.php
 * Body: { "team_id": 1, "assignment_id": 2 }
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

require_once __DIR__ . '/../../../../config/database.php';

try {
    $conn = getDbConnection();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $teamId = isset($input['team_id']) ? (int)$input['team_id'] : 0;
    $assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;

    if ($teamId <= 0 || $assignmentId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'team_id and assignment_id required']);
        exit;
    }

    $stmt = $conn->prepare('DELETE FROM team_assignment_defaults WHERE team_id = ? AND assignment_id = ?');
    $stmt->bind_param('ii', $teamId, $assignmentId);
    $stmt->execute();

    echo json_encode([
        'ok' => true,
        'deleted' => $stmt->affected_rows > 0,
        'affected_rows' => $stmt->affected_rows,
        'note' => 'Existing user assignments remain unchanged; only the team default for future assignments was removed.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
