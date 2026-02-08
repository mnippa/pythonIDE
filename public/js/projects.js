// projects.js - Project management for logged-in users

let currentProject = null;
let editorInstance = null;

// Make currentProject globally accessible
window.currentProject = null;

// Wait for editor to be ready - improved version
function waitForEditor() {
  return new Promise((resolve) => {
    let attempts = 0;
    const check = () => {
      attempts++;
      // Check both local editorInstance and global window.editorInstance
      if (window.editorInstance) {
        editorInstance = window.editorInstance;
        console.log('Editor ready after', attempts, 'attempts');
        resolve(editorInstance);
        return;
      }
      if (window.monaco && window.monaco.editor) {
        const editors = window.monaco.editor.getEditors();
        if (editors.length > 0) {
          editorInstance = editors[0];
          window.editorInstance = editorInstance;
          console.log('Editor found via getEditors() after', attempts, 'attempts');
          resolve(editorInstance);
          return;
        }
      }
      if (attempts < 200) { // max 20 seconds
        setTimeout(check, 100);
      } else {
        console.error('Editor did not initialize after', attempts, 'attempts');
        resolve(null);
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
    console.log('loadProject called with ID:', projectId);
    
    const response = await fetch(`../api/projects/load.php?id=${projectId}`, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });
    const data = await response.json();
    
    console.log('Project API response:', data);
    
    if (data.ok) {
      currentProject = data.project;
      window.currentProject = data.project;
      console.log('Project loaded:', currentProject.name);
      
      // Get editor - use global reference first
      let editor = window.editorInstance;
      if (!editor) {
        console.log('No window.editorInstance, waiting for editor...');
        editor = await waitForEditor();
      }
      
      if (!editor) {
        console.error('Failed to get editor instance');
        alert('Editor konnte nicht initialisiert werden');
        return;
      }
      
      // Set code in editor
      const code = data.project.code || '';
      console.log('Setting code in editor, length:', code.length);
      editor.setValue(code);
      
      // Update UI
      const currentBar = document.getElementById('current-project-bar');
      if (currentBar) {
        currentBar.style.display = 'flex';
      }
      
      const nameEl = document.getElementById('current-project-name');
      if (nameEl) {
        nameEl.textContent = data.project.name;
      }
      
      const saveBtn = document.getElementById('save-btn');
      if (saveBtn) {
        saveBtn.style.display = 'block';
      }
      
      // Show visibility
      const visibilityEl = document.getElementById('project-visibility');
      if (visibilityEl) {
        if (data.project.visibility === 'public') {
          const shareUrl = `${window.location.origin}/pythonIDE/public/share.php?token=${data.project.share_token}`;
          visibilityEl.innerHTML = `<span class="visibility-badge visibility-public">🌐 Public</span> 
            <button onclick="copyShareLink('${shareUrl}')" style="padding:4px 8px;font-size:11px;margin-left:8px;">Link kopieren</button>`;
        } else {
          visibilityEl.innerHTML = '<span class="visibility-badge visibility-private">🔒 Private</span>';
        }
      }
      
      // Close panel
      const panel = document.getElementById('projects-panel');
      if (panel) {
        panel.classList.remove('open');
      }
      
      console.log('Project loaded successfully in editor');
    } else {
      console.error('Project load failed:', data.error);
      alert('Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler'));
    }
  } catch (err) {
    console.error('Project load exception:', err);
    alert('Verbindungsfehler beim Laden');
  }
}

// Make loadProject globally available
window.loadProject = loadProject;

// Export for ES module usage
export { loadProject as loadProjectById };

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
document.addEventListener('DOMContentLoaded', async () => {
  // Load projects on open
  const projectsBtn = document.getElementById('projects-btn');
  projectsBtn?.addEventListener('click', () => {
    loadProjects();
  });
  
  // Note: project_id loading is now handled in editor-setup.js after editor is ready
  // to ensure proper initialization order
  
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
