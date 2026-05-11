<?php
/**
 * Projects Editor - Code editor view for managing personal projects
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

    body[data-pyide-mode="projects"] .hspf-header-content {
      max-width: none;
      padding-left: 8px;
      min-width: 0;
      gap: 10px;
    }

    body[data-pyide-mode="projects"] .hspf-header-left {
      flex: 1 1 auto;
      min-width: 0;
      flex-wrap: nowrap;
      overflow: hidden;
    }

    body[data-pyide-mode="projects"] .hspf-page-title {
      display: block;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    body[data-pyide-mode="projects"] #project-page-title {
      display: inline-block;
      max-width: 100%;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      vertical-align: bottom;
    }

    body[data-pyide-mode="projects"] .hspf-header-right {
      flex: 0 0 auto;
      white-space: nowrap;
    }

    body[data-pyide-mode="projects"] .hspf-header-right .toolbar {
      flex-wrap: nowrap;
      overflow-x: auto;
      overflow-y: hidden;
      min-width: 0;
      scrollbar-width: thin;
    }

    body[data-pyide-mode="projects"] .hspf-header-right .user-bar {
      flex: 0 0 auto;
    }

    .toolbar{
      display:flex; gap:12px; align-items:center; flex-wrap:wrap;
      padding:3px 10px;
      background:transparent;
    }
    .toolbar button{ padding:8px 12px; cursor:pointer; background:var(--panel); color:var(--text-primary); border:1px solid var(--border); border-radius:4px; transition:background 0.2s; }
    .toolbar button:hover{ background:var(--text-secondary); opacity:0.7; }
    .toolbar .icon-btn{ padding:6px; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:16px; }
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; transition:background 0.3s; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; display:block; width:20px; height:20px; line-height:20px; transition:transform 0.3s; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }

    .toolcheck{ display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; user-select:none; }
    .toolcheck input{ transform: translateY(0.5px); }
    .settings-panel{
      position:fixed; top:58px; right:10px; z-index:120;
      background:var(--panel); color:var(--text-primary);
      border:1px solid var(--border); border-radius:10px;
      padding:10px; min-width:190px;
      display:none; flex-direction:column; gap:8px;
      box-shadow:0 12px 30px rgba(0,0,0,0.12);
    }
    .settings-panel.open{ display:flex; }
    .settings-title{ font-size:12px; font-weight:300; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-secondary); }

    /* MASTER GRID: left sidebar | editor | splitter | right output */
    .app{
      height: 100%;
      display:grid;
      grid-template-columns: 1fr 5px 240px;
      min-height:0;
      overflow: hidden;
    }

    /* Medium: Project sidebar 264px, Code Rest, Output 240px */
    .app.with-project-details {
      grid-template-columns: 264px 1fr 5px 240px !important;
    }

    /* Desktop: Sidebar 440px, Code Rest, Output 320px */
    @media (min-width: 1201px) {
      .app {
        grid-template-columns: 1fr 5px 1fr;
      }
      .app.with-project-details {
        grid-template-columns: 440px 1fr 5px 1fr !important;
      }
    }

    /* Mobile: Sidebar hidden, Code 70%, Output 30% - no splitter */
    @media (max-width: 768px) {
      .app {
        grid-template-columns: 1fr 30%;
      }
      .app.with-project-details {
        grid-template-columns: 1fr 30% !important;
      }
      #project-list-panel {
        display: none !important;
      }
      .column-splitter {
        display: none !important;
      }
    }
    
    /* Resizable Splitter */
    .column-splitter {
      width: 5px;
      background: var(--border);
      cursor: col-resize;
      position: relative;
      transition: background 0.2s;
      min-height: 0;
    }
    .column-splitter:hover,
    .column-splitter.dragging {
      background: var(--hspf-accent, #667eea);
    }
    .column-splitter::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 3px;
      height: 60px;
      background: var(--text-secondary);
      border-radius: 3px;
      opacity: 0.3;
    }
    .column-splitter:hover::before,
    .column-splitter.dragging::before {
      opacity: 1;
      background: white;
    }

    /* PROJECT LIST SIDEBAR (left) */
    #project-list-panel {
      border-right: 1px solid var(--border);
      background: var(--bg);
      display: none;
      flex-direction: column;
      min-height:0;
      overflow: hidden;
    }
    #project-list-panel.active {
      display: flex;
    }

    /* Project Navigation (top) */
    .project-navigation {
      border-bottom: 2px solid var(--border);
      background: var(--panel);
      flex: 0 0 220px;
      min-height: 180px;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 8px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    #file-tree-wrapper {
      border-bottom: 1px solid var(--border);
      background: var(--bg);
      display: flex;
      flex-direction: column;
      flex: 1 1 auto;
      min-height: 220px;
      overflow: hidden;
      font-size: 13px;
    }

    #file-tree-wrapper .tree-header {
      padding: 8px;
      border-bottom: 1px solid var(--border);
      background: var(--panel);
      font-weight: 600;
      font-size: 12px;
      color: var(--text-primary);
    }

    #project-file-tree {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 4px;
      min-height: 0;
    }
    
    /* Force scrollbar to always show */
    .project-navigation::-webkit-scrollbar {
      width: 8px;
    }
    .project-navigation::-webkit-scrollbar-track {
      background: var(--panel);
    }
    .project-navigation::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 4px;
    }
    .project-navigation::-webkit-scrollbar-thumb:hover {
      background: var(--muted);
    }
    .project-nav-item {
      padding: 8px 10px;
      margin: 0;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      transition: background 0.15s;
      border-left: 3px solid transparent;
      position: relative;
    }
    .project-nav-item:hover {
      background: var(--bg);
    }
    .project-nav-item.active {
      background: var(--bg);
      border-left-color: #667eea;
      font-weight: 600;
    }
    .project-nav-title {
      flex: 1;
      color: var(--text-primary);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .project-nav-delete {
      font-size: 11px;
      color: #ef4444;
      cursor: pointer;
      opacity: 0;
      transition: opacity 0.2s;
      padding: 2px 4px;
    }
    .project-nav-item:hover .project-nav-delete {
      opacity: 1;
    }
    .project-nav-type {
      font-size: 11px;
      color: var(--text-secondary);
      min-width: 45px;
      text-align: right;
    }
    
    /* Project Details (bottom) */
    .project-details-content {
      flex: 0 0 180px;
      padding: 12px;
      overflow-y: auto;
      font-size: 13px;
      line-height: 1.6;
      min-height: 0;
      display: block;
    }
    
    .project-info-section {
      margin-bottom: 12px;
    }
    .project-info-section h4 {
      margin: 0 0 6px 0;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-primary);
      text-transform: uppercase;
    }
    .project-info-value {
      color: var(--text-secondary);
      font-size: 12px;
      word-break: break-word;
    }
    .project-visibility-toggle {
      width: 100%;
      padding: 8px 10px;
      margin-top: 10px;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: var(--panel);
      color: var(--text-primary);
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .project-visibility-toggle:hover {
      background: var(--border);
    }
    .project-visibility-toggle.public {
      border-color: #10b981;
      background: #d1fae5;
      color: #065f46;
    }
    html.dark-mode .project-visibility-toggle.public {
      background: rgba(16, 185, 129, 0.2);
      color: #6ee7b7;
    }

    /* EDITOR AREA (middle) */
    .editor-area {
      border-right:1px solid var(--border);
      display:grid;
      grid-template-rows: 1fr minmax(150px, 25%);
      min-width:0; min-height:0;
    }

    @media (max-width: 960px) {
      .editor-area {
        grid-template-rows: 1fr 120px;
      }
    }

    .editor-container-wrapper {
      min-width:0; 
      min-height:0;
      display:flex;
      flex-direction: column;
    }

    #editor-container{ width:100%; flex:1; min-width:0; min-height:0; }

    .editor-bottom{
      border-top:1px solid var(--border);
      display:grid;
      grid-template-columns: 40% 60%;
      min-width:0; min-height:0;
      background: var(--bg);
    }

    @media (max-width: 960px) {
      .editor-bottom {
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
    }
    html.dark-mode #help-container a{
      color:#60a5fa;
    }

    /* RIGHT PANEL: Output/GUI */
    .right{
      display:flex;
      flex-direction: column;
      min-width:0; min-height:0;
      background: var(--bg);
      overflow: hidden;
    }

    #gui-container{
      border-bottom:1px solid var(--border);
      background:var(--bg);
      color:var(--text-primary);
      padding:2px;
      overflow:auto;
      font-size:13px;
      min-width:0; min-height:0;
      display: none;
    }
    #gui-container.active{
      display: block;
      min-height: 120px;
      max-height: 45vh;
      flex: 0 0 auto;
    }

    .right.gui-active #gui-container.active {
      flex: 1 1 auto;
      min-height: 0;
      max-height: none;
      border-bottom: none;
    }

    .right.gui-active #output-plot-section {
      display: none;
    }

    .right.gui-active #gui-container.active .project-gui-stage {
      display: flex;
      flex-direction: column;
      min-height: 100%;
    }

    .right.gui-active #gui-container.active .project-gui-stage > :first-child:last-child {
      flex: 1 1 auto;
      min-height: 100%;
      width: 100%;
    }

    #output-plot-section {
      display: flex;
      flex-direction: column;
      flex: 1 1 auto;
      min-height: 0;
      min-width: 0;
    }

    #output-plot-tabs{
      display:flex;
      border-bottom:1px solid var(--border);
      background:var(--panel);
      flex:0 0 auto;
    }
    body[data-pyide-mode="projects"] #output-plot-tabs.hidden-when-no-plot {
      display: none;
    }
    .output-plot-tab{
      padding:8px 12px;
      border:none;
      background:transparent;
      color:var(--text-secondary);
      cursor:pointer;
      font-size:13px;
      transition:all 0.2s;
      border-bottom:2px solid transparent;
    }
    .output-plot-tab.active{
      color:var(--text-primary);
      border-bottom-color:#667eea;
      font-weight:600;
    }
    .output-plot-panel{
      display:none;
      flex:1;
      overflow:auto;
      padding:8px;
      background:var(--bg);
      color:var(--text-primary);
      font-size:13px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      min-width:0; min-height:0;
      white-space:pre-wrap;
    }
    .output-plot-panel.active{
      display:flex;
      flex-direction: column;
    }
    #output-container {
      background: var(--bg);
      color: var(--text-primary);
    }
    html.dark-mode #output-container {
      background: #252526;
      color: #e6edf3;
    }
    #plot-container {
      background: var(--bg);
    }
    #plot-container.output-plot-panel.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    #plot-container canvas {
      max-width: 100%;
      max-height: 100%;
    }

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-overlay.open {
      display: flex;
    }
    .modal-content {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 20px;
      min-width: 400px;
      max-width: 500px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    .modal-header h2 {
      margin: 0 0 10px 0;
      font-size: 18px;
      color: var(--text-primary);
    }
    .modal-body {
      margin: 15px 0;
      color: var(--text-secondary);
      font-size: 14px;
    }
    .modal-footer {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 20px;
    }
    .modal-footer button {
      padding: 8px 16px;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--panel);
      color: var(--text-primary);
      cursor: pointer;
      font-size: 13px;
      transition: all 0.2s;
    }
    .modal-footer button:hover {
      background: var(--border);
    }
    .modal-footer button.danger {
      background: #ef4444;
      color: white;
      border-color: #ef4444;
    }
    .modal-footer button.danger:hover {
      background: #dc2626;
    }
    .modal-footer button.primary {
      background: #667eea;
      color: white;
      border-color: #667eea;
    }
    .modal-footer button.primary:hover {
      background: #5568d3;
    }

    /* Project Type Badge */
    .project-type-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      background: #dbeafe;
      color: #0c4a6e;
    }
    .project-type-badge.html {
      background: #fef08a;
      color: #713f12;
    }
    .project-type-badge.mixed {
      background: #fce7f3;
      color: #831843;
    }

    /* User bar */
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
    
    /* Help link in project details */
    .help-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border-radius: 6px;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
    }
    .help-link:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    }
  </style>
