/**
 * Projects Editor - Manages project loading, creation, deletion, and UI updates
 * Based on assignment_editor.php structure but adapted for projects
 */

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
  if (!currentProject || !isHtmlLikeProject(currentProject) || !isProjectGuiAssetFile(fileName)) {
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

  if (currentProject && isHtmlLikeProject(currentProject) && previousDir !== nextDir) {
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

function isHtmlLikeProject(project) {
  const type = String(project?.project_type || '').toLowerCase();
  return type === 'html' || type === 'mixed';
}

function isDbSmallProject(project) {
  const type = String(project?.project_type || '').toLowerCase();
  return type === 'db_small';
}

function getProjectTypeLabel(projectType) {
  const type = String(projectType || '').toLowerCase();
  if (type === 'html') return 'HTML/Web';
  if (type === 'mixed') return 'Gemischt';
  if (type === 'db_small') return 'DB Small';
  return 'Python';
}

const DB_SMALL_MODEL_FILE = 'db_model.json';
const DB_SMALL_SQL_FILE = 'db_export.sql';
const dbSmallDesignerState = {
  projectId: 0,
  modelFileId: 0,
  sqlFileId: 0,
  model: null,
  selectedDatabaseIndex: 0,
  selectedTableIndex: 0,
  isDirty: false,
  lastSavedAt: '',
  rowDrafts: {},
  sortStates: {},
  selectedRowIndex: -1,
  activeCell: {
    rowIndex: -1,
    colName: '',
    pendingFocus: false
  },
  tableNameEdit: {
    key: '',
    active: false,
    value: ''
  }
};

function markDbSmallDirty() {
  dbSmallDesignerState.isDirty = true;
}

function markDbSmallSaved() {
  dbSmallDesignerState.isDirty = false;
  dbSmallDesignerState.lastSavedAt = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function getDbSmallSaveStatusHtml() {
  if (dbSmallDesignerState.isDirty) {
    return '<span style="color:#b45309;font-weight:600;">Nicht gespeichert</span>';
  }
  if (dbSmallDesignerState.lastSavedAt) {
    return `<span style="color:#065f46;font-weight:600;">Gespeichert (${escapeHtml(dbSmallDesignerState.lastSavedAt)})</span>`;
  }
  return '<span style="color:var(--text-secondary);">Noch nicht gespeichert</span>';
}

function createDefaultDbSmallModel() {
  return {
    version: 2,
    activeDatabaseIndex: 0,
    databases: [
      {
        name: 'Zwischenstand 1',
        tables: [
          {
            name: 'student',
            columns: [
              { name: 'id', type: 'AUTO', pk: true, fk: false, default: '' },
              { name: 'name', type: 'VARCHAR', length: '50', pk: false, fk: false, integerVariant: 'INT', floatVariant: 'FLOAT', default: '' }
            ],
            rows: [
              { name: 'Ada' },
              { name: 'Turing' }
            ]
          }
        ]
      }
    ]
  };
}

function normalizeDbSmallDatabaseName(value, fallbackPrefix = 'db') {
  const raw = String(value || '').trim();
  if (raw) return raw;
  return `${fallbackPrefix}_${Date.now()}`;
}

function normalizeDbSmallTable(table, tIndex) {
  const tableName = String(table?.name || `table_${tIndex + 1}`).trim() || `table_${tIndex + 1}`;
  const cols = Array.isArray(table?.columns) ? table.columns : [];
  const normalizedCols = cols.map((col, cIndex) => {
    const isPk = !!col?.pk;
    const normalizedType = normalizeDbSmallType(col?.type || 'AUTO');
    const normalizedIntegerVariant = normalizeDbSmallIntegerVariant(col?.type || 'INTEGER', col?.integerVariant);
    const normalizedFloatVariant = normalizeDbSmallFloatVariant(col?.type || 'FLOAT', col?.floatVariant);
    const normalizedLength = normalizedType === 'VARCHAR'
      ? String(col?.length ?? '').trim() || '50'
      : '';
    return {
      name: String(col?.name || `col_${cIndex + 1}`).trim() || `col_${cIndex + 1}`,
      type: isPk ? 'AUTO' : normalizedType,
      length: isPk ? '' : normalizedLength,
      pk: isPk,
      fk: !!col?.fk,
      integerVariant: isPk ? 'INT' : normalizedIntegerVariant,
      floatVariant: isPk ? 'FLOAT' : normalizedFloatVariant,
      default: String(col?.default || '')
    };
  });

  const rows = Array.isArray(table?.rows) ? table.rows : [];
  const normalizedRows = rows.map((row) => {
    const normalizedRow = {};
    normalizedCols.forEach((col) => {
      normalizedRow[col.name] = row && Object.prototype.hasOwnProperty.call(row, col.name)
        ? String(row[col.name] ?? '')
        : '';
    });
    return normalizedRow;
  });

  return {
    name: tableName,
    columns: normalizedCols.length > 0 ? normalizedCols : [{ name: 'id', type: 'AUTO', pk: true, fk: false, default: '' }],
    rows: normalizedRows
  };
}

function normalizeDbSmallDatabase(database, dbIndex) {
  const tables = Array.isArray(database?.tables) ? database.tables : [];
  const normalizedTables = tables.map((table, tIndex) => normalizeDbSmallTable(table, tIndex));

  return {
    name: normalizeDbSmallDatabaseName(database?.name, `db_${dbIndex + 1}`),
    tables: normalizedTables.length > 0 ? normalizedTables : createDefaultDbSmallModel().databases[0].tables.map((table, tIndex) => normalizeDbSmallTable(table, tIndex))
  };
}

function getDbSmallDatabases(model) {
  if (Array.isArray(model?.databases) && model.databases.length > 0) {
    return model.databases;
  }
  return createDefaultDbSmallModel().databases;
}

function getSelectedDbSmallDatabaseIndex(model) {
  const databases = getDbSmallDatabases(model);
  if (databases.length === 0) return 0;
  const idx = Number(dbSmallDesignerState.selectedDatabaseIndex || 0);
  if (idx < 0) return 0;
  if (idx >= databases.length) return databases.length - 1;
  return idx;
}

function getSelectedDbSmallDatabase(model) {
  const databases = getDbSmallDatabases(model);
  const index = getSelectedDbSmallDatabaseIndex(model);
  return {
    database: databases[index] || databases[0] || null,
    index
  };
}

function normalizeDbSmallIdentifier(value, fallbackPrefix = 't') {
  const raw = String(value || '').trim();
  const cleaned = raw.replace(/[^A-Za-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
  if (!cleaned) return `${fallbackPrefix}_${Date.now()}`;
  if (/^[0-9]/.test(cleaned)) return `${fallbackPrefix}_${cleaned}`;
  return cleaned;
}

function normalizeDbSmallType(rawType) {
  const upper = String(rawType || '').trim().toUpperCase();
  if (upper === 'AUTO') return 'AUTO';
  if (/^(INT|INTEGER|BIGINT|SMALLINT|TINYINT)$/.test(upper)) return 'INTEGER';
  if (/^(DECIMAL|NUMERIC|FLOAT|DOUBLE|REAL)/.test(upper)) return 'FLOAT';
  if (/^(TEXT|VARCHAR|CHAR|STRING)/.test(upper)) return 'VARCHAR';
  if (upper === 'DATE' || upper === 'DATETIME') return upper;
  if (upper === 'BOOLEAN' || upper === 'BOOL') return 'BOOLEAN';
  return 'VARCHAR';
}

function normalizeDbSmallIntegerVariant(rawType, explicitVariant = '') {
  const explicit = String(explicitVariant || '').trim().toUpperCase();
  if (['TINYINT', 'SMALLINT', 'INT', 'BIGINT'].includes(explicit)) {
    return explicit;
  }

  const upper = String(rawType || '').trim().toUpperCase();
  if (upper === 'TINYINT') return 'TINYINT';
  if (upper === 'SMALLINT') return 'SMALLINT';
  if (upper === 'BIGINT') return 'BIGINT';
  return 'INT';
}

function normalizeDbSmallFloatVariant(rawType, explicitVariant = '') {
  const explicit = String(explicitVariant || '').trim().toUpperCase();
  if (['DECIMAL', 'FLOAT', 'DOUBLE', 'NUMERIC'].includes(explicit)) {
    return explicit;
  }

  const upper = String(rawType || '').trim().toUpperCase();
  if (upper === 'DECIMAL') return 'DECIMAL';
  if (upper === 'DOUBLE') return 'DOUBLE';
  if (upper === 'NUMERIC') return 'NUMERIC';
  return 'FLOAT';
}

function inferAutoDbSmallType(values, fallback = 'VARCHAR') {
  const nonEmpty = (values || []).map((v) => String(v ?? '').trim()).filter((v) => v !== '');
  if (nonEmpty.length === 0) return fallback;

  const isInt = nonEmpty.every((v) => /^-?\d+$/.test(v));
  if (isInt) return 'INTEGER';

  const isReal = nonEmpty.every((v) => /^-?\d+(\.\d+)?$/.test(v));
  if (isReal) return 'FLOAT';

  const isBool = nonEmpty.every((v) => /^(true|false|0|1)$/i.test(v));
  if (isBool) return 'BOOLEAN';

  const isDate = nonEmpty.every((v) => /^\d{4}-\d{2}-\d{2}$/.test(v));
  if (isDate) return 'DATE';

  const isDatetime = nonEmpty.every((v) => /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/.test(v));
  if (isDatetime) return 'DATETIME';

  return 'VARCHAR';
}

function normalizeDbSmallModel(model) {
  const base = model && typeof model === 'object' ? model : {};
  const databasesSource = Array.isArray(base.databases) && base.databases.length > 0
    ? base.databases
    : [{
        name: String(base.databaseName || base.name || 'Zwischenstand 1'),
        tables: Array.isArray(base.tables) ? base.tables : []
      }];

  const normalizedDatabases = databasesSource.map((database, dbIndex) => normalizeDbSmallDatabase(database, dbIndex));
  const activeDatabaseIndex = normalizedDatabases.length > 0
    ? Math.min(Math.max(Number(base.activeDatabaseIndex || 0), 0), normalizedDatabases.length - 1)
    : 0;

  return {
    version: 2,
    activeDatabaseIndex,
    databases: normalizedDatabases.length > 0 ? normalizedDatabases : createDefaultDbSmallModel().databases
  };
}

function escapeSqlIdentifier(name) {
  return String(name || '').replace(/`/g, '``');
}

function toSqlLiteral(value, type) {
  const raw = String(value ?? '').trim();
  if (raw === '') return 'NULL';

  const upperType = normalizeDbSmallType(type);
  if (upperType === 'INTEGER' || upperType === 'FLOAT') {
    if (/^-?\d+(\.\d+)?$/.test(raw)) return raw;
  }
  if (upperType === 'BOOLEAN') {
    if (raw.toLowerCase() === 'true') return '1';
    if (raw.toLowerCase() === 'false') return '0';
  }

  return `'${raw.replace(/'/g, "''")}'`;
}

function generateDbSmallSql(model) {
  const normalized = normalizeDbSmallModel(model);
  const statements = [];
  normalized.databases.forEach((database, dbIndex) => {
    const databaseName = normalizeDbSmallIdentifier(database.name, `db${dbIndex + 1}`);
    statements.push(`-- Datenbank: ${database.name}`);
    statements.push(`CREATE DATABASE IF NOT EXISTS \`${escapeSqlIdentifier(databaseName)}\`;`);
    statements.push(`USE \`${escapeSqlIdentifier(databaseName)}\`;`);

    (database.tables || []).forEach((table, tIndex) => {
      const tableName = normalizeDbSmallIdentifier(table.name, `table${tIndex + 1}`);
      const validColumns = (table.columns || []).map((col, cIndex) => ({
        ...col,
        name: normalizeDbSmallIdentifier(col.name, `col${cIndex + 1}`)
      }));
      if (validColumns.length === 0) return;

      const resolvedTypesByColumn = {};
      validColumns.forEach((col) => {
        const tableValues = (table.rows || []).map((row) => row?.[col.name]);
        resolvedTypesByColumn[col.name] = normalizeDbSmallType(col.type) === 'AUTO'
          ? inferAutoDbSmallType(tableValues, col.pk ? 'INTEGER' : 'VARCHAR')
          : normalizeDbSmallType(col.type);
      });

      const colDefs = validColumns.map((col) => {
        const resolvedType = resolvedTypesByColumn[col.name] || 'VARCHAR';
        let sqlType = resolvedType;
        if (resolvedType === 'VARCHAR') {
          sqlType = `VARCHAR(${Math.max(1, Number.parseInt(String(col.length || '50'), 10) || 50)})`;
        } else if (resolvedType === 'INTEGER') {
          sqlType = normalizeDbSmallIntegerVariant(col.type, col.integerVariant);
        } else if (resolvedType === 'FLOAT') {
          sqlType = normalizeDbSmallFloatVariant(col.type, col.floatVariant);
        }

        const parts = [`\`${escapeSqlIdentifier(col.name)}\``, sqlType];
        if (col.fk) {
          parts.push('/* FK */');
        }
        return parts.join(' ');
      });

      const pkCols = validColumns.filter((col) => col.pk).map((col) => `\`${escapeSqlIdentifier(col.name)}\``);
      if (pkCols.length > 0) {
        colDefs.push(`PRIMARY KEY (${pkCols.join(', ')})`);
      }

      statements.push(`CREATE TABLE \`${escapeSqlIdentifier(tableName)}\` (\n  ${colDefs.join(',\n  ')}\n);`);

      const insertColumns = validColumns.filter((col) => !col.pk);

      (table.rows || []).forEach((row) => {
        if (insertColumns.length === 0) {
          statements.push(`INSERT INTO \`${escapeSqlIdentifier(tableName)}\` DEFAULT VALUES;`);
          return;
        }

        const hasAnyValue = insertColumns.some((col) => String(row?.[col.name] ?? '').trim() !== '');
        if (!hasAnyValue) return;

        const colNames = insertColumns.map((col) => `\`${escapeSqlIdentifier(col.name)}\``).join(', ');
        const values = insertColumns.map((col) => toSqlLiteral(row?.[col.name], resolvedTypesByColumn[col.name] || col.type)).join(', ');
        statements.push(`INSERT INTO \`${escapeSqlIdentifier(tableName)}\` (${colNames}) VALUES (${values});`);
      });
    });

    statements.push('');
  });

  if (statements.length === 0) {
    return '-- Keine Tabellen definiert.';
  }
  return `${statements.filter((line, index, arr) => !(line === '' && arr[index - 1] === '')).join('\n\n')}\n`;
}

function splitSqlByTopLevelComma(text) {
  const source = String(text || '');
  const parts = [];
  let current = '';
  let depth = 0;
  let inQuote = false;

  for (let i = 0; i < source.length; i += 1) {
    const ch = source[i];
    const prev = i > 0 ? source[i - 1] : '';

    if (ch === "'" && prev !== '\\') {
      inQuote = !inQuote;
      current += ch;
      continue;
    }
    if (!inQuote && ch === '(') {
      depth += 1;
      current += ch;
      continue;
    }
    if (!inQuote && ch === ')') {
      depth = Math.max(0, depth - 1);
      current += ch;
      continue;
    }
    if (!inQuote && depth === 0 && ch === ',') {
      parts.push(current.trim());
      current = '';
      continue;
    }
    current += ch;
  }

  if (current.trim()) {
    parts.push(current.trim());
  }
  return parts;
}

function splitSqlStatements(sqlText) {
  const source = String(sqlText || '');
  const statements = [];
  let current = '';
  let inQuote = false;

  for (let i = 0; i < source.length; i += 1) {
    const ch = source[i];
    const prev = i > 0 ? source[i - 1] : '';
    if (ch === "'" && prev !== '\\') {
      inQuote = !inQuote;
      current += ch;
      continue;
    }
    if (!inQuote && ch === ';') {
      const trimmed = current.trim();
      if (trimmed) statements.push(trimmed);
      current = '';
      continue;
    }
    current += ch;
  }

  const tail = current.trim();
  if (tail) statements.push(tail);
  return statements;
}

function parseSqlValueToken(rawToken) {
  const token = String(rawToken || '').trim();
  if (!token || /^NULL$/i.test(token)) return '';
  if (token.startsWith("'") && token.endsWith("'")) {
    return token.slice(1, -1).replace(/''/g, "'");
  }
  return token;
}

function parseDbSmallSqlImport(sqlText) {
  const linesWithoutComments = String(sqlText || '')
    .split(/\r?\n/)
    .filter((line) => !line.trim().startsWith('--'))
    .join('\n');
  const statements = splitSqlStatements(linesWithoutComments);
  const databases = [];
  const dbIndexByName = new Map();
  let currentDbName = 'Importierte DB';

  const ensureDatabase = (name) => {
    const normalizedName = String(name || '').trim() || 'Importierte DB';
    if (dbIndexByName.has(normalizedName)) {
      return databases[dbIndexByName.get(normalizedName)];
    }
    const database = { name: normalizedName, tables: [] };
    dbIndexByName.set(normalizedName, databases.length);
    databases.push(database);
    return database;
  };

  const findOrCreateTable = (database, tableName) => {
    const normalizedTableName = String(tableName || '').trim() || `table_${database.tables.length + 1}`;
    let table = database.tables.find((item) => String(item.name || '').toLowerCase() === normalizedTableName.toLowerCase());
    if (!table) {
      table = {
        name: normalizedTableName,
        columns: [{ name: 'id', type: 'AUTO', length: '', pk: true, fk: false, default: '' }],
        rows: []
      };
      database.tables.push(table);
    }
    return table;
  };

  statements.forEach((statement) => {
    const stmt = statement.trim();
    if (!stmt) return;

    let match = stmt.match(/^CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([^`\s]+)`?/i);
    if (match) {
      currentDbName = match[1];
      ensureDatabase(currentDbName);
      return;
    }

    match = stmt.match(/^USE\s+`?([^`\s]+)`?/i);
    if (match) {
      currentDbName = match[1];
      ensureDatabase(currentDbName);
      return;
    }

    match = stmt.match(/^CREATE\s+TABLE\s+`?([^`\s(]+)`?\s*\(([^\s\S]*)\)$/i);
    if (!match) {
      match = stmt.match(/^CREATE\s+TABLE\s+`?([^`\s(]+)`?\s*\(([\s\S]+)\)$/i);
    }
    if (match) {
      const tableName = match[1];
      const defsBlock = match[2];
      const defs = splitSqlByTopLevelComma(defsBlock);
      const columns = [];
      const pkNames = new Set();

      defs.forEach((definition) => {
        const def = String(definition || '').trim();
        if (!def) return;

        const pkMatch = def.match(/^PRIMARY\s+KEY\s*\((.+)\)$/i);
        if (pkMatch) {
          const pkCols = splitSqlByTopLevelComma(pkMatch[1]).map((part) => part.replace(/`/g, '').trim()).filter(Boolean);
          pkCols.forEach((name) => pkNames.add(name));
          return;
        }

        const colMatch = def.match(/^`?([^`\s]+)`?\s+([A-Z]+)(?:\(([^)]+)\))?/i);
        if (!colMatch) return;

        const colName = colMatch[1];
        const sqlType = String(colMatch[2] || '').toUpperCase();
        const sqlTypeArgs = String(colMatch[3] || '').trim();
        const col = {
          name: colName,
          type: 'VARCHAR',
          length: '50',
          pk: false,
          fk: /\/\*\s*FK\s*\*\//i.test(def),
          integerVariant: 'INT',
          floatVariant: 'FLOAT',
          default: ''
        };

        if (/^(TINYINT|SMALLINT|INT|INTEGER|BIGINT)$/.test(sqlType)) {
          col.type = 'INTEGER';
          col.length = '';
          col.integerVariant = normalizeDbSmallIntegerVariant(sqlType);
        } else if (/^(DECIMAL|FLOAT|DOUBLE|NUMERIC|REAL)$/.test(sqlType)) {
          col.type = 'FLOAT';
          col.length = '';
          col.floatVariant = normalizeDbSmallFloatVariant(sqlType);
        } else if (/^(VARCHAR|CHAR|TEXT|STRING)$/.test(sqlType)) {
          col.type = 'VARCHAR';
          const firstArg = Number.parseInt(String(sqlTypeArgs).split(',')[0], 10);
          col.length = String(Number.isFinite(firstArg) && firstArg > 0 ? firstArg : 50);
        } else if (sqlType === 'DATE' || sqlType === 'DATETIME') {
          col.type = sqlType;
          col.length = '';
        } else if (sqlType === 'BOOLEAN' || sqlType === 'BOOL') {
          col.type = 'BOOLEAN';
          col.length = '';
        }

        columns.push(col);
      });

      columns.forEach((col) => {
        if (!pkNames.has(col.name)) return;
        col.pk = true;
        col.type = 'AUTO';
        col.length = '';
        col.integerVariant = 'INT';
        col.floatVariant = 'FLOAT';
      });

      const database = ensureDatabase(currentDbName);
      const table = {
        name: tableName,
        columns: columns.length > 0 ? columns : [{ name: 'id', type: 'AUTO', length: '', pk: true, fk: false, default: '' }],
        rows: []
      };
      const existingIndex = database.tables.findIndex((item) => String(item.name || '').toLowerCase() === String(tableName).toLowerCase());
      if (existingIndex >= 0) {
        database.tables[existingIndex] = table;
      } else {
        database.tables.push(table);
      }
      return;
    }

    match = stmt.match(/^INSERT\s+INTO\s+`?([^`\s(]+)`?\s*\(([^)]*)\)\s*VALUES\s*(.+)$/i);
    if (match) {
      const tableName = match[1];
      const colNames = splitSqlByTopLevelComma(match[2]).map((name) => name.replace(/`/g, '').trim()).filter(Boolean);
      const tuplesRaw = String(match[3] || '');
      const tuples = [];
      let current = '';
      let depth = 0;
      let inQuote = false;

      for (let i = 0; i < tuplesRaw.length; i += 1) {
        const ch = tuplesRaw[i];
        const prev = i > 0 ? tuplesRaw[i - 1] : '';
        if (ch === "'" && prev !== '\\') {
          inQuote = !inQuote;
          current += ch;
          continue;
        }
        if (!inQuote && ch === '(') {
          if (depth === 0) current = '';
          depth += 1;
          continue;
        }
        if (!inQuote && ch === ')') {
          depth -= 1;
          if (depth === 0) {
            tuples.push(current);
            current = '';
            continue;
          }
        }
        if (depth > 0) current += ch;
      }

      const database = ensureDatabase(currentDbName);
      const table = findOrCreateTable(database, tableName);
      tuples.forEach((tupleValues) => {
        const values = splitSqlByTopLevelComma(tupleValues).map((value) => parseSqlValueToken(value));
        const row = {};
        colNames.forEach((colName, index) => {
          const column = (table.columns || []).find((col) => col.name === colName);
          if (!column || column.pk) return;
          row[colName] = String(values[index] ?? '');
        });
        if (Object.keys(row).length > 0) {
          table.rows.push(row);
        }
      });
    }
  });

  return databases.map((db, index) => normalizeDbSmallDatabase(db, index));
}

function openDbSmallSqlImportModal() {
  return new Promise((resolve) => {
    let modal = document.getElementById('db-small-sql-import-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'db-small-sql-import-modal';
      modal.style.position = 'fixed';
      modal.style.inset = '0';
      modal.style.background = 'rgba(0, 0, 0, 0.45)';
      modal.style.display = 'none';
      modal.style.alignItems = 'center';
      modal.style.justifyContent = 'center';
      modal.style.zIndex = '3000';
      modal.innerHTML = `
        <div style="width:min(920px, 92vw); max-height:86vh; background:var(--bg); color:var(--text-primary); border:1px solid var(--border); border-radius:10px; padding:12px; display:grid; gap:8px; box-shadow:0 12px 40px rgba(0,0,0,0.25);">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
            <strong>SQL Import</strong>
            <button type="button" data-role="sql-import-close" aria-label="Schließen" title="Schließen">✕</button>
          </div>
          <div style="font-size:12px; color:var(--text-secondary);">Unterstützt vereinfachtes SQL: CREATE DATABASE, USE, CREATE TABLE, INSERT INTO.</div>
          <textarea data-role="sql-import-input" placeholder="CREATE DATABASE ...; USE ...; CREATE TABLE ...; INSERT INTO ...;" style="width:100%; min-height:280px; max-height:58vh; font-family:Consolas, monospace; font-size:12px;"></textarea>
          <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" data-role="sql-import-cancel">Abbrechen</button>
            <button type="button" data-role="sql-import-confirm">Importieren</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    const input = modal.querySelector('[data-role="sql-import-input"]');
    const closeBtn = modal.querySelector('[data-role="sql-import-close"]');
    const cancelBtn = modal.querySelector('[data-role="sql-import-cancel"]');
    const confirmBtn = modal.querySelector('[data-role="sql-import-confirm"]');

    const cleanup = () => {
      modal.style.display = 'none';
      modal.onclick = null;
      if (closeBtn) closeBtn.onclick = null;
      if (cancelBtn) cancelBtn.onclick = null;
      if (confirmBtn) confirmBtn.onclick = null;
      document.removeEventListener('keydown', escHandler);
    };

    const closeWith = (value) => {
      cleanup();
      resolve(value);
    };

    const escHandler = (event) => {
      if (event.key === 'Escape') {
        closeWith(null);
      }
    };

    modal.style.display = 'flex';
    if (input instanceof HTMLTextAreaElement) {
      input.value = '';
      window.setTimeout(() => input.focus(), 0);
    }

    modal.onclick = (event) => {
      if (event.target === modal) {
        closeWith(null);
      }
    };
    if (closeBtn) closeBtn.onclick = () => closeWith(null);
    if (cancelBtn) cancelBtn.onclick = () => closeWith(null);
    if (confirmBtn) {
      confirmBtn.onclick = () => {
        const value = input instanceof HTMLTextAreaElement ? String(input.value || '').trim() : '';
        closeWith(value);
      };
    }
    document.addEventListener('keydown', escHandler);
  });
}

async function createProjectFileByName(projectId, fileName, content) {
  const response = await fetch('../api/projects/files-v2.php?action=create', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      project_id: projectId,
      folder_id: null,
      name: fileName,
      content: String(content ?? '')
    })
  });

  const payload = await response.json();
  if (!response.ok || !payload?.ok) {
    throw new Error(payload?.error || `Datei ${fileName} konnte nicht erstellt werden`);
  }
  return Number(payload.file_id || 0);
}

async function updateProjectFileContent(projectId, fileId, content) {
  const response = await fetch('../api/projects/files-v2.php?action=update', {
    method: 'PUT',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      project_id: projectId,
      file_id: Number(fileId),
      content: String(content ?? '')
    })
  });
  const payload = await response.json();
  if (!response.ok || !payload?.ok) {
    throw new Error(payload?.error || 'Datei-Update fehlgeschlagen');
  }
}

async function ensureDbSmallModelLoaded(project) {
  if (!project?.id) return;

  if (dbSmallDesignerState.projectId === Number(project.id) && dbSmallDesignerState.model) {
    return;
  }

  dbSmallDesignerState.projectId = Number(project.id);
  dbSmallDesignerState.modelFileId = 0;
  dbSmallDesignerState.sqlFileId = 0;
  dbSmallDesignerState.selectedDatabaseIndex = 0;
  dbSmallDesignerState.selectedTableIndex = 0;
  dbSmallDesignerState.isDirty = false;
  dbSmallDesignerState.lastSavedAt = '';
  dbSmallDesignerState.rowDrafts = {};
  dbSmallDesignerState.sortStates = {};

  let modelFile = await readProjectFileByName(project.id, DB_SMALL_MODEL_FILE);
  if (!modelFile?.fileId) {
    const defaultModel = createDefaultDbSmallModel();
    await createProjectFileByName(project.id, DB_SMALL_MODEL_FILE, JSON.stringify(defaultModel, null, 2));
    modelFile = await readProjectFileByName(project.id, DB_SMALL_MODEL_FILE);
  }

  let model = createDefaultDbSmallModel();
  try {
    model = normalizeDbSmallModel(JSON.parse(String(modelFile?.content || '{}')));
  } catch (_err) {
    model = createDefaultDbSmallModel();
  }

  dbSmallDesignerState.modelFileId = Number(modelFile?.fileId || 0);
  dbSmallDesignerState.model = model;
  dbSmallDesignerState.selectedDatabaseIndex = getSelectedDbSmallDatabaseIndex(model);

  const sqlFile = await readProjectFileByName(project.id, DB_SMALL_SQL_FILE);
  if (sqlFile?.fileId) {
    dbSmallDesignerState.sqlFileId = Number(sqlFile.fileId);
  } else {
    dbSmallDesignerState.sqlFileId = await createProjectFileByName(project.id, DB_SMALL_SQL_FILE, '-- SQL Export wird im DB-Designer erzeugt.\n');
  }
}

async function persistDbSmallModel() {
  if (!dbSmallDesignerState.projectId || !dbSmallDesignerState.modelFileId || !dbSmallDesignerState.model) return;
  const normalized = normalizeDbSmallModel(dbSmallDesignerState.model);
  dbSmallDesignerState.model = normalized;
  await updateProjectFileContent(dbSmallDesignerState.projectId, dbSmallDesignerState.modelFileId, JSON.stringify(normalized, null, 2));
}

async function persistDbSmallSql(sqlText) {
  if (!dbSmallDesignerState.projectId) return;
  if (!dbSmallDesignerState.sqlFileId) {
    dbSmallDesignerState.sqlFileId = await createProjectFileByName(dbSmallDesignerState.projectId, DB_SMALL_SQL_FILE, sqlText);
    return;
  }
  await updateProjectFileContent(dbSmallDesignerState.projectId, dbSmallDesignerState.sqlFileId, sqlText);
}

function setDbSmallWorkspaceMode(active) {
  const treeHeader = document.querySelector('#file-tree-wrapper .tree-header');
  const editorContainer = document.getElementById('editor-container');
  const designerPanel = document.getElementById('db-small-designer-panel');
  const runBtn = document.getElementById('run-btn');

  if (treeHeader) {
    treeHeader.textContent = active ? '🗂 Datenbank' : '📁 Dateien';
  }

  if (editorContainer) {
    editorContainer.style.display = active ? 'none' : '';
  }
  if (designerPanel) {
    designerPanel.style.display = active ? 'block' : 'none';
    if (!active) designerPanel.innerHTML = '';
  }

  if (runBtn) {
    runBtn.disabled = !!active;
    runBtn.title = active ? 'Run ist für db_small aktuell deaktiviert' : '';
    runBtn.style.opacity = active ? '0.55' : '1';
    runBtn.style.cursor = active ? 'not-allowed' : 'pointer';
  }
}

function getDbSmallNavigationContainer() {
  const directTree = document.getElementById('project-file-tree');
  if (directTree) {
    return directTree;
  }

  const wrapper = document.getElementById('file-tree-wrapper');
  if (!wrapper) {
    return null;
  }

  let header = wrapper.querySelector('.tree-header');
  if (!header) {
    header = document.createElement('div');
    header.className = 'tree-header';
    wrapper.prepend(header);
  }

  let fallback = wrapper.querySelector('.db-small-tree-container');
  if (!fallback) {
    // Remove stale file-tree content and mount a dedicated db_small nav container.
    Array.from(wrapper.children).forEach((child) => {
      if (!child.classList.contains('tree-header')) {
        child.remove();
      }
    });
    fallback = document.createElement('div');
    fallback.className = 'db-small-tree-container';
    fallback.style.flex = '1';
    fallback.style.overflowY = 'auto';
    fallback.style.overflowX = 'hidden';
    fallback.style.padding = '4px';
    fallback.style.minHeight = '0';
    wrapper.appendChild(fallback);
  }

  return fallback;
}

function getSelectedDbSmallTableIndex(model) {
  const databases = getDbSmallDatabases(model);
  const activeDatabaseIndex = getSelectedDbSmallDatabaseIndex(model);
  const tables = Array.isArray(databases[activeDatabaseIndex]?.tables) ? databases[activeDatabaseIndex].tables : [];
  if (tables.length === 0) return 0;
  const idx = Number(dbSmallDesignerState.selectedTableIndex || 0);
  if (idx < 0) return 0;
  if (idx >= tables.length) return tables.length - 1;
  return idx;
}

function sortDbSmallRows(table, colName, direction) {
  if (!table || !Array.isArray(table.rows) || !colName) return;
  const dir = direction === 'desc' ? -1 : 1;

  table.rows.sort((a, b) => {
    const av = String(a?.[colName] ?? '').trim();
    const bv = String(b?.[colName] ?? '').trim();

    if (av === '' && bv === '') return 0;
    if (av === '') return 1;
    if (bv === '') return -1;

    const aNum = Number(av);
    const bNum = Number(bv);
    const bothNumeric = Number.isFinite(aNum) && Number.isFinite(bNum) && /^-?\d+(\.\d+)?$/.test(av) && /^-?\d+(\.\d+)?$/.test(bv);

    if (bothNumeric) {
      if (aNum === bNum) return 0;
      return (aNum < bNum ? -1 : 1) * dir;
    }

    return av.localeCompare(bv, 'de', { sensitivity: 'base', numeric: true }) * dir;
  });
}

function assignDbSmallAutoPkValues(table) {
  if (!table || !Array.isArray(table.rows) || !Array.isArray(table.columns)) return;
  const pkColumns = table.columns.filter((col) => !!col?.pk);
  if (pkColumns.length === 0) return;

  pkColumns.forEach((pkCol) => {
    const usedValues = new Set();
    let nextValue = 1;

    table.rows.forEach((row) => {
      const raw = String(row?.[pkCol.name] ?? '').trim();
      if (!/^\d+$/.test(raw)) return;
      const numeric = Number.parseInt(raw, 10);
      if (!Number.isFinite(numeric) || numeric < 1) return;
      usedValues.add(numeric);
      if (numeric >= nextValue) nextValue = numeric + 1;
    });

    table.rows.forEach((row) => {
      const raw = String(row?.[pkCol.name] ?? '').trim();
      if (/^\d+$/.test(raw) && Number.parseInt(raw, 10) > 0) return;
      while (usedValues.has(nextValue)) {
        nextValue += 1;
      }
      row[pkCol.name] = String(nextValue);
      usedValues.add(nextValue);
      nextValue += 1;
    });
  });
}

function renderDbSmallTableNavigation(model) {
  const treeContainer = getDbSmallNavigationContainer();
  if (!treeContainer) return;

  const databases = getDbSmallDatabases(model);
  const selectedDatabaseIndex = getSelectedDbSmallDatabaseIndex(model);
  const tables = Array.isArray(databases[selectedDatabaseIndex]?.tables) ? databases[selectedDatabaseIndex].tables : [];
  const selectedIndex = getSelectedDbSmallTableIndex(model);
  dbSmallDesignerState.selectedTableIndex = selectedIndex;
  dbSmallDesignerState.selectedDatabaseIndex = selectedDatabaseIndex;

  const treeHeader = document.querySelector('#file-tree-wrapper .tree-header');
  if (treeHeader) {
    const activeDbName = String(databases[selectedDatabaseIndex]?.name || `db_${selectedDatabaseIndex + 1}`);
    treeHeader.textContent = `🗂 Datenbank: ${activeDbName}`;
  }

  treeContainer.innerHTML = `
    <div style="padding:8px; display:grid; gap:6px;">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:6px;">
        <strong>Tabellen</strong>
        <button type="button" data-action="add-table" title="Tabelle anlegen" aria-label="Tabelle anlegen" style="width:26px;height:26px;border-radius:999px;border:0;background:#2563eb;color:#fff;font-weight:700;line-height:1;cursor:pointer;">+</button>
      </div>
      ${tables.map((table, index) => `
        <div class="db-table-nav-item ${index === selectedIndex ? 'active' : ''}" data-action="select-table" data-table-index="${index}" style="display:flex; align-items:center; gap:6px; padding-top:4px; padding-bottom:4px; min-height:0;">
          <span>🧱</span>
          <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(table.name || `table_${index + 1}`)}</span>
          <button type="button" data-action="remove-table-nav" data-table-index="${index}" title="Tabelle löschen" aria-label="Tabelle löschen" style="padding:0 4px; min-width:24px;">🗑</button>
        </div>
      `).join('')}
      <div style="margin-top:6px; display:grid; gap:4px; border-top:1px solid var(--border); padding-top:6px;">
        <label style="font-size:12px; color:var(--text-secondary);">Datenbank</label>
        <select data-role="db-select" style="width:100%;">
          ${databases.map((database, index) => `<option value="${index}" ${index === selectedDatabaseIndex ? 'selected' : ''}>${escapeHtml(database.name || `db_${index + 1}`)}</option>`).join('')}
        </select>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
          <button type="button" data-action="add-database" title="Datenbank anlegen" aria-label="Datenbank anlegen" style="width:26px;height:26px;border-radius:999px;border:0;background:#2563eb;color:#fff;font-weight:700;line-height:1;cursor:pointer;">+</button>
          <button type="button" data-action="duplicate-database">Duplizieren</button>
          <button type="button" data-action="rename-database">Umbenennen</button>
          <button type="button" data-action="remove-database">Löschen</button>
        </div>
      </div>
    </div>
  `;

  treeContainer.onclick = (event) => {
    const actionEl = event.target.closest('[data-action]');
    if (!actionEl || !dbSmallDesignerState.model) return;
    const action = String(actionEl.dataset.action || '');
    const currentModel = dbSmallDesignerState.model;
    const databases = Array.isArray(currentModel.databases) ? currentModel.databases : [];
    const activeDatabaseIndex = getSelectedDbSmallDatabaseIndex(currentModel);
    const activeDatabase = databases[activeDatabaseIndex];
    if (!activeDatabase) return;

    if (action === 'add-database') {
      dbSmallDesignerState.model.databases.push({
        name: `Zwischenstand ${dbSmallDesignerState.model.databases.length + 1}`,
        tables: [{
          name: 'table_1',
          columns: [{ name: 'id', type: 'AUTO', pk: true, fk: false, default: '' }],
          rows: []
        }]
      });
      dbSmallDesignerState.selectedDatabaseIndex = dbSmallDesignerState.model.databases.length - 1;
      dbSmallDesignerState.selectedTableIndex = 0;
      dbSmallDesignerState.rowDrafts = {};
      dbSmallDesignerState.sortStates = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'duplicate-database') {
      const clone = JSON.parse(JSON.stringify(activeDatabase));
      clone.name = `${activeDatabase.name || 'Zwischenstand'} Kopie`;
      dbSmallDesignerState.model.databases.splice(activeDatabaseIndex + 1, 0, clone);
      dbSmallDesignerState.selectedDatabaseIndex = activeDatabaseIndex + 1;
      dbSmallDesignerState.selectedTableIndex = 0;
      dbSmallDesignerState.rowDrafts = {};
      dbSmallDesignerState.sortStates = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'rename-database') {
      const nextName = window.prompt('Name der Datenbank', activeDatabase.name || 'Zwischenstand 1');
      if (nextName === null) return;
      activeDatabase.name = normalizeDbSmallDatabaseName(nextName, `db_${activeDatabaseIndex + 1}`);
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'remove-database') {
      if (!window.confirm('Datenbank wirklich löschen?')) return;
      dbSmallDesignerState.model.databases.splice(activeDatabaseIndex, 1);
      if (dbSmallDesignerState.model.databases.length === 0) {
        dbSmallDesignerState.model.databases.push(createDefaultDbSmallModel().databases[0]);
      }
      dbSmallDesignerState.selectedDatabaseIndex = Math.max(0, activeDatabaseIndex - 1);
      dbSmallDesignerState.selectedTableIndex = 0;
      dbSmallDesignerState.rowDrafts = {};
      dbSmallDesignerState.sortStates = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'add-table') {
      activeDatabase.tables.push({
        name: `table_${activeDatabase.tables.length + 1}`,
        columns: [{ name: 'id', type: 'AUTO', length: '', pk: true, fk: false, default: '' }],
        rows: []
      });
      dbSmallDesignerState.selectedTableIndex = activeDatabase.tables.length - 1;
      dbSmallDesignerState.rowDrafts = {};
      dbSmallDesignerState.sortStates = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }
    if (action === 'remove-table-nav') {
      const tableIndex = Number(actionEl.dataset.tableIndex || -1);
      if (tableIndex < 0) return;
      const table = activeDatabase.tables?.[tableIndex];
      const tableName = table?.name || `table_${tableIndex + 1}`;
      if (!window.confirm(`Tabelle "${tableName}" wirklich löschen?`)) return;
      activeDatabase.tables.splice(tableIndex, 1);
      if (activeDatabase.tables.length === 0) {
        activeDatabase.tables.push({
          name: 'table_1',
          columns: [{ name: 'id', type: 'AUTO', length: '', pk: true, fk: false, default: '' }],
          rows: []
        });
      }
      dbSmallDesignerState.selectedTableIndex = Math.max(0, Math.min(tableIndex, activeDatabase.tables.length - 1));
      dbSmallDesignerState.rowDrafts = {};
      dbSmallDesignerState.sortStates = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }
    if (action === 'select-table') {
      const idx = Number(actionEl.dataset.tableIndex || 0);
      dbSmallDesignerState.selectedTableIndex = idx;
      renderDbSmallDesignerView();
    }
  };

  treeContainer.onchange = (event) => {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement)) return;
    const role = String(target.dataset.role || '');
    if (role !== 'db-select') return;
    const idx = Number(target.value || 0);
    dbSmallDesignerState.selectedDatabaseIndex = Number.isFinite(idx) ? idx : 0;
    dbSmallDesignerState.selectedTableIndex = 0;
    dbSmallDesignerState.tableNameEdit.active = false;
    renderDbSmallDesignerView();
  };
}

