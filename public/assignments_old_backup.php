<?php
/**
 * Assignments Page - For working on assigned programming tasks
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
  <title>Python IDE - Meine Aufgaben</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
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
      padding:6px 10px;
      background:transparent;
    }
    .toolbar button{ padding:8px 12px; cursor:pointer; background:var(--panel); color:var(--text-primary); border:1px solid var(--border); border-radius:4px; transition:background 0.2s; }
    .toolbar button:hover{ background:var(--text-secondary); opacity:0.7; }
    .toolbar .icon-btn{ padding:6px; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:16px; }
    #submitted-info{ display:none; margin:0 12px; font-weight:600; color:var(--text-primary); align-items:center; gap:8px; }
    #submitted-info.show{ display:flex; }
    #submitted-status.status-passed{ background-color:#34d399; }
    #submitted-status.status-failed{ background-color:#f87171; }
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; transition:background 0.3s; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; display:block; width:20px; height:20px; line-height:20px; transition:transform 0.3s; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }

    /* MASTER GRID: left sidebar | editor | right output */
    .app{
      height: calc(100vh - 52px);
      display:grid;
      grid-template-columns: 1fr 240px;
      min-height:0;
    }
    
    /* Medium: Navigation 264px, Code Rest, Output 240px (base) */
    .app.with-task-details {
      grid-template-columns: 264px 1fr 240px !important;
    }

    /* Desktop: Navigation 440px fix, Code Rest, Output 320px fix */
    @media (min-width: 1201px) {
      .app {
        grid-template-columns: 1fr 320px;
      }
      .app.with-task-details {
        grid-template-columns: 440px 1fr 320px !important;
      }
    }

    /* Mobile: Navigation collapsible, Code 70%, Output 30% */
    @media (max-width: 768px) {
      .app {
        grid-template-columns: 1fr 30%;
      }
      .app.with-task-details {
        grid-template-columns: 1fr 30% !important;
      }
      #task-details-panel {
        display: none !important;
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
    .task-details-tabs {
      display: flex;
      gap: 6px;
      margin-bottom: 10px;
    }
    .task-details-tab {
      padding: 6px 10px;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: var(--panel);
      color: var(--text-primary);
      font-size: 12px;
      cursor: pointer;
    }
    .task-details-tab.active {
      background: var(--bg);
      font-weight: 600;
    }
    .task-tab-count {
      font-size: 11px;
      color: var(--text-secondary);
      margin-left: 6px;
    }
    .task-details-panel-section {
      display: none;
    }
    .task-details-panel-section.active {
      display: block;
    }
    .task-hints-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 6px 0 10px;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-primary);
    }
    .task-hints-counter {
      color: var(--text-secondary);
      font-weight: 250;
    }
    .task-hints-empty {
      margin: 0 0 10px;
      color: var(--text-secondary);
    }
    .hint-item {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 8px;
      margin: 6px 0;
      font-size: 12px;
    }
    .hint-number {
      font-weight: 600;
      margin-right: 4px;
    }
    .hint-reveal-btn {
      width: 100%;
      padding: 8px 10px;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: #fef3c7;
      color: #92400e;
      cursor: pointer;
      font-weight: 600;
    }
    .hint-reveal-btn:disabled {
      background: var(--panel);
      color: var(--text-secondary);
      cursor: not-allowed;
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

    /* LEFT COLUMN: editor + lint/help */
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

    /* RIGHT COLUMN: output top + plot bottom */
    .right{
      display:grid;
      grid-template-rows: 1fr 1fr;
      min-width:0; min-height:0;
    }
    #output-container{
      padding:10px;
      overflow:auto;
      background:var(--bg);
      color:var(--text-primary);
      border-bottom:1px solid var(--border);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
      min-width:0; min-height:0;
    }
    #plot-container{
      padding:10px;
      overflow:auto;
      background:var(--bg);
      color:var(--text-primary);
      min-width:0; min-height:0;
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

    .assignments-panel {
      position: fixed;
      top: 0;
      right: -420px;
      width: 420px;
      height: 100vh;
      background: var(--bg);
      border-left: 1px solid var(--border);
      box-shadow: -4px 0 20px rgba(0,0,0,0.1);
      transition: right 0.3s;
      z-index: 1000;
      display: flex;
      flex-direction: column;
    }
    .assignments-panel.open { right: 0; }
    .assignments-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .assignments-header h2 {
      margin: 0;
      font-size: 18px;
      color: var(--text-primary);
    }
    .assignments-body {
      flex: 1;
      overflow-y: auto;
      padding: 12px;
    }
    .assignment-item {
      margin-bottom: 10px;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 10px;
      overflow: hidden;
    }
    .assignment-header-bar {
      padding: 12px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background 0.2s;
    }
    .assignment-header-bar:hover {
      background: var(--bg);
    }
    .assignment-header-left {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 1;
    }
    .assignment-expand-icon {
      transition: transform 0.2s;
      font-size: 14px;
      color: var(--text-secondary);
    }
    .assignment-item.expanded .assignment-expand-icon {
      transform: rotate(90deg);
    }
    .assignment-status-summary {
      font-size: 11px;
      color: var(--text-secondary);
      background: var(--bg);
      padding: 2px 8px;
      border-radius: 999px;
    }
    .assignment-tasks-list {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
      border-top: 1px solid var(--border);
    }
    .assignment-item.expanded .assignment-tasks-list {
      max-height: 600px;
      overflow-y: auto;
    }
    .assignment-task-row {
      padding: 10px 12px;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid var(--border);
      cursor: pointer;
      transition: background 0.2s;
    }
    .assignment-task-row:last-child {
      border-bottom: none;
    }
    .assignment-task-row:hover {
      background: var(--bg);
    }
    .assignment-header-bar .assignment-title {
      font-weight: 600;
      color: var(--text-primary);
      margin: 0;
      font-size: 14px;
    }
    .assignment-title {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 6px;
    }
    .assignment-meta {
      font-size: 12px;
      color: var(--text-secondary);
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }
    .status-badge {
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      background: #e5e7eb;
      color: #111827;
    }
    .status-assigned { background: #e0f2fe; color: #0369a1; }
    .status-in_progress { background: #fef3c7; color: #92400e; }
    .status-submitted { background: #ddd6fe; color: #5b21b6; }
    .status-passed { background: #dcfce7; color: #166534; }
    .status-failed { background: #fee2e2; color: #b91c1c; }
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

    /* Assignment List View */
    .assignment-list-view {
      min-height: calc(100vh - 52px);
      background: var(--bg);
      padding: 32px;
    }
    .assignment-list-header {
      max-width: 1200px;
      margin: 0 auto 32px auto;
    }
    .assignment-list-header h1 {
      font-size: 32px;
      font-weight: 700;
      color: var(--text-primary);
      margin: 0;
    }
    .assignment-list-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 24px;
    }
    .assignment-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .assignment-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
      border-color: #667eea;
    }
    .assignment-card-title {
      font-size: 20px;
      font-weight: 600;
      color: var(--text-primary);
      margin: 0 0 12px 0;
    }
    .assignment-card-description {
      color: var(--text-secondary);
      font-size: 14px;
      line-height: 1.6;
    }
    .assignment-card-meta {
      display: flex;
      gap: 16px;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
      font-size: 13px;
      color: var(--text-secondary);
    }
    .assignment-card-stat {
      display: flex;
      align-items: center;
      gap: 6px;
    }
  </style>
</head>
<body>
  <?php
  $pageTitle = 'Assignments';
  $showUser = false;
  $userInfo = [];
  
  $displayNameEscaped = htmlspecialchars($displayName);
  $adminBadge = ($user['role'] === 'admin') ? '<span class="user-badge">Admin</span>' : '';
  $adminLink = ($user['role'] === 'admin') ? '<a class="admin-link" href="admin.php" title="Admin Dashboard">Admin</a>' : '';
  
  $headerActions = <<<HTML
    <div class="toolbar">
      <button id="dashboard-btn" onclick="window.location.href='dashboard.php'" title="Zurück">⬅</button>
      <button id="back-to-list-btn" onclick="location.href='assignments.php'" style="display:none;" title="Zurück">⬅</button>
      <button id="run-btn" style="display:none;">Run</button>
      <button id="check-btn" style="display:none; background:#667eea; color:#fff; border-color:transparent;">🔍 Check (0/10)</button>
      <button id="submit-btn" style="display:none; background:#10b981; color:#fff; border-color:transparent;">📤 Abgeben</button>
      <span id="attempts-counter" style="display:none;"></span>
      <button id="undo-btn" class="icon-btn" style="display:none;" title="Rückgängig">↶</button>
      <button id="redo-btn" class="icon-btn" style="display:none;" title="Wiederherstellen">↷</button>
      <button id="save-task-btn" class="icon-btn" style="display:none;" title="Speichern">💾</button>
      <button id="download-btn" class="icon-btn" style="display:none;" title="Herunterladen">⬇</button>
      <div id="submitted-info" style="margin:0 12px; font-weight:600; color:var(--text-primary);">
        <span id="submitted-status" style="width:12px; height:12px; border-radius:50%; flex-shrink:0;"></span>
        <span>Abgegeben: <span id="submitted-date"></span></span>
        <span>Checks <span id="submitted-checks"></span></span>
        <span>Hints <span id="submitted-hints"></span></span>
      </div>
      <div style="flex:1"></div>
      <div class="user-bar">
        <div class="user-info">
          <span>{$displayNameEscaped}</span>
          {$adminBadge}
        </div>
        {$adminLink}
        <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
        <button id="logout-btn" title="Abmelden">🚪</button>
      </div>
    </div>
HTML;
  include(__DIR__ . '/../components/header.php');
  ?>

  <div class="current-project-bar" id="current-project-bar" style="display:none;">
    <span>Projekt: <span class="current-project-name" id="current-project-name">Ungespeichert</span></span>
    <span id="project-visibility"></span>
  </div>

  <!-- Assignment List View (initial view) -->
  <div id="assignment-list-view" class="assignment-list-view">
    <div class="assignment-list-header">
      <h1>Meine Aufgaben</h1>
      <p style="color:var(--text-secondary); margin-top:8px;">Wählen Sie ein Assignment aus um zu starten</p>
    </div>
    <div class="assignment-list-container" id="assignment-list-container">
      <p style="padding:20px; color:var(--text-secondary);">Lade Assignments...</p>
    </div>
  </div>

  <!-- Editor View (shown when assignment selected) -->
  <div class="app" id="editor-view" style="display:none;">
    <div id="task-details-panel">
      <div class="task-navigation" id="task-navigation">
        <p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Keine Aufgaben geladen</p>
      </div>
      <div class="task-details-content" id="task-details-content">
        <p>Laden Sie eine Aufgabe um Details zu sehen</p>
      </div>
    </div>

    <div class="left">
      <div id="editor-container"></div>

      <div class="left-bottom">
        <div id="lint-container"></div>
        <div id="help-container"></div>
      </div>
    </div>

    <div class="right">
      <div id="output-container"></div>
      <div id="plot-container"></div>
    </div>
  </div>

  <div id="projects-panel" class="projects-panel" style="display:none;">
    <!-- Projects panel removed - use projects.php instead -->
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

  <!-- Editor resources loaded lazily when needed -->
  <script>
    // Lazy load Monaco and Pyodide only when opening a task
    window.editorResourcesLoaded = false;
    window.editorResourcesLoading = false;
    
    window.loadEditorResources = function() {
      return new Promise((resolve, reject) => {
        if (window.editorResourcesLoaded) {
          resolve();
          return;
        }
        
        if (window.editorResourcesLoading) {
          // Wait for existing load to complete
          const checkInterval = setInterval(() => {
            if (window.editorResourcesLoaded) {
              clearInterval(checkInterval);
              resolve();
            }
          }, 100);
          return;
        }
        
        window.editorResourcesLoading = true;
        
        // Load Monaco
        const monacoLoader = document.createElement('script');
        monacoLoader.src = 'monaco/min/vs/loader.js';
        monacoLoader.onload = () => {
          require.config({ paths: { vs: 'monaco/min/vs' } });
          
          // Load Pyodide
          const pyodideScript = document.createElement('script');
          pyodideScript.src = 'pyodide/pyodide.js';
          pyodideScript.onload = () => {
            // Load helper scripts
            const loadScript = (src) => {
              return new Promise((res, rej) => {
                const script = document.createElement('script');
                script.src = src;
                script.onload = res;
                script.onerror = rej;
                document.body.appendChild(script);
              });
            };
            
            Promise.all([
              loadScript('js/file-tree.js'),
              loadScript('js/code-validator.js')
            ]).then(() => {
              // Load editor-setup as module
              const editorSetup = document.createElement('script');
              editorSetup.type = 'module';
              editorSetup.src = 'js/editor-setup.js';
              editorSetup.onload = () => {
                window.editorResourcesLoaded = true;
                window.editorResourcesLoading = false;
                resolve();
              };
              editorSetup.onerror = reject;
              document.body.appendChild(editorSetup);
            }).catch(reject);
          };
          pyodideScript.onerror = reject;
          document.body.appendChild(pyodideScript);
        };
        monacoLoader.onerror = reject;
        document.body.appendChild(monacoLoader);
      });
    };
  </script>

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
  <script type="module" src="js/assignments.js?v=20250224"></script>
</body>
</html>
