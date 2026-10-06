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
  <title>Dashboard - HS PF Python IDE</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <style>
    body {
      background: linear-gradient(135deg, 
        rgba(255, 190, 49, 0.02) 0%, 
        rgba(125, 115, 105, 0.03) 100%
      ),
      var(--hspf-bg);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .dashboard-container {
      flex: 1;
      max-width: 1400px;
      margin: 0 auto;
      padding: var(--hspf-spacing-2xl) var(--hspf-spacing-lg);
      width: 100%;
    }

    .welcome-section {
      margin-bottom: var(--hspf-spacing-2xl);
    }

    .welcome-section h2 {
      font-size: 32px;
      font-weight: 300;
      color: var(--hspf-primary);
      margin-bottom: var(--hspf-spacing-xs);
    }

    .welcome-section p {
      font-size: 16px;
      color: var(--hspf-text-secondary);
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
      gap: var(--hspf-spacing-xl);
    }

    .dashboard-card {
      background: var(--hspf-surface);
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius-lg);
      padding: var(--hspf-spacing-2xl);
      box-shadow: var(--hspf-shadow-lg);
      transition: var(--hspf-transition);
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
      height: 4px;
      background: var(--hspf-accent);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .dashboard-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--hspf-shadow-xl);
      border-color: var(--hspf-accent);
    }

    .dashboard-card:hover::before {
      transform: scaleX(1);
    }

    .card-icon {
      font-size: 64px;
      margin-bottom: var(--hspf-spacing-lg);
      line-height: 1;
    }

    .card-title {
      font-size: 26px;
      font-weight: 300;
      color: var(--hspf-primary);
      margin-bottom: var(--hspf-spacing-md);
    }

    .card-description {
      font-size: 15px;
      color: var(--hspf-text-secondary);
      line-height: 1.6;
      margin-bottom: var(--hspf-spacing-lg);
    }

    .card-stats {
      display: flex;
      gap: var(--hspf-spacing-xl);
      padding-top: var(--hspf-spacing-lg);
      border-top: 1px solid var(--hspf-border);
      width: 100%;
      justify-content: center;
    }

    .lab-status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: var(--hspf-spacing-md);
      border: 1px solid transparent;
      letter-spacing: 0.03em;
    }

    .lab-status-badge.is-bestanden {
      background: #dcfce7;
      color: #166534;
      border-color: #22c55e;
      box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.15);
    }

    .lab-status-badge.is-nachpruefung {
      background: #fef9c3;
      color: #854d0e;
      border-color: #facc15;
      box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.16);
    }

    .lab-status-badge.is-nicht-bestanden,
    .lab-status-badge.is-nicht-teilgenommen {
      background: #fee2e2;
      color: #991b1b;
      border-color: #f87171;
      box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.14);
    }

    .stat-item {
      text-align: center;
    }

    .stat-value {
      font-size: 28px;
      font-weight: 300;
      color: var(--hspf-primary);
    }

    .stat-label {
      font-size: 12px;
      color: var(--hspf-text-muted);
      margin-top: 4px;
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.05em;
    }

    @media (max-width: 768px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
        gap: var(--hspf-spacing-lg);
      }

      .welcome-section h2 {
        font-size: 26px;
      }

      .card-title {
        font-size: 22px;
      }

      .card-icon {
        font-size: 56px;
      }
    }
  </style>
