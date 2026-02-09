<?php
/**
 * Dashboard - Landing page after login
 * Shows navigation to Projects and Assignments
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
$isAdmin = ($user['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Python IDE - Dashboard</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .header {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 16px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .header-left h1 {
      font-size: 24px;
      font-weight: 700;
      color: white;
      margin: 0;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .user-info {
      color: white;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .user-info .name {
      font-weight: 600;
    }

    .btn-logout {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.3);
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-logout:hover {
      background: rgba(255, 255, 255, 0.3);
    }

    .container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 400px));
      gap: 32px;
      max-width: 1200px;
      width: 100%;
    }

    .dashboard-card {
      background: white;
      border-radius: 16px;
      padding: 40px 32px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .dashboard-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .dashboard-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }

    .dashboard-card:hover::before {
      transform: scaleX(1);
    }

    .card-icon {
      font-size: 72px;
      margin-bottom: 24px;
      line-height: 1;
    }

    .card-title {
      font-size: 28px;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 12px;
    }

    .card-description {
      font-size: 15px;
      color: #6b7280;
      line-height: 1.6;
      margin-bottom: 20px;
    }

    .card-stats {
      display: flex;
      gap: 24px;
      margin-top: 16px;
      padding-top: 20px;
      border-top: 1px solid #e5e7eb;
      width: 100%;
      justify-content: center;
    }

    .stat-item {
      text-align: center;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 700;
      color: #667eea;
    }

    .stat-label {
      font-size: 12px;
      color: #9ca3af;
      margin-top: 4px;
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.05em;
    }

    .welcome-section {
      text-align: center;
      margin-bottom: 48px;
      color: white;
    }

    .welcome-section h2 {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .welcome-section p {
      font-size: 18px;
      opacity: 0.9;
    }

    @media (max-width: 768px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 24px;
      }

      .welcome-section h2 {
        font-size: 28px;
      }

      .welcome-section p {
        font-size: 16px;
      }

      .card-title {
        font-size: 24px;
      }

      .card-icon {
        font-size: 60px;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-left">
      <h1>🐍 Python IDE</h1>
    </div>
    <div class="header-right">
      <div class="user-info">
        <span>👤</span>
        <span class="name"><?= htmlspecialchars($displayName) ?></span>
      </div>
      <?php if ($isAdmin): ?>
      <a href="admin.php" class="btn-logout">⚙️ Admin</a>
      <?php endif; ?>
      <button class="btn-logout" onclick="logout()">Abmelden</button>
    </div>
  </div>

  <div class="container">
    <div style="max-width: 1200px; width: 100%;">
      <div class="welcome-section">
        <h2>Willkommen zurück, <?= htmlspecialchars($user['first_name'] ?? 'Student') ?>!</h2>
        <p>Wähle einen Bereich, um loszulegen</p>
      </div>

      <div class="dashboard-grid">
        <!-- Projects Card -->
        <a href="projects.php" class="dashboard-card" id="projects-card">
          <div class="card-icon">📁</div>
          <div class="card-title">Projekte</div>
          <div class="card-description">
            Erstelle und bearbeite eigene Python-Projekte. Schreibe Code, teste Programme und speichere deine Arbeit.
          </div>
          <div class="card-stats">
            <div class="stat-item">
              <div class="stat-value" id="projects-count">-</div>
              <div class="stat-label">Projekte</div>
            </div>
            <div class="stat-item">
              <div class="stat-value" id="projects-last">-</div>
              <div class="stat-label">Zuletzt</div>
            </div>
          </div>
        </a>

        <!-- Assignments Card -->
        <a href="assignments.php" class="dashboard-card" id="assignments-card">
          <div class="card-icon">📚</div>
          <div class="card-title">Aufgaben</div>
          <div class="card-description">
            Bearbeite zugewiesene Übungsaufgaben. Löse Programmieraufgaben mit automatischer Prüfung.
          </div>
          <div class="card-stats">
            <div class="stat-item">
              <div class="stat-value" id="assignments-count">-</div>
              <div class="stat-label">Aufgaben</div>
            </div>
            <div class="stat-item">
              <div class="stat-value" id="assignments-progress">-</div>
              <div class="stat-label">Erledigt</div>
            </div>
          </div>
        </a>
      </div>
    </div>
  </div>

  <script>
    async function requestJson(url, options = {}) {
      const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          ...options.headers
        }
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    }

    async function logout() {
      try {
        await requestJson('../api/auth/logout.php', { method: 'POST', body: '{}' });
        window.location.href = 'login.php';
      } catch (err) {
        console.error('Logout failed:', err);
        // Force logout anyway
        window.location.href = 'login.php';
      }
    }

    async function loadStats() {
      try {
        // Load projects count
        const projectsResponse = await requestJson('../api/projects/list.php');
        if (projectsResponse.ok && projectsResponse.projects) {
          const projectsCount = projectsResponse.projects.length;
          document.getElementById('projects-count').textContent = projectsCount;
          
          // Show last updated time
          if (projectsCount > 0) {
            const lastProject = projectsResponse.projects[0]; // Assuming sorted by updated_at DESC
            const lastDate = new Date(lastProject.updated_at);
            const today = new Date();
            const isToday = lastDate.toDateString() === today.toDateString();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            const isYesterday = lastDate.toDateString() === yesterday.toDateString();
            
            if (isToday) {
              document.getElementById('projects-last').textContent = 'Heute';
            } else if (isYesterday) {
              document.getElementById('projects-last').textContent = 'Gestern';
            } else {
              const days = Math.floor((today - lastDate) / (1000 * 60 * 60 * 24));
              document.getElementById('projects-last').textContent = `vor ${days}d`;
            }
          } else {
            document.getElementById('projects-last').textContent = 'Neu';
          }
        }
      } catch (err) {
        console.error('Failed to load projects stats:', err);
        document.getElementById('projects-count').textContent = '0';
        document.getElementById('projects-last').textContent = 'Neu';
      }

      try {
        // Load assignments count and progress
        const assignmentsResponse = await requestJson('../api/assignments/list.php');
        if (assignmentsResponse.ok && assignmentsResponse.assignments) {
          const assignments = assignmentsResponse.assignments;
          document.getElementById('assignments-count').textContent = assignments.length;
          
          // Calculate progress (assignments with completed_tasks / total_tasks)
          let totalTasks = 0;
          let completedTasks = 0;
          
          for (const assignment of assignments) {
            const tasksResponse = await requestJson(`../api/assignments/tasks.php?assignment_id=${assignment.assignment_id}`);
            if (tasksResponse.ok && tasksResponse.tasks) {
              totalTasks += tasksResponse.tasks.length;
              
              // Count completed tasks
              const progressResponse = await requestJson(`../api/user_tasks/get.php?assignment_id=${assignment.assignment_id}`);
              if (progressResponse.ok && progressResponse.tasks) {
                completedTasks += progressResponse.tasks.filter(t => t.status === 'passed').length;
              }
            }
          }
          
          if (totalTasks > 0) {
            document.getElementById('assignments-progress').textContent = `${completedTasks}/${totalTasks}`;
          } else {
            document.getElementById('assignments-progress').textContent = '-';
          }
        }
      } catch (err) {
        console.error('Failed to load assignments stats:', err);
        document.getElementById('assignments-count').textContent = '0';
        document.getElementById('assignments-progress').textContent = '-';
      }
    }

    // Load stats on page load
    loadStats();
  </script>
</body>
</html>