</head>
<body data-pyide-mode="projects">
  <?php
  $pageTitle = '<span id="project-page-title">Meine Projekte</span>';
  $showUser = false;
  $userInfo = [];
  
  $displayNameEscaped = htmlspecialchars($displayName);
  $adminBadge = ($user['role'] === 'admin') ? '<span class="user-badge">Admin</span>' : '';
  $adminLink = ($user['role'] === 'admin') ? '<a class="admin-link" href="admin.php" title="Admin Dashboard">Admin</a>' : '';
  
  $headerActions = <<<HTML
    <div class="toolbar">
      <button id="dashboard-btn" title="Zurück">⬅</button>
      <button id="run-btn">Run</button>
      <button id="web-help-btn" style="display:none;" title="idegui Hilfe (Python + HTML)">❓ Hilfe</button>
      <button id="new-project-btn" style="background:#667eea; color:#fff; border-color:transparent;">+ Neues Projekt</button>
      <button id="undo-btn" class="icon-btn" style="display:none;" title="Rückgängig">↶</button>
      <button id="redo-btn" class="icon-btn" style="display:none;" title="Wiederherstellen">↷</button>
      <button id="save-project-btn" class="icon-btn" title="Speichern">💾</button>
      <button id="save-all-project-btn" class="icon-btn" title="Alle speichern">💾💾</button>
      <button id="project-export-btn" class="icon-btn" title="Projekt exportieren">📦⬇</button>
      <button id="project-import-btn" class="icon-btn" title="Projekt importieren">📦⬆</button>
      <input id="project-import-file-input" type="file" accept=".pyideproj,.json,.zip" style="display:none;" />
      <button id="download-btn" class="icon-btn" style="display:none;" title="Herunterladen">⬇</button>
      <div style="flex:1"></div>
      <div class="user-bar">
        <div class="user-info">
          <span>{$displayNameEscaped}</span>
          {$adminBadge}
        </div>
        {$adminLink}
        <button id="settings-toggle" class="icon-btn" title="Module / Bibliotheken" aria-label="Module settings">⚙</button>
        <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
        <button id="logout-btn" title="Abmelden">🚪</button>
      </div>
    </div>
