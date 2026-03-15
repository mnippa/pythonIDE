<?php
/**
 * Admin: List all projects
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

$search = trim($_GET['search'] ?? '');
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$teamId = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;
$semester = trim($_GET['semester'] ?? '');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($limit < 1) {
    $limit = 50;
}

if ($limit > 100) {
    $limit = 100;
}

$offset = ($page - 1) * $limit;

$semesterSql = "CASE 
        WHEN MONTH(u.registration_date) >= 3 AND MONTH(u.registration_date) <= 9
        THEN CONCAT('SoSe', YEAR(u.registration_date) % 100)
        WHEN MONTH(u.registration_date) >= 10
        THEN CONCAT('WiSe', YEAR(u.registration_date) % 100, (YEAR(u.registration_date) + 1) % 100)
        ELSE CONCAT('WiSe', (YEAR(u.registration_date) - 1) % 100, YEAR(u.registration_date) % 100)
    END";

if ($search === '' && !$userId && !$teamId && $semester === '') {
    jsonResponse([
        'ok' => true,
        'projects' => [],
        'count' => 0,
        'total' => 0,
        'page' => 1,
        'limit' => $limit,
        'total_pages' => 1,
        'has_prev' => false,
        'has_next' => false,
        'lazy' => true,
        'requires_search' => true
    ]);
}

$fromSql = '
    FROM projects p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN teams t ON t.id = u.team_id
    WHERE 1=1
';

$countSql = 'SELECT COUNT(*) AS total ' . $fromSql;

$sql = '
    SELECT p.id, p.name, p.description, p.visibility, p.created_at, p.updated_at,
           u.id AS user_id, u.email, u.first_name, u.last_name,
           t.name AS team_name,
           ' . $semesterSql . ' AS semester
' . $fromSql;

$params = [];
$types = '';

if ($userId) {
    $countSql .= ' AND p.user_id = ?';
    $sql .= ' AND p.user_id = ?';
    $types .= 'i';
    $params[] = $userId;
}

if ($teamId) {
    $countSql .= ' AND u.team_id = ?';
    $sql .= ' AND u.team_id = ?';
    $types .= 'i';
    $params[] = $teamId;
}

if ($semester !== '') {
    $countSql .= ' AND ' . $semesterSql . ' = ?';
    $sql .= ' AND ' . $semesterSql . ' = ?';
    $types .= 's';
    $params[] = $semester;
}

if ($search !== '') {
    $countSql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE ?)';
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE ?)';
    $searchParam = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int)(($countStmt->get_result()->fetch_assoc()['total'] ?? 0));

$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$sql .= ' ORDER BY p.updated_at DESC LIMIT ? OFFSET ?';

$queryParams = $params;
$queryTypes = $types . 'ii';
$queryParams[] = $limit;
$queryParams[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'visibility' => $row['visibility'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'user_id' => (int)$row['user_id'],
        'user_email' => $row['email'],
        'user_name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'team_name' => $row['team_name'],
        'semester' => $row['semester']
    ];
}

jsonResponse([
    'ok' => true,
    'projects' => $items,
    'count' => count($items),
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => $totalPages,
    'has_prev' => $page > 1,
    'has_next' => $page < $totalPages,
    'lazy' => true,
    'requires_search' => false
]);
