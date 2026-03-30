<?php
/**
 * List Assignments API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$showAll = isset($_GET['all']) && $_GET['all'] === '1';

$columnExists = function (mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $check && $check->num_rows > 0;
};

$hasUserTeamId = $columnExists($conn, 'users', 'team_id');
$hasAssignmentTeamId = $columnExists($conn, 'user_assignments', 'team_id');

$directCountSql = '(SELECT COUNT(DISTINCT ua1.user_id)
            FROM user_assignments ua1
            WHERE ua1.assignment_id = a.id AND ua1.user_id IS NOT NULL)';

$userCountSql = $directCountSql . ' AS user_count';

if ($hasUserTeamId && $hasAssignmentTeamId) {
    $teamCountSql = '(SELECT COUNT(DISTINCT u2.id)
            FROM user_assignments ua2
            INNER JOIN users u2 ON u2.team_id = ua2.team_id
            WHERE ua2.assignment_id = a.id
              AND ua2.team_id IS NOT NULL
              AND u2.id NOT IN (
                  SELECT ua3.user_id
                  FROM user_assignments ua3
                  WHERE ua3.assignment_id = a.id AND ua3.user_id IS NOT NULL
              ))';

    $userCountSql = '(' . $directCountSql . ' + ' . $teamCountSql . ') AS user_count';
}

// LOGIC:
// - Without ?all=1 (normal dashboard): Show assignments the user is assigned to (via user_assignments)
//   This applies to ALL users, including admins - on the dashboard, admins are participants too
// - With ?all=1 (admin management): Show ALL assignments for admins
//   This enables global admin management across all creators

if ($showAll && $user['role'] === 'admin') {
    // Admin Management: Show all assignments
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
            ' . $userCountSql . ',
            NULL AS user_status
        FROM assignments a
        LEFT JOIN users u ON a.created_by = u.id
        ORDER BY a.created_at DESC
    ';
    $params = [];
    $types = '';
} else {
    // Participant View (normal dashboard): Show assignments the user is assigned to
    // This is the SAME for admins and regular users
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
            ' . $userCountSql . ',
            ua.status AS user_status
        FROM assignments a
        LEFT JOIN users u ON a.created_by = u.id
        JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
        WHERE a.is_active = 1
        ORDER BY a.created_at DESC
    ';
    $params = [$user['id']];
    $types = 'i';
}

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    error_log('Assignments list error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load assignments'], 500);
}

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
        'user_count' => (int)$row['user_count'],
        'user_status' => $row['user_status']
    ];
}

jsonResponse([
    'ok' => true,
    'assignments' => $assignments,
    'count' => count($assignments)
]);
