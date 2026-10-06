/**
 * Projects Editor - Manages project loading, creation, deletion, and UI updates
 * Based on assignment_editor.php structure but adapted for projects
 */

import { DbModelSchema } from './db-model-schema.js';

let currentProject = null;
let projects = [];
let projectFileManager = null;
let projectNavBound = false;
let currentOpenFileId = null;
let currentOpenFileName = '';
let currentOpenFileSnapshot = '';
let projectDraftFiles = {};
let projectSavedSnapshots = {};
let projectFileNamesById = {};
let projectEditorDraftListenerBound = false;
let projectSkipNextDraftCache = false;
let unsavedChoiceResolver = null;
let lastOpenedProjectIdFromDb = null;
let projectFileTreeDirty = false; // Flag to force tree reload after file changes
let pyodideRuntimeFolderPath = null; // Track which folder's code is currently loaded in Pyodide VFS
let pyodideRuntimeModulesDirty = false; // Flag: folder changed, need to invalidate sys.modules
let projectsEditorInitPromise = null;
let projectsEditorInitialized = false;
let loadProjectPromise = null;
let loadProjectIdInFlight = null;
let dbDesignerState = null;
let dbTreeContextMenuState = null;

async function waitForEditorInstance() {
  for (let attempt = 0; attempt < 200; attempt++) {
    if (window.editor) return window.editor;
    if (window.editorInstance) return window.editorInstance;
    if (window.monaco?.editor?.getEditors) {
      const editors = window.monaco.editor.getEditors();
      if (editors.length > 0) return editors[0];
    }
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  return null;
}

function setEditorContent(editor, code) {
  if (!editor || typeof editor.setValue !== 'function') return;
  editor.setValue(code || '');
  if (typeof editor.clearSelection === 'function') {
    editor.clearSelection();
  } else if (typeof editor.setSelection === 'function' && window.monaco?.Selection) {
    editor.setSelection(new window.monaco.Selection(1, 1, 1, 1));
  }
}

function findFileIdByName(nodes, fileName) {
  if (!Array.isArray(nodes)) return null;
  for (const node of nodes) {
    if (node?.type === 'file' && node?.name === fileName) {
      return node.id;
    }
    const childMatch = findFileIdByName(node?.children, fileName);
    if (childMatch) return childMatch;
  }
  return null;
}

function isPythonFile(fileName) {
  return typeof fileName === 'string' && fileName.toLowerCase().endsWith('.py');
}

function isProjectGuiAssetFile(fileName) {
  const lower = String(fileName || '').toLowerCase();
  return lower === 'index.html' || lower === 'style.css' || lower.endsWith('/index.html') || lower.endsWith('/style.css');
}

function normalizeProjectDirectory(relativePath = '') {
  const normalized = String(relativePath || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
  if (!normalized.includes('/')) {
    return '';
  }
  return normalized.slice(0, normalized.lastIndexOf('/'));
}

async function resolveProjectDirectory(fileId, fallbackFileName = '') {
  const relativePath = await resolveProjectRelativePath(fileId, fallbackFileName);
  return normalizeProjectDirectory(relativePath);
}

function clearProjectOutputPanels() {
  const outputEl = document.getElementById('output-container');
  const plotEl = document.getElementById('plot-container');
  if (outputEl) {
    outputEl.textContent = '';
  }
  if (plotEl) {
    plotEl.innerHTML = '';
  }
}

function getActiveProjectFolderPath() {
  if (!projectFileManager || !Array.isArray(projectFileManager.folderPath)) {
    return '';
  }
  return projectFileManager.folderPath
    .map((segment) => String(segment?.name || '').trim())
    .filter(Boolean)
    .join('/');
}

function setProjectGuiPlaceholder(folderPath = '') {
  const guiContainer = document.getElementById('gui-container');
  if (!guiContainer) return;

  guiContainer.innerHTML = '<p style="color: #888; padding: 20px; text-align: center;">Drücke "Run", um die GUI anzuzeigen</p>';
  guiContainer.dataset.projectHtmlRendered = '0';
  guiContainer.dataset.projectHtmlDirty = '0';
  guiContainer.dataset.projectHtmlActiveFolder = String(folderPath || '');
}

function markProjectGuiDirty(folderPath = '') {
  const guiContainer = document.getElementById('gui-container');
  if (!guiContainer) return;

  if (folderPath) {
    guiContainer.dataset.projectHtmlActiveFolder = String(folderPath);
  }
  guiContainer.dataset.projectHtmlDirty = '1';
}

async function markProjectGuiDirtyForFile(fileId, fileName) {
  if (!currentProject || !isProjectGuiMode(currentProject) || !isProjectGuiAssetFile(fileName)) {
    return;
  }

  const fileDir = await resolveProjectDirectory(fileId, fileName);
  const guiContainer = document.getElementById('gui-container');
  const activeFolder = String(guiContainer?.dataset?.projectHtmlActiveFolder || '');
  const currentDir = await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');

  if (!activeFolder || activeFolder === fileDir || currentDir === fileDir) {
    markProjectGuiDirty(fileDir);
  }
}

function getEditorInstance() {
  return window.editor || window.editorInstance || null;
}

function isCurrentFileDirty() {
  const editor = getEditorInstance();
  if (!editor || !currentOpenFileId) return false;
  return String(editor.getValue() || '') !== String(currentOpenFileSnapshot || '');
}

function setProjectDraftContent(fileId, fileName, content) {
  const normalizedId = Number(fileId || 0);
  if (!normalizedId) return;
  projectDraftFiles[normalizedId] = String(content ?? '');
  if (fileName) {
    projectFileNamesById[normalizedId] = String(fileName);
  }
}

function setProjectSavedSnapshot(fileId, fileName, content) {
  const normalizedId = Number(fileId || 0);
  if (!normalizedId) return;
  projectSavedSnapshots[normalizedId] = String(content ?? '');
  if (fileName) {
    projectFileNamesById[normalizedId] = String(fileName);
  }
}

function getProjectDraftContent(fileId) {
  const normalizedId = Number(fileId || 0);
  if (!normalizedId) return null;
  return Object.prototype.hasOwnProperty.call(projectDraftFiles, normalizedId)
    ? projectDraftFiles[normalizedId]
    : null;
}

function isProjectFileDirty(fileId) {
  const normalizedId = Number(fileId || 0);
  if (!normalizedId) return false;
  const draft = Object.prototype.hasOwnProperty.call(projectDraftFiles, normalizedId)
    ? projectDraftFiles[normalizedId]
    : null;
  const snapshot = Object.prototype.hasOwnProperty.call(projectSavedSnapshots, normalizedId)
    ? projectSavedSnapshots[normalizedId]
    : '';
  if (draft === null) return false;
  return String(draft) !== String(snapshot);
}

function hasUnsavedProjectDrafts() {
  return Object.keys(projectDraftFiles).some((id) => isProjectFileDirty(Number(id)));
}

function cacheCurrentProjectEditorDraft() {
  if (projectSkipNextDraftCache) {
    projectSkipNextDraftCache = false;
    return;
  }
  const editor = getEditorInstance();
  if (!editor || !currentOpenFileId) return;
  setProjectDraftContent(currentOpenFileId, currentOpenFileName, editor.getValue());
  applyProjectFileDirtyMarker(currentOpenFileId);
  void markProjectGuiDirtyForFile(currentOpenFileId, currentOpenFileName);
}

function applyProjectFileDirtyMarker(fileId) {
  const normalizedId = Number(fileId || 0);
  if (!normalizedId) return;
  const node = document.querySelector(`#project-file-tree .file-tree-item[data-node-id="${normalizedId}"] .file-tree-name`);
  if (!node) return;

  const baseName = projectFileNamesById[normalizedId] || String(node.textContent || '').replace(/\s\*$/, '');
  projectFileNamesById[normalizedId] = baseName;
  node.textContent = isProjectFileDirty(normalizedId) ? `${baseName} *` : baseName;
}

function refreshAllProjectDirtyMarkers() {
  const nameNodes = document.querySelectorAll('#project-file-tree .file-tree-item[data-node-id] .file-tree-name');
  nameNodes.forEach((node) => {
    const item = node.closest('.file-tree-item');
    const fileId = Number(item?.getAttribute('data-node-id') || 0);
    const itemType = String(item?.getAttribute('data-type') || '');
    if (!fileId || itemType !== 'file') return;
    const baseName = projectFileNamesById[fileId] || String(node.textContent || '').replace(/\s\*$/, '');
    projectFileNamesById[fileId] = baseName;
    node.textContent = isProjectFileDirty(fileId) ? `${baseName} *` : baseName;
  });
}

async function readProjectFileById(projectId, fileId) {
  const contentResponse = await fetch(`../api/projects/files-v2.php?action=read&project_id=${projectId}&file_id=${fileId}`, {
    credentials: 'include',
    cache: 'no-store'
  });
  if (!contentResponse.ok) return null;
  const contentData = await contentResponse.json();
  if (!contentData?.ok) return null;
  return {
    fileId,
    fileName: contentData.name || '',
    content: contentData.content || ''
  };
}

async function readProjectFileByName(projectId, fileName) {
  const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${projectId}`, {
    credentials: 'include',
    cache: 'reload'
  });
  if (!treeResponse.ok) return null;

  const treeData = await treeResponse.json();
  const treeNodes = Array.isArray(treeData?.tree)
    ? treeData.tree
    : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);
  const fileId = findFileIdByName(treeNodes, fileName);
  if (!fileId) return null;
  return readProjectFileById(projectId, fileId);
}

function findProjectFileIdByPath(nodes, targetPath, parentPath = '') {
  if (!Array.isArray(nodes) || !targetPath) return null;

  for (const node of nodes) {
    if (!node || typeof node.name !== 'string') continue;

    const currentPath = parentPath ? `${parentPath}/${node.name}` : node.name;
    if (node.type === 'file' && currentPath === targetPath) {
      return Number(node.id || 0) || null;
    }

    if (node.type === 'folder') {
      const childId = findProjectFileIdByPath(node.children || [], targetPath, currentPath);
      if (childId) return childId;
    }
  }

  return null;
}

async function readProjectFileByPreferredPath(projectId, fileName, preferredFolderPath = '') {
  const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${projectId}`, {
    credentials: 'include',
    cache: 'reload'
  });
  if (!treeResponse.ok) return null;

  const treeData = await treeResponse.json();
  const treeNodes = Array.isArray(treeData?.tree)
    ? treeData.tree
    : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

  const normalizedFolder = String(preferredFolderPath || '').replace(/^\/+|\/+$/g, '');
  const preferredPath = normalizedFolder ? `${normalizedFolder}/${fileName}` : String(fileName || '');
  const preferredId = findProjectFileIdByPath(treeNodes, preferredPath, '');
  if (preferredId) {
    return readProjectFileById(projectId, preferredId);
  }

  const fallbackId = findFileIdByName(treeNodes, fileName);
  if (!fallbackId) return null;
  return readProjectFileById(projectId, fallbackId);
}

function findProjectFilePathById(nodes, targetId, parentPath = '') {
  if (!Array.isArray(nodes)) return null;

  const normalizedTargetId = Number(targetId || 0);
  if (!normalizedTargetId) return null;

  for (const node of nodes) {
    if (!node || typeof node.name !== 'string') continue;

    const currentPath = parentPath ? `${parentPath}/${node.name}` : node.name;
    if (node.type === 'file' && Number(node.id || 0) === normalizedTargetId) {
      return currentPath;
    }

    if (node.type === 'folder') {
      const childPath = findProjectFilePathById(node.children || [], normalizedTargetId, currentPath);
      if (childPath) return childPath;
    }
  }

  return null;
}

async function resolveProjectRelativePath(fileId, fallbackFileName = '') {
  if (!currentProject?.id || !fileId) {
    return String(fallbackFileName || '').replace(/^\/+/, '');
  }

  try {
    const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${currentProject.id}`, {
      credentials: 'include',
      cache: 'no-store'
    });

    if (!treeResponse.ok) {
      return String(fallbackFileName || '').replace(/^\/+/, '');
    }

    const treeData = await treeResponse.json();
    const treeNodes = Array.isArray(treeData?.tree)
      ? treeData.tree
      : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

    const foundPath = findProjectFilePathById(treeNodes, fileId, '');
    if (foundPath) {
      return String(foundPath || '').replace(/^\/+/, '');
    }
  } catch (err) {
    console.warn('[projects-editor] resolveProjectRelativePath failed:', err);
  }

  return String(fallbackFileName || '').replace(/^\/+/, '');
}

async function syncProjectFileToPyodideRuntime(fileId, fileName, content) {
  if (!window.pyodide || !currentProject?.id) {
    return;
  }

  const relativePath = await resolveProjectRelativePath(fileId, fileName);
  if (!relativePath) {
    return;
  }

  const normalizedPath = String(relativePath).replace(/\\/g, '/').replace(/^\/+/, '');
  const isPython = normalizedPath.toLowerCase().endsWith('.py');
  const runtimeSyncPayload = {
    root: '/project',
    relPath: normalizedPath,
    content: String(content ?? ''),
    invalidateModules: isPython,
  };

  await window.pyodide.runPythonAsync(`
import json
import os
import sys
import importlib

payload = json.loads(${JSON.stringify(JSON.stringify(runtimeSyncPayload))})
runtime_root = str(payload.get('root') or '/project')
rel_path = str(payload.get('relPath') or '').replace('\\\\', '/').strip('/')

if rel_path:
    abs_path = runtime_root.rstrip('/') + '/' + rel_path
    parent_dir = os.path.dirname(abs_path)
    if parent_dir:
        os.makedirs(parent_dir, exist_ok=True)

    with open(abs_path, 'w', encoding='utf-8') as fh:
        fh.write(str(payload.get('content') or ''))

    if runtime_root not in sys.path:
        sys.path.insert(0, runtime_root)

    if bool(payload.get('invalidateModules')):
        abs_root = os.path.abspath(runtime_root)
        prefix = abs_root + os.sep
        for mod_name, mod in list(sys.modules.items()):
            mod_file = getattr(mod, '__file__', None)
            if not mod_file:
                continue
            try:
                mod_abs = os.path.abspath(str(mod_file))
            except Exception:
                continue
            if mod_abs == abs_path or mod_abs.startswith(prefix):
                sys.modules.pop(mod_name, None)
        importlib.invalidate_caches()
`);

  if (isPython) {
    delete window.__codeUiGlobals;
  }
}

async function ensureInitPyExists(projectId, projectName, fallbackCode = '') {
  const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${projectId}`, {
    credentials: 'include',
    cache: 'no-store'
  });
  if (!treeResponse.ok) return;

  const treeData = await treeResponse.json();
  const treeNodes = Array.isArray(treeData?.tree)
    ? treeData.tree
    : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

  const initId = findFileIdByName(treeNodes, 'init.py');
  if (initId) return;

  const safeName = (projectName || 'Projekt').trim() || 'Projekt';
  const defaultContent = fallbackCode && String(fallbackCode).trim() !== ''
    ? String(fallbackCode)
    : `# ${safeName}\n\n# Start coding here\n`;

  await fetch('../api/projects/files-v2.php?action=create', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      project_id: projectId,
      folder_id: null,
      name: 'init.py',
      content: defaultContent
    })
  });
  
  projectFileTreeDirty = true; // Mark tree as dirty since new file was created
}

async function openFileInEditor(fileId, fileName, content) {
  cacheCurrentProjectEditorDraft();

  const editor = await waitForEditorInstance();
  if (!editor) return;

  const normalizedId = Number(fileId || 0);
  const previousDir = getActiveProjectFolderPath() || await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');
  const nextDir = getActiveProjectFolderPath() || await resolveProjectDirectory(normalizedId, fileName || '');
  const draftContent = getProjectDraftContent(normalizedId);
  const effectiveContent = draftContent !== null ? draftContent : String(content || '');

  // Update current file info BEFORE setting content to prevent change listener from caching with wrong fileId
  currentOpenFileId = normalizedId || null;
  currentOpenFileName = fileName || '';
  currentOpenFileSnapshot = Object.prototype.hasOwnProperty.call(projectSavedSnapshots, normalizedId)
    ? String(projectSavedSnapshots[normalizedId] ?? '')
    : String(content || '');

  // Skip next draft cache because we're about to set content programmatically
  projectSkipNextDraftCache = true;
  setEditorContent(editor, effectiveContent);
  window.editor = editor;

  setProjectSavedSnapshot(normalizedId, currentOpenFileName, currentOpenFileSnapshot);
  setProjectDraftContent(normalizedId, currentOpenFileName, effectiveContent);
  applyProjectFileDirtyMarker(normalizedId);

  if (currentProject && isProjectGuiMode(currentProject) && previousDir !== nextDir) {
    clearProjectOutputPanels();
    setProjectGuiPlaceholder(nextDir);
  }
  
  markFileInTreeWithRetry(fileId);
}

function markFileInTreeWithRetry(fileId, attempt = 0) {
  const normalizedFileId = Number(fileId);
  if (!normalizedFileId) {
    return;
  }

  if (window.fileTreeManager && typeof window.fileTreeManager.markFileAsSelected === 'function') {
    window.fileTreeManager.markFileAsSelected(normalizedFileId);
    const selectedEl = document.querySelector(`#project-file-tree .file-tree-item.selected[data-node-id="${normalizedFileId}"]`);
    if (selectedEl) {
      return;
    }
  }

  if (attempt < 8) {
    setTimeout(() => markFileInTreeWithRetry(normalizedFileId, attempt + 1), 120);
  }
}

const PROJECT_MODE = {
  CODE: 'code',
  GUI: 'gui',
  DB: 'db',
  UML: 'uml'
};

const DB_MODEL_FILE_NAME = 'db_model.json';
const DB_DATA_FILE_NAME = 'db_data.json';
const DB_EXPORT_FILE_NAME = 'db_export.sql';

const PROJECT_MODE_CONFIG = {
  [PROJECT_MODE.CODE]: {
    showGuiContainer: false,
    rightPanelGuiActive: false,
    showWebHelp: false,
    typeLabel: 'Python'
  },
  [PROJECT_MODE.GUI]: {
    showGuiContainer: true,
    rightPanelGuiActive: true,
    showWebHelp: true,
    typeLabel: 'HTML/Web'
  },
  [PROJECT_MODE.DB]: {
    showGuiContainer: true,
    rightPanelGuiActive: false,
    showWebHelp: false,
    typeLabel: 'Datenbank'
  },
  [PROJECT_MODE.UML]: {
    showGuiContainer: true,
    rightPanelGuiActive: false,
    showWebHelp: false,
    typeLabel: 'UML'
  }
};

function getProjectTypeNormalized(project) {
  return String(project?.project_type || '').trim().toLowerCase();
}

function resolveProjectMode(project) {
  const type = getProjectTypeNormalized(project);
  if (type === 'html' || type === 'mixed') {
    return PROJECT_MODE.GUI;
  }
  if (type === 'db_small' || type === 'db') {
    return PROJECT_MODE.DB;
  }
  if (type === 'uml' || type === 'uml_model') {
    return PROJECT_MODE.UML;
  }
  return PROJECT_MODE.CODE;
}

function getProjectModeConfig(project) {
  const mode = resolveProjectMode(project);
  return PROJECT_MODE_CONFIG[mode] || PROJECT_MODE_CONFIG[PROJECT_MODE.CODE];
}

function isProjectGuiMode(project) {
  return resolveProjectMode(project) === PROJECT_MODE.GUI;
}

function getProjectTypeLabel(project) {
  const config = getProjectModeConfig(project);
  return config.typeLabel || 'Python';
}

function ensureDbDesignerUiStyles() {
  if (document.getElementById('db-designer-ui-styles')) return;

  const style = document.createElement('style');
  style.id = 'db-designer-ui-styles';
  style.textContent = `
    #db-design-container button[data-db-action],
    #db-data-container button[data-db-action],
    #gui-container button[data-db-action],
    #db-structure-tree .db-tree-item[data-db-action] {
      transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease, opacity 120ms ease;
      cursor: pointer;
      border: 1px solid var(--border);
      background: var(--panel);
      color: var(--text-primary);
      border-radius: 4px;
      box-shadow: none;
      font-weight: 600;
    }
    #db-design-container button[data-db-action]:hover,
    #db-data-container button[data-db-action]:hover,
    #gui-container button[data-db-action]:hover,
    #db-structure-tree .db-tree-item[data-db-action]:hover {
      transform: translateY(-1px);
      background: var(--text-secondary) !important;
      border-color: var(--border) !important;
      color: var(--text-primary) !important;
      opacity: 0.7 !important;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    }
    #db-design-container button[data-db-action]:active,
    #db-data-container button[data-db-action]:active,
    #gui-container button[data-db-action]:active,
    #db-structure-tree .db-tree-item[data-db-action]:active,
    #db-design-container button[data-db-action]:focus-visible,
    #db-data-container button[data-db-action]:focus-visible,
    #gui-container button[data-db-action]:focus-visible,
    #db-structure-tree .db-tree-item[data-db-action]:focus-visible {
      transform: translateY(0);
      background: var(--text-secondary) !important;
      border-color: var(--border) !important;
      color: var(--text-primary) !important;
      opacity: 0.85 !important;
      box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.16);
      outline: none;
    }
    .db-designer-toast {
      margin-top: 8px;
      padding: 7px 10px;
      border-radius: 8px;
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #86efac;
      font-size: 12px;
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      align-self: flex-start;
    }
    .db-sql-log {
      margin-top: 8px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: #fafafa;
      overflow: hidden;
    }
    .db-sql-log-header {
      padding: 7px 10px;
      font-size: 12px;
      font-weight: 700;
      color: var(--text-secondary);
      border-bottom: 1px solid var(--border);
      background: #f5f5f5;
    }
    .db-sql-log-list {
      display: flex;
      flex-direction: column;
      gap: 4px;
      padding: 8px 10px;
      max-height: 150px;
      overflow: auto;
      font-size: 12px;
    }
    .db-sql-log-item {
      padding: 6px 8px;
      border-radius: 6px;
      background: white;
      border: 1px solid #ececec;
      color: #444;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .db-sql-log-item.error {
      border-color: #fecaca;
      background: #fff1f2;
      color: #b42318;
    }
    .db-sql-log-item.success {
      border-color: #bbf7d0;
      background: #f0fdf4;
      color: #166534;
    }
    .db-data-grid-row-dirty > td {
      background: #fefce8;
    }
    .db-data-grid-row:has(.db-data-grid-input:focus) > td {
      background: #f8fbff;
    }
    .db-data-grid-input {
      width: 100%;
      border: 1px solid transparent;
      border-radius: 4px;
      padding: 4px 6px;
      background: transparent;
      color: inherit;
    }
    .db-data-grid-input:focus {
      outline: none;
      border-color: #2563eb;
      background: #fef3c7;
      box-shadow: inset 0 0 0 1px #2563eb;
    }
    .db-data-grid-input[disabled] {
      background: #f8fafc;
      color: #64748b;
      cursor: not-allowed;
    }
  `;
  document.head.appendChild(style);
}

function getSqlEditorPermissions(project = currentProject) {
  const isProjectMode = Boolean(project && resolveProjectMode(project) === PROJECT_MODE.DB);
  const config = (
    window.dbSqlEditorConfig
    || window.currentTask?.sqlEditorConfig
    || window.currentTask?.dbSqlEditorConfig
    || window.currentTask?.taskSettings?.sqlEditorConfig
    || {}
  );

  const normalizeBool = (value, fallback) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();
      if (['1', 'true', 'yes', 'on'].includes(normalized)) return true;
      if (['0', 'false', 'no', 'off'].includes(normalized)) return false;
    }
    return fallback;
  };

  return {
    allowRun: isProjectMode || normalizeBool(config.allowRun ?? config.run ?? true, true),
    allowImport: isProjectMode || normalizeBool(config.allowImport ?? config.importSnippet ?? config.import ?? true, true),
    allowExport: isProjectMode || normalizeBool(config.allowExport ?? config.exportSnippet ?? config.export ?? true, true),
    allowCopy: isProjectMode || normalizeBool(config.allowCopy ?? config.copy ?? true, true),
    allowPaste: isProjectMode || normalizeBool(config.allowPaste ?? config.paste ?? true, true)
  };
}

function ensureSqlSnippetImportInput() {
  let input = document.getElementById('db-sql-snippet-import');
  if (input) return input;

  input = document.createElement('input');
  input.id = 'db-sql-snippet-import';
  input.type = 'file';
  input.accept = '.sql,.txt,.json';
  input.style.display = 'none';
  input.addEventListener('change', async () => {
    ensureDbDesignerStateExists();
    const file = input.files?.[0];
    if (!file) return;

    try {
      const content = await file.text();
      dbDesignerState.sqlText = content;
      dbDesignerState.statusMessage = `Snippet geladen: ${file.name}`;
      renderDbDesigner();
    } catch (error) {
      dbDesignerState.statusMessage = `Snippet laden fehlgeschlagen: ${error?.message || error}`;
      renderDbDesigner();
    } finally {
      input.value = '';
    }
  });

  document.body.appendChild(input);
  return input;
}

function renderNonGuiProjectPlaceholder(project) {
  const mode = resolveProjectMode(project);
  if (mode === PROJECT_MODE.DB) {
    return '<p style="color: #666; padding: 18px; margin: 0;">DB-Modus aktiv. Eigener DB-Designer-Container ist vorbereitet.</p>';
  }
  if (mode === PROJECT_MODE.UML) {
    return '<p style="color: #666; padding: 18px; margin: 0;">UML-Modus aktiv. Eigener UML-Designer-Container ist vorbereitet.</p>';
  }
  return '';
}

function setProjectsBodyMode(mode) {
  if (!document?.body) return;
  if (mode === PROJECT_MODE.DB) {
    document.body.classList.add('db-mode');
  } else {
    document.body.classList.remove('db-mode');
  }

  const leftHeader = document.getElementById('left-structure-header');
  if (leftHeader) {
    if (mode === PROJECT_MODE.DB) {
      leftHeader.innerHTML = [
        '<span style="display:inline-flex; align-items:center; gap:6px;">',
        '<span>🗄️ Struktur</span>',
        '</span>',
        '<span style="display:inline-flex; align-items:center; gap:6px; margin-left:auto;">',
        '<button data-db-action="add-table" title="Neue Tabelle" aria-label="Neue Tabelle" style="width:26px; height:26px; min-width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #93c5fd; background:#2563eb; color:#ffffff; font-weight:700;">+</button>',
        '<button data-db-action="delete-selected-table" title="Markierte Tabelle löschen" aria-label="Markierte Tabelle löschen" style="width:26px; height:26px; min-width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #fecaca; background:#fff1f2; color:#dc2626; font-weight:700;">✕</button>',
        '</span>'
      ].join('');
      leftHeader.style.display = 'flex';
      leftHeader.style.alignItems = 'center';
      leftHeader.style.gap = '8px';
    } else {
      leftHeader.textContent = '📁 Dateien';
      leftHeader.style.display = '';
      leftHeader.style.alignItems = '';
      leftHeader.style.gap = '';
    }
  }
}

