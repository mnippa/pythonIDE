<?php
/**
 * Projects Editor - For creating and managing personal Python projects
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/auth/middleware.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($displayName === '') {
  $displayName = $user['email'] ?? 'Benutzer';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Python IDE - Meine Projekte</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <link rel="stylesheet" href="css/ide.css">
  <link rel="stylesheet" href="css/file-tree.css">
  <link rel="stylesheet" href="css/quiz.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
    html { height: 100vh; overflow: hidden; }
    body{ 
      margin:0; 
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; 
      background:var(--bg); 
      color:var(--text-primary); 
      transition:background 0.2s, color 0.2s; 
      height: 100vh; 
      overflow: hidden;
      display: grid;
      grid-template-rows: auto 1fr;
    }

    .toolbar{
      display:flex; gap:12px; align-items:center; flex-wrap:wrap;
      padding:3px 10px;
      background:transparent;
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

    /* MASTER GRID: left editor | right output */
    .app{
      height: 100%;
      display:grid;
      grid-template-columns: 1fr 240px;
      min-height:0;
      overflow: hidden;
    }

    /* MASTER GRID: 3 columns (file-tree | editor | output) */
    /* Base: file-tree 264px, editor flex, output 240px */
    .app {
      grid-template-columns: 264px 1fr 240px !important;
    }

    /* Desktop: file-tree 264px, editor flex, output 320px */
    @media (min-width: 1201px) {
      .app {
        grid-template-columns: 264px 1fr 320px !important;
      }
    }

    /* Tablet: file-tree 220px, editor flex, output 240px */
    @media (max-width: 1200px) and (min-width: 769px) {
      .app {
        grid-template-columns: 220px 1fr 240px !important;
      }
    }

    /* Mobile: Hide file-tree, editor flex, output 30% */
    @media (max-width: 768px) {
      .app {
        grid-template-columns: 1fr 30% !important;
      }
      #file-tree-panel {
        display: none !important;
      }
      .left-bottom {
        display: none !important;
      }
    }

    /* FILE TREE PANEL (left) */
    #file-tree-panel {
      border-right: 1px solid var(--border);
      background: var(--bg);
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }

    .file-tree-header {
      padding: 0;
      border-bottom: 1px solid var(--border);
      background: var(--panel);
    }

    .file-tree-toggle {
      width: 100%;
      padding: 0 6px;
      background: transparent;
      border: 1px solid var(--border);
      border-radius: 0;
      color: var(--text-primary);
      cursor: pointer;
      font-size: 13px;
      text-align: left;
      font-weight: 600;
      transition: background 0.2s;
    }

    .file-tree-toggle:hover {
      background: var(--border);
    }

    .file-tree-content {
      flex: 1;
      overflow-y: auto;
      padding: 0;
      font-size: 13px;
      min-height: 0;
    }

    /* LEFT COLUMN: file tree + editor + lint/help */
    .left{
      border-right:1px solid var(--border);
      display:grid;
      grid-template-rows: 1fr minmax(150px, 25%);
      min-width:0; min-height:0;
    }

    @media (max-width: 960px) {
      .left {
        grid-template-rows: 1fr 120px;
      }
    }
    .file-tree-wrapper {
      border-bottom: 1px solid var(--border);
      background: var(--bg);
      overflow: hidden;
      max-height: 0;
      padding: 0;
      min-height: 0;
      transition: max-height 0.2s;
    }
    .file-tree-wrapper.active {
      max-height: 250px;
      overflow: auto;
      padding: 8px;
    }
    #editor-container{ width:100%; height:100%; min-width:0; min-height:0; }

    .left-bottom{
      border-top:1px solid var(--border);
      display:grid;
      grid-template-columns: 40% 60%;
      min-width:0; min-height:0;
      background: var(--bg);
    }

    @media (max-width: 960px) {
      .left-bottom {
        grid-template-columns: 50% 50%;
      }
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

    /* Autocomplete: light background, semi-transparent */
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
    .monaco-editor .suggest-widget .monaco-list-row.selected,
    .monaco-editor .suggest-widget .monaco-list-row.focused{
      background:rgba(30, 100, 200, 0.95) !important;
      color:#fff !important;
      font-weight: bold !important;
      border-left:4px solid #0044aa !important;
      padding-left:calc(8px - 4px) !important;
    }
    .monaco-editor .suggest-widget-details{
      background:rgba(245, 245, 250, 0.95) !important;
    }

    /* RIGHT COLUMN: GUI top (50%) + Output/Plot tabs bottom (50%) */
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
      display:none;
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

    /* User bar & projects panel */
    .user-bar {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-left: auto;
      padding: 4px 12px;
      background: var(--panel);
      border-radius: 8px;
      border: 1px solid var(--border);
    }
    .user-info {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: var(--text-primary);
    }
    .user-badge {
      padding: 2px 8px;
      background: #667eea;
      color: white;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }
    .admin-link {
      padding: 6px 10px;
      background: #0f766e;
      color: #fff;
      border-radius: 6px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 600;
    }
    .admin-link:hover {
      background: #0b5f57;
    }
    #projects-btn {
      background: var(--panel);
      border: 1px solid var(--border);
    }
    #projects-btn:hover {
      background: var(--border);
    }
    .projects-panel {
      position: fixed;
      top: 0;
      right: -400px;
      width: 400px;
      height: 100vh;
      background: var(--bg);
      border-left: 1px solid var(--border);
      box-shadow: -4px 0 20px rgba(0,0,0,0.1);
      transition: right 0.3s;
      z-index: 1000;
      display: flex;
      flex-direction: column;
    }
    .projects-panel.open {
      right: 0;
    }
    .projects-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .projects-header h2 {
      margin: 0;
      font-size: 18px;
      color: var(--text-primary);
    }
    .close-panel {
      background: transparent;
      border: none;
      font-size: 24px;
      cursor: pointer;
      padding: 4px 8px;
      color: var(--text-secondary);
    }
    .projects-body {
      flex: 1;
      overflow-y: auto;
      padding: 12px;
    }
    .project-item {
      padding: 12px;
      margin-bottom: 8px;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .project-item:hover {
      border-color: #667eea;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    }
    .project-name {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 4px;
    }
    .project-meta {
      font-size: 12px;
      color: var(--text-secondary);
      display: flex;
      gap: 12px;
    }
    .visibility-badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
    }
    .visibility-private {
      background: #fee;
      color: #c00;
    }
    .visibility-public {
      background: #efe;
      color: #060;
    }
    .new-project-btn {
      width: 100%;
      padding: 12px;
      margin-bottom: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
    }
    .project-actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }
    .current-project-bar {
      padding: 8px 12px;
      background: var(--panel);
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
    }
    .current-project-name {
      font-weight: 600;
      color: var(--text-primary);
    }

    .project-visibility {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--text-secondary);
      margin-left: 8px;
      white-space: nowrap;
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button id="dashboard-btn" onclick="window.location.href='dashboard.php'">⬅ Dashboard</button>
    <button id="projects-btn">📁 Projekte</button>
    <button id="run-btn">Run</button>
    <button id="save-project-btn" style="display:none;">💾 Speichern</button>
    <button id="download-btn" style="display:none;">⬇ Herunterladen</button>
    <span id="project-visibility" class="project-visibility"></span>

    <div style="flex:1"></div>
    
    <div class="user-bar">
      <div class="user-info">
        <span><?= htmlspecialchars($displayName) ?></span>
        <?php if ($user['role'] === 'admin'): ?>
        <span class="user-badge">Admin</span>
        <?php endif; ?>
      </div>
      <?php if ($user['role'] === 'admin'): ?>
      <a class="admin-link" href="admin.php" title="Admin Dashboard">Admin</a>
      <?php endif; ?>
      <button id="settings-toggle" title="Module" aria-label="Module settings">⚙</button>
      <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
      <button id="logout-btn" title="Abmelden">🚪</button>
    </div>
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


  <!-- Editor View -->
  <div class="app" id="editor-view" style="display:grid;">
    <!-- LEFT SIDE: File tree panel -->
    <div id="file-tree-panel" class="file-tree-panel">
      <div class="file-tree-header" id="file-tree-header">
        <button class="file-tree-toggle" id="file-tree-toggle" title="Dateibaum umschalten">
          ▼ Projekt
        </button>
      </div>
      <div class="file-tree-content" id="file-tree-wrapper">
        <!-- Initialized by file-tree-manager.js -->
      </div>
    </div>

    <!-- CENTER PART: Editor + Lint/Help -->
    <div class="left">
      <div id="editor-container"></div>

      <div class="left-bottom">
        <div id="lint-container"></div>
        <div id="help-container"></div>
      </div>
    </div>

    <!-- RIGHT SIDE: GUI + Output/Plot -->
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

  <div id="projects-panel" class="projects-panel">
    <div class="projects-header">
      <h2>Meine Projekte</h2>
      <button class="close-panel" id="close-projects">×</button>
    </div>
    <div class="projects-body">
      <button class="new-project-btn" id="new-project-btn">+ Neues Projekt</button>
      <div id="projects-list">Lade Projekte...</div>
    </div>
  </div>

  <!-- Monaco loader (AMD) -->
  <script src="monaco/min/vs/loader.js"></script>
  <script>
    require.config({ paths: { vs: "monaco/min/vs" } });
  </script>

  <!-- Pyodide -->
  <script src="pyodide/pyodide.js"></script>

  <!-- File Tree & Validation -->
  <script src="js/code-validator.js"></script>

  <script type="module" src="js/editor-setup.js"></script>

  <script>
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

      // File tree toggle
      (function() {
        const toggleBtn = document.getElementById('file-tree-toggle');
        const content = document.getElementById('file-tree-wrapper');
        let isExpanded = true; // Start expanded in projects.php

        if (toggleBtn && content) {
          const getLabel = () => {
            if (toggleBtn.dataset.label) return toggleBtn.dataset.label;
            return toggleBtn.textContent.replace(/^[▼▶]\s*/, '').trim() || 'Projekt';
          };

          toggleBtn.addEventListener('click', () => {
            isExpanded = !isExpanded;
            content.style.display = isExpanded ? 'block' : 'none';
            const label = getLabel();
            toggleBtn.textContent = (isExpanded ? '▼ ' : '▶ ') + label;
          });
        }
      })();

    // Logout
    document.getElementById('logout-btn')?.addEventListener('click', async () => {
      const response = await fetch('../api/auth/logout.php', {
        credentials: 'include'
      });
      if (response.ok) {
        window.location.href = 'login.php';
      }
    });

    // Projects panel toggle
    const projectsBtn = document.getElementById('projects-btn');
    const projectsPanel = document.getElementById('projects-panel');
    const closeProjectsBtn = document.getElementById('close-projects');

    projectsBtn?.addEventListener('click', () => {
      projectsPanel.classList.add('open');
    });

    closeProjectsBtn?.addEventListener('click', () => {
      projectsPanel.classList.remove('open');
    });
  </script>
  <script src="js/file-tree-manager.js"></script>
  <script type="module" src="js/projects.js"></script>
</body>
</html>
