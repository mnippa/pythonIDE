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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:#f6f1ea;
      --panel:#fffaf4;
      --ink:#1c1b1a;
      --muted:#6c6762;
      --accent:#0f766e;
      --accent-2:#f59e0b;
      --border:#e6ddd4;
      --shadow:0 18px 60px rgba(15, 23, 42, 0.12);
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family:"Space Grotesk", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
      color:var(--ink);
      background:radial-gradient(1200px 600px at 20% -10%, #e5f6f2, transparent),
                 radial-gradient(900px 500px at 110% 10%, #fef3c7, transparent),
                 var(--bg);
      min-height:100vh;
      padding:24px;
    }
    .shell{
      max-width:1200px;
      margin:0 auto;
    }
    .topbar{
      display:flex;
      align-items:center;
      gap:16px;
      background:var(--panel);
      border:1px solid var(--border);
      border-radius:12px;
      padding:12px 16px;
      box-shadow:var(--shadow);
    }
    .brand{
      font-weight:700;
      letter-spacing:0.4px;
    }
    .spacer{ flex:1; }
    .user{
      font-size:14px;
      color:var(--muted);
      display:flex;
      align-items:center;
      gap:10px;
    }
    .badge{
      padding:3px 8px;
      background:var(--accent);
      color:#fff;
      border-radius:6px;
      font-size:11px;
      font-weight:600;
      text-transform:uppercase;
    }
    .btn{
      border:1px solid var(--border);
      background:var(--panel);
      padding:8px 12px;
      border-radius:8px;
      cursor:pointer;
      font-weight:600;
    }
    .btn.primary{
      background:var(--accent);
      color:#fff;
      border-color:transparent;
    }
    .btn.warn{
      background:#b91c1c;
      color:#fff;
      border-color:transparent;
    }
    .btn.ghost{
      background:transparent;
    }
    .tabs{
      display:flex;
      gap:8px;
      margin:20px 0 12px;
    }
    .tab{
      padding:8px 12px;
      border-radius:999px;
      border:1px solid var(--border);
      background:var(--panel);
      cursor:pointer;
      font-weight:600;
      font-size:14px;
    }
    .tab.active{
      background:var(--accent);
      color:#fff;
      border-color:transparent;
    }
    .panel{
      display:none;
      background:var(--panel);
      border:1px solid var(--border);
      border-radius:16px;
      padding:16px;
      box-shadow:var(--shadow);
    }
    .panel.active{ display:block; }
    .grid{
      display:grid;
      gap:16px;
    }
    .grid.two{ grid-template-columns: 1.2fr 0.8fr; }
    .card{
      border:1px solid var(--border);
      border-radius:12px;
      padding:12px;
      background:#fff;
    }
    h2,h3{ margin:0 0 10px; }
    table{
      width:100%;
      border-collapse:collapse;
      font-size:14px;
    }
    th,td{
      text-align:left;
      padding:8px 6px;
      border-bottom:1px solid var(--border);
      vertical-align:top;
    }
    th{ color:var(--muted); font-weight:600; }
    .muted{ color:var(--muted); font-size:12px; }
    .row-actions{ display:flex; gap:6px; flex-wrap:wrap; }
    .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
    .field label{ font-size:12px; color:var(--muted); }
    .field input, .field select, .field textarea{
      padding:8px 10px;
      border:1px solid var(--border);
      border-radius:8px;
      font-size:14px;
      font-family:inherit;
    }
    .field textarea{ min-height:80px; resize:vertical; }
    .hint{ font-size:12px; color:var(--muted); }
    .tag{ padding:2px 6px; border-radius:6px; background:#fef3c7; font-size:11px; }
    .mono{ font-family:"IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; }
    .status{ padding:2px 8px; border-radius:999px; font-size:11px; background:#e5f6f2; }
    .status.arch{ background:#fde2e2; }
    
    /* Test Cases Builder GUI */
    .test-cases-builder{ 
      background:#ffffff; 
      border:1px solid #e5e7eb; 
      border-radius:8px; 
      padding:16px; 
      margin-bottom:12px;
    }
    .builder-header{ 
      display:flex; 
      align-items:center; 
      flex-wrap:wrap; 
      gap:8px;
      padding-bottom:12px;
      border-bottom:2px solid #e5e7eb;
    }
    .test-case-item{
      transition: all 0.2s ease;
    }
    .test-case-item:hover{
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .btn-remove-test:hover{
      background:#dc2626 !important;
    }
    
    @media (max-width: 980px){
      .grid.two{ grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="brand">Python IDE Admin</div>
      <div class="spacer"></div>
      <div class="user">
        <span><?= htmlspecialchars($displayName) ?></span>
        <span class="badge">Admin</span>
        <button class="btn ghost" id="logout-btn">Logout</button>
        <a class="btn" href="editor.php">Back to editor</a>
      </div>
    </div>

    <div class="tabs" role="tablist">
      <button class="tab active" data-tab="projects">Projects</button>
      <button class="tab" data-tab="assignments">Assignments</button>
      <button class="tab" data-tab="users">Users</button>
    </div>

    <section class="panel active" id="tab-projects">
      <div class="card">
        <h2>Projects</h2>
        <div class="muted">Alle Projekte mit Besitzer. Loeschen ist Admin-only.</div>
        <div style="margin-top:10px; overflow:auto;">
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
      <div class="card">
        <h2>Assignments</h2>
        <div class="muted">Create, update, delete assignments.</div>
        <div style="margin-top:10px; overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Difficulty</th>
                <th>Active</th>
                <th>Tasks</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="assignments-body"></tbody>
          </table>
        </div>
        <div style="margin-top:16px;">
          <button class="btn primary" type="button" id="open-assignment-modal">+ New Assignment</button>
        </div>
      </div>

      <div class="card" style="margin-top:16px;">
        <h3 id="tasks-title">Tasks</h3>
        <div class="muted" id="tasks-hint">Select an assignment to manage tasks.</div>
        <div style="margin-top:10px; overflow:auto;">
          <table>
            <thead>
              <tr>
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
        <div style="margin-top:12px;">
          <button class="btn primary" type="button" id="open-task-modal">+ New Task</button>
        </div>
      </div>
    </section>

    <section class="panel" id="tab-users">
      <div class="card">
        <h2>Users</h2>
        <div class="muted">Set status to aktiv or archiviert.</div>
        <div style="margin-top:10px; overflow:auto;">
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

  <div id="assignment-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; padding:20px; overflow:auto;">
    <div style="background:var(--panel); border-radius:12px; padding:20px; max-width:600px; margin:40px auto; border:1px solid var(--border);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 id="assignment-modal-title" style="margin:0;">New Assignment</h3>
        <button id="assignment-close-btn" class="btn ghost" style="font-size:20px;">✕</button>
      </div>
      <form id="assignment-form">
        <input type="hidden" id="assignment-id" />
        <div class="field">
          <label for="assignment-title">Title</label>
          <input id="assignment-title" required />
        </div>
        <div class="field">
          <label for="assignment-description">Description</label>
          <textarea id="assignment-description"></textarea>
        </div>
        <div class="field">
          <label for="assignment-difficulty">Difficulty</label>
          <select id="assignment-difficulty">
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <div class="field">
          <label for="assignment-active">Active</label>
          <select id="assignment-active">
            <option value="true">true</option>
            <option value="false">false</option>
          </select>
        </div>
        <div class="row-actions">
          <button class="btn primary" type="submit">Save</button>
          <button class="btn" type="button" id="assignment-cancel">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div id="task-create-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; padding:20px; overflow:auto;">
    <div style="background:var(--panel); border-radius:12px; padding:20px; max-width:800px; margin:40px auto; border:1px solid var(--border);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0;">New Task</h3>
        <button id="task-create-close-btn" class="btn ghost" style="font-size:20px;">✕</button>
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
          <label for="task-hint">Hint</label>
          <textarea id="task-hint"></textarea>
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
          <label for="task-expected">Expected output</label>
          <textarea id="task-expected"></textarea>
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
                <option value="code_check">CODE CHECK (Keywords) ✨</option>
              </select>
              <button type="button" class="btn" id="add-test-btn" style="margin-left:10px;">+ Add Test</button>
              <button type="button" class="btn primary" id="generate-json-btn" style="margin-left:10px;">↓ Generate JSON</button>
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
          <button class="btn primary" type="submit">Add Task</button>
          <button class="btn" type="button" id="task-create-cancel-btn">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div id="task-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; padding:20px; overflow:auto;">
    <div style="background:var(--panel); border-radius:12px; padding:20px; max-width:600px; margin:40px auto; border:1px solid var(--border);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 id="modal-title" style="margin:0;">Edit Task</h3>
        <button id="close-modal-btn" class="btn ghost" style="font-size:20px;">✕</button>
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
          <label for="edit-task-hint">Hint</label>
          <textarea id="edit-task-hint"></textarea>
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
          <label for="edit-task-expected">Expected output</label>
          <textarea id="edit-task-expected"></textarea>
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
                <option value="code_check">CODE CHECK (Keywords) ✨</option>
              </select>
              <button type="button" class="btn" id="edit-add-test-btn" style="margin-left:10px;">+ Add Test</button>
              <button type="button" class="btn primary" id="edit-generate-json-btn" style="margin-left:10px;">↓ Generate JSON</button>
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
          <button class="btn primary" type="submit">Save Changes</button>
          <button class="btn" type="button" id="cancel-modal-btn">Cancel</button>
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
