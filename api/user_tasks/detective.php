<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json; charset=utf-8');
$user = requireAuth();
$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}
$input = $method === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}
$taskId = (int)($input['task_id'] ?? 0);
$userId = (int)$user['id'];
$admin = ($user['role'] ?? '') === 'admin';
$adminTest = (string)($input['admin_test'] ?? '0') === '1';
if ($adminTest && !$admin) {
    jsonResponse(['ok' => false, 'error' => 'Admin-Test erfordert Adminrechte.'], 403);
}
$stmt = $conn->prepare(
    'SELECT t.task_type, t.test_cases FROM tasks t
     INNER JOIN assignments a ON a.id = t.assignment_id
     LEFT JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
     WHERE t.id = ? AND (? = 1 OR a.is_active = 1 OR ua.user_id IS NOT NULL)'
);
$isAdmin = $admin ? 1 : 0;
$stmt->bind_param('iii', $userId, $taskId, $isAdmin);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
if (!$task) {
    jsonResponse(['ok' => false, 'error' => 'Aufgabe nicht verfügbar.'], 404);
}
if ($task['task_type'] !== 'code' || !$task['test_cases']) {
    jsonResponse(['ok' => false, 'error' => 'Für diese Aufgabe ist kein Lösungsdetektiv verfügbar.'], 400);
}

$report = null;
$fingerprint = '';
if ($method === 'POST') {
    $fingerprint = (string)($input['fingerprint'] ?? '');
    $report = $input['report'] ?? null;
    if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint)
        || !is_array($report)
        || !isset($report['messages']) || !is_array($report['messages'])
        || count($report['messages']) < 1 || count($report['messages']) > 8) {
        jsonResponse(['ok' => false, 'error' => 'Ungültige Detektiv-Einschätzung.'], 400);
    }
    foreach ($report['messages'] as $message) {
        if (!is_string($message) || strlen($message) > 2000) {
            jsonResponse(['ok' => false, 'error' => 'Ungültige Detektiv-Rückmeldung.'], 400);
        }
    }
    // Store only plain diagnostic text, never submitted source or candidate programs.
    $report = ['messages' => array_values($report['messages'])];
}

if ($adminTest) {
    jsonResponse([
        'ok' => true, 'unlimited' => true, 'remaining' => null, 'reports' => [],
        'report' => $report
    ]);
}

$transaction = false;
try {
    $table = $conn->query("SHOW TABLES LIKE 'solution_detective_reports'");
    if (!$table || $table->num_rows !== 1) {
        error_log('Solution detective: migration_add_solution_detective.sql has not been applied');
        jsonResponse(['ok' => false, 'error' => 'Der Lösungsdetektiv ist noch nicht eingerichtet. Bitte die Lehrperson informieren.'], 503);
    }
    $conn->begin_transaction();
    $transaction = true;
    // Serialize quota decisions even when two tabs submit different reports concurrently.
    $lock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $lock->bind_param('i', $userId);
    $lock->execute();
    $lock->get_result()->fetch_assoc();
    $stmt = $conn->prepare(
        'SELECT fingerprint, report_json, created_at FROM solution_detective_reports
         WHERE user_id = ? AND task_id = ? ORDER BY id'
    );
    $stmt->bind_param('ii', $userId, $taskId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $reports = [];
    $existing = null;
    foreach ($rows as $row) {
        $saved = json_decode($row['report_json'], true, 512, JSON_THROW_ON_ERROR);
        $reports[] = ['fingerprint' => $row['fingerprint'], 'report' => $saved, 'created_at' => $row['created_at']];
        if ($row['fingerprint'] === $fingerprint) {
            $existing = $saved;
        }
    }
    if ($method === 'POST' && $existing === null) {
        if (count($reports) >= 2) {
            $conn->rollback();
            $transaction = false;
            jsonResponse(['ok' => false, 'error' => 'Beide Detektiv-Nutzungen für diese Aufgabe sind verbraucht.'], 409);
        }
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $insert = $conn->prepare(
            'INSERT INTO solution_detective_reports (user_id, task_id, fingerprint, report_json) VALUES (?, ?, ?, ?)'
        );
        $insert->bind_param('iiss', $userId, $taskId, $fingerprint, $json);
        $insert->execute();
        $reports[] = ['fingerprint' => $fingerprint, 'report' => $report, 'created_at' => date('Y-m-d H:i:s')];
    }
    $conn->commit();
    $transaction = false;
    jsonResponse([
        'ok' => true, 'unlimited' => false, 'remaining' => max(0, 2 - count($reports)),
        'reports' => $reports, 'report' => $existing ?? $report
    ]);
} catch (Throwable $e) {
    if ($transaction) $conn->rollback();
    error_log('Solution detective: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Die Detektiv-Nutzung konnte nicht gespeichert werden. Es wurde keine Nutzung abgezogen.'], 500);
}
