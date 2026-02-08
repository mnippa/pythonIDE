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
      <div class="grid two">
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
        </div>
        <div class="card">
          <h3 id="assignment-form-title">New Assignment</h3>
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
              <button class="btn" type="button" id="assignment-reset">Reset</button>
            </div>
          </form>
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
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="tasks-body"></tbody>
          </table>
        </div>
        <div style="margin-top:12px;">
          <h4>New Task</h4>
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
              <label for="task-expected">Expected output</label>
              <textarea id="task-expected"></textarea>
            </div>
            <div class="row-actions">
              <button class="btn primary" type="submit">Add Task</button>
            </div>
          </form>
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

  <script src="js/admin-dashboard.js"></script>
</body>
</html>