HTML;
  include(__DIR__ . '/../components/header.php');
  ?>

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
      <span>Panel (nicht verfügbar)</span>
    </label>
    <label class="toolcheck" title="Seaborn nicht verfuegbar in Pyodide">
      <input id="pkg-seaborn" type="checkbox" disabled>
      <span>Seaborn (nicht verfügbar)</span>
    </label>
  </div>

  <!-- Editor View -->
  <div class="app with-project-details" id="editor-view" style="display:grid;">
    <!-- Project Sidebar -->
    <div id="project-list-panel" class="active">
      <div class="project-navigation" id="project-navigation">
        <p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Lade Projekte...</p>
      </div>
      <div id="file-tree-wrapper">
        <div class="tree-header">📁 Dateien</div>
        <div id="project-file-tree"></div>
      </div>
      <div class="project-details-content" id="project-details-content">
        <p>Wählen Sie ein Projekt um zu starten</p>
      </div>
    </div>

    <!-- Main Editor Area (middle) -->
    <div class="editor-area">
      <div class="editor-container-wrapper">
        <div id="editor-container"></div>
      </div>

      <div class="editor-bottom">
        <div id="lint-container"></div>
        <div id="help-container"></div>
      </div>
    </div>

    <!-- Resizable Splitter -->
    <div class="column-splitter" id="column-splitter"></div>

    <!-- Right Output Panel -->
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

  <!-- Modals -->
  <div id="create-project-modal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Neues Projekt</h2>
      </div>
      <div class="modal-body">
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 5px; font-weight: 600;">Projektname:</label>
          <input id="project-name-input" type="text" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:4px; background:var(--panel); color:var(--text-primary);" placeholder="Mein Projekt">
        </div>
        
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 8px; font-weight: 600;">Vorlage:</label>
          <select id="project-template-input" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:4px; background:var(--panel); color:var(--text-primary);">
            <option value="empty_python" selected>Leeres Python Projekt</option>
            <option value="empty_python_html">Leeres Python-HTML Projekt</option>
            <option value="python_logic">Python-HTML mit Python-Logik</option>
            <option value="event_logic">Python-HTML mit Event-Handler-Logik</option>
            <option value="kniffel_demo">🎲 Demo: Kniffel (Yahtzee)</option>
            <option value="blackjack_demo">🎰 Demo: Blackjack</option>
          </select>
          <small style="display: block; margin-top: 5px; color: var(--text-secondary);">
            Wähle eine Vorlage als Ausgangspunkt für dein Projekt
          </small>
        </div>
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 5px; font-weight: 600;">Beschreibung:</label>
          <textarea id="project-desc-input" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:4px; background:var(--panel); color:var(--text-primary); min-height:80px; font-family:inherit; resize:vertical;" placeholder="Optional..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button onclick="document.getElementById('create-project-modal').classList.remove('open')">↪ Abbrechen</button>
        <button class="primary" onclick="createProjectFromDialog()">✓ Erstellen</button>
      </div>
    </div>
  </div>

  <div id="delete-project-modal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Projekt löschen?</h2>
      </div>
      <div class="modal-body">
        <p>Das Projekt "<span id="delete-project-name">Unbekannt</span>" wird permanent gelöscht. Dies kann nicht rückgängig gemacht werden.</p>
      </div>
      <div class="modal-footer">
        <button onclick="document.getElementById('delete-project-modal').classList.remove('open')">↪ Abbrechen</button>
        <button class="danger" onclick="confirmDeleteProject()">🗑️ Löschen</button>
      </div>
    </div>
  </div>

  <div id="unsaved-changes-modal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Ungespeicherte Änderungen</h2>
      </div>
      <div class="modal-body">
        <p id="unsaved-changes-description">Du hast ungespeicherte Änderungen in <strong id="unsaved-file-name">dieser Datei</strong>.</p>
        <p id="unsaved-changes-subtext" style="color:var(--text-secondary); margin-top:8px;">Was möchtest du tun?</p>
      </div>
      <div class="modal-footer">
        <button id="unsaved-cancel-btn">↪ Abbrechen</button>
        <button id="unsaved-discard-btn" class="danger">Änderungen verwerfen</button>
        <button id="unsaved-save-btn" class="primary">Änderungen speichern</button>
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

  <!-- File Tree -->
  <script src="js/file-tree-manager.js"></script>
  <script src="js/code-validator.js"></script>

  <script type="module" src="js/editor-setup.js?v=20260508a"></script>

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
        settingsPanel.setAttribute('aria-hidden', settingsPanel.classList.contains('open') ? 'false' : 'true');
      });
      document.addEventListener('click', (e) => {
        if (settingsPanel && !settingsPanel.contains(e.target)) {
          settingsPanel.classList.remove('open');
          settingsPanel.setAttribute('aria-hidden', 'true');
        }
      });
    })();

    // Dashboard
    document.getElementById('dashboard-btn')?.addEventListener('click', async () => {
      if (typeof confirmProjectSwitchWithDrafts === 'function') {
        const canLeave = await confirmProjectSwitchWithDrafts();
        if (!canLeave) return;
      }
      window.location.href = 'dashboard.php';
    });

    // Logout
    document.getElementById('logout-btn')?.addEventListener('click', async () => {
      if (typeof confirmProjectSwitchWithDrafts === 'function') {
        const canLeave = await confirmProjectSwitchWithDrafts();
        if (!canLeave) return;
      }
      const response = await fetch('../api/auth/logout.php', {
        credentials: 'include'
      });
      if (response.ok) {
        window.location.href = 'login.php';
      }
    });

    // Resizable Column Splitter
    (function initColumnSplitter() {
      const splitter = document.getElementById('column-splitter');
      const app = document.querySelector('.app');
      const editorArea = document.querySelector('.editor-area');
      const rightCol = document.querySelector('.right');

      if (!splitter || !app || !editorArea || !rightCol) {
        return;
      }

      let isDragging = false;
      let startX = 0;
      let startLeftWidth = 0;
      let startRightWidth = 0;

      splitter.addEventListener('pointerdown', (e) => {
        isDragging = true;
        startX = e.clientX;

        const editorRect = editorArea.getBoundingClientRect();
        const rightRect = rightCol.getBoundingClientRect();
        startLeftWidth = editorRect.width;
        startRightWidth = rightRect.width;

        splitter.classList.add('dragging');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        splitter.setPointerCapture(e.pointerId);

        e.preventDefault();
      });

      document.addEventListener('pointermove', (e) => {
        if (!isDragging) return;

        const deltaX = e.clientX - startX;
        const totalWidth = startLeftWidth + startRightWidth;

        let newLeftWidth = startLeftWidth + deltaX;
        let newRightWidth = startRightWidth - deltaX;

        const minWidth = 300;
        if (newLeftWidth < minWidth) {
          newLeftWidth = minWidth;
          newRightWidth = totalWidth - minWidth;
        }
        if (newRightWidth < minWidth) {
          newRightWidth = minWidth;
          newLeftWidth = totalWidth - minWidth;
        }

        const hasProjectDetails = app.classList.contains('with-project-details');
        const sidebarWidth = 440;
        const newGridTemplate = hasProjectDetails
          ? `${sidebarWidth}px ${newLeftWidth}px 5px ${newRightWidth}px`
          : `${newLeftWidth}px 5px ${newRightWidth}px`;
        
        app.style.setProperty('grid-template-columns', newGridTemplate, 'important');

        e.preventDefault();
      });

      const stopDragging = (e) => {
        if (!isDragging) return;
        isDragging = false;
        splitter.classList.remove('dragging');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        if (typeof e.pointerId === 'number' && splitter.hasPointerCapture?.(e.pointerId)) {
          splitter.releasePointerCapture(e.pointerId);
        }
      };

      document.addEventListener('pointerup', stopDragging);
      document.addEventListener('pointercancel', stopDragging);
    })();

    // Create Project from Dialog
    async function createProjectFromDialog() {
      const name = document.getElementById('project-name-input').value.trim();
      const description = document.getElementById('project-desc-input').value.trim();
      const template = document.getElementById('project-template-input').value;

      if (!name) {
        alert('Bitte gib einen Projektnamen ein.');
        return;
      }

      try {
        const response = await fetch('/api/projects/create.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            name: name,
            description: description,
            template: template,
            visibility: 'private'
          })
        });

        const data = await response.json();

        if (data.ok) {
          // Close modal
          document.getElementById('create-project-modal').classList.remove('open');
          
          // Clear inputs
          document.getElementById('project-name-input').value = '';
          document.getElementById('project-desc-input').value = '';
          
          // Reload page to show new project
          window.location.href = 'projects.php?project_id=' + data.project.id;
        } else {
          alert('Fehler beim Erstellen: ' + (data.error || 'Unbekannter Fehler'));
        }
      } catch (error) {
        console.error('Create project error:', error);
        alert('Fehler beim Erstellen des Projekts.');
      }
    }

    // New Project Button
    document.getElementById('new-project-btn')?.addEventListener('click', () => {
      document.getElementById('create-project-modal').classList.add('open');
      document.getElementById('project-name-input').focus();
    });
  </script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script type="module" src="js/projects-editor.js?v=20260511a"></script>
  <script>
    // Set project editor mode for editor-setup.js
    window.PROJECT_EDITOR_MODE = true;
    window.userId = <?= (int)$user['id'] ?>;
    window.isAdmin = <?= ($user['role'] === 'admin' ? 'true' : 'false') ?>;
  </script>
</body>
</html>