function resetProjectGuiModeState(guiContainer) {
  if (!guiContainer) return;
  delete guiContainer.dataset.projectHtmlRendered;
  delete guiContainer.dataset.projectHtmlDirty;
  delete guiContainer.dataset.projectHtmlActiveFolder;
  delete guiContainer.dataset.projectId;
  delete guiContainer.dataset.codeUiRunBound;
}

function applyCodeModeLayout(guiContainer) {
  setProjectsBodyMode(PROJECT_MODE.CODE);
  if (!guiContainer) {
    setProjectsRightPanelMode(false);
    return;
  }
  guiContainer.classList.remove('active');
  guiContainer.innerHTML = '';
  resetProjectGuiModeState(guiContainer);
  setProjectsRightPanelMode(false);
}

function applyGuiModeLayout(guiContainer, project) {
  setProjectsBodyMode(PROJECT_MODE.GUI);
  if (!guiContainer) {
    setProjectsRightPanelMode(true);
    return;
  }
  guiContainer.classList.add('active');
  setProjectsRightPanelMode(true);
  setProjectGuiPlaceholder('');
  guiContainer.dataset.projectId = String(project.id);
  delete guiContainer.dataset.codeUiRunBound;
}

function applyDbModeLayout(guiContainer, project) {
  setProjectsBodyMode(PROJECT_MODE.DB);
  if (!guiContainer) {
    setProjectsRightPanelMode(false);
    return;
  }
  guiContainer.classList.add('active');
  setProjectsRightPanelMode(false);
  guiContainer.innerHTML = '';
  resetProjectGuiModeState(guiContainer);
}

function applyUmlModeLayout(guiContainer, project) {
  setProjectsBodyMode(PROJECT_MODE.UML);
  if (!guiContainer) {
    setProjectsRightPanelMode(false);
    return;
  }
  guiContainer.classList.add('active');
  setProjectsRightPanelMode(false);
  guiContainer.innerHTML = renderNonGuiProjectPlaceholder(project);
  resetProjectGuiModeState(guiContainer);
}

function applyProjectModeLayout(project) {
  const guiContainer = document.getElementById('gui-container');
  const mode = resolveProjectMode(project);

  if (mode === PROJECT_MODE.GUI) {
    applyGuiModeLayout(guiContainer, project);
    return;
  }
  if (mode === PROJECT_MODE.DB) {
    applyDbModeLayout(guiContainer, project);
    return;
  }
  if (mode === PROJECT_MODE.UML) {
    applyUmlModeLayout(guiContainer, project);
    return;
  }
  applyCodeModeLayout(guiContainer);
}

async function readDbModelFile(projectId) {
  return readProjectFileByName(projectId, DB_MODEL_FILE_NAME);
}

async function readDbDataFile(projectId) {
  return readProjectFileByName(projectId, DB_DATA_FILE_NAME);
}

async function readDbExportFile(projectId) {
  return readProjectFileByName(projectId, DB_EXPORT_FILE_NAME);
}

function getActiveDbTables(model) {
  const dbs = Array.isArray(model?.databases) ? model.databases : [];
  const dbIndex = Number(model?.activeDatabaseIndex || 0);
  const activeDb = dbs[dbIndex];
  return Array.isArray(activeDb?.tables) ? activeDb.tables : [];
}

function ensureSelectedDbTableIndex() {
  ensureDbDesignerStateExists();
  const tables = getActiveDbTables(dbDesignerState.model);
  if (!tables.length) {
    dbDesignerState.selectedTableIndex = -1;
    return;
  }
  const idx = Number(dbDesignerState.selectedTableIndex ?? 0);
  if (!Number.isFinite(idx) || idx < 0 || idx >= tables.length) {
    dbDesignerState.selectedTableIndex = 0;
  }
}

function buildDbTableSelectionKey(model, tableIndex) {
  const dbIndex = Number(model?.activeDatabaseIndex || 0);
  return `${dbIndex}:${Number(tableIndex || 0)}`;
}

function normalizeDbDataCellValue(value) {
  const text = String(value ?? '');
  if (text.trim().toUpperCase() === 'NULL') {
    return null;
  }
  return text;
}

function getDbTreeContextMenuElement() {
  if (dbTreeContextMenuState?.menuEl && document.body.contains(dbTreeContextMenuState.menuEl)) {
    return dbTreeContextMenuState.menuEl;
  }

  const menuEl = document.createElement('div');
  menuEl.id = 'db-tree-context-menu';
  menuEl.style.position = 'fixed';
  menuEl.style.minWidth = '190px';
  menuEl.style.background = '#ffffff';
  menuEl.style.border = '1px solid #d1d5db';
  menuEl.style.borderRadius = '10px';
  menuEl.style.boxShadow = '0 8px 24px rgba(0,0,0,0.14)';
  menuEl.style.padding = '6px';
  menuEl.style.display = 'none';
  menuEl.style.zIndex = '9999';
  document.body.appendChild(menuEl);

  const onPointerDown = (event) => {
    if (!menuEl.contains(event.target)) {
      hideDbTreeContextMenu();
    }
  };

  const onEscape = (event) => {
    if (event.key === 'Escape') {
      hideDbTreeContextMenu();
    }
  };

  document.addEventListener('mousedown', onPointerDown, true);
  document.addEventListener('keydown', onEscape, true);

  dbTreeContextMenuState = {
    menuEl,
    dispose: () => {
      document.removeEventListener('mousedown', onPointerDown, true);
      document.removeEventListener('keydown', onEscape, true);
    }
  };

  return menuEl;
}

function hideDbTreeContextMenu() {
  if (!dbTreeContextMenuState?.menuEl) return;
  dbTreeContextMenuState.menuEl.style.display = 'none';
  dbTreeContextMenuState.menuEl.innerHTML = '';
}

function findNextDuplicatedTableName(activeDb, sourceName) {
  const tables = Array.isArray(activeDb?.tables) ? activeDb.tables : [];
  const existingNames = new Set(tables.map((t) => String(t?.name || '').trim().toLowerCase()));
  const baseName = String(sourceName || '').trim() || 'tabelle';
  for (let suffix = 2; suffix < 1000; suffix += 1) {
    const candidate = `${baseName}${suffix}`;
    if (!existingNames.has(candidate.toLowerCase())) {
      return candidate;
    }
  }
  return `${baseName}${Date.now()}`;
}

function showDbTreeContextMenu(tableIndex, pageX, pageY) {
  const menuEl = getDbTreeContextMenuElement();
  menuEl.innerHTML = '';

  const entries = [
    { label: 'Umbenennen', action: 'context-rename-table' },
    { label: 'Neue Tabelle', action: 'context-add-table' },
    { label: 'Tabellendaten löschen', action: 'context-clear-table-data' },
    { label: 'Tabelle löschen', action: 'context-delete-table', danger: true },
    { label: 'Tabelle duplizieren', action: 'context-duplicate-table' }
  ];

  entries.forEach((entry) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = entry.label;
    btn.style.display = 'block';
    btn.style.width = '100%';
    btn.style.textAlign = 'left';
    btn.style.border = 'none';
    btn.style.background = 'transparent';
    btn.style.padding = '8px 10px';
    btn.style.borderRadius = '8px';
    btn.style.cursor = 'pointer';
    btn.style.fontSize = '13px';
    btn.style.color = entry.danger ? '#b91c1c' : '#111827';
    btn.addEventListener('mouseenter', () => {
      btn.style.background = entry.danger ? '#fff1f2' : '#f3f4f6';
    });
    btn.addEventListener('mouseleave', () => {
      btn.style.background = 'transparent';
    });
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      hideDbTreeContextMenu();
      handleDbDesignerAction(entry.action, {
        getAttribute: (name) => {
          if (name === 'data-table-index') return String(tableIndex);
          return null;
        }
      });
    });
    menuEl.appendChild(btn);
  });

  menuEl.style.display = 'block';
  menuEl.style.left = `${Math.max(8, Number(pageX) || 0)}px`;
  menuEl.style.top = `${Math.max(8, Number(pageY) || 0)}px`;

  const rect = menuEl.getBoundingClientRect();
  const maxLeft = window.innerWidth - rect.width - 8;
  const maxTop = window.innerHeight - rect.height - 8;
  menuEl.style.left = `${Math.min(Math.max(8, Number(pageX) || 0), Math.max(8, maxLeft))}px`;
  menuEl.style.top = `${Math.min(Math.max(8, Number(pageY) || 0), Math.max(8, maxTop))}px`;
}

function getDbInlineDraftRow(model, tableIndex) {
  ensureDbDesignerStateExists();
  const key = buildDbTableSelectionKey(model, tableIndex);
  const drafts = dbDesignerState.inlineRowDrafts || {};
  if (!drafts[key] || typeof drafts[key] !== 'object') {
    drafts[key] = {};
    dbDesignerState.inlineRowDrafts = drafts;
  }
  return drafts[key];
}

function clearDbInlineDraftRow(model, tableIndex) {
  ensureDbDesignerStateExists();
  const key = buildDbTableSelectionKey(model, tableIndex);
  const drafts = dbDesignerState.inlineRowDrafts || {};
  drafts[key] = {};
  dbDesignerState.inlineRowDrafts = drafts;
}

function getDbPendingDataRowEdits(tableIndex, rowIndex) {
  ensureDbDesignerStateExists();
  const key = `${tableIndex}:${rowIndex}`;
  const pending = dbDesignerState.pendingDataRowEdits || {};
  if (!pending[key] || typeof pending[key] !== 'object') {
    pending[key] = {};
    dbDesignerState.pendingDataRowEdits = pending;
  }
  return pending[key];
}

function clearDbPendingDataRowEdits(tableIndex, rowIndex) {
  ensureDbDesignerStateExists();
  const key = `${tableIndex}:${rowIndex}`;
  const pending = dbDesignerState.pendingDataRowEdits || {};
  if (pending[key]) {
    delete pending[key];
    dbDesignerState.pendingDataRowEdits = pending;
  }
}

function syncDbDataRowActionButtons(inputEl) {
  const row = inputEl?.closest?.('tr');
  if (!row) return;
  const tableIndex = Number(inputEl?.getAttribute('data-table-index') || -1);
  const rowIndex = Number(inputEl?.getAttribute('data-row-index') || -1);
  const actionCell = row.querySelector('td:last-child');
  if (!actionCell) return;

  const key = String(inputEl?.getAttribute('data-db-input') || '');
  const pending = getDbPendingDataRowEdits(tableIndex, rowIndex);
  const isDirty = Object.keys(pending || {}).length > 0 || (key === 'row-value' && String(inputEl?.getAttribute('data-col-name') || ''));
  const colName = String(inputEl?.getAttribute('data-col-name') || '');
  const currentValue = inputEl?.value ?? '';
  let shouldShowActions = isDirty;

  if (key === 'row-new-value') {
    const draft = getDbInlineDraftRow(dbDesignerState?.model, tableIndex);
    const draftValues = Object.values(draft || {}).filter((value) => String(value ?? '').trim() !== '');
    shouldShowActions = String(currentValue ?? '').trim() !== '' || draftValues.length > 0;
  } else if (key === 'row-value') {
    const originalValue = colName && tableIndex >= 0 && rowIndex >= 0
      ? (() => {
          const model = dbDesignerState?.model;
          const activeDbIndex = Number(model?.activeDatabaseIndex || 0);
          const activeDb = model?.databases?.[activeDbIndex];
          const table = activeDb?.tables?.[tableIndex];
          const rowData = Array.isArray(table?.rows) ? table.rows[rowIndex] : null;
          return rowData?.[colName];
        })()
      : undefined;
    const changedFromOriginal = originalValue !== undefined && String(currentValue ?? '') !== String(originalValue ?? '');
    shouldShowActions = shouldShowActions || changedFromOriginal;
  }

  row.classList.toggle('db-data-grid-row-dirty', shouldShowActions);
  if (key === 'row-new-value') {
    actionCell.innerHTML = shouldShowActions
      ? `<span style="display:inline-flex; gap:2px; align-items:center;"><button data-db-action="add-row-inline" data-table-index="${tableIndex}" title="Zeile einfuegen" aria-label="Zeile einfuegen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; padding:0;">✓</button><button data-db-action="discard-inline-row" data-table-index="${tableIndex}" title="Änderungen verwerfen" aria-label="Änderungen verwerfen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #fecaca; background:#fff1f2; color:#b42318; border-radius:6px; padding:0;">⛔</button></span>`
      : `<button data-db-action="add-row-inline" data-table-index="${tableIndex}" title="Zeile einfuegen" style="border:1px solid #bfdbfe; background:#eff6ff; color:#2563eb; border-radius:6px; width:26px; height:26px; line-height:1; font-weight:700;">+</button>`;
    return;
  }

  actionCell.innerHTML = shouldShowActions
    ? `<span style="display:inline-flex; gap:2px; align-items:center;"><button data-db-action="commit-row-edit" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile speichern" aria-label="Zeile speichern" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; padding:0;">✓</button><button data-db-action="discard-row-edit" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Änderungen verwerfen" aria-label="Änderungen verwerfen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #fecaca; background:#fff1f2; color:#b42318; border-radius:6px; padding:0;">⛔</button></span><button data-db-action="duplicate-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile duplizieren" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">⧉</button><button data-db-action="delete-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile loeschen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">-</button>`
    : `<button data-db-action="duplicate-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile duplizieren" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">⧉</button><button data-db-action="delete-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile loeschen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">-</button>`;
}

function hasDbPendingRowEdits(tableIndex, rowIndex) {
  const pending = getDbPendingDataRowEdits(tableIndex, rowIndex);
  return Object.keys(pending || {}).length > 0;
}

function getDbAutoGeneratedValue(table, column, rowIndexToExclude = null) {
  const columnName = String(column?.name || '').trim();
  if (!columnName || String(column?.type || '').toUpperCase() !== 'AUTO') {
    return '';
  }
  const rows = Array.isArray(table?.rows) ? table.rows : [];
  const numericValues = rows
    .map((row, index) => {
      if (index === rowIndexToExclude) return null;
      const rawValue = row?.[columnName];
      if (rawValue === null || rawValue === undefined || rawValue === '') return null;
      const parsed = Number(String(rawValue).trim());
      return Number.isFinite(parsed) ? parsed : null;
    })
    .filter((value) => Number.isFinite(value));
  const nextValue = numericValues.length ? Math.max(...numericValues) + 1 : 1;
  return String(nextValue);
}

function createDefaultDbInlineColumnDraft() {
  const col = createDefaultDbColumn();
  col.name = '';
  col.default = '';
  return col;
}

function getDbInlineDraftColumn(model, tableIndex) {
  ensureDbDesignerStateExists();
  const key = buildDbTableSelectionKey(model, tableIndex);
  const drafts = dbDesignerState.inlineColumnDrafts || {};
  if (!drafts[key] || typeof drafts[key] !== 'object') {
    drafts[key] = createDefaultDbInlineColumnDraft();
    dbDesignerState.inlineColumnDrafts = drafts;
  }
  return drafts[key];
}

function clearDbInlineDraftColumn(model, tableIndex) {
  ensureDbDesignerStateExists();
  const key = buildDbTableSelectionKey(model, tableIndex);
  const drafts = dbDesignerState.inlineColumnDrafts || {};
  drafts[key] = createDefaultDbInlineColumnDraft();
  dbDesignerState.inlineColumnDrafts = drafts;
}

function focusDbGridCell(tableIndex, rowIndex, colIndex) {
  const dataContainer = document.getElementById('db-data-container');
  if (!dataContainer) return;
  const selector = `[data-db-grid-cell="1"][data-table-index="${tableIndex}"][data-row-index="${rowIndex}"][data-col-index="${colIndex}"]`;
  const input = dataContainer.querySelector(selector);
  if (!(input instanceof HTMLInputElement)) return;
  input.focus();
  input.select();
}

function applyDbGridPaste(model, tableIndex, startRowIndex, startColIndex, clipboardText) {
  const dbIndex = Number(model?.activeDatabaseIndex || 0);
  const activeDb = model?.databases?.[dbIndex];
  const table = activeDb?.tables?.[tableIndex];
  if (!table) return false;

  const columns = Array.isArray(table.columns) ? table.columns : [];
  if (!columns.length) return false;

  const rowsText = String(clipboardText || '')
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .split('\n')
    .filter((line) => line.length > 0);
  if (!rowsText.length) return false;

  table.rows = Array.isArray(table.rows) ? table.rows : [];
  let changed = false;

  rowsText.forEach((line, lineOffset) => {
    const values = line.split('\t');
    const targetRowIndex = startRowIndex + lineOffset;
    while (table.rows.length <= targetRowIndex) {
      const row = {};
      columns.forEach((col) => {
        const key = String(col?.name || '').trim();
        if (key) row[key] = '';
      });
      table.rows.push(row);
      changed = true;
    }

    const rowObj = table.rows[targetRowIndex] || {};
    values.forEach((rawValue, valueOffset) => {
      const colIndex = startColIndex + valueOffset;
      if (colIndex < 0 || colIndex >= columns.length) return;
      const colName = String(columns[colIndex]?.name || '').trim();
      if (!colName) return;
      rowObj[colName] = normalizeDbDataCellValue(rawValue);
      changed = true;
    });
    table.rows[targetRowIndex] = rowObj;
  });

  return changed;
}

function countDbModelEntities(model) {
  const databases = Array.isArray(model?.databases) ? model.databases : [];
  let tableCount = 0;
  let columnCount = 0;

  for (const db of databases) {
    const tables = Array.isArray(db?.tables) ? db.tables : [];
    tableCount += tables.length;
    for (const table of tables) {
      columnCount += Array.isArray(table?.columns) ? table.columns.length : 0;
    }
  }

  return {
    databaseCount: databases.length,
    tableCount,
    columnCount
  };
}

function cloneDbModel(model) {
  return JSON.parse(JSON.stringify(model || {}));
}

function buildDbDataPayloadFromModel(model) {
  const normalized = DbModelSchema.normalizeDbModel(model).model;
  const payload = {
    version: 1,
    databases: []
  };

  for (const db of normalized.databases || []) {
    const dbEntry = {
      id: db.id,
      name: db.name,
      tables: []
    };
    for (const table of db.tables || []) {
      dbEntry.tables.push({
        id: table.id,
        name: table.name,
        rows: Array.isArray(table.rows) ? table.rows : []
      });
    }
    payload.databases.push(dbEntry);
  }

  return payload;
}

function stripRowsFromDbModel(model) {
  const normalized = DbModelSchema.normalizeDbModel(model).model;
  for (const db of normalized.databases || []) {
    for (const table of db.tables || []) {
      table.rows = [];
    }
  }
  return normalized;
}

function mergeDbDataPayloadIntoModel(model, payloadRaw) {
  const normalizedModel = DbModelSchema.normalizeDbModel(model).model;
  const payload = typeof payloadRaw === 'string'
    ? JSON.parse(payloadRaw || '{}')
    : (payloadRaw || {});

  const dbMap = new Map();
  for (const db of payload?.databases || []) {
    const key = String(db?.id || db?.name || '').toLowerCase();
    if (!key) continue;
    dbMap.set(key, db);
  }

  for (const db of normalizedModel.databases || []) {
    const dbCandidate = dbMap.get(String(db.id || '').toLowerCase())
      || dbMap.get(String(db.name || '').toLowerCase());
    const tableMap = new Map();
    for (const table of dbCandidate?.tables || []) {
      const key = String(table?.id || table?.name || '').toLowerCase();
      if (!key) continue;
      tableMap.set(key, table);
    }

    for (const table of db.tables || []) {
      const tableCandidate = tableMap.get(String(table.id || '').toLowerCase())
        || tableMap.get(String(table.name || '').toLowerCase());
      table.rows = Array.isArray(tableCandidate?.rows) ? tableCandidate.rows : (Array.isArray(table.rows) ? table.rows : []);
    }
  }

  return normalizedModel;
}

function parseSqlLiteral(raw) {
  const token = String(raw || '').trim();
  if (!token) return '';
  if (/^null$/i.test(token)) return null;
  if (/^'.*'$/.test(token)) return token.slice(1, -1).replace(/''/g, "'");
  if (/^-?\d+(\.\d+)?$/.test(token)) return Number(token);
  return token;
}

function splitSqlValuesList(rawValues) {
  const values = [];
  let current = '';
  let inQuote = false;
  for (let i = 0; i < rawValues.length; i += 1) {
    const ch = rawValues[i];
    if (ch === "'" && rawValues[i - 1] !== '\\') {
      inQuote = !inQuote;
      current += ch;
      continue;
    }
    if (ch === ',' && !inQuote) {
      values.push(current.trim());
      current = '';
      continue;
    }
    current += ch;
  }
  if (current.trim() !== '') values.push(current.trim());
  return values;
}

function findActiveDbTableByName(model, tableName) {
  const dbs = Array.isArray(model?.databases) ? model.databases : [];
  const dbIndex = Number(model?.activeDatabaseIndex || 0);
  const activeDb = dbs[dbIndex];
  const tables = Array.isArray(activeDb?.tables) ? activeDb.tables : [];
  return tables.find((t) => String(t?.name || '').toLowerCase() === String(tableName || '').toLowerCase()) || null;
}

function buildSqlResultSet(columns, rows) {
  return {
    ok: true,
    mode: 'result-set',
    rowCount: rows.length,
    columns,
    rows,
    message: `SQL erfolgreich ausgefuehrt (${rows.length} Zeilen)`
  };
}

function applySimpleWhere(rows, whereClause) {
  if (!whereClause) return rows;
  const parts = String(whereClause).split(/\s+AND\s+/i).map((p) => p.trim()).filter(Boolean);
  return rows.filter((row) => parts.every((expr) => {
    const m = expr.match(/^([a-zA-Z_][\w]*(?:\.[a-zA-Z_][\w]*)?)\s*=\s*(.+)$/);
    if (!m) return true;
    const key = m[1];
    const expected = parseSqlLiteral(m[2]);
    return (row?.[key] ?? null) == expected;
  }));
}

function parseSelectItems(selectExpr) {
  return String(selectExpr || '')
    .split(',')
    .map((item) => String(item || '').trim())
    .filter(Boolean)
    .map((item) => {
      const aliasMatch = item.match(/^(.*?)\s+AS\s+([a-zA-Z_][\w]*)$/i);
      const expr = aliasMatch ? String(aliasMatch[1] || '').trim() : item;
      const label = aliasMatch ? String(aliasMatch[2] || '').trim() : expr;
      const aggregateMatch = expr.match(/^(COUNT|SUM|AVG|MIN|MAX)\s*\(\s*(\*|[a-zA-Z_][\w]*(?:\.[a-zA-Z_][\w]*)?)\s*\)$/i);
      return {
        expr,
        label,
        aggregate: aggregateMatch
          ? {
              fn: String(aggregateMatch[1] || '').toUpperCase(),
              arg: String(aggregateMatch[2] || '').trim()
            }
          : null
      };
    });
}

function collectSqlColumnCandidates(fallbackColumns) {
  const candidates = new Set();
  (Array.isArray(fallbackColumns) ? fallbackColumns : []).forEach((column) => {
    const value = String(column || '').trim();
    if (!value) return;
    candidates.add(value);
    const lastSegment = value.includes('.') ? value.split('.').pop() : value;
    if (lastSegment) candidates.add(String(lastSegment));
  });
  return candidates;
}

function assertSqlColumnReferencesExist(fallbackColumns, refs, contextLabel = 'Spalte') {
  const candidates = collectSqlColumnCandidates(fallbackColumns);
  const invalidRefs = (Array.isArray(refs) ? refs : [])
    .map((ref) => String(ref || '').trim())
    .filter((ref) => Boolean(ref) && ref !== '*')
    .filter((ref) => !candidates.has(ref));

  if (!invalidRefs.length) return;
  const firstInvalid = invalidRefs[0];
  const available = Array.from(candidates).sort();
  throw new Error(`${contextLabel} '${firstInvalid}' nicht gefunden. Verfuegbare Spalten: ${available.join(', ') || 'keine'}`);
}

function evalScalarSqlExpr(row, expr) {
  const normalized = String(expr || '').trim();
  if (!normalized) return null;
  if (normalized === '*') return null;
  return row?.[normalized] ?? null;
}

function evalAggregateSqlExpr(rows, aggregate) {
  const fn = String(aggregate?.fn || '').toUpperCase();
  const arg = String(aggregate?.arg || '').trim();
  if (fn === 'COUNT') {
    if (arg === '*') return rows.length;
    return rows.filter((row) => row?.[arg] != null && String(row?.[arg]) !== '').length;
  }

  const numeric = rows
    .map((row) => Number(row?.[arg]))
    .filter((value) => Number.isFinite(value));

  if (fn === 'SUM') return numeric.reduce((acc, value) => acc + value, 0);
  if (fn === 'AVG') return numeric.length ? (numeric.reduce((acc, value) => acc + value, 0) / numeric.length) : null;

  const values = rows.map((row) => row?.[arg]).filter((value) => value != null);
  if (!values.length) return null;
  if (fn === 'MIN') {
    return values.reduce((min, value) => (String(value).localeCompare(String(min), undefined, { numeric: true, sensitivity: 'base' }) < 0 ? value : min), values[0]);
  }
  if (fn === 'MAX') {
    return values.reduce((max, value) => (String(value).localeCompare(String(max), undefined, { numeric: true, sensitivity: 'base' }) > 0 ? value : max), values[0]);
  }
  return null;
}

