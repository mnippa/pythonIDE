// projects.js - Project management for logged-in users

let currentProject = null;
let editorInstance = null;
let fileTreeManager = null;

// Make currentProject globally accessible
window.currentProject = null;
window.fileTreeManager = null;

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
      
      // Update file tree header with project name
      const treeToggle = document.getElementById('file-tree-toggle');
      if (treeToggle) {
        treeToggle.textContent = `▼ ${data.project.name}`;
        treeToggle.dataset.label = data.project.name;
      }
      
      // Initialize file tree manager for the project
      if (window.FileTreeManager) {
        fileTreeManager = new window.FileTreeManager('file-tree-wrapper', {
          projectId: projectId,
          projectName: data.project.name,
          onFileSelected: (fileId, fileName, content) => {
            console.log('File selected:', fileName);
            if (editor) {
              editor.setValue(content);
            }
          },
          onFileSaved: (fileId, fileName) => {
            console.log('File saved:', fileName);
            // Optional: show toast notification
          },
          onFileDeleted: (fileId, fileName) => {
            console.log('File deleted:', fileName);
          }
        });
        window.fileTreeManager = fileTreeManager;
        
        // Load the file tree for this project
        fileTreeManager.init();
      }
      
      const saveProjectBtn = document.getElementById('save-project-btn');
      if (saveProjectBtn) {
        saveProjectBtn.style.display = 'inline-block';
      }
      
      // Hide save-task-btn (for assignments)
      const saveTaskBtn = document.getElementById('save-task-btn');
      if (saveTaskBtn) {
        saveTaskBtn.style.display = 'none';
      }
      
      // Hide download-btn (for assignments)
      const downloadBtn = document.getElementById('download-btn');
      if (downloadBtn) {
        downloadBtn.style.display = 'none';
      }
      
      // Hide check-btn (for assignments)
      const checkBtn = document.getElementById('check-btn');
      if (checkBtn) {
        checkBtn.style.display = 'none';
      }
      
      // Hide attempts-counter (for assignments)
      const attemptsCounter = document.getElementById('attempts-counter');
      if (attemptsCounter) {
        attemptsCounter.style.display = 'none';
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
      const currentBar = document.getElementById('current-project-bar');
      if (currentBar) currentBar.style.display = 'flex';
      
      const nameEl = document.getElementById('current-project-name');
      if (nameEl) nameEl.textContent = data.project.name;
      
      const saveBtn = document.getElementById('save-project-btn');
      if (saveBtn) saveBtn.style.display = 'inline-block';
      
      // Reload projects list
      loadProjects();
    } else {
      console.error('Project creation failed:', data);
      alert('Fehler: ' + (data.error || 'Projekt konnte nicht erstellt werden'));
    }
  } catch (err) {
    console.error('Connection error creating project:', err);
    alert('Verbindungsfehler: ' + err.message);
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
  
  // Auto-load last project if no project_id in URL
  const urlParams = new URLSearchParams(window.location.search);
  const projectIdFromUrl = urlParams.get('project_id');
  
  if (!projectIdFromUrl) {
    console.log('Projects: No project ID in URL, attempting auto-load of last project');
    // Wait for editor to be ready
    await waitForEditor();
    
    // Try to load the most recently updated project
    try {
      const response = await fetch('../api/projects/list.php', {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' }
      });
      const data = await response.json();
      
      if (data.ok && data.projects && data.projects.length > 0) {
        // Projects are already sorted by updated_at DESC in API
        const lastProject = data.projects[0];
        console.log('Auto-loading last project:', lastProject.name);
        await loadProject(lastProject.id);
      } else {
        console.log('No projects found for auto-load');
      }
    } catch (err) {
      console.error('Failed to auto-load last project:', err);
    }
  }
  
  // Note: project_id loading is now handled in editor-setup.js after editor is ready
  // to ensure proper initialization order
  
  // New project button
  document.getElementById('new-project-btn')?.addEventListener('click', () => {
    createNewProject();
  });
  
  // Save button
  document.getElementById('save-project-btn')?.addEventListener('click', () => {
    saveProject();
  });
  
  // Keyboard shortcut for save (Ctrl+S)
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      
      // If a file is selected in file tree, save it
      if (window.fileTreeManager && window.fileTreeManager.selectedFileId) {
        const content = editorInstance ? editorInstance.getValue() : '';
        window.fileTreeManager.saveFile(window.fileTreeManager.selectedFileId, content);
      }
      // Otherwise save the project code
      else if (currentProject) {
        saveProject();
      }
    }
  });
});
