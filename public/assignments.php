<?php
/**
 * Assignments List - Shows available assignments
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
  <style>
    :root {
      --border:#e5e7eb; --muted:#6b7280; --bg:#fff; --panel:#f9fafb;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
    }
    
    html.dark-mode {
      --border:#374151; --muted:#9ca3af; --bg:#1e1e1e; --panel:#252526;
      --text-primary: #e6edf3;
      --text-secondary: #8b949e;
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
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; transition:background 0.3s; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; display:block; width:20px; height:20px; line-height:20px; transition:transform 0.3s; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }

    /* Assignment List Styles */
    .assignment-list-view {
      max-width: 1200px;
      margin: 0 auto;
      padding: var(--hspf-spacing-lg);
      min-height: calc(100vh - 100px);
    }
    
    .assignment-list-header {
      margin-bottom: var(--hspf-spacing-xl);
    }
    
    .assignment-list-header h1 {
      margin: 0;
      font-size: 32px;
      font-weight: 300;
      color: var(--text-primary);
    }
    
    .assignment-list-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: var(--hspf-spacing-lg);
    }
    
    .assignment-card {
      background: var(--panel);
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 20px;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .assignment-card:hover {
      border-color: var(--hspf-accent);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      transform: translateY(-2px);
    }
    
    .assignment-card-title {
      font-size: 20px;
      font-weight: 600;
      margin: 0 0 12px 0;
      color: var(--text-primary);
    }
    
    .assignment-card-description {
      font-size: 14px;
      color: var(--text-secondary);
      margin-bottom: 16px;
      line-height: 1.5;
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

  <!-- Assignment List View -->
  <div id="assignment-list-view" class="assignment-list-view">
    <div class="assignment-list-header">
      <h1>Meine Aufgaben</h1>
      <p style="color:var(--text-secondary); margin-top:8px;">Wählen Sie ein Assignment aus um zu starten</p>
    </div>
    <div class="assignment-list-container" id="assignment-list-container">
      <p style="padding:20px; color:var(--text-secondary);">Lade Assignments...</p>
    </div>
  </div>

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
  </script>
  <script type="module" src="js/assignments.js"></script>
</body>
</html>
