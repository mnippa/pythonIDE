// projects.js - Project management for logged-in users

let currentProject = null;
let editorInstance = null;

// Wait for editor to be ready
function waitForEditor() {
  return new Promise((resolve) => {
    const check = () => {
      if (window.monaco && window.monaco.editor) {
        const editors = window.monaco.editor.getEditors();
        if (editors.length > 0) {
          editorInstance = editors[0];
          resolve(editorInstance);
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

// Load projects list
async function loadProjects() {
  const projectsList = document.getElementById('projects-list');
  projectsList.innerHTML = 'Lade Projekte...';
  
  try {
    const response = await fetch('../api/projects/list.php', {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });
    const data = await response.json();
    
    if (data.ok && data.projects.length > 0) {
      projectsList.innerHTML = data.projects.map(project => `
        <div class="project-item" data-id="${project.id}">
          <div class="project-name">${escapeHtml(project.name)}</div>
          <div class="project-meta">
            <span class="visibility-badge visibility-${project.visibility}">
              ${project.visibility === 'public' ? '🌐 Public' : '🔒 Private'}
            </span>
            <span>${new Date(project.updated_at).toLocaleDateString('de-DE')}</span>
          </div>
          ${project.description ? `<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">${escapeHtml(project.description)}</div>` : ''}
        </div>
      `).join('');
      
      // Add click handlers
      document.querySelectorAll('.project-item').forEach(item => {
        item.addEventListener('click', () => {
          const projectId = item.dataset.id;
          loadProject(projectId);
        });
      });
    } else {
      projectsList.innerHTML = '<p style="text-align:center;color:var(--text-secondary);padding:20px;">Noch keine Projekte.<br>Erstelle dein erstes Projekt!</p>';
    }
  } catch (err) {
    projectsList.innerHTML = '<p style="color:#c00;text-align:center;padding:20px;">Fehler beim Laden</p>';
  }
}

// Load specific project
async function loadProject(projectId) {
  try {
    const response = await fetch(`../api/projects/load.php?id=${projectId}`, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });
    const data = await response.json();
    
    if (data.ok) {
      currentProject = data.project;
      
      // Wait for editor to be ready
      const editor = await waitForEditor();
      
      // Set code in editor
      editor.setValue(data.project.code || '');
      
      // Update UI
      document.getElementById('current-project-bar').style.display = 'flex';
      document.getElementById('current-project-name').textContent = data.project.name;
      document.getElementById('save-btn').style.display = 'block';
      
      // Show visibility
      const visibilityEl = document.getElementById('project-visibility');
      if (data.project.visibility === 'public') {
        const shareUrl = `${window.location.origin}/pythonIDE/public/share.php?token=${data.project.share_token}`;
        visibilityEl.innerHTML = `<span class="visibility-badge visibility-public">🌐 Public</span> 
          <button onclick="copyShareLink('${shareUrl}')" style="padding:4px 8px;font-size:11px;margin-left:8px;">Link kopieren</button>`;
      } else {
        visibilityEl.innerHTML = '<span class="visibility-badge visibility-private">🔒 Private</span>';
      }
      
      // Close panel
      document.getElementById('projects-panel').classList.remove('open');
    } else {
      alert('Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler'));
    }
  } catch (err) {
    alert('Verbindungsfehler beim Laden');
  }
}

// Save current project
async function saveProject() {
  if (!currentProject) {
    return await createNewProject();
  }
  
  const editor = await waitForEditor();
  const code = editor.getValue();
  
  try {
    const response = await fetch('../api/projects/update.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: currentProject.id,
        code: code
      })
    });
    
    const data = await response.json();
    
    if (data.ok) {
      showNotification('✓ Gespeichert', 'success');
      currentProject.updated_at = data.project.updated_at;
    } else {
      showNotification('✗ ' + (data.error || 'Speichern fehlgeschlagen'), 'error');
    }
  } catch (err) {
    showNotification('✗ Verbindungsfehler', 'error');
  }
}

// Create new project
async function createNewProject() {
  const name = prompt('Projektname:');
  if (!name) return;
  
  const description = prompt('Beschreibung (optional):') || '';
  const isPublic = confirm('Projekt öffentlich teilen?');
  
  const editor = await waitForEditor();
  const code = editor.getValue();
  
  try {
    const response = await fetch('../api/projects/create.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: name,
        description: description,
        code: code,
        visibility: isPublic ? 'public' : 'private'
      })
    });
    
    const data = await response.json();
    
    if (data.ok) {
      currentProject = data.project;
      showNotification('✓ Projekt erstellt', 'success');
      
      // Update UI
      document.getElementById('current-project-bar').style.display = 'flex';
      document.getElementById('current-project-name').textContent = data.project.name;
      document.getElementById('save-btn').style.display = 'block';
      
      // Reload projects list
      loadProjects();
    } else {
      alert('Fehler: ' + (data.error || 'Projekt konnte nicht erstellt werden'));
    }
  } catch (err) {
    alert('Verbindungsfehler');
  }
}

// Copy share link
window.copyShareLink = function(url) {
  navigator.clipboard.writeText(url).then(() => {
    showNotification('✓ Link kopiert!', 'success');
  }).catch(() => {
    prompt('Share-Link:', url);
  });
};

// Show notification
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 70px;
    right: 20px;
    padding: 12px 20px;
    background: ${type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6'};
    color: white;
    border-radius: 8px;
    font-weight: 600;
    z-index: 10000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.transition = 'opacity 0.3s';
    notification.style.opacity = '0';
    setTimeout(() => notification.remove(), 300);
  }, 2000);
}

// Escape HTML
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  // Load projects on open
  const projectsBtn = document.getElementById('projects-btn');
  projectsBtn?.addEventListener('click', () => {
    loadProjects();
  });
  
  // Check if project_id is in URL params
  const urlParams = new URLSearchParams(window.location.search);
  const projectId = urlParams.get('project_id');
  if (projectId) {
    loadProject(projectId);
  }
  
  // New project button
  document.getElementById('new-project-btn')?.addEventListener('click', () => {
    createNewProject();
  });
  
  // Save button
  document.getElementById('save-btn')?.addEventListener('click', () => {
    saveProject();
  });
  
  // Keyboard shortcut for save (Ctrl+S)
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      if (currentProject) {
        saveProject();
      }
    }
  });
});
