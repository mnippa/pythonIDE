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
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Python IDE - Admin Dashboard</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <link rel="stylesheet" href="css/admin-compat.css">
  <style>
    body {
      padding: 0;
      min-height: 100vh;
    }
    
    .admin-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: var(--hspf-spacing-lg);
    }
    
    .admin-actions {
      display: flex;
      align-items: center;
      gap: var(--hspf-spacing-sm);
    }
    
    .tabs {
      display: flex;
      gap: var(--hspf-spacing-sm);
      margin: var(--hspf-spacing-lg) 0;
      border-bottom: 2px solid var(--hspf-border);
      padding-bottom: 0;
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
      margin-top: var(--hspf-spacing-lg);
    }
    
    .panel.active {
      display: block;
    }
    
    .admin-card {
      background: var(--hspf-surface);
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius-md);
      padding: var(--hspf-spacing-lg);
      margin-bottom: var(--hspf-spacing-lg);
      box-shadow: var(--hspf-shadow);
    }
    
    .admin-card h2, .admin-card h3 {
      margin: 0 0 var(--hspf-spacing-sm);
      color: var(--hspf-text-primary);
      font-weight: 300;
    }
    
    .admin-card-subtitle {
      font-size: 14px;
      color: var(--hspf-text-secondary);
      margin-bottom: var(--hspf-spacing-md);
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
      cursor: pointer;
      user-select: none;
      position: relative;
      padding-right: 24px;
    }
    
    tbody tr:nth-child(even) {
      background-color: var(--hspf-gray-50);
    }
    
    tbody tr:hover {
      background-color: var(--hspf-gray-100);
    }
    
    th:hover {
      background-color: var(--hspf-gray-200);
    }
    
    th.sortable::after {
      content: '\2195';
      position: absolute;
      right: 8px;
      opacity: 0.3;
      font-size: 12px;
    }
    
    th.sorted-asc::after {
      content: '\2191';
      opacity: 1;
    }
    
    th.sorted-desc::after {
      content: '\2193';
      opacity: 1;
    }
    
    .row-actions {
      display: flex;
      gap: 4px;
      flex-wrap: nowrap;
    }
    
    .icon-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 4px 8px;
      font-size: 16px;
      color: var(--hspf-text-secondary);
      transition: var(--hspf-transition);
      border-radius: var(--hspf-radius-sm);
    }
    
    .icon-btn:hover {
      background-color: var(--hspf-bg-secondary);
      color: var(--hspf-text-primary);
    }
    
    .icon-btn.warn:hover {
      background-color: #fef3c7;
      color: #d97706;
    }
    
    .icon-btn.danger:hover {
      background-color: #fee2e2;
      color: #dc2626;
    }
    
    .search-filter {
      display: flex;
      gap: var(--hspf-spacing-md);
      margin-bottom: var(--hspf-spacing-md);
      align-items: center;
    }
    
    .search-filter input {
      flex: 1;
      padding: 8px 12px;
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius);
      font-size: 14px;
    }
    
    .search-filter input:focus {
      outline: none;
      border-color: var(--hspf-accent);
    }
    
    .pagination {
      display: flex;
      gap: var(--hspf-spacing-sm);
      align-items: center;
      justify-content: center;
      margin-top: var(--hspf-spacing-md);
    }
    
    .pagination button {
      padding: 6px 12px;
      border: 1px solid var(--hspf-border);
      background: var(--hspf-surface);
      cursor: pointer;
      border-radius: var(--hspf-radius-sm);
      font-size: 13px;
    }
    
    .pagination button:hover:not(:disabled) {
      background: var(--hspf-bg-secondary);
    }
    
    .pagination button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    .pagination .page-info {
      font-size: 13px;
      color: var(--hspf-text-secondary);
    }
    
    .field {
      display: flex;
      flex-direction: column;
      gap: var(--hspf-spacing-xs);
      margin-bottom: var(--hspf-spacing-md);
    }
    
    .field label {
      font-size: 14px;
      font-weight: 250;
      color: var(--hspf-text-primary);
    }
    
    .field input,
    .field select,
    .field textarea {
      width: 100%;
      padding: 10px 14px;
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius);
      font-size: 14px;
      font-family: var(--hspf-font-family);
      transition: var(--hspf-transition);
    }
    
    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      outline: none;
      border-color: var(--hspf-accent);
      box-shadow: 0 0 0 3px rgba(255, 190, 49, 0.2);
    }
    
    .field textarea {
      min-height: 100px;
      resize: vertical;
      font-family: var(--hspf-font-mono);
    }
    
    .hint {
      font-size: 12px;
      color: var(--hspf-text-muted);
      margin-top: 4px;
    }
    
    .tag {
      padding: 4px 8px;
      border-radius: var(--hspf-radius-sm);
      background-color: #fef3c7;
      font-size: 11px;
      font-weight: 250;
    }

    .tag.quiz {
      background-color: #e0f2fe;
      color: #075985;
    }
    
    .mono {
      font-family: var(--hspf-font-mono);
      font-size: 12px;
    }
    
    .status {
      padding: 4px 10px;
      border-radius: var(--hspf-radius-full);
      font-size: 11px;
      font-weight: 600;
      background-color: #dcfce7;
      color: #166534;
    }
    
    .status.arch {
      background-color: #fee2e2;
      color: #991b1b;
    }

    .ticket-note {
      max-width: 320px;
      color: var(--hspf-text-secondary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .ticket-token {
      font-family: var(--hspf-font-mono);
      font-size: 12px;
      color: var(--hspf-text-muted);
    }

    .ticket-open-btn {
      color: #047857;
    }

    .ticket-open-btn:hover {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      padding: var(--hspf-spacing-lg);
      overflow-y: auto;
      animation: fadeIn 0.2s ease-in;
    }
    
    .modal.active {
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding-top: 32px;
      padding-bottom: 32px;
    }
    
    .modal-content {
      background: var(--hspf-surface);
      border-radius: var(--hspf-radius-md);
      padding: var(--hspf-spacing-xl);
      max-width: 800px;
      max-height: calc(100vh - 64px);
      overflow-y: auto;
      margin: auto;
      border: 2px solid var(--hspf-border);
      box-shadow: var(--hspf-shadow-xl);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--hspf-spacing-lg);
      padding-bottom: var(--hspf-spacing-md);
      border-bottom: 2px solid var(--hspf-accent);
    }
    
    .modal-header h3 {
      margin: 0;
      color: var(--hspf-text-primary);
      font-weight: 300;
    }
    
    .modal-close {
      font-size: 24px;
      font-weight: bold;
      color: var(--hspf-text-secondary);
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      line-height: 1;
    }
    
    .modal-close:hover {
      color: var(--hspf-text-primary);
    }
    
    /* Test Cases Builder GUI */
    .test-cases-builder {
      background: var(--hspf-surface);
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius);
      padding: var(--hspf-spacing-md);
      margin-bottom: var(--hspf-spacing-md);
    }
    
    .builder-header {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: var(--hspf-spacing-sm);
      padding-bottom: var(--hspf-spacing-md);
      border-bottom: 2px solid var(--hspf-border-accent);
    }
    
    .test-case-item {
      transition: var(--hspf-transition);
      padding: var(--hspf-spacing-sm);
      border: 1px solid var(--hspf-border);
      border-radius: var(--hspf-radius);
      margin-bottom: var(--hspf-spacing-sm);
    }
    
    .test-case-item:hover {
      box-shadow: var(--hspf-shadow-md);
      border-color: var(--hspf-accent);
    }

    .deploy-sync-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: var(--hspf-spacing-md);
      align-items: end;
    }

    .deploy-sync-flags {
      display: flex;
      flex-wrap: wrap;
      gap: var(--hspf-spacing-md);
      margin-top: var(--hspf-spacing-md);
    }

    .deploy-sync-flag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: var(--hspf-text-primary);
      user-select: none;
    }

    .deploy-sync-flag input {
      width: 16px;
      height: 16px;
      accent-color: var(--hspf-accent);
    }

    .deploy-sync-warning {
      margin-top: var(--hspf-spacing-md);
      padding: var(--hspf-spacing-sm) var(--hspf-spacing-md);
      border: 1px solid #f59e0b;
      background: #fffbeb;
      border-radius: var(--hspf-radius-sm);
      color: #92400e;
      font-size: 13px;
    }

    .deploy-sync-actions {
      display: flex;
      gap: var(--hspf-spacing-sm);
      margin-top: var(--hspf-spacing-md);
      flex-wrap: wrap;
      align-items: center;
    }

    .deploy-sync-status {
      margin-top: var(--hspf-spacing-md);
      font-size: 13px;
      color: var(--hspf-text-secondary);
      min-height: 1.2em;
    }

    .deploy-sync-status.error {
      color: #b91c1c;
      font-weight: 600;
    }

    .deploy-sync-status.ok {
      color: #166534;
      font-weight: 600;
    }

    .deploy-sync-output {
      margin-top: var(--hspf-spacing-md);
      background: #0f172a;
      color: #e2e8f0;
      border-radius: var(--hspf-radius-sm);
      border: 1px solid #1e293b;
      font-family: var(--hspf-font-mono);
      font-size: 12px;
      line-height: 1.5;
      padding: var(--hspf-spacing-md);
      min-height: 140px;
      max-height: 320px;
      overflow: auto;
      white-space: pre-wrap;
    }

    #deploy-sync-confirm-modal .modal-content {
      max-width: 620px;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @media (max-width: 768px) {
      .admin-container {
        padding: var(--hspf-spacing-md);
      }
      
      .tabs {
        overflow-x: auto;
      }
    }
  </style>
  <!-- TinyMCE WYSIWYG Editor (local first, keyless CDN fallback) -->
  <script src="vendor/tinymce/tinymce.min.js"></script>
  <script>
    if (typeof window.tinymce === 'undefined') {
      document.write('<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"><\\/script>');
    }
  </script>
