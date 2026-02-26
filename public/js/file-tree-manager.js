/**
 * FileTreeManager - Hierarchical File System for Projects
 * 
 * Reusable component for managing project file structures
 * Can be used in: projects.php, assignment_editor.php, test_projects, etc.
 * 
 * Usage:
 *   const manager = new FileTreeManager('container-id', {
 *     projectId: 123,
 *     projectName: 'MyProject',
 *     onFileSelected: (file) => { loadInEditor(file); },
 *     readOnly: false
 *   });
 *   manager.init();
 */

// Prevent redeclaration if script is loaded multiple times
if (typeof window.FileTreeManager === 'undefined') {

class FileTreeManager {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.projectId = options.projectId;
    this.projectName = options.projectName || 'Project';
    this.readOnly = options.readOnly || false;
    this.onFileSelected = options.onFileSelected || (() => {});
    this.onFileDeleted = options.onFileDeleted || (() => {});
    this.onFileSaved = options.onFileSaved || (() => {});
    
    this.uploadExtensions = options.uploadExtensions || ['.py', '.txt', '.md', '.json', '.html', '.htm', '.jpg', '.jpeg', '.webp', '.gif'];
    this.uploadInput = null;
    
    this.currentProjectTree = null;
    this.selectedFileId = null;
    this.selectedFileName = null;
    this.currentFolderId = null; // null = root
    this.folderPath = []; // Breadcrumb path
    this.listenersAttached = false; // Prevent duplicate listeners
  }

  /**
   * Initialize: Load tree structure from server
   */
  async init() {
    if (!this.container || !this.projectId) {
      console.error('[FileTreeManager] Container or projectId missing');
      return;
    }
    await this.loadTree();
    this.attachEventListeners();
    this.initUploadInput();
  }

  /**
   * Load file tree structure from API
   */
  async loadTree() {
    try {
      const response = await fetch(`/pythonIDE/api/projects/files-v2.php?action=tree&project_id=${this.projectId}`);
      const result = await response.json();
      
      if (result.ok) {
        this.currentProjectTree = result.tree;
        this.render(result.tree);
      } else {
        console.error('[FileTreeManager] Error loading tree:', result.error);
        this.showError(result.error);
      }
    } catch (err) {
      console.error('[FileTreeManager] Network error:', err);
      this.showError('Error loading file structure');
    }
  }

  /**
   * Render the entire file tree (current folder view)
   */
  render(tree) {
    if (!this.container) return;

    // Store tree for navigation
    this.currentProjectTree = tree;

    // Find current folder content
    const currentContent = this.getCurrentFolderContent(tree);
    
    const html = `
      <div class="file-tree">
        ${this.renderBreadcrumb()}
        <div class="file-tree-content-items">
          ${currentContent.length > 0 ? currentContent.map(item => this.renderFlatItem(item)).join('') : '<div class="file-tree-empty">Ordner ist leer</div>'}
        </div>
      </div>
    `;

    this.container.innerHTML = html;
    this.attachEventListeners();
  }

  /**
   * Render breadcrumb navigation with inline action buttons
   */
  renderBreadcrumb() {
    let breadcrumb = `<div class="file-tree-breadcrumb">`;
    breadcrumb += `<button class="breadcrumb-item" data-folder-id="null">📦</button>`;
    
    for (let i = 0; i < this.folderPath.length; i++) {
      const folder = this.folderPath[i];
      breadcrumb += ` / `;
      breadcrumb += `<button class="breadcrumb-item" data-folder-id="${folder.id}">${escapeHtml(folder.name)}</button>`;
    }
    
    breadcrumb += `<span style="flex:1;"></span>`;
    if (!this.readOnly) {
      breadcrumb += `<button class="breadcrumb-action" data-action="new-file" title="Neue Datei">📄➕</button>`;
      breadcrumb += `<button class="breadcrumb-action" data-action="new-folder" title="Neuer Ordner">📂➕</button>`;
      breadcrumb += `<button class="breadcrumb-action" data-action="upload" title="Datei hochladen">⬆</button>`;
      breadcrumb += `<button class="breadcrumb-action" data-action="download" title="Datei herunterladen">⬇</button>`;
    }
    breadcrumb += `</div>`;
    return breadcrumb;
  }

  /**
   * Get content of current folder
   */
  getCurrentFolderContent(tree) {
    if (this.currentFolderId === null) {
      return tree.children || [];
    }
    
    // Find folder in tree
    const folder = this.findFolderInTree(tree, this.currentFolderId);
    return folder ? (folder.children || []) : [];
  }

  /**
   * Find folder by ID in tree
   */
  findFolderInTree(node, folderId) {
    if (node.id === folderId && node.type === 'folder') {
      return node;
    }
    
    if (node.children) {
      for (const child of node.children) {
        const found = this.findFolderInTree(child, folderId);
        if (found) return found;
      }
    }
    
    return null;
  }

  /**
   * Render flat item (no hierarchy, just current level)
   */
  renderFlatItem(node) {
    const nodeId = node.type === 'folder' ? `folder-${node.id}` : `file-${node.id}`;
    
    let html = `<div class="file-tree-item" data-id="${nodeId}" data-node-id="${node.id}" data-type="${node.type}">`;
    
    // Icon + Name
    html += `<span class="file-tree-icon">${node.type === 'folder' ? '📁' : '📄'}</span>`;
    html += `<span class="file-tree-name">${escapeHtml(node.name)}</span>`;
    
    // File info
    if (node.type === 'file') {
      html += `<span class="file-tree-info">${node.size ? formatFileSize(node.size) : '0B'}</span>`;
    } else {
      html += `<span class="file-tree-folder-indicator">›</span>`;
    }
    
    // Actions (if not readonly)
    if (!this.readOnly) {
      html += `
        <div class="file-tree-actions">
          <button class="btn-file-action" data-action="rename" title="Rename" data-node-id="${node.id}">
            <span>✎</span>
          </button>
          <button class="btn-file-action" data-action="delete" title="Delete" data-node-id="${node.id}">
            <span>🗑</span>
          </button>
        </div>
      `;
    }
    
    html += `</div>`;
    return html;
  }

  /**
   * Recursively render tree nodes (deprecated - kept for reference)
   */
  renderNode(node, depth) {
    const indent = depth * 14;
    const nodeId = node.type === 'folder' ? `folder-${node.id}` : `file-${node.id}`;
    
    let html = `<div class="file-tree-item" data-id="${nodeId}" data-node-id="${node.id}" data-type="${node.type}" style="margin-left: ${indent}px;">`;
    
    // Icon + Name
    html += `<span class="file-tree-icon">${node.type === 'folder' ? '📁' : '📄'}</span>`;
    html += `<span class="file-tree-name">${escapeHtml(node.name)}</span>`;
    
    // File info
    if (node.type === 'file') {
      html += `<span class="file-tree-info">${node.size ? formatFileSize(node.size) : '0B'}</span>`;
    }
    
    // Actions (if not readonly)
    if (!this.readOnly && node.type === 'folder') {
      html += `
        <div class="file-tree-actions">
          <button class="btn-file-action" data-action="expand" title="Expand/Collapse" style="visibility: ${node.children && node.children.length > 0 ? 'visible' : 'hidden'};">
            <span>${node.expanded ? '▼' : '▶'}</span>
          </button>
          <button class="btn-file-action" data-action="new-file" title="New File" data-folder-id="${node.id}">
            <span>📄➕</span>
          </button>
          <button class="btn-file-action" data-action="new-folder" title="New Folder" data-folder-id="${node.id}">
            <span>📂➕</span>
          </button>
          <button class="btn-file-action" data-action="rename" title="Rename" data-node-id="${node.id}">
            <span>✎</span>
          </button>
          <button class="btn-file-action" data-action="delete" title="Delete" data-node-id="${node.id}">
            <span>🗑</span>
          </button>
        </div>
      `;
    } else if (!this.readOnly && node.type === 'file') {
      html += `
        <div class="file-tree-actions">
          <button class="btn-file-action" data-action="rename" title="Rename" data-node-id="${node.id}">
            <span>✎</span>
          </button>
          <button class="btn-file-action" data-action="delete" title="Delete" data-node-id="${node.id}">
            <span>🗑</span>
          </button>
        </div>
      `;
    }
    
    html += `</div>`;
    
    // Children
    if (node.type === 'folder' && node.children && node.children.length > 0) {
      const childrenDisplay = node.expanded !== false ? 'block' : 'none';
      html += `<div class="file-tree-children" style="display: ${childrenDisplay};">`;
      node.children.forEach(child => {
        html += this.renderNode(child, depth + 1);
      });
      html += `</div>`;
    }
    
    return html;
  }

  /**
   * Attach event listeners to tree
   */
  attachEventListeners() {
    if (!this.container) {
      console.error('[FileTreeManager] Cannot attach listeners: container is null');
      return;
    }
    
    // Prevent duplicate event listeners
    if (this.listenersAttached) {
      console.log('[FileTreeManager] Event listeners already attached, skipping');
      return;
    }
    this.listenersAttached = true;
    console.log('[FileTreeManager] Attaching event listeners to container:', this.container);

    // Single click on items
    this.container.addEventListener('click', (e) => {
      console.log('[FileTreeManager] Click detected:', e.target);
      const item = e.target.closest('.file-tree-item');
      const action = e.target.closest('[data-action]');
      const breadcrumb = e.target.closest('.breadcrumb-item');
      
      console.log('[FileTreeManager] Click handlers:', { item, action, breadcrumb });
      
      if (breadcrumb) {
        const folderId = breadcrumb.dataset.folderId;
        this.navigateToFolder(folderId === 'null' ? null : parseInt(folderId));
        return;
      }
      
      if (action) {
        console.log('[FileTreeManager] Action button clicked:', action.dataset.action);
        this.handleAction(action);
        return;
      }
      
      if (item) {
        // Don't try to select items that are being created inline
        if (item.classList.contains('inline-editing') || item.dataset.isNew === 'true') {
          return;
        }
        
        const type = item.dataset.type;
        const id = parseInt(item.dataset.nodeId);
        
        if (type === 'file') {
          this.selectFile(id, item);
        }
      }
    });

    // Double click to navigate into folders
    this.container.addEventListener('dblclick', (e) => {
      const item = e.target.closest('.file-tree-item');
      if (!item) return;
      
      const type = item.dataset.type;
      const id = parseInt(item.dataset.nodeId);
      
      if (type === 'folder') {
        this.navigateIntoFolder(id, item);
      }
    });

    // Context menu (right-click)
    this.container.addEventListener('contextmenu', (e) => {
      e.preventDefault();
      const item = e.target.closest('.file-tree-item');
      if (!item) return;
      
      this.showContextMenu(item, e.pageX, e.pageY);
    });
  }

  /**
   * Navigate into a folder (double-click)
   */
  navigateIntoFolder(folderId, itemEl) {
    const folderName = itemEl.querySelector('.file-tree-name').textContent;
    this.currentFolderId = folderId;
    // Replace path instead of pushing to avoid duplication
    this.folderPath = this.folderPath.filter(f => f.id !== folderId);
    this.folderPath.push({ id: folderId, name: folderName });
    this.render(this.currentProjectTree);
  }

  /**
   * Navigate to specific folder (breadcrumb)
   */
  navigateToFolder(folderId) {
    if (folderId === null) {
      // Root
      this.currentFolderId = null;
      this.folderPath = [];
    } else {
      // Find and navigate to folder in path
      const index = this.folderPath.findIndex(f => f.id === folderId);
      if (index !== -1) {
        this.currentFolderId = folderId;
        this.folderPath = this.folderPath.slice(0, index + 1);
      }
    }
    this.render(this.currentProjectTree);
  }

  /**
   * Handle button actions
   */
  async handleAction(btn) {
    const action = btn.dataset.action;
    const nodeId = btn.dataset.nodeId ? parseInt(btn.dataset.nodeId) : null;
    const folderId = btn.dataset.folderId ? parseInt(btn.dataset.folderId) : null;

    switch(action) {
      case 'new-file':
        await this.createNewFile(this.currentFolderId);
        break;
      case 'new-folder':
        await this.createNewFolder(this.currentFolderId);
        break;
      case 'upload':
        if (this.uploadInput) this.uploadInput.click();
        break;
      case 'download':
        await this.downloadSelectedFile();
        break;
      case 'rename':
        await this.renameNode(nodeId, btn.closest('.file-tree-item').dataset.type);
        break;
      case 'delete':
        await this.deleteNode(nodeId, btn.closest('.file-tree-item').dataset.type);
        break;
    }
  }

  /**
   * Select a file and load it
   */
  async selectFile(fileId, itemEl) {
    this.selectedFileId = fileId;
    
    // Update UI
    this.container.querySelectorAll('.file-tree-item.selected').forEach(el => {
      el.classList.remove('selected');
    });
    itemEl.classList.add('selected');
    
    // Load file content
    try {
      const response = await fetch(`/pythonIDE/api/projects/files-v2.php?action=read&project_id=${this.projectId}&file_id=${fileId}`);
      const result = await response.json();
      
      if (result.ok) {
        this.selectedFileName = result.name;
        this.onFileSelected(fileId, result.name, result.content);
      } else {
        console.error('[FileTreeManager] Error reading file:', result.error);
      }
    } catch (err) {
      console.error('[FileTreeManager] Error loading file:', err);
    }
  }

  /**
   * Create new file with inline editing
   */
  async createNewFile(parentFolderId = null) {
    console.log('[FileTreeManager] createNewFile called with:', { parentFolderId, projectId: this.projectId });
    // Create inline item for editing
    this.createInlineItem('file', parentFolderId, 'new_file.py');
  }

  /**
   * Create new folder with inline editing
   */
  async createNewFolder(parentFolderId = null) {
    console.log('[FileTreeManager] createNewFolder called with:', { parentFolderId, projectId: this.projectId });
    // Create inline item for editing
    this.createInlineItem('folder', parentFolderId, 'new_folder');
  }

  /**
   * Initialize hidden upload input
   */
  initUploadInput() {
    if (this.uploadInput || !this.container || this.readOnly) return;

    const input = document.createElement('input');
    input.type = 'file';
    input.style.display = 'none';
    input.accept = this.uploadExtensions.join(',');

    input.addEventListener('change', async () => {
      const file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;
      await this.uploadFile(file);
      input.value = '';
    });

    this.container.appendChild(input);
    this.uploadInput = input;
  }

  /**
   * Upload a file to the current folder
   */
  async uploadFile(file) {
    if (!this.projectId) {
      alert('Error: Project ID required');
      return;
    }

    const fileName = file.name || 'upload.txt';
    if (!fileName.match(/^[\w\-. ]+$/)) {
      alert('Invalid filename');
      return;
    }

    const ext = fileName.includes('.') ? '.' + fileName.split('.').pop().toLowerCase() : '';
    const allowed = this.uploadExtensions.map(e => e.toLowerCase());
    if (ext && !allowed.includes(ext)) {
      alert('Dateityp nicht erlaubt');
      return;
    }

    const parentFolderId = this.currentFolderId;
    const isImage = file.type && file.type.startsWith('image/');

    const readFile = () => new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(reader.error);
      if (isImage) {
        reader.readAsDataURL(file);
      } else {
        reader.readAsText(file);
      }
    });

    try {
      const content = await readFile();
      const payload = {
        project_id: this.projectId,
        folder_id: (parentFolderId === null || parentFolderId === undefined) ? null : parseInt(parentFolderId),
        name: fileName,
        content: content
      };

      const response = await fetch('/pythonIDE/api/projects/files-v2.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();
      if (result.ok) {
        await this.loadTree();
      } else {
        alert('Error: ' + result.error);
      }
    } catch (err) {
      console.error('[FileTreeManager] Error uploading file:', err);
      alert('Error uploading file');
    }
  }

  /**
   * Download the currently selected file
   */
  async downloadSelectedFile() {
    if (!this.selectedFileId) {
      alert('Bitte zuerst eine Datei auswaehlen');
      return;
    }

    try {
      const response = await fetch(`/pythonIDE/api/projects/files-v2.php?action=read&project_id=${this.projectId}&file_id=${this.selectedFileId}`);
      const result = await response.json();

      if (!result.ok) {
        alert('Error: ' + result.error);
        return;
      }

      const fileName = result.name || this.selectedFileName || 'download.txt';
      const content = result.content || '';
      const mimeType = result.mime_type || 'text/plain';

      let blob;
      if (typeof content === 'string' && content.startsWith('data:')) {
        const dataResponse = await fetch(content);
        blob = await dataResponse.blob();
      } else {
        blob = new Blob([content], { type: mimeType });
      }

      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = fileName;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch (err) {
      console.error('[FileTreeManager] Error downloading file:', err);
      alert('Error downloading file');
    }
  }

  /**
   * Rename node with inline editing
   */
  async renameNode(nodeId, type) {
    // Find the item element
    const item = this.container.querySelector(`[data-node-id="${nodeId}"][data-type="${type}"]`);
    if (!item) return;
    
    const nameSpan = item.querySelector('.file-tree-name');
    if (!nameSpan) return;
    
    const currentName = nameSpan.textContent;
    this.startInlineEdit(item, nodeId, type, currentName);
    return; // Early return - inline edit will handle the rename
  }

  /**
   * Legacy rename node (kept for compatibility)
   */
  async renameNodePrompt(nodeId, type) {
    const newName = prompt('New name:');
    if (!newName) return;

    if (!newName.match(/^[\w\-. ]+$/)) {
      alert('Invalid name');
      return;
    }

    try {
      const endpoint = type === 'folder' ? '/pythonIDE/api/projects/folders-v2.php' : '/pythonIDE/api/projects/files-v2.php';
      const response = await fetch(endpoint + '?action=rename', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_id: this.projectId,
          [type === 'folder' ? 'folder_id' : 'file_id']: nodeId,
          name: newName
        })
      });

      const result = await response.json();
      if (result.ok) {
        await this.loadTree();
      } else {
        alert('Error: ' + result.error);
      }
    } catch (err) {
      console.error('[FileTreeManager] Error renaming:', err);
      alert('Error renaming');
    }
  }

  /**
   * Delete node
   */
  async deleteNode(nodeId, type) {
    if (!confirm(`Delete this ${type}?`)) return;

    try {
      const endpoint = type === 'folder' ? '/pythonIDE/api/projects/folders-v2.php' : '/pythonIDE/api/projects/files-v2.php';
      const response = await fetch(endpoint + '?action=delete', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_id: this.projectId,
          [type === 'folder' ? 'folder_id' : 'file_id']: nodeId
        })
      });

      const result = await response.json();
      if (result.ok) {
        // Get file name from tree before deleting
        const fileName = type === 'file' && this.selectedFileId === nodeId ? this.selectedFileName : 'Unknown';
        
        if (this.selectedFileId === nodeId) {
          this.selectedFileId = null;
          this.selectedFileName = null;
        }
        await this.loadTree();
        this.onFileDeleted(nodeId, fileName);
      } else {
        alert('Error: ' + result.error);
      }
    } catch (err) {
      console.error('[FileTreeManager] Error deleting:', err);
      alert('Error deleting');
    }
  }

  /**
   * Save file content
   */
  async saveFile(fileId, content) {
    if (!fileId) return false;

    try {
      const response = await fetch('/pythonIDE/api/projects/files-v2.php?action=update', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_id: this.projectId,
          file_id: fileId,
          content: content
        })
      });

      const result = await response.json();
      if (result.ok) {
        this.onFileSaved(fileId, this.selectedFileName || 'Unknown');
        return true;
      } else {
        console.error('[FileTreeManager] Save error:', result.error);
        return false;
      }
    } catch (err) {
      console.error('[FileTreeManager] Network error on save:', err);
      return false;
    }
  }

  /**
   * Create inline item for immediate editing (Windows Explorer style)
   */
  createInlineItem(type, parentFolderId, defaultName) {
    console.log('[FileTreeManager] createInlineItem called:', { type, parentFolderId, defaultName, projectId: this.projectId });
    if (!this.projectId) {
      console.error('[FileTreeManager] Project ID is missing!');
      alert('Error: Project ID required');
      return;
    }
    
    // Get the content container
    const contentDiv = this.container.querySelector('.file-tree-content-items');
    if (!contentDiv) {
      console.error('[FileTreeManager] Content container not found');
      return;
    }
    
    // Create temporary item element
    const tempId = 'temp-' + Date.now();
    const icon = type === 'folder' ? '📁' : '📄';
    
    const itemDiv = document.createElement('div');
    itemDiv.className = 'file-tree-item inline-editing';
    itemDiv.dataset.id = tempId;
    itemDiv.dataset.nodeId = tempId;
    itemDiv.dataset.type = type;
    itemDiv.dataset.isNew = 'true';
    itemDiv.dataset.parentFolderId = parentFolderId || 'null';
    
    itemDiv.innerHTML = `
      <span class="file-tree-icon">${icon}</span>
      <input type="text" class="file-tree-name-input" value="${escapeHtml(defaultName)}" />
      <span class="file-tree-info" style="opacity:0.5">new</span>
    `;
    
    // Insert at the beginning
    contentDiv.insertBefore(itemDiv, contentDiv.firstChild);
    
    // Focus and select the input
    const input = itemDiv.querySelector('.file-tree-name-input');
    input.focus();
    input.select();
    
    // Handle Enter (save) and Escape (cancel)
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.finishInlineCreate(itemDiv, input.value, type, parentFolderId);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        this.cancelInlineEdit(itemDiv);
      }
    });
    
    // Handle blur (save)
    input.addEventListener('blur', () => {
      setTimeout(() => {
        if (itemDiv.parentElement) {
          this.finishInlineCreate(itemDiv, input.value, type, parentFolderId);
        }
      }, 150);
    });
  }
  
  /**
   * Start inline editing for existing item (rename)
   */
  startInlineEdit(itemElement, nodeId, type, currentName) {
    // Prevent multiple inline edits
    if (this.container.querySelector('.inline-editing')) return;
    
    const nameSpan = itemElement.querySelector('.file-tree-name');
    if (!nameSpan) return;
    
    // Replace span with input
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'file-tree-name-input';
    input.value = currentName;
    
    itemElement.classList.add('inline-editing');
    nameSpan.replaceWith(input);
    
    input.focus();
    input.select();
    
    // Handle Enter (save) and Escape (cancel)
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.finishInlineRename(itemElement, nodeId, type, input.value, currentName);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        this.cancelInlineEdit(itemElement, currentName);
      }
    });
    
    // Handle blur (save)
    input.addEventListener('blur', () => {
      setTimeout(() => {
        if (input.parentElement) {
          this.finishInlineRename(itemElement, nodeId, type, input.value, currentName);
        }
      }, 150);
    });
  }
  
  /**
   * Finish creating new item
   */
  async finishInlineCreate(itemElement, name, type, parentFolderId) {
    console.log('[FileTreeManager] finishInlineCreate called:', { name, type, parentFolderId, projectId: this.projectId });
    // Validate name
    name = name.trim();
    if (!name) {
      this.cancelInlineEdit(itemElement);
      return;
    }
    
    if (!name.match(/^[\w\-. ]+$/)) {
      alert('Invalid name. Use only letters, numbers, spaces, dots, hyphens and underscores.');
      const input = itemElement.querySelector('.file-tree-name-input');
      if (input) input.focus();
      return;
    }
    
    // Show loading state
    const input = itemElement.querySelector('.file-tree-name-input');
    if (input) input.disabled = true;
    
    try {
      if (type === 'file') {
        const payload = {
          project_id: this.projectId,
          folder_id: (parentFolderId === 'null' || parentFolderId === null || parentFolderId === undefined) ? null : parseInt(parentFolderId),
          name: name,
          content: '# New file\n'
        };
        console.log('[FileTreeManager] Creating file with payload:', payload);
        const response = await fetch('/pythonIDE/api/projects/files-v2.php?action=create', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        console.log('[FileTreeManager] File creation response:', result);
        if (result.ok) {
          await this.loadTree();
        } else {
          console.error('[FileTreeManager] File creation failed:', result.error);
          alert('Error: ' + result.error);
          this.cancelInlineEdit(itemElement);
        }
      } else {
        const payload = {
          project_id: this.projectId,
          parent_folder_id: (parentFolderId === 'null' || parentFolderId === null || parentFolderId === undefined) ? null : parseInt(parentFolderId),
          name: name
        };
        console.log('[FileTreeManager] Creating folder with payload:', payload);
        const response = await fetch('/pythonIDE/api/projects/folders-v2.php?action=create', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        
        console.log('[FileTreeManager] Folder creation raw response:', response);
        const responseText = await response.text();
        console.log('[FileTreeManager] Folder creation response text:', responseText);
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          console.error('[FileTreeManager] Failed to parse JSON:', e);
          alert('Server error: ' + responseText.substring(0, 200));
          this.cancelInlineEdit(itemElement);
          return;
        }
        
        console.log('[FileTreeManager] Folder creation response:', result);
        if (result.ok) {
          await this.loadTree();
        } else {
          console.error('[FileTreeManager] Folder creation failed:', result.error);
          alert('Error: ' + result.error);
          this.cancelInlineEdit(itemElement);
        }
      }
    } catch (err) {
      console.error('[FileTreeManager] Error creating item:', err);
      alert('Error creating ' + type);
      this.cancelInlineEdit(itemElement);
    }
  }
  
  /**
   * Finish renaming existing item
   */
  async finishInlineRename(itemElement, nodeId, type, newName, oldName) {
    newName = newName.trim();
    
    // If name unchanged, just cancel
    if (newName === oldName) {
      this.cancelInlineEdit(itemElement, oldName);
      return;
    }
    
    if (!newName) {
      this.cancelInlineEdit(itemElement, oldName);
      return;
    }
    
    if (!newName.match(/^[\w\-. ]+$/)) {
      alert('Invalid name. Use only letters, numbers, spaces, dots, hyphens and underscores.');
      const input = itemElement.querySelector('.file-tree-name-input');
      if (input) {
        input.value = oldName;
        input.focus();
      }
      return;
    }
    
    try {
      const endpoint = type === 'folder' ? '/pythonIDE/api/projects/folders-v2.php' : '/pythonIDE/api/projects/files-v2.php';
      const response = await fetch(endpoint + '?action=rename', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_id: this.projectId,
          [type === 'folder' ? 'folder_id' : 'file_id']: nodeId,
          name: newName
        })
      });
      
      const result = await response.json();
      if (result.ok) {
        await this.loadTree();
      } else {
        alert('Error: ' + result.error);
        this.cancelInlineEdit(itemElement, oldName);
      }
    } catch (err) {
      console.error('[FileTreeManager] Error renaming:', err);
      alert('Error renaming ' + type);
      this.cancelInlineEdit(itemElement, oldName);
    }
  }
  
  /**
   * Cancel inline editing
   */
  cancelInlineEdit(itemElement, originalName = null) {
    if (!itemElement) return;
    
    // If this is a new item, just remove it
    if (itemElement.dataset.isNew === 'true') {
      itemElement.remove();
      return;
    }
    
    // If renaming existing item, restore original name
    const input = itemElement.querySelector('.file-tree-name-input');
    if (input && originalName) {
      const nameSpan = document.createElement('span');
      nameSpan.className = 'file-tree-name';
      nameSpan.textContent = originalName;
      input.replaceWith(nameSpan);
    }
    
    itemElement.classList.remove('inline-editing');
  }

  /**
   * Show context menu (placeholder for now)
   */
  showContextMenu(item, x, y) {
    // TODO: Implement custom context menu
    console.log('[FileTreeManager] Context menu at', x, y);
  }

  /**
   * Show error message
   */
  showError(message) {
    if (!this.container) return;
    this.container.innerHTML = `<div class="error-message">${escapeHtml(message)}</div>`;
  }
}

/**
 * Utility functions
 */
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + 'B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB';
  return (bytes / 1024 / 1024).toFixed(1) + 'MB';
}

// Make FileTreeManager available globally
window.FileTreeManager = FileTreeManager;

} // End of guard clause
