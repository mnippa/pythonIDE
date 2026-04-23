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
    cache: 'no-store'
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
}

async function openFileInEditor(fileId, fileName, content) {
  cacheCurrentProjectEditorDraft();

  const editor = await waitForEditorInstance();
  if (!editor) return;

  const normalizedId = Number(fileId || 0);
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

  const guiContainer = document.getElementById('gui-container');
  const alreadyRendered = Boolean(
    guiContainer
    && guiContainer.dataset.projectHtmlRendered === '1'
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

  if (!isPythonFile(fileName)) {
    const initFile = await readProjectFileByName(currentProject.id, 'init.py');
    const initFileId = Number(initFile?.fileId || initFile?.id || 0);
    const draft = getProjectDraftContent(initFileId);
    if (draft !== null) {
      code = String(draft || '');
      fileName = 'init.py';
    } else if (initFile?.content != null) {
      code = String(initFile.content || '');
      fileName = 'init.py';
    }
  }

  return {
    code,
    fileName,
    projectType: String(currentProject.project_type || 'python').toLowerCase(),
    isCodeUiMode: isHtmlLikeProject(currentProject)
  };
}

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
    cache: 'no-store'
  });

  if (!treeResponse.ok) return null;

  const treeData = await treeResponse.json();
  const treeNodes = Array.isArray(treeData?.tree)
    ? treeData.tree
    : (Array.isArray(treeData?.tree?.children) ? treeData.tree.children : []);

  const pyFiles = collectProjectPythonFiles(treeNodes);
  if (!pyFiles.length) return null;

  const pathToMeta = new Map(pyFiles.map((f) => [f.path, f]));

  let mainPath = '';
  if (currentOpenFileId) {
    const openFile = pyFiles.find((f) => Number(f.id) === Number(currentOpenFileId));
    if (openFile) {
      mainPath = openFile.path;
    }
  }

  if (!mainPath) {
    const initFile = pyFiles.find((f) => f.path === 'init.py' || f.name === 'init.py');
    if (initFile) {
      mainPath = initFile.path;
    }
  }

  if (!mainPath && pyFiles.length > 0) {
    mainPath = pyFiles[0].path;
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
    const initFile = await readProjectFileByName(currentProject.id, 'init.py');
    if (!initFile?.fileId) {
      throw new Error('Keine aktive Datei zum Speichern');
    }
    currentOpenFileId = Number(initFile.fileId);
    currentOpenFileName = initFile.fileName || 'init.py';
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
    credentials: 'include'
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
  try {
    window.currentProject = null;
    updateWebHelpButton(null);
    setProjectsRightPanelMode(false);
    
    const response = await fetch(`../api/projects/load.php?id=${projectId}`, {
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

    await ensureInitPyExists(projectId, project.name, project.code || '');
    
    // Update UI
    document.getElementById('project-page-title').textContent = project.name || 'Projekt';
    
    // Render project list with active state
    renderProjectList();
    
    // Update project details panel
    updateProjectDetails(project);
    
    // Load editor code from init.py (fallback to project.code)
    console.log('[projects-editor] Loading project code...');
    const editorReady = await waitForEditorInstance();
    if (editorReady) {
      try {
        const preferredFile = await loadPreferredProjectFile(projectId, project.code || '');
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
    console.log('[projects-editor] Starting file tree initialization for project:', projectId);
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
          projectId,
          projectName: project.name,
          readOnly: false,
          doubleClickAction: 'open-folder',
          beforeFileSelect: async () => {
            cacheCurrentProjectEditorDraft();
            return true;
          },
          onFileSelected: async (fileId, fileName, content) => {
            await openFileInEditor(fileId, fileName, content);
            console.log('[projects-editor] Opened file from tree:', fileName);
          },
          onFileSaved: () => {},
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
        await renderFileTreeManually(projectId);
      }
    } else {
      console.log('[projects-editor] FileTreeManager not available, using manual tree');
      await renderFileTreeManually(projectId);
      setTimeout(() => refreshAllProjectDirtyMarkers(), 0);
    }
    
    // Show GUI container only for HTML/Mixed projects
    const guiContainer = document.getElementById('gui-container');
    
    // Load and render HTML if project_type is html or mixed
    if (project.project_type === 'html' || project.project_type === 'mixed') {
      // Always show GUI container for HTML/Mixed, even if empty (will render on first RUN)
      guiContainer.classList.add('active');
      setProjectsRightPanelMode(true);
      guiContainer.innerHTML = '<p style="color: #888; padding: 20px; text-align: center;">Drücke "Run", um die GUI anzuzeigen</p>';
      guiContainer.dataset.projectHtmlRendered = '0';
      guiContainer.dataset.projectId = String(project.id);
      delete guiContainer.dataset.codeUiRunBound;
    } else {
      // For python-only projects: hide GUI container completely
      guiContainer.classList.remove('active');
      setProjectsRightPanelMode(false);
      guiContainer.innerHTML = '';
      delete guiContainer.dataset.projectHtmlRendered;
      delete guiContainer.dataset.projectId;
      delete guiContainer.dataset.codeUiRunBound;
    }
    
    // Clear output
    document.getElementById('output-container').textContent = '';
    document.getElementById('plot-container').innerHTML = '';
    
    // Save as last opened project (DB + localStorage fallback)
    await persistLastOpenedProject(projectId);
    
    // Auto-open init.py after FileTreeManager is ready
    setTimeout(async () => {
      try {
        const initFile = await readProjectFileByName(projectId, 'init.py');
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
        ${project.project_type === 'html' ? 'HTML/Web' : project.project_type === 'mixed' ? 'Gemischt' : 'Python'}
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
    
    ${project.project_type === 'html' || project.project_type === 'mixed' ? `
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
    
    // Helper to read a project file by name
    const readProjectFile = async (fileName) => {
      console.log(`[projects-editor] Reading file: ${fileName}`);
      // Get file tree to find file ID
      const treeResponse = await fetch(`../api/projects/files-v2.php?action=tree&project_id=${currentProject.id}`, {
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
      
      const tree = treeData.tree || treeData || [];
      console.log('[projects-editor] File tree structure:', tree);
      const filesToSearch = Array.isArray(tree) ? tree : (tree.children || []);
      findFile(filesToSearch);
      
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
      guiContainer.innerHTML = '<p style="color: #888; padding: 20px; text-align: center;">Drücke "Run", um die GUI anzuzeigen</p>';
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
    
    // Inject body HTML into container
    guiContainer.innerHTML = bodyHtml;
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
    guiContainer.dataset.projectId = String(currentProject.id);
    console.log('[projects-editor] GUI container marked active');
    
  } catch (error) {
    console.error('[projects-editor] Error rendering HTML:', error);
    // Keep container visible but show error placeholder
    const guiContainer = document.getElementById('gui-container');
    guiContainer.innerHTML = '<p style="color: #888; padding: 20px; text-align: center;">Fehler beim Laden der GUI</p>';
    guiContainer.classList.add('active');
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
      credentials: 'include'
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
      templateSelect.value = 'python_logic';
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
      console.log('[projects-editor] File saved:', currentOpenFileName);
    } catch (error) {
      console.error('[projects-editor] Save failed:', error);
      alert('Speichern fehlgeschlagen');
    }
  });

  document.getElementById('save-all-project-btn')?.addEventListener('click', async () => {
    try {
      await saveAllProjectFiles();
      console.log('[projects-editor] All files saved');
    } catch (error) {
      console.error('[projects-editor] Save all failed:', error);
      alert('Alle speichern fehlgeschlagen');
    }
  });

  document.getElementById('project-export-btn')?.addEventListener('click', async () => {
    await exportCurrentProjectToFile();
  });

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