function sortSqlRows(rows, orderCol, orderDir) {
  if (!orderCol) return rows;
  const direction = String(orderDir || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
  return [...rows].sort((a, b) => {
    const av = a?.[orderCol] ?? '';
    const bv = b?.[orderCol] ?? '';
    if (av === bv) return 0;
    const cmp = String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' });
    return direction === 'DESC' ? -cmp : cmp;
  });
}

function buildJoinSqlRow(leftRow, rightRow, leftAlias, leftTableName, rightAlias, rightTableName) {
  const row = {};

  const addFields = (source, alias, tableName) => {
    Object.keys(source || {}).forEach((key) => {
      const value = source?.[key] ?? null;
      row[`${alias}.${key}`] = value;
      row[`${tableName}.${key}`] = value;
      if (!(key in row)) row[key] = value;
    });
  };

  addFields(leftRow, leftAlias, leftTableName);
  addFields(rightRow, rightAlias, rightTableName);
  return row;
}

function materializeSelectResult(selectExpr, sourceRows, fallbackColumns, groupByClause, orderCol, orderDir, limitRaw) {
  const rowsInput = Array.isArray(sourceRows) ? sourceRows : [];
  const groupByCols = String(groupByClause || '')
    .split(',')
    .map((part) => String(part || '').trim())
    .filter(Boolean);

  if (String(selectExpr || '').trim() === '*' && groupByCols.length === 0) {
    const columns = Array.isArray(fallbackColumns) ? fallbackColumns : [];
    let rows = rowsInput.map((row) => {
      const entry = {};
      columns.forEach((col) => {
        entry[col] = row?.[col] ?? null;
      });
      return entry;
    });
    rows = sortSqlRows(rows, orderCol, orderDir);
    const limit = Number.isFinite(Number(limitRaw)) ? Number(limitRaw) : null;
    if (limit != null && limit >= 0) rows = rows.slice(0, limit);
    return buildSqlResultSet(columns, rows);
  }

  const selectItems = parseSelectItems(selectExpr);
  const hasAggregate = selectItems.some((item) => Boolean(item.aggregate));
  const resultColumns = selectItems.map((item) => item.label);
  assertSqlColumnReferencesExist(
    fallbackColumns,
    [
      ...selectItems.filter((item) => !item.aggregate || item.aggregate.arg !== '*').map((item) => item.aggregate ? item.aggregate.arg : item.expr),
      ...groupByCols,
      ...(orderCol ? [orderCol] : [])
    ],
    'Spalte'
  );
  let rows = [];

  if (groupByCols.length > 0 || hasAggregate) {
    const grouped = new Map();
    rowsInput.forEach((row) => {
      const key = groupByCols.length
        ? groupByCols.map((col) => JSON.stringify(evalScalarSqlExpr(row, col))).join('|')
        : '__all__';
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key).push(row);
    });

    grouped.forEach((groupRows) => {
      const first = groupRows[0] || {};
      const out = {};
      selectItems.forEach((item) => {
        out[item.label] = item.aggregate
          ? evalAggregateSqlExpr(groupRows, item.aggregate)
          : evalScalarSqlExpr(first, item.expr);
      });
      rows.push(out);
    });
  } else {
    rows = rowsInput.map((row) => {
      const out = {};
      selectItems.forEach((item) => {
        out[item.label] = evalScalarSqlExpr(row, item.expr);
      });
      return out;
    });
  }

  rows = sortSqlRows(rows, orderCol, orderDir);
  const limit = Number.isFinite(Number(limitRaw)) ? Number(limitRaw) : null;
  if (limit != null && limit >= 0) rows = rows.slice(0, limit);
  return buildSqlResultSet(resultColumns, rows);
}

function executeDbSqlQueryLocal(queryText, model) {
  const query = String(queryText || '').trim().replace(/;$/, '');
  const workingModel = cloneDbModel(model);

  const showTables = query.match(/^\s*(SHOW\s+TABLES|SELECT\s+name\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*'table')\s*$/i);
  if (showTables) {
    const db = getActiveDbTables(workingModel);
    const rows = db.map((t) => ({ name: t.name }));
    return { result: buildSqlResultSet(['name'], rows), model: workingModel, changedData: false };
  }

  const describeMatch = query.match(/^\s*(DESCRIBE|PRAGMA\s+table_info)\s+([a-zA-Z_][\w]*)\s*\)?\s*$/i);
  if (describeMatch) {
    const table = findActiveDbTableByName(workingModel, describeMatch[2]);
    if (!table) throw new Error(`Tabelle '${describeMatch[2]}' nicht gefunden.`);
    const rows = (table.columns || []).map((c, idx) => ({
      cid: idx,
      name: c.name,
      type: c.type,
      notnull: c.nullable ? 0 : 1,
      dflt_value: c.default ?? '',
      pk: c.pk ? 1 : 0
    }));
    return { result: buildSqlResultSet(['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk'], rows), model: workingModel, changedData: false };
  }

  const joinSelectMatch = query.match(/^\s*SELECT\s+(.+?)\s+FROM\s+([a-zA-Z_][\w]*)(?:\s+(?:AS\s+)?([a-zA-Z_][\w]*))?\s+INNER\s+JOIN\s+([a-zA-Z_][\w]*)(?:\s+(?:AS\s+)?([a-zA-Z_][\w]*))?\s+ON\s+([a-zA-Z_][\w]*(?:\.[a-zA-Z_][\w]*)?)\s*=\s*([a-zA-Z_][\w]*(?:\.[a-zA-Z_][\w]*)?)(?:\s+WHERE\s+(.+?))?(?:\s+GROUP\s+BY\s+(.+?))?(?:\s+ORDER\s+BY\s+([a-zA-Z_][\w]*(?:\.[a-zA-Z_][\w]*)?)(?:\s+(ASC|DESC))?)?(?:\s+LIMIT\s+(\d+))?\s*$/i);
  if (joinSelectMatch) {
    const selectExpr = String(joinSelectMatch[1] || '').trim();
    const leftTableName = String(joinSelectMatch[2] || '').trim();
    const leftAlias = String(joinSelectMatch[3] || leftTableName).trim();
    const rightTableName = String(joinSelectMatch[4] || '').trim();
    const rightAlias = String(joinSelectMatch[5] || rightTableName).trim();
    const onLeft = String(joinSelectMatch[6] || '').trim();
    const onRight = String(joinSelectMatch[7] || '').trim();
    const whereClause = joinSelectMatch[8];
    const groupByClause = joinSelectMatch[9];
    const orderCol = joinSelectMatch[10];
    const orderDir = joinSelectMatch[11];
    const limit = joinSelectMatch[12];

    const leftTable = findActiveDbTableByName(workingModel, leftTableName);
    const rightTable = findActiveDbTableByName(workingModel, rightTableName);
    if (!leftTable) throw new Error(`Tabelle '${leftTableName}' nicht gefunden.`);
    if (!rightTable) throw new Error(`Tabelle '${rightTableName}' nicht gefunden.`);

    const leftRows = Array.isArray(leftTable.rows) ? leftTable.rows : [];
    const rightRows = Array.isArray(rightTable.rows) ? rightTable.rows : [];
    const joined = [];

    leftRows.forEach((leftRow) => {
      rightRows.forEach((rightRow) => {
        const combined = buildJoinSqlRow(leftRow, rightRow, leftAlias, leftTableName, rightAlias, rightTableName);
        if ((combined?.[onLeft] ?? null) == (combined?.[onRight] ?? null)) {
          joined.push(combined);
        }
      });
    });

    const filtered = applySimpleWhere(joined, whereClause);
    const fallbackColumns = [
      ...(leftTable.columns || []).map((c) => `${leftAlias}.${String(c?.name || '').trim()}`).filter(Boolean),
      ...(rightTable.columns || []).map((c) => `${rightAlias}.${String(c?.name || '').trim()}`).filter(Boolean)
    ];
    const result = materializeSelectResult(selectExpr, filtered, fallbackColumns, groupByClause, orderCol, orderDir, limit);
    return { result, model: workingModel, changedData: false };
  }

  const selectMatch = query.match(/^\s*SELECT\s+(.+?)\s+FROM\s+([a-zA-Z_][\w]*)(?:\s+(?:AS\s+)?([a-zA-Z_][\w]*))?(?:\s+WHERE\s+(.+?))?(?:\s+GROUP\s+BY\s+(.+?))?(?:\s+ORDER\s+BY\s+([a-zA-Z_][\w]*(?:\.[a-zA-Z_][\w]*)?)(?:\s+(ASC|DESC))?)?(?:\s+LIMIT\s+(\d+))?\s*$/i);
  if (selectMatch) {
    const selectExpr = selectMatch[1].trim();
    const table = findActiveDbTableByName(workingModel, selectMatch[2]);
    if (!table) throw new Error(`Tabelle '${selectMatch[2]}' nicht gefunden.`);
    const tableAlias = String(selectMatch[3] || selectMatch[2]).trim();
    const sourceRows = Array.isArray(table.rows)
      ? table.rows.map((r) => buildJoinSqlRow(r, {}, tableAlias, String(selectMatch[2] || '').trim(), tableAlias, String(selectMatch[2] || '').trim()))
      : [];
    const filtered = applySimpleWhere(sourceRows, selectMatch[4]);
    const groupByClause = selectMatch[5];
    const orderCol = selectMatch[6];
    const orderDir = selectMatch[7];
    const limit = selectMatch[8];
    const fallbackColumns = (table.columns || []).map((c) => String(c.name || '').trim()).filter(Boolean);
    const result = materializeSelectResult(selectExpr, filtered, fallbackColumns, groupByClause, orderCol, orderDir, limit);
    return { result, model: workingModel, changedData: false };
  }

  const insertMatch = query.match(/^\s*INSERT\s+INTO\s+([a-zA-Z_][\w]*)\s*\((.+)\)\s*VALUES\s*\((.+)\)\s*$/i);
  if (insertMatch) {
    const table = findActiveDbTableByName(workingModel, insertMatch[1]);
    if (!table) throw new Error(`Tabelle '${insertMatch[1]}' nicht gefunden.`);
    const columns = insertMatch[2].split(',').map((c) => c.trim()).filter(Boolean);
    const values = splitSqlValuesList(insertMatch[3]).map(parseSqlLiteral);
    if (columns.length !== values.length) throw new Error('INSERT Spaltenanzahl passt nicht zu Werteanzahl.');
    const row = {};
    columns.forEach((col, idx) => { row[col] = values[idx]; });
    table.rows = Array.isArray(table.rows) ? table.rows : [];
    table.rows.push(row);
    return {
      result: { ok: true, mode: 'command', rowCount: 1, columns: [], rows: [], message: '1 Zeile eingefuegt' },
      model: workingModel,
      changedData: true
    };
  }

  const updateMatch = query.match(/^\s*UPDATE\s+([a-zA-Z_][\w]*)\s+SET\s+(.+?)(?:\s+WHERE\s+(.+))?\s*$/i);
  if (updateMatch) {
    const table = findActiveDbTableByName(workingModel, updateMatch[1]);
    if (!table) throw new Error(`Tabelle '${updateMatch[1]}' nicht gefunden.`);
    const assignments = updateMatch[2].split(',').map((a) => a.trim()).filter(Boolean);
    const setOps = assignments.map((a) => {
      const m = a.match(/^([a-zA-Z_][\w]*)\s*=\s*(.+)$/);
      if (!m) throw new Error(`Ungueltiges SET: ${a}`);
      return { col: m[1], val: parseSqlLiteral(m[2]) };
    });
    const rows = Array.isArray(table.rows) ? table.rows : [];
    const targets = applySimpleWhere(rows, updateMatch[3]);
    targets.forEach((row) => {
      setOps.forEach((op) => {
        row[op.col] = op.val;
      });
    });
    return {
      result: { ok: true, mode: 'command', rowCount: targets.length, columns: [], rows: [], message: `${targets.length} Zeilen aktualisiert` },
      model: workingModel,
      changedData: true
    };
  }

  const deleteMatch = query.match(/^\s*DELETE\s+FROM\s+([a-zA-Z_][\w]*)(?:\s+WHERE\s+(.+))?\s*$/i);
  if (deleteMatch) {
    const table = findActiveDbTableByName(workingModel, deleteMatch[1]);
    if (!table) throw new Error(`Tabelle '${deleteMatch[1]}' nicht gefunden.`);
    const rows = Array.isArray(table.rows) ? table.rows : [];
    const toDelete = new Set(applySimpleWhere(rows, deleteMatch[2]));
    table.rows = rows.filter((r) => !toDelete.has(r));
    return {
      result: { ok: true, mode: 'command', rowCount: toDelete.size, columns: [], rows: [], message: `${toDelete.size} Zeilen geloescht` },
      model: workingModel,
      changedData: true
    };
  }

  throw new Error('SQL nicht unterstuetzt. Unterstuetzt: SELECT (inkl. INNER JOIN, GROUP BY), SHOW TABLES, DESCRIBE, INSERT, UPDATE, DELETE.');
}

function createDbEntityId(prefix) {
  return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 10000)}`;
}

function createDefaultDbDatabase() {
  return {
    id: createDbEntityId('db'),
    name: 'Neue Datenbank',
    tables: []
  };
}

function createDefaultDbTable() {
  return {
    id: createDbEntityId('tbl'),
    name: 'neue_tabelle',
    columns: [
      {
        id: createDbEntityId('col'),
        name: 'id',
        type: 'AUTO',
        size: 3,
        pk: true,
        fk: false,
        nullable: false,
        default: '',
        references: null
      }
    ],
    rows: []
  };
}

function createDefaultDbColumn() {
  return {
    id: createDbEntityId('col'),
    name: 'neue_spalte',
    type: 'VARCHAR',
    size: 50,
    pk: false,
    fk: false,
    nullable: true,
    default: '',
    references: null
  };
}

function getActiveDbModel() {
  return dbDesignerState?.model || null;
}

function getActiveDbEntry() {
  const model = getActiveDbModel();
  if (!model) return null;

  const dbs = Array.isArray(model.databases) ? model.databases : [];
  const activeIndex = Number(model.activeDatabaseIndex || 0);
  if (activeIndex < 0 || activeIndex >= dbs.length) return null;
  return dbs[activeIndex] || null;
}

function ensureDbDesignerStateExists() {
  if (!dbDesignerState || !dbDesignerState.model) {
    dbDesignerState = {
      projectId: currentProject?.id || null,
      dbFileId: null,
      dbFileName: DB_MODEL_FILE_NAME,
      model: DbModelSchema.createEmptyDbModel(),
      dirty: false,
      dirtyStructure: false,
      dirtyData: false,
      statusMessage: '',
      toastMessage: '',
      toastTimer: null,
      sqlLogEntries: [],
      history: [],
      historyIndex: -1,
      saveInFlight: false,
      selectedTableIndex: 0,
      sqlText: '',
      sqlQueryResult: null,
      sqlRunInFlight: false,
      inlineRowDrafts: {},
      inlineColumnDrafts: {},
      pendingDataRowEdits: {},
      showDiagnostics: false,
      dataFilter: '',
      dataSortColumn: '',
      dataSortDirection: 'ASC'
    };
  }
}

function showDbDesignerToast(message, timeoutMs = 1500) {
  ensureDbDesignerStateExists();
  if (dbDesignerState.toastTimer) {
    window.clearTimeout(dbDesignerState.toastTimer);
  }
  dbDesignerState.toastMessage = message;
  renderDbDesigner();
  dbDesignerState.toastTimer = window.setTimeout(() => {
    dbDesignerState.toastMessage = '';
    dbDesignerState.toastTimer = null;
    renderDbDesigner();
  }, timeoutMs);
}

function pushDbDesignerHistorySnapshot() {
  ensureDbDesignerStateExists();
  const serialized = DbModelSchema.stringifyDbModel(dbDesignerState.model, false);

  if (dbDesignerState.history[dbDesignerState.historyIndex] === serialized) {
    return;
  }

  const truncated = dbDesignerState.history.slice(0, dbDesignerState.historyIndex + 1);
  truncated.push(serialized);
  dbDesignerState.history = truncated.slice(-40);
  dbDesignerState.historyIndex = dbDesignerState.history.length - 1;
}

function setDbDesignerModel(nextModel, options = {}) {
  ensureDbDesignerStateExists();
  const normalized = DbModelSchema.normalizeDbModel(nextModel);
  dbDesignerState.model = normalized.model;
  const scope = String(options.scope || 'structure');
  const changed = options.dirty !== false;
  if (changed) {
    if (scope === 'data') {
      dbDesignerState.dirtyData = true;
    } else {
      dbDesignerState.dirtyStructure = true;
    }
  }
  dbDesignerState.dirty = Boolean(dbDesignerState.dirtyStructure || dbDesignerState.dirtyData);

  if (options.pushHistory !== false) {
    pushDbDesignerHistorySnapshot();
  }

  if (Object.prototype.hasOwnProperty.call(options, 'statusMessage')) {
    dbDesignerState.statusMessage = String(options.statusMessage || '');
  }
}

function buildDbDesignerDiagnostics(model) {
  const schemaValidation = DbModelSchema.validateDbModel(model);
  const diagnostics = {
    errors: [],
    warnings: [],
    tableErrorKeys: new Set(),
    columnErrorKeys: new Set(),
    tableWarningKeys: new Set(),
    columnWarningKeys: new Set()
  };

  function tableKey(tableIndex) {
    return `${tableIndex}`;
  }

  function columnKey(tableIndex, colIndex) {
    return `${tableIndex}:${colIndex}`;
  }

  function pushError(message, tableIndex = -1, colIndex = -1) {
    diagnostics.errors.push({ message, tableIndex, colIndex });
    if (tableIndex >= 0 && colIndex >= 0) {
      diagnostics.columnErrorKeys.add(columnKey(tableIndex, colIndex));
    } else if (tableIndex >= 0) {
      diagnostics.tableErrorKeys.add(tableKey(tableIndex));
    }
  }

  function pushWarning(message, tableIndex = -1, colIndex = -1) {
    diagnostics.warnings.push({ message, tableIndex, colIndex });
    if (tableIndex >= 0 && colIndex >= 0) {
      diagnostics.columnWarningKeys.add(columnKey(tableIndex, colIndex));
    } else if (tableIndex >= 0) {
      diagnostics.tableWarningKeys.add(tableKey(tableIndex));
    }
  }

  for (const err of schemaValidation.errors || []) {
    pushError(String(err));
  }
  for (const warn of schemaValidation.warnings || []) {
    pushWarning(String(warn));
  }

  const databases = Array.isArray(model?.databases) ? model.databases : [];
  const dbIndex = Number(model?.activeDatabaseIndex || 0);
  const activeDb = databases[dbIndex];
  if (!activeDb || !Array.isArray(activeDb.tables)) {
    return {
      ...diagnostics,
      ok: diagnostics.errors.length === 0
    };
  }

  const tables = activeDb.tables;
  const tableNameToIndex = new Map();

  tables.forEach((table, tableIndex) => {
    const tableName = String(table?.name || '').trim();
    if (!tableName) {
      pushError('Tabellenname darf nicht leer sein.', tableIndex);
    } else {
      const lower = tableName.toLowerCase();
      if (tableNameToIndex.has(lower)) {
        pushError(`Doppelter Tabellenname: '${tableName}'.`, tableIndex);
      } else {
        tableNameToIndex.set(lower, tableIndex);
      }
    }
  });

  tables.forEach((table, tableIndex) => {
    const columns = Array.isArray(table?.columns) ? table.columns : [];
    const colNameToIndex = new Map();
    let pkCount = 0;

    columns.forEach((col, colIndex) => {
      const colName = String(col?.name || '').trim();
      if (!colName) {
        pushError('Spaltenname darf nicht leer sein.', tableIndex, colIndex);
      } else {
        const lowerCol = colName.toLowerCase();
        if (colNameToIndex.has(lowerCol)) {
          pushError(`Doppelte Spalte '${colName}'.`, tableIndex, colIndex);
        } else {
          colNameToIndex.set(lowerCol, colIndex);
        }
      }

      if (col?.pk) {
        pkCount += 1;
        if (col?.nullable) {
          pushError(`PK-Spalte '${colName || colIndex + 1}' darf nicht Nullable sein.`, tableIndex, colIndex);
        }
      }

      if (col?.fk) {
        const refTable = String(col?.references?.table || '').trim();
        const refColumn = String(col?.references?.column || '').trim();
        if (!refTable || !refColumn) {
          pushError(`FK-Spalte '${colName || colIndex + 1}' braucht Ref Tabelle und Ref Spalte.`, tableIndex, colIndex);
        } else {
          const refTableIndex = tableNameToIndex.get(refTable.toLowerCase());
          if (typeof refTableIndex !== 'number') {
            pushError(`FK-Referenz auf unbekannte Tabelle '${refTable}'.`, tableIndex, colIndex);
          } else {
            const refCols = Array.isArray(tables[refTableIndex]?.columns) ? tables[refTableIndex].columns : [];
            const refExists = refCols.some((c) => String(c?.name || '').trim().toLowerCase() === refColumn.toLowerCase());
            if (!refExists) {
              pushError(`FK-Referenz auf unbekannte Spalte '${refColumn}' in Tabelle '${refTable}'.`, tableIndex, colIndex);
            }
          }
        }
      } else if (col?.references?.table || col?.references?.column) {
        pushWarning(`Spalte '${colName || colIndex + 1}' hat Referenzfelder ohne aktiviertes FK.`, tableIndex, colIndex);
      }
    });

    if (columns.length === 0) {
      pushWarning('Tabelle hat keine Spalten.', tableIndex);
    }
    if (pkCount === 0) {
      pushWarning('Tabelle hat keinen Primaerschluessel.', tableIndex);
    }
  });

  return {
    ...diagnostics,
    ok: diagnostics.errors.length === 0
  };
}

function focusDbDesignerIssue(tableIndex, colIndex = -1) {
  const designContainer = document.getElementById('db-design-container');
  if (!designContainer) return;

  let selector = '';
  if (tableIndex >= 0 && colIndex >= 0) {
    selector = `[data-db-input="col-name"][data-table-index="${tableIndex}"][data-col-index="${colIndex}"]`;
  } else if (tableIndex >= 0) {
    selector = `[data-db-input="table-name"][data-table-index="${tableIndex}"]`;
  }
  if (!selector) return;

  const el = designContainer.querySelector(selector);
  if (!el || typeof el.focus !== 'function') return;
  el.scrollIntoView({ block: 'center', behavior: 'smooth' });
  el.focus();
}

function dbTypeOptionsMarkup(selectedType) {
  const types = ['AUTO', 'INTEGER', 'BOOLEAN', 'DATE', 'DATETIME', 'TIME', 'VARCHAR', 'FLOAT'];
  const normalizedType = (() => {
    const value = String(selectedType || '').toUpperCase();
    if (value === 'TEXT') return 'VARCHAR';
    if (value === 'REAL' || value === 'NUMERIC') return 'FLOAT';
    return value;
  })();
  return types
    .map((t) => `<option value="${t}" ${normalizedType === t ? 'selected' : ''}>${t}</option>`)
    .join('');
}

function dbColumnSizeMarkup(column, tableIndex, colIndex) {
  const type = String(column?.type || '').toUpperCase();
  const size = column?.size;
  if (type === 'AUTO') {
    return `<input value="3" disabled style="width:58px; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 6px; opacity:0.6; box-sizing:border-box;">`;
  }
  if (type === 'VARCHAR') {
    return `<input data-db-input="col-size" data-table-index="${tableIndex}" data-col-index="${colIndex}" value="${escapeHtml(String(Number.isFinite(Number(size)) ? Number(size) : 50))}" style="width:58px; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 6px; box-sizing:border-box;">`;
  }
  if (type === 'INTEGER') {
    const options = [1, 2, 3, 4, 8].map((value) => `<option value="${value}" ${Number(size || 2) === value ? 'selected' : ''}>${value}</option>`).join('');
    return `<select data-db-input="col-size" data-table-index="${tableIndex}" data-col-index="${colIndex}" style="width:58px; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 6px; box-sizing:border-box;">${options}</select>`;
  }
  return '';
}

function dbInlineColumnSizeMarkup(column, tableIndex) {
  const type = String(column?.type || '').toUpperCase();
  const size = column?.size;
  if (type === 'AUTO') {
    return '<input value="3" disabled style="width:58px; margin:0; border-radius:0 10px 10px 0; border:1px solid var(--border); background:var(--bg); padding:4px 6px; opacity:0.6; box-sizing:border-box;">';
  }
  if (type === 'VARCHAR') {
    return `<input data-db-input="col-new-size" data-table-index="${tableIndex}" value="${escapeHtml(String(Number.isFinite(Number(size)) ? Number(size) : 50))}" style="width:58px; margin:0; border-radius:0 10px 10px 0; border:1px solid var(--border); background:var(--bg); padding:4px 6px; box-sizing:border-box;">`;
  }
  if (type === 'INTEGER') {
    const options = [1, 2, 3, 4, 8].map((value) => `<option value="${value}" ${Number(size || 2) === value ? 'selected' : ''}>${value}</option>`).join('');
    return `<select data-db-input="col-new-size" data-table-index="${tableIndex}" style="width:58px; margin:0; border-radius:0 10px 10px 0; border:1px solid var(--border); background:var(--bg); padding:4px 6px; box-sizing:border-box;">${options}</select>`;
  }
  return '';
}

function dbColumnDefaultPlaceholder(column) {
  const type = String(column?.type || '').toUpperCase();
  if (type === 'AUTO') return '';
  if (type === 'BOOLEAN') return '0 / 1';
  if (type === 'DATE') return 'YYYY-MM-DD';
  if (type === 'DATETIME') return 'YYYY-MM-DD HH:MM:SS';
  if (type === 'TIME') return 'HH:MM:SS';
  if (type === 'INTEGER') return '0';
  if (type === 'FLOAT') return '0.0';
  return '';
}

function sqlQuoteIdent(name) {
  return `"${String(name || '').replace(/"/g, '""')}"`;
}

function sqlQuoteValue(value) {
  if (value == null || value === '') return null;
  if (typeof value === 'number') return String(value);
  if (typeof value === 'boolean') return value ? '1' : '0';

  const text = String(value);
  if (/^-?\d+(\.\d+)?$/.test(text)) return text;
  if (/^(CURRENT_TIMESTAMP|CURRENT_DATE|CURRENT_TIME)$/i.test(text)) return text.toUpperCase();
  return `'${text.replace(/'/g, "''")}'`;
}

function generateDbExportSql(model, options = {}) {
  const includeRows = options.includeRows !== false;
  const normalized = DbModelSchema.normalizeDbModel(model).model;
  const blocks = [];

  for (const db of normalized.databases || []) {
    blocks.push(`-- ===== Datenbank: ${db.name} =====`);

    for (const table of db.tables || []) {
      const columnLines = [];
      const primaryKeys = [];
      const foreignKeys = [];

      for (const col of table.columns || []) {
        const type = String(col.type || 'VARCHAR').toUpperCase();
        const size = Number(col.size || 0);
        const typeSql = type === 'AUTO'
          ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
          : (type === 'VARCHAR' && Number.isFinite(size) && size > 0 ? `VARCHAR(${Math.floor(size)})` : type);
        const parts = [sqlQuoteIdent(col.name), typeSql];
        if (type !== 'AUTO' && !col.nullable) parts.push('NOT NULL');
        const defaultSql = sqlQuoteValue(col.default);
        if (type !== 'AUTO' && defaultSql !== null) parts.push(`DEFAULT ${defaultSql}`);
        columnLines.push(parts.join(' '));

        if (col.pk && type !== 'AUTO') {
          primaryKeys.push(sqlQuoteIdent(col.name));
        }

        if (col.fk && col.references?.table && col.references?.column) {
          const onUpdate = String(col.references.onUpdate || 'NO ACTION').toUpperCase();
          const onDelete = String(col.references.onDelete || 'NO ACTION').toUpperCase();
          foreignKeys.push(
            `FOREIGN KEY (${sqlQuoteIdent(col.name)}) REFERENCES ${sqlQuoteIdent(col.references.table)} (${sqlQuoteIdent(col.references.column)}) ON UPDATE ${onUpdate} ON DELETE ${onDelete}`
          );
        }
      }

      if (primaryKeys.length > 0) {
        columnLines.push(`PRIMARY KEY (${primaryKeys.join(', ')})`);
      }

      for (const fkLine of foreignKeys) {
        columnLines.push(fkLine);
      }

      blocks.push(`CREATE TABLE ${sqlQuoteIdent(table.name)} (\n  ${columnLines.join(',\n  ')}\n);`);

      const rows = includeRows && Array.isArray(table.rows) ? table.rows : [];
      if (rows.length > 0) {
        const insertCols = (table.columns || []).map((c) => c.name).filter(Boolean);
        if (insertCols.length > 0) {
          const colSql = insertCols.map(sqlQuoteIdent).join(', ');
          for (const row of rows) {
            const valuesSql = insertCols
              .map((colName) => sqlQuoteValue(row?.[colName]))
              .map((v) => (v == null ? 'NULL' : v))
              .join(', ');
            blocks.push(`INSERT INTO ${sqlQuoteIdent(table.name)} (${colSql}) VALUES (${valuesSql});`);
          }
        }
      }

      blocks.push('');
    }
  }

  return blocks.join('\n').trim() + '\n';
}

