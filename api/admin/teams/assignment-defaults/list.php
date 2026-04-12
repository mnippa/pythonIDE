<?php
/**
 * Team assignment defaults list
 * GET api/admin/teams/assignment-defaults/list.php?team_id=X
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

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res && $res->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

try {
    $conn = getDbConnection();
    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'team_id required']);
        exit;
    }

    if (!tableExists($conn, 'team_assignment_defaults')) {
        echo json_encode(['ok' => true, 'items' => [], 'count' => 0]);
        exit;
    }

    $hasDueDate = columnExists($conn, 'team_assignment_defaults', 'due_date');

    $sql = '
        SELECT
            tad.id,
            tad.team_id,
            tad.assignment_id,
            tad.created_at,
            tad.is_active,
            ' . ($hasDueDate ? 'tad.due_date AS team_due_date,' : 'NULL AS team_due_date,') . '
            a.title,
            a.is_active AS assignment_is_active,
            a.available_from,
            a.due_date AS assignment_due_date,
            a.hard_deadline,
            a.allow_late_submission,
            a.difficulty,
            (SELECT COUNT(*) FROM tasks t WHERE t.assignment_id = a.id) AS task_count
        FROM team_assignment_defaults tad
        INNER JOIN assignments a ON a.id = tad.assignment_id
        WHERE tad.team_id = ?
        ORDER BY a.title ASC, tad.id ASC
    ';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'team_id' => (int)$row['team_id'],
            'assignment_id' => (int)$row['assignment_id'],
            'title' => $row['title'],
            'difficulty' => $row['difficulty'],
            'task_count' => (int)$row['task_count'],
            'created_at' => $row['created_at'],
            'is_active' => (bool)$row['is_active'],
            'assignment_is_active' => (bool)$row['assignment_is_active'],
            'available_from' => $row['available_from'],
            'assignment_due_date' => $row['assignment_due_date'],
            'team_due_date' => $row['team_due_date'],
            'effective_due_date' => $row['team_due_date'] ?: $row['assignment_due_date'],
            'hard_deadline' => $row['hard_deadline'],
            'allow_late_submission' => isset($row['allow_late_submission']) ? (bool)$row['allow_late_submission'] : true,
        ];
    }

    echo json_encode([
        'ok' => true,
        'items' => $items,
        'count' => count($items),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