</head>
<body>
  <?php
  $pageTitle = 'Admin Dashboard';
  $showUser = true;
  $userInfo = [
    'name' => $displayName,
    'role' => 'admin'
  ];
  $headerActions = '
    <a class="hspf-btn hspf-btn-ghost" href="change-password.php">Passwort ändern</a>
    <a class="hspf-btn hspf-btn-ghost" href="dashboard.php">← Dashboard</a>
    <button class="hspf-btn hspf-btn-ghost" id="logout-btn">Logout</button>
  ';
  include(__DIR__ . '/../components/header.php');
  ?>
  
  <div class="admin-container">

    <div class="tabs" role="tablist">
      <button class="tab active" data-tab="projects">Meine Projekte</button>
      <button class="tab" data-tab="assignments">Assignments</button>
      <button class="tab" data-tab="tickets">Tickets</button>
      <button class="tab" data-tab="teams">Teams</button>
      <button class="tab" data-tab="users">Users</button>
    </div>

    <section class="panel active" id="tab-projects">
      <div class="admin-card">
        <h2>Meine Projekte</h2>
        <div class="admin-card-subtitle">Projektzugriff für Admins per Suche oder direkt aus der Userliste. Lazy Load ist bei vielen Nutzern bewusst aktiviert.</div>
        <div class="search-filter" style="margin-bottom: var(--hspf-spacing-md);">
          <input type="text" id="projects-search" placeholder="Projektname, Beschreibung, Owner-Mail oder Name suchen..." />
          <select id="projects-team-filter" style="min-width: 180px;">
            <option value="">Alle Teams</option>
          </select>
          <select id="projects-semester-filter" style="min-width: 160px;">
            <option value="">Alle Semester</option>
          </select>
          <input type="number" id="projects-limit" min="1" max="100" value="50" style="max-width: 120px;" />
          <button class="hspf-btn hspf-btn-primary" type="button" id="projects-search-btn">Suchen</button>
          <button class="hspf-btn" type="button" id="projects-clear-btn">Zurücksetzen</button>
        </div>
        <div class="admin-card-subtitle" id="projects-status">Noch keine Suche ausgeführt.</div>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Beschreibung</th>
                <th>Owner</th>
                <th>Visibility</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="projects-body"></tbody>
          </table>
        </div>
        <div class="pagination" id="projects-pagination">
          <button id="projects-prev">Previous</button>
          <span class="page-info" id="projects-page-info">Page 1 of 1</span>
          <button id="projects-next">Next</button>
        </div>
      </div>

      <div class="admin-card">
        <h3>Deploy Sync (Live/Beta)</h3>
        <div class="admin-card-subtitle">Admin-Tool fuer Live/Beta-Synchronisation. Kritischer Modus <strong>Beta -> Live</strong> hat eine zusaetzliche Sicherheitsabfrage.</div>

        <div class="deploy-sync-grid">
          <div class="field" style="margin-bottom: 0;">
            <label for="deploy-sync-mode">Modus</label>
            <select id="deploy-sync-mode">
              <option value="hydrate-beta">Live -> Beta (hydrate beta)</option>
              <option value="promote-live">Beta -> Live (promote live)</option>
            </select>
          </div>

          <div class="field" style="margin-bottom: 0;">
            <label for="deploy-sync-trigger">Aktion</label>
            <div class="deploy-sync-actions" style="margin-top: 0;">
              <button class="hspf-btn hspf-btn-primary" type="button" id="deploy-sync-trigger">Ausfuehren</button>
            </div>
          </div>
        </div>

        <div class="deploy-sync-flags">
          <label class="deploy-sync-flag" for="deploy-sync-dry-run">
            <input type="checkbox" id="deploy-sync-dry-run" checked>
            <span>Dry-Run (keine Aenderungen schreiben)</span>
          </label>

          <label class="deploy-sync-flag" for="deploy-sync-db">
            <input type="checkbox" id="deploy-sync-db">
            <span>DB-Snapshot mitziehen (nur Live -> Beta)</span>
          </label>

          <label class="deploy-sync-flag" for="deploy-sync-delete">
            <input type="checkbox" id="deploy-sync-delete">
            <span>Delete aktivieren (nur Beta -> Live)</span>
          </label>
        </div>

        <div class="deploy-sync-warning" id="deploy-sync-warning">
          Hinweis: Fuer produktive Deployments zuerst Dry-Run pruefen. Der Beta -> Live Modus verlangt eine explizite Sicherheitsbestaetigung.
        </div>

        <div class="deploy-sync-status" id="deploy-sync-status">Bereit.</div>
        <pre class="deploy-sync-output" id="deploy-sync-output">Noch keine Ausfuehrung.</pre>
      </div>
    </section>

    <section class="panel" id="tab-assignments">
      <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--hspf-spacing-md);">
          <h2 style="margin: 0;">Assignments</h2>
          <button class="hspf-btn hspf-btn-primary" type="button" id="open-assignment-modal">+ New Assignment</button>
        </div>
        
        <div class="search-filter">
          <input type="text" id="assignments-search" placeholder="Search assignments..." />
        </div>
        
        <div style="overflow:auto;">
          <table id="assignments-table">
            <thead>
              <tr>
                <th class="sortable" data-sort="id">ID</th>
                <th class="sortable" data-sort="title">Title</th>
                <th class="sortable" data-sort="created_by_name">Owner</th>
                <th class="sortable" data-sort="difficulty">Difficulty</th>
                <th class="sortable" data-sort="is_active">Active</th>
                <th class="sortable" data-sort="available_from">Start</th>
                <th class="sortable" data-sort="due_date">Due</th>
                <th class="sortable" data-sort="hard_deadline">Hard</th>
                <th class="sortable" data-sort="task_count">Tasks</th>
                <th class="sortable" data-sort="user_count">Users</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="assignments-body"></tbody>
          </table>
        </div>
        
        <div class="pagination" id="assignments-pagination">
          <button id="assignments-prev">Previous</button>
          <span class="page-info" id="assignments-page-info">Page 1 of 1</span>
          <button id="assignments-next">Next</button>
        </div>
      </div>

      <div class="admin-card" id="tasks-section" style="display:none;">
        <h3 id="tasks-title">📚 Tasks</h3>
        <div class="admin-card-subtitle" id="tasks-hint">Select an assignment to manage tasks.</div>
        <div class="search-filter" style="margin-top: var(--hspf-spacing-sm);">
          <input type="text" id="tasks-filter-text" placeholder="Filter by title..." style="max-width: 260px;" />
          <select id="tasks-filter-type" style="max-width: 220px;">
            <option value="all">All task types</option>
            <option value="code">Code (Python)</option>
            <option value="code_ui">Code + UI</option>
            <option value="single_choice">Single-Choice</option>
            <option value="multiple_choice">Multiple-Choice</option>
            <option value="free_text">Freitext</option>
            <option value="code_reading">Code-Lesequest</option>
            <option value="code_random_complex">Code (versteckt)</option>
          </select>
        </div>
        <div style="overflow:auto;">
          <table id="tasks-table">
            <thead>
              <tr>
                <th style="width: 40px;"><input type="checkbox" id="select-all-tasks" title="Select All"></th>
                <th>Pos</th>
                <th>Title</th>
                <th>Task Type</th>
                <th>Difficulty</th>
                <th>Tests</th>
                <th>Solution</th>
                <th>Mode</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="tasks-body"></tbody>
          </table>
        </div>
        <div style="margin-top:var(--hspf-spacing-md);">
          <button class="hspf-btn hspf-btn-primary" type="button" id="open-task-modal">+ New Task</button>
          <button class="hspf-btn hspf-btn-secondary" type="button" id="open-task-ai-modal" style="margin-left:var(--hspf-spacing-sm);">✨ Task AI Generator</button>
          <button class="hspf-btn hspf-btn-secondary" type="button" id="import-json-text-btn" style="margin-left:var(--hspf-spacing-sm);">📋 Import JSON Text</button>
          <button class="hspf-btn hspf-btn-secondary" type="button" id="import-task-btn" style="margin-left:var(--hspf-spacing-sm);">Import Tasks (ZIP or JSON)</button>
          <button class="hspf-btn hspf-btn-secondary" type="button" id="export-tasks-btn" style="margin-left:var(--hspf-spacing-sm);">Export Selected Tasks (ZIP)</button>
          <input type="file" id="import-task-file-input" accept=".json,.zip" style="display:none;">
        </div>
      </div>

    </section>

    <section class="panel" id="tab-tickets">
      <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--hspf-spacing-md); gap: var(--hspf-spacing-md); flex-wrap: wrap;">
          <div>
            <h2 style="margin: 0;">Support Tickets</h2>
            <div class="admin-card-subtitle" id="tickets-status">Eingehende Hilfsanfragen aus dem Assignment-Editor.</div>
          </div>
          <div style="display: flex; gap: var(--hspf-spacing-sm);">
            <button class="hspf-btn hspf-btn-primary" type="button" id="tickets-refresh-btn">Aktualisieren</button>
            <button class="hspf-btn hspf-btn-danger" type="button" id="tickets-delete-all-btn">Alle löschen</button>
          </div>
        </div>

        <div class="search-filter">
          <input type="text" id="tickets-search" placeholder="Nach User, E-Mail, Assignment, Token oder Notiz suchen..." />
        </div>

        <div style="overflow:auto;">
          <table id="tickets-table">
            <thead>
              <tr>
                <th>Zeit</th>
                <th>User</th>
                <th>E-Mail</th>
                <th>Assignment</th>
                <th>Notiz</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="tickets-body">
              <tr><td colspan="6" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Tickets werden geladen...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="panel" id="tab-teams">
      <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--hspf-spacing-md);">
          <h2 style="margin: 0;">👥 Teams</h2>
          <button class="hspf-btn hspf-btn-primary" type="button" id="open-team-modal">+ New Team</button>
        </div>
        <div class="admin-card-subtitle">Teams für Gruppen-Zuweisung. Klicken Sie auf ID, Name oder Beschreibung eines Teams, um Teilnehmer und Standard-Assignments unten zu verwalten.</div>
        
        <div style="overflow:auto;">
          <table id="teams-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Teilnehmer</th>
                <th>Einladungslink</th>
                <th>Active</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="teams-body"></tbody>
          </table>
        </div>
      </div>

      <div class="admin-card" style="display:none;" id="team-detail-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--hspf-spacing-md);">
          <h3 style="margin: 0;">Teilnehmer - Assignment-Übersicht für <span id="selected-team-name" style="font-weight: 700;"></span></h3>
          <button class="hspf-btn" type="button" onclick="document.getElementById('team-detail-card').style.display='none'; document.getElementById('team-assignments-detail-card').style.display='none';">✕ Close</button>
        </div>
        <div style="overflow:auto;">
          <table id="team-matrix-table">
            <thead id="team-matrix-head"></thead>
            <tbody id="team-matrix-body">
              <tr><td colspan="100" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Team wird geladen...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="admin-card" style="display:none;" id="team-assignments-detail-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--hspf-spacing-md);">
          <h3 style="margin: 0;">Standard-Assignments für <span id="selected-team-name-2" style="font-weight: 700;"></span></h3>
          <button class="hspf-btn hspf-btn-primary" type="button" id="add-team-assignment-btn" style="display:none;">+ Neues Assignment</button>
        </div>
        <div class="admin-card-subtitle">Verwaltet die Default-Zuordnungen pro Team. Entfernen betrifft nur zukünftige bzw. neue Team-Zuweisungen; bestehende User-Assignments bleiben erhalten.</div>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Assignment</th>
                <th>Start</th>
                <th>Deadline</th>
                <th>Hard Deadline</th>
                <th>Late</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="team-assignments-body">
              <tr><td colspan="7" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Team-Assignments werden geladen...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="panel" id="tab-users">
      <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--hspf-spacing-md);">
          <h2 style="margin: 0;">👤 Users</h2>
          <div style="display: flex; gap: var(--hspf-spacing-sm);">
            <button class="hspf-btn" type="button" id="bulk-delete-users-btn">🗑️ Bulk Delete</button>
            <button class="hspf-btn hspf-btn-primary" type="button" id="bulk-assign-btn">📋 Bulk Assign</button>
          </div>
        </div>
        
        <div class="search-filter" style="margin-bottom: var(--hspf-spacing-md);">
          <select id="users-team-filter" style="min-width: 200px;">
            <option value="">All Teams</option>
          </select>
          <select id="users-semester-filter" style="min-width: 160px;">
            <option value="">All Semesters</option>
          </select>
          <input type="text" id="users-search" placeholder="Search users..." />
          <select id="users-limit" style="min-width: 120px;">
            <option value="10">10 / page</option>
            <option value="25" selected>25 / page</option>
            <option value="50">50 / page</option>
            <option value="100">100 / page</option>
          </select>
        </div>
        
        <div style="overflow:auto;">
          <table id="users-table">
            <thead>
              <tr>
                <th style="width: 40px;"><input type="checkbox" id="select-all-users" title="Select All"></th>
                <th>ID</th>
                <th>Email</th>
                <th>Name</th>
                <th>Team</th>
                <th>Semester</th>
                <th>Assignments</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="users-body"></tbody>
          </table>
        </div>
        <div class="pagination" id="users-pagination">
          <button id="users-prev">Previous</button>
          <span class="page-info" id="users-page-info">Page 1 of 1</span>
          <button id="users-next">Next</button>
        </div>
      </div>
    </section>
  </div>

  <div id="bulk-assign-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>📋 Assignments verteilen</h3>
        <button id="bulk-assign-close-btn" class="modal-close">✕</button>
      </div>
      <div class="modal-body" style="padding: var(--hspf-spacing-lg);">
        <form id="bulk-assign-form">
          <div class="field">
            <label for="bulk-assign-assignment">Assignment wählen</label>
            <select id="bulk-assign-assignment" required style="width: 100%; padding: 8px;">
              <option value="">-- Assignment auswählen --</option>
            </select>
          </div>
          
          <div class="field">
            <label for="bulk-assign-due-date">Fälligkeitsdatum (optional)</label>
            <input type="datetime-local" id="bulk-assign-due-date" style="width: 100%; padding: 8px;" />
          </div>
          
          <div style="background: var(--hspf-bg-secondary); padding: var(--hspf-spacing-md); border-radius: var(--hspf-radius-sm); margin: var(--hspf-spacing-md) 0;">
            <p style="margin: 0 0 var(--hspf-spacing-sm); font-weight: 600;">Ausgewählte Benutzer:</p>
            <div id="bulk-assign-users-list" style="max-height: 150px; overflow-y: auto; padding: var(--hspf-spacing-sm); background: var(--hspf-surface); border-radius: var(--hspf-radius-sm);"></div>
            <p id="bulk-assign-count" style="margin: var(--hspf-spacing-sm) 0 0; font-size: 14px; color: var(--hspf-text-secondary);">0 Benutzer ausgewählt</p>
          </div>
          
          <div class="row-actions">
            <button type="submit" class="hspf-btn hspf-btn-primary">✓ Verteilen</button>
            <button type="button" id="bulk-assign-cancel-btn" class="hspf-btn">Abbrechen</button>
          </div>
          <div id="bulk-assign-status" style="margin-top: var(--hspf-spacing-md); padding: var(--hspf-spacing-sm); border-radius: var(--hspf-radius-sm); display: none;"></div>
        </form>
      </div>
    </div>
  </div>

  <div id="team-assign-modal" class="modal">
    <div class="modal-content" style="max-width: 640px;">
      <div class="modal-header">
        <h3>📚 Assignment einem Team zuordnen</h3>
        <button id="team-assign-close-btn" class="modal-close">✕</button>
      </div>
      <div class="modal-body" style="padding: var(--hspf-spacing-lg);">
        <form id="team-assign-form">
          <input type="hidden" id="team-assign-team-id" />
          <div class="field">
            <label>Team</label>
            <div id="team-assign-team-name" style="padding: 10px 12px; background: var(--hspf-bg-secondary); border: 1px solid var(--hspf-border); border-radius: var(--hspf-radius-sm);">-</div>
          </div>
          <div class="field">
            <label for="team-assign-assignment">Assignment wählen</label>
            <select id="team-assign-assignment" required style="width: 100%; padding: 8px;">
              <option value="">-- Assignment auswählen --</option>
            </select>
          </div>
          <div class="field">
            <label for="team-assign-assignment-due-date-display">Standardfälligkeit(Assignment)</label>
            <input type="datetime-local" id="team-assign-assignment-due-date-display" style="width: 100%; padding: 8px;" readonly disabled />
          </div>
          <div class="field">
            <label for="team-assign-due-date">individuelles Fälligkeitsdatum(Team)</label>
            <input type="datetime-local" id="team-assign-due-date" style="width: 100%; padding: 8px;" />
            <div class="hint">Hier wird nur die Team-Zuordnung angepasst, ohne globale Auswirkungen auf andere Teams.</div>
          </div>
          <div class="row-actions">
            <button type="submit" class="hspf-btn hspf-btn-primary">✓ Team zuordnen</button>
            <button type="button" id="team-assign-cancel-btn" class="hspf-btn">Abbrechen</button>
          </div>
          <div id="team-assign-status" style="margin-top: var(--hspf-spacing-md); padding: var(--hspf-spacing-sm); border-radius: var(--hspf-radius-sm); display: none;"></div>
        </form>
      </div>
    </div>
  </div>

  <div id="user-edit-modal" class="modal">
    <div class="modal-content" style="max-width: 560px;">
      <div class="modal-header">
        <h3>User bearbeiten</h3>
        <button id="user-edit-close-btn" class="modal-close" type="button">✕</button>
      </div>
      <form id="user-edit-form">
        <input type="hidden" id="user-edit-id" />
        <div class="field">
          <label for="user-edit-email">E-Mail</label>
          <input id="user-edit-email" type="email" required />
        </div>
        <div class="field">
          <label for="user-edit-first-name">Vorname</label>
          <input id="user-edit-first-name" type="text" />
        </div>
        <div class="field">
          <label for="user-edit-last-name">Nachname</label>
          <input id="user-edit-last-name" type="text" />
        </div>
        <div class="field">
          <label for="user-edit-team">Team</label>
          <select id="user-edit-team">
            <option value="">Kein Team</option>
          </select>
        </div>
        <div class="field">
          <label for="user-edit-role">Rolle</label>
          <select id="user-edit-role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="field">
          <label for="user-edit-status">Status</label>
          <select id="user-edit-status" required>
            <option value="aktiv">aktiv</option>
            <option value="archiviert">archiviert</option>
          </select>
        </div>
        <div class="row-actions" style="margin-top: var(--hspf-spacing-md);">
          <button type="submit" class="hspf-btn hspf-btn-primary">Speichern</button>
          <button type="button" id="user-edit-cancel-btn" class="hspf-btn">Abbrechen</button>
        </div>
      </form>
    </div>
  </div>

  <div id="assignment-modal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
      <div class="modal-header">
        <h3 id="assignment-modal-title">New Assignment</h3>
        <button id="assignment-close-btn" class="modal-close">✕</button>
      </div>
      <form id="assignment-form">
        <input type="hidden" id="assignment-id" />
        <div class="field">
          <label for="assignment-title">Title</label>
          <input id="assignment-title" class="hspf-input" required />
        </div>
        <div class="field">
          <label for="assignment-description">Description</label>
          <textarea id="assignment-description" class="hspf-textarea"></textarea>
        </div>
        <div class="field">
          <label for="assignment-difficulty">Difficulty</label>
          <select id="assignment-difficulty" class="hspf-select">
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <div class="field">
          <label for="assignment-active">Active</label>
          <select id="assignment-active" class="hspf-select">
            <option value="true">true</option>
            <option value="false">false</option>
          </select>
        </div>
        <div class="field">
          <label for="assignment-available-from">Verfuegbar ab</label>
          <input id="assignment-available-from" type="datetime-local" class="hspf-input" />
        </div>
        <div class="field">
          <label for="assignment-due-date">Regulaere Deadline</label>
          <input id="assignment-due-date" type="datetime-local" class="hspf-input" />
        </div>
        <div class="field">
          <label for="assignment-hard-deadline">Spaeteste Abgabe (Hard Deadline)</label>
          <input id="assignment-hard-deadline" type="datetime-local" class="hspf-input" />
        </div>
        <div class="field">
          <label for="assignment-allow-late">Verspaetete Abgabe erlauben</label>
          <select id="assignment-allow-late" class="hspf-select">
            <option value="true">true</option>
            <option value="false">false</option>
          </select>
        </div>
        <div class="row-actions">
          <button class="hspf-btn hspf-btn-primary" type="submit">Save</button>
          <button class="hspf-btn" type="button" id="assignment-cancel">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Import JSON Text Modal -->
  <div id="import-json-text-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
      <div class="modal-header">
        <h3>📋 Import JSON Text</h3>
        <button id="import-json-text-close-btn" class="modal-close">✕</button>
      </div>
      <div style="padding: var(--hspf-spacing-md);">
        <p style="color: var(--hspf-text-secondary); margin-bottom: var(--hspf-spacing-md);">
          Kopiere das generierte JSON von der KI ein und klicke auf "Importieren".
        </p>
        
        <textarea id="import-json-text-input" placeholder='{"version":"1.0","title":"...","problem_type":"..."}' style="width: 100%; min-height: 300px; font-family: 'Monaco', monospace; font-size: 12px; resize: vertical;"></textarea>
        
        <div id="import-json-text-error" style="margin-top: var(--hspf-spacing-md); padding: var(--hspf-spacing-sm); background: #fee; border: 1px solid #f99; border-radius: var(--hspf-radius-sm); color: #c33; font-size: 12px; display: none;"></div>
        
        <div style="margin-top: var(--hspf-spacing-lg); display: flex; gap: var(--hspf-spacing-sm);">
          <button class="hspf-btn hspf-btn-primary" id="import-json-text-confirm-btn" style="flex: 1;">✓ Importieren</button>
          <button class="hspf-btn" id="import-json-text-cancel-btn" style="flex: 1;">Abbrechen</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Pre-Task Dialog: Select Type and Title -->
  <div id="pre-task-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <h3>📝 Create New Task</h3>
        <button id="pre-task-close-btn" class="modal-close">✕</button>
      </div>
      <div style="padding: var(--hspf-spacing-md);">
        <p style="color: var(--hspf-text-secondary); margin-bottom: var(--hspf-spacing-md);">Wählen Sie zuerst den Aufgabentyp und Titel aus. Diese können später nicht mehr geändert werden.</p>
        
        <div class="field">
          <label for="pre-task-type">Task Type</label>
          <select id="pre-task-type">
            <option value="code">Code (Python)</option>
            <option value="code_ui">Code + UI</option>
            <option value="single_choice">Single-Choice</option>
            <option value="multiple_choice">Multiple-Choice</option>
            <option value="free_text">Freitext</option>
            <option value="code_reading">Code Reading</option>
            <option value="code_random_complex">Random Complex</option>
            <option value="db_model">Datenbank-Modell</option>
            <option value="file_submission">Dateiabgabe</option>
          </select>
        </div>
        
        <div class="field">
          <label for="pre-task-title">Task Title</label>
          <input type="text" id="pre-task-title" placeholder="z.B. 'Fibonacci Funktion'" required />
        </div>
        
        <div id="pre-task-error" style="margin-top: var(--hspf-spacing-md); padding: var(--hspf-spacing-sm); background: #fee; border: 1px solid #f99; border-radius: var(--hspf-radius-sm); color: #c33; font-size: 12px; display: none;"></div>
        
        <div style="margin-top: var(--hspf-spacing-lg); display: flex; gap: var(--hspf-spacing-sm);">
          <button type="button" class="hspf-btn hspf-btn-primary" id="pre-task-continue-btn" style="flex: 1;">→ Weiter</button>
          <button type="button" class="hspf-btn" id="pre-task-cancel-btn" style="flex: 1;">Abbrechen</button>
        </div>
      </div>
    </div>
  </div>

  <div id="task-create-modal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
      <div class="modal-header">
        <h3>New Task</h3>
        <button id="task-create-close-btn" class="modal-close">✕</button>
      </div>
      <form id="task-form">
        
        <!-- Task Form Tabs -->
        <div class="task-tabs" role="tablist">
          <button class="task-tab active" type="button" data-task-tab="base">Basis</button>
          <button class="task-tab" type="button" data-task-tab="optional">Zusatz</button>
          <button class="task-tab" type="button" data-task-tab="validation">Prüfung</button>
        </div>
        
        <!-- Tab 1: Base Fields -->
        <div class="task-tab-panel active" data-task-tab-panel="base">
          <div class="field">
            <label for="task-title">Title</label>
            <input id="task-title" required />
          </div>
          
          <div class="field">
            <label for="task-text">Aufgabe (Student-facing)</label>
            <textarea id="task-text" placeholder="Die Aufgabenstellung / Frage für den Studierenden"></textarea>
            <div class="hint">Dies ist die zentrale Aufgabenbeschreibung, die dem Student angezeigt wird</div>
          </div>
          
          <!-- Task Type Selector -->
          <div class="field">
            <label for="new-task-type">Task Type</label>
            <select id="new-task-type">
              <option value="code">Code (Python)</option>
              <option value="code_ui">Code + UI</option>
              <option value="single_choice">Single-Choice</option>
              <option value="multiple_choice">Multiple-Choice</option>
              <option value="free_text">Freitext</option>
              <option value="code_reading">Code Reading</option>
              <option value="code_random_complex">Random Complex</option>
              <option value="db_model">Datenbank-Modell</option>
              <option value="file_submission">Dateiabgabe</option>
            </select>
          </div>

          <div class="field">
            <label for="task-difficulty">Schwierigkeit</label>
            <select id="task-difficulty">
              <option value="basic">Basic</option>
              <option value="medium" selected>Medium</option>
              <option value="hard">Hard</option>
            </select>
          </div>
          
          <!-- Legacy problem_type (hidden, for compatibility) -->
          <input type="hidden" id="task-type" value="code_completion" />
          
          <!-- Code Template (for code and code_reading) -->
          <div class="field" data-field="code-template">
            <label for="task-template">Code Template</label>
            <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
              <button type="button" class="hspf-btn hspf-btn-secondary" id="task-random-snippet" style="font-size:12px;">🎲 randomNumbers</button>
              <button type="button" class="hspf-btn hspf-btn-secondary" id="task-init-block-generator" style="font-size:12px;">📝 Init-Block einfügen</button>
            </div>
            <textarea id="task-template" placeholder="Für code: def hello():\n    pass\n\nFür code_reading: {var} Platzhalter\nFür code_random_complex: result = int({binary}, 2)"></textarea>
            <div class="hint">
              <strong>Für code:</strong> Starter-Code im Editor für Schüler<br>
              <strong>Für code_reading:</strong> Vorlage mit Platzhaltern <code>{varName}</code> (Werte via variable_overrides)<br>
              <strong>Für code_random_complex:</strong> Optional - Vorlage mit Platzhaltern wie <code>{binary}</code> (Werte via randomizer_code)
            </div>
          </div>
          
          <div class="field">
            <label for="task-max-attempts">Max Versuche</label>
            <input id="task-max-attempts" type="number" min="1" value="1" />
          </div>
          
          <div class="field" data-field="max-iterations">
            <label for="task-max-iterations">Iterationen</label>
            <input id="task-max-iterations" type="number" min="1" value="3" />
            <div class="hint">Für code_reading wird die Anzahl automatisch aus den Sets bestimmt, bei code_random_complex manuell über dieses Feld.</div>
          </div>

          <div class="field" data-field="file-submission-config">
            <label for="task-file-submission-types">Dateiabgabe: erlaubte Typen</label>
            <input id="task-file-submission-types" type="text" value="zip,png" placeholder="zip,png" />
            <label for="task-file-submission-max-size" style="margin-top:8px;">Dateiabgabe: Max. Dateigröße</label>
            <select id="task-file-submission-max-size">
              <option value="51200">50 KB</option>
              <option value="102400" selected>100 KB (Standard)</option>
              <option value="256000">250 KB</option>
              <option value="1048576">1 MB</option>
              <option value="2097152">2 MB</option>
              <option value="5242880">5 MB</option>
            </select>
          </div>
          
          <div class="field checkbox-field">
            <label>
              <input id="task-folderstructure" type="checkbox" />
              <span>📁 Mit Ordnerstruktur</span>
            </label>
            <div class="hint">Schüler arbeiten mit einer Dateistruktur</div>
          </div>
          
          <div class="field checkbox-field">
            <label>
              <input id="task-allowDownload" type="checkbox" />
              <span>� Download erlauben</span>
            </label>
            <div class="hint">Schüler können Dateien herunterladen</div>
          </div>
          
          <div class="field checkbox-field">
            <label>
              <input id="task-allowCodeUiWebEdit" type="checkbox" />
              <span>🎨 Code-UI HTML+CSS editierbar</span>
            </label>
            <div class="hint">Bei code_ui-Aufgaben: Schüler können index.html und style.css bearbeiten</div>
          </div>
        </div>
        
        <!-- Tab 2: Optional Fields -->
        <div class="task-tab-panel" data-task-tab-panel="optional">
          <div class="field">
            <div style="display: flex; gap: 10px; margin-bottom: 8px;">
              <label for="task-description">Description (Kontext/Metadaten)</label>
              <button type="button" id="auto-description-btn" class="hspf-btn" style="padding: 4px 12px; font-size: 12px;">📝 Auto Desc</button>
            </div>
            <textarea id="task-description" class="tinymce-editor" placeholder="Optionale Zusatzinformationen (nicht die Hauptaufgabe)"></textarea>
            <div class="hint">Für Admins: zusätzliche Dokumentation, Lernziele, Meta-Infos</div>
          </div>
          
          <!-- Image Upload (for quiz tasks) -->
          <div class="field" data-field="image-upload">
            <label for="task-image">Bild hochladen (optional)</label>
            <input type="file" id="task-image-upload" accept="image/*" style="margin-bottom: 8px;" />
            <div id="task-image-preview"></div>
            <input type="hidden" id="task-image-url" />
          </div>
          
          <!-- Hints -->
          <div data-field="hints-section">
            <div class="field">
              <label for="task-hint1">Zusätzlicher Hinweis 1 (optional)</label>
              <textarea id="task-hint1"></textarea>
            </div>
            <div class="field">
              <label for="task-hint2">Zusätzlicher Hinweis 2 (optional)</label>
              <textarea id="task-hint2"></textarea>
            </div>
            <div class="field">
              <label for="task-hint3">Zusätzlicher Hinweis 3 (optional)</label>
              <textarea id="task-hint3"></textarea>
            </div>
            <div class="field">
              <label for="task-stoff">Lerninhalt/Stoff (optional)</label>
              <textarea id="task-stoff" class="tinymce-editor" placeholder="Verwandte Lerninhalte, Ressourcen, etc."></textarea>
            </div>
          </div>
        </div>
        
        <!-- Tab 3: Validation Fields -->
        <div class="task-tab-panel" data-task-tab-panel="validation">
          <div class="field checkbox-field">
            <label>
              <input id="task-show-solution" type="checkbox" checked />
              <span>Lösung anzeigen bei max Versuchen</span>
            </label>
          </div>
          
          <div class="field checkbox-field" data-field="show-solution-code">
            <label>
              <input id="task-show-solution-code" type="checkbox" />
              <span>Code anzeigen (für code_random_complex und code_reading)</span>
            </label>
          </div>

          <div class="field checkbox-field">
            <label>
              <input id="task-manual-review-required" type="checkbox" />
              <span>Manuelle Prüfung nach Abgabe</span>
            </label>
            <div class="hint">Nur für nicht-iterative Aufgaben: Nach Abgabe bleibt der Task blau bis ein Admin ihn bewertet.</div>
          </div>
          
          <!-- Options Builder (for single/multiple choice) -->
          <div class="field" data-field="options-builder">
            <label>Antwortoptionen</label>
            <div id="task-options-container"></div>
            <div id="task-options-error" class="field-error" style="display:none;"></div>
          </div>
          
          
          <!-- Correct Answer (for code reading & code_random_complex) -->
          <div class="field" data-field="correct-answer">
            <label for="task-correct-answer">Erwartete Antwort (Variable oder Wert)</label>
            <input id="task-correct-answer" placeholder="z.B. 'result' oder '42'" />
            <div class="hint">Name der Variable deren Wert geprüft wird, oder direkt erwarteter Wert</div>
          </div>
          
          <!-- Variable Overrides (for code reading & code_random_complex) -->
          <div class="field" data-field="variable-overrides">
            <label>Iterationen (Variablenwerte)</label>
            <div id="task-var-overrides-builder" class="overrides-builder"></div>
            <div class="overrides-actions">
              <button type="button" class="hspf-btn" id="task-add-iteration">+ Iteration</button>
              <button type="button" class="hspf-btn" id="task-apply-overrides-json">JSON → Builder</button>
              <button type="button" class="hspf-btn" id="task-toggle-overrides-json">▼ JSON manuell bearbeiten</button>
            </div>
            <div id="task-var-overrides-json" class="overrides-json" style="display:none;">
              <label for="task-var-overrides" style="display:block; font-weight:bold;">JSON (Manual Edit):</label>
              <textarea id="task-var-overrides" placeholder='[{"inputs":{"a":1,"b":5},"expected":{"variable":"summe"}}]'></textarea>
            </div>
            <div class="hint" style="margin-top:8px;">
              <strong>💡 Für code_reading:</strong> NUR feste Wert-Sets (Iteration = Set-Reihenfolge)<br>
              <strong>Format:</strong> <code>[{"inputs":{"a":1},"expected":{"variable":"result"}}]</code><br>
              <strong>Im Code Template:</strong> <code>{varName}</code> verwenden. Beispiel: <code>x = {x}</code>
            </div>
          </div>
          
          <!-- Test Cases (for code tasks) -->
          <div class="field" data-field="test-cases-section">
            <label for="task-test-cases">Test Cases (JSON)</label>
            
            <!-- Test Cases GUI Builder -->
            <div class="test-cases-builder" id="test-cases-builder">
              <div id="tests-container" style="margin-bottom: 15px;"></div>
              
              <div class="builder-header">
                <label style="display: flex; align-items: center; gap: 10px;">
                  Test Type:
                  <select id="test-type-selector" style="padding: 4px 8px;">
                    <option value="output">OUTPUT (Console Output)</option>
                    <option value="function">FUNCTION (Return Value)</option>
                    <option value="variable">VARIABLE (Check Variables)</option>
                    <option value="intelligent">INTELLIGENT (Musterloesung)</option>
                    <option value="code_check">CODE CHECK (Keywords) ✨</option>
                  </select>
                </label>
                <button type="button" class="hspf-btn" id="add-test-btn">+ Add Test</button>
                <button type="button" class="hspf-btn" id="auto-description-btn" title="Auto-generate description from tests">📝 Auto Desc</button>
                <button type="button" class="hspf-btn hspf-btn-primary" id="generate-json-btn">↓ Generate JSON</button>
              </div>
            </div>
            
            <div style="margin-top:15px;">
              <button type="button" class="hspf-btn" onclick="document.getElementById('task-json-manual').style.display = document.getElementById('task-json-manual').style.display === 'none' ? 'block' : 'none'; this.textContent = this.textContent.includes('▼') ? '▲ JSON ausblenden' : '▼ JSON manuell bearbeiten';" style="font-size: 12px;">
                ▼ JSON manuell bearbeiten
              </button>
            </div>
            
            <div id="task-json-manual" style="display:none; margin-top:10px;">
              <label for="task-test-cases" style="display:block; font-weight:bold;">JSON (Manual Edit):</label>
              <textarea id="task-test-cases" placeholder='[{"input":"","expected":"output"}]'></textarea>
              <div class="hint">Format: [{"input":"test input","expected":"output"}]</div>
            </div>
          </div>
          
          <!-- Solution Code (for code & code_random_complex tasks) -->
          <div class="field" data-field="solution">
            <label for="task-solution">Solution Code <span style="color:#999; font-size:12px;">(code_random_complex nutzt Placeholder)</span></label>
            <textarea id="task-solution" placeholder="Für code/intentelligent: volle Musterlösung\nFür code_random_complex: result = int({binary}, 2)"></textarea>
            <div class="hint">
              <strong>Für code_random_complex:</strong> Verwende Placeholder <code>{varName}</code> für dynamische Werte<br>
              Beispiel: <code>result = int({binary}, 2)</code><br>
              <strong>Für code_reading:</strong> Solution Code bleibt leer
            </div>
          </div>
          
          <!-- Randomizer Code (for code_random_complex and intelligent tests) -->
          <div class="field" data-field="randomizer-code">
            <label for="task-randomizer-code" style="display:flex; align-items:center; gap:8px;">
              <span>Randomizer Code <span style="color:#999; font-size:12px;">(versteckt für Studenten)</span></span>
              <button type="button" class="hspf-btn hspf-btn-secondary" id="task-randomizer-generator" style="font-size:12px;">🎲 Randomizer</button>
            </label>
            <textarea id="task-randomizer-code" placeholder="import random\nbinary = format(random.randint(0, 255), '08b')\ncelsius = random.randint(-50, 50)"></textarea>
            <div class="hint">
              <strong>Nur bei:</strong> code_random_complex oder intelligent (mit Test Type)<br>
              <strong>Für code_random_complex:</strong> Erzeugt Variablen direkt (KEIN values dict!)<br>
              Beispiel: <code>binary = format(random.randint(0, 255), '08b')</code><br>
              <strong>Für intelligent tests:</strong> Generiert Testwerte (wird jedes Mal neu ausgeführt)
            </div>
          </div>
        </div>
        
        <div class="row-actions">
          <button class="hspf-btn hspf-btn-primary" type="submit" name="action" value="save">Speichern</button>
          <button class="hspf-btn hspf-btn-primary" type="submit" name="action" value="save-close">Speichern + Schließen</button>
          <button class="hspf-btn" type="button" id="task-create-cancel-btn">Abbrechen</button>
        </div>
      </form>
    </div>
  </div>

  <div id="task-modal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
      <div class="modal-header">
        <h3 id="modal-title">Edit Task</h3>
        <button id="close-modal-btn" class="modal-close">✕</button>
      </div>
      <form id="task-edit-form">
        <input type="hidden" id="edit-task-id" />
        
        <!-- Task Form Tabs -->
        <div class="task-tabs" role="tablist">
          <button class="task-tab active" type="button" data-task-tab="base">Basis</button>
          <button class="task-tab" type="button" data-task-tab="optional">Zusatz</button>
          <button class="task-tab" type="button" data-task-tab="validation">Prüfung</button>
        </div>
        
        <!-- Tab 1: Base Fields -->
        <div class="task-tab-panel active" data-task-tab-panel="base">
          <div class="field">
            <label for="edit-task-title">Title</label>
            <input id="edit-task-title" required />
          </div>
          
          <div class="field">
            <label for="edit-task-text">Aufgabe (Student-facing)</label>
            <textarea id="edit-task-text" placeholder="Die Aufgabenstellung / Frage für den Studierenden"></textarea>
            <div class="hint">Dies ist die zentrale Aufgabenbeschreibung, die dem Student angezeigt wird</div>
          </div>
          
          <!-- Task Type Selector -->
          <div class="field">
            <label for="edit-task-type">Task Type</label>
            <select id="edit-task-type">
              <option value="code">Code (Python)</option>
              <option value="code_ui">Code + UI</option>
              <option value="single_choice">Single-Choice</option>
              <option value="multiple_choice">Multiple-Choice</option>
              <option value="free_text">Freitext</option>
              <option value="code_reading">Code Reading</option>
              <option value="code_random_complex">Random Complex</option>
              <option value="db_model">Datenbank-Modell</option>
              <option value="file_submission">Dateiabgabe</option>
            </select>
          </div>

          <div class="field">
            <label for="edit-task-difficulty">Schwierigkeit</label>
            <select id="edit-task-difficulty">
              <option value="basic">Basic</option>
              <option value="medium" selected>Medium</option>
              <option value="hard">Hard</option>
            </select>
          </div>
          
          <!-- Code Template (for code and code_reading) -->
          <div class="field" data-field="code-template">
            <label for="edit-task-template">Code Template</label>
            <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
              <button type="button" class="hspf-btn hspf-btn-secondary" id="edit-task-random-snippet" style="font-size:12px;">🎲 randomNumbers</button>
              <button type="button" class="hspf-btn hspf-btn-secondary" id="edit-task-init-block-generator" style="font-size:12px;">📝 Init-Block einfügen</button>
            </div>
            <textarea id="edit-task-template" placeholder="Für code: def hello():\n    pass\n\nFür code_reading: {var} Platzhalter\nFür code_random_complex: result = int(values[\"binary\"], 2)"></textarea>
            <div class="hint">
              <strong>Für code:</strong> Starter-Code im Editor für Schüler<br>
              <strong>Für code_reading:</strong> Vorlage mit Platzhaltern <code>{varName}</code> (Werte via variable_overrides)<br>
              <strong>Für code_random_complex:</strong> Muss <code>values</code> verwenden (z.B. <code>values["binary"]</code>)
            </div>
          </div>
          
          <div class="field">
            <label for="edit-task-max-attempts">Max Versuche</label>
            <input id="edit-task-max-attempts" type="number" min="1" value="1" />
          </div>
          
          <div class="field" data-field="max-iterations">
            <label for="edit-task-max-iterations">Iterationen</label>
            <input id="edit-task-max-iterations" type="number" min="1" value="3" />
            <div class="hint">Für code_reading wird die Anzahl automatisch aus den Sets bestimmt, bei code_random_complex manuell über dieses Feld.</div>
          </div>

          <div class="field" data-field="file-submission-config">
            <label for="edit-task-file-submission-types">Dateiabgabe: erlaubte Typen</label>
            <input id="edit-task-file-submission-types" type="text" value="zip,png" placeholder="zip,png" />
            <label for="edit-task-file-submission-max-size" style="margin-top:8px;">Dateiabgabe: Max. Dateigröße</label>
            <select id="edit-task-file-submission-max-size">
              <option value="51200">50 KB</option>
              <option value="102400" selected>100 KB (Standard)</option>
              <option value="256000">250 KB</option>
              <option value="1048576">1 MB</option>
              <option value="2097152">2 MB</option>
              <option value="5242880">5 MB</option>
            </select>
          </div>
          
          <div class="field checkbox-field">
            <label>
              <input id="edit-task-folderstructure" type="checkbox" />
              <span>📁 Mit Ordnerstruktur</span>
            </label>
            <div class="hint">Schüler arbeiten mit einer Dateistruktur</div>
          </div>
          
          <div class="field checkbox-field">
            <label>
              <input id="edit-task-allowDownload" type="checkbox" />
              <span>� Download erlauben</span>
            </label>
            <div class="hint">Schüler können Dateien herunterladen</div>
          </div>
          
          <div class="field checkbox-field">
            <label>
              <input id="edit-task-allowCodeUiWebEdit" type="checkbox" />
              <span>🎨 Code-UI HTML+CSS editierbar</span>
            </label>
            <div class="hint">Bei code_ui-Aufgaben: Schüler können index.html und style.css bearbeiten</div>
          </div>
        </div>
        
        <!-- Tab 2: Optional Fields -->
        <div class="task-tab-panel" data-task-tab-panel="optional">
          <div class="field">
            <div style="display: flex; gap: 10px; margin-bottom: 8px;">
              <label for="edit-task-description">Description (Kontext/Metadaten)</label>
              <button type="button" id="edit-auto-description-btn" class="hspf-btn" style="padding: 4px 12px; font-size: 12px;">📝 Auto Desc</button>
            </div>
            <textarea id="edit-task-description" class="tinymce-editor" placeholder="Optionale Zusatzinformationen (nicht die Hauptaufgabe)"></textarea>
            <div class="hint">Für Admins: zusätzliche Dokumentation, Lernziele, Meta-Infos</div>
          </div>
          
          <!-- Image Upload (for quiz tasks) -->
          <div class="field" data-field="image-upload">
            <label for="edit-task-image">Bild hochladen (optional)</label>
            <input type="file" id="edit-task-image-upload" accept="image/*" style="margin-bottom: 8px;" />
            <div id="edit-task-image-preview"></div>
            <input type="hidden" id="edit-task-image-url" />
          </div>
          
          <!-- Hints -->
          <div data-field="hints-section">
            <div class="field">
              <label for="edit-task-hint1">Zusätzlicher Hinweis 1 (optional)</label>
              <textarea id="edit-task-hint1"></textarea>
            </div>
            <div class="field">
              <label for="edit-task-hint2">Zusätzlicher Hinweis 2 (optional)</label>
              <textarea id="edit-task-hint2"></textarea>
            </div>
            <div class="field">
              <label for="edit-task-hint3">Zusätzlicher Hinweis 3 (optional)</label>
              <textarea id="edit-task-hint3"></textarea>
            </div>
            <div class="field">
              <label for="edit-task-stoff">Lerninhalt/Stoff (optional)</label>
              <textarea id="edit-task-stoff" class="tinymce-editor" placeholder="Verwandte Lerninhalte, Ressourcen, etc."></textarea>
            </div>
          </div>
        </div>
        
        <!-- Tab 3: Validation Fields -->
        <div class="task-tab-panel" data-task-tab-panel="validation">
          <div class="field checkbox-field">
            <label>
              <input id="edit-task-show-solution" type="checkbox" checked />
              <span>Lösung anzeigen bei max Versuchen</span>
            </label>
          </div>
          
          <div class="field checkbox-field" data-field="show-solution-code">
            <label>
              <input id="edit-task-show-solution-code" type="checkbox" />
              <span>Code anzeigen (für code_random_complex und code_reading)</span>
            </label>
          </div>

          <div class="field checkbox-field">
            <label>
              <input id="edit-task-manual-review-required" type="checkbox" />
              <span>Manuelle Prüfung nach Abgabe</span>
            </label>
            <div class="hint">Nur für nicht-iterative Aufgaben: Nach Abgabe bleibt der Task blau bis ein Admin ihn bewertet.</div>
          </div>
          
          <!-- Dynamic Fields -->
        
          <!-- Options Builder (for single/multiple choice) -->
          <div class="field" data-field="options-builder">
            <label>Antwortoptionen</label>
            <div id="edit-task-options-container"></div>
            <div id="edit-task-options-error" class="field-error" style="display:none;"></div>
          </div>
          
          <!-- Correct Answer (for code reading & code_random_complex) -->
          <div class="field" data-field="correct-answer">
            <label for="edit-task-correct-answer">Erwartete Antwort (Variable oder Wert)</label>
            <input id="edit-task-correct-answer" placeholder="z.B. 'result' oder '42'" />
            <div class="hint">Name der Variable deren Wert geprüft wird, oder direkt erwarteter Wert</div>
          </div>
        
          <!-- Variable Overrides (for code reading) -->
          <div class="field" data-field="variable-overrides">
            <label>Iterationen (Variablenwerte für code_reading)</label>
            <div id="edit-task-var-overrides-builder" class="overrides-builder"></div>
            <div class="overrides-actions">
              <button type="button" class="hspf-btn" id="edit-task-add-iteration">+ Iteration</button>
              <button type="button" class="hspf-btn" id="edit-task-apply-overrides-json">JSON → Builder</button>
              <button type="button" class="hspf-btn" id="edit-task-toggle-overrides-json">▼ JSON manuell bearbeiten</button>
            </div>
            <div id="edit-task-var-overrides-json" class="overrides-json" style="display:none;">
              <label for="edit-task-var-overrides" style="display:block; font-weight:bold;">JSON (Manual Edit):</label>
              <textarea id="edit-task-var-overrides" placeholder='[{"inputs":{"a":1,"b":5},"expected":{"variable":"summe"}}]'></textarea>
            </div>
            <div class="hint" style="margin-top:8px;">
              <strong>💡 Für code_reading:</strong> NUR feste Wert-Sets (Iteration = Set-Reihenfolge)<br>
              <strong>Format:</strong> <code>[{"inputs":{"a":1},"expected":{"variable":"result"}}]</code><br>
              <strong>Im Code Template:</strong> <code>{varName}</code> verwenden. Beispiel: <code>x = {x}</code>
            </div>
          </div>
          
          <!-- Test Cases (for code tasks) -->
          <div class="field" data-field="test-cases-section">
            <label for="edit-task-test-cases">Test Cases (JSON)</label>
            
            <!-- Test Cases GUI Builder (Edit) -->
            <div class="test-cases-builder" id="edit-test-cases-builder">
              <div id="edit-tests-container" style="margin-bottom: 15px;"></div>
              
              <div class="builder-header">
                <label style="display: flex; align-items: center; gap: 10px;">
                  Test Type:
                  <select id="edit-test-type-selector" style="padding: 4px 8px;">
                    <option value="output">OUTPUT (Console Output)</option>
                    <option value="function">FUNCTION (Return Value)</option>
                    <option value="variable">VARIABLE (Check Variables)</option>
                    <option value="intelligent">INTELLIGENT (Musterloesung)</option>
                    <option value="code_check">CODE CHECK (Keywords) ✨</option>
                  </select>
                </label>
                <button type="button" class="hspf-btn" id="edit-add-test-btn">+ Add Test</button>
                <button type="button" class="hspf-btn" id="edit-auto-description-btn" title="Auto-generate description from tests">📝 Auto Desc</button>
                <button type="button" class="hspf-btn hspf-btn-primary" id="edit-generate-json-btn">↓ Generate JSON</button>
              </div>
            </div>
          
          <div style="margin-top:15px;">
            <button type="button" class="hspf-btn" onclick="document.getElementById('edit-json-manual').style.display = document.getElementById('edit-json-manual').style.display === 'none' ? 'block' : 'none'; this.textContent = this.textContent.includes('▼') ? '▲ JSON ausblenden' : '▼ JSON manuell bearbeiten';" style="font-size: 12px;">
              ▼ JSON manuell bearbeiten
            </button>
          </div>
          
          <div id="edit-json-manual" style="display:none; margin-top:10px;">
            <label for="edit-task-test-cases" style="display:block; font-weight:bold;">JSON (Manual Edit):</label>
            <textarea id="edit-task-test-cases" placeholder='[{"input":"","expected":"output"}]'></textarea>
            <div class="hint">Format: [{"input":"test input","expected":"output"}]</div>
          </div>
        </div>
        
        <!-- Solution Code (for code & code_random_complex tasks) -->
        <div class="field" data-field="solution">
          <label for="edit-task-solution">Solution Code <span style="color:#999; font-size:12px;">(code_random_complex nutzt Placeholder)</span></label>
          <textarea id="edit-task-solution" placeholder="Für code/intentelligent: volle Musterlösung\nFür code_random_complex: result = int({binary}, 2)"></textarea>
          <div class="hint">
            <strong>Für code & intelligent:</strong> Vollständige Musterlösung<br>
            <strong>Für code_random_complex:</strong> Verwende Placeholder <code>{varName}</code> für dynamische Werte<br>
            Beispiel: <code>result = int({binary}, 2)</code><br>
            <strong>Für code_reading:</strong> Solution Code bleibt leer
          </div>
        </div>
        
        <!-- Randomizer Code (for code_random_complex and intelligent tests) -->
        <div class="field" data-field="randomizer-code">
          <label for="edit-task-randomizer-code" style="display:flex; align-items:center; gap:8px;">
            <span>Randomizer Code <span style="color:#999; font-size:12px;">(versteckt für Studenten)</span></span>
            <button type="button" class="hspf-btn hspf-btn-secondary" id="edit-task-randomizer-generator" style="font-size:12px;">🎲 Randomizer</button>
          </label>
          <textarea id="edit-task-randomizer-code" placeholder="import random\nbinary = format(random.randint(0, 255), '08b')\ncelsius = random.randint(-50, 50)"></textarea>
          <div class="hint">
            <strong>Nur bei:</strong> code_random_complex oder intelligent (mit Test Type)<br>
            <strong>Für code_random_complex:</strong> Erzeugt Variablen direkt (KEIN values dict!)<br>
            Beispiel: <code>binary = format(random.randint(0, 255), '08b')</code><br>
            <strong>Für intelligent tests:</strong> Generiert Testwerte (wird jedes Mal neu ausgeführt)
          </div>
          </div>
        </div>
        </div>
        
        <div class="row-actions" style="margin-top: 20px;">
          <button class="hspf-btn hspf-btn-primary" type="submit" name="action" value="save">Speichern</button>
          <button class="hspf-btn hspf-btn-primary" type="submit" name="action" value="save-close">Speichern + Schließen</button>
          <button class="hspf-btn" type="button" id="cancel-modal-btn">Abbrechen</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    #task-modal textarea,
    #task-create-modal textarea,
    #assignment-modal textarea { min-height:120px; }

    /* Make placeholders in task create/edit flows clearly distinct from entered text */
    #pre-task-modal input::placeholder,
    #pre-task-modal textarea::placeholder,
    #task-create-modal input::placeholder,
    #task-create-modal textarea::placeholder,
    #task-modal input::placeholder,
    #task-modal textarea::placeholder {
      color: #b8c2cf;
      opacity: 1;
      font-style: italic;
    }

    #task-modal,
    #task-create-modal,
    #assignment-modal { animation: fadeIn 0.2s ease-in; }
    #task-create-modal .modal-content,
    #task-modal .modal-content {
      width: min(1200px, calc(100% - 32px));
      max-width: 1200px;
      margin: 16px auto;
    }
    .overrides-builder {
      display: flex;
      flex-direction: column;
      gap: 10px;
      padding: 10px;
      border: 1px solid var(--hspf-border);
      border-radius: 6px;
      background: var(--hspf-bg-secondary);
    }
    .override-iteration {
      background: #fff;
      border: 1px solid var(--hspf-border);
      border-radius: 6px;
      padding: 10px;
    }
    .override-iteration-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 13px;
      font-weight: 600;
      color: var(--hspf-text-secondary);
    }
    .override-variables {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .override-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .override-row input {
      flex: 1;
      min-width: 0;
    }
    .overrides-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 8px;
    }
    .overrides-json textarea {
      min-height: 120px;
    }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    
    /* Task AI Generator Modal Styling */
    #task-ai-modal .modal-content {
      display: flex;
      flex-direction: column;
      max-height: 90vh;
    }
    
    #task-ai-content {
      flex: 1;
      overflow-y: auto;
      padding: var(--hspf-spacing-md);
    }
    
    #task-ai-content > div:first-child {
      flex-shrink: 0;
    }
    
    @media (max-width: 1200px) {
      #task-ai-content > div:nth-child(2) {
        grid-template-columns: 1fr !important;
      }
    }
    
    .ai-task-form-section {
      background: var(--hspf-surface);
      padding: var(--hspf-spacing-md);
      border-radius: var(--hspf-radius-sm);
      border: 1px solid var(--hspf-border);
    }
    
    .ai-prompt-section {
      background: var(--hspf-bg-secondary);
      padding: var(--hspf-spacing-md);
      border-radius: var(--hspf-radius-sm);
      border: 2px solid var(--hspf-accent);
    }
    
    /* Task Form Tabs */
    .task-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
      border-bottom: 2px solid var(--hspf-border);
      padding-bottom: 0;
    }
    
    .task-tab {
      padding: 10px 18px;
      border: none;
      background: transparent;
      cursor: pointer;
      font-weight: 400;
      font-size: 14px;
      color: var(--hspf-text-secondary);
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      transition: var(--hspf-transition);
    }
    
    .task-tab:hover {
      color: var(--hspf-text-primary);
      background-color: var(--hspf-bg-secondary);
    }
    
    .task-tab.active {
      color: var(--hspf-text-primary);
      border-bottom-color: var(--hspf-accent);
      font-weight: 600;
    }
    
    .task-tab-panel {
      display: none;
    }
    
    .task-tab-panel.active {
      display: block;
    }

    /* Disabled field styles */
    input:disabled,
    select:disabled,
    textarea:disabled {
      background-color: var(--hspf-bg-secondary);
      color: var(--hspf-text-secondary);
      cursor: not-allowed;
      opacity: 0.7;
      border-color: var(--hspf-border);
    }
    
  </style>

  <!-- Task AI Generator Modal -->
  <div id="task-ai-modal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
      <div class="modal-header">
        <h3>✨ Task AI Generator</h3>
        <button id="task-ai-close-btn" class="modal-close">✕</button>
      </div>
      <div id="task-ai-content">
        <!-- Description -->
        <div class="admin-card" style="margin-bottom: var(--hspf-spacing-md);">
          <p style="margin: 0; color: var(--hspf-text-secondary); font-size: 14px;">
            Beschreiben Sie Ihre Aufgabe in natürlicher Sprache. Der KI-Prompt-Generator erzeugt dann ein gültiges JSON-Format, das Sie mit einer externen KI (z.B. Claude, ChatGPT) verwenden können, um die Aufgabe zu generieren.
          </p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--hspf-spacing-lg);">
          <!-- Left Column: Form -->
          <div class="ai-task-form-section">
            <h4 style="margin-top: 0; margin-bottom: var(--hspf-spacing-md);">1. Aufgabe beschreiben</h4>
            
            <div class="field">
              <label for="ai-task-type">Aufgabentyp</label>
              <select id="ai-task-type" style="width: 100%;">
                <option value="code">📝 Code (Python)</option>
                <option value="single_choice">🎯 Single-Choice</option>
                <option value="multiple_choice">☑️ Multiple-Choice</option>
                <option value="free_text">📄 Freitext</option>
                <option value="code_reading">👀 Code-Lesequest</option>
                <option value="code_random_complex">🎲 Code (versteckt)</option>
              </select>
            </div>

            <div class="field">
              <label for="ai-task-title">Titel (optional - wird von KI generiert, wenn leer)</label>
              <input type="text" id="ai-task-title" placeholder="z.B. 'Schaltjahr prüfen' (optional)" style="width: 100%;" />
            </div>

            <div class="field">
              <label for="ai-task-topic">Thema/Topic für die KI</label>
              <textarea id="ai-task-topic" placeholder="z.B. 'Array Slicing', 'Rekursion mit Fibonacci', 'String-Manipulation', 'Dictionary Comprehensions'" style="width: 100%; min-height: 100px; resize: vertical;"></textarea>
              <small style="color: var(--hspf-text-secondary); margin-top: 4px; display: block;">💡 Die KI erstellt basierend auf diesem Thema die komplette Aufgabenbeschreibung.</small>
            </div>

            <div class="field">
              <label for="ai-attempts">Max. Versuche</label>
              <input type="number" id="ai-attempts" value="3" min="1" style="width: 100%;" />
            </div>

            <div class="field checkbox-field">
              <label style="display: flex; align-items: center;">
                <input type="checkbox" id="ai-with-hints" checked />
                <span style="margin-left: 8px;">Mit Hinweisen generieren</span>
              </label>
            </div>

            <div class="field checkbox-field">
              <label style="display: flex; align-items: center;">
                <input type="checkbox" id="ai-with-solution" checked />
                <span style="margin-left: 8px;">Mit Lösungscode</span>
              </label>
            </div>

            <button class="hspf-btn hspf-btn-primary" id="generate-prompt-btn" style="width: 100%; margin-top: var(--hspf-spacing-md);">→ Prompt generieren</button>
          </div>

          <!-- Right Column: Prompt Preview -->
          <div class="ai-prompt-section">
            <h4 style="margin-top: 0; margin-bottom: var(--hspf-spacing-md);">2. KI-Prompt & Import</h4>
            
            <!-- Tabs for Prompt / JSON Import -->
            <div style="display: flex; gap: var(--hspf-spacing-sm); margin-bottom: var(--hspf-spacing-md);">
              <button class="hspf-btn" id="ai-tab-prompt" style="flex: 1; background: var(--hspf-accent); color: white; font-weight: bold; cursor: pointer; border: none; padding: 10px;">📋 Prompt</button>
              <button class="hspf-btn" id="ai-tab-import" style="flex: 1; background: var(--hspf-bg-secondary); color: var(--hspf-text-primary); cursor: pointer; border: 1px solid var(--hspf-border); padding: 10px; font-weight: normal;">📥 JSON Import</button>
            </div>
            
            <!-- Prompt Display -->
            <div id="ai-prompt-section" style="display: block;">
              <div style="border: 1px solid var(--hspf-border); border-radius: var(--hspf-radius-sm); background: var(--hspf-surface); padding: var(--hspf-spacing-md); min-height: 300px; max-height: 400px; overflow-y: auto; position: relative;">
                <pre id="prompt-preview" style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; font-family: 'Monaco', monospace; line-height: 1.4;">