async function upsertProjectTextFile(projectId, fileName, content, mimeType) {
  const existing = await readProjectFileByName(projectId, fileName);
  if (existing?.fileId) {
    await persistProjectFileContent(existing.fileId, fileName, content);
    return existing.fileId;
  }

  const createRes = await fetch('../api/projects/files-v2.php?action=create', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      project_id: projectId,
      folder_id: null,
      name: fileName,
      content,
      mime_type: mimeType
    })
  });

  const createData = await createRes.json().catch(() => null);
  if (!createRes.ok || !createData?.ok) {
    throw new Error(createData?.error || `Datei ${fileName} konnte nicht angelegt werden`);
  }

  const created = await readProjectFileByName(projectId, fileName);
  return Number(created?.fileId || 0) || null;
}

async function saveDbDesignerStructure() {
  ensureDbDesignerStateExists();
  if (dbDesignerState.saveInFlight || !currentProject?.id) {
    return;
  }

  const diagnostics = buildDbDesignerDiagnostics(dbDesignerState.model);
  if (!diagnostics.ok) {
    dbDesignerState.statusMessage = `Speichern blockiert: ${diagnostics.errors.length} Validierungsfehler`;
    renderDbDesigner();
    return;
  }

  dbDesignerState.saveInFlight = true;
  try {
    const structureOnlyModel = stripRowsFromDbModel(dbDesignerState.model);
    const canonicalJson = DbModelSchema.stringifyDbModel(structureOnlyModel, true);
    const normalizedModel = DbModelSchema.normalizeDbModel(canonicalJson).model;
    const exportSql = generateDbExportSql(normalizedModel, { includeRows: false });

    const dbFileId = await upsertProjectTextFile(currentProject.id, DB_MODEL_FILE_NAME, canonicalJson, 'application/json');
    await upsertProjectTextFile(currentProject.id, DB_EXPORT_FILE_NAME, exportSql, 'application/sql');

    dbDesignerState.model = mergeDbDataPayloadIntoModel(normalizedModel, buildDbDataPayloadFromModel(dbDesignerState.model));
    dbDesignerState.dbFileId = dbFileId;
    dbDesignerState.dirtyStructure = false;
    dbDesignerState.dirty = Boolean(dbDesignerState.dirtyStructure || dbDesignerState.dirtyData);
    dbDesignerState.statusMessage = '';
    showDbDesignerToast('Entwurf gespeichert');
  } catch (error) {
    dbDesignerState.statusMessage = `Entwurf speichern fehlgeschlagen: ${error?.message || error}`;
    showDbDesignerToast(dbDesignerState.statusMessage);
  } finally {
    dbDesignerState.saveInFlight = false;
    renderDbDesigner();
  }
}

async function saveDbDesignerData() {
  ensureDbDesignerStateExists();
  if (dbDesignerState.saveInFlight || !currentProject?.id) {
    return;
  }

  dbDesignerState.saveInFlight = true;
  try {
    const dataPayload = buildDbDataPayloadFromModel(dbDesignerState.model);
    const dataJson = JSON.stringify(dataPayload, null, 2) + '\n';
    const exportSql = generateDbExportSql(dbDesignerState.model, { includeRows: true });

    await upsertProjectTextFile(currentProject.id, DB_DATA_FILE_NAME, dataJson, 'application/json');
    await upsertProjectTextFile(currentProject.id, DB_EXPORT_FILE_NAME, exportSql, 'application/sql');

    dbDesignerState.dirtyData = false;
    dbDesignerState.dirty = Boolean(dbDesignerState.dirtyStructure || dbDesignerState.dirtyData);
    dbDesignerState.statusMessage = '';
    showDbDesignerToast('Daten gespeichert');
  } catch (error) {
    dbDesignerState.statusMessage = `Daten speichern fehlgeschlagen: ${error?.message || error}`;
    showDbDesignerToast(dbDesignerState.statusMessage);
  } finally {
    dbDesignerState.saveInFlight = false;
    renderDbDesigner();
  }
}

async function discardDbDesignerChanges() {
  if (!currentProject?.id) {
    return;
  }

  dbDesignerState.statusMessage = 'Aenderungen werden verworfen...';
  renderDbDesigner();

  try {
    await initializeDbMode(currentProject);
    if (dbDesignerState) {
      dbDesignerState.statusMessage = 'Aenderungen verworfen';
      renderDbDesigner();
    }
  } catch (error) {
    dbDesignerState.statusMessage = `Verwerfen fehlgeschlagen: ${error?.message || error}`;
    renderDbDesigner();
  }
}

function undoDbDesignerChange() {
  ensureDbDesignerStateExists();
  if (dbDesignerState.historyIndex <= 0) return;
  dbDesignerState.historyIndex -= 1;
  const snapshot = dbDesignerState.history[dbDesignerState.historyIndex];
  const normalized = DbModelSchema.normalizeDbModel(snapshot);
  dbDesignerState.model = normalized.model;
  dbDesignerState.dirtyStructure = true;
  dbDesignerState.dirty = Boolean(dbDesignerState.dirtyStructure || dbDesignerState.dirtyData);
  dbDesignerState.statusMessage = 'Undo ausgefuehrt';
  renderDbDesigner();
}

function redoDbDesignerChange() {
  ensureDbDesignerStateExists();
  if (dbDesignerState.historyIndex >= dbDesignerState.history.length - 1) return;
  dbDesignerState.historyIndex += 1;
  const snapshot = dbDesignerState.history[dbDesignerState.historyIndex];
  const normalized = DbModelSchema.normalizeDbModel(snapshot);
  dbDesignerState.model = normalized.model;
  dbDesignerState.dirtyStructure = true;
  dbDesignerState.dirty = Boolean(dbDesignerState.dirtyStructure || dbDesignerState.dirtyData);
  dbDesignerState.statusMessage = 'Redo ausgefuehrt';
  renderDbDesigner();
}

async function executeDbSqlQuery() {
  ensureDbDesignerStateExists();
  if (!currentProject?.id) {
    return;
  }

  const sqlInput = document.querySelector('textarea[data-db-input="sql-text"]');
  const queryText = String(((sqlInput?.value ?? dbDesignerState.sqlText) || '')).trim();
  dbDesignerState.sqlText = queryText;
  if (!queryText) {
    dbDesignerState.statusMessage = 'Bitte zuerst eine SQL-Abfrage eingeben.';
    dbDesignerState.sqlQueryResult = null;
    dbDesignerState.sqlLogEntries = [{ type: 'error', message: 'Keine SQL-Abfrage eingegeben.' }];
    renderDbDesigner();
    return;
  }

  dbDesignerState.sqlRunInFlight = true;
  dbDesignerState.statusMessage = '';
  dbDesignerState.sqlLogEntries = [
    ...(dbDesignerState.sqlLogEntries || []),
    { type: 'success', message: `SQL gestartet: ${queryText.split(/\s+/).slice(0, 6).join(' ')}${queryText.split(/\s+/).length > 6 ? '…' : ''}` }
  ].slice(-20);
  try {
    renderDbDesigner();
  } catch (renderErr) {
    console.error('SQL pre-run render failed:', renderErr);
  }

  try {
    const localExec = executeDbSqlQueryLocal(queryText, dbDesignerState.model);
    dbDesignerState.sqlQueryResult = localExec.result;
    if (localExec.changedData) {
      setDbDesignerModel(localExec.model, { statusMessage: '', scope: 'data' });
    }
    dbDesignerState.sqlLogEntries = [
      ...(dbDesignerState.sqlLogEntries || []),
      { type: 'success', message: `${dbDesignerState.sqlQueryResult.message || 'SQL erfolgreich ausgefuehrt.'}` }
    ].slice(-20);
  } catch (error) {
    const message = String(error?.message || error || 'SQL-Ausfuehrung fehlgeschlagen');
    dbDesignerState.sqlQueryResult = {
      ok: false,
      message
    };
    dbDesignerState.sqlLogEntries = [
      ...(dbDesignerState.sqlLogEntries || []),
      { type: 'error', message: `Fehler: ${message}` }
    ].slice(-20);
  } finally {
    dbDesignerState.sqlRunInFlight = false;
    try {
      renderDbDesigner();
    } catch (renderErr) {
      console.error('SQL post-run render failed:', renderErr);
    }
  }
}

