<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/tasks/task-code-audit.php';

$conn = getDbConnection();
taskAuditEnsureTable($conn);

$displayName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = $_SESSION['email'] ?? 'Admin';
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$taskId = isset($_GET['task_id']) ? max(0, (int)$_GET['task_id']) : 0;
$field = isset($_GET['field']) ? trim((string)$_GET['field']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 500) {
    $limit = 500;
}

$allowedFields = ['code_template', 'solution_code', 'template_file', 'solution_file'];
if ($field !== '' && !in_array($field, $allowedFields, true)) {
    $field = '';
}

$where = [];
$params = [];
$types = '';

if ($taskId > 0) {
    $where[] = 'task_id = ?';
    $params[] = $taskId;
    $types .= 'i';
}

if ($field !== '') {
    $where[] = 'field_name = ?';
    $params[] = $field;
    $types .= 's';
}

$sql = 'SELECT id, task_id, field_name, file_path, change_type, old_sha256, new_sha256, old_length, new_length, actor_user_id, actor_role, actor_email, source_endpoint, source_ip, created_at
        FROM task_code_audit_log';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT ?';

$params[] = $limit;
$types .= 'i';

$rows = [];
$errorMsg = null;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $errorMsg = 'Query prepare failed: ' . $conn->error;
} else {
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $errorMsg = 'Query execute failed: ' . $stmt->error;
    } else {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Python IDE - Task Audit</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <link rel="stylesheet" href="css/admin-compat.css">
  <style>
    body { padding: 0; min-height: 100vh; }
    .page-container { max-width: 1500px; margin: 0 auto; padding: var(--hspf-spacing-lg); }
    .card {
      background: var(--hspf-surface);
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius-md);
      padding: var(--hspf-spacing-lg);
      box-shadow: var(--hspf-shadow);
      margin-bottom: var(--hspf-spacing-lg);
    }
    .filters {
      display: grid;
      grid-template-columns: 140px 220px 120px auto;
      gap: 10px;
      align-items: end;
    }
    .filters label { font-size: 12px; color: var(--hspf-text-secondary); display: block; margin-bottom: 4px; }
    .filters input, .filters select {
      width: 100%;
      padding: 8px;
      border: 1px solid var(--hspf-border);
      border-radius: 8px;
      background: var(--hspf-bg-primary);
      color: var(--hspf-text-primary);
    }
    .actions { display: flex; gap: 8px; justify-content: flex-end; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--hspf-border); vertical-align: top; }
    th { font-weight: 600; color: var(--hspf-text-secondary); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
  </style>
</head>
<body>
<?php
$pageTitle = 'Task-Audit (Template/Lösung)';
$showUser = true;
$userInfo = [
    'name' => $displayName,
    'role' => 'admin'
];
$headerActions = '
  <a class="hspf-btn hspf-btn-ghost" href="admin.php">← Admin</a>
  <a class="hspf-btn hspf-btn-ghost" href="dashboard.php">Dashboard</a>
  <button class="hspf-btn hspf-btn-ghost" id="logout-btn">Logout</button>
';
include(__DIR__ . '/../components/header.php');
?>

<div class="page-container">
  <div class="card">
    <h2 style="margin-top:0;">Letzte Task-Änderungen</h2>
    <form method="get" class="filters">
      <div>
        <label for="task_id">Task-ID</label>
        <input id="task_id" name="task_id" type="number" min="0" value="<?php echo h($taskId ?: ''); ?>">
      </div>
      <div>
        <label for="field">Feld</label>
        <select id="field" name="field">
          <option value="">Alle</option>
          <?php foreach ($allowedFields as $f): ?>
            <option value="<?php echo h($f); ?>" <?php echo $field === $f ? 'selected' : ''; ?>><?php echo h($f); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="limit">Limit</label>
        <input id="limit" name="limit" type="number" min="1" max="500" value="<?php echo h($limit); ?>">
      </div>
      <div class="actions">
        <button class="hspf-btn hspf-btn-primary" type="submit">Filtern</button>
        <a class="hspf-btn" href="admin_task_audit.php">Reset</a>
      </div>
    </form>
  </div>

  <div class="card">
    <?php if ($errorMsg): ?>
      <div style="color:#b91c1c; margin-bottom:10px;"><?php echo h($errorMsg); ?></div>
    <?php endif; ?>
    <div style="margin-bottom:10px; color: var(--hspf-text-secondary);">
      <?php echo h(count($rows)); ?> Einträge geladen.
    </div>
    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Zeit</th>
            <th>Task</th>
            <th>Feld</th>
            <th>Datei</th>
            <th>Typ</th>
            <th>Actor</th>
            <th>Delta</th>
            <th>Quelle</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="color: var(--hspf-text-secondary);">Keine Einträge gefunden.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="mono"><?php echo h($r['created_at']); ?></td>
                <td><?php echo h($r['task_id']); ?></td>
                <td class="mono"><?php echo h($r['field_name']); ?></td>
                <td class="mono"><?php echo h($r['file_path'] ?? ''); ?></td>
                <td class="mono"><?php echo h($r['change_type']); ?></td>
                <td>
                  <?php echo h($r['actor_email'] ?? ''); ?><br>
                  <span class="mono">id=<?php echo h($r['actor_user_id'] ?? ''); ?> role=<?php echo h($r['actor_role'] ?? ''); ?></span>
                </td>
                <td class="mono">
                  <?php echo h((string)($r['old_length'] ?? 'null')); ?> → <?php echo h((string)($r['new_length'] ?? 'null')); ?><br>
                  <?php echo h(substr((string)($r['old_sha256'] ?? ''), 0, 12)); ?> → <?php echo h(substr((string)($r['new_sha256'] ?? ''), 0, 12)); ?>
                </td>
                <td class="mono">
                  <?php echo h($r['source_endpoint'] ?? ''); ?><br>
                  <?php echo h($r['source_ip'] ?? ''); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="js/auth.js"></script>
</body>
</html>