function renderDbSmallSqlPanel(model) {
  const guiContainer = document.getElementById('gui-container');
  const outputEl = document.getElementById('output-container');
  const plotEl = document.getElementById('plot-container');
  const lintEl = document.getElementById('lint-container');
  const helpEl = document.getElementById('help-container');
  if (!outputEl || !helpEl) return;

  const databases = getDbSmallDatabases(model);
  const selectedDatabaseIndex = getSelectedDbSmallDatabaseIndex(model);
  const database = databases[selectedDatabaseIndex] || databases[0];
  const tables = Array.isArray(database?.tables) ? database.tables : [];
  const tableIndex = getSelectedDbSmallTableIndex(model);
  const table = tables[tableIndex];
  const allColumns = table?.columns || [];
  const dataColumns = allColumns.filter((col) => !col.pk);
  const sortState = dbSmallDesignerState.sortStates[`${selectedDatabaseIndex}:${tableIndex}`] || null;
  const sqlPreview = generateDbSmallSql(model);
  const selectedRowIndex = Array.isArray(table?.rows) && table.rows[dbSmallDesignerState.selectedRowIndex]
    ? dbSmallDesignerState.selectedRowIndex
    : -1;
  dbSmallDesignerState.selectedRowIndex = selectedRowIndex;
  const activeCell = dbSmallDesignerState.activeCell || { rowIndex: -1, colName: '', pendingFocus: false };
  if (activeCell.rowIndex < 0 || !table?.rows?.[activeCell.rowIndex]) {
    dbSmallDesignerState.activeCell = { rowIndex: -1, colName: '', pendingFocus: false };
  }
  const selectedRowDraftKey = `${selectedDatabaseIndex}:${tableIndex}:${selectedRowIndex}`;
  const selectedRowDraft = selectedRowIndex >= 0 ? (dbSmallDesignerState.rowDrafts[selectedRowDraftKey] || {}) : null;
  const selectedRow = selectedRowIndex >= 0 ? table?.rows?.[selectedRowIndex] : null;
  const selectedRowDirty = !!(selectedRow && selectedRowDraft && dataColumns.some((col) => {
    if (!Object.prototype.hasOwnProperty.call(selectedRowDraft, col.name)) return false;
    return String(selectedRowDraft[col.name] ?? '') !== String(selectedRow?.[col.name] ?? '');
  }));

  if (table) {
    assignDbSmallAutoPkValues(table);
  }

  if (guiContainer) {
    guiContainer.classList.remove('active');
    guiContainer.innerHTML = '';
  }
  setProjectsRightPanelMode(false);

  const outputTab = document.querySelector('.output-plot-tab[data-tab="output"]');
  const plotTab = document.querySelector('.output-plot-tab[data-tab="plot"]');
  outputTab?.classList.add('active');
  plotTab?.classList.remove('active');
  outputEl.classList.add('active');
  plotEl?.classList.remove('active');
  if (plotEl) plotEl.innerHTML = '';

  const rowsHtml = (table?.rows || []).map((row, rowIndex) => {
    const rowDraftKey = `${selectedDatabaseIndex}:${tableIndex}:${rowIndex}`;
    const rowDraft = dbSmallDesignerState.rowDrafts[rowDraftKey] || {};
    const rowBackground = rowIndex === selectedRowIndex ? '#fff7cc' : 'var(--surface)';

    return `
      <tr data-role="data-row" data-row-index="${rowIndex}" style="background:${rowBackground}; cursor:pointer;">
        ${allColumns.map((col) => {
          if (col.pk) {
            const pkValue = String(row?.[col.name] ?? (rowIndex + 1));
            return `<td style="padding:0;border:1px solid var(--border);background:${rowIndex === selectedRowIndex ? '#fff7cc' : '#fafafa'};"><input value="${escapeHtml(pkValue)}" readonly tabindex="-1" style="width:100%;border:0;margin:0;padding:3px 6px;border-radius:0;box-sizing:border-box;background:transparent;color:var(--text-secondary);" /></td>`;
          }
          const draftValue = Object.prototype.hasOwnProperty.call(rowDraft, col.name)
            ? rowDraft[col.name]
            : String(row?.[col.name] ?? '');
          const isActiveCell = dbSmallDesignerState.activeCell?.rowIndex === rowIndex && dbSmallDesignerState.activeCell?.colName === col.name;
          const cellBackground = isActiveCell ? '#dbeafe' : rowBackground;
          const inputStyle = isActiveCell
            ? 'width:100%;border:1px inset #93c5fd;margin:0;padding:2px 5px;border-radius:2px;box-sizing:border-box;background:#dbeafe;box-shadow:inset 0 0 0 1px #bfdbfe;'
            : 'width:100%;border:0;margin:0;padding:3px 6px;border-radius:0;box-sizing:border-box;background:transparent;';
          return `<td data-role="data-cell" data-row-index="${rowIndex}" data-col-name="${escapeHtml(col.name)}" style="padding:0;border:1px solid var(--border);background:${cellBackground};"><input data-role="row-value" data-row-index="${rowIndex}" data-col-name="${escapeHtml(col.name)}" value="${escapeHtml(String(draftValue ?? ''))}" style="${inputStyle}" /></td>`;
        }).join('')}
      </tr>
    `;
  }).join('');

  outputEl.style.whiteSpace = 'normal';
  outputEl.style.fontFamily = 'inherit';
  outputEl.innerHTML = table
    ? `
      <div style="display:grid;gap:8px;">
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
          <strong style="color:#7c3aed;">Datenanzeige</strong>
          <button type="button" data-action="add-row" title="Neue Zeile anlegen" aria-label="Neue Zeile anlegen" style="width:28px;height:28px;border-radius:999px;border:0;background:#2563eb;color:#fff;font-weight:700;line-height:1;cursor:pointer;">+</button>
          <button type="button" data-action="save-selected-row" title="Markierte Zeile speichern" aria-label="Markierte Zeile speichern" ${selectedRowDirty ? '' : 'disabled'} style="width:28px;height:28px;border-radius:999px;border:0;background:#16a34a;color:#fff;font-weight:700;line-height:1;cursor:${selectedRowDirty ? 'pointer' : 'not-allowed'};opacity:${selectedRowDirty ? '1' : '0.45'};">✓</button>
          <button type="button" data-action="cancel-selected-row" title="Änderungen der markierten Zeile verwerfen" aria-label="Änderungen der markierten Zeile verwerfen" ${selectedRowDirty ? '' : 'disabled'} style="width:28px;height:28px;border-radius:999px;border:0;background:#d97706;color:#fff;font-weight:700;line-height:1;cursor:${selectedRowDirty ? 'pointer' : 'not-allowed'};opacity:${selectedRowDirty ? '1' : '0.45'};">↶</button>
          <button type="button" data-action="remove-selected-row" title="Markierte Zeile löschen" aria-label="Markierte Zeile löschen" ${selectedRowIndex >= 0 ? '' : 'disabled'} style="width:28px;height:28px;border-radius:999px;border:0;background:#dc2626;color:#fff;font-weight:700;line-height:1;cursor:${selectedRowIndex >= 0 ? 'pointer' : 'not-allowed'};opacity:${selectedRowIndex >= 0 ? '1' : '0.45'};">🗑</button>
        </div>
        <div style="overflow:auto; border:1px solid var(--border); border-radius:8px; padding:8px;">
          <table style="width:100%; border-collapse:collapse; border-spacing:0; font-size:12px; table-layout:auto;">
            <thead><tr>${allColumns.map((col) => {
              const keyColor = col.pk ? '#1d4ed8' : (col.fk ? '#15803d' : 'inherit');
              if (col.pk) {
                return `<th style="padding:0;border:1px solid var(--border);background:#eef5ff;text-align:left;"><div style="padding:3px 6px;color:${keyColor};font-weight:600;">${escapeHtml(col.name)}</div></th>`;
              }
              const arrow = sortState && sortState.colName === col.name ? (sortState.direction === 'asc' ? ' ▲' : ' ▼') : '';
              return `<th style="padding:0;border:1px solid var(--border);background:#eef5ff;"><button type="button" data-action="sort-rows" data-col-name="${escapeHtml(col.name)}" title="Nach ${escapeHtml(col.name)} sortieren" style="width:100%;border:0;background:transparent;text-align:left;padding:3px 6px;cursor:pointer;color:${keyColor};font-weight:${col.pk || col.fk ? '600' : '400'};">${escapeHtml(col.name)}${arrow}</button></th>`;
            }).join('')}</tr></thead>
            <tbody>${rowsHtml}</tbody>
          </table>
          ${dataColumns.length === 0
            ? '<div style="margin-top:6px;font-size:12px;color:var(--text-secondary);">Nur AUTO-PK vorhanden. Jeder neue Datensatz erzeugt automatisch einen PK-Wert.</div>'
            : ''}
        </div>
      </div>
    `
    : '<p style="color:var(--text-secondary);">Keine Datenanzeige verfügbar.</p>';

  if (dbSmallDesignerState.activeCell?.pendingFocus) {
    const { rowIndex, colName } = dbSmallDesignerState.activeCell;
    window.requestAnimationFrame(() => {
      const targetInput = Array.from(outputEl.querySelectorAll(`[data-role="row-value"][data-row-index="${rowIndex}"]`))
        .find((input) => String(input.getAttribute('data-col-name') || '') === colName);
      if (targetInput instanceof HTMLInputElement) {
        targetInput.focus();
        targetInput.select();
      }
      dbSmallDesignerState.activeCell.pendingFocus = false;
    });
  }

  outputEl.onclick = (event) => {
    const actionEl = event.target.closest('[data-action]');
    if (!dbSmallDesignerState.model) return;

    const cellEl = event.target.closest('[data-role="data-cell"]');
    if (cellEl) {
      const rowIndex = Number(cellEl.getAttribute('data-row-index') || -1);
      const colName = String(cellEl.getAttribute('data-col-name') || '');
      if (rowIndex >= 0 && colName) {
        const cellChanged = dbSmallDesignerState.activeCell.rowIndex !== rowIndex || dbSmallDesignerState.activeCell.colName !== colName;
        const rowChanged = rowIndex !== dbSmallDesignerState.selectedRowIndex;
        dbSmallDesignerState.selectedRowIndex = rowIndex;
        dbSmallDesignerState.activeCell = { rowIndex, colName, pendingFocus: cellChanged || rowChanged };
        if (cellChanged || rowChanged) {
          renderDbSmallDesignerView();
          return;
        }
      }
    }

    const rowEl = event.target.closest('[data-role="data-row"]');
    if (rowEl) {
      const rowIndex = Number(rowEl.getAttribute('data-row-index') || -1);
      if (rowIndex >= 0 && rowIndex !== dbSmallDesignerState.selectedRowIndex) {
        dbSmallDesignerState.selectedRowIndex = rowIndex;
        dbSmallDesignerState.activeCell = { rowIndex: -1, colName: '', pendingFocus: false };
        renderDbSmallDesignerView();
        return;
      }
    }

    if (!actionEl) return;

    const action = String(actionEl.dataset.action || '');
    const activeDatabase = dbSmallDesignerState.model.databases?.[dbSmallDesignerState.selectedDatabaseIndex];
    const activeTable = activeDatabase?.tables?.[dbSmallDesignerState.selectedTableIndex];
    if (!activeTable) return;

    if (action === 'add-row') {
      const row = {};
      (activeTable.columns || []).filter((col) => !col.pk).forEach((col) => {
        row[col.name] = '';
      });
      activeTable.rows.push(row);
      assignDbSmallAutoPkValues(activeTable);
      dbSmallDesignerState.selectedRowIndex = activeTable.rows.length - 1;
      const firstEditableCol = (activeTable.columns || []).find((col) => !col.pk);
      dbSmallDesignerState.activeCell = firstEditableCol
        ? { rowIndex: activeTable.rows.length - 1, colName: firstEditableCol.name, pendingFocus: true }
        : { rowIndex: -1, colName: '', pendingFocus: false };
      dbSmallDesignerState.rowDrafts = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'save-selected-row') {
      const rowIndex = dbSmallDesignerState.selectedRowIndex;
      if (rowIndex < 0) return;
      const row = activeTable.rows?.[rowIndex];
      if (!row) return;
      const rowDraftKey = `${dbSmallDesignerState.selectedDatabaseIndex}:${dbSmallDesignerState.selectedTableIndex}:${rowIndex}`;
      const rowDraft = dbSmallDesignerState.rowDrafts[rowDraftKey] || {};
      Object.keys(rowDraft).forEach((colName) => {
        row[colName] = String(rowDraft[colName] ?? '');
      });
      delete dbSmallDesignerState.rowDrafts[rowDraftKey];
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'cancel-selected-row') {
      const rowIndex = dbSmallDesignerState.selectedRowIndex;
      if (rowIndex < 0) return;
      const rowDraftKey = `${dbSmallDesignerState.selectedDatabaseIndex}:${dbSmallDesignerState.selectedTableIndex}:${rowIndex}`;
      delete dbSmallDesignerState.rowDrafts[rowDraftKey];
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'remove-selected-row') {
      const rowIndex = dbSmallDesignerState.selectedRowIndex;
      if (rowIndex < 0) return;
      if (!window.confirm('Zeile wirklich löschen?')) return;
      activeTable.rows.splice(rowIndex, 1);
      dbSmallDesignerState.selectedRowIndex = activeTable.rows[rowIndex] ? rowIndex : Math.max(-1, activeTable.rows.length - 1);
      dbSmallDesignerState.activeCell = { rowIndex: -1, colName: '', pendingFocus: false };
      dbSmallDesignerState.rowDrafts = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'sort-rows') {
      const colName = String(actionEl.dataset.colName || '');
      if (!colName) return;
      const stateKey = `${dbSmallDesignerState.selectedDatabaseIndex}:${dbSmallDesignerState.selectedTableIndex}`;
      const prev = dbSmallDesignerState.sortStates[stateKey];
      const nextDirection = prev && prev.colName === colName && prev.direction === 'asc' ? 'desc' : 'asc';
      sortDbSmallRows(activeTable, colName, nextDirection);
      dbSmallDesignerState.sortStates[stateKey] = { colName, direction: nextDirection };
      dbSmallDesignerState.rowDrafts = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
    }
  };

  outputEl.oninput = (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) || !dbSmallDesignerState.model) return;
    const role = String(target.dataset.role || '');
    if (role !== 'row-value') return;

    const rowIndex = Number(target.dataset.rowIndex || -1);
    const colName = String(target.dataset.colName || '');
    if (rowIndex < 0 || !colName) return;
    dbSmallDesignerState.selectedRowIndex = rowIndex;
    dbSmallDesignerState.activeCell = { rowIndex, colName, pendingFocus: false };

    const rowDraftKey = `${dbSmallDesignerState.selectedDatabaseIndex}:${dbSmallDesignerState.selectedTableIndex}:${rowIndex}`;
    if (!dbSmallDesignerState.rowDrafts[rowDraftKey]) {
      dbSmallDesignerState.rowDrafts[rowDraftKey] = {};
    }
    dbSmallDesignerState.rowDrafts[rowDraftKey][colName] = target.value;

    const row = dbSmallDesignerState.model.databases?.[dbSmallDesignerState.selectedDatabaseIndex]?.tables?.[dbSmallDesignerState.selectedTableIndex]?.rows?.[rowIndex];
    const hasChanges = Object.keys(dbSmallDesignerState.rowDrafts[rowDraftKey]).some((key) => {
      return String(dbSmallDesignerState.rowDrafts[rowDraftKey][key] ?? '') !== String(row?.[key] ?? '');
    });

    const rowSaveBtn = outputEl.querySelector(`button[data-action="save-row"][data-row-index="${rowIndex}"]`);
    const rowCancelBtn = outputEl.querySelector(`button[data-action="cancel-row"][data-row-index="${rowIndex}"]`);
    if (rowSaveBtn) rowSaveBtn.disabled = !hasChanges;
    if (rowCancelBtn) rowCancelBtn.disabled = !hasChanges;
  };

  outputEl.onfocusin = (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    const role = String(target.dataset.role || '');
    if (role !== 'row-value') return;
    const rowIndex = Number(target.dataset.rowIndex || -1);
    const colName = String(target.dataset.colName || '');
    if (rowIndex < 0 || !colName) return;
    dbSmallDesignerState.selectedRowIndex = rowIndex;
    dbSmallDesignerState.activeCell = { rowIndex, colName, pendingFocus: false };
    const cellEl = target.closest('[data-role="data-cell"]');
    if (cellEl instanceof HTMLElement) {
      cellEl.style.background = '#dbeafe';
    }
    target.style.background = '#dbeafe';
    target.style.border = '1px inset #93c5fd';
    target.style.boxShadow = 'inset 0 0 0 1px #bfdbfe';
  };

  if (lintEl) {
    lintEl.innerHTML = `<span style="color:var(--text-secondary);">DB Designer aktiv</span>`;
  }

  helpEl.innerHTML = `
    <div style="display:grid; gap:8px; width:100%;">
      <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <strong>Debug / Lint / Doku: SQL Output</strong>
        <button type="button" data-action="save-model">Speichern (Create/Update/Delete)</button>
        <button type="button" data-action="discard-model">Ungespeicherte Änderungen verwerfen</button>
        <button type="button" data-action="export-sql">SQL exportieren</button>
      </div>
      <div style="font-size:12px;">Status: ${getDbSmallSaveStatusHtml()}</div>
      <textarea readonly style="width:100%; min-height:180px; font-family:Consolas, monospace; font-size:12px;">${escapeHtml(sqlPreview)}</textarea>
    </div>
  `;

  helpEl.onclick = async (event) => {
    const actionEl = event.target.closest('[data-action]');
    if (!actionEl) return;
    const action = String(actionEl.dataset.action || '');

    if (action === 'save-model') {
      try {
        await persistDbSmallModel();
        await persistDbSmallSql(generateDbSmallSql(dbSmallDesignerState.model));
        markDbSmallSaved();
        renderDbSmallDesignerView();
        alert('DB-Modell gespeichert.');
      } catch (err) {
        alert('Speichern fehlgeschlagen: ' + (err?.message || err));
      }
      return;
    }
    if (action === 'discard-model') {
      if (!dbSmallDesignerState.isDirty) return;
      dbSmallDesignerState.projectId = 0;
      dbSmallDesignerState.model = null;
      ensureDbSmallModelLoaded(currentProject)
        .then(() => {
          markDbSmallSaved();
          renderDbSmallDesignerView();
        })
        .catch((err) => {
          alert('Verwerfen fehlgeschlagen: ' + (err?.message || err));
        });
      return;
    }
    if (action === 'export-sql') {
      try {
        const sql = generateDbSmallSql(dbSmallDesignerState.model);
        await persistDbSmallSql(sql);
        triggerFileDownload(new Blob([sql], { type: 'application/sql' }), `${normalizeDbSmallIdentifier(currentProject?.name || 'db_model')}.sql`);
      } catch (err) {
        alert('SQL-Export fehlgeschlagen: ' + (err?.message || err));
      }
    }
  };
}