function renderDbDesigner() {
  const sqlContainer = document.getElementById('gui-container');
  const treeContainer = document.getElementById('db-structure-tree');
  const designContainer = document.getElementById('db-design-container');
  const dataContainer = document.getElementById('db-data-container');
  if (!sqlContainer || !treeContainer || !designContainer || !dataContainer) return;
  hideDbTreeContextMenu();

  ensureDbDesignerUiStyles();
  ensureDbDesignerStateExists();
  const model = dbDesignerState.model;
  const diagnostics = buildDbDesignerDiagnostics(model);
  const stats = countDbModelEntities(model);
  const activeDb = getActiveDbEntry();
  const activeDbIndex = Number(model.activeDatabaseIndex || 0);
  ensureSelectedDbTableIndex();
  const selectedTableIndex = Number(dbDesignerState.selectedTableIndex || 0);
  const selectedTable = Array.isArray(activeDb?.tables) ? activeDb.tables[selectedTableIndex] : null;

  const dbOptions = (model.databases || []).map((db, idx) => (
    `<option value="${idx}" ${idx === activeDbIndex ? 'selected' : ''}>${escapeHtml(db.name || `DB ${idx + 1}`)}</option>`
  )).join('');

  const treeMarkup = activeDb
    ? (activeDb.tables || []).map((table, tableIndex) => {
        const tableHasError = diagnostics.tableErrorKeys.has(String(tableIndex));
        const tableHasWarning = diagnostics.tableWarningKeys.has(String(tableIndex));
        const icon = tableHasError ? '⚠️' : (tableHasWarning ? '⚑' : '📄');
        return `<div class="db-tree-item ${tableIndex === selectedTableIndex ? 'active' : ''}" data-db-action="select-table" data-table-index="${tableIndex}">${icon} ${escapeHtml(String(table.name || `Tabelle ${tableIndex + 1}`))}</div>`;
      }).join('')
    : '<p style="padding:8px; color:#666; margin:0;">Keine Tabellen vorhanden.</p>';

  treeContainer.innerHTML = treeMarkup;

  const selectedTableColumns = Array.isArray(selectedTable?.columns) ? selectedTable.columns : [];
  const inlineColumnDraft = getDbInlineDraftColumn(model, selectedTableIndex);
  const designToastMarkup = dbDesignerState.toastMessage
    ? `<div class="db-designer-toast">${escapeHtml(String(dbDesignerState.toastMessage || ''))}</div>`
    : '';
  const inlineColumnIsAuto = String(inlineColumnDraft?.type || '').toUpperCase() === 'AUTO';
  const inlineColumnDefaultPlaceholder = dbColumnDefaultPlaceholder(inlineColumnDraft);
  const selectedColumnRows = selectedTableColumns.map((col, colIndex) => {
    const colKey = `${selectedTableIndex}:${colIndex}`;
    const colHasError = diagnostics.columnErrorKeys.has(colKey);
    const colHasWarning = diagnostics.columnWarningKeys.has(colKey);
    const isAuto = String(col.type || '').toUpperCase() === 'AUTO';
    const defaultPlaceholder = dbColumnDefaultPlaceholder(col);
    return `
      <tr style="background:${colHasError ? '#fff1f2' : (colHasWarning ? '#fffbeb' : 'transparent')};">
        <td style="padding:0 1px; text-align:center;"><input type="checkbox" data-db-input="col-pk" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" ${col.pk ? 'checked' : ''} ${isAuto ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td style="padding:0 1px; text-align:center;"><input type="checkbox" data-db-input="col-fk" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" ${col.fk ? 'checked' : ''} ${isAuto ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td style="padding:0 1px; text-align:center;"><input type="checkbox" data-db-input="col-nullable" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" ${col.nullable ? 'checked' : ''} ${isAuto ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td style="padding:0 2px;"><input data-db-input="col-name" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" value="${escapeHtml(String(col.name || ''))}" style="width:clamp(140px, 24vw, 320px); margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 8px; box-sizing:border-box; box-shadow:0 1px 2px rgba(0,0,0,0.03);"></td>
        <td style="padding:0 1px;"><div style="display:flex; align-items:center; gap:0; flex-wrap:nowrap;"><select data-db-input="col-type" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" style="width:102px; margin:0; border-radius:10px 0 0 10px; border:1px solid var(--border); border-right:none; background:var(--bg); padding:4px 6px; box-sizing:border-box; box-shadow:0 1px 2px rgba(0,0,0,0.03);">${dbTypeOptionsMarkup(col.type)}</select>${dbColumnSizeMarkup(col, selectedTableIndex, colIndex)}</div></td>
        <td style="padding:0 1px;"><input data-db-input="col-default" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" value="${escapeHtml(String(col.default ?? ''))}" placeholder="${escapeHtml(defaultPlaceholder)}" ${isAuto ? 'disabled' : ''} style="width:56px !important; min-width:56px !important; max-width:56px !important; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:4px 6px; box-sizing:border-box; box-shadow:0 1px 2px rgba(0,0,0,0.03); opacity:${isAuto ? '0.55' : '1'};"></td>
        <td style="padding:0 1px; text-align:center;"><button data-db-action="delete-column" data-table-index="${selectedTableIndex}" data-col-index="${colIndex}" title="Löschen" aria-label="Löschen" style="width:32px; height:32px; min-width:32px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid #fecaca; background:#fff1f2; box-shadow:0 1px 2px rgba(0,0,0,0.04);"><span style="color:#dc2626; font-size:15px; line-height:1; font-weight:700;">✕</span></button></td>
      </tr>
    `;
  }).join('');
  const inlineColumnInsertRow = selectedTable ? `
      <tr style="background:#f0fdf4;">
        <td style="padding:0 1px; text-align:center;"><input type="checkbox" data-db-input="col-new-pk" data-table-index="${selectedTableIndex}" ${inlineColumnDraft.pk ? 'checked' : ''} ${inlineColumnIsAuto ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td style="padding:0 1px; text-align:center;"><input type="checkbox" data-db-input="col-new-fk" data-table-index="${selectedTableIndex}" ${inlineColumnDraft.fk ? 'checked' : ''} ${inlineColumnIsAuto ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td style="padding:0 1px; text-align:center;"><input type="checkbox" data-db-input="col-new-nullable" data-table-index="${selectedTableIndex}" ${inlineColumnDraft.nullable ? 'checked' : ''} ${inlineColumnIsAuto ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td style="padding:0 2px;"><input data-db-input="col-new-name" data-table-index="${selectedTableIndex}" value="${escapeHtml(String(inlineColumnDraft.name || ''))}" placeholder="neu..." style="width:clamp(140px, 24vw, 320px); margin:0; border-radius:10px; border:1px dashed #86efac; background:#ffffff; padding:5px 8px; box-sizing:border-box;"></td>
        <td style="padding:0 1px;"><div style="display:flex; align-items:center; gap:0; flex-wrap:nowrap;"><select data-db-input="col-new-type" data-table-index="${selectedTableIndex}" style="width:102px; margin:0; border-radius:10px 0 0 10px; border:1px solid var(--border); border-right:none; background:#ffffff; padding:4px 6px; box-sizing:border-box;">${dbTypeOptionsMarkup(inlineColumnDraft.type)}</select>${dbInlineColumnSizeMarkup(inlineColumnDraft, selectedTableIndex)}</div></td>
        <td style="padding:0 1px;"><input data-db-input="col-new-default" data-table-index="${selectedTableIndex}" value="${escapeHtml(String(inlineColumnDraft.default ?? ''))}" placeholder="${escapeHtml(inlineColumnDefaultPlaceholder)}" ${inlineColumnIsAuto ? 'disabled' : ''} style="width:56px !important; min-width:56px !important; max-width:56px !important; margin:0; border-radius:10px; border:1px dashed #86efac; background:#ffffff; padding:4px 6px; box-sizing:border-box; opacity:${inlineColumnIsAuto ? '0.55' : '1'};"></td>
        <td style="padding:0 1px; text-align:center;"><button data-db-action="add-column-inline" data-table-index="${selectedTableIndex}" title="Feld einfuegen" aria-label="Feld einfuegen" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; min-width:32px; border:1px solid #93c5fd; background:#2563eb; color:#ffffff; border-radius:10px; padding:0; font-size:18px; font-weight:700; box-shadow:0 1px 2px rgba(0,0,0,0.06);">+</button></td>
      </tr>
    ` : '';
  designContainer.innerHTML = [
    '<div style="color:#222; display:flex; flex-direction:column; gap:8px;">',
    '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between;">',
    `<div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; font-size:16px; font-weight:700; line-height:1.1;"><span>Entwurf:</span><span style="min-width:220px; max-width:360px; font-size:16px; font-weight:700; color:#2563eb; padding:6px 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(String(selectedTable?.name || ''))}</span></div>`,
    '<div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">',
    '<button data-db-action="save-structure" title="Entwurf speichern" aria-label="Entwurf speichern" style="width:34px; height:34px; min-width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--border); background:var(--bg); box-shadow:0 1px 2px rgba(0,0,0,0.04);">💾</button>',
    '<button data-db-action="discard" title="Aenderungen verwerfen" aria-label="Aenderungen verwerfen" style="width:34px; height:34px; min-width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--border); background:var(--bg); box-shadow:0 1px 2px rgba(0,0,0,0.04);">↺</button>',
    '<button data-db-action="undo" title="Undo" aria-label="Undo" style="width:34px; height:34px; min-width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--border); background:var(--bg); box-shadow:0 1px 2px rgba(0,0,0,0.04);">↶</button>',
    '<button data-db-action="redo" title="Redo" aria-label="Redo" style="width:34px; height:34px; min-width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--border); background:var(--bg); box-shadow:0 1px 2px rgba(0,0,0,0.04);">↷</button>',
    selectedTable ? `<button data-db-action="delete-table" data-table-index="${selectedTableIndex}" title="Tabelle loeschen" aria-label="Tabelle loeschen" style="width:34px; height:34px; min-width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--border); background:var(--bg); box-shadow:0 1px 2px rgba(0,0,0,0.04);">🗑</button>` : '',
    '<button data-db-action="add-table" title="Neue Tabelle" aria-label="Neue Tabelle" style="width:34px; height:34px; min-width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid #bfdbfe; background:#eff6ff; color:#2563eb; box-shadow:0 1px 2px rgba(0,0,0,0.04);">＋</button>',
    '</div>',
    '</div>',
    selectedTable ? '<div style="overflow:auto; max-height: calc(100% - 118px);">' : '<div>',
    selectedTable ? `
      <table style="border-collapse:separate; border-spacing:0 1px; width:100%; min-width:0; table-layout:fixed; font-size:12px;">
        <thead>
          <tr>
            <th style="text-align:left; padding:0 1px 1px 1px; width:28px;">PK</th>
            <th style="text-align:left; padding:0 1px 1px 1px; width:28px;">FK</th>
            <th style="text-align:left; padding:0 1px 1px 1px; width:34px;">Null</th>
            <th style="text-align:left; padding:0 4px 1px 4px; width:auto;">Name</th>
            <th style="text-align:left; padding:0 2px 1px 2px; width:164px;">Typ</th>
            <th style="text-align:left; padding:0 2px 1px 2px; width:64px;">Default</th>
            <th style="text-align:left; padding:0 2px 1px 2px; width:40px;">Del</th>
          </tr>
        </thead>
        <tbody>
          ${selectedColumnRows || '<tr><td colspan="7" style="padding:8px; color:#666;">Keine Spalten vorhanden</td></tr>'}
          ${inlineColumnInsertRow}
        </tbody>
      </table>
    ` : '<p style="margin:0; color:#666;">Waehle links eine Tabelle aus.</p>',
    '</div>',
    designToastMarkup ? `<div style="margin-top:4px;">${designToastMarkup}</div>` : ''
  ].join('');

  const columns = selectedTableColumns;
  const rows = Array.isArray(selectedTable?.rows) ? selectedTable.rows : [];
  const inlineDraft = getDbInlineDraftRow(model, selectedTableIndex);
  const filterQuery = String(dbDesignerState.dataFilter || '').trim().toLowerCase();
  const sortCol = String(dbDesignerState.dataSortColumn || '').trim();
  const sortDir = String(dbDesignerState.dataSortDirection || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';

  const gridRows = rows.map((row, idx) => ({ row, idx }));
  const filteredRows = filterQuery
    ? gridRows.filter(({ row }) => columns.some((col) => String(row?.[String(col?.name || '')] ?? '').toLowerCase().includes(filterQuery)))
    : gridRows;
  const sortedRows = [...filteredRows];
  if (sortCol) {
    sortedRows.sort((a, b) => {
      const av = a.row?.[sortCol] ?? '';
      const bv = b.row?.[sortCol] ?? '';
      const cmp = String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' });
      return sortDir === 'DESC' ? -cmp : cmp;
    });
  }

  const dataHeader = columns.map((c) => {
    const colName = String(c.name || '');
    const active = sortCol === colName;
    const arrow = active ? (sortDir === 'DESC' ? ' ▼' : ' ▲') : '';
    return `<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px 6px;"><button data-db-action="sort-data" data-col-name="${escapeHtml(colName)}" style="border:none; background:transparent; cursor:pointer; padding:0; color:inherit; font:inherit;">${escapeHtml(colName)}${arrow}</button></th>`;
  }).join('');
  const rowCells = sortedRows.map(({ row, idx: rowIndex }) => {
    const rowDirty = hasDbPendingRowEdits(selectedTableIndex, rowIndex);
    const cells = columns.map((col) => {
      const colName = String(col.name || '');
      const pendingValue = getDbPendingDataRowEdits(selectedTableIndex, rowIndex)[colName];
      const value = pendingValue !== undefined ? pendingValue : (row?.[colName] ?? '');
      const isAutoColumn = String(col?.type || '').toUpperCase() === 'AUTO';
      return `<td style="padding:2px;"><input class="db-data-grid-input" data-db-input="row-value" data-db-grid-cell="1" data-table-index="${selectedTableIndex}" data-row-index="${rowIndex}" data-col-index="${columns.indexOf(col)}" data-col-name="${escapeHtml(colName)}" value="${escapeHtml(String(value ?? ''))}" placeholder="Wert oder NULL" ${isAutoColumn ? 'readonly' : ''} style="${isAutoColumn ? 'background:#f8fafc; color:#64748b;' : ''}"></td>`;
    }).join('');
    const rowActions = rowDirty
      ? `<span style="display:inline-flex; gap:2px; align-items:center;"><button data-db-action="commit-row-edit" data-table-index="${selectedTableIndex}" data-row-index="${rowIndex}" title="Zeile speichern" aria-label="Zeile speichern" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; padding:0;">✓</button><button data-db-action="discard-row-edit" data-table-index="${selectedTableIndex}" data-row-index="${rowIndex}" title="Änderungen verwerfen" aria-label="Änderungen verwerfen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #fecaca; background:#fff1f2; color:#b42318; border-radius:6px; padding:0;">⛔</button></span>`
      : '';
    return `<tr class="db-data-grid-row ${rowDirty ? 'db-data-grid-row-dirty' : ''}"><td style="padding:4px 6px; color:#666; border-right:1px solid #eee;">${rowIndex + 1}</td>${cells}<td style="padding:2px; display:flex; gap:2px; align-items:center;">${rowActions}<button data-db-action="duplicate-row" data-table-index="${selectedTableIndex}" data-row-index="${rowIndex}" title="Zeile duplizieren" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">⧉</button><button data-db-action="delete-row" data-table-index="${selectedTableIndex}" data-row-index="${rowIndex}" title="Zeile loeschen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">-</button></td></tr>`;
  }).join('');

  const inlineRowCells = columns.map((col, colIndex) => {
    const colName = String(col.name || '');
    const pendingDraftValue = inlineDraft[colName];
    const draftValue = pendingDraftValue !== undefined ? pendingDraftValue : '';
    const isAutoColumn = String(col?.type || '').toUpperCase() === 'AUTO';
    return `<td style="padding:2px;"><input class="db-data-grid-input" data-db-input="row-new-value" data-db-grid-cell="1" data-table-index="${selectedTableIndex}" data-row-index="${rows.length}" data-col-index="${colIndex}" data-col-name="${escapeHtml(colName)}" value="${escapeHtml(String(draftValue ?? ''))}" placeholder="neu..." ${isAutoColumn ? 'readonly' : ''} style="${isAutoColumn ? 'background:#f8fafc; color:#64748b;' : 'border:1px dashed #c9ced6; background:#fbfdff;'}"></td>`;
  }).join('');
  const inlineInsertRow = selectedTable
    ? `<tr class="db-data-grid-row"><td style="padding:4px 6px; color:#3b82f6; border-right:1px solid #eee;">+</td>${inlineRowCells}<td style="padding:2px;"><button data-db-action="add-row-inline" data-table-index="${selectedTableIndex}" title="Zeile einfuegen" style="border:1px solid #bfdbfe; background:#eff6ff; color:#2563eb; border-radius:6px; width:26px; height:26px; line-height:1; font-weight:700;">+</button></td></tr>`
    : '';

  dataContainer.innerHTML = [
    '<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">',
    `<strong>Daten (${selectedTable ? escapeHtml(String(selectedTable.name || 'Tabelle')) : 'keine Tabelle'})</strong>`,
    `<input data-db-input="data-filter" value="${escapeHtml(String(dbDesignerState.dataFilter || ''))}" placeholder="Filter..." style="min-width:180px; max-width:240px;">`,
    selectedTable ? `<button data-db-action="add-row" data-table-index="${selectedTableIndex}" style="border:1px solid #bfdbfe; background:#eff6ff; color:#2563eb; border-radius:6px; font-weight:600;">+ Zeile</button>` : '',
    '</div>',
    selectedTable
      ? `<div style="overflow:auto; max-height:100%;"><table style="border-collapse:collapse; width:100%; min-width:560px; font-size:12px;"><thead><tr><th style="text-align:left; border-bottom:1px solid #ddd; padding:4px 6px; width:42px;">#</th>${dataHeader}<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px 6px; width:120px;">Aktion</th></tr></thead><tbody>${rowCells || `<tr><td colspan="${columns.length + 2}" style="padding:8px; color:#666;">Keine Daten vorhanden.</td></tr>`}${inlineInsertRow}</tbody></table></div><div style="margin-top:6px; color:#666; font-size:11px;">Tipp: In der Inline-Zeile Enter druecken oder + klicken, um direkt einzufuegen.</div>`
      : '<p style="margin:0; color:#666;">Waehle links eine Tabelle aus, um Daten zu bearbeiten.</p>'
  ].join('');

  const sqlPermissions = getSqlEditorPermissions(currentProject);
  const sqlValue = String(dbDesignerState.sqlText || '');
  const sqlResult = dbDesignerState.sqlQueryResult;
  const sqlResultRows = Array.isArray(sqlResult?.rows) ? sqlResult.rows : [];
  const sqlLogEntries = Array.isArray(dbDesignerState.sqlLogEntries) ? dbDesignerState.sqlLogEntries : [];
  const sqlResultCols = Array.isArray(sqlResult?.columns) ? sqlResult.columns : [];
  const sqlResultTableHead = sqlResultCols
    .map((col) => `<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px 6px;">${escapeHtml(String(col || ''))}</th>`)
    .join('');
  const sqlResultTableRows = sqlResultRows.map((row) => {
    const cells = sqlResultCols.map((col) => {
      const value = row?.[col];
      return `<td style="padding:4px 6px; border-bottom:1px solid #f1f1f1;">${escapeHtml(String(value ?? ''))}</td>`;
    }).join('');
    return `<tr>${cells}</tr>`;
  }).join('');
  const latestSqlLogEntry = sqlLogEntries.length ? sqlLogEntries[sqlLogEntries.length - 1] : null;
  const latestSqlLogMarkup = latestSqlLogEntry
    ? `<div class="db-sql-log-item ${latestSqlLogEntry.type === 'error' ? 'error' : (latestSqlLogEntry.type === 'success' ? 'success' : '')}">${escapeHtml(String(latestSqlLogEntry.message || ''))}</div>`
    : '<div class="db-sql-log-item">Noch keine SQL-Protokoll-Einträge.</div>';
  sqlContainer.innerHTML = [
    '<div style="display:flex; flex-direction:column; height:100%; min-height:0; overflow:hidden;">',
    '<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; position:sticky; top:0; background:var(--bg); z-index:2; padding-bottom:4px; flex-wrap:wrap;">',
    '<div style="font-weight:600; white-space:nowrap;">SQL Editor</div>',
    `<div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-left:0;">${sqlPermissions.allowRun ? '<button data-db-action="execute-sql" ' + (dbDesignerState.sqlRunInFlight ? 'disabled' : '') + ' title="SQL ausfuehren" aria-label="SQL ausfuehren" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; min-width:30px; border:1px solid #16a34a; background:#ecfdf3; color:#166534; border-radius:8px; padding:0; font-size:14px; line-height:1; box-shadow:0 1px 2px rgba(0,0,0,0.04);">▶</button>' : ''}${sqlPermissions.allowImport ? '<button data-db-action="import-sql-snippet" title="SQL-Snippet laden" aria-label="SQL-Snippet laden" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; min-width:30px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:#2563eb; font-size:14px; line-height:1; box-shadow:0 1px 2px rgba(0,0,0,0.04);">⇩</button>' : ''}${sqlPermissions.allowExport ? '<button data-db-action="export-sql-snippet" title="SQL-Snippet exportieren" aria-label="SQL-Snippet exportieren" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; min-width:30px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:#2563eb; font-size:14px; line-height:1; box-shadow:0 1px 2px rgba(0,0,0,0.04);">⇧</button>' : ''}</div>`,
    '</div>',
    `<textarea data-db-input="sql-text" placeholder="SELECT ..." style="width:100%; min-height:110px; max-height:55%; resize:vertical; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; background:var(--bg); color:var(--text-primary); border:1px solid var(--border); border-radius:6px; padding:8px;">${escapeHtml(sqlValue)}</textarea>`,
    '<div style="margin-top:8px; font-size:12px; color:var(--text-secondary);">Abfrageergebnis</div>',
    sqlResult
      ? (sqlResult.ok
          ? (sqlResult.mode === 'result-set'
              ? `<div style="overflow:auto; margin-top:4px; border:1px solid var(--border); border-radius:6px; max-height:42%;"><table style="border-collapse:collapse; width:100%; font-size:12px;"><thead><tr>${sqlResultTableHead || '<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px 6px;">Ergebnis</th>'}</tr></thead><tbody>${sqlResultTableRows || `<tr><td style="padding:6px; color:#666;">0 Zeilen</td></tr>`}</tbody></table></div>`
              : `<div style="margin-top:6px; padding:8px; border:1px solid var(--border); border-radius:6px; color:#1a7f37;">${escapeHtml(String(sqlResult.message || 'OK'))}</div>`)
          : `<div style="margin-top:6px; padding:8px; border:1px solid #fda4af; border-radius:6px; color:#b42318; background:#fff1f2;">${escapeHtml(String(sqlResult.message || 'Fehler'))}</div>`)
      : '<div style="margin-top:6px; padding:8px; border:1px dashed var(--border); border-radius:6px; color:#666;">Noch keine SQL-Abfrage ausgefuehrt.</div>',
    '<div class="db-sql-log" style="margin-top:8px;">',
    `<div class="db-sql-log-list">${latestSqlLogMarkup}</div>`,
    '</div>',
    '</div>'
  ].join('');
}

function handleDbDesignerAction(action, buttonEl) {
  ensureDbDesignerStateExists();
  const model = cloneDbModel(dbDesignerState.model);
  const activeDbIndex = Number(model.activeDatabaseIndex || 0);
  const activeDb = model.databases?.[activeDbIndex];
  const sqlPermissions = getSqlEditorPermissions(currentProject);

  if (action === 'save-structure') {
    saveDbDesignerStructure();
    return;
  }
  if (action === 'save-data') {
    saveDbDesignerData();
    return;
  }
  if (action === 'undo') {
    undoDbDesignerChange();
    return;
  }
  if (action === 'redo') {
    redoDbDesignerChange();
    return;
  }
  if (action === 'discard') {
    discardDbDesignerChanges();
    return;
  }
  if (action === 'execute-sql') {
    if (!sqlPermissions.allowRun) {
      dbDesignerState.statusMessage = 'Ausführung ist für diesen Kontext deaktiviert.';
      renderDbDesigner();
      return;
    }
    executeDbSqlQuery();
    return;
  }
  if (action === 'import-sql-snippet') {
    if (!sqlPermissions.allowImport) {
      dbDesignerState.statusMessage = 'Import von SQL-Snippets ist für diesen Kontext deaktiviert.';
      renderDbDesigner();
      return;
    }
    ensureSqlSnippetImportInput().click();
    return;
  }
  if (action === 'export-sql-snippet') {
    if (!sqlPermissions.allowExport) {
      dbDesignerState.statusMessage = 'Export von SQL-Snippets ist für diesen Kontext deaktiviert.';
      renderDbDesigner();
      return;
    }

    const content = String(dbDesignerState.sqlText || '');
    if (!content.trim()) {
      dbDesignerState.statusMessage = 'Keine SQL-Abfrage zum Exportieren vorhanden.';
      renderDbDesigner();
      return;
    }

    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sql-snippet-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.sql`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);

    dbDesignerState.statusMessage = 'SQL-Snippet exportiert.';
    renderDbDesigner();
    return;
  }
  if (action === 'toggle-diagnostics') {
    dbDesignerState.showDiagnostics = !dbDesignerState.showDiagnostics;
    renderDbDesigner();
    return;
  }
  if (action === 'sort-data') {
    const colName = String(buttonEl?.getAttribute('data-col-name') || '').trim();
    if (!colName) return;
    if (dbDesignerState.dataSortColumn === colName) {
      dbDesignerState.dataSortDirection = dbDesignerState.dataSortDirection === 'ASC' ? 'DESC' : 'ASC';
    } else {
      dbDesignerState.dataSortColumn = colName;
      dbDesignerState.dataSortDirection = 'ASC';
    }
    renderDbDesigner();
    return;
  }
  if (action === 'focus-issue') {
    const tableIndex = Number(buttonEl?.getAttribute('data-table-index') || -1);
    const rawCol = String(buttonEl?.getAttribute('data-col-index') || '').trim();
    const colIndex = rawCol === '' ? -1 : Number(rawCol);
    if (tableIndex >= 0) {
      dbDesignerState.selectedTableIndex = tableIndex;
      renderDbDesigner();
    }
    focusDbDesignerIssue(tableIndex, Number.isFinite(colIndex) ? colIndex : -1);
    return;
  }
  if (action === 'select-table') {
    const tableIndex = Number(buttonEl?.getAttribute('data-table-index') || -1);
    if (tableIndex >= 0) {
      dbDesignerState.selectedTableIndex = tableIndex;
      dbDesignerState.statusMessage = '';
      renderDbDesigner();
    }
    return;
  }
  if (action === 'add-table') {
    if (!activeDb) return;
    activeDb.tables = Array.isArray(activeDb.tables) ? activeDb.tables : [];
    activeDb.tables.push(createDefaultDbTable());
    dbDesignerState.selectedTableIndex = activeDb.tables.length - 1;
    setDbDesignerModel(model, { statusMessage: 'Neue Tabelle hinzugefuegt', scope: 'structure' });
    renderDbDesigner();
    return;
  }
  if (action === 'context-add-table') {
    if (!activeDb) return;
    activeDb.tables = Array.isArray(activeDb.tables) ? activeDb.tables : [];
    activeDb.tables.push(createDefaultDbTable());
    dbDesignerState.selectedTableIndex = activeDb.tables.length - 1;
    setDbDesignerModel(model, { statusMessage: 'Neue Tabelle hinzugefuegt', scope: 'structure' });
    renderDbDesigner();
    return;
  }
  if (action === 'delete-selected-table') {
    if (!activeDb || !Array.isArray(activeDb.tables) || !activeDb.tables.length) return;
    const selectedIndex = Number(dbDesignerState.selectedTableIndex || 0);
    if (selectedIndex < 0 || selectedIndex >= activeDb.tables.length) return;
    activeDb.tables.splice(selectedIndex, 1);
    dbDesignerState.selectedTableIndex = Math.max(0, selectedIndex - 1);
    setDbDesignerModel(model, { statusMessage: 'Tabelle geloescht', scope: 'structure' });
    renderDbDesigner();
    return;
  }

  const tableIndex = Number(buttonEl?.getAttribute('data-table-index') || -1);
  const colIndex = Number(buttonEl?.getAttribute('data-col-index') || -1);
  const table = activeDb?.tables?.[tableIndex];

  if (action === 'context-rename-table' && table) {
    const currentName = String(table?.name || '').trim() || `Tabelle ${tableIndex + 1}`;
    const renamed = window.prompt('Neuer Tabellenname:', currentName);
    if (renamed == null) return;
    const nextName = String(renamed || '').trim();
    if (!nextName) {
      dbDesignerState.statusMessage = 'Tabellenname darf nicht leer sein.';
      renderDbDesigner();
      return;
    }
    table.name = nextName;
    setDbDesignerModel(model, { statusMessage: 'Tabelle umbenannt', scope: 'structure' });
    renderDbDesigner();
    return;
  }

  if (action === 'context-clear-table-data' && table) {
    table.rows = [];
    setDbDesignerModel(model, { statusMessage: 'Tabellendaten geloescht', scope: 'data' });
    renderDbDesigner();
    return;
  }

  if (action === 'context-duplicate-table' && table) {
    if (!activeDb) return;
    activeDb.tables = Array.isArray(activeDb.tables) ? activeDb.tables : [];
    const clone = JSON.parse(JSON.stringify(table));
    clone.name = findNextDuplicatedTableName(activeDb, table.name);
    activeDb.tables.splice(tableIndex + 1, 0, clone);
    dbDesignerState.selectedTableIndex = tableIndex + 1;
    setDbDesignerModel(model, { statusMessage: 'Tabelle dupliziert', scope: 'structure' });
    renderDbDesigner();
    return;
  }

  if (action === 'context-delete-table' && table) {
    activeDb.tables.splice(tableIndex, 1);
    dbDesignerState.selectedTableIndex = Math.max(0, tableIndex - 1);
    setDbDesignerModel(model, { statusMessage: 'Tabelle geloescht', scope: 'structure' });
    renderDbDesigner();
    return;
  }

  if (action === 'delete-table' && table) {
    activeDb.tables.splice(tableIndex, 1);
    dbDesignerState.selectedTableIndex = Math.max(0, tableIndex - 1);
    setDbDesignerModel(model, { statusMessage: 'Tabelle geloescht', scope: 'structure' });
    renderDbDesigner();
    return;
  }
  if (action === 'add-column' && table) {
    table.columns = Array.isArray(table.columns) ? table.columns : [];
    table.columns.push(createDefaultDbColumn());
    setDbDesignerModel(model, { statusMessage: 'Spalte hinzugefuegt', scope: 'structure' });
    renderDbDesigner();
    return;
  }
  if (action === 'add-column-inline' && table) {
    table.columns = Array.isArray(table.columns) ? table.columns : [];
    const draft = getDbInlineDraftColumn(model, tableIndex);
    const name = String(draft?.name || '').trim();

    if (!name) {
      dbDesignerState.statusMessage = 'Inline-Feld ist leer.';
      renderDbDesigner();
      return;
    }

    const col = createDefaultDbColumn();
    col.name = name;
    col.type = String(draft?.type || 'VARCHAR').toUpperCase();
    col.default = String(draft?.default ?? '');

    if (col.type === 'AUTO') {
      col.size = 3;
      col.pk = true;
      col.fk = false;
      col.nullable = false;
      col.references = null;
    } else {
      const draftSize = Number(draft?.size);
      if (col.type === 'VARCHAR') {
        col.size = Number.isFinite(draftSize) && draftSize > 0 ? Math.floor(draftSize) : 50;
      } else if (col.type === 'INTEGER') {
        col.size = [1, 2, 3, 4, 8].includes(Math.floor(draftSize)) ? Math.floor(draftSize) : 2;
      } else {
        col.size = null;
      }
      col.pk = Boolean(draft?.pk);
      col.fk = Boolean(draft?.fk);
      col.nullable = col.pk ? false : Boolean(draft?.nullable);
      col.references = col.fk ? { table: '', column: '', onUpdate: 'NO ACTION', onDelete: 'NO ACTION' } : null;
    }

    table.columns.push(col);
    clearDbInlineDraftColumn(model, tableIndex);
    setDbDesignerModel(model, { statusMessage: 'Feld eingefuegt', scope: 'structure' });
    renderDbDesigner();
    return;
  }
  if (action === 'delete-column' && table && colIndex >= 0 && colIndex < table.columns.length) {
    table.columns.splice(colIndex, 1);
    setDbDesignerModel(model, { statusMessage: 'Spalte geloescht', scope: 'structure' });
    renderDbDesigner();
    return;
  }

  if (action === 'add-row' && table) {
    table.rows = Array.isArray(table.rows) ? table.rows : [];
    const newRow = {};
    (table.columns || []).forEach((column) => {
      const key = String(column?.name || '').trim();
      if (!key) return;
      newRow[key] = '';
    });
    table.rows.push(newRow);
    const rowIndex = table.rows.length - 1;
    const pending = getDbPendingDataRowEdits(tableIndex, rowIndex);
    pending.__new__ = true;
    dbDesignerState.pendingDataRowEdits = dbDesignerState.pendingDataRowEdits || {};
    dbDesignerState.pendingDataRowEdits[`${tableIndex}:${rowIndex}`] = pending;
    setDbDesignerModel(model, { statusMessage: 'Zeile vorbereitet', scope: 'data' });
    renderDbDesigner();
    focusDbGridCell(tableIndex, rowIndex, 0);
    return;
  }

  if (action === 'discard-inline-row' && table) {
    clearDbInlineDraftRow(model, tableIndex);
    setDbDesignerModel(model, { statusMessage: 'Änderungen verworfen', scope: 'data' });
    renderDbDesigner();
    return;
  }

  if (action === 'add-row-inline' && table) {
    table.rows = Array.isArray(table.rows) ? table.rows : [];
    const draft = getDbInlineDraftRow(model, tableIndex);
    const newRow = {};
    let hasAnyValue = false;
    (table.columns || []).forEach((column) => {
      const key = String(column?.name || '').trim();
      if (!key) return;
      const rawValue = draft[key] ?? '';
      const normalizedValue = normalizeDbDataCellValue(rawValue);
      if (String(rawValue).trim() !== '') {
        hasAnyValue = true;
      }
      const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
      const autoValue = isAutoColumn ? getDbAutoGeneratedValue(table, column) : normalizedValue;
      newRow[key] = autoValue;
    });

    if (!hasAnyValue) {
      dbDesignerState.statusMessage = 'Inline-Zeile ist leer.';
      renderDbDesigner();
      return;
    }

    table.rows.push(newRow);
    clearDbPendingDataRowEdits(tableIndex, table.rows.length - 1);
    const draftKey = buildDbTableSelectionKey(model, tableIndex);
    const nextDrafts = dbDesignerState.inlineRowDrafts || {};
    nextDrafts[draftKey] = {};
    dbDesignerState.inlineRowDrafts = nextDrafts;
    clearDbInlineDraftRow(model, tableIndex);
    setDbDesignerModel(model, { statusMessage: 'Zeile gespeichert', scope: 'data' });
    renderDbDesigner();
    window.setTimeout(() => {
      const inlineInput = document.querySelector('input[data-db-input="row-new-value"]');
      if (inlineInput instanceof HTMLInputElement) {
        inlineInput.focus();
      }
    }, 0);
    return;
  }

  if (action === 'duplicate-row' && table) {
    const rowIndex = Number(buttonEl?.getAttribute('data-row-index') || -1);
    if (Array.isArray(table.rows) && rowIndex >= 0 && rowIndex < table.rows.length) {
      const source = table.rows[rowIndex] || {};
      const clone = JSON.parse(JSON.stringify(source));
      (table.columns || []).forEach((column) => {
        const key = String(column?.name || '').trim();
        if (!key) return;
        if (String(column?.type || '').toUpperCase() === 'AUTO') {
          clone[key] = getDbAutoGeneratedValue(table, column, rowIndex);
        }
      });
      table.rows.splice(rowIndex + 1, 0, clone);
      setDbDesignerModel(model, { statusMessage: 'Zeile dupliziert', scope: 'data' });
      renderDbDesigner();
      focusDbGridCell(tableIndex, rowIndex + 1, 0);
    }
    return;
  }

  if (action === 'commit-row-edit' && table) {
    const rowIndex = Number(buttonEl?.getAttribute('data-row-index') || -1);
    if (Array.isArray(table.rows) && rowIndex >= 0 && rowIndex < table.rows.length) {
      const pending = getDbPendingDataRowEdits(tableIndex, rowIndex);
      const pendingEntries = Object.entries(pending || {}).filter(([colName]) => colName !== '__new__');
      if (pendingEntries.length || pending?.__new__) {
        (table.columns || []).forEach((column) => {
          const colName = String(column?.name || '').trim();
          if (!colName) return;
          const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
          const pendingValue = pendingEntries.find(([name]) => name === colName)?.[1];
          const nextValue = isAutoColumn
            ? (pendingValue !== undefined && String(pendingValue).trim() !== '' ? pendingValue : getDbAutoGeneratedValue(table, column, rowIndex))
            : (pendingValue !== undefined ? pendingValue : table.rows[rowIndex]?.[colName]);
          table.rows[rowIndex][colName] = normalizeDbDataCellValue(nextValue);
        });
        clearDbPendingDataRowEdits(tableIndex, rowIndex);
        setDbDesignerModel(model, { statusMessage: 'Zeile gespeichert', scope: 'data' });
        renderDbDesigner();
      }
    }
    return;
  }

  if (action === 'discard-row-edit' && table) {
    const rowIndex = Number(buttonEl?.getAttribute('data-row-index') || -1);
    if (Array.isArray(table.rows) && rowIndex >= 0 && rowIndex < table.rows.length) {
      const pending = getDbPendingDataRowEdits(tableIndex, rowIndex);
      if (pending?.__new__) {
        table.rows.splice(rowIndex, 1);
      }
      clearDbPendingDataRowEdits(tableIndex, rowIndex);
      setDbDesignerModel(model, { statusMessage: 'Änderungen verworfen', scope: 'data' });
      renderDbDesigner();
    }
    return;
  }

  if (action === 'delete-row' && table) {
    const rowIndex = Number(buttonEl?.getAttribute('data-row-index') || -1);
    if (Array.isArray(table.rows) && rowIndex >= 0 && rowIndex < table.rows.length) {
      table.rows.splice(rowIndex, 1);
      setDbDesignerModel(model, { statusMessage: 'Zeile geloescht', scope: 'data' });
      renderDbDesigner();
    }
  }
}

function handleDbDesignerInput(inputEl, options = {}) {
  ensureDbDesignerStateExists();
  const model = cloneDbModel(dbDesignerState.model);
  const key = String(inputEl?.getAttribute('data-db-input') || '');
  if (!key) return;
  const rerender = options.rerender !== false;
  const pushHistory = options.pushHistory !== false;

  const value = inputEl.type === 'checkbox' ? inputEl.checked : inputEl.value;
  const tableIndex = Number(inputEl.getAttribute('data-table-index') || -1);
  const colIndex = Number(inputEl.getAttribute('data-col-index') || -1);
  const activeDbIndex = Number(model.activeDatabaseIndex || 0);
  const activeDb = model.databases?.[activeDbIndex];
  const table = activeDb?.tables?.[tableIndex];
  const col = table?.columns?.[colIndex];
  const rowIndex = Number(inputEl.getAttribute('data-row-index') || -1);
  const rowColName = String(inputEl.getAttribute('data-col-name') || '');

  switch (key) {
    case 'active-db':
      model.activeDatabaseIndex = Math.max(0, Number(value || 0));
      dbDesignerState.selectedTableIndex = 0;
      setDbDesignerModel(model, { pushHistory: false, statusMessage: '', scope: 'structure' });
      break;
    case 'table-name':
      if (table) table.name = String(value || '').trim() || 'unbenannte_tabelle';
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-name':
      if (col) col.name = String(value || '').trim() || 'spalte';
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-type':
      if (col) {
        col.type = String(value || 'VARCHAR').toUpperCase();
        if (col.type === 'AUTO') {
          col.size = 3;
          col.pk = true;
          col.fk = false;
          col.nullable = false;
        } else if (col.type === 'VARCHAR' && !Number.isFinite(Number(col.size))) {
          col.size = 50;
        } else if (col.type === 'INTEGER') {
          col.size = [1, 2, 3, 4, 8].includes(Number(col.size)) ? Number(col.size) : 2;
        } else if (col.type !== 'VARCHAR') {
          col.size = null;
        }
      }
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-pk':
      if (col && col.type !== 'AUTO') {
        col.pk = Boolean(value);
        if (col.pk) col.nullable = false;
      }
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-fk':
      if (col && col.type !== 'AUTO') {
        col.fk = Boolean(value);
        if (!col.fk) {
          col.references = null;
        } else if (!col.references) {
          col.references = { table: '', column: '', onUpdate: 'NO ACTION', onDelete: 'NO ACTION' };
        }
      }
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-size':
      if (col) {
        const sizeValue = Number(value);
        if (col.type === 'AUTO') {
          col.size = 3;
        } else if (col.type === 'VARCHAR') {
          col.size = Number.isFinite(sizeValue) && sizeValue > 0 ? Math.floor(sizeValue) : 50;
        } else if (col.type === 'INTEGER') {
          col.size = [1, 2, 3, 4, 8].includes(Math.floor(sizeValue)) ? Math.floor(sizeValue) : 2;
        }
      }
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-nullable':
      if (col && col.type !== 'AUTO' && !col.pk) col.nullable = Boolean(value);
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'col-default':
      if (col) col.default = String(value ?? '');
      setDbDesignerModel(model, { statusMessage: '', pushHistory, scope: 'structure' });
      break;
    case 'row-value':
      if (table && Array.isArray(table.rows) && rowIndex >= 0 && rowIndex < table.rows.length && rowColName) {
        const pending = getDbPendingDataRowEdits(tableIndex, rowIndex);
        pending[rowColName] = String(value ?? '');
        dbDesignerState.pendingDataRowEdits = dbDesignerState.pendingDataRowEdits || {};
        dbDesignerState.pendingDataRowEdits[`${tableIndex}:${rowIndex}`] = pending;
        syncDbDataRowActionButtons(inputEl);
      }
      break;
    case 'row-new-value': {
      if (tableIndex >= 0 && rowColName) {
        const draft = getDbInlineDraftRow(dbDesignerState.model, tableIndex);
        draft[rowColName] = String(value ?? '');
        syncDbDataRowActionButtons(inputEl);
      }
      break;
    }
    case 'col-new-name': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        draft.name = String(value ?? '');
      }
      break;
    }
    case 'col-new-type': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        draft.type = String(value || 'VARCHAR').toUpperCase();
        if (draft.type === 'AUTO') {
          draft.size = 3;
          draft.pk = true;
          draft.fk = false;
          draft.nullable = false;
        } else if (draft.type === 'VARCHAR' && !Number.isFinite(Number(draft.size))) {
          draft.size = 50;
        } else if (draft.type === 'INTEGER') {
          draft.size = [1, 2, 3, 4, 8].includes(Number(draft.size)) ? Number(draft.size) : 2;
        } else if (draft.type !== 'VARCHAR') {
          draft.size = null;
        }
      }
      break;
    }
    case 'col-new-size': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        const sizeValue = Number(value);
        if (draft.type === 'AUTO') {
          draft.size = 3;
        } else if (draft.type === 'VARCHAR') {
          draft.size = Number.isFinite(sizeValue) && sizeValue > 0 ? Math.floor(sizeValue) : 50;
        } else if (draft.type === 'INTEGER') {
          draft.size = [1, 2, 3, 4, 8].includes(Math.floor(sizeValue)) ? Math.floor(sizeValue) : 2;
        }
      }
      break;
    }
    case 'col-new-pk': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        if (String(draft.type || '').toUpperCase() !== 'AUTO') {
          draft.pk = Boolean(value);
          if (draft.pk) draft.nullable = false;
        }
      }
      break;
    }
    case 'col-new-fk': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        if (String(draft.type || '').toUpperCase() !== 'AUTO') {
          draft.fk = Boolean(value);
        }
      }
      break;
    }
    case 'col-new-nullable': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        if (String(draft.type || '').toUpperCase() !== 'AUTO' && !draft.pk) {
          draft.nullable = Boolean(value);
        }
      }
      break;
    }
    case 'col-new-default': {
      if (tableIndex >= 0) {
        const draft = getDbInlineDraftColumn(model, tableIndex);
        draft.default = String(value ?? '');
      }
      break;
    }
    case 'data-filter':
      dbDesignerState.dataFilter = String(value ?? '');
      break;
    case 'sql-text':
      dbDesignerState.sqlText = String(value ?? '');
      dbDesignerState.statusMessage = '';
      break;
    default:
      return;
  }

  if (rerender) {
    renderDbDesigner();
  }
}

function ensureDbDesignerBindings() {
  const bindTargets = [
    document.getElementById('gui-container'),
    document.getElementById('db-structure-tree'),
    document.getElementById('db-design-container'),
    document.getElementById('db-data-container'),
    document.getElementById('left-structure-header')
  ].filter(Boolean);

  if (!bindTargets.length) return;
  const alreadyBound = bindTargets.every((el) => el.dataset.dbDesignerBound === '1');
  if (alreadyBound) return;

  for (const target of bindTargets) {
    if (target.dataset.dbDesignerBound === '1') continue;

    target.addEventListener('mousedown', (event) => {
      if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
      if (event.button !== 0) return;
      const actionEl = event.target?.closest?.('[data-db-action]');
      if (!actionEl || !target.contains(actionEl)) return;
      event.preventDefault();
      event.stopPropagation();
      const action = actionEl.getAttribute('data-db-action') || '';
      handleDbDesignerAction(action, actionEl);
    });

    if (target.id === 'db-structure-tree') {
      target.addEventListener('contextmenu', (event) => {
        if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
        const treeItem = event.target?.closest?.('.db-tree-item[data-table-index]');
        if (!treeItem || !target.contains(treeItem)) return;
        event.preventDefault();
        const tableIndex = Number(treeItem.getAttribute('data-table-index') || -1);
        if (tableIndex < 0) return;
        dbDesignerState.selectedTableIndex = tableIndex;
        renderDbDesigner();
        showDbTreeContextMenu(tableIndex, event.pageX, event.pageY);
      });
    }

    target.addEventListener('input', (event) => {
      if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
      const inputEl = event.target;
      if (!(inputEl instanceof HTMLInputElement || inputEl instanceof HTMLSelectElement || inputEl instanceof HTMLTextAreaElement)) return;
      if (!inputEl.hasAttribute('data-db-input')) return;
      // Do not rerender while typing to avoid replacing the active input element mid-event.
      handleDbDesignerInput(inputEl, { rerender: false, pushHistory: false });
    });

    target.addEventListener('change', (event) => {
      if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
      const inputEl = event.target;
      if (!(inputEl instanceof HTMLInputElement || inputEl instanceof HTMLSelectElement || inputEl instanceof HTMLTextAreaElement)) return;
      if (!inputEl.hasAttribute('data-db-input')) return;
      const key = String(inputEl.getAttribute('data-db-input') || '');
      if (key === 'row-value' || key === 'row-new-value' || key === 'sql-text') {
        handleDbDesignerInput(inputEl, { rerender: false, pushHistory: false });
        return;
      }
      handleDbDesignerInput(inputEl);
    });

    target.addEventListener('keydown', (event) => {
      if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
      const inputEl = event.target;
      if (!(inputEl instanceof HTMLInputElement || inputEl instanceof HTMLTextAreaElement)) return;
      if (!inputEl.hasAttribute('data-db-input')) return;

      const key = String(inputEl.getAttribute('data-db-input') || '');
      const tableIndex = Number(inputEl.getAttribute('data-table-index') || -1);
      const rowIndex = Number(inputEl.getAttribute('data-row-index') || -1);
      const colIndex = Number(inputEl.getAttribute('data-col-index') || -1);
      const sqlPermissions = getSqlEditorPermissions(currentProject);

      if (key === 'sql-text' && (event.ctrlKey || event.metaKey)) {
        const shortcut = String(event.key || '').toLowerCase();
        if (shortcut === 'c' && !sqlPermissions.allowCopy) {
          event.preventDefault();
          dbDesignerState.statusMessage = 'Copy ist für diesen Kontext deaktiviert.';
          renderDbDesigner();
          return;
        }
        if (shortcut === 'v' && !sqlPermissions.allowPaste) {
          event.preventDefault();
          dbDesignerState.statusMessage = 'Paste ist für diesen Kontext deaktiviert.';
          renderDbDesigner();
          return;
        }
      }

      if (key === 'row-value' && event.key === 'Enter') {
        event.preventDefault();
        handleDbDesignerAction('commit-row-edit', inputEl);
        return;
      }

      if (key === 'row-new-value' && event.key === 'Enter') {
        event.preventDefault();
        handleDbDesignerAction('add-row-inline', inputEl);
        return;
      }

      if (key.startsWith('col-new-') && event.key === 'Enter') {
        event.preventDefault();
        handleDbDesignerAction('add-column-inline', inputEl);
        return;
      }

      if ((key === 'row-value' || key === 'row-new-value') && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
        const cursorPos = Number(inputEl.selectionStart || 0);
        const textLength = String(inputEl.value || '').length;

        if (event.key === 'ArrowLeft' && cursorPos > 0) return;
        if (event.key === 'ArrowRight' && cursorPos < textLength) return;

        event.preventDefault();
        if (event.key === 'ArrowUp') {
          focusDbGridCell(tableIndex, Math.max(0, rowIndex - 1), colIndex);
          return;
        }
        if (event.key === 'ArrowDown') {
          focusDbGridCell(tableIndex, rowIndex + 1, colIndex);
          return;
        }
        if (event.key === 'ArrowLeft') {
          focusDbGridCell(tableIndex, rowIndex, Math.max(0, colIndex - 1));
          return;
        }
        if (event.key === 'ArrowRight') {
          focusDbGridCell(tableIndex, rowIndex, colIndex + 1);
        }
      }
    });

    target.addEventListener('copy', (event) => {
      if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
      const inputEl = event.target;
      if (!(inputEl instanceof HTMLInputElement || inputEl instanceof HTMLTextAreaElement)) return;
      const key = String(inputEl.getAttribute('data-db-input') || '');
      const sqlPermissions = getSqlEditorPermissions(currentProject);
      if (key === 'sql-text' && !sqlPermissions.allowCopy) {
        event.preventDefault();
        dbDesignerState.statusMessage = 'Copy ist für diesen Kontext deaktiviert.';
        renderDbDesigner();
      }
    });

    target.addEventListener('paste', (event) => {
      if (!currentProject || resolveProjectMode(currentProject) !== PROJECT_MODE.DB) return;
      const inputEl = event.target;
      if (!(inputEl instanceof HTMLInputElement || inputEl instanceof HTMLTextAreaElement)) return;
      const key = String(inputEl.getAttribute('data-db-input') || '');
      const sqlPermissions = getSqlEditorPermissions(currentProject);
      if (key === 'sql-text') {
        if (!sqlPermissions.allowPaste) {
          event.preventDefault();
          dbDesignerState.statusMessage = 'Paste ist für diesen Kontext deaktiviert.';
          renderDbDesigner();
        }
        return;
      }
      if (key !== 'row-value' && key !== 'row-new-value') return;

      const clipboard = event.clipboardData?.getData('text/plain') || '';
      if (!clipboard || !clipboard.includes('\t') && !clipboard.includes('\n')) return;

      const tableIndex = Number(inputEl.getAttribute('data-table-index') || -1);
      const rowIndex = Number(inputEl.getAttribute('data-row-index') || -1);
      const colIndex = Number(inputEl.getAttribute('data-col-index') || -1);
      if (tableIndex < 0 || rowIndex < 0 || colIndex < 0) return;

      event.preventDefault();
      const model = cloneDbModel(dbDesignerState.model);
      const changed = applyDbGridPaste(model, tableIndex, rowIndex, colIndex, clipboard);
      if (!changed) return;

      setDbDesignerModel(model, { statusMessage: 'Tabellendaten eingefuegt', scope: 'data' });
      renderDbDesigner();
    });

    target.dataset.dbDesignerBound = '1';
  }
}

async function initializeDbMode(project) {
  const guiContainer = document.getElementById('gui-container');
  if (!guiContainer) return;

  ensureDbDesignerBindings();

  const dbFile = await readDbModelFile(project.id);
  const dbDataFile = await readDbDataFile(project.id);
  const normalized = DbModelSchema.normalizeDbModel(dbFile?.content || '');
  let mergedModel = normalized.model;
  try {
    if (dbDataFile?.content) {
      mergedModel = mergeDbDataPayloadIntoModel(mergedModel, dbDataFile.content);
    }
  } catch (_e) {
    // Keep model rows from db_model.json when db_data.json is absent or malformed.
  }
  dbDesignerState = {
    projectId: project.id,
    dbFileId: Number(dbFile?.fileId || 0) || null,
    dbFileName: DB_MODEL_FILE_NAME,
    model: mergedModel,
    dirty: false,
    dirtyStructure: false,
    dirtyData: false,
    statusMessage: normalized.parseError ? `Warnung: ${normalized.parseError}` : '',
    history: [],
    historyIndex: -1,
    saveInFlight: false,
    selectedTableIndex: 0,
    sqlText: '',
    sqlQueryResult: null,
    sqlRunInFlight: false,
    inlineRowDrafts: {},
    inlineColumnDrafts: {},
    pendingDataRowEdits: {},
    showDiagnostics: false,
    dataFilter: '',
    dataSortColumn: '',
    dataSortDirection: 'ASC'
  };

  ensureSelectedDbTableIndex();
  pushDbDesignerHistorySnapshot();
  renderDbDesigner();
}

function initializeUmlMode(project) {
  const guiContainer = document.getElementById('gui-container');
  if (!guiContainer) return;
  guiContainer.innerHTML = [
    '<div style="padding:16px; color:#333;">',
    '<h3 style="margin:0 0 8px 0;">UML Modus</h3>',
    '<p style="margin:0; color:#666;">UML Renderer wird in Phase E angebunden. Mode-Entkopplung ist aktiv.</p>',
    '</div>'
  ].join('');
}

async function initializeProjectModeRuntime(project) {
  const mode = resolveProjectMode(project);
  if (mode === PROJECT_MODE.DB) {
    await initializeDbMode(project);
    return;
  }
  if (mode === PROJECT_MODE.UML) {
    initializeUmlMode(project);
  }
}

function updateWebHelpButton(project) {
  const helpBtn = document.getElementById('web-help-btn');
  if (!helpBtn) return;

  const shouldShow = Boolean(project && getProjectModeConfig(project).showWebHelp);
  helpBtn.style.display = shouldShow ? '' : 'none';
  helpBtn.disabled = !shouldShow;
}

function setProjectsRightPanelMode(guiActive) {
  const guiContainer = document.getElementById('gui-container');
  const rightPanel = guiContainer?.closest('.right') || document.querySelector('.right');
  if (!rightPanel) return;

  rightPanel.classList.toggle('gui-active', Boolean(guiActive));
}

function buildExportFileName(projectName) {
  const base = String(projectName || 'project')
    .trim()
    .replace(/[^a-zA-Z0-9._-]+/g, '_')
    .replace(/^[_\.\-]+|[_\.\-]+$/g, '') || 'project';
  return `${base}.pyideproj`;
}

function normalizeProjectNameInput(name) {
  return String(name || '').replace(/\s+/g, ' ').trim();
}

function buildImportNameSuggestion(baseName, existingNames) {
  const normalizedBase = normalizeProjectNameInput(baseName) || 'Importiertes Projekt';
  const nameSet = new Set((existingNames || []).map((n) => normalizeProjectNameInput(n).toLowerCase()));

  if (!nameSet.has(normalizedBase.toLowerCase())) {
    return normalizedBase;
  }

  const firstCandidate = `${normalizedBase}=1`;
  if (!nameSet.has(firstCandidate.toLowerCase())) {
    return firstCandidate;
  }

  for (let i = 2; i < 1000; i++) {
    const suffix = i < 100 ? String(i).padStart(2, '0') : String(i);
    const candidate = `${normalizedBase}=${suffix}`;
    if (!nameSet.has(candidate.toLowerCase())) {
      return candidate;
    }
  }

  return `${normalizedBase}=${Date.now()}`;
}

function resolveUniqueImportName(chosenName, existingNames) {
  const desired = normalizeProjectNameInput(chosenName);
  if (!desired) return '';
  return buildImportNameSuggestion(desired, existingNames);
}

function isZipImportFile(file) {
  if (!file) return false;
  const name = String(file.name || '').toLowerCase();
  const type = String(file.type || '').toLowerCase();
  return name.endsWith('.zip') || type === 'application/zip' || type === 'application/x-zip-compressed';
}

function normalizeZipEntryPath(path) {
  return String(path || '')
    .replace(/\\/g, '/')
    .replace(/^\/+|\/+$/g, '')
    .replace(/\/+/g, '/');
}

async function parseZipProjectArchive(file) {
  if (typeof JSZip === 'undefined') {
    throw new Error('JSZip ist nicht geladen. Bitte Seite neu laden.');
  }

  const zip = await JSZip.loadAsync(file);
  let fileEntries = Object.values(zip.files)
    .filter((entry) => !entry.dir)
    .map((entry) => ({
      zipPath: String(entry.name || ''),
      normalizedPath: normalizeZipEntryPath(entry.name || '')
    }))
    .filter((entry) => entry.normalizedPath !== '' && !entry.normalizedPath.startsWith('__MACOSX/'));

  if (fileEntries.length === 0) {
    throw new Error('ZIP enthält keine importierbaren Dateien.');
  }

  // Auto-strip one common top-level folder (typical OS zip behavior).
  const topLevel = Array.from(new Set(fileEntries.map((entry) => entry.normalizedPath.split('/')[0])));
  const canStripRoot = topLevel.length === 1 && fileEntries.every((entry) => entry.normalizedPath.includes('/'));
  if (canStripRoot) {
    fileEntries = fileEntries
      .map((entry) => {
        const stripped = normalizeZipEntryPath(entry.normalizedPath.split('/').slice(1).join('/'));
        return {
          ...entry,
          normalizedPath: stripped
        };
      })
      .filter((entry) => entry.normalizedPath !== '');
  }

  const files = [];
  for (const entry of fileEntries) {
    const zipFile = zip.files[entry.zipPath];
    if (!zipFile) continue;
    let content = '';
    try {
      content = await zipFile.async('text');
    } catch (_err) {
      throw new Error(`Datei konnte nicht als Text entpackt werden: ${entry.normalizedPath}`);
    }

    files.push({
      path: entry.normalizedPath,
      content
    });
  }

  if (files.length === 0) {
    throw new Error('ZIP enthält keine lesbaren Textdateien.');
  }

  const foldersSet = new Set();
  for (const f of files) {
    const parts = normalizeZipEntryPath(f.path).split('/').filter(Boolean);
    for (let i = 1; i < parts.length; i++) {
      foldersSet.add(parts.slice(0, i).join('/'));
    }
  }
  const folders = Array.from(foldersSet).sort((a, b) => {
    const depthA = a.split('/').length;
    const depthB = b.split('/').length;
    if (depthA !== depthB) return depthA - depthB;
    return a.localeCompare(b);
  });

  return {
    archive: {
      format: 'pythonide-project-v1',
      project: {
        name: normalizeProjectNameInput(file.name.replace(/\.[^.]+$/, '')) || 'Importiertes ZIP-Projekt'
      },
      folders,
      files
    },
    sourceType: 'zip'
  };
}

async function parseImportArchiveFile(file) {
  if (isZipImportFile(file)) {
    return parseZipProjectArchive(file);
  }

  const raw = await file.text();
  let archive;
  try {
    archive = JSON.parse(raw);
  } catch (_err) {
    throw new Error('Die Datei ist kein gültiges JSON-Archiv.');
  }

  if (!archive || typeof archive !== 'object') {
    throw new Error('Ungültiges Archivformat.');
  }

  return {
    archive,
    sourceType: 'json'
  };
}

function triggerFileDownload(blob, fileName) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

async function exportCurrentProjectToFile() {
  if (!currentProject?.id) {
    alert('Bitte zuerst ein Projekt öffnen.');
    return;
  }

  try {
    const canSwitch = await confirmProjectSwitchWithDrafts();
    if (!canSwitch) return;

    const response = await fetch(`../api/projects/export.php?project_id=${currentProject.id}`, {
      credentials: 'include',
      cache: 'no-store'
    });

    if (!response.ok) {
      let message = 'Export fehlgeschlagen';
      try {
        const data = await response.json();
        if (data?.error) message = data.error;
      } catch (_err) {
        // Ignore parse failure and keep generic message.
      }
      throw new Error(message);
    }

    const blob = await response.blob();
    triggerFileDownload(blob, buildExportFileName(currentProject.name));
  } catch (error) {
    console.error('Project export failed:', error);
    alert('Fehler beim Export: ' + (error?.message || error));
  }
}

async function importProjectFromArchiveFile(file) {
  if (!file) return;

  try {
    const parsed = await parseImportArchiveFile(file);
    const archive = parsed.archive;
    const sourceType = parsed.sourceType;

    const importedName = normalizeProjectNameInput(String(
      archive?.project?.name
      || file.name.replace(/\.[^.]+$/, '')
      || 'Importiertes Projekt'
    ));

    const existingNames = Array.isArray(projects) ? projects.map((p) => String(p?.name || '')) : [];
    const suggestedName = buildImportNameSuggestion(importedName, existingNames);

    const userInput = window.prompt('Name für das neue importierte Projekt:', suggestedName);
    if (userInput === null) {
      return;
    }

    const uniqueChosenName = resolveUniqueImportName(userInput, existingNames);
    if (!uniqueChosenName) {
      alert('Bitte einen gültigen Projektnamen eingeben.');
      return;
    }

    if (normalizeProjectNameInput(userInput).toLowerCase() !== uniqueChosenName.toLowerCase()) {
      alert(`Projektname bereits vorhanden. Import erfolgt als: ${uniqueChosenName}`);
    }

    const response = await fetch('../api/projects/import.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: uniqueChosenName,
        archive,
        source_type: sourceType
      })
    });

    const data = await response.json();
    if (!response.ok || !data?.ok) {
      throw new Error(data?.error || 'Import fehlgeschlagen');
    }

    const newProjectId = Number(data?.project?.id || 0);
    if (!newProjectId) {
      throw new Error('Importantwort enthält keine Projekt-ID');
    }

    await loadProjects();
    await loadProject(newProjectId);
  } catch (error) {
    console.error('Project import failed:', error);
    alert('Fehler beim Import: ' + (error?.message || error));
  }
}

async function beforeRunExecution() {
  if (!currentProject || !isProjectGuiMode(currentProject)) {
    return;
  }

  // Ensure current editor content is in draft cache before reading it in renderProjectHtml
  cacheCurrentProjectEditorDraft();

  // If file tree was marked dirty (e.g., after saving HTML/CSS), reinitialize it with fresh data
  if (projectFileTreeDirty && projectFileManager && typeof projectFileManager.init === 'function') {
    projectFileTreeDirty = false;
    console.log('[projects-editor] File tree was dirty, reinitializing with fresh data...');
    try {
      await projectFileManager.init();
      console.log('[projects-editor] File tree reinitialized successfully');
    } catch (reloadErr) {
      console.warn('[projects-editor] File tree reinit failed, continuing anyway:', reloadErr);
    }
  }

  const guiContainer = document.getElementById('gui-container');
  const currentDir = getActiveProjectFolderPath() || await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');
  const alreadyRendered = Boolean(
    guiContainer
    && guiContainer.dataset.projectHtmlRendered === '1'
    && String(guiContainer.dataset.projectHtmlDirty || '0') !== '1'
    && String(guiContainer.dataset.projectHtmlActiveFolder || '') === String(currentDir || '')
    && guiContainer.dataset.projectId === String(currentProject.id)
    && guiContainer.querySelector('[data-element]')
  );

  const skipRenderForTrigger = window.__projectSkipHtmlRerenderOnce === true;
  if (skipRenderForTrigger && alreadyRendered) {
    window.__projectSkipHtmlRerenderOnce = false;
    return;
  }

  window.__projectSkipHtmlRerenderOnce = false;
  await renderProjectHtml();
}

async function getProjectRunContext() {
  if (!currentProject) {
    return null;
  }

  cacheCurrentProjectEditorDraft();

  const editor = getEditorInstance();
  let code = String(editor?.getValue?.() || '');
  let fileName = currentOpenFileName || '';
  const activeFolder = getActiveProjectFolderPath() || await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');

  if (!isPythonFile(fileName)) {
    const initFile = await readProjectFileByPreferredPath(currentProject.id, 'init.py', activeFolder);
    const initFileId = Number(initFile?.fileId || initFile?.id || 0);
    const draft = getProjectDraftContent(initFileId);
    if (draft !== null) {
      code = String(draft || '');
      fileName = activeFolder ? `${activeFolder}/init.py` : 'init.py';
    } else if (initFile?.content != null) {
      code = String(initFile.content || '');
      fileName = activeFolder ? `${activeFolder}/init.py` : 'init.py';
    }
  }

  return {
    code,
    fileName,
    projectType: String(currentProject.project_type || 'python').toLowerCase(),
    isCodeUiMode: isProjectGuiMode(currentProject)
  };
}

const PROJECT_DATA_FILE_EXTENSIONS = new Set(['.txt', '.csv', '.json', '.tsv', '.dat', '.xml', '.yaml', '.yml', '.md', '.ini', '.cfg']);

function collectProjectPythonFiles(nodes, parentPath = '') {
  if (!Array.isArray(nodes)) return [];

  const result = [];
  for (const node of nodes) {
    if (!node || typeof node.name !== 'string') continue;

    if (node.type === 'folder') {
      const folderPath = parentPath ? `${parentPath}/${node.name}` : node.name;
      result.push(...collectProjectPythonFiles(node.children || [], folderPath));
      continue;
    }

    if (node.type === 'file' && node.name.toLowerCase().endsWith('.py')) {
      const path = parentPath ? `${parentPath}/${node.name}` : node.name;
      result.push({
        id: Number(node.id || 0),
        name: node.name,
        path
      });
    }
  }

  return result;
}

function collectProjectDataFiles(nodes, parentPath = '') {
  if (!Array.isArray(nodes)) return [];

  const result = [];
  for (const node of nodes) {
    if (!node || typeof node.name !== 'string') continue;

    if (node.type === 'folder') {
      const folderPath = parentPath ? `${parentPath}/${node.name}` : node.name;
      result.push(...collectProjectDataFiles(node.children || [], folderPath));
      continue;
    }

    if (node.type === 'file') {
      const ext = node.name.toLowerCase().slice(node.name.lastIndexOf('.'));
      if (PROJECT_DATA_FILE_EXTENSIONS.has(ext)) {
        const path = parentPath ? `${parentPath}/${node.name}` : node.name;
        result.push({
          id: Number(node.id || 0),
          name: node.name,
          path
        });
      }
    }
  }

  return result;
}

function parseLocalImportSpecifiers(code) {
  const imports = new Set();
  const text = String(code || '');

  const importMatches = text.matchAll(/^\s*import\s+([^#\n]+)/gm);
  for (const match of importMatches) {
    const parts = String(match[1] || '').split(',');
    for (const part of parts) {
      const token = part.trim().split(/\s+as\s+/i)[0]?.trim();
      if (token && /^[A-Za-z_][A-Za-z0-9_\.]*$/.test(token)) {
        imports.add(token);
      }
    }
  }

  const fromMatches = text.matchAll(/^\s*from\s+([^\s]+)\s+import\s+([^#\n]+)/gm);
  for (const match of fromMatches) {
    const base = String(match[1] || '').trim();
    if (!base || base.startsWith('.')) {
      continue;
    }

    if (/^[A-Za-z_][A-Za-z0-9_\.]*$/.test(base)) {
      imports.add(base);
    }
  }

  return Array.from(imports);
}

function resolveLocalModulePath(moduleName, currentPath, pathToMeta) {
  const rel = String(moduleName || '').replace(/\./g, '/');
  if (!rel) return null;

  const currentDir = currentPath && currentPath.includes('/')
    ? currentPath.slice(0, currentPath.lastIndexOf('/'))
    : '';

  const candidates = [];
  if (currentDir) {
    candidates.push(`${currentDir}/${rel}.py`);
    candidates.push(`${currentDir}/${rel}/__init__.py`);
  }
  candidates.push(`${rel}.py`);
  candidates.push(`${rel}/__init__.py`);

  for (const candidate of candidates) {
    if (pathToMeta.has(candidate)) {
      return candidate;
    }
  }

  return null;
}

async function readProjectPythonFileContent(meta) {
  if (!meta || !meta.id) return '';

  let content = getProjectDraftContent(meta.id);

  if (content === null && currentOpenFileId && Number(currentOpenFileId) === Number(meta.id)) {
    const editor = getEditorInstance();
    if (editor && typeof editor.getValue === 'function') {
      content = String(editor.getValue() || '');
    }
  }

  if (content === null) {
    const fileData = await readProjectFileById(currentProject.id, meta.id);
    content = fileData?.content ?? '';
  }

  return String(content ?? '');
}

async function getProjectPythonRuntimePayload() {
  if (!currentProject?.id) return null;

  cacheCurrentProjectEditorDraft();

  const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${currentProject.id}`, {
    credentials: 'include',
    cache: 'reload'
  });

  if (!treeResponse.ok) return null;

  const treeData = await treeResponse.json();
  const treeNodes = Array.isArray(treeData?.tree)
    ? treeData.tree
    : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

  const pyFiles = collectProjectPythonFiles(treeNodes);
  if (!pyFiles.length) return null;

  const openFileMeta = currentOpenFileId
    ? pyFiles.find((f) => Number(f.id) === Number(currentOpenFileId))
    : null;
  const openFileDir = openFileMeta?.path && openFileMeta.path.includes('/')
    ? openFileMeta.path.slice(0, openFileMeta.path.lastIndexOf('/'))
    : '';

  const activeFolder =
    getActiveProjectFolderPath()
    || pyodideRuntimeFolderPath
    || openFileDir
    || await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');

  const scopeRoot = activeFolder ? String(activeFolder).split('/')[0] : '';

  // Enforce runtime scope to a single top-level folder (e.g. 02_...),
  // so same module names in parallel folders cannot collide.
  const runtimePyFiles = scopeRoot
    ? pyFiles.filter((f) => f.path === scopeRoot || f.path.startsWith(`${scopeRoot}/`))
    : pyFiles;
  const pathToMeta = new Map(runtimePyFiles.map((f) => [f.path, f]));

  let mainPath = '';
  if (currentOpenFileId) {
    const openFile = runtimePyFiles.find((f) => Number(f.id) === Number(currentOpenFileId));
    const openFileInActiveFolder = !scopeRoot
      || openFile?.path === scopeRoot
      || openFile?.path.startsWith(`${scopeRoot}/`);
    if (openFile && openFileInActiveFolder) {
      mainPath = openFile.path;
    }
  }

  if (!mainPath && activeFolder) {
    const activeMainPath = `${activeFolder}/main.py`;
    if (pathToMeta.has(activeMainPath)) {
      mainPath = activeMainPath;
    }
  }

  if (!mainPath && activeFolder) {
    const activeInitPath = `${activeFolder}/init.py`;
    if (pathToMeta.has(activeInitPath)) {
      mainPath = activeInitPath;
    }
  }

  if (!mainPath && activeFolder) {
    const firstActiveFolderPyFile = runtimePyFiles.find((f) => f.path.startsWith(`${activeFolder}/`));
    if (firstActiveFolderPyFile) {
      mainPath = firstActiveFolderPyFile.path;
    }
  }

  if (!mainPath) {
    const initFile = runtimePyFiles.find((f) => f.path === 'init.py' || f.name === 'init.py');
    if (initFile) {
      mainPath = initFile.path;
    }
  }

  if (!mainPath && runtimePyFiles.length > 0) {
    mainPath = runtimePyFiles[0].path;
  }

  const includedPaths = new Set();
  const pathToContent = new Map();
  const stack = [mainPath];

  while (stack.length > 0) {
    const currentPath = stack.pop();
    if (!currentPath || includedPaths.has(currentPath)) {
      continue;
    }

    const meta = pathToMeta.get(currentPath);
    if (!meta) {
      continue;
    }

    const content = await readProjectPythonFileContent(meta);
    includedPaths.add(currentPath);
    pathToContent.set(currentPath, content);

    const importSpecs = parseLocalImportSpecifiers(content);
    for (const spec of importSpecs) {
      const targetPath = resolveLocalModulePath(spec, currentPath, pathToMeta);
      if (targetPath && !includedPaths.has(targetPath)) {
        stack.push(targetPath);
      }
    }
  }

  const files = Array.from(includedPaths).map((path) => ({
    path,
    content: pathToContent.get(path) ?? ''
  }));

  // Include data files (txt, csv, json, etc.) so open() calls work in Pyodide
  const dataFiles = collectProjectDataFiles(treeNodes).filter((dataFile) => {
    if (!scopeRoot) return true;
    return dataFile.path === scopeRoot || dataFile.path.startsWith(`${scopeRoot}/`);
  });
  for (const dataFile of dataFiles) {
    if (!includedPaths.has(dataFile.path)) {
      const fileData = await readProjectFileById(currentProject.id, dataFile.id);
      files.push({
        path: dataFile.path,
        content: fileData?.content ?? ''
      });
    }
  }

  return {
    root: '/project',
    mainPath,
    files
  };
}

function triggerProjectPythonRun() {
  const runButton = document.getElementById('run-btn');
  if (!runButton) return;
  runButton.click();
}

/**
 * Reset Pyodide's sys.modules if folder changed
 * Called before runtime payload is synced to avoid stale module imports
 */
async function resetPyodideModulesIfNeeded(force = false) {
  if ((!pyodideRuntimeModulesDirty && !force) || !window.pyodide) {
    return;
  }

  pyodideRuntimeModulesDirty = false;
  
  try {
    console.log('[projects-editor] Clearing Pyodide sys.modules and .pyc caches...');
    await window.pyodide.runPythonAsync(`
import sys
import importlib
import os

# Aggressively remove all user modules (not system modules) from /project.
# Wrap __file__ access in try/except: some lazy modules (e.g. idegui)
# raise in __getattr__ and would abort the whole cleanup.
modules_to_remove = []
for name, module in list(sys.modules.items()):
  try:
    module_file = getattr(module, '__file__', None)
  except Exception:
    module_file = None
  if module_file and str(module_file).startswith('/project/'):
    modules_to_remove.append(name)

for mod in modules_to_remove:
  if mod in sys.modules:
    del sys.modules[mod]

# Drop cached path importers for previous project paths
for cache_key in list(sys.path_importer_cache.keys()):
  key = str(cache_key)
  if key.startswith('/project'):
    del sys.path_importer_cache[cache_key]

# Keep sys.path clean from stale project folders, current run will re-add active dir
sys.path[:] = [p for p in sys.path if not str(p).startswith('/project/')]

# Aggressively invalidate all import caches multiple times
importlib.invalidate_caches()
if hasattr(importlib, '_bootstrap_external'):
  try:
    importlib._bootstrap_external._path_importer_cache.clear()
  except Exception:
    pass

# Remove .pyc bytecode files under /project to force reimport
try:
  runtime_root = '/project'
  if os.path.exists(runtime_root):
    for dirpath, dirnames, filenames in os.walk(runtime_root):
      for fname in filenames:
        if fname.endswith('.pyc'):
          try:
            pyc_path = os.path.join(dirpath, fname)
            os.remove(pyc_path)
          except Exception:
            pass
except Exception:
  pass

print(f"[Pyodide] Cleared {len(modules_to_remove)} modules and .pyc bytecode from /project")
    `);
    console.log('[projects-editor] Pyodide sys.modules and .pyc caches cleared successfully');
  } catch (err) {
    console.warn('[projects-editor] Failed to clear Pyodide sys.modules:', err);
  }
}

function setProjectTriggerContext(guiContainer, triggerElement, isEventDriven = false) {
  if (!guiContainer || !triggerElement) return;

  const triggerName =
    triggerElement.getAttribute('name') ||
    triggerElement.id ||
    triggerElement.getAttribute('data-run-name') ||
    triggerElement.getAttribute('data-function') ||
    '';

  const explicitValueAttr = triggerElement.getAttribute('value');
  const triggerValue =
    (explicitValueAttr !== null
      ? explicitValueAttr
      : (typeof triggerElement.value === 'string' ? triggerElement.value : '')) ||
    triggerElement.getAttribute('data-run-value') ||
    '';

  let triggerInput = guiContainer.querySelector('[data-element="__trigger__"]');
  if (!triggerInput) {
    triggerInput = document.createElement('input');
    triggerInput.type = 'hidden';
    triggerInput.setAttribute('data-element', '__trigger__');
    guiContainer.appendChild(triggerInput);
  }
  triggerInput.value = String(triggerName);

  let triggerValueInput = guiContainer.querySelector('[data-element="__trigger_value__"]');
  if (!triggerValueInput) {
    triggerValueInput = document.createElement('input');
    triggerValueInput.type = 'hidden';
    triggerValueInput.setAttribute('data-element', '__trigger_value__');
    guiContainer.appendChild(triggerValueInput);
  }
  triggerValueInput.value = String(triggerValue);

  window.__codeUiTrigger = {
    name: String(triggerName),
    value: String(triggerValue)
  };
  window.__codeUiEventDrivenMode = isEventDriven;
  window.__projectSkipHtmlRerenderOnce = true;
}

async function triggerProjectFunctionCall(triggerElement) {
  if (!window.pyodide) {
    return;
  }

  const functionName = triggerElement?.getAttribute?.('data-function') || triggerElement?.getAttribute?.('data-run-name') || '';
  if (!functionName) {
    return;
  }

  const functionValue = triggerElement?.getAttribute?.('value') ?? triggerElement?.value ?? '';
  const triggerSignature = `${functionName}::${functionValue}`;
  const triggerNow = Date.now();
  const lastTrigger = window.__codeUiLastFunctionTrigger || null;
  if (
    lastTrigger
    && lastTrigger.signature === triggerSignature
    && (triggerNow - Number(lastTrigger.at || 0)) < 150
  ) {
    return;
  }
  window.__codeUiLastFunctionTrigger = {
    signature: triggerSignature,
    at: triggerNow,
  };

  const outputEl = document.getElementById('output-container');
  const lintEl = document.getElementById('lint-container');
  if (outputEl) {
    outputEl.innerText = '';
  }

  // First click after load: no preserved globals yet.
  // Fallback to full RUN so functions are defined, then auto-dispatch via trigger context.
  if (!window.__codeUiGlobals) {
    window.__codeUiEventDrivenMode = false;
    triggerProjectPythonRun();
    return;
  }

  try {
    await window.pyodide.runPythonAsync(`
import sys

class JSOut:
    def __init__(self):
        self.buffer = ""
    def write(self, s):
        s = str(s)
        if s.strip():
            self.buffer += s + "\\n"
    def flush(self):
        pass

old_out = sys.stdout
sys.stdout = JSOut()
try:
    from js import window as js_window
    import idegui as ui

    g = getattr(js_window, '__codeUiGlobals', globals())

    if hasattr(ui, '_refresh_trigger'):
      ui.trigger.name = "${functionName}"
      ui.trigger.value = "${functionValue}"

    func = g.get("${functionName}")
    if callable(func):
        try:
            func(ui.trigger)
        except TypeError:
            func()
    else:
        print(f"Fehler: Funktion '${functionName}' nicht definiert")

    if hasattr(js_window, '__codeUiGlobals'):
        js_window.__codeUiGlobals = g
finally:
    sys.stdout = old_out
`);
    if (lintEl) {
      lintEl.innerHTML = '<span class="lint-ok">✓</span>';
    }
  } catch (error) {
    if (outputEl) {
      outputEl.innerText = 'Fehler: ' + String(error?.message || error || '').split('\n')[0];
    }
  }
}

function ensureProjectCodeUiRunTriggers(guiContainer) {
  if (!guiContainer || guiContainer.dataset.codeUiRunBound === '1') {
    return;
  }

  guiContainer.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[data-run-python="true"], [data-run="true"], [data-run]');
    if (!trigger || !guiContainer.contains(trigger)) return;
    event.preventDefault();
    setProjectTriggerContext(guiContainer, trigger, false);
    triggerProjectPythonRun();
  });

  guiContainer.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const isRunForm = form.getAttribute('data-run-python') === 'true' || form.getAttribute('data-run') === 'true' || form.hasAttribute('data-run');
    if (!isRunForm) return;
    event.preventDefault();
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : form;
    setProjectTriggerContext(guiContainer, submitter, false);
    triggerProjectPythonRun();
  });

  guiContainer.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[data-function]');
    if (!trigger || !guiContainer.contains(trigger)) return;
    if (trigger.hasAttribute('data-run-python') || trigger.hasAttribute('data-run')) return;
    event.preventDefault();
    setProjectTriggerContext(guiContainer, trigger, true);
    triggerProjectFunctionCall(trigger);
  });

  guiContainer.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute('data-function')) return;
    if (form.getAttribute('data-run-python') === 'true' || form.getAttribute('data-run') === 'true' || form.hasAttribute('data-run')) return;
    event.preventDefault();
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : form;
    const functionTarget = submitter.hasAttribute('data-function') ? submitter : form;
    setProjectTriggerContext(guiContainer, functionTarget, true);
    triggerProjectFunctionCall(functionTarget);
  });

  guiContainer.dataset.codeUiRunBound = '1';
}

