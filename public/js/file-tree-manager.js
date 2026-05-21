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
    this.beforeFileSelect = options.beforeFileSelect || (async () => true);
    this.onFileDeleted = options.onFileDeleted || (() => {});
    this.onFileSaved = options.onFileSaved || (() => {});
    this.onFolderChanged = options.onFolderChanged || (() => {});
    this.doubleClickAction = options.doubleClickAction || 'open-folder';
    
    this.uploadExtensions = options.uploadExtensions || ['.py', '.txt', '.md', '.json', '.html', '.htm', '.jpg', '.jpeg', '.webp', '.gif', '.zip'];
    this.uploadInput = null;
    
    this.currentProjectTree = null;
    this.selectedFileId = null;
    this.selectedFileName = null;
    this.currentFolderId = null; // null = root
    this.folderPath = []; // Breadcrumb path
    this.listenersAttached = false; // Prevent duplicate listeners

    this._onClick = null;
    this._onDblClick = null;
    this._onContextMenu = null;
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
    // Remove old listeners explicitly before re-attaching (innerHTML only replaces children,
    // NOT event listeners on the container element itself – so without this, every render()
    // call accumulates another listener, causing N clicks per user interaction after N renders).
    if (this._onClick) {
      this.container.removeEventListener('click', this._onClick);
      this._onClick = null;
    }
    if (this._onDblClick) {
      this.container.removeEventListener('dblclick', this._onDblClick);
      this._onDblClick = null;
    }
    if (this._onContextMenu) {
      this.container.removeEventListener('contextmenu', this._onContextMenu);
      this._onContextMenu = null;
    }
    this.listenersAttached = false;
    this.attachEventListeners();
    
    // Restore selection after re-render
    if (this.selectedFileId) {
      this.markFileAsSelected(this.selectedFileId);
    }
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
      breadcrumb += `<button class="breadcrumb-action" data-action="upload" title="Datei/ZIP hochladen">⬆</button>`;
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

    this._onClick = (e) => {
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

        if (type === 'folder') {
          this.navigateIntoFolder(id, item);
          return;
        }

        if (type === 'file') {
          this.selectFile(id, item);
        }
      }
    };

    this._onDblClick = (e) => {
      const item = e.target.closest('.file-tree-item');
      if (!item) return;

      const type = item.dataset.type;
      const id = parseInt(item.dataset.nodeId);

      if (this.doubleClickAction === 'rename') {
        this.renameNode(id, type);
        return;
      }

      if (type === 'folder') {
        this.navigateIntoFolder(id, item);
      }
    };

    this._onContextMenu = (e) => {
      e.preventDefault();
      const item = e.target.closest('.file-tree-item');
      if (!item) return;

      this.showContextMenu(item, e.pageX, e.pageY);
    };

    // Single click on items
    this.container.addEventListener('click', this._onClick);
    // Double click to navigate into folders
    this.container.addEventListener('dblclick', this._onDblClick);
    // Context menu (right-click)
    this.container.addEventListener('contextmenu', this._onContextMenu);
  }

  destroy() {
    if (!this.container) return;

    if (this._onClick) {
      this.container.removeEventListener('click', this._onClick);
      this._onClick = null;
    }
    if (this._onDblClick) {
      this.container.removeEventListener('dblclick', this._onDblClick);
      this._onDblClick = null;
    }
    if (this._onContextMenu) {
      this.container.removeEventListener('contextmenu', this._onContextMenu);
      this._onContextMenu = null;
    }

    this.listenersAttached = false;
    this.uploadInput = null;
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
    this.onFolderChanged(this.currentFolderId, [...this.folderPath]);
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
    this.onFolderChanged(this.currentFolderId, [...this.folderPath]);
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
   * Mark a file as selected in the UI without loading it
   */
  markFileAsSelected(fileId) {
    const normalizedFileId = Number(fileId);
    if (!Number.isFinite(normalizedFileId)) {
      return;
    }

    this.selectedFileId = normalizedFileId;

    // FileTreeManager file DOM uses data-node-id for real file IDs.
    const itemEl = this.container.querySelector(`[data-node-id="${normalizedFileId}"][data-type="file"]`);
    if (!itemEl) {
      return;
    }

    // Update UI only when we can actually mark a real item.
    this.container.querySelectorAll('.file-tree-item.selected').forEach(el => {
      el.classList.remove('selected');
    });
    itemEl.classList.add('selected');
  }

  /**
   * Select a file and load it
   */
  async selectFile(fileId, itemEl) {
    try {
      const shouldContinue = await this.beforeFileSelect(fileId, itemEl);
      if (!shouldContinue) {
        return;
      }
    } catch (guardErr) {
      console.error('[FileTreeManager] beforeFileSelect failed:', guardErr);
      return;
    }

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

    if (ext === '.zip') {
      await this.uploadZipArchive(file);
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

  normalizeZipPath(path) {
    return String(path || '')
      .replace(/\\/g, '/')
      .replace(/^\/+|\/+$/g, '')
      .replace(/\/+/g, '/');
  }

  getMimeTypeByFileName(fileName) {
    const ext = String(fileName || '').toLowerCase().split('.').pop() || '';
    const map = {
      py: 'text/x-python',
      txt: 'text/plain',
      md: 'text/markdown',
      json: 'application/json',
      html: 'text/html',
      htm: 'text/html',
      css: 'text/css',
      js: 'text/javascript',
      png: 'image/png',
      jpg: 'image/jpeg',
      jpeg: 'image/jpeg',
      gif: 'image/gif',
      webp: 'image/webp',
      svg: 'image/svg+xml'
    };
    return map[ext] || 'text/plain';
  }

  isImageFileName(fileName) {
    const ext = String(fileName || '').toLowerCase().split('.').pop() || '';
    return ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext);
  }

  isTextFileName(fileName) {
    const ext = String(fileName || '').toLowerCase().split('.').pop() || '';
    return ['py', 'txt', 'md', 'json', 'html', 'htm', 'css', 'js', 'csv', 'xml', 'yml', 'yaml', 'ini', 'cfg'].includes(ext);
  }

  findFolderById(node, folderId) {
    if (!node) return null;
    if (node.type === 'folder' && Number(node.id) === Number(folderId)) return node;
    if (!Array.isArray(node.children)) return null;
    for (const child of node.children) {
      const found = this.findFolderById(child, folderId);
      if (found) return found;
    }
    return null;
  }

  async ensureFolderInParent(folderName, parentFolderId) {
    const normalizedName = String(folderName || '').trim();
    if (!normalizedName.match(/^[\w\-. ]+$/)) {
      throw new Error('Invalid folder name in ZIP: ' + normalizedName);
    }

    const createRes = await fetch('/pythonIDE/api/projects/folders-v2.php?action=create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        project_id: this.projectId,
        parent_folder_id: parentFolderId,
        name: normalizedName
      })
    });

    const createData = await createRes.json();
    if (createData.ok && createData.folder_id) {
      return Number(createData.folder_id);
    }

    // Folder might already exist.
    if (createRes.status === 409 || /already exists/i.test(String(createData.error || ''))) {
      await this.loadTree();
      const parentNode = parentFolderId === null
        ? this.currentProjectTree
        : this.findFolderById(this.currentProjectTree, parentFolderId);
      const children = Array.isArray(parentNode?.children) ? parentNode.children : [];
      const existing = children.find((c) => c?.type === 'folder' && String(c?.name || '') === normalizedName);
      if (existing?.id) {
        return Number(existing.id);
      }
    }

    throw new Error(createData?.error || ('Could not create folder: ' + normalizedName));
  }

  async ensureFolderPath(folderSegments, baseFolderId) {
    let parentId = (baseFolderId === null || baseFolderId === undefined) ? null : Number(baseFolderId);
    for (const segment of folderSegments) {
      parentId = await this.ensureFolderInParent(segment, parentId);
    }
    return parentId;
  }

  splitFileName(fileName) {
    const name = String(fileName || '');
    const idx = name.lastIndexOf('.');
    if (idx <= 0) {
      return { base: name, ext: '' };
    }
    return {
      base: name.slice(0, idx),
      ext: name.slice(idx)
    };
  }

  buildConflictFileName(base, ext, index) {
    const suffix = index === 1 ? '=1' : `=${String(index).padStart(2, '0')}`;
    const maxLen = 255;
    const allowedBaseLen = Math.max(1, maxLen - ext.length - suffix.length);
    const clippedBase = String(base || 'datei').slice(0, allowedBaseLen);
    return `${clippedBase}${suffix}${ext}`;
  }

  isDuplicateNameCreateError(status, errorMessage) {
    if (Number(status) === 409) return true;
    const msg = String(errorMessage || '').toLowerCase();
    return msg.includes('bereits vorhanden')
      || msg.includes('already exists')
      || msg.includes('duplicate')
      || msg.includes('exists');
  }

  async createFileWithAutoRename(payload) {
    const originalName = String(payload?.name || 'datei.txt');
    const { base, ext } = this.splitFileName(originalName);
    const basePayload = {
      project_id: payload.project_id,
      folder_id: payload.folder_id,
      content: payload.content
    };

    for (let i = 0; i < 1000; i++) {
      const candidateName = i === 0
        ? originalName
        : this.buildConflictFileName(base || 'datei', ext, i);

      const response = await fetch('/pythonIDE/api/projects/files-v2.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...basePayload,
          name: candidateName
        })
      });

      let result = null;
      try {
        result = await response.json();
      } catch (_err) {
        result = { ok: false, error: 'Ungültige API-Antwort' };
      }

      if (result?.ok) {
        return {
          ok: true,
          name: candidateName,
          renamed: candidateName !== originalName
        };
      }

      if (!this.isDuplicateNameCreateError(response.status, result?.error)) {
        return {
          ok: false,
          error: result?.error || 'Import fehlgeschlagen'
        };
      }
    }

    return {
      ok: false,
      error: `Konnte keinen freien Dateinamen für ${originalName} finden`
    };
  }

  async uploadZipArchive(file) {
    if (typeof JSZip === 'undefined') {
      await this.uploadZipArchiveServer(file);
      return;
    }

    try {
      const zip = await JSZip.loadAsync(file);
      const baseFolderId = (this.currentFolderId === null || this.currentFolderId === undefined)
        ? null
        : Number(this.currentFolderId);

      const entries = Object.values(zip.files)
        .filter((entry) => !entry.dir)
        .map((entry) => ({
          entryName: String(entry.name || ''),
          normalizedPath: this.normalizeZipPath(entry.name)
        }))
        .filter((item) => item.normalizedPath !== '' && !item.normalizedPath.startsWith('__MACOSX/'));

      if (entries.length === 0) {
        alert('ZIP enthält keine importierbaren Dateien.');
        return;
      }

      let importedCount = 0;
      let skippedCount = 0;
      let renamedCount = 0;
      const renamedFiles = [];
      const failed = [];

      for (const item of entries) {
        const rawPath = item.normalizedPath;
        const pathParts = rawPath.split('/').filter(Boolean);
        if (pathParts.length === 0) continue;

        if (pathParts.some((part) => part === '.' || part === '..' || !part.match(/^[\w\-. ]+$/))) {
          failed.push(`${rawPath}: Ungültiger Pfad`);
          continue;
        }

        const fileName = pathParts[pathParts.length - 1];
        if (!fileName.match(/^[\w\-. ]+$/)) {
          failed.push(`${rawPath}: Ungültiger Dateiname`);
          continue;
        }

        const folderSegments = pathParts.slice(0, -1);

        const isImage = this.isImageFileName(fileName);
        const isText = this.isTextFileName(fileName);

        try {
          const targetFolderId = await this.ensureFolderPath(folderSegments, baseFolderId);
          const zipEntry = zip.files[item.entryName];
          if (!zipEntry) {
            failed.push(`${rawPath}: ZIP-Eintrag nicht gefunden`);
            continue;
          }

          let content = '';
          if (isImage) {
            const base64 = await zipEntry.async('base64');
            const mime = this.getMimeTypeByFileName(fileName);
            content = `data:${mime};base64,${base64}`;
          } else if (isText) {
            content = await zipEntry.async('text');
          } else {
            const base64 = await zipEntry.async('base64');
            content = `data:application/octet-stream;base64,${base64}`;
          }

          const payload = {
            project_id: this.projectId,
            folder_id: targetFolderId,
            name: fileName,
            content
          };

          const created = await this.createFileWithAutoRename(payload);
          if (created.ok) {
            importedCount += 1;
            if (created.renamed) {
              renamedCount += 1;
              renamedFiles.push(`${rawPath} -> ${created.name}`);
            }
          } else {
            failed.push(`${rawPath}: ${created.error || 'Import fehlgeschlagen'}`);
          }
        } catch (entryErr) {
          failed.push(`${rawPath}: ${entryErr.message || entryErr}`);
        }
      }

      await this.loadTree();

      const summary = [
        `ZIP-Import abgeschlossen.`,
        `Importiert: ${importedCount}`,
        `Umbenannt: ${renamedCount}`,
        `Übersprungen: ${skippedCount}`,
        `Fehler: ${failed.length}`
      ];
      if (renamedFiles.length > 0) {
        summary.push('', 'Umbenannte Dateien (max 10):', ...renamedFiles.slice(0, 10));
      }
      if (failed.length > 0) {
        summary.push('', 'Details (max 10):', ...failed.slice(0, 10));
      }
      alert(summary.join('\n'));
    } catch (err) {
      console.error('[FileTreeManager] ZIP upload failed:', err);
      alert('ZIP-Import fehlgeschlagen: ' + (err?.message || err));
    }
  }

  async uploadZipArchiveServer(file) {
    try {
      const formData = new FormData();
      formData.append('project_id', String(this.projectId));
      formData.append('folder_id', this.currentFolderId === null || this.currentFolderId === undefined ? '' : String(this.currentFolderId));
      formData.append('zip_file', file, file.name || 'upload.zip');

      const response = await fetch('/pythonIDE/api/projects/files-v2.php?action=import_zip', {
        method: 'POST',
        body: formData
      });

      let result = null;
      try {
        result = await response.json();
      } catch (_err) {
        result = { ok: false, error: 'Ungültige API-Antwort' };
      }

      if (!response.ok || !result?.ok) {
        throw new Error(result?.error || 'ZIP-Import fehlgeschlagen');
      }

      await this.loadTree();

      const summary = [
        'ZIP-Import abgeschlossen.',
        `Importiert: ${Number(result.imported || 0)}`,
        `Umbenannt: ${Number(result.renamed || 0)}`,
        `Übersprungen: ${Number(result.skipped || 0)}`,
        `Fehler: ${Array.isArray(result.failed) ? result.failed.length : 0}`
      ];

      if (Array.isArray(result.renamed_files) && result.renamed_files.length > 0) {
        summary.push('', 'Umbenannte Dateien (max 10):', ...result.renamed_files.slice(0, 10));
      }

      if (Array.isArray(result.failed) && result.failed.length > 0) {
        summary.push('', 'Details (max 10):', ...result.failed.slice(0, 10));
      }

      alert(summary.join('\n'));
    } catch (err) {
      console.error('[FileTreeManager] ZIP upload (server) failed:', err);
      alert('ZIP-Import fehlgeschlagen: ' + (err?.message || err));
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
    if (!itemElement || itemElement.dataset.submitting === 'true') return;
    itemElement.dataset.submitting = 'true';

    console.log('[FileTreeManager] finishInlineCreate called:', { name, type, parentFolderId, projectId: this.projectId });
    // Validate name
    name = name.trim();
    if (!name) {
      this.cancelInlineEdit(itemElement);
      return;
    }
    
    if (!name.match(/^[\w\-. ]+$/)) {
      itemElement.dataset.submitting = 'false';
      alert('Invalid name. Use only letters, numbers, spaces, dots, hyphens and underscores.');
      const input = itemElement.querySelector('.file-tree-name-input');
      if (input) {
        input.disabled = false;
        input.focus();
      }
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
          itemElement.dataset.submitting = 'false';
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
          itemElement.dataset.submitting = 'false';
          console.error('[FileTreeManager] Failed to parse JSON:', e);
          alert('Server error: ' + responseText.substring(0, 200));
          this.cancelInlineEdit(itemElement);
          return;
        }
        
        console.log('[FileTreeManager] Folder creation response:', result);
        if (result.ok) {
          await this.loadTree();
        } else {
          itemElement.dataset.submitting = 'false';
          console.error('[FileTreeManager] Folder creation failed:', result.error);
          alert('Error: ' + result.error);
          this.cancelInlineEdit(itemElement);
        }
      }
    } catch (err) {
      itemElement.dataset.submitting = 'false';
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
