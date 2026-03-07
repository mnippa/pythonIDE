<?php
/**
 * Public shared project view (read-only)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/auth/middleware.php';

$shareToken = isset($_GET['token']) ? trim($_GET['token']) : null;

if ($shareToken && isLoggedIn()) {
  header('Location: projects.php?token=' . urlencode($shareToken));
  exit;
}

if (!$shareToken) {
    die('Ungültiger Share-Link');
}

// Load project via token
$conn = getDbConnection();
$stmt = $conn->prepare('
    SELECT p.*, u.email as owner_email, u.first_name, u.last_name
    FROM projects p
    JOIN users u ON p.user_id = u.id
    WHERE p.share_token = ? AND p.visibility = ?
');
$visibility = 'public';
$stmt->bind_param('ss', $shareToken, $visibility);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Projekt nicht gefunden oder nicht öffentlich');
}

$project = $result->fetch_assoc();

// Build owner display name
$ownerName = trim(($project['first_name'] ?? '') . ' ' . ($project['last_name'] ?? ''));
if ($ownerName === '') {
    $ownerName = $project['owner_email'];
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($project['name']) ?> - Python IDE</title>
  <link rel="stylesheet" href="css/ide.css">
  <style>
    :root {
      --border:#e5e7eb; --muted:#6b7280; --bg:#fff; --panel:#f9fafb;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --code-bg: #f3f4f6;
      --code-color: #1f2937;
      --inline-code-bg: #e5e7eb;
      --help-bg: #ffffff;
      --help-text: #1f2937;
    }
    
    html.dark-mode {
      --border:#374151; --muted:#9ca3af; --bg:#1e1e1e; --panel:#252526;
      --text-primary: #e6edf3;
      --text-secondary: #8b949e;
      --code-bg: #0d1117;
      --code-color: #e6edf3;
      --inline-code-bg: #161b22;
      --help-bg: #1e1e1e;
      --help-text: #e6edf3;
    }
    
    *{ box-sizing:border-box; }
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--text-primary); transition:background 0.2s, color 0.2s; }

    .toolbar{
      display:flex; gap:12px; align-items:center; flex-wrap:wrap;
      padding:10px; border-bottom:1px solid var(--border);
      background:var(--bg);
    }
    .toolbar button{ padding:8px 12px; cursor:pointer; background:var(--panel); color:var(--text-primary); border:1px solid var(--border); border-radius:4px; transition:background 0.2s; }
    .toolbar button:hover{ background:var(--text-secondary); opacity:0.7; }
    #settings-toggle{ width:34px; height:34px; padding:0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; }
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; transition:background 0.3s; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; display:block; width:20px; height:20px; line-height:20px; transition:transform 0.3s; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }
    .toolcheck{ display:flex; gap:6px; align-items:center; padding:6px 10px; border:1px solid var(--border); border-radius:999px; background:var(--panel); color:var(--text-primary); }
    .toolcheck input{ transform: translateY(0.5px); }

    .settings-panel{
      position:fixed;
      top:58px;
      right:10px;
      z-index:120;
      background:var(--panel);
      color:var(--text-primary);
      border:1px solid var(--border);
      border-radius:10px;
      padding:10px;
      min-width:190px;
      display:none;
      flex-direction:column;
      gap:8px;
      box-shadow:0 12px 30px rgba(0,0,0,0.12);
    }
    .settings-panel.open{ display:flex; }
    .settings-title{
      font-size:12px;
      font-weight:300;
      letter-spacing:0.04em;
      text-transform:uppercase;
      color:var(--text-secondary);
    }

    /* MASTER GRID: 75% left / 25% right */
    .app{
      height: calc(100vh - 52px);
      display:grid;
      grid-template-columns: 75% 25%;
      min-height:0;
      min-width:0;
    }

    @media (min-width: 1201px) {
      .app {
        grid-template-columns: minmax(0, 800px) minmax(280px, 1fr);
      }
    }

    /* LEFT COLUMN: editor top, bottom tools (lint+help) */
    .left{
      border-right:1px solid var(--border);
      display:grid;
      grid-template-rows: 1fr 180px;
      min-width:0; min-height:0;
    }
    #editor-container{ width:100%; height:100%; min-width:0; min-height:0; }

    .left-bottom{
      border-top:1px solid var(--border);
      display:grid;
      grid-template-columns: 40% 60%;
      min-width:0; min-height:0;
    }
    #lint-container{
      border-right:1px solid var(--border);
      background:var(--bg);
      color:var(--text-primary);
      padding:10px;
      overflow:auto;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
      min-width:0; min-height:0;
    }
    
    html.dark-mode #lint-container {
      background: #252526;
      color: #cccccc;
    }
    #lint-container .lint-checking{ color:var(--text-secondary); font-weight:250; }
    #lint-container .lint-ok{ color:var(--text-primary); font-weight:600; }
    #lint-container .lint-checkmark{ color:#22c55e; font-weight:300; }
    #lint-container .lint-fix-label{ color:var(--text-primary); font-weight:600; }
    #lint-container .lint-fix-link{ cursor:pointer; text-decoration:underline; color:#2563eb; }
    html.dark-mode #lint-container .lint-fix-link{ color:#60a5fa; }
    #help-container{
      padding:6px 8px;
      overflow:auto;
      background:var(--help-bg);
      color:var(--help-text);
      font-size:14px;
      line-height:1.6;
      min-width:0; min-height:0;
    }
    #help-container h1, #help-container h2, #help-container h3{
      margin:2px 0 6px 0;
      padding:0;
      font-size:1em;
    }
    #help-container p{
      margin:4px 0;
      padding:0;
    }
    #help-container .help-muted{ color:var(--text-secondary); margin:0; padding:0; }
    #help-container pre{
      background:var(--code-bg);
      color:var(--code-color);
      padding:8px;
      border-radius:4px;
      overflow-x:auto;
      margin:4px 0;
      border-left:2px solid var(--border);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:0.9em;
      line-height:1.4;
    }
    #help-container code{
      background:var(--inline-code-bg);
      color:var(--code-color);
      padding:2px 6px;
      border-radius:4px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:0.9em;
    }
    #help-container strong{
      color:var(--text-primary);
      font-weight:600;
    }
    #help-container a{
      color:#3b82f6;
      text-decoration:none;
    }
    #help-container a:hover{
      text-decoration:underline;
    }

    /* Autocomplete */
    .monaco-editor .suggest-widget{
      z-index:100 !important;
      opacity:0.9 !important;
      background:rgba(245, 245, 250, 0.95) !important;
      border:1px solid rgba(180, 180, 190, 0.9) !important;
      color:#333 !important;
    }
    .editor-widget.suggest-widget{
      z-index:100 !important;
      opacity:0.9 !important;
      background:rgba(245, 245, 250, 0.95) !important;
    }
    .monaco-editor .suggest-widget .monaco-list-row{
      background:rgba(245, 245, 250, 0.95) !important;
      color:#333 !important;
    }
    .monaco-editor .suggest-widget .monaco-list-row:hover{
      background:rgba(230, 235, 245, 0.95) !important;
    }
    .monaco-editor .suggest-widget .monaco-list-row.selected{
      background:rgba(220, 230, 245, 0.95) !important;
    }
    .monaco-editor .suggest-widget-details{
      background:rgba(245, 245, 250, 0.95) !important;
    }

    /* RIGHT COLUMN: output top + plot bottom */
    .right{
      display:grid;
      grid-template-rows: 1fr 1fr;
      min-width:0; min-height:0;
      gap: 0;
    }
    
    /* GUI Container (top) */
    #gui-container{
      padding:10px;
      overflow:auto;
      background:var(--bg);
      color:var(--text-primary);
      border-bottom:1px solid var(--border);
      min-width:0; min-height:0;
      display:none; /* Hidden by default, shown when import idegui detected */
    }
    #gui-container.active{
      display:block;
    }
    
    /* Output/Plot Section (bottom) */
    #output-plot-section{
      display:grid;
      grid-template-rows: auto 1fr;
      min-width:0; min-height:0;
    }
    
    /* Tab Navigation for Output/Plot */
    #output-plot-tabs{
      display:flex;
      gap:0;
      border-bottom:1px solid var(--border);
      background:var(--panel);
      margin:0;
      padding:0;
    }
    .output-plot-tab{
      flex:1;
      padding:10px 12px;
      border:none;
      background:var(--panel);
      color:var(--text-secondary);
      cursor:pointer;
      font-size:12px;
      font-weight:500;
      border-bottom:3px solid transparent;
      transition: all 0.2s ease;
    }
    .output-plot-tab:hover{
      background:var(--bg);
      color:var(--text-primary);
    }
    .output-plot-tab.active{
      color:var(--accent);
      border-bottom-color:var(--accent);
      background:var(--bg);
    }
    
    /* Output & Plot Panels */
    #output-container,
    #plot-container{
      padding:10px;
      overflow:auto;
      background:var(--bg);
      color:var(--text-primary);
      min-width:0; min-height:0;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
    }
    .output-plot-panel{
      display:none;
    }
    .output-plot-panel.active{
      display:block;
    }

    .plot-card{ border:1px solid var(--border); border-radius:12px; margin-bottom:10px; overflow:hidden; }
    .plot-card-header{ padding:8px 10px; background:var(--panel); color:var(--text-primary); font-weight:300; border-bottom:1px solid var(--border); }
    .plot-img{ width:100%; height:auto; display:block; }

    /* Share Header */
    .share-header {
      padding: 12px 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    .project-info h1 {
      margin: 0;
      font-size: 20px;
    }
    .project-info .owner {
      font-size: 13px;
      opacity: 0.9;
      margin-top: 2px;
    }
    .share-actions a {
      color: white;
      text-decoration: none;
      padding: 8px 16px;
      background: rgba(255,255,255,0.2);
      border-radius: 6px;
      font-weight: 600;
      font-size: 14px;
    }
    .share-actions a:hover {
      background: rgba(255,255,255,0.3);
    }
    .read-only-notice {
      padding: 8px 12px;
      background: #fef3c7;
      color: #92400e;
      text-align: center;
      font-size: 13px;
      border-bottom: 1px solid #fde68a;
    }
  </style>
</head>
<body>
  <div class="share-header">
    <div class="project-info">
      <h1><?= htmlspecialchars($project['name']) ?></h1>
      <div class="owner">von <?= htmlspecialchars($ownerName) ?></div>
    </div>
    <div class="share-actions">
      <a href="index.php">→ Eigenen Editor öffnen</a>
    </div>
  </div>

  <div class="read-only-notice">
    🔒 Nur-Lesen-Modus · Du kannst diesen Code ausführen, aber nicht speichern
  </div>

  <div class="toolbar">
    <button id="run-btn">Run</button>
    <div style="flex:1"></div>
    <button id="settings-toggle" title="Module" aria-label="Module settings">⚙</button>
    <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
  </div>

  <div id="settings-panel" class="settings-panel" aria-hidden="true">
    <div class="settings-title">Module</div>
    <label class="toolcheck" title="NumPy laden">
      <input id="pkg-numpy" type="checkbox" checked>
      <span>NumPy</span>
    </label>
    <label class="toolcheck" title="Matplotlib laden">
      <input id="pkg-matplotlib" type="checkbox" checked>
      <span>Matplotlib</span>
    </label>
    <label class="toolcheck" title="Pandas laden">
      <input id="pkg-pandas" type="checkbox">
      <span>Pandas</span>
    </label>
    <label class="toolcheck" title="Panel nicht verfuegbar in Pyodide">
      <input id="pkg-panel" type="checkbox" disabled>
      <span>Panel (nicht verfuegbar)</span>
    </label>
    <label class="toolcheck" title="Seaborn nicht verfuegbar in Pyodide">
      <input id="pkg-seaborn" type="checkbox" disabled>
      <span>Seaborn (nicht verfuegbar)</span>
    </label>
  </div>

  <div class="app">
    <div class="left">
      <div id="editor-container"></div>
      <div class="left-bottom">
        <div id="lint-container"></div>
        <div id="help-container"></div>
      </div>
    </div>
    <div class="right">
      <div id="gui-container"></div>
      <div id="output-plot-section">
        <div id="output-plot-tabs">
          <button class="output-plot-tab active" data-tab="output">📜 Output</button>
          <button class="output-plot-tab" data-tab="plot">📊 Plot</button>
        </div>
        <div id="output-container" class="output-plot-panel active"></div>
        <div id="plot-container" class="output-plot-panel"></div>
      </div>
    </div>
  </div>

  <!-- Monaco loader (AMD) -->
  <script src="monaco/min/vs/loader.js"></script>
  <script>
    require.config({ paths: { vs: "monaco/min/vs" } });
  </script>

  <!-- Pyodide -->
  <script src="pyodide/pyodide.js"></script>

  <script type="module" src="js/editor-setup.js"></script>

  <script>
    // Load shared project code into editor
    const projectCode = <?= json_encode($project['code']) ?>;

    // Wait for editor to be ready, then set code
    function waitForEditor() {
      return new Promise((resolve) => {
        const check = () => {
          if (window.monaco && window.monaco.editor) {
            const editors = window.monaco.editor.getEditors();
            if (editors.length > 0) {
              resolve(editors[0]);
            } else {
              setTimeout(check, 100);
            }
          } else {
            setTimeout(check, 100);
          }
        };
        check();
      });
    }

    waitForEditor().then((editor) => {
      editor.setValue(projectCode || '');
      editor.updateOptions({ readOnly: true });
    });

    // Theme Toggle
    (function() {
      const html = document.documentElement;
      const themeBtn = document.getElementById('theme-toggle');
      
      const savedTheme = localStorage.getItem('theme') || 'light';
      if (savedTheme === 'dark') {
        html.classList.add('dark-mode');
      }
      
      themeBtn?.addEventListener('click', () => {
        html.classList.toggle('dark-mode');
        const isDark = html.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
      });
    })();

    // Settings Toggle
    (function() {
      const settingsBtn = document.getElementById('settings-toggle');
      const settingsPanel = document.getElementById('settings-panel');
      
      settingsBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        settingsPanel.classList.toggle('open');
      });
      
      document.addEventListener('click', (e) => {
        if (settingsPanel && !settingsPanel.contains(e.target)) {
          settingsPanel.classList.remove('open');
        }
      });
    })();
  </script>
</body>
</html>