async function saveCurrentOpenFile() {
  const editor = getEditorInstance();
  if (!currentProject || !editor) {
    return false;
  }

  if (!currentOpenFileId) {
    const activeFolder = getActiveProjectFolderPath() || await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');
    const initFile = await readProjectFileByPreferredPath(currentProject.id, 'init.py', activeFolder);
    if (!initFile?.fileId) {
      throw new Error('Keine aktive Datei zum Speichern');
    }
    currentOpenFileId = Number(initFile.fileId);
    currentOpenFileName = initFile.fileName || (activeFolder ? `${activeFolder}/init.py` : 'init.py');
  }

  const content = String(editor.getValue() || '');
  cacheCurrentProjectEditorDraft();
  await persistProjectFileContent(currentOpenFileId, currentOpenFileName, content);

  currentOpenFileSnapshot = content;
  setProjectSavedSnapshot(currentOpenFileId, currentOpenFileName, content);
  setProjectDraftContent(currentOpenFileId, currentOpenFileName, content);
  applyProjectFileDirtyMarker(currentOpenFileId);
  return true;
}

async function persistProjectFileContent(fileId, fileName, content) {
  if (!currentProject || !fileId) {
    throw new Error('Keine aktive Datei zum Speichern');
  }

  const response = await fetch('../api/projects/files-v2.php?action=update', {
    method: 'PUT',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      project_id: currentProject.id,
      file_id: Number(fileId),
      content
    })
  });

  const data = await response.json();
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || 'Speichern fehlgeschlagen');
  }

  setProjectSavedSnapshot(fileId, fileName, content);
  setProjectDraftContent(fileId, fileName, content);

  try {
    await syncProjectFileToPyodideRuntime(fileId, fileName, content);
  } catch (runtimeSyncError) {
    console.warn('[projects-editor] Pyodide runtime sync after save failed:', runtimeSyncError);
  }

  // Mark file tree as dirty if HTML/CSS was saved, so next render gets fresh data
  if (fileName && isProjectGuiAssetFile(fileName)) {
    projectFileTreeDirty = true;
    console.log('[projects-editor] HTML/CSS file saved, marked file tree as dirty for refresh');
  }

  await markProjectGuiDirtyForFile(fileId, fileName);
}

