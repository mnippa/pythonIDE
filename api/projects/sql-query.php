<?php
/**
 * Execute ad-hoc SQL queries for DB designer against an isolated in-memory SQLite database.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!class_exists('SQLite3')) {
    jsonResponse(['ok' => false, 'error' => 'SQLite3 extension is not available on server'], 500);
}

$user = requireAuth();
$conn = getDbConnection();
$input = json_decode(file_get_contents('php://input'), true);

$projectId = (int)($input['project_id'] ?? 0);
$query = trim((string)($input['query'] ?? ''));
$setupSql = (string)($input['setup_sql'] ?? '');

if ($projectId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Project ID required'], 400);
}
if ($query === '') {
    jsonResponse(['ok' => false, 'error' => 'SQL query required'], 400);
}
if (strlen($query) > 50000 || strlen($setupSql) > 1000000) {
    jsonResponse(['ok' => false, 'error' => 'SQL payload too large'], 413);
}

$projectStmt = $conn->prepare('SELECT id, user_id FROM projects WHERE id = ? LIMIT 1');
$projectStmt->bind_param('i', $projectId);
$projectStmt->execute();
$project = $projectStmt->get_result()->fetch_assoc();
$projectStmt->close();

if (!$project) {
    jsonResponse(['ok' => false, 'error' => 'Project not found'], 404);
}

$isOwner = ((int)$project['user_id'] === (int)$user['id']);
$isAdmin = (($user['role'] ?? '') === 'admin');
if (!$isOwner && !$isAdmin) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

$db = null;

try {
    $db = new SQLite3(':memory:');
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');

    if ($setupSql !== '') {
      if (!$db->exec($setupSql)) {
        throw new RuntimeException('Setup SQL failed: ' . $db->lastErrorMsg());
      }
    }

    $queryStartsWithResultSet = preg_match('/^\s*(SELECT|WITH|PRAGMA|EXPLAIN)\b/i', $query) === 1;

    if ($queryStartsWithResultSet) {
        $result = $db->query($query);
        if ($result === false) {
            throw new RuntimeException($db->lastErrorMsg());
        }

        $rows = [];
        $columns = [];

        $firstRow = $result->fetchArray(SQLITE3_ASSOC);
        if (is_array($firstRow)) {
            $columns = array_keys($firstRow);
            $rows[] = $firstRow;
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $rows[] = $row;
            }
        }

        jsonResponse([
            'ok' => true,
            'mode' => 'result-set',
            'row_count' => count($rows),
            'columns' => $columns,
            'rows' => $rows,
            'message' => 'SQL erfolgreich ausgefuehrt'
        ]);
    }

    $execOk = $db->exec($query);
    if (!$execOk) {
        throw new RuntimeException($db->lastErrorMsg());
    }

    jsonResponse([
        'ok' => true,
        'mode' => 'command',
        'row_count' => (int)$db->changes(),
        'columns' => [],
        'rows' => [],
        'message' => 'SQL-Befehl erfolgreich ausgefuehrt'
    ]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
} finally {
    if ($db instanceof SQLite3) {
        $db->close();
    }
}
