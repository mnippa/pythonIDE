<?php
/**
 * Database configuration and connection
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'start123');
define('DB_NAME', 'pythonide');

function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Database connection failed']));
        }
        
        $conn->set_charset('utf8mb4');
    }
    
    return $conn;
}

function getPdoConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'PDO connection failed: ' . $e->getMessage()]));
        }
    }
    
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function dbTableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res && $res->num_rows > 0;
}

function materializeTeamAssignmentsForUser(mysqli $conn, int $userId, ?int $teamId, ?int $assignedBy = null): int {
    $teamId = (int)($teamId ?? 0);
    if ($userId <= 0 || $teamId <= 0) {
        return 0;
    }

    if (!dbTableExists($conn, 'team_assignment_defaults') || !dbTableExists($conn, 'user_assignments')) {
        return 0;
    }

    $assignedBy = (int)($assignedBy ?? $userId);

    $stmt = $conn->prepare(
        'INSERT INTO user_assignments (assignment_id, user_id, assigned_by, status, due_date)
         SELECT tad.assignment_id, ?, ?, "assigned", COALESCE(tad.due_date, a.due_date)
         FROM team_assignment_defaults tad
         INNER JOIN assignments a ON a.id = tad.assignment_id
         WHERE tad.team_id = ?
           AND tad.is_active = 1
           AND a.is_active = 1
           AND NOT EXISTS (
               SELECT 1
               FROM user_assignments ua
               WHERE ua.assignment_id = tad.assignment_id
                 AND ua.user_id = ?
           )'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('iiii', $userId, $assignedBy, $teamId, $userId);
    if (!$stmt->execute()) {
        return 0;
    }

    return (int)$stmt->affected_rows;
}
