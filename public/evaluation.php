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

$displayName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($displayName === '') {
  $displayName = $_SESSION['email'] ?? 'Admin';
}

$pageTitle = 'Evaluation';
$showUser = true;
$userInfo = [
  'name' => $displayName,
  'role' => $_SESSION['role'] ?? 'admin'
];
$headerActions = '<button class="hspf-btn hspf-btn-ghost" id="back-to-admin">Back to Admin</button>';
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Python IDE - Evaluation</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <link rel="stylesheet" href="css/admin-compat.css">
  <style>
    body {
      padding: 0;
      min-height: 100vh;
    }

    .evaluation-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: var(--hspf-spacing-lg);
      min-height: calc(100vh - 80px);
      display: flex;
      flex-direction: column;
      gap: var(--hspf-spacing-lg);
    }

    .evaluation-toolbar {
      display: flex;
      gap: var(--hspf-spacing-lg);
      align-items: center;
      flex-wrap: wrap;
    }

    .evaluation-toolbar.evaluation-card {
      padding: var(--hspf-spacing-md);
    }

    .evaluation-toolbar .field {
      margin: 0;
    }

    .evaluation-toolbar select {
      min-height: 38px;
    }

    #assignment-title {
      line-height: 1.2;
      margin: 0;
    }

    .tabs {
      display: flex;
      gap: var(--hspf-spacing-sm);
      border-bottom: 2px solid var(--hspf-border);
    }

    .tab {
      padding: 12px 20px;
      border: none;
      background: transparent;
      cursor: pointer;
      font-weight: 250;
      font-size: 15px;
      color: var(--hspf-text-secondary);
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      transition: var(--hspf-transition);
    }

    .tab:hover {
      color: var(--hspf-text-primary);
      background-color: var(--hspf-bg-secondary);
    }

    .tab.active {
      color: var(--hspf-text-primary);
      border-bottom-color: var(--hspf-accent);
      font-weight: 600;
    }

    .panel {
      display: none;
    }

    .panel.active {
      display: block;
    }

    .evaluation-card {
      background: var(--hspf-surface);
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius-md);
      padding: var(--hspf-spacing-lg);
      box-shadow: var(--hspf-shadow);
    }

    .evaluation-card h2, .evaluation-card h3 {
      margin: 0 0 var(--hspf-spacing-sm);
      color: var(--hspf-text-primary);
      font-weight: 300;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: var(--hspf-spacing-md);
    }

    .stat-card {
      border: 1px solid var(--hspf-border);
      border-radius: var(--hspf-radius);
      padding: var(--hspf-spacing-md);
      background: var(--hspf-bg-secondary);
    }

    .stat-label {
      font-size: 13px;
      color: var(--hspf-text-secondary);
    }

    .stat-value {
      font-size: 24px;
      font-weight: 600;
      color: var(--hspf-text-primary);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      margin-top: var(--hspf-spacing-md);
    }

    th, td {
      text-align: left;
      padding: 6px 8px;
      border-bottom: 1px solid var(--hspf-border);
      vertical-align: middle;
    }

    th {
      color: var(--hspf-text-secondary);
      font-weight: 600;
      background-color: var(--hspf-bg-secondary);
    }

    tbody tr:nth-child(even) {
      background-color: var(--hspf-gray-50);
    }

    tbody tr:hover {
      background-color: var(--hspf-gray-100);
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 6px;
    }

    .status-unstarted { background: #d1d5db; }
    .status-in-progress { background: #facc15; }
    .status-completed { background: #38bdf8; }
    .status-late-completed { background: #f59e0b; }
    .status-passed { background: #22c55e; }
    .status-passed-delayed { background: #10b981; }
    .status-failed { background: #ef4444; }
    .status-missed { background: #f97316; }

    .status-bar {
      display: flex;
      height: 12px;
      border-radius: 999px;
      overflow: hidden;
      background: #e5e7eb;
    }

    .status-seg-unstarted { background: #d1d5db; }
    .status-seg-in-progress { background: #facc15; }
    .status-seg-passed { background: #22c55e; }
    .status-seg-failed { background: #ef4444; }

    .status-legend {
      display: flex;
      gap: 12px;
      margin-top: 6px;
      font-size: 12px;
      color: var(--hspf-text-secondary);
      flex-wrap: wrap;
    }

    .modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }

    .modal.active {
      display: flex;
    }

    .modal-content {
      background: var(--hspf-surface);
      border-radius: var(--hspf-radius-md);
      padding: var(--hspf-spacing-lg);
      max-width: 900px;
      width: 90%;
      max-height: 85vh;
      overflow: auto;
      border: 2px solid var(--hspf-border);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--hspf-spacing-md);
    }

    .mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    .num-right {
      text-align: right;
    }
  </style>
</head>
<body>
  <?php include(__DIR__ . '/../components/header.php'); ?>
  <div class="evaluation-container">
    <div class="evaluation-toolbar evaluation-card">
      <div class="field" style="min-width: 260px;">
        <select id="assignment-select"></select>
      </div>
      <div>
        <div class="stat-value" id="assignment-title">-</div>
      </div>
    </div>

    <div class="tabs" role="tablist">
      <button class="tab active" data-tab="overview">Uebersicht</button>
      <button class="tab" data-tab="participants">Teilnehmer</button>
    </div>

    <section class="panel active" id="tab-overview">
      <div class="evaluation-card">
        <h2>Kennzahlen</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">Teilnehmer gesamt</div>
            <div class="stat-value" id="stat-total-users">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-unstarted"></span>Zugewiesen</div>
            <div class="stat-value" id="stat-assigned">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-in-progress"></span>In Bearbeitung</div>
            <div class="stat-value" id="stat-in-progress">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-in-progress"></span>Nacharbeit</div>
            <div class="stat-value" id="stat-rework">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-completed"></span>Abgeschlossen</div>
            <div class="stat-value" id="stat-completed">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-late-completed"></span>Verspaetet abgeschlossen</div>
            <div class="stat-value" id="stat-late-completed">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-passed"></span>Bestanden</div>
            <div class="stat-value" id="stat-passed">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-passed-delayed"></span>Bestanden (verspaetet)</div>
            <div class="stat-value" id="stat-passed-delayed">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><span class="status-dot status-missed"></span>Verpasst</div>
            <div class="stat-value" id="stat-missed">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Durchschnitt Runs</div>
            <div class="stat-value" id="stat-avg-runs">0.0</div>
          </div>
        </div>
      </div>

      <div class="evaluation-card">
        <h2>Aufgaben</h2>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>Pos</th>
                <th>Aufgabe</th>
                <th>Status</th>
                <th class="num-right">Checks Σ/Ø</th>
                <th class="num-right">Runs Σ/Ø</th>
                <th class="num-right">Hints Σ/Ø</th>
                <th class="num-right">Zeit Σ/Ø</th>
              </tr>
            </thead>
            <tbody id="tasks-overview-body"></tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="panel" id="tab-participants">
      <div class="evaluation-card">
        <h2>Teilnehmer</h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:12px;">
          <div style="display:flex;flex-direction:column;gap:6px;min-width:220px;">
            <label for="participants-team-filter">Team</label>
            <select id="participants-team-filter">
              <option value="">Alle Teams</option>
            </select>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px;min-width:260px;flex:1;">
            <label for="participants-search">Studierende suchen</label>
            <input type="text" id="participants-search" placeholder="Mail, Vorname oder Nachname" />
          </div>
        </div>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Name</th>
                <th>Team</th>
                <th>Aufgaben</th>
                <th>Abgabe</th>
                <th>Bewertung</th>
                <th class="num-right">Runs</th>
                <th class="num-right">Zeit</th>
                <th>Source</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="participants-body"></tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  </div>

  <div class="modal" id="user-detail-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="user-detail-title">User</h3>
        <button class="hspf-btn hspf-btn-ghost" id="user-detail-close">Close</button>
      </div>
      <div id="user-detail-meta" class="admin-card-subtitle"></div>
      <div class="evaluation-card" style="margin-top: var(--hspf-spacing-md);">
        <h3>Status</h3>
        <div id="user-detail-status"></div>
      </div>
      <div class="evaluation-card" style="margin-top: var(--hspf-spacing-md);">
        <h3>Aufgaben</h3>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>Pos</th>
                <th>Aufgabe</th>
                <th>Status</th>
                <th class="num-right">Attempts</th>
                <th class="num-right">Runs</th>
                <th class="num-right">Zeit</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="user-detail-tasks"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="js/evaluation.js?v=4"></script>
</body>
</html>
