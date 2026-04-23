<?php
/**
 * Legacy entrypoint.
 * Use projects.php as single source of truth for project editing UI.
 */

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'projects.php' . ($query !== '' ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Python IDE - Meine Projekte</title>
  <link rel="stylesheet" href="css/ide.css">
  <link rel="stylesheet" href="css/file-tree.css">
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

    /* MASTER GRID: left sidebar | editor | right output */
    .app{
      height: calc(100vh - 52px);
      display:grid;
      grid-template-columns: 1fr 25%;
      min-height:0;
      min-width:0;
    }
    .app.with-task-details {
      grid-template-columns: 30% 1fr 25%;
    }

    @media (min-width: 1201px) {
      .app {
        grid-template-columns: minmax(0, 800px) minmax(280px, 1fr);
      }
      .app.with-task-details {
        grid-template-columns: 30% minmax(0, 800px) minmax(280px, 1fr);
      }
    }

    /* TASK DETAILS SIDEBAR (left) */
    #task-details-panel {
      border-right: 1px solid var(--border);
      background: var(--bg);
      display: none;
      flex-direction: column;
      min-height:0;
    }
    #task-details-panel.active {
      display: flex;
    }
    
    /* Task Navigation (oben, kompakt) */
    .task-navigation {
      border-bottom: 2px solid var(--border);
      background: var(--panel);
      max-height: 40vh;
      overflow-y: auto;
      padding: 8px;
    }
    .task-nav-item {
      padding: 8px 10px;
      margin: 2px 0;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      transition: background 0.15s;
      border-left: 3px solid transparent;
    }
    .task-nav-item:hover {
      background: var(--bg);
    }
    .task-nav-item.active {
      background: var(--bg);
      border-left-color: #667eea;
      font-weight: 600;
    }
    .task-nav-title {
      flex: 1;
      color: var(--text-primary);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .task-nav-status {
      width: 11px;
      height: 11px;
      border-radius: 50%;
      flex-shrink: 0;
      border: 2px solid transparent;
    }
    .task-nav-position {
      font-size: 11px;
      color: #9ca3af;
      min-width: 20px;
      text-align: right;
    }
    .task-nav-status.status-unbearbeitet {
      background-color: #9ca3af;
      border-color: #6b7280;
    }
    .task-nav-status.status-in-progress {
      background-color: #fbbf24;
      border-color: #f59e0b;
    }
    .task-nav-status.status-passed {
      background-color: #34d399;
      border-color: #10b981;
    }
    .task-nav-status.status-failed {
      background-color: #f87171;
      border-color: #dc2626;
    }
    
    /* Task Details (unten) */
    .task-details-content {
      flex: 1;
      padding: 12px;
      overflow-y: auto;
      font-size: 13px;
      line-height: 1.6;
    }
    .task-details-content h4 {
      margin: 12px 0 6px 0;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-primary);
      text-transform: uppercase;
    }
    .task-details-content p {
      margin: 0 0 10px 0;
      color: var(--text-secondary);
    }
    .task-hint {
      background: var(--panel);
      border-left: 3px solid #f59e0b;
      padding: 8px;
      border-radius: 4px;
      margin: 10px 0;
      font-size: 12px;
    }
    .task-expected {
      background: var(--code-bg);
      border-left: 3px solid #10b981;
      padding: 8px;
      border-radius: 4px;
      margin: 10px 0;
      font-size: 12px;
      font-family: ui-monospace, Menlo, monospace;
      white-space: pre-wrap;
      word-wrap: break-word;
    }
    .stoff-section {
      background: var(--panel);
      border-left: 3px solid #3b82f6;
      padding: 8px;
      border-radius: 4px;
      margin: 10px 0;
      font-size: 12px;
    }

    /* LEFT COLUMN: file tree + editor/quiz + lint/help */
    .left{
      border-right:1px solid var(--border);
      display:grid;
      grid-template-rows: auto 1fr 180px;
      min-width:0; min-height:0;
    }
    .left.quiz-mode{
      grid-template-rows: auto 1fr 0px;
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
    #quiz-container{ width:100%; height:100%; min-width:0; min-height:0; display: none; overflow: auto; padding: 20px; }
    .editor-quiz-wrapper { position: relative; width: 100%; height: 100%; min-width:0; min-height:0; }

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
    .monaco-editor .suggest-widget .monaco-list-row.selected{
      background:rgba(220, 230, 245, 0.95) !important;
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
    .assignment-actions { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
    .assignment-detail {
      margin-top: 14px;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: var(--panel);
    }
    .task-item {
      padding: 10px;
      border: 1px solid var(--border);
      border-radius: 8px;
      margin-top: 8px;
      background: var(--bg);
    }
    .task-title { font-weight: 600; margin-bottom: 4px; }
    .task-actions { margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }

    /* Success Modal */
    .success-modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      animation: fadeIn 0.2s;
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    .success-modal-content {
      background: var(--bg);
      color: var(--text-primary);
      border-radius: 12px;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideUp 0.3s;
    }
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .success-modal-header {
      padding: 24px;
      border-bottom: 1px solid var(--border);
      text-align: center;
    }
    .success-modal-header h2 {
      margin: 0;
      font-size: 28px;
    }
    .success-modal-body {
      padding: 24px;
    }
    .success-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin: 20px 0;
    }
    .success-stat {
      background: var(--panel);
      padding: 12px;
      border-radius: 8px;
      text-align: center;
      border: 1px solid var(--border);
    }
    .success-stat-value {
      font-size: 24px;
      font-weight: 300;
      color: #10b981;
    }
    .success-stat-label {
      font-size: 12px;
      color: var(--text-secondary);
      margin-top: 4px;
    }
    .success-modal-footer {
      padding: 20px 24px;
      border-top: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .success-btn {
      padding: 12px 16px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .success-btn-ok {
      background: #10b981;
      color: white;
    }
    .success-btn-ok:hover {
      background: #059669;
      transform: translateY(-2px);
    }
    .success-btn-next {
      background: #3b82f6;
      color: white;
    }
    .success-btn-next:hover {
      background: #2563eb;
      transform: translateY(-2px);
    }
    .success-btn-next-assignment {
      background: #8b5cf6;
      color: white;
    }
    .success-btn-next-assignment:hover {
      background: #7c3aed;
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button id="projects-btn">📁 Projekte</button>
    <button id="assignments-btn" onclick="window.location.href='assignments.php'">📚 Aufgaben</button>
    <button id="run-btn">Run</button>
    <button id="check-btn" style="display:none; background:#10b981; color:#fff; border-color:transparent;">✓ Check</button>
    <span id="attempts-counter" style="display:none; margin:0 12px; font-weight:600; color:var(--text-primary);">Versuche: <span id="attempts-value">0/10</span></span>
    <button id="save-project-btn" style="display:none;">💾 Speichern</button>
    <button id="save-task-btn" style="display:none;">💾 Speichern</button>
    <button id="download-btn" style="display:none;">⬇ Herunterladen</button>

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

  <div class="current-project-bar" id="current-project-bar" style="display:none;">
    <span>Projekt: <span class="current-project-name" id="current-project-name">Ungespeichert</span></span>
    <span id="project-visibility"></span>
  </div>

  <div class="app">
    <div id="task-details-panel">
      <div class="task-navigation" id="task-navigation">
        <p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Keine Aufgaben geladen</p>
      </div>
      <div class="task-details-content" id="task-details-content">
        <p>Laden Sie eine Aufgabe um Details zu sehen</p>
      </div>
    </div>

    <div class="left">
      <div class="file-tree-wrapper" id="file-tree-wrapper"></div>
      <div class="editor-quiz-wrapper">
        <div id="editor-container"></div>
        <div id="quiz-container"></div>
      </div>

      <div class="left-bottom" id="left-bottom">
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

  <!-- Success Modal -->
  <div id="success-modal" class="success-modal" style="display: none;">
    <div class="success-modal-content">
      <div class="success-modal-header">
        <h2>🎉 Glückwunsch!</h2>
      </div>
      <div class="success-modal-body">
        <p id="success-message" style="font-size: 18px; margin-bottom: 24px;"></p>
        <div class="success-stats" id="success-stats"></div>
      </div>
      <div class="success-modal-footer">
        <button id="success-ok-btn" class="success-btn success-btn-ok">✓ OK - Auf dieser Aufgabe bleiben</button>
        <button id="success-next-task-btn" class="success-btn success-btn-next">→ Nächste Aufgabe</button>
        <button id="success-next-assignment-btn" class="success-btn success-btn-next-assignment" style="display: none;">⇒ Nächstes Assignment</button>
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

  <!-- File Tree & Validation -->
  <script src="js/file-tree-manager.js"></script>
  <script src="js/code-validator.js"></script>

  <script type="module" src="js/editor-setup.js?v=20260422zq"></script>

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
  <script type="module" src="js/projects.js"></script>
  <script type="module" src="js/assignments.js?v=20260422c"></script>
</body>
</html>
