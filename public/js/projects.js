// projects.js - Project management for logged-in users

let currentProject = null;
let editorInstance = null;
let fileTreeManager = null;
let currentAccess = null;
let isSharedTokenMode = false;
let currentShareToken = null;

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

function scopeProjectCssToGui(cssText, scopeSelector) {
  if (!cssText || !scopeSelector) return cssText || '';

  const keyframePlaceholders = [];
  let css = String(cssText);

  // Keep keyframes untouched, otherwise selector prefixing would break 0%/100% blocks.
  css = css.replace(/@keyframes\s+[^{]+\{[\s\S]*?\}\s*\}/g, (match) => {
    const token = `__PYIDE_KEYFRAMES_${keyframePlaceholders.length}__`;
    keyframePlaceholders.push(match);
    return token;
  });

  css = css.replace(/(^|[{}])\s*([^@{}][^{}]*)\{/g, (full, prefix, selectorGroup) => {
    const scopedSelectors = selectorGroup
      .split(',')
      .map((rawSelector) => {
        const selector = rawSelector.trim();
        if (!selector) return selector;
        if (selector.startsWith(scopeSelector)) return selector;
        if (selector === 'html' || selector === 'body' || selector === ':root') {
          return scopeSelector;
        }
        return `${scopeSelector} ${selector}`;
      })
      .join(', ');

    return `${prefix}\n${scopedSelectors}{`;
  });

  keyframePlaceholders.forEach((content, index) => {
    const token = `__PYIDE_KEYFRAMES_${index}__`;
    css = css.replace(token, content);
  });

  return css;
}

// Render HTML/CSS for HTML projects (similar to renderCodeUiHtml for tasks)
async function renderProjectHtml(projectId) {
  const guiContainer = document.getElementById('gui-container');
  if (!guiContainer || !projectId) return;

  const readProjectFile = async (fileName) => {
    try {
      // Get file tree to find file ID
      const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${projectId}`, {
        credentials: 'include',
        cache: 'no-store'
      });
      
      if (!treeResponse.ok) {
        throw new Error(`Failed to load file tree`);
      }
      
      const treeData = await treeResponse.json();
      if (!treeData.ok) {
        throw new Error(treeData.error || 'Failed to load file tree');
      }
      
      // Find file in tree
      let fileId = null;
      const findFile = (nodes) => {
        for (const node of nodes) {
          if (node.type === 'file' && node.name === fileName) {
            fileId = node.id;
            return true;
          }
          if (node.children && findFile(node.children)) {
            return true;
          }
        }
        return false;
      };
      
      findFile(treeData.tree || []);
      
      if (!fileId) {
        throw new Error(`${fileName} not found`);
      }
      
      // Read file content
      const fileResponse = await fetch(`../api/projects/files-v2.php?action=read&project_id=${projectId}&file_id=${fileId}`, {
        credentials: 'include',
        cache: 'no-store'
      });
      
      if (!fileResponse.ok) {
        throw new Error(`Failed to read ${fileName}`);
      }
      
      const fileData = await fileResponse.json();
      if (!fileData.ok) {
        throw new Error(fileData.error || `Failed to read ${fileName}`);
      }
      
      return String(fileData.content || '');
    } catch (err) {
      console.error(`Error reading ${fileName}:`, err);
      return '';
    }
  };

  try {
    const htmlContent = await readProjectFile('index.html');
    const cssContent = await readProjectFile('style.css');

    if (!htmlContent) {
      console.warn('[projects.js] No index.html found for project - creating minimal template');
      // Fallback: Create minimal HTML structure
      guiContainer.innerHTML = `
        <div class="code-ui-wrapper" style="font-family: system-ui, sans-serif; padding: 16px;">
          <div id="idegui-root" data-idegui-root="true" style="min-height: 180px; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; background: #fff;"></div>
          <div id="idegui-output" data-idegui-output="true" style="margin-top: 12px; font-size: 14px; color: #374151; padding: 8px; background: #f9fafb; border-radius: 4px;"></div>
        </div>
      `;
      guiContainer.classList.add('active');
      console.log('[projects.js] Minimal HTML template created for project', projectId);
      return;
    }

    // Clear GUI container
    guiContainer.innerHTML = '';
    guiContainer.classList.add('active');

    const parser = new DOMParser();
    const parsed = parser.parseFromString(htmlContent, 'text/html');
    const bodyHtml = parsed?.body?.innerHTML?.trim();
    const inlineStyleTags = parsed?.querySelectorAll?.('style') || [];
    const inlineCss = Array.from(inlineStyleTags).map((tag) => tag.textContent || '').join('\n');

    guiContainer.innerHTML = `<div class="project-code-ui-scope">${bodyHtml || ''}</div>`;
    guiContainer.dataset.projectId = String(projectId);

    // Inject CSS
    if (cssContent || inlineCss) {
      const combinedCss = [cssContent, inlineCss].filter(Boolean).join('\n\n');
      const scopedCss = scopeProjectCssToGui(combinedCss, '#gui-container .project-code-ui-scope');
      let styleEl = document.getElementById('project-dynamic-styles');
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'project-dynamic-styles';
        document.head.appendChild(styleEl);
      }
      styleEl.textContent = scopedCss;
    }

    console.log('[projects.js] HTML rendered for project', projectId);
  } catch (err) {
    console.error('[projects.js] Failed to render project HTML:', err);
  }
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
    const projectsTitleEl = document.querySelector('#projects-panel .projects-header h2');
    if (projectsTitleEl) {
      projectsTitleEl.textContent = data.scope === 'all' ? 'Alle Projekte' : 'Meine Projekte';
    }
    
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
          ${project.owner_name ? `<div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">👤 ${escapeHtml(project.owner_name)}</div>` : ''}
          ${project.description ? `<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">${escapeHtml(project.description)}</div>` : ''}
          <button class="delete-project-btn" data-project-id="${project.id}" data-project-name="${escapeHtml(project.name)}" style="
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 8px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
          " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">🗑️ Löschen</button>
        </div>
      `).join('');
      
      // Add click handlers for project items
      document.querySelectorAll('.project-item').forEach(item => {
        item.addEventListener('click', (e) => {
          // Don't open project if delete button was clicked
          if (e.target.classList.contains('delete-project-btn')) {
            return;
          }
          const projectId = item.dataset.id;
          loadProject(projectId);
        });
      });
      
      // Add delete handlers
      document.querySelectorAll('.delete-project-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.stopPropagation(); // Prevent opening project
          const projectId = btn.dataset.projectId;
          const projectName = btn.dataset.projectName;
          await deleteProject(projectId, projectName);
        });
      });
    } else {
      projectsList.innerHTML = '<p style="text-align:center;color:var(--text-secondary);padding:20px;">Noch keine Projekte.<br>Erstelle dein erstes Projekt!</p>';
    }
  } catch (err) {
    projectsList.innerHTML = '<p style="color:#c00;text-align:center;padding:20px;">Fehler beim Laden</p>';
  }
}

// Delete project
async function deleteProject(projectId, projectName) {
  const confirmed = confirm(`Projekt "${projectName}" wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden.`);
  if (!confirmed) return;

  try {
    const response = await fetch(`../api/projects/delete.php?id=${projectId}`, {
      method: 'DELETE',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });

    const data = await response.json();

    if (data.ok) {
      showNotification('✓ Projekt gelöscht', 'success');
      
      // If the deleted project is currently loaded, clear the editor
      if (currentProject && currentProject.id === parseInt(projectId)) {
        currentProject = null;
        window.currentProject = null;
        
        const currentBar = document.getElementById('current-project-bar');
        if (currentBar) currentBar.style.display = 'none';
        
        const editor = await waitForEditor();
        if (editor) editor.setValue('# Projekt auswählen oder neu erstellen\n');
        
        // Hide GUI container
        const guiContainer = document.getElementById('gui-container');
        if (guiContainer) guiContainer.classList.remove('active');
      }
      
      // Reload projects list
      await loadProjects();
    } else {
      alert('Fehler beim Löschen: ' + (data.error || 'Unbekannter Fehler'));
    }
  } catch (err) {
    console.error('Delete project error:', err);
    alert('Verbindungsfehler beim Löschen');
  }
}

function renderProjectVisibility(project, access) {
  const visibilityEl = document.getElementById('project-visibility');
  if (!visibilityEl) return;

  const canEdit = !!(access && access.can_edit);

  if (!canEdit && isSharedTokenMode) {
    visibilityEl.innerHTML = '<span class="visibility-badge visibility-public">🔗 Link-Modus (virtuell)</span>';
    return;
  }

  if (project.visibility === 'public') {
    const shareUrl = `${window.location.origin}/pythonIDE/public/share.php?token=${project.share_token}`;
    visibilityEl.innerHTML = `<span class="visibility-badge visibility-public">🌐 Public</span> 
      <button onclick="copyShareLink('${shareUrl}')" style="padding:4px 8px;font-size:11px;margin-left:8px;">Link kopieren</button>
      <button onclick="setProjectVisibility('private')" style="padding:4px 8px;font-size:11px;margin-left:6px;">Privat machen</button>`;
  } else {
    visibilityEl.innerHTML = '<span class="visibility-badge visibility-private">🔒 Private</span> <button onclick="setProjectVisibility(\'public\')" style="padding:4px 8px;font-size:11px;margin-left:8px;">Öffentlich machen</button>';
  }
}

async function loadProjectFromApi(requestUrl, options = {}) {
  try {
    console.log('loadProject request:', requestUrl);

    const response = await fetch(requestUrl, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });
    const data = await response.json();

    console.log('Project API response:', data);

    if (!data.ok) {
      console.error('Project load failed:', data.error);
      alert('Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler'));
      return;
    }

    currentProject = data.project;
    currentAccess = data.access || null;
    isSharedTokenMode = !!options.sharedTokenMode;
    currentShareToken = options.shareToken || null;
    window.currentProject = data.project;

    let editor = window.editorInstance;
    if (!editor) {
      editor = await waitForEditor();
    }

    if (!editor) {
      console.error('Failed to get editor instance');
      alert('Editor konnte nicht initialisiert werden');
      return;
    }

    const code = data.project.code || '';
    editor.setValue(code);

    const currentBar = document.getElementById('current-project-bar');
    if (currentBar) currentBar.style.display = 'flex';

    const nameEl = document.getElementById('current-project-name');
    if (nameEl) {
      nameEl.textContent = data.project.name || 'Unbenannt';
      if (isSharedTokenMode) {
        nameEl.textContent += ' (Link)';
      }
    }

    const canEdit = !!(data.access && data.access.can_edit);
    const fileTreeWrapper = document.getElementById('file-tree-wrapper');
    const fileTreePanel = document.getElementById('file-tree-panel');
    if (fileTreePanel) {
      fileTreePanel.style.display = canEdit ? 'flex' : 'none';
    }

    if (canEdit && fileTreeWrapper) {
      fileTreeWrapper.classList.add('active');
    } else if (fileTreeWrapper) {
      fileTreeWrapper.classList.remove('active');
      fileTreeWrapper.innerHTML = '<div style="padding:10px;color:var(--text-secondary);font-size:12px;">Link-Modus: Dateibaum deaktiviert</div>';
    }

    const treeToggle = document.getElementById('file-tree-toggle');
    if (treeToggle) {
      treeToggle.textContent = `▼ ${data.project.name}`;
      treeToggle.dataset.label = data.project.name;
    }

    if (window.fileTreeManager && typeof window.fileTreeManager.destroy === 'function') {
      window.fileTreeManager.destroy();
    }

    if (canEdit && window.FileTreeManager && options.projectIdForTree) {
      fileTreeManager = new window.FileTreeManager('file-tree-wrapper', {
        projectId: options.projectIdForTree,
        projectName: data.project.name,
        onFileSelected: (fileId, fileName, content) => {
          if (editor) editor.setValue(content);
        },
        onFileSaved: () => {},
        onFileDeleted: () => {}
      });
      window.fileTreeManager = fileTreeManager;

      if (typeof fileTreeManager.init === 'function') {
        await fileTreeManager.init();
      } else if (
        typeof fileTreeManager.initializeDefaultStructure === 'function' &&
        typeof fileTreeManager.render === 'function'
      ) {
        const structure = fileTreeManager.initializeDefaultStructure(data.project.name);
        fileTreeManager.render(structure);
      }
    } else {
      fileTreeManager = null;
      window.fileTreeManager = null;
    }

    const saveProjectBtn = document.getElementById('save-project-btn');
    const saveAsProjectBtn = document.getElementById('save-as-project-btn');
    if (saveProjectBtn) {
      saveProjectBtn.style.display = canEdit ? 'inline-block' : 'none';
    }
    if (saveAsProjectBtn) {
      saveAsProjectBtn.style.display = canEdit ? 'none' : 'inline-block';
    }

    // Toggle GUI container based on project type
    const guiContainer = document.getElementById('gui-container');
    if (guiContainer) {
      const projectType = data.project.project_type || 'python';
      const showGui = projectType === 'html' || projectType === 'mixed';
      if (showGui) {
        guiContainer.classList.add('active');
        // Load HTML content for HTML/mixed projects
        await renderProjectHtml(options.projectIdForTree);
      } else {
        guiContainer.classList.remove('active');
      }
    }

    const saveTaskBtn = document.getElementById('save-task-btn');
    if (saveTaskBtn) saveTaskBtn.style.display = 'none';
    const downloadBtn = document.getElementById('download-btn');
    if (downloadBtn) downloadBtn.style.display = 'none';
    const checkBtn = document.getElementById('check-btn');
    if (checkBtn) checkBtn.style.display = 'none';
    const attemptsCounter = document.getElementById('attempts-counter');
    if (attemptsCounter) attemptsCounter.style.display = 'none';

    renderProjectVisibility(data.project, data.access || {});

    const panel = document.getElementById('projects-panel');
    if (panel) panel.classList.remove('open');
  } catch (err) {
    console.error('Project load exception:', err);
    alert('Verbindungsfehler beim Laden');
  }
}

async function loadProject(projectId) {
  const requestUrl = `../api/projects/load.php?id=${projectId}`;
  return loadProjectFromApi(requestUrl, {
    projectIdForTree: projectId,
    sharedTokenMode: false,
    shareToken: null
  });
}

async function loadSharedProject(shareToken) {
  const requestUrl = `../api/projects/load.php?token=${encodeURIComponent(shareToken)}`;
  return loadProjectFromApi(requestUrl, {
    projectIdForTree: null,
    sharedTokenMode: true,
    shareToken
  });
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

  if (currentAccess && currentAccess.can_edit === false) {
    return await saveAsOwnProject();
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

async function saveAsOwnProject() {
  const editor = await waitForEditor();
  if (!editor) {
    showNotification('✗ Editor nicht bereit', 'error');
    return;
  }

  const baseName = currentProject?.name ? `${currentProject.name} (Kopie)` : 'Geteiltes Projekt (Kopie)';
  const name = prompt('Neuer Projektname:', baseName);
  if (!name) return;

  const description = currentProject?.description || 'Importiert aus geteiltem Projektlink';
  const projectType = currentProject?.project_type || 'python';
  const code = editor.getValue();

  try {
    const response = await fetch('../api/projects/create.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name,
        description,
        code,
        project_type: projectType,
        visibility: 'private'
      })
    });

    const data = await response.json();
    if (!data.ok) {
      showNotification('✗ ' + (data.error || 'Kopie konnte nicht erstellt werden'), 'error');
      return;
    }

    showNotification('✓ Als eigenes Projekt gespeichert', 'success');
    await loadProject(data.project.id);
    await loadProjects();
  } catch (err) {
    showNotification('✗ Verbindungsfehler', 'error');
  }
}

// Create new project - show a proper form dialog
async function createNewProject() {
  const formData = await showCreateProjectDialog();
  if (!formData) return; // User cancelled
  
  const editor = await waitForEditor();
  const code = editor.getValue();
  
  try {
    const response = await fetch('../api/projects/create.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: formData.name,
        description: formData.description,
        code: code,
        project_type: formData.projectType,
        visibility: formData.isPublic ? 'public' : 'private'
      })
    });
    
    const data = await response.json();
    
    if (data.ok) {
      showNotification('✓ Projekt erstellt', 'success');
      
      // Reload projects list
      await loadProjects();
      
      // Load the newly created project
      await loadProject(data.project.id);
    } else {
      console.error('Project creation failed:', data);
      alert('Fehler: ' + (data.error || 'Projekt konnte nicht erstellt werden'));
    }
  } catch (err) {
    console.error('Connection error creating project:', err);
    alert('Verbindungsfehler: ' + err.message);
  }
}

// Show create project dialog - returns form data or null if cancelled
function showCreateProjectDialog() {
  return new Promise((resolve) => {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 99999;
    `;

    // Create dialog
    const dialog = document.createElement('div');
    dialog.style.cssText = `
      background: #2a2a2a;
      border: 1px solid #444;
      border-radius: 8px;
      padding: 25px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
      color: #e8e8e8;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;

    dialog.innerHTML = `
      <h3 style="margin: 0 0 20px 0; color: #fff; font-size: 16px; font-weight: 600;">Neues Projekt</h3>
      
      <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #aaa; font-weight: 500;">Projektname *</label>
        <input type="text" id="projectName" placeholder="z.B. Mein Projekt" style="
          width: 100%;
          padding: 8px 12px;
          border: 1px solid #444;
          border-radius: 4px;
          background: #333;
          color: #e8e8e8;
          box-sizing: border-box;
          font-family: inherit;
          font-size: 13px;
        " />
      </div>

      <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #aaa; font-weight: 500;">Beschreibung (optional)</label>
        <input type="text" id="projectDescription" placeholder="Kurze Beschreibung" style="
          width: 100%;
          padding: 8px 12px;
          border: 1px solid #444;
          border-radius: 4px;
          background: #333;
          color: #e8e8e8;
          box-sizing: border-box;
          font-family: inherit;
          font-size: 13px;
        " />
      </div>

      <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 8px; font-size: 12px; color: #aaa; font-weight: 500;">Projekt-Typ *</label>
        <div style="display: flex; gap: 15px;">
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; color: #e8e8e8;">
            <input type="radio" name="projectType" value="python" checked style="cursor: pointer; width: 14px; height: 14px;" />
            🐍 Python
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; color: #e8e8e8;">
            <input type="radio" name="projectType" value="html" style="cursor: pointer; width: 14px; height: 14px;" />
            🌐 HTML
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; color: #e8e8e8;">
            <input type="radio" name="projectType" value="mixed" style="cursor: pointer; width: 14px; height: 14px;" />
            🎨 Mixed
          </label>
        </div>
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #e8e8e8;">
          <input type="checkbox" id="isPublic" style="cursor: pointer; width: 14px; height: 14px;" />
          Öffentlich teilen
        </label>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <button id="cancelBtn" style="
          padding: 8px 16px;
          border: 1px solid #444;
          background: #333;
          color: #e8e8e8;
          border-radius: 4px;
          cursor: pointer;
          font-size: 13px;
          font-weight: 500;
          transition: all 0.2s;
        ">Abbrechen</button>
        <button id="createBtn" style="
          padding: 8px 16px;
          border: none;
          background: #4CAF50;
          color: white;
          border-radius: 4px;
          cursor: pointer;
          font-size: 13px;
          font-weight: 600;
          transition: all 0.2s;
          position: relative;
          z-index: 100000;
        ">✓ Erstellen</button>
      </div>
    `;

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    // Focus name input
    setTimeout(() => {
      const nameInput = dialog.querySelector('#projectName');
      if (nameInput) nameInput.focus();
    }, 100);

    // Event handlers
    const handleCancel = () => {
      overlay.remove();
      resolve(null);
    };

    const handleCreate = () => {
      const name = dialog.querySelector('#projectName').value.trim();
      if (!name) {
        alert('Bitte geben Sie einen Projektnamen ein');
        return;
      }

      const description = dialog.querySelector('#projectDescription').value.trim();
      const projectType = document.querySelector('input[name="projectType"]:checked').value;
      const isPublic = dialog.querySelector('#isPublic').checked;

      overlay.remove();
      resolve({
        name,
        description,
        projectType,
        isPublic
      });
    };

    // Add hover effects
    const createBtn = dialog.querySelector('#createBtn');
    const cancelBtn = dialog.querySelector('#cancelBtn');
    
    createBtn.addEventListener('mouseover', () => {
      createBtn.style.background = '#45a049';
      createBtn.style.transform = 'scale(1.02)';
    });
    createBtn.addEventListener('mouseout', () => {
      createBtn.style.background = '#4CAF50';
      createBtn.style.transform = 'scale(1)';
    });
    
    cancelBtn.addEventListener('mouseover', () => {
      cancelBtn.style.background = '#444';
    });
    cancelBtn.addEventListener('mouseout', () => {
      cancelBtn.style.background = '#333';
    });

    cancelBtn.addEventListener('click', handleCancel);
    createBtn.addEventListener('click', handleCreate);

    // Enter key to create, Escape to cancel
    dialog.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') handleCreate();
    });
    overlay.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') handleCancel();
    });
  });
}

// Copy share link
window.copyShareLink = function(url) {
  navigator.clipboard.writeText(url).then(() => {
    showNotification('✓ Link kopiert!', 'success');
  }).catch(() => {
    prompt('Share-Link:', url);
  });
};

window.setProjectVisibility = async function(visibility) {
  if (!currentProject || !currentProject.id) {
    showNotification('✗ Kein Projekt geladen', 'error');
    return;
  }

  if (currentAccess && currentAccess.can_edit === false) {
    showNotification('✗ Link-Modus: bitte zuerst als eigenes Projekt speichern', 'error');
    return;
  }

  if (!['public', 'private'].includes(visibility)) {
    showNotification('✗ Ungültige Sichtbarkeit', 'error');
    return;
  }

  try {
    const response = await fetch('../api/projects/update.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: currentProject.id,
        visibility
      })
    });

    const data = await response.json();
    if (!data.ok) {
      showNotification('✗ ' + (data.error || 'Sichtbarkeit konnte nicht geändert werden'), 'error');
      return;
    }

    currentProject = { ...currentProject, ...data.project };
    window.currentProject = currentProject;

    const visibilityEl = document.getElementById('project-visibility');
    if (visibilityEl) {
      if (currentProject.visibility === 'public') {
        const shareUrl = `${window.location.origin}/pythonIDE/public/share.php?token=${currentProject.share_token}`;
        visibilityEl.innerHTML = `<span class="visibility-badge visibility-public">🌐 Public</span> 
          <button onclick="copyShareLink('${shareUrl}')" style="padding:4px 8px;font-size:11px;margin-left:8px;">Link kopieren</button>
          <button onclick="setProjectVisibility('private')" style="padding:4px 8px;font-size:11px;margin-left:6px;">Privat machen</button>`;
      } else {
        visibilityEl.innerHTML = '<span class="visibility-badge visibility-private">🔒 Private</span> <button onclick="setProjectVisibility(\'public\')" style="padding:4px 8px;font-size:11px;margin-left:8px;">Öffentlich machen</button>';
      }
    }

    showNotification(currentProject.visibility === 'public' ? '✓ Projekt ist jetzt öffentlich' : '✓ Projekt ist jetzt privat', 'success');
    loadProjects();
  } catch (err) {
    showNotification('✗ Verbindungsfehler', 'error');
  }
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
  const shareTokenFromUrl = urlParams.get('token');

  if (shareTokenFromUrl) {
    console.log('Projects: share token detected, loading in virtual edit mode');
    await waitForEditor();
    await loadSharedProject(shareTokenFromUrl);
  }
  
  if (!projectIdFromUrl && !shareTokenFromUrl) {
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

  document.getElementById('save-as-project-btn')?.addEventListener('click', () => {
    saveAsOwnProject();
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
