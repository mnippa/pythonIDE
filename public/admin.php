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
      overflow: auto;
      animation: fadeIn 0.2s ease-in;
    }
    
    .modal.active {
      display: block;
    }
    
    .modal-content {
      background: var(--hspf-surface);
      border-radius: var(--hspf-radius-md);
      padding: var(--hspf-spacing-xl);
      max-width: 800px;
      margin: 40px auto;
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
    <a class="hspf-btn hspf-btn-ghost" href="dashboard.php">← Dashboard</a>
    <button class="hspf-btn hspf-btn-ghost" id="logout-btn">Logout</button>
  ';
  include(__DIR__ . '/../components/header.php');
  ?>
  
  <div class="admin-container">

    <div class="tabs" role="tablist">
      <button class="tab active" data-tab="projects">Projects</button>
      <button class="tab" data-tab="assignments">Assignments</button>
      <button class="tab" data-tab="users">Users</button>
    </div>

    <section class="panel active" id="tab-projects">
      <div class="admin-card">
        <h2>Projects</h2>
        <div class="admin-card-subtitle">Alle Projekte mit Besitzer. Löschen ist Admin-only.</div>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Owner</th>
                <th>Visibility</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="projects-body"></tbody>
          </table>
        </div>
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
                <th class="sortable" data-sort="difficulty">Difficulty</th>
                <th class="sortable" data-sort="is_active">Active</th>
                <th class="sortable" data-sort="task_count">Tasks</th>
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
        <div style="overflow:auto;">
          <table id="tasks-table">
            <thead>
              <tr>
                <th style="width: 40px;"><input type="checkbox" id="select-all-tasks" title="Select All"></th>
                <th>Pos</th>
                <th>Title</th>
                <th>Type</th>
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
          <button class="hspf-btn hspf-btn-secondary" type="button" id="import-task-btn" style="margin-left:var(--hspf-spacing-sm);">Import Tasks (JSON)</button>
          <button class="hspf-btn hspf-btn-secondary" type="button" id="export-tasks-btn" style="margin-left:var(--hspf-spacing-sm);">Export Selected Tasks (JSON)</button>
          <input type="file" id="import-task-file-input" accept=".json" style="display:none;">
        </div>
      </div>
    </section>

    <section class="panel" id="tab-users">
      <div class="admin-card">
        <h2>Users</h2>
        <div class="admin-card-subtitle">Set status to aktiv or archiviert.</div>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Name</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="users-body"></tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div id="assignment-modal" class="modal">
    <div class="modal-content">
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
        <div class="row-actions">
          <button class="hspf-btn hspf-btn-primary" type="submit">Save</button>
          <button class="hspf-btn" type="button" id="assignment-cancel">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div id="task-create-modal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
      <div class="modal-header">
        <h3>New Task</h3>
        <button id="task-create-close-btn" class="modal-close">✕</button>
      </div>
      <form id="task-form">
        <div class="field">
          <label for="task-title">Title</label>
          <input id="task-title" />
        </div>
        <div class="field">
          <label for="task-description">Description</label>
          <textarea id="task-description"></textarea>
        </div>
        <div class="field">
          <label for="task-position">Position</label>
          <input id="task-position" type="number" min="1" />
        </div>
        <div class="field">
          <label for="task-type">Problem type</label>
          <select id="task-type">
            <option value="code_completion">code_completion</option>
            <option value="code_fix">code_fix</option>
            <option value="multiple_choice">multiple_choice</option>
            <option value="essay">essay</option>
          </select>
        </div>
        <div class="field">
          <label for="task-template">Code template</label>
          <textarea id="task-template"></textarea>
        </div>
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
          <textarea id="task-stoff" placeholder="Verwandte Lerninhalte, Ressourcen, etc."></textarea>
        </div>
        <div class="field">
          <label for="task-validation-mode">Validation Mode</label>
          <select id="task-validation-mode">
            <option value="">-- none --</option>
            <option value="strict">strict (exact match)</option>
            <option value="loose">loose (whitespace tolerant)</option>
          </select>
        </div>
        <div class="field">
          <label for="task-test-cases">Test Cases (JSON)</label>
          
          <!-- Test Cases GUI Builder -->
          <div class="test-cases-builder" id="test-cases-builder">
            <div class="builder-header">
              <label>Test Type:</label>
              <select id="test-type-selector" style="margin-left:10px; padding:4px 8px;">
                <option value="output">OUTPUT (Console Output)</option>
                <option value="function">FUNCTION (Return Value)</option>
                <option value="variable">VARIABLE (Check Variables)</option>
                <option value="intelligent">INTELLIGENT (Musterloesung)</option>
                <option value="code_check">CODE CHECK (Keywords) ✨</option>
              </select>
              <button type="button" class="hspf-btn" id="add-test-btn" style="margin-left:10px;">+ Add Test</button>
              <button type="button" class="hspf-btn hspf-btn-primary" id="generate-json-btn" style="margin-left:10px;">↓ Generate JSON</button>
            </div>
            
            <div id="tests-container" style="margin-top:15px;"></div>
          </div>
          
          <label for="task-test-cases" style="margin-top:20px; display:block;">JSON (Manual Edit):</label>
          <textarea id="task-test-cases" placeholder='[{"input":"","expected":"output"}]'></textarea>
          <div class="hint">Format: [{"input":"test input","expected":"expected output"}]</div>
        </div>
        <div class="field">
          <label for="task-solution">Solution Code</label>
          <textarea id="task-solution" placeholder="Musterlösung"></textarea>
        </div>
        <div class="row-actions">
          <button class="hspf-btn hspf-btn-primary" type="submit">Add Task</button>
          <button class="hspf-btn" type="button" id="task-create-cancel-btn">Cancel</button>
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
        <div class="field">
          <label for="edit-task-title">Title</label>
          <input id="edit-task-title" required />
        </div>
        <div class="field">
          <label for="edit-task-description">Description</label>
          <textarea id="edit-task-description"></textarea>
        </div>
        <div class="field">
          <label for="edit-task-position">Position</label>
          <input id="edit-task-position" type="number" min="1" />
        </div>
        <div class="field">
          <label for="edit-task-type">Problem type</label>
          <select id="edit-task-type">
            <option value="code_completion">code_completion</option>
            <option value="code_fix">code_fix</option>
            <option value="multiple_choice">multiple_choice</option>
            <option value="essay">essay</option>
          </select>
        </div>
        <div class="field">
          <label for="edit-task-template">Code template</label>
          <textarea id="edit-task-template"></textarea>
        </div>
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
          <textarea id="edit-task-stoff" placeholder="Verwandte Lerninhalte, Ressourcen, etc."></textarea>
        </div>
        <div class="field">
          <label for="edit-task-validation-mode">Validation Mode</label>
          <select id="edit-task-validation-mode">
            <option value="">-- none --</option>
            <option value="strict">strict (exact match)</option>
            <option value="loose">loose (whitespace tolerant)</option>
          </select>
        </div>
        <div class="field">
          <label for="edit-task-test-cases">Test Cases (JSON)</label>
          
          <!-- Test Cases GUI Builder (Edit) -->
          <div class="test-cases-builder" id="edit-test-cases-builder">
            <div class="builder-header">
              <label>Test Type:</label>
              <select id="edit-test-type-selector" style="margin-left:10px; padding:4px 8px;">
                <option value="output">OUTPUT (Console Output)</option>
                <option value="function">FUNCTION (Return Value)</option>
                <option value="variable">VARIABLE (Check Variables)</option>
                <option value="intelligent">INTELLIGENT (Musterloesung)</option>
                <option value="code_check">CODE CHECK (Keywords) ✨</option>
              </select>
              <button type="button" class="hspf-btn" id="edit-add-test-btn" style="margin-left:10px;">+ Add Test</button>
              <button type="button" class="hspf-btn hspf-btn-primary" id="edit-generate-json-btn" style="margin-left:10px;">↓ Generate JSON</button>
            </div>
            
            <div id="edit-tests-container" style="margin-top:15px;"></div>
          </div>
          
          <label for="edit-task-test-cases" style="margin-top:20px; display:block;">JSON (Manual Edit):</label>
          <textarea id="edit-task-test-cases" placeholder='[{"input":"","expected":"output"}]'></textarea>
          <div class="hint">Format: [{"input":"test input","expected":"expected output"}]</div>
        </div>
        <div class="field">
          <label for="edit-task-solution">Solution Code</label>
          <textarea id="edit-task-solution" placeholder="Musterlösung"></textarea>
        </div>
        <div class="row-actions">
          <button class="hspf-btn hspf-btn-primary" type="submit">Save Changes</button>
          <button class="hspf-btn" type="button" id="cancel-modal-btn">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    #task-modal textarea,
    #task-create-modal textarea,
    #assignment-modal textarea { min-height:120px; }
    #task-modal,
    #task-create-modal,
    #assignment-modal { animation: fadeIn 0.2s ease-in; }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
  </style>

  <script src="js/admin-dashboard.js"></script>
</body>
</html>