async function saveCurrentProjectFile() {
  const editor = getEditorInstance();
  if (!currentProject || !editor || !currentOpenFileId) return true;

  cacheCurrentProjectEditorDraft();

  const fileId = currentOpenFileId;
  if (isProjectFileDirty(fileId)) {
    const content = String(projectDraftFiles[fileId] ?? '');
    const fileName = projectFileNamesById[fileId] || currentOpenFileName || '';
    await persistProjectFileContent(fileId, fileName, content);
    applyProjectFileDirtyMarker(fileId);
    currentOpenFileSnapshot = String(projectSavedSnapshots[fileId] ?? currentOpenFileSnapshot);
  }

  return true;
}

async function saveAllProjectFiles() {
  const editor = getEditorInstance();
  if (!currentProject || !editor) return false;

  cacheCurrentProjectEditorDraft();

  const dirtyFileIds = Object.keys(projectDraftFiles)
    .map((id) => Number(id))
    .filter((id) => id && isProjectFileDirty(id));

  for (const fileId of dirtyFileIds) {
    const content = String(projectDraftFiles[fileId] ?? '');
    const fileName = projectFileNamesById[fileId] || '';
    await persistProjectFileContent(fileId, fileName, content);
    applyProjectFileDirtyMarker(fileId);
  }

  if (currentOpenFileId) {
    currentOpenFileSnapshot = String(projectSavedSnapshots[currentOpenFileId] ?? currentOpenFileSnapshot);
  }

  return true;
}

function showUnsavedChangesModal(fileName = '', options = {}) {
  const modal = document.getElementById('unsaved-changes-modal');
  const fileNameEl = document.getElementById('unsaved-file-name');
  const descriptionEl = document.getElementById('unsaved-changes-description');
  const subtextEl = document.getElementById('unsaved-changes-subtext');
  const saveBtn = document.getElementById('unsaved-save-btn');
  const discardBtn = document.getElementById('unsaved-discard-btn');
  const scope = options?.scope === 'project' ? 'project' : 'file';

  if (!modal) {
    return Promise.resolve('cancel');
  }

  if (fileNameEl) {
    fileNameEl.textContent = fileName || (scope === 'project' ? 'diesem Projekt' : 'dieser Datei');
  }

  if (descriptionEl) {
    if (scope === 'project') {
      descriptionEl.innerHTML = 'Du hast ungespeicherte Änderungen im Projekt <strong id="unsaved-file-name">diesem Projekt</strong>.';
    } else {
      descriptionEl.innerHTML = 'Du hast ungespeicherte Änderungen in <strong id="unsaved-file-name">dieser Datei</strong>.';
    }
    const refreshedFileNameEl = document.getElementById('unsaved-file-name');
    if (refreshedFileNameEl) {
      refreshedFileNameEl.textContent = fileName || (scope === 'project' ? 'diesem Projekt' : 'dieser Datei');
    }
  }

  if (subtextEl) {
    subtextEl.textContent = scope === 'project'
      ? 'Alle Änderungen speichern, verwerfen oder abbrechen?'
      : 'Was möchtest du tun?';
  }

  if (saveBtn) {
    saveBtn.textContent = scope === 'project' ? 'Alle Änderungen speichern' : 'Änderungen speichern';
  }

  if (discardBtn) {
    discardBtn.textContent = scope === 'project' ? 'Änderungen verwerfen' : 'Änderungen verwerfen';
  }

  modal.classList.add('open');

  return new Promise((resolve) => {
    unsavedChoiceResolver = resolve;
  });
}

function resolveUnsavedChangesModal(choice) {
  const modal = document.getElementById('unsaved-changes-modal');
  if (modal) {
    modal.classList.remove('open');
  }

  if (unsavedChoiceResolver) {
    const resolver = unsavedChoiceResolver;
    unsavedChoiceResolver = null;
    resolver(choice);
  }
}

async function confirmDiscardOrSaveCurrentFile() {
  if (!isCurrentFileDirty()) return true;

  const choice = await showUnsavedChangesModal(currentOpenFileName, { scope: 'file' });

  if (choice === 'save') {
    try {
      await saveCurrentOpenFile();
      return true;
    } catch (saveErr) {
      alert('Speichern fehlgeschlagen: ' + (saveErr?.message || saveErr));
      return false;
    }
  }

  if (choice === 'discard') {
    projectSkipNextDraftCache = true;
    return true;
  }

  return false;
}

async function confirmProjectSwitchWithDrafts() {
  cacheCurrentProjectEditorDraft();
  if (!hasUnsavedProjectDrafts()) return true;

  const choice = await showUnsavedChangesModal('diesem Projekt', { scope: 'project' });

  if (choice === 'save') {
    try {
      await saveAllProjectFiles();
      return true;
    } catch (saveErr) {
      alert('Speichern fehlgeschlagen: ' + (saveErr?.message || saveErr));
      return false;
    }
  }

  if (choice === 'discard') {
    projectSkipNextDraftCache = true;
    projectDraftFiles = {};
    refreshAllProjectDirtyMarkers();
    return true;
  }

  return false;
}

