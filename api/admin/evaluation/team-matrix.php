<?php
/**
 * Admin: Cross-assignment status matrix for users in a team
 * GET /api/admin/evaluation/team-matrix.php?team_id=X
 * Returns team members with their status per assignment + summary.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();

    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    if ($teamId <= 0) {
        jsonResponse(['ok' => true, 'assignments' => [], 'users' => []]);
    }

    // 1. Only assignments explicitly assigned to this team.
    $aStmt = $conn->prepare(
        'SELECT a.id, a.title
         FROM assignments a
         WHERE a.id IN (
             SELECT tad.assignment_id
             FROM team_assignment_defaults tad
             WHERE tad.team_id = ?
         )
         ORDER BY a.id'
    );
    $aStmt->bind_param('i', $teamId);
    $aStmt->execute();
    $aResult = $aStmt->get_result();
    $assignments = [];
    while ($row = $aResult->fetch_assoc()) {
        $assignments[] = [
            'id'    => (int)$row['id'],
            'title' => $row['title'],
            'short' => shortenTitle($row['title']),
        ];
    }

    if (empty($assignments)) {
        jsonResponse(['ok' => true, 'assignments' => [], 'users' => []]);
    }

    // 2. All users in this team × assignments explicitly assigned to this team.
    $sql = '
        SELECT
            u.id AS user_id,
            u.first_name,
            u.last_name,
            u.email,
            a.id AS assignment_id,
            COALESCE(ua_direct.status, ua_team.status, \'assigned\') AS raw_status,
            COALESCE(ua_direct.is_late, ua_team.is_late, 0) AS is_late,
            COALESCE(ua_direct.is_rework, ua_team.is_rework, 0) AS is_rework
        FROM (
            SELECT id, first_name, last_name, email FROM users WHERE team_id = ?
        ) u
        CROSS JOIN (
            SELECT a.id, a.title FROM assignments a
            WHERE a.id IN (
                SELECT tad.assignment_id FROM team_assignment_defaults tad WHERE tad.team_id = ?
            )
        ) a
        LEFT JOIN user_assignments ua_direct
            ON ua_direct.assignment_id = a.id AND ua_direct.user_id = u.id
        LEFT JOIN user_assignments ua_team
            ON ua_team.assignment_id = a.id AND ua_team.team_id = ?
        ORDER BY u.last_name, u.first_name, u.id, a.id
    ';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iii', $teamId, $teamId, $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. Aggregate per user with auto-detection of is_late
    $userMap = [];
    while ($row = $result->fetch_assoc()) {
        $uid = (int)$row['user_id'];
        if (!isset($userMap[$uid])) {
            $userMap[$uid] = [
                'id'        => $uid,
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
                'email'      => $row['email'],
                'statuses'   => [],
            ];
        }
        $aid = (int)$row['assignment_id'];
        $raw = (string)$row['raw_status'];
        // is_late is now correctly set by migration 054 backfill
        $isLate = !empty($row['is_late']);
        $rework = !empty($row['is_rework']);
        $userMap[$uid]['statuses'][$aid] = [
            'status' => mapStatus($raw, $isLate, $rework),
            'is_late' => $isLate,
            'is_rework' => $rework,
        ];
    }

    // 4. Compute summary and convert to list
    $assignmentIds = array_column($assignments, 'id');
    $users = array_values($userMap);
    foreach ($users as &$u) {
        $passed = 0;
        $total  = 0;
        foreach ($assignmentIds as $aid) {
            if (isset($u['statuses'][$aid])) {
                $total++;
                $statusObj = $u['statuses'][$aid];
                $statusStr = is_array($statusObj) ? $statusObj['status'] : $statusObj;
                if (in_array($statusStr, ['passed', 'passed_delayed'], true)) {
                    $passed++;
                }
            }
        }
        $u['passed'] = $passed;
        $u['total']  = $total;
    }
    unset($u);

    jsonResponse(['ok' => true, 'assignments' => $assignments, 'users' => $users]);

} catch (Exception $e) {
    error_log('Team matrix error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load team matrix'], 500);
}

function mapStatus(string $raw, bool $late, bool $isRework): string {
    if ($isRework) return 'rework';
    if ($raw === 'passed') return $late ? 'passed_delayed' : 'passed';
    if ($raw === 'rework') return 'rework';
    if ($raw === 'failed') return 'failed';
    if (in_array($raw, ['submitted', 'in_progress', 'completed', 'late_completed'], true)) return 'in_progress';
    return 'assigned';
}

function shortenTitle(string $title): string {
    $words = preg_split('/\s+/', trim($title));
    if (count($words) === 0) return $title;

    $first = $words[0];
    if (mb_strlen($first) > 10) {
        return mb_substr($first, 0, 10) . '.';
    }

    $abbr = $first;
    for ($i = 1; $i < count($words); $i++) {
        $w = $words[$i];
        if (mb_strlen($w) > 2) {
            $abbr .= ' ' . mb_strtoupper(mb_substr($w, 0, 1)) . '.';
        }
    }
    return $abbr;
}
