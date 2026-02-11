<?php
/**
 * Admin: List users with team filter and assignment stats
 * GET ?team_id=X&search=name
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

try {
    $user = requireAdmin();
    $conn = getDbConnection();
    
    $teamId = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query with team join
    $sql = "SELECT 
                u.id,
                u.email,
                u.first_name,
                u.last_name,
                u.role,
                u.status,
                u.team_id,
                t.name as team_name,
                u.registration_date,
                u.created_at,
                u.last_login
            FROM users u
            LEFT JOIN teams t ON t.id = u.team_id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($teamId) {
        $sql .= " AND u.team_id = ?";
        $types .= 'i';
        $params[] = $teamId;
    }
    
    if ($search !== '') {
        $sql .= " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $types .= 'sss';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $columnExists = function (mysqli $conn, string $table, string $column): bool {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $check && $check->num_rows > 0;
    };

    $hasTeamId = $columnExists($conn, 'user_assignments', 'team_id');
    $hasStatus = $columnExists($conn, 'user_assignments', 'status');
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $userId = (int)$row['id'];
        $userTeamId = $row['team_id'] ? (int)$row['team_id'] : null;
        
        // Calculate assignment stats based on assignment status
        $stats = [
            'total' => 0,
            'unstarted' => 0,
            'in_progress' => 0,
            'passed' => 0,
            'failed' => 0
        ];
        
        try {
            // Get assignments for this user (via user_id or team_id if available)
            $selectCols = 'assignment_id, user_id';
            if ($hasTeamId) {
                $selectCols .= ', team_id';
            }
            if ($hasStatus) {
                $selectCols .= ', status';
            }

            $assignQuery = "SELECT {$selectCols} FROM user_assignments WHERE ";
            if ($hasTeamId && $userTeamId) {
                $assignQuery .= "user_id = ? OR team_id = ?";
                $assignStmt = $conn->prepare($assignQuery);
                if (!$assignStmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $assignStmt->bind_param('ii', $userId, $userTeamId);
            } else {
                $assignQuery .= "user_id = ?";
                $assignStmt = $conn->prepare($assignQuery);
                if (!$assignStmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $assignStmt->bind_param('i', $userId);
            }
            
            $assignStmt->execute();
            $assignResult = $assignStmt->get_result();
            
            $assignments = [];
            while ($assignRow = $assignResult->fetch_assoc()) {
                $assignmentId = (int)$assignRow['assignment_id'];
                $rowUserId = $assignRow['user_id'] !== null ? (int)$assignRow['user_id'] : null;
                $rowStatus = $hasStatus ? ($assignRow['status'] ?? 'assigned') : 'assigned';
                
                if (!isset($assignments[$assignmentId])) {
                    $assignments[$assignmentId] = [
                        'status' => $rowStatus,
                        'user_id' => $rowUserId
                    ];
                    continue;
                }
                
                // Prefer user-specific assignment over team assignment
                if ($rowUserId !== null && $assignments[$assignmentId]['user_id'] === null) {
                    $assignments[$assignmentId] = [
                        'status' => $rowStatus,
                        'user_id' => $rowUserId
                    ];
                }
            }
            
            foreach ($assignments as $assignment) {
                $stats['total']++;
                $status = $assignment['status'] ?? 'assigned';
                
                switch ($status) {
                    case 'passed':
                        $stats['passed']++;
                        break;
                    case 'failed':
                        $stats['failed']++;
                        break;
                    case 'in_progress':
                    case 'submitted':
                        $stats['in_progress']++;
                        break;
                    case 'assigned':
                    default:
                        $stats['unstarted']++;
                        break;
                }
            }
        } catch (Exception $e) {
            // Stats calculation failed - keep zeros
            error_log("Stats calc failed for user $userId: " . $e->getMessage());
        }
        
        $items[] = [
            'id' => $userId,
            'email' => $row['email'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'role' => $row['role'],
            'status' => $row['status'] ?? 'aktiv',
            'team_id' => $userTeamId,
            'team_name' => $row['team_name'],
            'registration_date' => $row['registration_date'],
            'created_at' => $row['created_at'],
            'last_login' => $row['last_login'],
            'assignment_stats' => $stats
        ];
    }
    
    jsonResponse([
        'ok' => true,
        'users' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    error_log("User list API error: " . $e->getMessage());
    jsonResponse([
        'ok' => false,
        'error' => 'Failed to load users: ' . $e->getMessage()
    ], 500);
}