</head>
<body>
  <?php 
  $pageTitle = 'Dashboard';
  $showUser = true;
  $userInfo = [
    'name' => $displayName,
    'role' => $user['role'] ?? 'user'
  ];
  $headerActions = '
    <a class="hspf-btn hspf-btn-ghost" href="change-password.php">Passwort ändern</a>
    <button class="hspf-btn hspf-btn-secondary" onclick="logout()">Abmelden</button>
  ';
  
  include(__DIR__ . '/../components/header.php');
  ?>

  <div class="dashboard-container">
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

      <!-- Lab Evaluation Card -->
      <a href="assignments.php" class="dashboard-card" id="lab-evaluation-card">
        <div class="card-icon">🧪</div>
        <div class="card-title">Laborbewertung</div>
        <div class="lab-status-badge" id="lab-status-badge">Status offen</div>
        <div class="card-description" id="lab-card-description">
          Kurzzusammenfassung zu deinen Assignments und dem aktuellen Laborstatus.
        </div>
        <div class="card-stats">
          <div class="stat-item">
            <div class="stat-value" id="lab-assignments-total">-</div>
            <div class="stat-label">Assignments</div>
          </div>
          <div class="stat-item">
            <div class="stat-value" id="lab-assignments-success">-</div>
            <div class="stat-label">Erfolgreich</div>
          </div>
        </div>
      </a>

      <?php if ($isAdmin): ?>
      <!-- Admin Card -->
      <a href="admin.php" class="dashboard-card">
        <div class="card-icon">⚙️</div>
        <div class="card-title">Verwalten</div>
        <div class="card-description">
          Verwalte Assignments, Tasks und Benutzer. Administrationsbereich für Dozenten.
        </div>
        <div class="card-stats">
          <div class="stat-item">
            <div class="stat-value">Admin</div>
            <div class="stat-label">Zugriff</div>
          </div>
        </div>
      </a>
      <?php endif; ?>
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
        const assignmentsResponse = await requestJson('../api/user_assignments/list.php');
        if (assignmentsResponse.ok && assignmentsResponse.items) {
          const assignments = assignmentsResponse.items;
          document.getElementById('assignments-count').textContent = assignments.length;

          try {
            // Calculate progress (assignments with completed_tasks / total_tasks)
            let totalTasks = 0;
            let completedTasks = 0;

            for (const assignment of assignments) {
              const assignmentId = assignment.assignment_id;
              const tasksResponse = await requestJson(`../api/assignments/tasks.php?assignment_id=${assignmentId}`);
              if (tasksResponse.ok && tasksResponse.tasks) {
                totalTasks += tasksResponse.tasks.length;

                // Count completed tasks
                const progressResponse = await requestJson(`../api/user_tasks/get.php?assignment_id=${assignmentId}`);
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
          } catch (progressErr) {
            console.error('Failed to load assignments progress:', progressErr);
            document.getElementById('assignments-progress').textContent = '-';
          }
        }
      } catch (err) {
        console.error('Failed to load assignments stats:', err);
        document.getElementById('assignments-count').textContent = '0';
        document.getElementById('assignments-progress').textContent = '-';
      }

      try {
        const labResponse = await requestJson('../api/user/lab-evaluation-summary.php');
        if (labResponse.ok) {
          const status = String(labResponse.status || (labResponse.has_team ? 'durchfuehrung' : 'nicht_teilgenommen'));
          const statusMeta = {
            durchfuehrung: {
              label: 'Durchfuehrung',
              description: 'Kurzzusammenfassung zu deinen Assignments und dem aktuellen Laborstatus.',
              badgeClass: ''
            },
            bewertung: {
              label: 'Bewertung',
              description: 'Dein Laborstand wird aktuell bewertet.',
              badgeClass: ''
            },
            bestanden: {
              label: 'Bestanden',
              description: 'Bestanden! Alle Anforderungen fuer das Labor wurden erfolgreich abgeschlossen.',
              badgeClass: 'is-bestanden'
            },
            nachpruefung: {
              label: 'Nachpruefung',
              description: 'Nachpruefung erforderlich. Bitte beachte die Hinweise deiner Betreuung.',
              badgeClass: 'is-nachpruefung'
            },
            nicht_bestanden: {
              label: 'Nicht bestanden',
              description: 'Das Labor wurde als nicht bestanden bewertet. Bitte wende dich an dein Betreuungsteam.',
              badgeClass: 'is-nicht-bestanden'
            },
            nicht_teilgenommen: {
              label: 'Nicht teilgenommen',
              description: 'Es wurde keine Teilnahme am Labor vermerkt.',
              badgeClass: 'is-nicht-teilgenommen'
            }
          };

          const badge = document.getElementById('lab-status-badge');
          const description = document.getElementById('lab-card-description');
          const total = document.getElementById('lab-assignments-total');
          const success = document.getElementById('lab-assignments-success');
          const meta = statusMeta[status] || statusMeta.durchfuehrung;

          if (badge) {
            badge.textContent = meta.label;
            badge.classList.remove('is-bestanden', 'is-nachpruefung', 'is-nicht-bestanden', 'is-nicht-teilgenommen');
            if (meta.badgeClass) {
              badge.classList.add(meta.badgeClass);
            }
          }
          if (description) {
            description.textContent = meta.description;
          }
          if (total) total.textContent = String((labResponse.counts && labResponse.counts.total) || 0);
          if (success) success.textContent = String((labResponse.counts && labResponse.counts.success) || 0);
        }
      } catch (labErr) {
        console.error('Failed to load lab evaluation summary:', labErr);
        const badge = document.getElementById('lab-status-badge');
        if (badge) badge.textContent = 'Nicht verfuegbar';
      }
    }

    // Load stats on page load
    loadStats();
    
  </script>
</body>
</html>