function renderDbSmallDesignerCenter(model) {
  const designerPanel = document.getElementById('db-small-designer-panel');
  if (!designerPanel) return;

  const databases = getDbSmallDatabases(model);
  const selectedDatabaseIndex = getSelectedDbSmallDatabaseIndex(model);
  const database = databases[selectedDatabaseIndex] || databases[0];
  const tables = Array.isArray(database?.tables) ? database.tables : [];
  const tableIndex = getSelectedDbSmallTableIndex(model);
  dbSmallDesignerState.selectedDatabaseIndex = selectedDatabaseIndex;
  dbSmallDesignerState.selectedTableIndex = tableIndex;
  const table = tables[tableIndex];

  if (!table) {
    designerPanel.innerHTML = '<p style="color:var(--text-secondary);">Keine Tabelle in dieser Datenbank vorhanden.</p>';
    return;
  }

  const allColumns = table.columns || [];
  const tableKey = `${selectedDatabaseIndex}:${tableIndex}`;
  if (dbSmallDesignerState.tableNameEdit.key !== tableKey) {
    dbSmallDesignerState.tableNameEdit = {
      key: tableKey,
      active: false,
      value: String(table.name || '')
    };
  }
  const tableNameEdit = dbSmallDesignerState.tableNameEdit;

  const columnsHtml = (table.columns || []).map((col, colIndex) => {
    const normalizedType = normalizeDbSmallType(col.type);
    let sizeControlHtml = '<span style="color:var(--text-secondary);">-</span>';
    const keyColor = col.pk ? '#1d4ed8' : (col.fk ? '#15803d' : 'inherit');

    if (!col.pk && normalizedType === 'VARCHAR') {
      sizeControlHtml = `<input data-role="col-length" data-col-index="${colIndex}" value="${escapeHtml(String(col.length || '50'))}" placeholder="50" title="VARCHAR-Länge" style="width:78px;border:1px solid var(--border);margin:0;padding:4px 6px;border-radius:8px;box-sizing:border-box;background:var(--surface);" />`;
    } else if (!col.pk && normalizedType === 'INTEGER') {
      sizeControlHtml = `<select data-role="col-int-variant" data-col-index="${colIndex}" title="MySQL INTEGER-Typ" style="width:96px;padding:4px 6px;border:1px solid var(--border);border-radius:8px;background:var(--surface);">${['TINYINT', 'SMALLINT', 'INT', 'BIGINT'].map((variant) => `<option value="${variant}" ${normalizeDbSmallIntegerVariant(col.type, col.integerVariant) === variant ? 'selected' : ''}>${variant}</option>`).join('')}</select>`;
    } else if (!col.pk && normalizedType === 'FLOAT') {
      sizeControlHtml = `<select data-role="col-float-variant" data-col-index="${colIndex}" title="MySQL FLOAT-Typ" style="width:96px;padding:4px 6px;border:1px solid var(--border);border-radius:8px;background:var(--surface);">${['DECIMAL', 'FLOAT', 'DOUBLE', 'NUMERIC'].map((variant) => `<option value="${variant}" ${normalizeDbSmallFloatVariant(col.type, col.floatVariant) === variant ? 'selected' : ''}>${variant}</option>`).join('')}</select>`;
    }

    return `
    <tr style="background:var(--surface);">
      <td style="width:26px;min-width:26px;padding:2px 1px 2px 0;border-bottom:1px solid var(--border);text-align:center;"><input type="checkbox" data-role="col-pk" data-col-index="${colIndex}" ${col.pk ? 'checked' : ''} style="margin:0;" /></td>
      <td style="width:26px;min-width:26px;padding:2px 1px;border-bottom:1px solid var(--border);text-align:center;"><input type="checkbox" data-role="col-fk" data-col-index="${colIndex}" ${col.fk ? 'checked' : ''} style="margin:0;" /></td>
      <td style="padding:2px 6px;border-bottom:1px solid var(--border);"><input data-role="col-name" data-col-index="${colIndex}" data-old-name="${escapeHtml(col.name)}" value="${escapeHtml(col.name)}" style="width:100%;border:1px solid var(--border);margin:0;padding:4px 6px;border-radius:8px;box-sizing:border-box;background:var(--surface);min-width:120px;color:${keyColor};font-weight:${col.pk || col.fk ? '600' : '400'};" /></td>
      <td style="width:122px;min-width:122px;padding:2px 6px;border-bottom:1px solid var(--border);">
        <select data-role="col-type" data-col-index="${colIndex}" ${col.pk ? 'disabled' : ''} style="width:100%;padding:4px 6px;border:1px solid var(--border);border-radius:8px;background:var(--surface);">
          ${['AUTO', 'INTEGER', 'FLOAT', 'VARCHAR', 'DATE', 'DATETIME', 'BOOLEAN'].map((type) => `<option value="${type}" ${normalizedType === type ? 'selected' : ''}>${type}</option>`).join('')}
        </select>
      </td>
      <td style="width:102px;min-width:102px;padding:2px 6px;border-bottom:1px solid var(--border);">${sizeControlHtml}</td>
      <td style="width:30px;min-width:30px;padding:2px 0 2px 6px;border-bottom:1px solid var(--border);text-align:center;"><button type="button" data-action="remove-column" data-col-index="${colIndex}" title="Spalte löschen" aria-label="Spalte löschen">🗑</button></td>
    </tr>
  `;
  }).join('');

  designerPanel.innerHTML = `
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
      <strong>Tabellenentwurf</strong>
    </div>

    <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
      <span style="font-size:13px; color:#1d4ed8; font-weight:700; min-width:90px;">${escapeHtml(table.name)}</span>
      ${tableNameEdit.active
        ? `<input data-role="table-name-edit" value="${escapeHtml(tableNameEdit.value || table.name)}" style="flex:1;" />
           <button type="button" data-action="save-table-name" title="Speichern">💾</button>
           <button type="button" data-action="cancel-table-name" title="Abbrechen">✕</button>`
        : `<button type="button" data-action="edit-table-name" title="Tabellenname bearbeiten">✏️</button>`}
    </div>

    <div style="overflow:auto; border:1px solid var(--border); border-radius:8px; padding:8px; background:var(--surface);">
      <table style="width:100%; border-collapse:separate; border-spacing:0 8px; font-size:12px; table-layout:fixed; background:var(--surface);">
        <thead><tr><th style="width:26px;min-width:26px;padding:1px 1px 1px 0;text-align:center;background:#eef5ff;">PK</th><th style="width:26px;min-width:26px;padding:1px 1px;text-align:center;background:#eef5ff;">FK</th><th style="padding:2px 6px;text-align:left;background:#eef5ff;">Feld</th><th style="width:122px;min-width:122px;padding:2px 6px;text-align:left;background:#eef5ff;">Datentyp</th><th style="width:102px;min-width:102px;padding:2px 6px;text-align:left;background:#eef5ff;">Größe</th><th style="width:30px;min-width:30px;padding:2px 0 2px 6px;background:#eef5ff;"></th></tr></thead>
        <tbody>${columnsHtml}</tbody>
      </table>
      <div style="margin-top:6px;"><button type="button" data-action="add-column" title="Feld anlegen" aria-label="Feld anlegen" style="width:26px;height:26px;border-radius:999px;border:0;background:#2563eb;color:#fff;font-weight:700;line-height:1;cursor:pointer;">+</button></div>
    </div>

    <div style="margin-top:12px; display:flex; justify-content:flex-start;">
      <button type="button" data-action="import-sql">SQL importieren</button>
    </div>
  `;

  designerPanel.onclick = async (event) => {
    const actionEl = event.target.closest('[data-action]');
    if (!actionEl || !dbSmallDesignerState.model) return;
    const action = String(actionEl.dataset.action || '');
    const activeDatabase = dbSmallDesignerState.model.databases?.[dbSmallDesignerState.selectedDatabaseIndex];
    if (!activeDatabase) return;
    const activeTable = activeDatabase.tables?.[dbSmallDesignerState.selectedTableIndex];
    if (!activeTable) return;

    if (action === 'add-column') {
      const nextCol = `col_${(activeTable.columns || []).length + 1}`;
      activeTable.columns.push({ name: nextCol, type: 'VARCHAR', length: '50', pk: false, fk: false, integerVariant: 'INT', floatVariant: 'FLOAT', default: '' });
      (activeTable.rows || []).forEach((row) => {
        if (!Object.prototype.hasOwnProperty.call(row, nextCol)) row[nextCol] = '';
      });
      dbSmallDesignerState.rowDrafts = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'edit-table-name') {
      dbSmallDesignerState.tableNameEdit.active = true;
      dbSmallDesignerState.tableNameEdit.value = String(activeTable.name || '');
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'cancel-table-name') {
      dbSmallDesignerState.tableNameEdit.active = false;
      dbSmallDesignerState.tableNameEdit.value = String(activeTable.name || '');
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'save-table-name') {
      const input = designerPanel.querySelector('[data-role="table-name-edit"]');
      const nextName = String(input?.value || '').trim() || String(activeTable.name || '');
      activeTable.name = nextName;
      dbSmallDesignerState.tableNameEdit.active = false;
      dbSmallDesignerState.tableNameEdit.value = nextName;
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'remove-column') {
      const colIndex = Number(actionEl.dataset.colIndex || -1);
      if (colIndex < 0) return;
      if (!window.confirm('Spalte wirklich löschen?')) return;
      const removed = activeTable.columns.splice(colIndex, 1)[0];
      if (activeTable.columns.length === 0) {
        activeTable.columns.push({ name: 'id', type: 'AUTO', length: '', pk: true, fk: false, default: '' });
      }
      if (removed?.name) {
        (activeTable.rows || []).forEach((row) => delete row[removed.name]);
      }
      dbSmallDesignerState.rowDrafts = {};
      markDbSmallDirty();
      renderDbSmallDesignerView();
      return;
    }

    if (action === 'import-sql') {
      const sqlText = await openDbSmallSqlImportModal();
      if (!sqlText) {
        return;
      }

      try {
        const importedDatabases = parseDbSmallSqlImport(sqlText);
        if (!Array.isArray(importedDatabases) || importedDatabases.length === 0) {
          alert('Kein importierbares SQL erkannt.');
          return;
        }

        const activeDbIndex = dbSmallDesignerState.selectedDatabaseIndex;
        dbSmallDesignerState.model.databases[activeDbIndex] = importedDatabases[0];
        if (importedDatabases.length > 1) {
          dbSmallDesignerState.model.databases.splice(activeDbIndex + 1, 0, ...importedDatabases.slice(1));
        }

        dbSmallDesignerState.selectedTableIndex = 0;
        dbSmallDesignerState.rowDrafts = {};
        dbSmallDesignerState.sortStates = {};
        markDbSmallDirty();
        renderDbSmallDesignerView();
        alert(importedDatabases.length > 1
          ? `${importedDatabases.length} Datenbanken aus SQL importiert.`
          : 'SQL in aktive Datenbank importiert.');
      } catch (err) {
        alert('SQL-Import fehlgeschlagen: ' + (err?.message || err));
      }
    }
  };

  designerPanel.onchange = (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement)) return;
    if (!dbSmallDesignerState.model) return;

    const activeDatabase = dbSmallDesignerState.model.databases?.[dbSmallDesignerState.selectedDatabaseIndex];
    const activeTable = activeDatabase?.tables?.[dbSmallDesignerState.selectedTableIndex];
    if (!activeTable) return;
    const role = String(target.dataset.role || '');

    if (role === 'table-name-edit' && target instanceof HTMLInputElement) {
      dbSmallDesignerState.tableNameEdit.value = target.value;
      return;
    }

    if (role === 'col-name' || role === 'col-type' || role === 'col-pk' || role === 'col-fk' || role === 'col-length' || role === 'col-int-variant' || role === 'col-float-variant') {
      const colIndex = Number(target.dataset.colIndex || -1);
      const column = activeTable.columns?.[colIndex];
      if (!column) return;

      if (role === 'col-name') {
        const oldName = String(target.dataset.oldName || column.name || '');
        const newName = String(target.value || '').trim() || oldName;
        column.name = newName;
        target.dataset.oldName = newName;
        (activeTable.rows || []).forEach((row) => {
          if (oldName !== newName && Object.prototype.hasOwnProperty.call(row, oldName)) {
            row[newName] = row[oldName];
            delete row[oldName];
          }
          if (!Object.prototype.hasOwnProperty.call(row, newName)) {
            row[newName] = '';
          }
        });
        dbSmallDesignerState.rowDrafts = {};
        markDbSmallDirty();
        renderDbSmallDesignerView();
        return;
      }

      if (role === 'col-type' && target instanceof HTMLSelectElement) {
        if (column.pk) {
          column.type = 'AUTO';
          column.length = '';
          column.integerVariant = 'INT';
          column.floatVariant = 'FLOAT';
          renderDbSmallDesignerView();
          return;
        }
        column.type = normalizeDbSmallType(target.value);
        if (column.type === 'VARCHAR') {
          column.length = String(column.length || '').trim() || '50';
          column.integerVariant = 'INT';
          column.floatVariant = 'FLOAT';
        } else if (column.type === 'INTEGER') {
          column.length = '';
          column.integerVariant = normalizeDbSmallIntegerVariant(column.type, column.integerVariant);
          column.floatVariant = 'FLOAT';
        } else if (column.type === 'FLOAT') {
          column.length = '';
          column.integerVariant = 'INT';
          column.floatVariant = normalizeDbSmallFloatVariant(column.type, column.floatVariant);
        } else {
          column.length = '';
          column.integerVariant = 'INT';
          column.floatVariant = 'FLOAT';
        }
        markDbSmallDirty();
        renderDbSmallDesignerView();
        return;
      }
      if (role === 'col-length' && target instanceof HTMLInputElement) {
        const raw = String(target.value || '').trim();
        if (!column.pk && normalizeDbSmallType(column.type) === 'VARCHAR') {
          column.length = raw.replace(/[^0-9]/g, '').slice(0, 5);
        } else {
          column.length = '';
        }
        markDbSmallDirty();
        return;
      }
      if (role === 'col-int-variant' && target instanceof HTMLSelectElement) {
        if (!column.pk && normalizeDbSmallType(column.type) === 'INTEGER') {
          column.integerVariant = normalizeDbSmallIntegerVariant(column.type, target.value);
          markDbSmallDirty();
        }
        return;
      }
      if (role === 'col-float-variant' && target instanceof HTMLSelectElement) {
        if (!column.pk && normalizeDbSmallType(column.type) === 'FLOAT') {
          column.floatVariant = normalizeDbSmallFloatVariant(column.type, target.value);
          markDbSmallDirty();
        }
        return;
      }
      if (role === 'col-pk' && target instanceof HTMLInputElement) {
        column.pk = target.checked;
        if (column.pk) {
          column.type = 'AUTO';
          column.length = '';
          column.integerVariant = 'INT';
          column.floatVariant = 'FLOAT';
        }
        (activeTable.rows || []).forEach((row) => {
          if (column.pk) {
            delete row[column.name];
          } else if (!Object.prototype.hasOwnProperty.call(row, column.name)) {
            row[column.name] = '';
          }
        });
        dbSmallDesignerState.rowDrafts = {};
        markDbSmallDirty();
        renderDbSmallDesignerView();
        return;
      }
      if (role === 'col-fk' && target instanceof HTMLInputElement) {
        column.fk = target.checked;
        markDbSmallDirty();
      }
      return;
    }

  };
}

