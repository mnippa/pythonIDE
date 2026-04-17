<?php
/**
 * Bulk Assign Users/Team to Assignment
 * POST api/admin/assignments/bulk-assign.php
 * Body: {
 *   "assignment_id": 1,
 *   "team_id": 2,           // ODER: schreibt Team-Default + materialisiert aktuelle Team-Mitglieder
 *   "user_ids": [1,2,3],    // Array von User IDs
 *   "due_date": "2025-12-31 23:59:59" // Optional
 * }
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    $conn = getDbConnection();

    $normalizeDateTimeInput = static function ($value): ?string {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    };

    $tableExists = function (mysqli $conn, string $table): bool {
        $safeTable = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        return $res && $res->num_rows > 0;
    };

    $sessionRole = $_SESSION['role'] ?? null;
    $assignedBy = (int)$_SESSION['user_id'];

    // Fallback: validate role from DB to avoid false 403 when session role is stale/missing
    if ($sessionRole !== 'admin') {
        $roleStmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $roleStmt->bind_param('i', $assignedBy);
        $roleStmt->execute();
        $roleRow = $roleStmt->get_result()->fetch_assoc();
        $dbRole = $roleRow['role'] ?? 'user';

        if ($dbRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Admin access required']);
            exit;
        }

        // Heal session for subsequent requests
        $_SESSION['role'] = 'admin';
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['assignment_id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Assignment ID required']);
        exit;
    }
    
    $assignmentId = (int)$data['assignment_id'];
    $dueDate = $data['due_date'] ?? null;

    $assignmentStmt = $conn->prepare('SELECT id, is_active, available_from, due_date, hard_deadline, allow_late_submission FROM assignments WHERE id = ?');
    $assignmentStmt->bind_param('i', $assignmentId);
    $assignmentStmt->execute();
    $assignment = $assignmentStmt->get_result()->fetch_assoc();
    if (!$assignment) {
        throw new Exception('Assignment not found');
    }
    if ((int)$assignment['is_active'] !== 1) {
        throw new Exception('Assignment is inactive and cannot be assigned in bulk');
    }

    $assignmentAvailableFrom = array_key_exists('assignment_available_from', $data)
        ? $normalizeDateTimeInput($data['assignment_available_from'])
        : ($assignment['available_from'] ?? null);
    $assignmentDueDate = array_key_exists('assignment_due_date', $data)
        ? $normalizeDateTimeInput($data['assignment_due_date'])
        : ($assignment['due_date'] ?? null);
    $assignmentHardDeadline = array_key_exists('assignment_hard_deadline', $data)
        ? $normalizeDateTimeInput($data['assignment_hard_deadline'])
        : ($assignment['hard_deadline'] ?? null);
    $assignmentAllowLate = array_key_exists('assignment_allow_late_submission', $data)
        ? ((int)!empty($data['assignment_allow_late_submission']))
        : (int)($assignment['allow_late_submission'] ?? 1);

    if ($assignmentAvailableFrom !== null && $assignmentDueDate !== null && strtotime($assignmentDueDate) < strtotime($assignmentAvailableFrom)) {
        throw new Exception('assignment_due_date must be on/after assignment_available_from');
    }
    if ($assignmentDueDate !== null && $assignmentHardDeadline !== null && strtotime($assignmentHardDeadline) < strtotime($assignmentDueDate)) {
        throw new Exception('assignment_hard_deadline must be on/after assignment_due_date');
    }
    
    $conn->begin_transaction();
    
    $assignedCount = 0;
    $materializedCount = 0;
    $skippedCount = 0;
    
    // Option 1: Assign to entire team
    if (isset($data['team_id']) && $data['team_id']) {
        $teamId = (int)$data['team_id'];

        if (
            array_key_exists('assignment_available_from', $data)
            || array_key_exists('assignment_due_date', $data)
            || array_key_exists('assignment_hard_deadline', $data)
            || array_key_exists('assignment_allow_late_submission', $data)
        ) {
            $updateAssignmentStmt = $conn->prepare(
                'UPDATE assignments
                 SET available_from = ?, due_date = ?, hard_deadline = ?, allow_late_submission = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $updateAssignmentStmt->bind_param(
                'sssii',
                $assignmentAvailableFrom,
                $assignmentDueDate,
                $assignmentHardDeadline,
                $assignmentAllowLate,
                $assignmentId
            );
            $updateAssignmentStmt->execute();
        }

        $effectiveDueDate = $dueDate ?: $assignmentDueDate;

        if ($tableExists($conn, 'team_assignment_defaults')) {
            $defaultsStmt = $conn->prepare(
                'INSERT INTO team_assignment_defaults (team_id, assignment_id, assigned_by, due_date, is_active)
                 VALUES (?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), due_date = VALUES(due_date), is_active = VALUES(is_active)'
            );
            $defaultsStmt->bind_param('iiis', $teamId, $assignmentId, $assignedBy, $effectiveDueDate);
            $defaultsStmt->execute();
            $assignedCount = 1;

            $materializeStmt = $conn->prepare(
                'INSERT IGNORE INTO user_assignments (assignment_id, user_id, assigned_by, due_date, status)
                 SELECT ?, u.id, ?, ?, "assigned"
                 FROM users u
                 WHERE u.team_id = ?'
            );
            $materializeStmt->bind_param('iisi', $assignmentId, $assignedBy, $effectiveDueDate, $teamId);
            $materializeStmt->execute();
            $materializedCount = (int)$materializeStmt->affected_rows;

            $updateExistingStmt = $conn->prepare(
                'UPDATE user_assignments ua
                 INNER JOIN users u ON u.id = ua.user_id
                 SET ua.due_date = ?
                 WHERE ua.assignment_id = ? AND u.team_id = ?'
            );
            $updateExistingStmt->bind_param('sii', $effectiveDueDate, $assignmentId, $teamId);
            $updateExistingStmt->execute();
        } else {
            throw new Exception('team_assignment_defaults table missing. Run migration 026 first.');
        }
    }
    // Option 2: Assign to individual users
    elseif (isset($data['user_ids']) && is_array($data['user_ids'])) {
        $effectiveDueDate = $dueDate ?: ($assignment['due_date'] ?? null);
        $stmt = $conn->prepare('INSERT IGNORE INTO user_assignments (assignment_id, user_id, assigned_by, due_date) VALUES (?, ?, ?, ?)');

        $normalizedUserIds = array_values(array_unique(array_map('intval', $data['user_ids'])));
        foreach ($normalizedUserIds as $userId) {
            $userId = (int)$userId;
            if ($userId <= 0) {
                $skippedCount++;
                continue;
            }
            $stmt->bind_param('iiis', $assignmentId, $userId, $assignedBy, $effectiveDueDate);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $assignedCount++;
            } else {
                $skippedCount++;
            }
        }
    } else {
        throw new Exception('Either team_id or user_ids[] required');
    }
    
    $conn->commit();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Assignment assigned successfully',
        'assigned_count' => $assignedCount,
        'materialized_count' => $materializedCount,
        'skipped_count' => $skippedCount
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
