<?php
/**
 * Admin: Team matrix ZIP export
 * GET /api/admin/evaluation/team-matrix-export.php?team_id=X
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

function ensureLabEvaluationTable(mysqli $conn): void {
    $res = $conn->query("SHOW TABLES LIKE 'team_member_lab_evaluations'");
    if ($res instanceof mysqli_result && $res->num_rows > 0) {
        return;
    }

    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS team_member_lab_evaluations (
  user_id INT NOT NULL,
  status ENUM('durchfuehrung','bewertung','bestanden','nachpruefung','nicht_bestanden','nicht_teilgenommen') NOT NULL DEFAULT 'durchfuehrung',
  updated_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_tmlab_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmlab_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed to ensure lab evaluation table: ' . $conn->error);
    }
}

function mapStatus(string $raw, string $submittedAt, bool $late, bool $isRework): string {
    if ($raw === 'passed') return $late ? 'passed_delayed' : 'passed';
    if ($raw === 'rework') return 'rework';
    if ($raw === 'failed') return 'failed';
    if ($submittedAt !== '' && in_array($raw, ['assigned', 'in_progress', 'completed', 'late_completed', 'submitted'], true)) return 'submitted';
    if ($raw === 'submitted') return 'submitted';
    if ($isRework) return 'rework';
    if (in_array($raw, ['in_progress', 'completed', 'late_completed'], true)) return 'in_progress';
    return 'assigned';
}

function csvString(array $header, array $rows): string {
    $fp = fopen('php://temp', 'r+');
    if ($fp === false) {
        throw new RuntimeException('Could not open CSV temp stream');
    }

    fputcsv($fp, $header, ';');
    foreach ($rows as $row) {
        fputcsv($fp, $row, ';');
    }

    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);

    if ($csv === false) {
        throw new RuntimeException('Could not read CSV temp stream');
    }

    return "\xEF\xBB\xBF" . $csv;
}

function labStatusLabel(string $status): string {
    $map = [
        'durchfuehrung' => 'Durchfuehrung',
        'bewertung' => 'Bewertung',
        'bestanden' => 'Bestanden',
        'nachpruefung' => 'Nachtest',
        'nicht_bestanden' => 'Nicht bestanden',
        'nicht_teilgenommen' => 'Nicht teilgenommen',
    ];
    return $map[$status] ?? 'Offen';
}

function labStatusSortRank(string $status): int {
    $rank = [
        'bestanden' => 1,
        'nachpruefung' => 2,
        'nicht_bestanden' => 3,
        'nicht_teilgenommen' => 4,
        'bewertung' => 5,
        'durchfuehrung' => 6,
        'offen' => 7,
    ];
    return $rank[$status] ?? 99;
}

try {
    requireAdmin();

    if (!class_exists('ZipArchive')) {
        jsonResponse(['ok' => false, 'error' => 'ZipArchive is not available on server'], 500);
    }

    $conn = getDbConnection();
    ensureLabEvaluationTable($conn);

    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    if ($teamId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'team_id required'], 400);
    }

    $teamStmt = $conn->prepare('SELECT id, name FROM teams WHERE id = ? LIMIT 1');
    $teamStmt->bind_param('i', $teamId);
    $teamStmt->execute();
    $team = $teamStmt->get_result()->fetch_assoc();

    if (!$team) {
        jsonResponse(['ok' => false, 'error' => 'Team not found'], 404);
    }

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
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
        ];
    }
    $assignmentIds = array_map(static fn(array $a): int => (int)$a['id'], $assignments);

    $uStmt = $conn->prepare(
        'SELECT id, first_name, last_name, email
         FROM users
         WHERE team_id = ?
         ORDER BY last_name, first_name, id'
    );
    $uStmt->bind_param('i', $teamId);
    $uStmt->execute();
    $uResult = $uStmt->get_result();

    $users = [];
    while ($row = $uResult->fetch_assoc()) {
        $uid = (int)$row['id'];
        $users[$uid] = [
            'id' => $uid,
            'first_name' => (string)($row['first_name'] ?? ''),
            'last_name' => (string)($row['last_name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'lab_status' => 'offen',
            'statuses' => [],
        ];
    }

    if (!empty($users)) {
        $userIds = array_keys($users);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));

        $labSql = "SELECT user_id, status FROM team_member_lab_evaluations WHERE user_id IN ($placeholders)";
        $labStmt = $conn->prepare($labSql);
        $labStmt->bind_param($types, ...$userIds);
        $labStmt->execute();
        $labResult = $labStmt->get_result();
        while ($row = $labResult->fetch_assoc()) {
            $uid = (int)$row['user_id'];
            if (isset($users[$uid])) {
                $users[$uid]['lab_status'] = (string)($row['status'] ?? 'offen');
            }
        }
    }

    if (!empty($users) && !empty($assignmentIds)) {
        $userIds = array_keys($users);
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $assignmentPlaceholders = implode(',', array_fill(0, count($assignmentIds), '?'));

        $statusSql = "
            SELECT
                u.id AS user_id,
                a.id AS assignment_id,
                COALESCE(ua_direct.status, ua_team.status, 'assigned') AS raw_status,
                COALESCE(ua_direct.submitted_at, ua_team.submitted_at) AS submitted_at,
                COALESCE(ua_direct.is_late, ua_team.is_late, 0) AS is_late,
                COALESCE(ua_direct.is_rework, ua_team.is_rework, 0) AS is_rework
            FROM users u
            CROSS JOIN assignments a
            LEFT JOIN user_assignments ua_direct
                ON ua_direct.assignment_id = a.id AND ua_direct.user_id = u.id
            LEFT JOIN user_assignments ua_team
                ON ua_team.assignment_id = a.id AND ua_team.team_id = ?
            WHERE u.id IN ($userPlaceholders)
              AND a.id IN ($assignmentPlaceholders)
            ORDER BY u.last_name, u.first_name, u.id, a.id
        ";

        $statusStmt = $conn->prepare($statusSql);
        $bindTypes = 'i' . str_repeat('i', count($userIds)) . str_repeat('i', count($assignmentIds));
        $bindValues = array_merge([$teamId], $userIds, $assignmentIds);
        $statusStmt->bind_param($bindTypes, ...$bindValues);
        $statusStmt->execute();
        $statusResult = $statusStmt->get_result();

        while ($row = $statusResult->fetch_assoc()) {
            $uid = (int)$row['user_id'];
            $aid = (int)$row['assignment_id'];
            if (!isset($users[$uid])) {
                continue;
            }
            $users[$uid]['statuses'][$aid] = mapStatus(
                (string)$row['raw_status'],
                trim((string)($row['submitted_at'] ?? '')),
                !empty($row['is_late']),
                !empty($row['is_rework'])
            );
        }
    }

    $overviewRows = [];
    $detailRows = [];
    $overviewSortedRows = [];
    $emailsBestanden = [];
    $emailsNichtBestanden = [];
    $emailsNachtest = [];

    foreach ($users as $u) {
        $passed = 0;
        $statusCounts = [
            'passed' => 0,
            'passed_delayed' => 0,
            'failed' => 0,
            'rework' => 0,
            'submitted' => 0,
            'in_progress' => 0,
            'assigned' => 0,
        ];
        $assignmentDetails = [];

        foreach ($assignmentIds as $aid) {
            $status = $u['statuses'][$aid] ?? 'assigned';
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            } else {
                $statusCounts['assigned']++;
            }
            if (in_array($status, ['passed', 'passed_delayed'], true)) {
                $passed++;
            }
            $assignmentDetails[] = 'A' . $aid . '=' . $status;
        }

        $total = count($assignmentIds);
        $ratioText = $passed . '(' . $total . ')';
        $labStatus = $u['lab_status'] ?: 'offen';
        $labLabel = labStatusLabel($labStatus);

        $overview = [
            $u['last_name'],
            $u['first_name'],
            $u['email'],
            $ratioText,
            $labLabel,
        ];

        $detail = [
            $u['last_name'],
            $u['first_name'],
            $u['email'],
            $ratioText,
            $labLabel,
            (string)$statusCounts['passed'],
            (string)$statusCounts['passed_delayed'],
            (string)$statusCounts['failed'],
            (string)$statusCounts['rework'],
            (string)$statusCounts['submitted'],
            (string)$statusCounts['in_progress'],
            (string)$statusCounts['assigned'],
            implode(' | ', $assignmentDetails),
        ];

        $overviewRows[] = $overview;
        $detailRows[] = $detail;
        $overviewSortedRows[] = [
            '_status_rank' => labStatusSortRank($labStatus),
            '_last_name' => mb_strtolower($u['last_name']),
            '_first_name' => mb_strtolower($u['first_name']),
            'row' => $overview,
        ];

        if ($labStatus === 'bestanden' && $u['email'] !== '') {
            $emailsBestanden[] = $u['email'];
        } elseif ($labStatus === 'nicht_bestanden' && $u['email'] !== '') {
            $emailsNichtBestanden[] = $u['email'];
        } elseif ($labStatus === 'nachpruefung' && $u['email'] !== '') {
            $emailsNachtest[] = $u['email'];
        }
    }

    usort($overviewSortedRows, static function (array $a, array $b): int {
        if ($a['_status_rank'] !== $b['_status_rank']) {
            return $a['_status_rank'] <=> $b['_status_rank'];
        }
        if ($a['_last_name'] !== $b['_last_name']) {
            return strcmp($a['_last_name'], $b['_last_name']);
        }
        return strcmp($a['_first_name'], $b['_first_name']);
    });

    $overviewSortedCsvRows = array_map(static fn(array $r): array => $r['row'], $overviewSortedRows);

    sort($emailsBestanden, SORT_NATURAL | SORT_FLAG_CASE);
    sort($emailsNichtBestanden, SORT_NATURAL | SORT_FLAG_CASE);
    sort($emailsNachtest, SORT_NATURAL | SORT_FLAG_CASE);

    $overviewCsv = csvString(
        ['Nachname', 'Vorname', 'E-Mail', 'Bestanden(Gesamt)', 'Abschlussbewertung'],
        $overviewRows
    );

    $detailCsv = csvString(
        [
            'Nachname',
            'Vorname',
            'E-Mail',
            'Bestanden(Gesamt)',
            'Abschlussbewertung',
            'Assignments_bestanden',
            'Assignments_bestanden_verspaetet',
            'Assignments_nicht_bestanden',
            'Assignments_nacharbeit',
            'Assignments_eingereicht',
            'Assignments_in_bearbeitung',
            'Assignments_offen',
            'Assignments_details'
        ],
        $detailRows
    );

    $overviewSortedCsv = csvString(
        ['Nachname', 'Vorname', 'E-Mail', 'Bestanden(Gesamt)', 'Abschlussbewertung'],
        $overviewSortedCsvRows
    );

    $zipFileName = 'team_matrix_export_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string)$team['name']) . '_' . date('Ymd_His') . '.zip';
    $tmpZip = tempnam(sys_get_temp_dir(), 'tmx_');
    if ($tmpZip === false) {
        throw new RuntimeException('Could not create temp file for zip export');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create zip archive');
    }

    $zip->addFromString('01_uebersicht.csv', $overviewCsv);
    $zip->addFromString('02_uebersicht_assignment_details.csv', $detailCsv);
    $zip->addFromString('03_uebersicht_sortiert_status_name.csv', $overviewSortedCsv);

    $zip->addFromString('emails_bestanden.txt', implode(';', $emailsBestanden));
    $zip->addFromString('emails_nicht_bestanden.txt', implode(';', $emailsNichtBestanden));
    $zip->addFromString('emails_nachtest.txt', implode(';', $emailsNachtest));

    $zip->close();

    if (!is_file($tmpZip)) {
        throw new RuntimeException('Zip export file missing after creation');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . (string)filesize($tmpZip));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
} catch (Throwable $e) {
    error_log('Team matrix export error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to export team matrix zip'], 500);
}