function renderDbSmallDesignerView() {
  if (!dbSmallDesignerState.model) return;
  const model = normalizeDbSmallModel(dbSmallDesignerState.model);
  dbSmallDesignerState.model = model;
  dbSmallDesignerState.selectedDatabaseIndex = getSelectedDbSmallDatabaseIndex(model);
  setDbSmallWorkspaceMode(true);
  renderDbSmallTableNavigation(model);
  renderDbSmallDesignerCenter(model);
  renderDbSmallSqlPanel(model);
}

async function renderDbSmallDesigner(project) {
  if (!project?.id) return;
  await ensureDbSmallModelLoaded(project);
  renderDbSmallDesignerView();
}

function updateWebHelpButton(project) {
  const helpBtn = document.getElementById('web-help-btn');
  if (!helpBtn) return;

  const shouldShow = Boolean(project && isHtmlLikeProject(project));
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
  if (!currentProject || !isHtmlLikeProject(currentProject)) {
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
    isCodeUiMode: isHtmlLikeProject(currentProject)
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
      <span class="project-nav-type">${getProjectTypeLabel(project.project_type)}</span>
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
    
    if (!isDbSmallProject(project)) {
      setDbSmallWorkspaceMode(false);

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

              if (!currentProject || !isHtmlLikeProject(currentProject)) {
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
    } else {
      if (projectFileManager && typeof projectFileManager.destroy === 'function') {
        projectFileManager.destroy();
      }
      projectFileManager = null;
    }
    
    // Show GUI container for HTML/Mixed and db_small projects
    const guiContainer = document.getElementById('gui-container');
    
    if (isHtmlLikeProject(project)) {
      // Always show GUI container for HTML/Mixed, even if empty (will render on first RUN)
      guiContainer.classList.add('active');
      setProjectsRightPanelMode(true);
      setProjectGuiPlaceholder('');
      guiContainer.dataset.projectId = String(project.id);
      delete guiContainer.dataset.codeUiRunBound;
    } else if (isDbSmallProject(project)) {
      await renderDbSmallDesigner(project);
    } else {
      // For python-only projects: hide GUI container completely
      guiContainer.classList.remove('active');
      setProjectsRightPanelMode(false);
      guiContainer.innerHTML = '';
      delete guiContainer.dataset.projectHtmlRendered;
      delete guiContainer.dataset.projectHtmlDirty;
      delete guiContainer.dataset.projectHtmlActiveFolder;
      delete guiContainer.dataset.projectId;
      delete guiContainer.dataset.codeUiRunBound;
    }
    
    // Clear output
    document.getElementById('output-container').textContent = '';
    document.getElementById('plot-container').innerHTML = '';
    
    // Save as last opened project (DB + localStorage fallback)
    await persistLastOpenedProject(normalizedProjectId);
    
    // Auto-open init.py after FileTreeManager is ready
    if (!isDbSmallProject(project)) {
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
    }
    
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
        ${getProjectTypeLabel(project.project_type)}
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
    
    ${(project.project_type === 'html' || project.project_type === 'mixed' || project.project_type === 'db_small') ? `
      <div class="project-info-section">
        <h4>Hilfe</h4>
        ${project.project_type === 'db_small'
          ? '<span class="help-link" style="display:inline-block;opacity:0.9;">Nutze den DB-Designer rechts für Tabellen, Testdaten und SQL-Export.</span>'
          : '<a href="/public/help/idegui/index.html" target="_blank" rel="noopener noreferrer" class="help-link">❓ idegui Dokumentation</a>'}
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
    const existingInputs = guiContainer.querySelectorAll('[data-element]');
    existingInputs.forEach(input => {
      const name = input.getAttribute('data-element');
      if (name && input.value !== undefined) {
        preservedValues[name] = input.value;
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
      const input = guiContainer.querySelector(`[data-element="${name}"]`);
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