(Prompt wird hier angezeigt)
                </pre>
                <button class="hspf-btn hspf-btn-secondary" id="copy-prompt-btn" style="position: absolute; top: var(--hspf-spacing-sm); right: var(--hspf-spacing-sm); font-size: 12px; padding: 6px 12px;">📋 Copy</button>
              </div>

              <div style="margin-top: var(--hspf-spacing-md); padding: var(--hspf-spacing-sm); background: var(--hspf-surface); border-left: 4px solid var(--hspf-accent); border-radius: var(--hspf-radius-sm);">
                <p style="margin: 0; font-size: 13px; color: var(--hspf-text-secondary); line-height: 1.5;">
                  💡 <strong>Hinweis:</strong> Kopiere den Prompt und füge ihn in Claude, ChatGPT oder eine andere KI ein. Die KI soll das JSON generieren, das du dann im Tab "📥 JSON Import" importieren kannst.
                </p>
              </div>
            </div>

            <!-- JSON Import Section -->
            <div id="ai-import-section" style="display: none;">
              <textarea id="ai-generated-json" placeholder="Kopiere hier das generierte JSON von der KI ein..." style="width: 100%; min-height: 300px; font-family: 'Monaco', monospace; font-size: 12px; resize: vertical;"></textarea>
              
              <div id="ai-json-error" style="margin-top: var(--hspf-spacing-sm); padding: var(--hspf-spacing-sm); background: #fee; border: 1px solid #f99; border-radius: var(--hspf-radius-sm); color: #c33; font-size: 12px; display: none;"></div>
              
              <button class="hspf-btn hspf-btn-primary" id="import-generated-json-btn" style="width: 100%; margin-top: var(--hspf-spacing-md);">✓ Validieren & Importieren</button>
              
              <div style="margin-top: var(--hspf-spacing-md); padding: var(--hspf-spacing-sm); background: var(--hspf-surface); border-left: 4px solid #4a9; border-radius: var(--hspf-radius-sm);">
                <p style="margin: 0; font-size: 13px; color: var(--hspf-text-secondary); line-height: 1.5;">
                  ✓ Das JSON wird validiert und direkt in die aktuelle Aufgabensammlung importiert.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div style="margin-top: var(--hspf-spacing-lg); text-align: right; padding-top: var(--hspf-spacing-md); border-top: 1px solid var(--hspf-border);">
          <button class="hspf-btn" id="task-ai-cancel-btn">Schließen</button>
        </div>
      </div>
    </div>
  </div>

  <div id="deploy-sync-confirm-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Sicherheitsabfrage: Beta -> Live</h3>
        <button id="deploy-sync-confirm-close" class="modal-close" type="button">✕</button>
      </div>
      <div class="modal-body" style="padding: var(--hspf-spacing-lg);">
        <p style="margin-top: 0;">Sie sind dabei, den Modus <strong>Beta -> Live</strong> auszufuehren.</p>
        <p>Bitte geben Sie zur Bestaetigung exakt <strong>PROMOTE LIVE</strong> ein.</p>
        <div class="field" style="margin-bottom: var(--hspf-spacing-md);">
          <label for="deploy-sync-confirm-input">Sicherheitscode</label>
          <input type="text" id="deploy-sync-confirm-input" placeholder="PROMOTE LIVE" autocomplete="off">
        </div>
        <div class="row-actions">
          <button type="button" class="hspf-btn" id="deploy-sync-confirm-cancel">Abbrechen</button>
          <button type="button" class="hspf-btn hspf-btn-primary" id="deploy-sync-confirm-run" disabled>Deploy starten</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JSZip Library for ZIP export/import -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

  <script src="js/task-type-manager.js"></script>
  <script src="js/options-builder.js"></script>
  <script src="js/export-tasks.js"></script>
  <script src="js/import-tasks.js"></script>
  <script src="js/task-ai-generator.js"></script>
  <script src="js/admin-dashboard.js?v=20260506a"></script>
  <script src="js/admin-teams-users.js?v=20260423c"></script>
</body>
</html>