async function loadPreferredProjectFile(projectId, projectFallbackCode) {
  const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${projectId}`, {
    credentials: 'include',
    cache: 'reload'
  });
  if (!treeResponse.ok) {
    return {
      fileId: null,
      fileName: 'init.py',
      content: projectFallbackCode || ''
    };
  }

  const treeData = await treeResponse.json();
  const treeNodes = Array.isArray(treeData?.tree)
    ? treeData.tree
    : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

  let fileId = findFileIdByName(treeNodes, 'init.py');
  if (!fileId) {
    fileId = findFileIdByName(treeNodes, `${(currentProject?.name || '').trim()}.py`);
  }

  if (!fileId) {
    const firstPyNode = (function pickFirstPy(nodes) {
      if (!Array.isArray(nodes)) return null;
      for (const node of nodes) {
        if (node?.type === 'file' && typeof node?.name === 'string' && node.name.toLowerCase().endsWith('.py')) {
          return node;
        }
        const nested = pickFirstPy(node?.children);
        if (nested) return nested;
      }
      return null;
    })(treeNodes);
    fileId = firstPyNode?.id || null;
  }

  if (!fileId) {
    return {
      fileId: null,
      fileName: 'init.py',
      content: projectFallbackCode || ''
    };
  }

  const fileData = await readProjectFileById(projectId, fileId);
  if (!fileData) {
    return {
      fileId: null,
      fileName: 'init.py',
      content: projectFallbackCode || ''
    };
  }

  return fileData;
}

/**
 * Initialize projects editor on page load
 */
async function initProjectsEditor() {
  if (projectsEditorInitialized) {
    return;
  }

  if (projectsEditorInitPromise) {
    await projectsEditorInitPromise;
    return;
  }

  projectsEditorInitPromise = (async () => {
    console.log('Initializing projects editor...');

    document.getElementById('project-list-panel')?.classList.add('active');
    
    // Load projects list
    await loadProjects();
    
    // Set up event listeners
    setupEventListeners();
    
    // Auto-load last opened project (DB first, localStorage fallback)
    const localProjectId = localStorage.getItem('lastOpenedProjectId');
    const effectiveLastProjectId = Number(lastOpenedProjectIdFromDb || localProjectId || 0);
    if (effectiveLastProjectId && projects.length > 0) {
      const project = projects.find(p => p.id === effectiveLastProjectId);
      if (project) {
        console.log('[projects-editor] Auto-loading last project:', effectiveLastProjectId);
        await loadProject(project.id);
      } else {
        console.log('[projects-editor] Last project not found, staying on project list');
      }
    }

    projectsEditorInitialized = true;
  })();

  try {
    await projectsEditorInitPromise;
  } catch (initErr) {
    projectsEditorInitPromise = null;
    throw initErr;
  }
}

/**
 * Load all projects from API
 */
async function loadProjects() {
  try {
    const response = await fetch('../api/projects/list.php', {
      credentials: 'include'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load projects');
    }
    
    const data = await response.json();
    projects = data.projects || [];
    lastOpenedProjectIdFromDb = Number(data.last_opened_project_id || 0) || null;
    
    renderProjectList();
  } catch (error) {
    console.error('Error loading projects:', error);
    document.getElementById('project-navigation').innerHTML = 
      '<p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Fehler beim Laden</p>';
  }
}

async function persistLastOpenedProject(projectId) {
  const normalizedProjectId = Number(projectId || 0);
  if (!normalizedProjectId) {
    return;
  }

  localStorage.setItem('lastOpenedProjectId', String(normalizedProjectId));

  try {
    const response = await fetch('../api/projects/set-last-opened.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: normalizedProjectId })
    });

    if (response.ok) {
      lastOpenedProjectIdFromDb = normalizedProjectId;
    }
  } catch (err) {
    console.warn('[projects-editor] Could not persist last opened project in DB:', err);
  }
}

/**
 * Render project list in sidebar
 */
function renderProjectList() {
  const nav = document.getElementById('project-navigation');
  
  if (projects.length === 0) {
    nav.innerHTML = '<p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Keine Projekte vorhanden</p>';
    return;
  }
  
  nav.innerHTML = projects.map(project => `
    <div class="project-nav-item ${currentProject?.id === project.id ? 'active' : ''}" 
         data-project-id="${project.id}">
      <span class="project-nav-title">${escapeHtml(project.name)}</span>
      <span class="project-nav-type">${project.project_type || 'python'}</span>
      <span class="project-nav-delete" data-project-id="${project.id}" data-project-name="${escapeHtml(project.name)}" 
            title="Löschen">🗑️</span>
    </div>
  `).join('');
}

/**
 * Load a specific project
 */
async function loadProject(projectId) {
  const normalizedProjectId = Number(projectId || 0);
  if (!normalizedProjectId) {
    return;
  }

  if (loadProjectPromise && loadProjectIdInFlight === normalizedProjectId) {
    console.log('[projects-editor] loadProject already running for project:', normalizedProjectId, '- skipping duplicate trigger');
    return loadProjectPromise;
  }

  loadProjectIdInFlight = normalizedProjectId;

  loadProjectPromise = (async () => {
  try {
    window.currentProject = null;
    updateWebHelpButton(null);
    setProjectsRightPanelMode(false);
    
    // Reset Pyodide runtime tracking when switching projects
    pyodideRuntimeFolderPath = null;
    pyodideRuntimeModulesDirty = false;
    
    const response = await fetch(`../api/projects/load.php?id=${normalizedProjectId}`, {
      credentials: 'include'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load project');
    }
    
    const data = await response.json();
    const project = data.project;
    const access = data.access;
    
    // Check access
    if (!access || !access.can_edit) {
      alert('Sie haben keine Berechtigung, dieses Projekt zu bearbeiten.');
      return;
    }
    
    currentProject = project;
    window.currentProject = project;
    updateWebHelpButton(project);
    currentOpenFileId = null;
    currentOpenFileName = '';
    currentOpenFileSnapshot = '';
    projectDraftFiles = {};
    projectSavedSnapshots = {};
    projectFileNamesById = {};

    await ensureInitPyExists(normalizedProjectId, project.name, project.code || '');
    
    // Update UI
    const projectTitleEl = document.getElementById('project-page-title');
    if (projectTitleEl) {
      const effectiveProjectName = project.name || 'Projekt';
      projectTitleEl.textContent = effectiveProjectName;
      projectTitleEl.title = effectiveProjectName;
    }
    
    // Render project list with active state
    renderProjectList();
    
    // Update project details panel
    updateProjectDetails(project);
    
    // Load editor code from init.py (fallback to project.code)
    console.log('[projects-editor] Loading project code...');
    const editorReady = await waitForEditorInstance();
    if (editorReady) {
      try {
        const preferredFile = await loadPreferredProjectFile(normalizedProjectId, project.code || '');
        setEditorContent(editorReady, preferredFile.content || '');
        window.editor = editorReady;
        currentOpenFileId = preferredFile.fileId ? Number(preferredFile.fileId) : null;
        currentOpenFileName = preferredFile.fileName || 'init.py';
        currentOpenFileSnapshot = String(preferredFile.content || '');
        setProjectSavedSnapshot(currentOpenFileId, currentOpenFileName, currentOpenFileSnapshot);
        setProjectDraftContent(currentOpenFileId, currentOpenFileName, currentOpenFileSnapshot);
        console.log('[projects-editor] Editor loaded, file:', currentOpenFileName, 'length:', (preferredFile.content || '').length);
      } catch (err) {
        console.warn('[projects-editor] Could not load from file tree, using project.code:', err);
        setEditorContent(editorReady, project.code || '');
        window.editor = editorReady;
        currentOpenFileId = null;
        currentOpenFileName = 'init.py';
        currentOpenFileSnapshot = String(project.code || '');
      }
    } else {
      console.warn('[projects-editor] Editor not available, code not loaded');
    }
    
    // File tree like assignment-test/projects.js: use FileTreeManager first
    console.log('[projects-editor] Starting file tree initialization for project:', normalizedProjectId);
    const treeContainer = document.getElementById('project-file-tree');
    if (treeContainer) {
      treeContainer.innerHTML = '<p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Lade Dateibaum...</p>';
    } else {
      console.error('[projects-editor] Tree container #project-file-tree not found!');
    }

    if (projectFileManager && typeof projectFileManager.destroy === 'function') {
      console.log('[projects-editor] Destroying existing FileTreeManager');
      projectFileManager.destroy();
    }

    console.log('[projects-editor] FileTreeManager available:', typeof window.FileTreeManager !== 'undefined');
    if (typeof window.FileTreeManager !== 'undefined') {
      try {
        console.log('[projects-editor] Creating FileTreeManager instance...');
        projectFileManager = new window.FileTreeManager('project-file-tree', {
          projectId: normalizedProjectId,
          projectName: project.name,
          readOnly: false,
          doubleClickAction: 'open-folder',
          onFolderChanged: async (_folderId, folderPath) => {
            const activeFolder = Array.isArray(folderPath)
              ? folderPath.map((segment) => String(segment?.name || '').trim()).filter(Boolean).join('/')
              : '';
            
            // Track: folder changed, so Pyodide's runtime state is now stale
            if (activeFolder !== pyodideRuntimeFolderPath) {
              pyodideRuntimeModulesDirty = true;
              pyodideRuntimeFolderPath = activeFolder;
              console.log('[projects-editor] Folder changed to:', activeFolder, '- marking Pyodide runtime as dirty');
            }

            if (!currentProject || !isProjectGuiMode(currentProject)) {
              return;
            }
            
            clearProjectOutputPanels();
            setProjectGuiPlaceholder(activeFolder);
          },
          beforeFileSelect: async () => {
            cacheCurrentProjectEditorDraft();
            return true;
          },
          onFileSelected: async (fileId, fileName, content) => {
            await openFileInEditor(fileId, fileName, content);
            console.log('[projects-editor] Opened file from tree:', fileName);
          },
          onFileSaved: () => {
            // File was saved via tree editor: mark Pyodide runtime as dirty
            // so modules are reloaded on next run
            pyodideRuntimeModulesDirty = true;
            console.log('[projects-editor] File saved via tree - marking Pyodide runtime as dirty');
          },
          onFileDeleted: () => {}
        });
        console.log('[projects-editor] FileTreeManager instance created, calling init()...');
        if (typeof projectFileManager.init === 'function') {
          await projectFileManager.init();
          console.log('[projects-editor] FileTreeManager init() completed successfully');
          setTimeout(() => refreshAllProjectDirtyMarkers(), 0);
        } else {
          console.error('[projects-editor] FileTreeManager has no init() method!');
          throw new Error('No init method');
        }
      } catch (treeErr) {
        console.error('[projects-editor] FileTreeManager failed, fallback to manual tree:', treeErr);
        projectFileManager = null;
        await renderFileTreeManually(normalizedProjectId);
      }
    } else {
      console.log('[projects-editor] FileTreeManager not available, using manual tree');
      await renderFileTreeManually(normalizedProjectId);
      setTimeout(() => refreshAllProjectDirtyMarkers(), 0);
    }
    
    // Central mode entry: each mode manages only its own container/runtime state.
    applyProjectModeLayout(project);
    await initializeProjectModeRuntime(project);
    
    // Clear output
    document.getElementById('output-container').textContent = '';
    document.getElementById('plot-container').innerHTML = '';
    
    // Save as last opened project (DB + localStorage fallback)
    await persistLastOpenedProject(normalizedProjectId);
    
    // Auto-open init.py after FileTreeManager is ready
    setTimeout(async () => {
      try {
        const initFile = await readProjectFileByName(projectId, 'init.py');
        if (!currentProject || Number(currentProject.id) !== normalizedProjectId) {
          return;
        }
        const initFileId = Number(initFile?.id || initFile?.fileId || 0);
        if (initFileId) {
          console.log('[projects-editor] Auto-opening init.py:', initFileId);
          await openFileInEditor(initFileId, 'init.py', initFile.content || '');
          markFileInTreeWithRetry(initFileId);
          setTimeout(() => refreshAllProjectDirtyMarkers(), 0);
        } else {
          console.warn('[projects-editor] init.py not found');
        }
      } catch (autoOpenErr) {
        console.warn('[projects-editor] Could not auto-open init.py:', autoOpenErr);
      }
    }, 500);
    
  } catch (error) {
    console.error('Error loading project:', error);
    alert('Fehler beim Laden des Projekts');
  }
  })();

  try {
    return await loadProjectPromise;
  } finally {
    if (loadProjectIdInFlight === normalizedProjectId) {
      loadProjectPromise = null;
      loadProjectIdInFlight = null;
    }
  }
}

/**
 * Update project details panel
 */
function updateProjectDetails(project) {
  const detailsPanel = document.getElementById('project-details-content');
  
  const visibilityLabel = project.visibility === 'public' ? '🌐 Öffentlich' : '🔒 Privat';
  const visibilityClass = project.visibility === 'public' ? 'public' : '';
  
  detailsPanel.innerHTML = `
    <div class="project-info-section">
      <h4>Typ</h4>
      <span class="project-type-badge ${project.project_type || 'python'}">
        ${escapeHtml(getProjectTypeLabel(project))}
      </span>
    </div>
    
    <div class="project-info-section">
      <h4>Beschreibung</h4>
      <div class="project-info-value">
        ${project.description ? escapeHtml(project.description) : '<em>Keine Beschreibung</em>'}
      </div>
    </div>
    
    <div class="project-info-section">
      <h4>Sichtbarkeit</h4>
      <button class="project-visibility-toggle ${visibilityClass}" 
              onclick="toggleProjectVisibility(${project.id}, '${project.visibility}')">
        ${visibilityLabel}
      </button>
    </div>
    
    ${getProjectModeConfig(project).showWebHelp ? `
      <div class="project-info-section">
        <h4>Hilfe</h4>
        <a href="/public/help/idegui/index.html" target="_blank" rel="noopener noreferrer" class="help-link">
          ❓ idegui Dokumentation
        </a>
      </div>
    ` : ''}
  `;
  
  detailsPanel.classList.add('active');
}

/**
 * Toggle project visibility
 */
async function toggleProjectVisibility(projectId, currentVisibility) {
  try {
    const newVisibility = currentVisibility === 'public' ? 'private' : 'public';
    
    const response = await fetch('../api/projects/update.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: projectId, visibility: newVisibility })
    });
    
    if (!response.ok) {
      throw new Error('Failed to update visibility');
    }
    
    // Update current project and UI
    if (currentProject?.id === projectId) {
      currentProject.visibility = newVisibility;
      updateProjectDetails(currentProject);
    }
    
    // Reload project list to reflect changes
    await loadProjects();
    
  } catch (error) {
    console.error('Error toggling visibility:', error);
    alert('Fehler beim Aktualisieren der Sichtbarkeit');
  }
}

/**
 * Render HTML from project index.html
 */
async function renderProjectHtml() {
  try {
    if (!currentProject) return;
    
    const guiContainer = document.getElementById('gui-container');
    if (!guiContainer) {
      console.error('[projects-editor] GUI container not found');
      return;
    }

    // Flush current editor content into draft cache so unsaved edits are picked up
    cacheCurrentProjectEditorDraft();

    const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${currentProject.id}`, {
      credentials: 'include',
      cache: 'reload'
    });

    if (!treeResponse.ok) {
      throw new Error('Failed to load file tree');
    }

    const treeData = await treeResponse.json();
    if (!treeData.ok) {
      throw new Error(treeData.error || 'Failed to load file tree');
    }

    const treeNodes = Array.isArray(treeData?.tree)
      ? treeData.tree
      : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

    const currentDir = getActiveProjectFolderPath() || await resolveProjectDirectory(currentOpenFileId, currentOpenFileName || '');

    const findProjectFileIdByPath = (nodes, targetPath, parentPath = '') => {
      if (!Array.isArray(nodes) || !targetPath) return null;

      for (const node of nodes) {
        if (!node || typeof node.name !== 'string') continue;

        const currentPath = parentPath ? `${parentPath}/${node.name}` : node.name;
        if (node.type === 'file' && currentPath === targetPath) {
          return Number(node.id || 0) || null;
        }

        if (node.type === 'folder') {
          const childId = findProjectFileIdByPath(node.children || [], targetPath, currentPath);
          if (childId) return childId;
        }
      }

      return null;
    };
    
    // Helper to read a project file by name
    const readProjectFile = async (fileName) => {
      console.log(`[projects-editor] Reading file: ${fileName}`);
      let fileId = null;

      if (currentDir) {
        const preferredPath = `${currentDir}/${fileName}`;
        fileId = findProjectFileIdByPath(treeNodes, preferredPath, '');
        if (fileId) {
          console.log(`[projects-editor] Found ${fileName} in current folder:`, preferredPath, fileId);
        }
      }

      const findFile = (nodes) => {
        if (!nodes || !Array.isArray(nodes)) return false;
        for (const node of nodes) {
          if (node.type === 'file' && node.name === fileName) {
            fileId = node.id;
            console.log(`[projects-editor] Found ${fileName} with ID:`, fileId);
            return true;
          }
          if (node.children && findFile(node.children)) {
            return true;
          }
        }
        return false;
      };

      if (!fileId) {
        console.log('[projects-editor] File tree structure:', treeNodes);
        findFile(treeNodes);
      }
      
      if (!fileId) {
        console.warn(`[projects-editor] File not found: ${fileName}`);
        return null; // File not found
      }

      const draft = getProjectDraftContent(fileId);
      if (draft !== null) {
        return draft;
      }
      
      // Read file content
      const fileResponse = await fetch(`../api/projects/files-v2.php?action=read&project_id=${currentProject.id}&file_id=${fileId}`, {
        credentials: 'include',
        cache: 'no-store'
      });
      
      if (!fileResponse.ok) {
        throw new Error(`Failed to read ${fileName}`);
      }
      
      const fileData = await fileResponse.json();
      return fileData.content || null;
    };
    
    // Load index.html
    const htmlContent = await readProjectFile('index.html');
    console.log('[projects-editor] index.html loaded:', htmlContent ? `${htmlContent.length} chars` : 'NOT FOUND');
    if (!htmlContent) {
      // Keep container visible but show placeholder
      setProjectGuiPlaceholder(currentDir);
      return;
    }
    
    // Load style.css
    let cssContent = '';
    try {
      cssContent = await readProjectFile('style.css');
      console.log('[projects-editor] style.css loaded:', cssContent ? `${cssContent.length} chars` : 'NOT FOUND');
    } catch (err) {
      console.warn('[projects-editor] Could not load style.css:', err);
    }
    
    // Clear and set up GUI container
    // BUT: Preserve input values before clearing
    const preservedValues = {};
    const bindingKeyFor = (element) => {
      if (!element || typeof element.getAttribute !== 'function') return '';
      const dataElement = (element.getAttribute('data-element') || '').trim();
      if (dataElement) return dataElement;
      const elementId = typeof element.id === 'string' ? element.id.trim() : '';
      if (elementId) return elementId;
      const nameAttr = (element.getAttribute('name') || '').trim();
      return nameAttr;
    };
    const existingInputs = guiContainer.querySelectorAll('[data-element], [id], [name]');
    existingInputs.forEach((input) => {
      if (!input || input.value === undefined) return;
      const key = bindingKeyFor(input);
      if (key) {
        preservedValues[key] = input.value;
      }
    });
    
    guiContainer.innerHTML = '';
    console.log('[projects-editor] GUI container cleared, parsing HTML...');
    
    // Parse HTML using DOMParser (like assignments.js does) to extract body content
    const parser = new DOMParser();
    const parsed = parser.parseFromString(htmlContent, 'text/html');
    const bodyHtml = parsed?.body?.innerHTML?.trim() || '';
    const inlineStyleTags = parsed?.querySelectorAll?.('style') || [];
    const inlineCss = Array.from(inlineStyleTags).map((tag) => tag.textContent || '').join('\n');
    
    // Inject body HTML into a dedicated stage wrapper so project GUI layouts
    // can stretch to the full available height without affecting non-project modes.
    const stage = document.createElement('div');
    stage.className = 'project-gui-stage';
    stage.innerHTML = bodyHtml;
    guiContainer.appendChild(stage);
    console.log('[projects-editor] HTML content injected');
    
    // Restore preserved input values BEFORE binding triggers
    Object.entries(preservedValues).forEach(([name, value]) => {
      const candidates = guiContainer.querySelectorAll('[data-element], [id], [name]');
      const input = Array.from(candidates).find((el) => bindingKeyFor(el) === name);
      if (input && input.value !== undefined) {
        input.value = value;
      }
    });
    console.log('[projects-editor] Input values restored');
    
    // Bind UI triggers AFTER restoring values
    ensureProjectCodeUiRunTriggers(guiContainer);
    console.log('[projects-editor] UI triggers bound');
    
    // Create and inject scoped CSS
    const styleTag = document.createElement('style');
    styleTag.setAttribute('data-project-style', 'true');
    const mergedCss = [cssContent, inlineCss].filter(Boolean).join('\n\n');
    
    // Scope CSS to #gui-container - exactly like assignments.js
    // Replace body/html selectors and wrap standalone selectors
    let scopedCss = mergedCss
      .replace(/\bbody\b(?=\s*\{)/g, '#gui-container')
      .replace(/\bhtml\b(?=\s*\{)/g, '#gui-container');
    
    styleTag.textContent = scopedCss;
    if (styleTag.textContent.trim()) {
      guiContainer.prepend(styleTag);
      console.log('[projects-editor] Scoped CSS style injected');
    }
    
    guiContainer.classList.add('active');
    setProjectsRightPanelMode(true);
    guiContainer.dataset.projectHtmlRendered = '1';
    guiContainer.dataset.projectHtmlDirty = '0';
    guiContainer.dataset.projectHtmlActiveFolder = String(currentDir || '');
    guiContainer.dataset.projectId = String(currentProject.id);
    console.log('[projects-editor] GUI container marked active');
    
  } catch (error) {
    console.error('[projects-editor] Error rendering HTML:', error);
    // Keep container visible but show error placeholder
    const guiContainer = document.getElementById('gui-container');
    guiContainer.innerHTML = '<p style="color: #888; padding: 20px; text-align: center;">Fehler beim Laden der GUI</p>';
    guiContainer.classList.add('active');
    guiContainer.dataset.projectHtmlRendered = '0';
    setProjectsRightPanelMode(true);
  }
}

/**
 * Render file tree manually (fallback if FileTreeManager not available)
 */
async function renderFileTreeManually(projectId) {
  console.log('[projects-editor] renderFileTreeManually called for project:', projectId);
  try {
    const treeWrapper = document.getElementById('project-file-tree');
    if (!treeWrapper) {
      console.error('[projects-editor] Tree wrapper not found in renderFileTreeManually');
      return;
    }
    treeWrapper.innerHTML = '';
    
    console.log('[projects-editor] Fetching tree from API...');
    const response = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${projectId}`, {
      credentials: 'include',
      cache: 'reload'
    });
    
    if (!response.ok) {
      console.error('[projects-editor] Tree API returned error:', response.status);
      treeWrapper.innerHTML = '<p style="padding: 8px; color: var(--text-secondary); font-size: 12px;">Fehler beim Laden</p>';
      return;
    }
    
    const data = await response.json();
    console.log('[projects-editor] Tree API response:', data);
    if (!data.ok || !data.tree) {
      console.log('[projects-editor] Tree data invalid or missing');
      treeWrapper.innerHTML = '<p style="padding: 8px; color: var(--text-secondary); font-size: 12px;">Keine Dateien</p>';
      return;
    }

    console.log('[projects-editor] Adding create file/folder buttons');
    treeWrapper.insertAdjacentHTML('afterbegin', `
      <div style="display:flex; gap:6px; padding:6px 6px 8px; border-bottom:1px solid var(--border); margin-bottom:6px;">
        <button id="project-tree-new-file" style="font-size:12px; padding:4px 6px; border:1px solid var(--border); background:var(--panel); color:var(--text-primary); border-radius:4px; cursor:pointer;">📄➕ Datei</button>
        <button id="project-tree-new-folder" style="font-size:12px; padding:4px 6px; border:1px solid var(--border); background:var(--panel); color:var(--text-primary); border-radius:4px; cursor:pointer;">📁➕ Ordner</button>
      </div>
    `);

    const rootNodes = Array.isArray(data.tree)
      ? data.tree
      : (Array.isArray(data.tree?.children) ? data.tree.children : []);
    console.log('[projects-editor] Root nodes:', rootNodes.length, 'items');

    const renderNode = (node, depth = 0) => {
      const padding = 10 + depth * 14;
      if (node?.type === 'folder') {
        return `
          <div class="project-tree-folder" data-folder-id="${node.id}" style="padding-left:${padding}px; padding-top:4px; padding-bottom:4px; cursor:pointer; color:var(--text-primary); user-select:none;">📁 ${escapeHtml(node.name || 'Ordner')}</div>
          <div class="project-tree-children" data-parent-folder="${node.id}" style="display:none;">
            ${(node.children || []).map(child => renderNode(child, depth + 1)).join('')}
          </div>
        `;
      }
      if (node?.type === 'file') {
        return `<div class="project-tree-file" data-file-id="${node.id}" style="padding-left:${padding}px; padding-top:4px; padding-bottom:4px; cursor:pointer; color:var(--text-secondary); user-select:none;">📄 ${escapeHtml(node.name || 'datei')}</div>`;
      }
      return '';
    };

    if (!rootNodes.length) {
      console.log('[projects-editor] No root nodes, showing empty message');
      treeWrapper.insertAdjacentHTML('beforeend', '<p style="padding: 8px; color: var(--text-secondary); font-size: 12px;">Keine Dateien</p>');
    } else {
      console.log('[projects-editor] Rendering tree nodes...');
      treeWrapper.insertAdjacentHTML('beforeend', rootNodes.map(node => renderNode(node)).join(''));
      console.log('[projects-editor] Tree nodes rendered');
    }

    const createNode = async (type) => {
      const name = prompt(type === 'file' ? 'Dateiname:' : 'Ordnername:', type === 'file' ? 'new_file.py' : 'new_folder');
      if (!name) return;
      const endpoint = type === 'file'
        ? '../api/projects/files-v2.php?action=create'
        : '../api/projects/folders-v2.php?action=create';
      const payload = type === 'file'
        ? { project_id: projectId, folder_id: null, name: name.trim(), content: '' }
        : { project_id: projectId, parent_folder_id: null, name: name.trim() };

      const createResponse = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const createData = await createResponse.json();
      if (!createResponse.ok || !createData?.ok) {
        alert(createData?.error || 'Erstellen fehlgeschlagen');
        return;
      }
      projectFileTreeDirty = true; // Mark tree as dirty since file/folder was created
      await renderFileTreeManually(projectId);
    };

    document.getElementById('project-tree-new-file')?.addEventListener('click', () => {
      createNode('file').catch(err => console.error('Create file failed:', err));
    });
    document.getElementById('project-tree-new-folder')?.addEventListener('click', () => {
      createNode('folder').catch(err => console.error('Create folder failed:', err));
    });

    console.log('[projects-editor] Attaching folder click handlers...');
    treeWrapper.querySelectorAll('.project-tree-folder').forEach((folderEl) => {
      folderEl.addEventListener('click', (e) => {
        e.stopPropagation();
        const folderId = folderEl.getAttribute('data-folder-id');
        const children = treeWrapper.querySelector(`.project-tree-children[data-parent-folder="${folderId}"]`);
        if (!children) return;
        children.style.display = children.style.display === 'none' ? 'block' : 'none';
      });
    });

    console.log('[projects-editor] Attaching file click handlers...');
    treeWrapper.querySelectorAll('.project-tree-file').forEach((fileEl) => {
      fileEl.addEventListener('click', async (e) => {
        e.stopPropagation();
        cacheCurrentProjectEditorDraft();
        const fileId = fileEl.getAttribute('data-file-id');
        if (!fileId) return;
        
        // Mark as selected immediately
        treeWrapper.querySelectorAll('.project-tree-file').forEach(el => {
          el.style.backgroundColor = '';
          el.style.color = 'var(--text-secondary)';
        });
        fileEl.style.backgroundColor = 'var(--accent-color)';
        fileEl.style.color = '#fff';
        
        try {
          const fileResponse = await fetch(`../api/projects/files-v2.php?action=read&project_id=${projectId}&file_id=${encodeURIComponent(fileId)}`, {
            credentials: 'include',
            cache: 'no-store'
          });
          if (!fileResponse.ok) return;
          const fileData = await fileResponse.json();
          if (fileData?.ok) {
            await openFileInEditor(fileId, fileData.name || '', fileData.content || '');
          }
        } catch (readErr) {
          console.error('Error opening file from tree:', readErr);
        }
      });
    });
    
    console.log('[projects-editor] renderFileTreeManually completed successfully');
  } catch (error) {
    console.error('[projects-editor] Error rendering file tree:', error);
  }
}

/**
 * Show delete project confirmation modal
 */
function showDeleteProjectModal(projectId, projectName) {
  document.getElementById('delete-project-name').textContent = projectName;
  document.getElementById('delete-project-modal').classList.add('open');
  window.projectToDelete = projectId;
}

/**
 * Confirm and delete project
 */
async function confirmDeleteProject() {
  const projectId = window.projectToDelete;
  if (!projectId) return;
  
  try {
    const response = await fetch('../api/projects/delete.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: projectId })
    });
    
    if (!response.ok) {
      throw new Error('Failed to delete project');
    }
    
    // Close modal
    document.getElementById('delete-project-modal').classList.remove('open');
    
    // If deleted project was current, clear editor
    if (currentProject?.id === projectId) {
      currentProject = null;
      window.currentProject = null;
      setProjectsRightPanelMode(false);
      if (window.editor) {
        window.editor.setValue('');
      }
      document.getElementById('project-details-content').classList.remove('active');
    }
    
    // Reload projects
    await loadProjects();
    
  } catch (error) {
    console.error('Error deleting project:', error);
    alert('Fehler beim Löschen des Projekts');
  }
}

/**
 * Create project from dialog
 */
async function createProjectFromDialog() {
  const name = document.getElementById('project-name-input').value.trim();
  const template = document.getElementById('project-template-input')?.value || 'empty_python';
  const description = document.getElementById('project-desc-input').value.trim();
  
  if (!name) {
    alert('Bitte geben Sie einen Projektnamen ein');
    return;
  }
  
  try {
    const response = await fetch('../api/projects/create.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name,
        description,
        template,
        visibility: 'private',
        code: ''
      })
    });
    
    if (!response.ok) {
      throw new Error('Failed to create project');
    }
    
    const data = await response.json();
    const newProjectId = data?.project?.id || data?.project_id;
    if (!newProjectId) {
      throw new Error('Ungültige Projekt-Antwort');
    }
    
    // Close modal
    document.getElementById('create-project-modal').classList.remove('open');
    
    // Clear form
    document.getElementById('project-name-input').value = '';
    document.getElementById('project-desc-input').value = '';
    const templateSelect = document.getElementById('project-template-input');
    if (templateSelect) {
      templateSelect.value = 'empty_python';
    }
    
    // Reload projects and load the new one
    await loadProjects();
    await loadProject(newProjectId);
    
  } catch (error) {
    console.error('Error creating project:', error);
    alert('Fehler beim Erstellen des Projekts');
  }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
  if (!projectNavBound) {
    const nav = document.getElementById('project-navigation');
    nav?.addEventListener('click', async (e) => {
      const item = e.target.closest('.project-nav-item');
      if (item && !e.target.closest('.project-nav-delete')) {
        const projectId = parseInt(item.dataset.projectId, 10);
        if (!Number.isNaN(projectId)) {
          if (currentProject?.id !== projectId) {
            const canSwitch = await confirmProjectSwitchWithDrafts();
            if (!canSwitch) return;
          }
          loadProject(projectId);
        }
      }

      const deleteBtn = e.target.closest('.project-nav-delete');
      if (deleteBtn) {
        e.stopPropagation();
        const projectId = parseInt(deleteBtn.dataset.projectId, 10);
        const projectName = deleteBtn.dataset.projectName;
        if (!Number.isNaN(projectId)) {
          showDeleteProjectModal(projectId, projectName);
        }
      }
    });
    projectNavBound = true;
  }

  // Save project button
  document.getElementById('save-project-btn')?.addEventListener('click', async () => {
    try {
      await saveCurrentOpenFile();
      // Mark Pyodide runtime as dirty: user changed a project file
      pyodideRuntimeModulesDirty = true;
      console.log('[projects-editor] File saved:', currentOpenFileName, '- marking Pyodide runtime as dirty');
    } catch (error) {
      console.error('[projects-editor] Save failed:', error);
      alert('Speichern fehlgeschlagen');
    }
  });

  document.getElementById('save-all-project-btn')?.addEventListener('click', async () => {
    try {
      await saveAllProjectFiles();
      // Mark Pyodide runtime as dirty: user may have changed any project file
      pyodideRuntimeModulesDirty = true;
      console.log('[projects-editor] All files saved - marking Pyodide runtime as dirty');
    } catch (error) {
      console.error('[projects-editor] Save all failed:', error);
      alert('Alle speichern fehlgeschlagen');
    }
  });

  document.getElementById('project-export-btn')?.addEventListener('click', async () => {
    await exportCurrentProjectToFile();
  });

  // Restore editor undo/redo toolbar controls for project mode.
  const undoBtn = document.getElementById('undo-btn');
  const redoBtn = document.getElementById('redo-btn');
  if (undoBtn) {
    undoBtn.style.display = 'inline-block';
    if (!undoBtn.dataset.bound) {
      undoBtn.addEventListener('click', () => {
        const editor = getEditorInstance();
        if (!editor || typeof editor.trigger !== 'function') return;
        editor.focus?.();
        editor.trigger('', 'undo');
      });
      undoBtn.dataset.bound = '1';
    }
  }

  if (redoBtn) {
    redoBtn.style.display = 'inline-block';
    if (!redoBtn.dataset.bound) {
      redoBtn.addEventListener('click', () => {
        const editor = getEditorInstance();
        if (!editor || typeof editor.trigger !== 'function') return;
        editor.focus?.();
        editor.trigger('', 'redo');
      });
      redoBtn.dataset.bound = '1';
    }
  }

  document.getElementById('project-import-btn')?.addEventListener('click', async () => {
    const canSwitch = await confirmProjectSwitchWithDrafts();
    if (!canSwitch) return;

    const input = document.getElementById('project-import-file-input');
    if (!input) {
      alert('Import-Eingabe nicht gefunden.');
      return;
    }

    input.value = '';
    input.click();
  });

  document.getElementById('project-import-file-input')?.addEventListener('change', async (event) => {
    const input = event.target;
    const file = input?.files?.[0] || null;
    await importProjectFromArchiveFile(file);
    if (input) {
      input.value = '';
    }
  });

  if (!projectEditorDraftListenerBound) {
    waitForEditorInstance().then((editor) => {
      if (!editor || projectEditorDraftListenerBound) return;
      editor.onDidChangeModelContent(() => {
        cacheCurrentProjectEditorDraft();
      });
      projectEditorDraftListenerBound = true;
    });
  }

  document.getElementById('web-help-btn')?.addEventListener('click', () => {
    const basePath = window.location.pathname.replace(/\/projects\.php$/i, '');
    const helpUrl = `${window.location.origin}${basePath}/help/idegui/index.html`;
    window.open(helpUrl, 'idegui_help', 'noopener,noreferrer,width=1200,height=900');
  });
  
  // Close modals on overlay click
  document.getElementById('create-project-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'create-project-modal') {
      e.target.classList.remove('open');
    }
  });
  
  document.getElementById('delete-project-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'delete-project-modal') {
      e.target.classList.remove('open');
    }
  });

  document.getElementById('unsaved-save-btn')?.addEventListener('click', () => {
    resolveUnsavedChangesModal('save');
  });

  document.getElementById('unsaved-discard-btn')?.addEventListener('click', () => {
    resolveUnsavedChangesModal('discard');
  });

  document.getElementById('unsaved-cancel-btn')?.addEventListener('click', () => {
    resolveUnsavedChangesModal('cancel');
  });

  document.getElementById('unsaved-changes-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'unsaved-changes-modal') {
      resolveUnsavedChangesModal('cancel');
    }
  });
}

/**
 * Save project code to API
 */
async function saveProjectCode(projectId, code) {
  try {
    const response = await fetch('../api/projects/update.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: projectId, code })
    });
    
    if (!response.ok) {
      throw new Error('Failed to save project');
    }
    
    console.log('Project saved successfully');
  } catch (error) {
    console.error('Error saving project:', error);
  }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initProjectsEditor);

// Expose functions to global scope for module context
window.initProjectsEditor = initProjectsEditor;
window.loadProjects = loadProjects;
window.loadProject = loadProject;
window.showDeleteProjectModal = showDeleteProjectModal;
window.confirmDeleteProject = confirmDeleteProject;
window.createProjectFromDialog = createProjectFromDialog;
window.toggleProjectVisibility = toggleProjectVisibility;
window.beforeRunExecution = beforeRunExecution;
window.getProjectRunContext = getProjectRunContext;
window.getProjectPythonRuntimePayload = getProjectPythonRuntimePayload;
window.resetPyodideModulesIfNeeded = resetPyodideModulesIfNeeded;
window.getCurrentProjectOpenFileName = () => String(currentOpenFileName || '');
