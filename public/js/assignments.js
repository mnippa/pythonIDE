// assignments.js - Assigned tasks panel for logged-in users

const assignmentState = {
  assignments: [],
  tasksByAssignment: {},
  assignmentDetails: {},
  currentTask: null,
  currentAssignmentId: null,
  currentTaskId: null,
  currentUserAssignmentId: null,
  taskStatuses: {},
  taskAttempts: {},
  taskIterations: {},
  taskUserAnswers: {}, // Store user answers: { taskId: { selected_options: [], text_answer: '', variable_values: {} } }
  taskRuns: {},
  taskStartTimes: {}, // Track start time for each task: { taskId: timestamp }
  taskCompletedAt: {}, // Track completion timestamp for each task: { taskId: 'YYYY-MM-DD HH:MM:SS' }
  expandedAssignmentId: null, // Track which assignment is expanded
  hintsRevealed: {}, // Track revealed hints per task: { taskId: [1, 2, 3] }
  solutionVisible: {}, // Track solution visibility per task: { taskId: boolean }
  solutionMode: false, // Track if currently in solution mode (readonly)
  savedCodeBeforeSolution: null, // Store user code before showing solution
  hasAutoLoaded: false, // Flag to prevent multiple auto-loads
  taskLoadToken: 0, // Guards against async race conditions during fast task switching
  // Activity tracking: accumulated active seconds per task
  taskActiveSeconds: {}, // { taskId: totalSeconds }
  taskLastActivityTime: {}, // { taskId: timestamp }
  taskActivityIntervals: {}, // { taskId: timerId }
  taskFileMeta: {} // { taskId: { path: { read_only, ... } } }
};

// Export to window for editor-setup.js access
window.assignmentState = assignmentState;

// Activity tracking constants
const ACTIVITY_HEARTBEAT_INTERVAL = 30000; // Send heartbeat every 30 seconds
const ACTIVITY_IDLE_TIMEOUT = 90000; // 90 seconds of inactivity = idle
const ACTIVITY_DEBUG = false;

function logActivityDebug(message, data) {
  if (!ACTIVITY_DEBUG) return;
  if (data !== undefined) {
    console.debug('[activity]', message, data);
  } else {
    console.debug('[activity]', message);
  }
}

function $(id) {
  return document.getElementById(id);
}

function getTestUserQueryParam() {
  return window.TEST_USER_ID ? `&test_user_id=${encodeURIComponent(window.TEST_USER_ID)}` : '';
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

const taskDraftFiles = {};
const taskSavedSnapshots = {};

function ensureTaskFileMaps(taskId) {
  const key = String(taskId);
  taskDraftFiles[key] = taskDraftFiles[key] || {};
  taskSavedSnapshots[key] = taskSavedSnapshots[key] || {};
  return key;
}

function setTaskDraftContent(taskId, path, content) {
  if (!taskId || !path) return;
  const key = ensureTaskFileMaps(taskId);
  taskDraftFiles[key][path] = String(content ?? '');
}

function getTaskDraftContent(taskId, path) {
  if (!taskId || !path) return null;
  const key = String(taskId);
  const taskDraft = taskDraftFiles[key] || {};
  return Object.prototype.hasOwnProperty.call(taskDraft, path) ? taskDraft[path] : null;
}

function setTaskSavedSnapshot(taskId, path, content) {
  if (!taskId || !path) return;
  const key = ensureTaskFileMaps(taskId);
  taskSavedSnapshots[key][path] = String(content ?? '');
}

function clearTaskDrafts(taskId) {
  if (!taskId) return;
  const key = String(taskId);
  delete taskDraftFiles[key];
}

function cacheCurrentEditorDraft() {
  if (!window.editorInstance || !window.currentFile) return;
  const { taskId, path } = window.currentFile;
  if (!taskId || !path) return;
  setTaskDraftContent(taskId, path, window.editorInstance.getValue());
}

function hasUnsavedDraftsForTask(taskId) {
  if (!taskId) return false;
  const key = String(taskId);
  const drafts = taskDraftFiles[key] || {};
  const snapshots = taskSavedSnapshots[key] || {};
  return Object.keys(drafts).some((path) => {
    const draftValue = String(drafts[path] ?? '');
    const snapshotValue = String(snapshots[path] ?? '');
    return draftValue !== snapshotValue;
  });
}

async function persistTaskFileContent(taskId, path, content, isVirtual = false) {
  if (window.TEST_MODE_NO_PERSIST === true) {
    setTaskSavedSnapshot(taskId, path, content);
    setTaskDraftContent(taskId, path, content);
    return true;
  }

  const isAdminFolderMode = window.testMode === true;

  // Find the current task to check if it has folderstructure
  const allTasks = Object.values(assignmentState.tasksByAssignment || {}).flat();
  const currentTaskMeta = allTasks.find(t => Number(t.id) === Number(taskId));
  const hasFolderStructure = currentTaskMeta && (
    currentTaskMeta.folderstructure === 1 || 
    currentTaskMeta.folderstructure === true || 
    currentTaskMeta.folderstructure === '1'
  );

  if (!isAdminFolderMode) {
    const testUserParam = window.TEST_USER_ID ? `?test_user_id=${window.TEST_USER_ID}` : '';
    
    // If task has no folder structure, use simple update API
    if (!hasFolderStructure) {
      const response = await fetch(`/pythonIDE/api/user_tasks/update.php${testUserParam}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, current_code: content })
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result?.error || 'Speichern fehlgeschlagen');
      }
    } else {
      // Task has folder structure, use folder-files API
      const response = await fetch(`/pythonIDE/api/user_tasks/folder-files.php?action=save${testUserParam}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, path, content })
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result?.error || 'Speichern fehlgeschlagen');
      }
    }
  } else if (isVirtual && path === 'init.py') {
    const response = await fetch(`/pythonIDE/api/tasks/folder-manage.php?action=save_template`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ task_id: taskId, content })
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result?.error || 'Speichern fehlgeschlagen');
    }
  } else {
    const response = await fetch(`/pythonIDE/api/tasks/folder-manage.php?action=save`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ task_id: taskId, path, content })
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result?.error || 'Speichern fehlgeschlagen');
    }
  }

  if (window.taskInitPyContent && path === 'init.py') {
    window.taskInitPyContent[taskId] = content;
  }

  setTaskSavedSnapshot(taskId, path, content);
  setTaskDraftContent(taskId, path, content);
  return true;
}

async function saveAllTaskDrafts(taskId) {
  if (!taskId) return true;

  cacheCurrentEditorDraft();

  const key = String(taskId);
  const drafts = taskDraftFiles[key] || {};
  const snapshots = taskSavedSnapshots[key] || {};

  for (const path of Object.keys(drafts)) {
    const draftValue = String(drafts[path] ?? '');
    const snapshotValue = String(snapshots[path] ?? '');
    if (draftValue === snapshotValue) continue;

    const isVirtual = path === 'init.py';
    await persistTaskFileContent(taskId, path, draftValue, isVirtual);
  }

  return true;
}

async function confirmTaskSwitchWithDrafts(nextTaskId) {
  const currentTaskId = assignmentState.currentTaskId;
  if (!currentTaskId || Number(currentTaskId) === Number(nextTaskId)) return true;

  cacheCurrentEditorDraft();

  if (!hasUnsavedDraftsForTask(currentTaskId)) {
    return true;
  }

  const shouldSave = window.confirm('Du hast ungespeicherte Änderungen. Speichern, bevor du den Task wechselst?');
  if (shouldSave) {
    try {
      await saveAllTaskDrafts(currentTaskId);
      return true;
    } catch (error) {
      alert('Speichern fehlgeschlagen: ' + (error?.message || error));
      return false;
    }
  }

  const shouldDiscard = window.confirm('Änderungen verwerfen und Task wechseln?');
  if (!shouldDiscard) {
    return false;
  }

  clearTaskDrafts(currentTaskId);
  return true;
}

async function requestJson(url, options = {}) {
  const isTestMode = window.testMode === true;
  if (isTestMode) {
    // Intercept WRITE operations in test mode - prevent DB writes
    if (url.includes('/api/user_tasks/update.php') || url.includes('/api/user_tasks/heartbeat.php') || url.includes('/api/user_tasks/submit.php')) {
      // Just return success without writing to DB
      return { ok: true };
    }
    // Allow READ operations to hit the real API
  }

  const response = await fetch(url, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    ...options
  });

  const data = await response.json();
  if (!response.ok || (data && data.ok === false)) {
    const msg = data && data.error ? data.error : response.statusText;
    throw new Error(msg);
  }
  return data;
}

function statusClass(status) {
  if (!status) return 'status-badge';
  return `status-badge status-${status}`;
}

// ============================================================================
// Activity Tracking System: Track accumulated active seconds per task
// ============================================================================

/**
 * Track activity on current task: updates last activity time and sends heartbeats
 */
function startActivityTracking(taskId) {
  if (!taskId) return;
  logActivityDebug('startActivityTracking', { taskId });
  
  // Initialize task activity tracking
  assignmentState.taskLastActivityTime[taskId] = Date.now();
  assignmentState.taskActiveSeconds[taskId] = assignmentState.taskActiveSeconds[taskId] || 0;
  
  // Clear any existing interval
  if (assignmentState.taskActivityIntervals[taskId]) {
    clearInterval(assignmentState.taskActivityIntervals[taskId]);
  }

  // Start heartbeat interval: send accumulated seconds to server periodically
  assignmentState.taskActivityIntervals[taskId] = setInterval(() => {
    const now = Date.now();
    const lastActivity = assignmentState.taskLastActivityTime[taskId] || now;
    const timeSinceLastActivity = now - lastActivity;
    logActivityDebug('heartbeat tick', { taskId, timeSinceLastActivity });
    
    // Only add time if user was active (not idle)
    if (timeSinceLastActivity < ACTIVITY_IDLE_TIMEOUT) {
      const secondsSinceLastHeartbeat = ACTIVITY_HEARTBEAT_INTERVAL / 1000;
      assignmentState.taskActiveSeconds[taskId] += secondsSinceLastHeartbeat;
      logActivityDebug('active time added', { taskId, secondsSinceLastHeartbeat });
      
      // Send heartbeat to server
      sendActivityHeartbeat(taskId, secondsSinceLastHeartbeat, true);
    } else {
      // User is idle
      logActivityDebug('idle heartbeat', { taskId });
      sendActivityHeartbeat(taskId, 0, false);
    }
  }, ACTIVITY_HEARTBEAT_INTERVAL);
  
  // Set up activity event listeners on editor
  const editor = window.editorInstance;
  if (editor) {
    const editorContainer = editor.getDomNode();
    if (editorContainer) {
      const updateActivity = () => {
        assignmentState.taskLastActivityTime[taskId] = Date.now();
        logActivityDebug('activity event', { taskId });
      };
      
      editorContainer.addEventListener('click', updateActivity);
      editorContainer.addEventListener('keydown', updateActivity);
      editorContainer.addEventListener('scroll', updateActivity);
      logActivityDebug('activity listeners bound', { taskId });
    } else {
      logActivityDebug('editor container missing', { taskId });
    }
  } else {
    logActivityDebug('editor instance missing', { taskId });
  }
}

/**
 * Stop tracking activity for a task
 */
function stopActivityTracking(taskId) {
  if (assignmentState.taskActivityIntervals[taskId]) {
    clearInterval(assignmentState.taskActivityIntervals[taskId]);
    delete assignmentState.taskActivityIntervals[taskId];
    logActivityDebug('stopActivityTracking', { taskId });
  }
}

/**
 * Send activity heartbeat to server
 * @param {number} taskId - Task ID
 * @param {number} deltaSeconds - Seconds of active time since last heartbeat
 * @param {boolean} isActive - Whether user is currently active
 */
async function sendActivityHeartbeat(taskId, deltaSeconds, isActive) {
  logActivityDebug('sendActivityHeartbeat', { taskId, deltaSeconds, isActive });
  try {
    const response = await fetch('../api/user_tasks/heartbeat.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        task_id: taskId,
        active_seconds_delta: Math.max(0, deltaSeconds),
        is_active: isActive ? 1 : 0
      })
    });
    const text = await response.text();
    logActivityDebug('heartbeat raw response', { status: response.status, text: text.slice(0, 200) });
    let json = null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch (err) {
      console.warn('Heartbeat JSON parse failed:', err);
    }
    if (json) {
      logActivityDebug('heartbeat response', json);
    }
    if (!response.ok || (json && json.ok === false)) {
      throw new Error((json && json.error) ? json.error : response.statusText);
    }
  } catch (err) {
    console.warn('Failed to send activity heartbeat:', err);
  }
}

/**
 * Exposed global function to track task activity (called from editor)
 */
window.trackTaskActivity = function(taskId) {
  startActivityTracking(taskId);
};

// Display task details in left sidebar
function showTaskDetails(task, activeTab = 'details') {
  console.log('[TASK DETAILS] showTaskDetails called for task:', task.id, task.title, 'activeTab:', activeTab);
  console.log('[TASK DETAILS] window.testMode:', window.testMode, 'task_type:', task.task_type);
  
  // Normalize task data (convert escaped newlines)
  normalizeTaskData(task);
  
  assignmentState.currentTask = task;
  
  const contentEl = $('task-details-content');
  const panel = $('task-details-panel');
  const app = document.querySelector('.app');

  console.log('[TASK DETAILS] Elements found - contentEl:', !!contentEl, 'panel:', !!panel, 'app:', !!app);

  if (!contentEl || !panel) return;

  panel.classList.add('active');
  if (app) app.classList.add('with-task-details');

  // Get status and attempts for header
  const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';
  const attempts = assignmentState.taskAttempts[task.id] || 0;
  const maxAttempts = task.max_attempts || 1;

  const availableHints = [];
  if (task.hint1) {
    availableHints.push({ id: 1, text: task.hint1 });
  }
  if (task.hint2) {
    availableHints.push({ id: 2, text: task.hint2 });
  }
  if (task.hint3) {
    availableHints.push({ id: 3, text: task.hint3 });
  }

  const revealedRaw = assignmentState.hintsRevealed[task.id] || [];
  const revealedSet = new Set(revealedRaw);
  const revealedHints = availableHints.filter(hint => revealedSet.has(hint.id));
  const totalHints = availableHints.length;
  const revealedCount = revealedHints.length;
  const nextHint = availableHints.find(hint => !revealedSet.has(hint.id));

  // --- Extract test types for display (code tasks only) ---
  let testTypeHtml = '';
  if ((task.task_type === 'code' || task.task_type === 'code_ui') && task.test_cases) {
    console.log('[TEST TYPES] Parsing test_cases for task:', task.id);
    let testTypes = new Set(); // Use Set to avoid duplicates
    
    try {
      let parsed = JSON.parse(task.test_cases);
      
      // Handle intelligent test config (single object with mode, tests, etc.)
      if (parsed && !Array.isArray(parsed) && parsed.mode) {
        testTypes.add(parsed.mode);
        parsed = [{type: 'intelligent', ...parsed}];
      }
      
      // Extract types from all test cases
      if (Array.isArray(parsed)) {
        parsed.forEach(tc => {
          // Check both 'type' and 'mode' fields (handle inconsistency)
          if (tc.type) testTypes.add(tc.type);
          if (tc.mode) testTypes.add(tc.mode);
          
          // Legacy: if neither exists, assume 'output'
          if (!tc.type && !tc.mode) testTypes.add('output');
        });
      }
      
      console.log('[TEST TYPES] Found test types:', Array.from(testTypes));
    } catch (e) {
      console.warn('[TEST TYPES] Failed to parse test_cases:', e);
    }
    
    // Convert Set to sorted array
    const typeOrder = ['output', 'function', 'variable', 'intelligent', 'code_check'];
    const sortedTypes = typeOrder.filter(t => testTypes.has(t));
    
    // Map to icons (requested by user)
    const typeMap = {
      'output': {icon: '🖨️', tooltip: 'Output-Test'},
      'function': {icon: 'ƒ', tooltip: 'Function-Test'},
      'variable': {icon: '𝑥', tooltip: 'Variable-Test'},
      'intelligent': {icon: '🧠', tooltip: 'Intelligent-Test'},
      'code_check': {icon: '🔑', tooltip: 'Keywords-Test'}
    };
    
    if (sortedTypes.length > 0) {
      testTypeHtml = `<span class="test-type-indicators" style="margin-left:10px; font-weight:normal; font-variant:normal;">${sortedTypes.map(t => {
        const extraStyle = t === 'function' ? 'font-variant:normal;text-transform:none;' : '';
        return `<span class="test-type-icon" title="${typeMap[t]?.tooltip || t}" style="margin:0 4px; font-size:16px; cursor:help; ${extraStyle}">${typeMap[t]?.icon || '❓'}</span>`;
      }).join('')}</span>`;
    }
  }

  // Determine attempts label before using it
  const isIterative = task.task_type === 'code_reading' || task.task_type === 'code_random_complex';
  const attemptsLabel = isIterative ? 'Fehlversuche' : 'Versuche';

  let descriptionHtml = '';
  // Show only title in sidebar, description is shown centrally
  if (task.title) {
    const attemptsInfo = (task.task_type !== 'code' && task.task_type !== 'code_ui') ? ` <span class="task-attempts-info" style="margin-left:8px;font-size:0.9em;color:var(--text-secondary);">${attemptsLabel}: ${attempts}/${maxAttempts}</span>` : '';
    descriptionHtml = `<div class="task-description-box">
      <h4 style="display:inline-flex;align-items:center;font-weight:normal;">AUFGABE: ${escapeHtml(task.title)} ${getStatusEmoji(status)}${testTypeHtml}${attemptsInfo}</h4>
    </div>`;
  }

  // Details Tab Content (Description + Stoff, Status moved to title area)
  let detailsHtml = '';
  
  // Show description for all task types (optional context/metadata)
  if (task.description) {
    detailsHtml += `<div class="description-section">
      <div>${task.description}</div>
    </div>`;
  }
  
  if (task.stoff) {
    detailsHtml += `<div class="stoff-section">
      <h4>📚 Lerninhalt (Stoff)</h4>
      <div>${task.stoff}</div>
    </div>`;
  }

  const canShowSolution = window.testMode === true;
  console.log('[SOLUTION CHECK] canShowSolution:', canShowSolution, 'task_type:', task.task_type);
  // Check if solution exists based on task type
  let hasSolution = false;
  if (canShowSolution) {
    if (task.task_type === 'code' || task.task_type === 'code_ui') {
      hasSolution = !!task.solution_code;
      console.log('[SOLUTION] Code task - hasSolution:', hasSolution, 'solution_code exists:', !!task.solution_code, 'solution_code:', task.solution_code?.substring(0, 50));
    } else if (task.task_type === 'single_choice' || task.task_type === 'multiple_choice') {
      hasSolution = !!(task.options && task.options.some(opt => opt.is_correct));
      console.log('[SOLUTION] Choice task - hasSolution:', hasSolution);
    } else if (task.task_type === 'free_text') {
      hasSolution = !!task.correct_answer;
      console.log('[SOLUTION] Free text task - hasSolution:', hasSolution);
    } else if (task.task_type === 'code_reading') {
      // Code reading has solution if code_template and variable_overrides exist (will be computed from iterations)
      // correct_answer may be NULL (using per-iteration expected values)
      hasSolution = !!(task.code_template && task.variable_overrides);
      console.log('[SOLUTION] Code reading task - hasSolution:', hasSolution, 'has code_template:', !!task.code_template, 'has variable_overrides:', !!task.variable_overrides);
    } else if (task.task_type === 'code_random_complex') {
      // Code random complex has solution if solution_code exists
      hasSolution = !!task.solution_code;
      console.log('[SOLUTION] Code random complex task - hasSolution:', hasSolution, 'solution_code exists:', !!task.solution_code);
    }
  } else {
    console.log('[SOLUTION] Cannot show solution - testMode:', window.testMode, 'task_type:', task.task_type);
  }
  
  console.log('[SOLUTION RESULT] Final hasSolution:', hasSolution, 'for task', task.id);

  if (detailsHtml === '' || detailsHtml.trim() === `<div class="task-status-header">
    <span class="${statusClass(status)}">${getStatusLabel(status)}</span>
    ${(task.task_type !== 'code' && task.task_type !== 'code_ui') ? `<span class="task-attempts-info">${attemptsLabel}: ${attempts}/${maxAttempts}</span>` : ''}
  </div>`) {
    detailsHtml += '<p>Keine weiteren Details vorhanden.</p>';
  }

  let hintsHtml = '';
  if (totalHints > 0) {
    hintsHtml += `<div class="task-hints-header">
      <span>Hinweise</span>
      <span id="hints-counter" class="task-hints-counter">${revealedCount}/${totalHints}</span>
    </div>`;

    if (revealedCount === 0) {
      hintsHtml += '<p class="task-hints-empty">Noch keine Hinweise freigeschaltet.</p>';
    }

    revealedHints.forEach((hint) => {
      const displayIndex = availableHints.findIndex(item => item.id === hint.id) + 1;
      hintsHtml += `<div class="hint-item revealed">
        <span class="hint-number">Hinweis ${displayIndex}:</span> ${escapeHtml(hint.text)}
      </div>`;
    });

    const nextIndex = nextHint ? (availableHints.findIndex(item => item.id === nextHint.id) + 1) : null;
    const buttonLabel = nextHint ? `Hinweis ${nextIndex} freischalten` : 'Alle Hinweise freigeschaltet';
    const disabledAttr = nextHint ? '' : 'disabled';
    hintsHtml += `<button type="button" class="hint-reveal-btn" id="hint-reveal-btn" ${disabledAttr}>${buttonLabel}</button>`;
  }

  // Build tabs - WITHOUT solution tab (solution is now a toggle button)
  let tabsHtml = `<div class="task-details-tabs">`;
  tabsHtml += `<button type="button" class="task-details-tab ${activeTab === 'details' ? 'active' : ''}" data-tab="details">Details</button>`;
  if (totalHints > 0) {
    tabsHtml += `<button type="button" class="task-details-tab ${activeTab === 'hints' ? 'active' : ''}" data-tab="hints">Hinweise <span class="task-tab-count">${revealedCount}/${totalHints}</span></button>`;
  }
  
  // Folder structure tab (if enabled for this task)
  if (task.folderstructure === 1 || task.folderstructure === true || task.folderstructure === '1') {
    tabsHtml += `<button type="button" class="task-details-tab ${activeTab === 'folders' ? 'active' : ''}" data-tab="folders">📁 Ordner</button>`;
  }
  
  // Solution toggle button (inside tabs container for proper alignment)
  if (hasSolution) {
    const solutionActive = assignmentState.solutionMode === true;
    const buttonClass = solutionActive ? 'solution-toggle-active' : 'solution-toggle-inactive';
    const buttonText = solutionActive ? '📝 Lösung AN' : '📝 Lösung';
    tabsHtml += `<button type="button" class="solution-toggle-btn ${buttonClass}" id="solution-toggle-btn" title="Lösung ein/aus-schalten">${buttonText}</button>`;
  }
  
  tabsHtml += `</div>`;

  // Build final HTML: description (with test types in h4), then tabs
  let html = descriptionHtml + tabsHtml;
  html += `<div class="task-details-panel-section ${activeTab === 'details' ? 'active' : ''}" data-tab-panel="details">${detailsHtml}</div>`;
  if (totalHints > 0) {
    html += `<div class="task-details-panel-section ${activeTab === 'hints' ? 'active' : ''}" data-tab-panel="hints">${hintsHtml}</div>`;
  }
  
  // Folder structure panel (empty for now)
  if (task.folderstructure === 1 || task.folderstructure === true || task.folderstructure === '1') {
    html += `<div class="task-details-panel-section ${activeTab === 'folders' ? 'active' : ''}" data-tab-panel="folders">
      <div id="folder-panel-content-${task.id}" style="color: var(--text-secondary); font-size: 13px;">
        Lade Dateien...
      </div>
    </div>`;
  }

  contentEl.innerHTML = html;
  
  // Load folder files if this is a folder structure task
  if (task.folderstructure === 1 || task.folderstructure === true || task.folderstructure === '1') {
    const folderPanelId = `folder-panel-content-${task.id}`;
    loadAndDisplayTaskFiles(folderPanelId, task.id);
  }

  const tabButtons = contentEl.querySelectorAll('.task-details-tab');
  const tabPanels = contentEl.querySelectorAll('.task-details-panel-section');

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const tabName = btn.dataset.tab;
      tabButtons.forEach(other => {
        other.classList.toggle('active', other === btn);
      });
      tabPanels.forEach(panelEl => {
        panelEl.classList.toggle('active', panelEl.dataset.tabPanel === tabName);
      });
    });
  });
  
  // Solution toggle button handler
  const solToggleBtn = $('solution-toggle-btn');
  if (solToggleBtn && hasSolution) {
    solToggleBtn.addEventListener('click', async () => {
      if (assignmentState.solutionMode) {
        // Turn OFF solution mode - restore original
        assignmentState.solutionMode = false;
        loadTaskIntoEditor(assignmentState.currentAssignmentId, task.id);
      } else {
        // Turn ON solution mode
        assignmentState.solutionMode = true;
        await loadSolutionIntoMainArea(task);
      }
      // Refresh the sidebar to update button color
      showTaskDetails(task, 'details');
    });
  }

  const revealBtn = $('hint-reveal-btn');
  if (revealBtn && nextHint) {
    revealBtn.addEventListener('click', async () => {
      if (!assignmentState.hintsRevealed[task.id]) {
        assignmentState.hintsRevealed[task.id] = [];
      }

      if (!assignmentState.hintsRevealed[task.id].includes(nextHint.id)) {
        assignmentState.hintsRevealed[task.id].push(nextHint.id);
      }

      try {
        const payload = {
          task_id: task.id,
          hints_revealed: assignmentState.hintsRevealed[task.id]
        };
        console.log('[HINT] Revealing hint - Payload:', payload);
        const response = await requestJson('../api/user_tasks/update.php', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
        console.log('[HINT] Response:', response);
      } catch (err) {
        console.warn('Failed to save hints progress:', err);
      }

      showTaskDetails(task, 'hints');
    });
  }
}

// Load solution into main editor/quiz area (not sidebar)
async function loadSolutionIntoMainArea(task) {
  // Refresh task data from API to ensure latest solution_code
  await refreshCurrentTaskFromAPI();
  task = assignmentState.currentTask || task;
  
  assignmentState.solutionMode = true;
  
  if (task.task_type === 'code' || task.task_type === 'code_ui') {
    // Save current code before showing solution
    const editor = window.editorInstance;
    if (editor) {
      assignmentState.savedCodeBeforeSolution = editor.getValue();
      if (task.solution_code) {
        // Convert escaped newlines to actual newlines (safeguard for older data)
        const displaySolution = task.solution_code.replace(/\\n/g, '\n');
        editor.setValue(displaySolution);
        // Make solution code editable in test mode (admin can edit and save)
        editor.updateOptions({ readOnly: false });
      }
    }
  } else if (task.task_type === 'single_choice' || task.task_type === 'multiple_choice') {
    // Render quiz with correct answers checked and disabled
    const quizContainer = document.getElementById('quiz-container');
    if (quizContainer && window.QuizRenderer) {
      window.QuizRenderer.renderSolution(task, quizContainer);
    }
  } else if (task.task_type === 'free_text') {
    // Show all acceptable answers below the textarea
    const quizContainer = document.getElementById('quiz-container');
    if (quizContainer) {
      const acceptableAnswers = task.correct_answer ? task.correct_answer.split(',').map(a => a.trim()) : [];
      quizContainer.innerHTML = `
        <div class="quiz-container solution-mode">
          <div class="quiz-question">
            ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
          </div>
          <div class="quiz-freetext">
            <textarea disabled rows="8" placeholder="(Bereich für Teilnehmerantwort)"></textarea>
          </div>
          <div class="solution-info" style="padding:12px; margin-top:16px; background:var(--bg-secondary, var(--panel)); border-left:3px solid #10b981; border-radius:4px;">
            <strong>✓ Akzeptierte Antworten / Schlüsselwörter:</strong>
            <ul style="margin:8px 0 0; padding-left:20px; list-style:disc;">
              ${acceptableAnswers.map(ans => `<li style="margin:4px 0;">${escapeHtml(ans)}</li>`).join('')}
            </ul>
          </div>
        </div>
      `;
    }
  } else if (task.task_type === 'code_reading') {
    // Compute solution by running code with current variables
    const quizContainer = document.getElementById('quiz-container');
    if (quizContainer) {
      const taskState = window.assignmentState?.taskUserAnswers?.[task.id] || {};
      const varValues = taskState.variable_values || {};
      const variableName = taskState.expectedVariableName || task.correct_answer || '?';
      const expectedType = taskState.expectedType || 'variable';
      const expectedValue = taskState.expectedValue;
      
      // Show loading state
      quizContainer.innerHTML = `
        <div class="quiz-container solution-mode">
          <div class="quiz-question">
            ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
            ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
          </div>
          <div class="code-reading-vars">
            <strong>Variablenwerte:</strong>
            <ul>
              ${Object.entries(varValues).map(([name, value]) => 
                `<li><code>${escapeHtml(name)} = ${value}</code></li>`
              ).join('')}
            </ul>
          </div>
          <div class="solution-info" style="padding:12px; margin:12px 0; background:var(--bg-secondary, var(--panel)); border-left:3px solid #f59e0b; border-radius:4px;">
            <strong>⏳ Lösung wird berechnet...</strong>
          </div>
        </div>
      `;
      
      if (expectedType === 'value') {
        const displayValue = expectedValue !== undefined ? expectedValue : '';
        const hasCodeTemplate = task.code_template && task.code_template.trim() !== '';
        quizContainer.innerHTML = `
          <div class="quiz-container solution-mode">
            <div class="quiz-question">
              ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
              ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
            </div>
            <div class="code-reading-vars">
              <strong>Variablenwerte:</strong>
              <ul>
                ${Object.entries(varValues).map(([name, value]) => 
                  `<li><code>${escapeHtml(name)} = ${value}</code></li>`
                ).join('')}
              </ul>
            </div>
            ${hasCodeTemplate ? `<div class="code-reading-code">
              <pre><code>${escapeHtml(task.code_template)}</code></pre>
            </div>` : ''}
            <div class="solution-info" style="padding:12px; margin:12px 0; background:var(--bg-secondary, var(--panel)); border-left:3px solid #10b981; border-radius:4px;">
              <strong>✓ Erwartete Ausgabe:</strong>
            </div>
            <div class="quiz-question">
              <input type="text" value="${escapeHtml(String(displayValue))}" disabled style="width:100%; padding:8px; font-family:monospace; background:var(--code-bg);" />
            </div>
          </div>
        `;
        return;
      }

      // Execute code to compute solution
      const hasCodeTemplate = task.code_template && task.code_template.trim() !== '';
      computeCodeReadingSolution(task, varValues, variableName).then(result => {
        quizContainer.innerHTML = `
          <div class="quiz-container solution-mode">
            <div class="quiz-question">
              ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
              ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
            </div>
            <div class="code-reading-vars">
              <strong>Variablenwerte:</strong>
              <ul>
                ${Object.entries(varValues).map(([name, value]) => 
                  `<li><code>${escapeHtml(name)} = ${value}</code></li>`
                ).join('')}
              </ul>
            </div>
            ${hasCodeTemplate ? `<div class="code-reading-code">
              <pre><code>${escapeHtml(task.code_template)}</code></pre>
            </div>` : ''}
            <div class="solution-info" style="padding:12px; margin:12px 0; background:var(--bg-secondary, var(--panel)); border-left:3px solid #10b981; border-radius:4px;">
              <strong>✓ Erwartete Ausgabe:</strong>
            </div>
            <div class="quiz-question">
              <label>Was ist der Wert von <code>${escapeHtml(variableName)}</code> am Ende?</label>
              <input type="text" value="${escapeHtml(String(result))}" disabled style="width:100%; padding:8px; font-family:monospace; background:var(--code-bg);" />
            </div>
          </div>
        `;
      }).catch(err => {
        quizContainer.innerHTML = `
          <div class="quiz-container solution-mode">
            <div class="quiz-question">
              ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
              ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
            </div>
            <div class="solution-info" style="padding:12px; background:#fee2e2; border-left:3px solid #ef4444; border-radius:4px;">
              <strong>❌ Fehler beim Berechnen der Lösung:</strong>
              <p style="margin:4px 0 0; color:#991b1b;">${escapeHtml(String(err))}</p>
            </div>
          </div>
        `;
      });
    }
  } else if (task.task_type === 'code_random_complex') {
    // Compute solution by executing solution_code with generated random values
    const quizContainer = document.getElementById('quiz-container');
    if (quizContainer) {
      let varValues = window.assignmentState?.taskUserAnswers?.[task.id]?.variable_values || {};
      
      // If no variable_values exist yet, we need to generate them now
      if (!varValues || Object.keys(varValues).length === 0) {
        console.log('[SOLUTION] No varValues found, generating values now...');
        
        quizContainer.innerHTML = `
          <div class="quiz-container solution-mode">
            <div class="solution-info" style="padding:12px; margin:12px 0; background:var(--bg-secondary, var(--panel)); border-left:3px solid #f59e0b; border-radius:4px;">
              <strong>⏳ Werte werden generiert...</strong>
            </div>
          </div>
        `;
        
        // Generate values using same logic as quiz renderer
        try {
          varValues = await generateRandomComplexValues(task);
        } catch (err) {
          quizContainer.innerHTML = `
            <div class="quiz-container solution-mode">
              <div class="solution-info" style="padding:12px; background:#fee2e2; border-left:3px solid #ef4444; border-radius:4px;">
                <strong>❌ Fehler beim Generieren der Werte:</strong>
                <p style="margin:4px 0 0; color:#991b1b;">${escapeHtml(String(err))}</p>
              </div>
            </div>
          `;
          return;
        }
      }
      
      // Show loading state
      quizContainer.innerHTML = `
        <div class="quiz-container solution-mode">
          <div class="quiz-question">
            ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
            ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
          </div>
          <div class="code-reading-vars">
            <strong>Zufallswerte für diese Iteration:</strong>
            <ul>
              ${Object.entries(varValues).map(([name, value]) => {
                const formatted = typeof value === 'object' ? JSON.stringify(value) : String(value);
                return `<li><code>${escapeHtml(name)} = ${escapeHtml(formatted)}</code></li>`;
              }).join('') || '<li><em>Keine Werte gespeichert</em></li>'}
            </ul>
          </div>
          <div class="solution-info" style="padding:12px; margin:12px 0; background:var(--bg-secondary, var(--panel)); border-left:3px solid #f59e0b; border-radius:4px;">
            <strong>⏳ Erwartete Ausgabe wird berechnet...</strong>
          </div>
        </div>
      `;
      
      // Compute the solution result
      computeRandomComplexSolution(task, varValues).then(result => {
        quizContainer.innerHTML = `
          <div class="quiz-container solution-mode">
            <div class="quiz-question">
              ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
              ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
            </div>
            <div class="code-reading-vars">
              <strong>Zufallswerte für diese Iteration:</strong>
              <ul>
                ${Object.entries(varValues).map(([name, value]) => {
                  const formatted = typeof value === 'object' ? JSON.stringify(value) : String(value);
                  return `<li><code>${escapeHtml(name)} = ${escapeHtml(formatted)}</code></li>`;
                }).join('') || '<li><em>Keine Werte gespeichert</em></li>'}
              </ul>
            </div>
            <div class="solution-info" style="padding:12px; margin:12px 0; background:var(--bg-secondary, var(--panel)); border-left:3px solid #10b981; border-radius:4px;">
              <strong>✓ Erwartete Ausgabe:</strong>
            </div>
            <div class="quiz-question">
              <textarea disabled rows="8" style="width:100%; padding:8px; font-family:monospace; background:var(--code-bg); color:var(--text-primary);">${escapeHtml(String(result))}</textarea>
            </div>
          </div>
        `;
      }).catch(err => {
        quizContainer.innerHTML = `
          <div class="quiz-container solution-mode">
            <div class="quiz-question">
              ${task.task_text ? `<div class="question-text">${window.QuizRenderer.formatText(task.task_text)}</div>` : ''}
              ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
            </div>
            <div class="solution-info" style="padding:12px; background:#fee2e2; border-left:3px solid #ef4444; border-radius:4px;">
              <strong>❌ Fehler beim Berechnen der Lösung:</strong>
              <p style="margin:4px 0 0; color:#991b1b;">${escapeHtml(String(err))}</p>
            </div>
          </div>
        `;
      });
    }
  }
}

// Compute solution for code_random_complex tasks
async function computeRandomComplexSolution(task, varValues) {
  // Wait for Pyodide to be ready
  if (!window.pyodide) {
    let attempts = 0;
    while (!window.pyodide && attempts < 100) {
      await new Promise(resolve => setTimeout(resolve, 100));
      attempts++;
    }
    if (!window.pyodide) {
      throw new Error('Pyodide konnte nicht geladen werden (Timeout)');
    }
  }
  
  let solutionCode = task.solution_code || '';
  if (!solutionCode) {
    throw new Error('Keine Musterlösung vorhanden');
  }
  
  // Convert escaped newlines to actual newlines (safeguard for older data)
  solutionCode = solutionCode.replace(/\\n/g, '\n');
  
  try {
    // Execute solution code and capture output
    const namespace = window.pyodide.globals.get('dict')();
    
    const toPythonLiteral = (value) => {
      if (value === null || value === undefined) return 'None';
      if (typeof value === 'string') return JSON.stringify(value);
      if (typeof value === 'number') return String(value);
      if (typeof value === 'boolean') return value ? 'True' : 'False';
      try {
        return JSON.stringify(value);
      } catch (err) {
        return JSON.stringify(String(value));
      }
    };

    // NEW SCHEMA: code_random_complex und code_reading verwenden beide Placeholder
    // Replace placeholders {varName} in solution_code with actual values
    console.log('[SOLUTION] Replacing placeholders in solution_code');
    for (const [key, value] of Object.entries(varValues)) {
      const placeholder = `{${key}}`;
      const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
      solutionCode = solutionCode.replace(regex, toPythonLiteral(value));
    }
    
    // Redirect print output
    let capturedOutput = '';
    const originalPrint = window.pyodide.globals.get('print');
    window.pyodide.globals.set('print', function(...args) {
      capturedOutput += args.map(a => String(a)).join(' ') + '\n';
    });
    
    // Execute the solution code
    await window.pyodide.runPythonAsync(solutionCode, { globals: namespace });
    
    // Restore original print
    window.pyodide.globals.set('print', originalPrint);
    
    // Try to get result variable first (using task's correct_answer field)
    let result;
    const resultVarName = task.correct_answer || 'result';
    if (namespace.has(resultVarName)) {
      result = namespace.get(resultVarName);
    } else if (capturedOutput) {
      // Otherwise, use captured output from print statements
      result = capturedOutput.trim();
    } else {
      throw new Error(`Keine "${resultVarName}"-Variable oder print()-Ausgabe gefunden`);
    }
    
    return result;
  } catch (err) {
    throw new Error(`Python-Fehler: ${err.message || err}`);
  }
}

// Generate random values for code_random_complex tasks (called when solution is shown before quiz execution)
async function generateRandomComplexValues(task) {
  if (!window.pyodide) {
    let attempts = 0;
    while (!window.pyodide && attempts < 100) {
      await new Promise(resolve => setTimeout(resolve, 100));
      attempts++;
    }
    if (!window.pyodide) {
      throw new Error('Pyodide konnte nicht geladen werden');
    }
  }
  
  // CASE 1: Use variable_overrides if available
  if (task.task_type === 'code_random_complex' && task.variable_overrides) {
    throw new Error('code_random_complex erlaubt keine festen Wertepaare (variable_overrides)');
  }

  if (task.variable_overrides) {
    const overrides = typeof task.variable_overrides === 'string' 
      ? JSON.parse(task.variable_overrides) 
      : task.variable_overrides;

    let values = {};

    if (Array.isArray(overrides) && overrides.length > 0) {
      const idx = Math.floor(Math.random() * overrides.length);
      const selectedSet = overrides[idx];
      if (selectedSet && typeof selectedSet === 'object' && !Array.isArray(selectedSet)) {
        values = selectedSet;
      }
    } else if (overrides && typeof overrides === 'object') {
      for (const varName in overrides) {
        const possibleValues = overrides[varName];
        if (Array.isArray(possibleValues) && possibleValues.length > 0) {
          values[varName] = possibleValues[Math.floor(Math.random() * possibleValues.length)];
        } else if (possibleValues !== undefined) {
          values[varName] = possibleValues;
        }
      }
    }
    
    if (Object.keys(values).length > 0) {
      if (!window.assignmentState.taskUserAnswers[task.id]) {
        window.assignmentState.taskUserAnswers[task.id] = {};
      }
      window.assignmentState.taskUserAnswers[task.id].variable_values = values;
      return values;
    }
  }
  
  // CASE 2: Execute randomizer_code to generate values (NEW SCHEMA)
  const code = (task.randomizer_code || '').trim();
  if (!code) {
    throw new Error('Kein randomizer_code hinterlegt');
  }
  
  // NEW SCHEMA: randomizer_code creates variables directly (no 'values' dict)
  // Example: binary = format(random.randint(0, 255), '08b')
  const python = `
__randomizer_namespace = {}
exec("""${code.replace(/"/g, '\\"').replace(/\n/g, '\\n')}""", __randomizer_namespace)
__randomizer_namespace
`;
  const resultObj = await window.pyodide.runPythonAsync(python);
  const allVariables = resultObj.toJs();

  // Extract all variables from namespace (except builtins)
  const values = {};
  Object.entries(allVariables).forEach(([rawKey, val]) => {
    const key = String(rawKey ?? '');
    if (!key || key.startsWith('__') || key === 'random') {
      return;
    }
    values[key] = val;
  });
  
  if (!values || typeof values !== 'object' || Array.isArray(values)) {
    throw new Error('Generator muss ein dict liefern');
  }
  
  if (!window.assignmentState.taskUserAnswers[task.id]) {
    window.assignmentState.taskUserAnswers[task.id] = {};
  }
  window.assignmentState.taskUserAnswers[task.id].variable_values = values;
  
  return values;
}

// Compute solution for code_reading tasks
async function computeCodeReadingSolution(task, varValues, variableName) {
  // Wait for Pyodide to be ready (max 10 seconds)
  if (!window.pyodide) {
    let attempts = 0;
    while (!window.pyodide && attempts < 100) {
      await new Promise(resolve => setTimeout(resolve, 100));
      attempts++;
    }
    if (!window.pyodide) {
      throw new Error('Pyodide konnte nicht geladen werden (Timeout)');
    }
  }
  
  let code = task.code_template || '';
  
  // Replace template placeholders with actual values
  for (const varName in varValues) {
    const placeholder = `{${varName}}`;
    const value = varValues[varName];
    const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
    code = code.replace(regex, String(value));
  }
  
  try {
    // Execute code and get the requested variable value
    await window.pyodide.runPythonAsync(code);
    const result = await window.pyodide.runPythonAsync(variableName);
    return result;
  } catch (err) {
    throw new Error(`Python-Fehler: ${err.message || err}`);
  }
}

function getStatusLabel(status) {
  const labels = {
    'unbearbeitet': 'Unbearbeitet',
    'in-progress': 'In Bearbeitung',
    'passed': 'Bestanden ✓',
    'failed': 'Misslungen'
  };
  return labels[status] || status;
}

function getStatusEmoji(status) {
  const emojis = {
    'unbearbeitet': '⚪',
    'in-progress': '🟡',
    'passed': '🟢',
    'failed': '🔴'
  };
  return emojis[status] || '⚪';
}

// Export showTaskDetails for access from quiz-renderer
window.showTaskDetails = showTaskDetails;

// Show task question/description above the editor
function showTaskQuestionAboveEditor(task) {
  const editorContainer = document.getElementById('editor-container');
  if (!editorContainer) return;
  
  const taskContent = task.task_text;
  
  if (!taskContent) return; // No question to show
  
  // Remove existing question if it exists
  const existingQuestion = editorContainer.querySelector('.quiz-question');
  if (existingQuestion) {
    existingQuestion.remove();
  }
  
  // Create question element using same classes as quiz tasks
  const questionEl = document.createElement('div');
  questionEl.className = 'quiz-question';
  questionEl.innerHTML = `
    <div class="question-text">
      ${escapeHtml(taskContent)}
    </div>
  `;
  
  // Insert at the beginning of editor container
  editorContainer.insertBefore(questionEl, editorContainer.firstChild);
}

// Render task navigation in left panel (compact task list)
function renderTaskNavigation() {
  const navEl = $('task-navigation');
  if (!navEl) return;

  // Get tasks for current assignment
  const tasks = assignmentState.currentAssignmentId 
    ? assignmentState.tasksByAssignment[assignmentState.currentAssignmentId] || []
    : [];

  if (tasks.length === 0) {
    navEl.innerHTML = '<p style="padding:8px; margin:0; color:var(--text-secondary); font-size:12px;">Keine Aufgaben geladen</p>';
    return;
  }

  // Task type icon mapping
  const taskTypeIcons = {
    'single_choice': '<i class="fas fa-circle-dot"></i>',
    'multiple_choice': '<i class="fas fa-square-check"></i>',
    'free_text': '<i class="fas fa-file-alt"></i>',
    'code': '<i class="fas fa-code"></i>',
    'code_ui': '<i class="fas fa-code"></i>',
    'code_reading': '<i class="fas fa-eye"></i>',
    'code_random_complex': '<i class="fas fa-random"></i>'
  };

  navEl.innerHTML = tasks.map((task, idx) => {
    const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';
    const isActive = task.id === assignmentState.currentTaskId;
    const taskIcon = taskTypeIcons[task.task_type] || '<i class="fas fa-question-circle"></i>';
    
    return `
      <div class="task-nav-item ${isActive ? 'active' : ''}" data-task-id="${task.id}">
        <span class="task-nav-position">${idx + 1}. (#${task.id})</span>
        <span class="task-nav-status status-${status}"></span>
        <span class="task-nav-title">${escapeHtml(task.title)}</span>
        <span class="task-nav-icon">${taskIcon}</span>
      </div>
    `;
  }).join('');

  // Add click handlers for task navigation
  navEl.querySelectorAll('.task-nav-item').forEach(item => {
    item.addEventListener('click', () => {
      const taskId = parseInt(item.dataset.taskId);
      if (assignmentState.currentAssignmentId) {
        loadTaskIntoEditor(assignmentState.currentAssignmentId, taskId);
      }
    });
  });
}

// Optimized: Load only the specific assignment (for editor mode)
async function loadSingleAssignment(assignmentId) {
  try {
    const cachebust = `&t=${Date.now()}`;
    const testModeParam = window.testMode ? '&test_mode=1' : '';
    const [assignmentRes, tasksRes] = await Promise.all([
      requestJson(`../api/assignments/get.php?id=${assignmentId}${cachebust}`),
      requestJson(`../api/tasks/list.php?assignment_id=${assignmentId}${testModeParam}${cachebust}`)
    ]);
    
    assignmentState.assignmentDetails[assignmentId] = assignmentRes.assignment;
    assignmentState.tasksByAssignment[assignmentId] = tasksRes.tasks || [];

    if (window.testMode === true) {
      assignmentState.tasksByAssignment[assignmentId].forEach((task) => {
        if (assignmentState.taskStatuses[task.id] === undefined) {
          assignmentState.taskStatuses[task.id] = 'unbearbeitet';
        }
        if (assignmentState.taskAttempts[task.id] === undefined) {
          assignmentState.taskAttempts[task.id] = 0;
        }
        if (assignmentState.taskIterations[task.id] === undefined) {
          assignmentState.taskIterations[task.id] = 1;
        }

        if (window.TestMode && typeof window.TestMode.initializeTask === 'function') {
          const maxIterations = task.max_iterations || null;
          window.TestMode.initializeTask(task.id, maxIterations);
        }
      });
    }
    
    // Load user_tasks progress for this assignment (also in test mode with test_user_id)
    try {
      const testUserParam = getTestUserQueryParam();
      const userTasksRes = await requestJson(`../api/user_tasks/get.php?assignment_id=${assignmentId}${testUserParam}`);
      const userTasks = userTasksRes.tasks || [];
      
      // Populate status and attempts from user_tasks
      userTasks.forEach(ut => {
        assignmentState.taskStatuses[ut.task_id] = ut.status;
        assignmentState.taskAttempts[ut.task_id] = ut.attempts;
        if (ut.current_iteration !== undefined && ut.current_iteration !== null) {
          assignmentState.taskIterations[ut.task_id] = parseInt(ut.current_iteration, 10) || 1;
        }
        // Store user answers
        assignmentState.taskUserAnswers[ut.task_id] = {
          selected_options: (ut.selected_options && ut.selected_options !== 'null') ? JSON.parse(ut.selected_options) : [],
          text_answer: ut.text_answer || '',
          variable_values: (ut.variable_values && ut.variable_values !== 'null') ? JSON.parse(ut.variable_values) : {}
        };
        if (ut.run_count !== undefined && ut.run_count !== null) {
          assignmentState.taskRuns[ut.task_id] = ut.run_count;
        }
        if (ut.completed_at) {
          assignmentState.taskCompletedAt[ut.task_id] = ut.completed_at;
        }
        if (ut.hints_revealed && Array.isArray(ut.hints_revealed)) {
          assignmentState.hintsRevealed[ut.task_id] = ut.hints_revealed;
        }
      });
    } catch (err) {
      console.warn(`Failed to load user_tasks for assignment ${assignmentId}:`, err);
    }
    
    return true;
  } catch (err) {
    console.error(`Failed to load assignment ${assignmentId}:`, err);
    return false;
  }
}

async function loadAssignments() {
  const containerEl = $('assignment-list-container');
  if (!containerEl) return;
  
  containerEl.innerHTML = '<p style="padding:20px; color:var(--text-secondary);">Lade Assignments...</p>';

  try {
    const data = await requestJson('../api/user_assignments/list.php');
    assignmentState.assignments = data.items || [];
    
    // Load all tasks for all assignments
    for (const item of assignmentState.assignments) {
      try {
        const [assignmentRes, tasksRes] = await Promise.all([
          requestJson(`../api/assignments/get.php?id=${item.assignment_id}`),
          requestJson(`../api/tasks/list.php?assignment_id=${item.assignment_id}`)
        ]);
        
        assignmentState.assignmentDetails[item.assignment_id] = assignmentRes.assignment;
        assignmentState.tasksByAssignment[item.assignment_id] = tasksRes.tasks || [];
        
        // Load user_tasks progress for this assignment (also in test mode with test_user_id)
        try {
          const testUserParam = getTestUserQueryParam();
          const userTasksRes = await requestJson(`../api/user_tasks/get.php?assignment_id=${item.assignment_id}${testUserParam}`);
          const userTasks = userTasksRes.tasks || [];
          
          // Populate status and attempts from user_tasks
          userTasks.forEach(ut => {
            assignmentState.taskStatuses[ut.task_id] = ut.status;
            assignmentState.taskAttempts[ut.task_id] = ut.attempts;
            if (ut.current_iteration !== undefined && ut.current_iteration !== null) {
              assignmentState.taskIterations[ut.task_id] = parseInt(ut.current_iteration, 10) || 1;
            }
            // Store user answers
            assignmentState.taskUserAnswers[ut.task_id] = {
              selected_options: (ut.selected_options && ut.selected_options !== 'null') ? JSON.parse(ut.selected_options) : [],
              text_answer: ut.text_answer || '',
              variable_values: (ut.variable_values && ut.variable_values !== 'null') ? JSON.parse(ut.variable_values) : {}
            };
            if (ut.run_count !== undefined && ut.run_count !== null) {
              assignmentState.taskRuns[ut.task_id] = ut.run_count;
            }
            if (ut.completed_at) {
              assignmentState.taskCompletedAt[ut.task_id] = ut.completed_at;
            }
            if (ut.hints_revealed && Array.isArray(ut.hints_revealed)) {
              assignmentState.hintsRevealed[ut.task_id] = ut.hints_revealed;
            }
          });
        } catch (err) {
          console.warn(`Failed to load user_tasks for assignment ${item.assignment_id}:`, err);
        }
      } catch (err) {
        console.error(`Failed to load tasks for assignment ${item.assignment_id}:`, err);
      }
    }
    
    renderAssignmentList();
  } catch (err) {
    containerEl.innerHTML = `<div style="color:#b91c1c; padding:20px;">Failed to load assignments</div>`;
  }
}

// Render assignment cards in list view
function renderAssignmentList() {
  const containerEl = $('assignment-list-container');
  if (!containerEl) return;

  if (!assignmentState.assignments.length) {
    containerEl.innerHTML = '<div style="color:var(--text-secondary); padding:20px; text-align:center;">Keine Assignments verfügbar.</div>';
    return;
  }

  containerEl.innerHTML = assignmentState.assignments.map((item) => {
    const assignment = assignmentState.assignmentDetails[item.assignment_id];
    const tasks = assignmentState.tasksByAssignment[item.assignment_id] || [];
    const completedCount = tasks.filter(t => {
      const status = assignmentState.taskStatuses[t.id];
      return status === 'passed';
    }).length;
    
    return `
      <div class="assignment-card" data-assignment-id="${item.assignment_id}">
        <div class="assignment-card-title">${escapeHtml(item.assignment_title)}</div>
        <div class="assignment-card-description">${escapeHtml(assignment?.description || 'Keine Beschreibung')}</div>
        <div class="assignment-card-meta">
          <div class="assignment-card-stat">
            <span>📝</span>
            <span>${tasks.length} ${tasks.length === 1 ? 'Aufgabe' : 'Aufgaben'}</span>
          </div>
          <div class="assignment-card-stat">
            <span>✅</span>
            <span>${completedCount}/${tasks.length} erledigt</span>
          </div>
        </div>
      </div>
    `;
  }).join('');

  // Add click handlers
  containerEl.querySelectorAll('.assignment-card').forEach(card => {
    card.addEventListener('click', () => {
      const assignmentId = parseInt(card.dataset.assignmentId);
      openAssignmentEditor(assignmentId);
    });
  });
}

// Open assignment editor view (hide list, show editor with tasks)
function openAssignmentEditor(assignmentId) {
  // If not in editor mode, redirect to assignment_editor.php
  if (!window.EDITOR_MODE) {
    window.location.href = `assignment_editor.php?assignment_id=${assignmentId}`;
    return;
  }
  
  // Editor mode: show editor inline
  const listView = $('assignment-list-view');
  const editorView = $('editor-view');
  const dashboardBtn = $('dashboard-btn');
  const backToListBtn = $('back-to-list-btn');
  const runBtn = $('run-btn');
  
  // Hide list, show editor
  if (listView) listView.style.display = 'none';
  if (editorView) editorView.style.display = 'grid';
  
  // Switch buttons (but keep refresh button visible in test mode)
  const isTestMode = window.TEST_MODE_NO_PERSIST === true || window.testMode === true;
  if (!isTestMode) {
    if (dashboardBtn) dashboardBtn.style.display = 'none';
    if (backToListBtn) backToListBtn.style.display = 'inline-block';
  }
  if (runBtn) runBtn.style.display = 'inline-block';
  
  // Set current assignment
  assignmentState.currentAssignmentId = assignmentId;
  
  // Update page title with assignment name
  const assignmentDetails = assignmentState.assignmentDetails[assignmentId];
  const titleEl = document.getElementById('assignment-page-title');
  if (titleEl) {
    if (assignmentDetails && assignmentDetails.title) {
      titleEl.textContent = assignmentDetails.title;
    } else {
      titleEl.textContent = 'Assignments';
    }
  }
  
  // Get tasks for this assignment
  const tasks = assignmentState.tasksByAssignment[assignmentId] || [];
  
  if (tasks.length === 0) {
    console.warn('No tasks found for assignment', assignmentId);
    return;
  }
  
  // Load specified task (if TASK_ID is set) or first task
  let taskToLoad = tasks[0];
  if (window.TASK_ID) {
    const specifiedTask = tasks.find(t => t.id === window.TASK_ID);
    if (specifiedTask) {
      taskToLoad = specifiedTask;
    }
  }
  loadTaskIntoEditor(assignmentId, taskToLoad.id);
}

// Go back to assignment list
function backToAssignmentList() {
  // If in editor mode, redirect to assignments.php
  if (window.EDITOR_MODE) {
    window.location.href = 'assignments.php';
    return;
  }
  
  const listView = $('assignment-list-view');
  const editorView = $('editor-view');
  const dashboardBtn = $('dashboard-btn');
  const backToListBtn = $('back-to-list-btn');
  const runBtn = $('run-btn');
  const checkBtn = $('check-btn');
  const attemptsCounter = $('attempts-counter');
  
  // Show list, hide editor
  if (listView) listView.style.display = 'block';
  if (editorView) editorView.style.display = 'none';
  
  // Switch buttons
  if (dashboardBtn) dashboardBtn.style.display = 'inline-block';
  if (backToListBtn) backToListBtn.style.display = 'none';
  if (runBtn) runBtn.style.display = 'none';
  if (checkBtn) checkBtn.style.display = 'none';
  if (attemptsCounter) attemptsCounter.style.display = 'none';
  
  // Clear current assignment/task
  assignmentState.currentAssignmentId = null;
  assignmentState.currentTaskId = null;
}

async function loadAssignmentDetails(assignmentId) {
  const detailEl = $('assignment-detail');
  if (!detailEl) return;

  detailEl.style.display = 'block';
  detailEl.innerHTML = 'Loading tasks...';

  try {
    const [assignmentRes, tasksRes] = await Promise.all([
      requestJson(`../api/assignments/get.php?id=${assignmentId}`),
      requestJson(`../api/tasks/list.php?assignment_id=${assignmentId}`)
    ]);

    const assignment = assignmentRes.assignment;
    const tasks = tasksRes.tasks || [];
    assignmentState.assignmentDetails[assignmentId] = assignment;
    assignmentState.tasksByAssignment[assignmentId] = tasks;
    assignmentState.currentAssignmentId = assignmentId;

    renderAssignmentDetail(assignmentId, assignment, tasks);
  } catch (err) {
    detailEl.innerHTML = `<div style="color:#b91c1c;">Failed to load tasks</div>`;
  }
}

function renderAssignmentDetail(assignmentId, assignment, tasks) {
  const detailEl = $('assignment-detail');
  if (!detailEl) return;

  detailEl.innerHTML = `
    <div class="assignment-title">${escapeHtml(assignment.title)}</div>
    <div class="assignment-meta" style="margin-bottom:8px;">
      <span>${escapeHtml(assignment.difficulty || 'beginner')}</span>
      <span>${escapeHtml(assignment.user_status || 'assigned')}</span>
    </div>
    ${assignment.description ? `<div style="font-size:13px; color:var(--text-secondary); margin-bottom:10px;">${escapeHtml(assignment.description)}</div>` : ''}
    <div style="font-weight:600; margin-bottom:6px;">Aufgaben</div>
    ${tasks.length ? tasks.map((task) => {
      const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';
      return `
        <div class="task-item">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
            <span class="status-light status-${status}"></span>
            <div class="task-title" style="flex:1;">${escapeHtml(task.position)}. ${escapeHtml(task.title)}</div>
          </div>
          ${task.description ? `<div style="font-size:12px; color:var(--text-secondary); margin-left:24px;">${escapeHtml(task.description.substring(0, 80)) || ''}${task.description.length > 80 ? '...' : ''}</div>` : ''}
          <div class="task-actions">
            <button class="btn" data-action="load-task" data-task-id="${task.id}" data-assignment-id="${assignmentId}">Im Editor öffnen</button>
          </div>
        </div>
      `;
    }).join('') : '<div style="color:var(--text-secondary);">Keine Aufgaben vorhanden.</div>'}
  `;  
}

async function loadSavedCode(taskId) {
  try {
    const testUserParam = getTestUserQueryParam();
    const response = await requestJson(`../api/user_tasks/get.php?task_id=${taskId}${testUserParam}`);
    if (response && response.task && response.task.current_code) {
      return response.task.current_code;
    }
    return null;
  } catch (err) {
    console.warn('Failed to load saved code:', err);
    return null;
  }
}

function normalizeTaskData(task) {
  /**
   * Normalize task data by converting escaped newlines to actual newlines
   * This handles data from database that was stored with \\n escape sequences
   */
  if (!task) return task;
  
  // Convert escaped newlines in code fields
  if (task.code_template && typeof task.code_template === 'string') {
    task.code_template = task.code_template.replace(/\\n/g, '\n');
  }
  
  if (task.solution_code && typeof task.solution_code === 'string') {
    task.solution_code = task.solution_code.replace(/\\n/g, '\n');
  }
  
  if (task.hint1 && typeof task.hint1 === 'string') {
    task.hint1 = task.hint1.replace(/\\n/g, '\n');
  }
  
  if (task.hint2 && typeof task.hint2 === 'string') {
    task.hint2 = task.hint2.replace(/\\n/g, '\n');
  }
  
  if (task.hint3 && typeof task.hint3 === 'string') {
    task.hint3 = task.hint3.replace(/\\n/g, '\n');
  }
  
  return task;
}

/**
 * Refresh current task data from API (ensures admin edits are reflected immediately)
 * Called before running tests to get latest solution_code, test_cases, etc.
 */
async function refreshCurrentTaskFromAPI() {
  if (!assignmentState.currentTaskId || !assignmentState.currentAssignmentId) {
    console.log('[Task Refresh] No current task ID or assignment ID');
    return false;
  }

  try {
    console.log('[Task Refresh] Fetching latest task data for task', assignmentState.currentTaskId);
    const response = await requestJson('/api/tasks/list.php?assignment_id=' + assignmentState.currentAssignmentId);
    
    if (response && response.tasks && response.tasks.length > 0) {
      const updatedTask = response.tasks[0];
      normalizeTaskData(updatedTask);
      
      // Update current task in memory
      assignmentState.currentTask = updatedTask;
      
      // Also update in tasksByAssignment if available
      if (assignmentState.currentAssignmentId) {
        const tasksInAssignment = assignmentState.tasksByAssignment[assignmentState.currentAssignmentId] || [];
        const taskIndex = tasksInAssignment.findIndex(t => t.id === assignmentState.currentTaskId);
        if (taskIndex >= 0) {
          tasksInAssignment[taskIndex] = updatedTask;
        }
      }
      
      console.log('[Task Refresh] Task refreshed successfully:', {
        taskId: updatedTask.id,
        hasSolution: !!updatedTask.solution_code,
        hasTestCases: !!updatedTask.test_cases
      });
      return true;
    }
  } catch (err) {
    console.warn('[Task Refresh] Failed to refresh task from API:', err.message);
    return false;
  }
  
  return false;
}

// Helper function to wait for editor initialization
function waitForEditor(maxAttempts = 20, interval = 100) {
  return new Promise((resolve, reject) => {
    let attempts = 0;
    
    const checkEditor = () => {
      if (window.editorInstance) {
        resolve(window.editorInstance);
        return;
      }
      
      attempts++;
      if (attempts >= maxAttempts) {
        reject(new Error('Editor initialization timeout'));
        return;
      }
      
      setTimeout(checkEditor, interval);
    };
    
    checkEditor();
  });
}

function triggerCodeUiPythonRun() {
  const runButton = document.getElementById('run-btn');
  if (!runButton) return;
  runButton.click();
}

async function triggerCodeUiFunctionCall(triggerElement) {
  /* Event-Driven Mode: Call single function without full code restart.
     Global variables persist between trigger calls. */
  if (!window.pyodide) {
    console.error('[CODE-UI] Pyodide not ready');
    return;
  }

  const functionName = triggerElement?.getAttribute?.('data-function') || triggerElement?.getAttribute?.('data-run-name') || '';
  if (!functionName) {
    console.warn('[CODE-UI] No function trigger attribute (data-function / data-run-name)');
    return;
  }

  const functionValue = triggerElement?.getAttribute?.('value') ?? triggerElement?.value ?? '';

  const outputEl = document.getElementById('output-container');
  const lintEl = document.getElementById('lint-container');
  if (!outputEl || !lintEl) return;

  // First click after load: no preserved globals yet.
  // Fallback to full RUN so functions are defined, then auto-dispatch via trigger context.
  if (!window.__codeUiGlobals) {
    window.__codeUiEventDrivenMode = false;
    triggerCodeUiPythonRun();
    return;
  }

  try {
    outputEl.innerText = '';

    await window.pyodide.runPythonAsync(
      `
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
    
    # Get preserved globals from RUN button
    g = getattr(js_window, '__codeUiGlobals', globals())
    
    # Update ui.trigger
    if hasattr(ui, '_refresh_trigger'):
        ui.trigger._name = "${functionName}"
        ui.trigger._value = "${functionValue}"
    
    # Call function from preserved globals
    func = g.get("${functionName}")
    if callable(func):
        try:
            func(ui.trigger)
        except TypeError:
            func()
    else:
        print(f"Fehler: Funktion '${functionName}' nicht definiert")
    
    # Preserve globals for next call
    if hasattr(js_window, '__codeUiGlobals'):
        js_window.__codeUiGlobals = g
finally:
    sys.stdout = old_out
`
    );
    lintEl.innerHTML = '<span class="lint-ok">✓</span>';
  } catch (e) {
    const errMsg = String(e?.message || e || '').split('\\n')[0];
    outputEl.innerText = 'Fehler: ' + errMsg;
  }
}

function setCodeUiTriggerContext(guiContainer, triggerElement, isEventDriven = false) {
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
    value: String(triggerValue),
  };

  // Signal for event-driven mode: no full code restart, only function call
  window.__codeUiEventDrivenMode = isEventDriven;
}

function ensureCodeUiRunTriggers(guiContainer) {
  if (!guiContainer || guiContainer.dataset.codeUiRunBound === '1') return;

  // === RUN MODE: data-run="true" (legacy: data-run-python="true") ===
  // Full code restart, dispatch handled in Python
  guiContainer.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[data-run="true"], [data-run], [data-run-python="true"]');
    if (!trigger || !guiContainer.contains(trigger)) return;
    event.preventDefault();
    setCodeUiTriggerContext(guiContainer, trigger, false); // Traditional mode
    triggerCodeUiPythonRun();
  });

  guiContainer.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const isRunForm = form.getAttribute('data-run') === 'true' || form.hasAttribute('data-run') || form.getAttribute('data-run-python') === 'true';
    if (!isRunForm) return;
    event.preventDefault();
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : form;
    setCodeUiTriggerContext(guiContainer, submitter, false); // Traditional mode
    triggerCodeUiPythonRun();
  });

  // === EVENT-DRIVEN MODE: data-function="functionName" ===
  // Direct function call, global scope preserved, no full code restart
  guiContainer.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[data-function]');
    if (!trigger || !guiContainer.contains(trigger)) return;
    if (trigger.hasAttribute('data-run-python') || trigger.hasAttribute('data-run')) return;
    event.preventDefault();
    setCodeUiTriggerContext(guiContainer, trigger, true); // Event-driven mode
    triggerCodeUiFunctionCall(trigger);
  });

  guiContainer.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute('data-function')) return;
    if (form.getAttribute('data-run-python') === 'true' || form.getAttribute('data-run') === 'true' || form.hasAttribute('data-run')) return;
    event.preventDefault();
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : form;
    const functionTarget = submitter.hasAttribute('data-function') ? submitter : form;
    setCodeUiTriggerContext(guiContainer, functionTarget, true); // Event-driven mode
    triggerCodeUiFunctionCall(functionTarget);
  });

  guiContainer.dataset.codeUiRunBound = '1';
}

async function renderCodeUiHtml(taskId) {
  const guiContainer = document.getElementById('gui-container');
  if (!guiContainer || !taskId) return;

  const requestedTaskId = Number(taskId);
  if (assignmentState.currentTaskId !== null && Number(assignmentState.currentTaskId) !== requestedTaskId) return;

  const isAdminFolderMode = window.testMode === true;
  const testUserParam = window.TEST_USER_ID ? `&test_user_id=${window.TEST_USER_ID}` : '';

  const readTaskFile = async (path) => {
    const draft = getTaskDraftContent(taskId, path);
    if (draft !== null) {
      return String(draft || '');
    }

    const readEndpoint = isAdminFolderMode
      ? `/pythonIDE/api/tasks/folder-manage.php?action=read&task_id=${taskId}&path=${encodeURIComponent(path)}`
      : `/pythonIDE/api/user_tasks/folder-files.php?action=read&task_id=${taskId}&path=${encodeURIComponent(path)}${testUserParam}`;

    const response = await fetch(readEndpoint, { credentials: 'include', cache: 'no-store' });
    if (!response.ok) {
      throw new Error(`${path} nicht gefunden`);
    }

    const result = await response.json();
    const content = String(result?.content || '');
    setTaskSavedSnapshot(taskId, path, content);
    return content;
  };

  try {
    const htmlContent = await readTaskFile('index.html');
    const cssContent = await readTaskFile('style.css').catch(() => '');

    // Preserve existing input values before any clear/re-render operation
    const preservedValues = {};
    const existingInputs = guiContainer.querySelectorAll('[data-element]');
    existingInputs.forEach(input => {
      const name = input.getAttribute('data-element');
      if (name && input.value !== undefined) {
        preservedValues[name] = input.value;
      }
    });

    if (assignmentState.currentTaskId !== null && Number(assignmentState.currentTaskId) !== requestedTaskId) return;

    if (assignmentState.currentTaskId !== null && Number(assignmentState.currentTaskId) !== requestedTaskId) return;

    if (window.guiBridge) {
      window.guiBridge.clearGUI();
      window.guiBridge.showGUI();
    } else {
      guiContainer.innerHTML = '';
      guiContainer.classList.add('active');
    }

    const parser = new DOMParser();
    const parsed = parser.parseFromString(htmlContent, 'text/html');
    const bodyHtml = parsed?.body?.innerHTML?.trim();
    const inlineStyleTags = parsed?.querySelectorAll?.('style') || [];
    const inlineCss = Array.from(inlineStyleTags).map((tag) => tag.textContent || '').join('\n');

    guiContainer.innerHTML = bodyHtml || '';
    guiContainer.dataset.codeUiTaskId = String(taskId);

    // Restore preserved input values
    Object.entries(preservedValues).forEach(([name, value]) => {
      const input = guiContainer.querySelector(`[data-element="${name}"]`);
      if (input && input.value !== undefined) {
        input.value = value;
      }
    });
    
    ensureCodeUiRunTriggers(guiContainer);

    const styleTag = document.createElement('style');
    styleTag.setAttribute('data-code-ui-style', 'true');
    const mergedCss = [cssContent, inlineCss].filter(Boolean).join('\n\n');
    const scopedCss = mergedCss
      .replace(/\bbody\b(?=\s*\{)/g, '#gui-container')
      .replace(/\bhtml\b(?=\s*\{)/g, '#gui-container');
    styleTag.textContent = scopedCss;
    if (styleTag.textContent.trim()) {
      guiContainer.prepend(styleTag);
    }

    if (!guiContainer.querySelector('#idegui-root')) {
      const root = document.createElement('div');
      root.id = 'idegui-root';
      root.setAttribute('data-idegui-root', 'true');
      guiContainer.appendChild(root);
    }
    if (!guiContainer.querySelector('#idegui-output')) {
      const output = document.createElement('div');
      output.id = 'idegui-output';
      output.setAttribute('data-idegui-output', 'true');
      guiContainer.appendChild(output);
    }
  } catch (error) {
    console.warn('Code UI index.html render skipped:', error?.message || error);
    if (window.guiBridge) {
      window.guiBridge.clearGUI();
      window.guiBridge.showGUI();
    }
  }
}

function setEditorToInitPy(taskId, content = '') {
  const editor = window.editorInstance;
  const monaco = window.monaco;

  if (!editor) return;

  if (monaco) {
    const oldModel = editor.getModel();
    if (oldModel) {
      oldModel.dispose();
    }

    const model = monaco.editor.createModel(
      content,
      'python',
      monaco.Uri.parse(`task://task${taskId}/init.py`)
    );
    editor.setModel(model);
  } else {
    editor.setValue(content);
  }

  editor.updateOptions({ readOnly: false });
  window.currentFile = { taskId, path: 'init.py', fileName: 'init.py', isVirtual: true };
  setTaskSavedSnapshot(taskId, 'init.py', content);
  setTaskDraftContent(taskId, 'init.py', content);

  const title = document.querySelector('.editor-title');
  if (title) {
    title.textContent = 'init.py (Hauptdatei)';
  }
}

async function loadTaskIntoEditor(assignmentId, taskId) {
  const loadToken = ++assignmentState.taskLoadToken;
  const isStaleLoad = () => assignmentState.taskLoadToken !== loadToken;

  const tasks = assignmentState.tasksByAssignment[assignmentId] || [];
  const task = tasks.find((t) => t.id === taskId);
  if (!task) return;

  const canSwitch = await confirmTaskSwitchWithDrafts(taskId);
  if (!canSwitch) {
    return;
  }

  // Normalize task data (convert escaped newlines)
  normalizeTaskData(task);

  // Clear solution mode when loading a task normally
  assignmentState.solutionMode = false;

  // Check if this is a quiz-style task
  const isQuizTask = task.task_type && !['code', 'code_ui'].includes(task.task_type);
  const isCodeUiTask = task.task_type === 'code_ui';
  
  // For code tasks, wait for editor to be ready
  if (!isQuizTask) {
    try {
      await waitForEditor();
      if (isStaleLoad()) return;
    } catch (err) {
      alert('Editor konnte nicht initialisiert werden. Bitte Seite neu laden.');
      console.error('Editor initialization failed:', err);
      return;
    }
  }

  const editor = window.editorInstance;

  // Stop activity tracking for previous task
  if (assignmentState.currentTaskId) {
    stopActivityTracking(assignmentState.currentTaskId);
  }

  // Store current task info globally for Check button
  assignmentState.currentTask = task;
  assignmentState.currentAssignmentId = assignmentId;
  assignmentState.currentTaskId = taskId;

  // GUI setup: Only for tasks (not projects)
  if (!window.currentProject && window.guiBridge) {
    if (isCodeUiTask) {
      // code_ui Tasks: Initialize GUI container with placeholder, HTML renders on first RUN
      const guiContainer = document.getElementById('gui-container');
      if (guiContainer) {
        guiContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-secondary); font-size: 14px;">▶️ Klicke auf RUN, um die HTML-Oberfläche zu laden</div>';
        guiContainer.classList.add('active');
      }
    } else {
      // Normal code tasks: Hide GUI container completely
      window.guiBridge.hideGUI();
      window.guiBridge.clearGUI();
    }
  }
  
  if (isStaleLoad()) return;

  // Start activity tracking for new task (only if not finalized)
  const currentStatus = assignmentState.taskStatuses[task.id];
  const isFinalized = currentStatus === 'passed' || currentStatus === 'failed';
  if (!isFinalized) {
    startActivityTracking(taskId);
  } else {
    logActivityDebug('task finalized, tracking skipped', { taskId, currentStatus });
  }

  // Track task start time for time calculation
  if (!assignmentState.taskStartTimes[taskId]) {
    assignmentState.taskStartTimes[taskId] = Date.now();
  }

  // Find the user_assignment_id from assignments list
  const userAssignment = assignmentState.assignments.find(ua => ua.assignment_id === assignmentId);
  if (userAssignment) {
    assignmentState.currentUserAssignmentId = userAssignment.id;
  } else {
    // If no assignment entry exists, we'll need to create one
    // For now, we'll create a temporary entry with the assignment_id
    assignmentState.currentUserAssignmentId = null;
    console.warn(`No user_assignment found for assignment ${assignmentId}. Will be created on first save.`);
  }

  // Handle Quiz Tasks
  if (isQuizTask) {
    // Hide code editor, show quiz container
    const editorContainer = document.getElementById('editor-container');
    const quizContainer = document.getElementById('quiz-container');
    const leftBottom = document.getElementById('left-bottom');
    const leftSection = document.querySelector('.left');
    
    if (editorContainer) editorContainer.style.display = 'none';
    if (leftBottom) leftBottom.style.display = 'none';
    if (leftSection) leftSection.classList.add('quiz-mode');
    
    if (quizContainer) {
      quizContainer.style.display = 'block';
      
      // Render quiz UI
      if (window.QuizRenderer) {
        window.QuizRenderer.render(task, quizContainer);
      } else {
        quizContainer.innerHTML = '<p>Quiz renderer not loaded</p>';
      }
    }
    
    // Hide code-specific buttons
    const runBtn = document.getElementById('run-btn');
    const checkBtn = document.getElementById('check-btn');
    const submitBtn = document.getElementById('submit-btn');
    
    if (runBtn) runBtn.style.display = 'none';
    if (checkBtn) checkBtn.style.display = 'none';
    if (submitBtn) submitBtn.style.display = 'none';
    
  } else {
    // Handle Code Tasks - show editor
    const editorContainer = document.getElementById('editor-container');
    const quizContainer = document.getElementById('quiz-container');
    const leftBottom = document.getElementById('left-bottom');
    const leftSection = document.querySelector('.left');
    const runBtn = document.getElementById('run-btn');
    
    if (editorContainer) editorContainer.style.display = 'block';
    if (quizContainer) quizContainer.style.display = 'none';
    if (leftBottom) leftBottom.style.display = 'grid';
    if (leftSection) leftSection.classList.remove('quiz-mode');
    if (runBtn) runBtn.style.display = 'inline-block';
    
    // Restore saved code if returning from solution mode, otherwise load from DB
    if (assignmentState.savedCodeBeforeSolution !== null) {
      setEditorToInitPy(taskId, assignmentState.savedCodeBeforeSolution);
      assignmentState.savedCodeBeforeSolution = null;
    } else {
      // Load saved code from user_tasks if available
      try {
        const savedCode = await loadSavedCode(taskId);
        if (isStaleLoad()) return;
        const code = savedCode || task.code_template || '# Start here';
        setEditorToInitPy(taskId, code);
      } catch (err) {
        console.warn('Failed to load saved code, using template:', err);
        if (isStaleLoad()) return;
        const code = task.code_template || '# Start here';
        setEditorToInitPy(taskId, code);
      }
    }
  }

  // Show task details
  showTaskDetails(task);
  
  // Show task question above editor for code tasks
  if (!isQuizTask) {
    showTaskQuestionAboveEditor(task);
  }

  // Render task navigation
  renderTaskNavigation();

  // Show/update attempts counter if task has test_cases
  updateAttemptsCounter(task);

  // NOTE: Auto-save removed - only save when user clicks Save button or switches tasks

  // Hide file tree initially, show only if needed
  const fileTreeWrapper = document.getElementById('file-tree-wrapper');
  if (fileTreeWrapper) {
    fileTreeWrapper.classList.remove('active');
  }

  const outputEl = document.getElementById('output-container');
  if (outputEl) {
    outputEl.textContent = `Task geladen: ${task.title}`;
  }

  // Show check button if test cases exist (code tasks only)
  if (!isQuizTask) {
    const checkBtn = document.getElementById('check-btn');
    const submitBtn = document.getElementById('submit-btn');
    if (checkBtn) {
      // Show button if test cases exist
      if (task.test_cases) {
        checkBtn.style.display = 'inline-block';
        if (submitBtn) submitBtn.style.display = 'inline-block';
        checkBtn.disabled = false;
        checkBtn.style.opacity = '1';
        checkBtn.style.cursor = 'pointer';
      } else {
        checkBtn.style.display = 'none';
        if (submitBtn) submitBtn.style.display = 'none';
      }
    }
  }

  // Lock editor and hide check/submit if task already finalized (passed or failed)
  // (currentStatus and isFinalized already computed above)
  
  // Get elements
  const saveTaskBtn = document.getElementById('save-task-btn');
  const downloadBtn = document.getElementById('download-btn');
  const undoBtn = document.getElementById('undo-btn');
  const redoBtn = document.getElementById('redo-btn');
  const attemptsCounter = document.getElementById('attempts-counter');
  const submittedInfo = document.getElementById('submitted-info');
  const submittedStatus = document.getElementById('submitted-status');
  const submittedDate = document.getElementById('submitted-date');
  
  if (isFinalized) {
    // Task finalized - hide buttons (but keep download), lock editor, show submitted info
    const checkBtn = document.getElementById('check-btn');
    const submitBtn = document.getElementById('submit-btn');
    if (checkBtn) checkBtn.style.display = 'none';
    if (submitBtn) submitBtn.style.display = 'none';
    if (saveTaskBtn) saveTaskBtn.style.display = 'none';
    if (undoBtn) undoBtn.style.display = 'none';
    if (redoBtn) redoBtn.style.display = 'none';
    if (attemptsCounter) attemptsCounter.style.display = 'none';
    // Keep download button visible
    
    // Show submitted info with status and date
    if (submittedInfo && submittedStatus && submittedDate) {
      submittedInfo.classList.add('show');
      submittedStatus.className = 'status-' + currentStatus;
      
      // Format date: DD.MM.YYYY HH:MM
      const completedAt = assignmentState.taskCompletedAt[task.id];
      if (completedAt) {
        const date = new Date(completedAt);
        const formatted = date.toLocaleString('de-DE', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
        submittedDate.textContent = formatted;
      } else {
        submittedDate.textContent = '-';
      }

      updateSubmittedMeta(task);
    }
    
    if (editor) {
      editor.updateOptions({ readOnly: true });
    }
  } else {
    // Normal state - show buttons, editor editable, hide submitted info
    if (submittedInfo) submittedInfo.classList.remove('show');
    
    if (editor) {
      editor.updateOptions({ readOnly: false });
    }
    
    // Show save and download buttons for tasks
    if (saveTaskBtn) {
      saveTaskBtn.style.display = 'inline-block';
      // Override the onclick handler to save task code
      saveTaskBtn.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        saveCode();
        return false;
      };
    }

    if (downloadBtn) {
      downloadBtn.style.display = 'inline-block';
    }

    // Show share button (only in non-test mode)
    const shareBtn = $('share-btn');
    if (shareBtn && window.testMode !== true) {
      shareBtn.style.display = 'inline-block';
    }

    // Show undo/redo buttons
    if (undoBtn) undoBtn.style.display = 'inline-block';
    if (redoBtn) redoBtn.style.display = 'inline-block';
  }

  // Hide project save button
  const saveProjectBtn = $('save-project-btn');
  if (saveProjectBtn) {
    saveProjectBtn.style.display = 'none';
  }

  // Watch for code changes to update status to in-progress (only if unbearbeitet)
  if (editor && !task._changeListenerAdded) {
    editor.onDidChangeModelContent(() => {
      cacheCurrentEditorDraft();
      const currentStatus = assignmentState.taskStatuses[task.id];
      if (!currentStatus || currentStatus === 'unbearbeitet') {
        assignmentState.taskStatuses[task.id] = 'in-progress';
        updateTaskStatusDisplay(task);
      }
    });
    task._changeListenerAdded = true;
  }
}

function updateAttemptsCounter(task) {
  const maxAttempts = task.max_attempts || 10;
  const attempts = assignmentState.taskAttempts[task.id] || 0;
  
  // Update check button text with attempts
  const checkBtn = $('check-btn');
  if (checkBtn) {
    checkBtn.textContent = `🔍 Check (${attempts}/${maxAttempts})`;
  }
}

function getHintStats(task) {
  const totalHints = [task.hint1, task.hint2, task.hint3]
    .filter(hint => hint && String(hint).trim() !== '').length;
  const revealed = assignmentState.hintsRevealed[task.id] || [];
  return { revealedCount: revealed.length, totalHints };
}

function updateSubmittedMeta(task) {
  const submittedChecks = $('submitted-checks');
  const submittedHints = $('submitted-hints');
  if (!submittedChecks || !submittedHints) return;

  const maxAttempts = task.max_attempts || 10;
  const attempts = assignmentState.taskAttempts[task.id] || 0;
  const { revealedCount, totalHints } = getHintStats(task);

  submittedChecks.textContent = `${attempts}/${maxAttempts}`;
  submittedHints.textContent = `${revealedCount}/${totalHints}`;
}

function generateFilename(title) {
  return title
    .trim()
    .replace(/\s+/g, '_')  // Replace spaces with underscores
    .replace(/[^a-zA-Z0-9_]/g, '') // Remove special characters
    .substring(0, 50) + '.py'; // Max 50 chars before .py
}

async function downloadCode() {
  const task = assignmentState.currentTask;
  if (!task) {
    console.warn('No task loaded for download');
    return;
  }

  const editor = window.editorInstance;
  if (!editor) {
    console.warn('Editor not ready');
    return;
  }

  const code = editor.getValue();
  const filename = generateFilename(task.title);

  // Create a blob from the code
  const blob = new Blob([code], { type: 'text/plain;charset=utf-8' });
  
  // Create a download link and trigger it
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  link.style.visibility = 'hidden';
  
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  // Clean up
  URL.revokeObjectURL(url);
}

async function saveCode(options = {}) {
  // In test mode, skip API persistence (keep changes in DOM only)
  if (window.TEST_MODE_NO_PERSIST === true) {
    return true;
  }

  // Check if we're editing a folder structure file (from admin test view)
  if (window.currentFile && window.currentFile.taskId) {
    return await saveTaskFile();
  }

  const { setStatus = true } = options;
  const task = assignmentState.currentTask;
  if (!task) {
    console.warn('No task loaded for saving');
    return false;
  }

  const editor = window.editorInstance;
  if (!editor) {
    console.warn('Editor not ready');
    return false;
  }

  const code = editor.getValue();
  const saveTaskBtn = $('save-task-btn');
  const taskId = task.id;

  if (!taskId) {
    console.error('Task ID not found');
    if (saveTaskBtn) {
      saveTaskBtn.title = 'Fehler: Task ID nicht gefunden';
      saveTaskBtn.style.background = '#ef4444';
      saveTaskBtn.style.color = '#fff';
      setTimeout(() => {
        saveTaskBtn.title = 'Speichern';
        saveTaskBtn.style.background = '';
        saveTaskBtn.style.color = '';
      }, 3000);
    }
    return false;
  }

  try {
    // Show saving indicator
    if (saveTaskBtn) {
      saveTaskBtn.style.opacity = '0.6';
      saveTaskBtn.disabled = true;
    }

    // Check if we're in solution mode (admin editing solution code)
    if (assignmentState.solutionMode === true) {
      console.log('[SAVE SOLUTION] Saving solution code for task:', taskId, 'Code length:', code.length);
      
      // Save to tasks API (solution_code field)
      const payload = {
        id: taskId,
        solution_code: code
      };

      console.log('[SAVE SOLUTION] Payload:', payload);

      const response = await fetch('../api/tasks/update.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await response.json();
      console.log('[SAVE SOLUTION] API Response:', data);

      if (!response.ok || (data && data.ok === false)) {
        throw new Error(data?.error || 'Failed to save solution');
      }

      // Show success message
      if (saveTaskBtn) {
        saveTaskBtn.style.opacity = '1';
        saveTaskBtn.disabled = false;
        saveTaskBtn.title = 'Lösung erfolgreich gespeichert ✓';
        saveTaskBtn.style.background = '#10b981';
        saveTaskBtn.style.color = '#fff';
        setTimeout(() => {
          saveTaskBtn.title = 'Speichern';
          saveTaskBtn.style.background = '';
          saveTaskBtn.style.color = '';
        }, 3000);
      }

      return true;
    }

    // Normal mode: Save to user_tasks API
    // Generate filename from task title
    const filename = generateFilename(task.title);

    // Save to user_tasks API
    const payload = {
      task_id: taskId,
      current_code: code,
      hints_revealed: assignmentState.hintsRevealed[taskId] || [],
      started_at: new Date(assignmentState.taskStartTimes[taskId] || Date.now()).toISOString().slice(0, 19).replace('T', ' ')
    };

    if (setStatus) {
      payload.status = 'in-progress';
    }

    console.log('[SAVE] Saving task:', taskId, 'Code length:', code.length, 'chars');
    console.log('[SAVE] Payload:', payload);

    const response = await requestJson('../api/user_tasks/update.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    console.log('[SAVE] API Response:', response);

    if (saveTaskBtn) {
      saveTaskBtn.style.opacity = '1';
      saveTaskBtn.disabled = false;
    }

    return true;
  } catch (err) {
    console.error('Failed to save code:', err);
    if (saveTaskBtn) {
      saveTaskBtn.disabled = false;
      saveTaskBtn.style.opacity = '1';
      saveTaskBtn.title = 'Speichern fehlgeschlagen: ' + err.message;
      saveTaskBtn.style.background = '#ef4444';
      saveTaskBtn.style.color = '#fff';
      setTimeout(() => {
        saveTaskBtn.title = 'Speichern';
        saveTaskBtn.style.background = '';
        saveTaskBtn.style.color = '';
      }, 3000);
    }
    return false;
  }
}

async function incrementRunCount(taskId) {
  if (!taskId) return;
  assignmentState.taskRuns[taskId] = (assignmentState.taskRuns[taskId] || 0) + 1;

  try {
    const payload = {
      task_id: taskId,
      run_count: assignmentState.taskRuns[taskId]
    };
    console.log('[RUN_COUNT] Incrementing run count - Payload:', payload);
    const response = await requestJson('../api/user_tasks/update.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    console.log('[RUN_COUNT] Response:', response);
  } catch (err) {
    console.warn('Failed to update run_count:', err);
  }
}

window.incrementTaskRunCount = incrementRunCount;

function showSuccessModal(task, attempts, maxAttempts) {
  // Calculate elapsed time
  const startTime = assignmentState.taskStartTimes[task.id];
  let elapsedSeconds = 0;
  if (startTime) {
    elapsedSeconds = Math.floor((Date.now() - startTime) / 1000);
  }
  
  const minutes = Math.floor(elapsedSeconds / 60);
  const seconds = elapsedSeconds % 60;
  const timeString = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;

  // Count hints revealed for this task
  const hintsRevealedCount = assignmentState.hintsRevealed[task.id] ? assignmentState.hintsRevealed[task.id].length : 0;

  // Determine attempts label based on task type
  const isIterative = task.task_type === 'code_reading' || task.task_type === 'code_random_complex';
  const attemptsLabel = isIterative ? 'Fehlversuche' : 'Versuche';

  // Build stats HTML (4 stats in 2x2 grid)
  const statsHtml = `
    <div class="success-stat">
      <div class="success-stat-value">✓</div>
      <div class="success-stat-label">Gelöst</div>
    </div>
    <div class="success-stat">
      <div class="success-stat-value">${attempts}/${maxAttempts}</div>
      <div class="success-stat-label">${attemptsLabel}</div>
    </div>
    <div class="success-stat">
      <div class="success-stat-value">${timeString}</div>
      <div class="success-stat-label">Zeit</div>
    </div>
    <div class="success-stat">
      <div class="success-stat-value">${hintsRevealedCount}</div>
      <div class="success-stat-label">Hinweise</div>
    </div>
  `;

  document.getElementById('success-stats').innerHTML = statsHtml;

  // Set message
  document.getElementById('success-message').textContent = `Glückwunsch! Du hast die Aufgabe "${task.title}" erfolgreich gelöst!`;

  // Check if there are more tasks in this assignment
  const tasks = assignmentState.tasksByAssignment[assignmentState.currentAssignmentId] || [];
  const nextTask = tasks.find(t => {
    const status = assignmentState.taskStatuses[t.id];
    return status !== 'passed' && status !== 'failed' && t.id !== task.id;
  });

  // Show/hide next task button
  const nextTaskBtn = $('success-next-task-btn');
  if (nextTaskBtn) {
    nextTaskBtn.style.display = nextTask ? 'inline-block' : 'none';
  }

  // Check if all tasks in current assignment are done
  const allTasksDone = tasks.every(t => {
    const status = assignmentState.taskStatuses[t.id];
    return status === 'passed' || status === 'failed';
  });

  // Show/hide next assignment button
  const nextAssignmentBtn = $('success-next-assignment-btn');
  if (nextAssignmentBtn) {
    let showNextAssignment = false;
    if (allTasksDone && assignmentState.currentAssignmentId) {
      const currentAssignmentIndex = assignmentState.assignments.findIndex(a => a.assignment_id === assignmentState.currentAssignmentId);
      showNextAssignment = currentAssignmentIndex < assignmentState.assignments.length - 1;
    }
    nextAssignmentBtn.style.display = showNextAssignment ? 'inline-block' : 'none';
  }

  // Show modal
  const modal = $('success-modal');
  if (modal) {
    modal.style.display = 'flex';
  }
}

function getNextTaskInAssignment() {
  const tasks = assignmentState.tasksByAssignment[assignmentState.currentAssignmentId] || [];
  const nextTask = tasks.find(t => {
    const status = assignmentState.taskStatuses[t.id];
    return status !== 'passed' && status !== 'failed';
  });
  return nextTask;
}

function getNextAssignment() {
  const currentAssignmentIndex = assignmentState.assignments.findIndex(a => a.assignment_id === assignmentState.currentAssignmentId);
  if (currentAssignmentIndex < assignmentState.assignments.length - 1) {
    return assignmentState.assignments[currentAssignmentIndex + 1];
  }
  return null;
}

function closeSuccessModal() {
  const modal = $('success-modal');
  if (modal) {
    modal.style.display = 'none';
  }
}

function detectEditorFileType() {
  const path = window.currentFile?.path || '';
  if (!path) return 'py';
  const fileName = path.split('/').pop() || '';
  const lower = fileName.toLowerCase();

  if (lower === 'init.py' || lower.endsWith('.py')) return 'py';
  if (lower.endsWith('.html') || lower.endsWith('.htm')) return 'html';
  if (lower.endsWith('.css')) return 'css';
  if (lower.endsWith('.json')) return 'json';
  if (lower.endsWith('.js') || lower.endsWith('.mjs') || lower.endsWith('.cjs')) return 'js';
  if (lower.endsWith('.xml')) return 'xml';
  return 'text';
}

function renderNonPythonCheckResult(fileType, code, outputEl) {
  if (!outputEl) return;

  if (fileType === 'json') {
    try {
      JSON.parse(code || '{}');
      outputEl.innerHTML = `<div style="padding:12px; background:#f0fdf4; border:1px solid #86efac; border-radius:6px; color:#166534;">
        <strong style="font-size:14px;">✅ JSON gültig</strong>
        <div style="font-size:12px; margin-top:4px; color:#15803d;">Die Datei ist syntaktisch korrekt.</div>
      </div>`;
    } catch (err) {
      outputEl.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; color:#991b1b;">
        <strong style="font-size:14px;">❌ JSON-Fehler</strong>
        <div style="font-size:12px; margin-top:4px; color:#dc2626; font-family:monospace;">${escapeHtml(String(err.message || err))}</div>
      </div>`;
    }
    return;
  }

  if (fileType === 'js') {
    // Einfache JS-Syntax-Check (nur offensichtliche Fehler)
    try {
      // Versuche, JavaScript zu kompilieren
      new Function(code);
      outputEl.innerHTML = `<div style="padding:12px; background:#f0fdf4; border:1px solid #86efac; border-radius:6px; color:#166534;">
        <strong style="font-size:14px;">✅ JavaScript OK</strong>
        <div style="font-size:12px; margin-top:4px; color:#15803d;">Keine offensichtlichen Syntaxfehler.</div>
      </div>`;
    } catch (err) {
      outputEl.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; color:#991b1b;">
        <strong style="font-size:14px;">❌ JavaScript-Fehler</strong>
        <div style="font-size:12px; margin-top:4px; color:#dc2626; font-family:monospace;">${escapeHtml(String(err.message || err))}</div>
      </div>`;
    }
    return;
  }

  const labels = {
    html: 'HTML',
    css: 'CSS',
    xml: 'XML',
    text: 'Textdatei'
  };

  const label = labels[fileType] || 'Datei';
  outputEl.innerHTML = `<div style="padding:12px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px; color:#374151;">
    <strong style="font-size:14px;">ℹ️ ${label}</strong>
    <div style="font-size:12px; margin-top:4px; color:#4b5563;">Für ${label.toLowerCase()} wird kein Python-Test ausgeführt. Die Datei ist lokal gespeichert.</div>
  </div>`;
}

async function checkTask() {
  const task = assignmentState.currentTask;
  if (!task) {
    alert('No task loaded');
    return;
  }

  // Only show error if test_cases is missing
  if (!task.test_cases) {
    alert('No test cases available');
    return;
  }

  const editor = window.editorInstance;
  if (!editor) {
    alert('Editor not ready');
    return;
  }

  // Auto-save code before checking (no status change)
  await saveCode({ setStatus: false });

  // Get code from editor
  const code = editor.getValue();
  const activeFileType = detectEditorFileType();

  // Run code in Pyodide
  const outputEl = $('output-container');
  if (outputEl) {
    outputEl.innerHTML = '<span style="color:#666;">Prüfe Code...</span>';
  }

  if (activeFileType !== 'py') {
    renderNonPythonCheckResult(activeFileType, code, outputEl);
    return;
  }

  try {
    // Initialize pyodide (assuming it's globally available)
    let pyodide = window.pyodide;
    if (!pyodide) {
      outputEl.innerHTML = '<span style="color:#c00;">Pyodide not ready</span>';
      return;
    }

    // Parse test cases
    let testCases = [];
    try {
      // Only parse if test_cases exists and is not null/empty
      if (task.test_cases) {
        testCases = JSON.parse(task.test_cases);
        
        // Handle intelligent test config (single object with mode, tests, etc.)
        if (testCases && !Array.isArray(testCases) && testCases.mode) {
          testCases = [{type: 'intelligent', ...testCases}];
        }
        
        // Migrate legacy FUNCTION structure to new structure
        testCases = migrateLegacyTestCases(testCases);
      } else {
        testCases = [];
      }
    } catch (e) {
      console.error('Failed to parse test cases:', e);
      testCases = [];
    }

    let allResults = [];

    // ==========================================
    // GROUP TEST CASES BY TYPE
    // ==========================================
    const groupedByType = {};
    testCases.forEach(tc => {
      const type = tc.type || 'output';
      if (!groupedByType[type]) {
        groupedByType[type] = [];
      }
      groupedByType[type].push(tc);
    });

    // ==========================================
    // EXECUTE EACH TEST TYPE
    // ==========================================
    
    for (const [type, cases] of Object.entries(groupedByType)) {
      console.log(`[CHECK] Executing ${type} tests (${cases.length} case(s))`);
      
      if (type === 'output') {
        // OUTPUT TESTING: Run code, capture stdout
        allResults.push(...await runOutputTests(pyodide, code, cases, task));
      } else if (type === 'function') {
        // FUNCTION TESTING: Call function with args
        allResults.push(...await runFunctionTests(pyodide, code, cases));
      } else if (type === 'variable') {
        // VARIABLE TESTING: Set init vars, check expected vars
        allResults.push(...await runVariableTests(pyodide, code, cases));
      } else if (type === 'intelligent') {
        // INTELLIGENT TESTING: Compare against solution code
        allResults.push(...await runIntelligentTests(pyodide, code, cases, task.solution_code, task.randomizer_code));
      } else if (type === 'code_check') {
        // CODE CHECK: Check if code contains required keywords
        allResults.push(...runCodeCheck(code, cases));
      } else {
        console.warn(`[CHECK] Unknown test type: ${type}`);
      }
    }

    if (allResults.length === 0) {
      outputEl.innerHTML = '<span style="color:#c00;">No test results</span>';
      return;
    }
    
    // Display results
    displayTestResults(allResults, testCases, outputEl);
    
    // Create result object
    const result = {
      passed: allResults.every(r => r.passed),
      total: allResults.length,
      passedCount: allResults.filter(r => r.passed).length,
      message: `${allResults.filter(r => r.passed).length}/${allResults.length} Tests bestanden`,
      results: allResults
    };
    
    // Increment attempts counter and save to database
    assignmentState.taskAttempts[task.id] = (assignmentState.taskAttempts[task.id] || 0) + 1;
    updateAttemptsCounter(task);
    
    // Save attempts to database
    try {
      const editor = window.editorInstance;
      const code = editor ? editor.getValue() : '';
      const savePayload = {
        task_id: task.id,
        attempts: assignmentState.taskAttempts[task.id],
        run_count: assignmentState.taskRuns[task.id] || 0,
        current_code: code,
        hints_revealed: assignmentState.hintsRevealed[task.id] || []
      };
      
      console.log('[CHECK] Saving attempts - Payload:', savePayload);
      const saveResponse = await requestJson('../api/user_tasks/update.php', {
        method: 'POST',
        body: JSON.stringify(savePayload)
      });
      console.log('[CHECK] Attempts save response:', saveResponse);
    } catch (err) {
      console.error('[CHECK] Failed to save attempts:', err);
    }
    
    // Process validation result (for CHECK only - don't change status)
    processValidationResult(result, task, outputEl, false);

  } catch (err) {
    outputEl.innerHTML = `<div style="color:#c00;"><strong>Fehler:</strong> ${escapeHtml(String(err))}</div>`;
  }
}

/**
 * Submit task for grading - runs validation and commits final status
 */
async function submitTask() {
  // Refresh task data from API before running tests (ensures admin edits are reflected)
  const taskRefreshed = await refreshCurrentTaskFromAPI();
  if (taskRefreshed) {
    console.log('[Submit] Task data refreshed from API');
  }
  const task = assignmentState.currentTask;
  if (!task) {
    alert('No task loaded');
    return;
  }

  if (!task.test_cases) {
    alert('No test cases available');
    return;
  }

  const editor = window.editorInstance;
  if (!editor) {
    alert('Editor not ready');
    return;
  }

  // Auto-save code before submitting (no status change)
  await saveCode({ setStatus: false });

  const code = editor.getValue();
  const activeFileType = detectEditorFileType();
  const outputEl = $('output-container');
  if (outputEl) outputEl.innerHTML = '<span style="color:#666;">Überprüfe Code...</span>';

  if (activeFileType !== 'py') {
    renderNonPythonCheckResult(activeFileType, code, outputEl);
    return;
  }

  try {
    let pyodide = window.pyodide;
    if (!pyodide) {
      outputEl.innerHTML = '<span style="color:#c00;">Pyodide not ready</span>';
      return;
    }

    // Parse test cases
    let testCases = [];
    try {
      testCases = JSON.parse(task.test_cases);
      
      // Handle intelligent test config (single object with mode, tests, etc.)
      if (!Array.isArray(testCases) && testCases.mode) {
        testCases = [{type: 'intelligent', ...testCases}];
      }
      
      testCases = migrateLegacyTestCases(testCases);
    } catch (e) {
      console.error('Failed to parse test cases:', e);
      testCases = [];
    }

    let allResults = [];
    const groupedByType = {};
    testCases.forEach(tc => {
      const type = tc.type || 'output';
      if (!groupedByType[type]) groupedByType[type] = [];
      groupedByType[type].push(tc);
    });

    // Execute tests
    for (const [type, cases] of Object.entries(groupedByType)) {
      console.log(`[SUBMIT] Executing ${type} tests`);
      if (type === 'output') {
        allResults.push(...await runOutputTests(pyodide, code, cases, task));
      } else if (type === 'function') {
        allResults.push(...await runFunctionTests(pyodide, code, cases));
      } else if (type === 'variable') {
        allResults.push(...await runVariableTests(pyodide, code, cases));
      } else if (type === 'intelligent') {
        allResults.push(...await runIntelligentTests(pyodide, code, cases, task.solution_code, task.randomizer_code));
      } else if (type === 'code_check') {
        allResults.push(...runCodeCheck(code, cases));
      }
    }

    if (allResults.length === 0) {
      outputEl.innerHTML = '<span style="color:#c00;">No test results</span>';
      return;
    }
    
    // Display results
    displayTestResults(allResults, testCases, outputEl);
    
    const result = {
      passed: allResults.every(r => r.passed),
      total: allResults.length,
      passedCount: allResults.filter(r => r.passed).length,
      message: `${allResults.filter(r => r.passed).length}/${allResults.length} Tests bestanden`,
      results: allResults
    };
    
    // Process with isSubmission=true to commit status and lock editor
    processValidationResult(result, task, outputEl, true);

  } catch (err) {
    outputEl.innerHTML = `<div style="color:#c00;"><strong>Fehler:</strong> ${escapeHtml(String(err))}</div>`;
  }
}

/**
 * Detect test type from test cases
 */
function detectTestType(testCases) {
  if (testCases.length === 0) return 'output';
  
  const firstTest = testCases[0];
  
  // Explicit type specified
  if (firstTest.type) {
    return firstTest.type;
  }
  
  // Auto-detect from legacy format
  if (firstTest.function_name) return 'function';
  if (firstTest.init_vars || firstTest.expected_vars) return 'variable';
  if (firstTest.input === '' || !firstTest.input) return 'output';
  
  // Legacy: has input = function mode
  return 'function';
}

/**
 * Migrate legacy test case structures to new unified structure
 * Legacy FUNCTION: [{ type: 'function', function_name: 'f', args: [...], expected: value }, ...]
 * New FUNCTION:    [{ type: 'function', function_name: 'f', test_cases: [{ args: [...], expected: value }, ...] }]
 */
function migrateLegacyTestCases(testCases) {
  if (!Array.isArray(testCases) || testCases.length === 0) return testCases;

  // Migrate legacy CODE_CHECK structure:
  // [{ type: 'code_check', pattern: '...', hint: '...' }]
  // to
  // [{ type: 'code_check', keywords: ['...'], operator: 'AND', feedback: '...' }]
  const hasLegacyCodeCheck = testCases.some(tc =>
    tc && tc.type === 'code_check' && !Array.isArray(tc.keywords) && (tc.pattern || tc.hint || tc.description)
  );
  if (hasLegacyCodeCheck) {
    return testCases.map(tc => {
      if (!tc || tc.type !== 'code_check') return tc;

      if (Array.isArray(tc.keywords) && tc.keywords.length > 0) {
        return tc;
      }

      const legacyPattern = typeof tc.pattern === 'string' ? tc.pattern.trim() : '';
      const feedback = tc.feedback || tc.hint || tc.description || 'Code-Check';

      return {
        ...tc,
        keywords: legacyPattern ? [legacyPattern] : [],
        operator: tc.operator || 'AND',
        feedback
      };
    });
  }
  
  const firstTest = testCases[0];
  
  // Check if already migrated (new structure has test_cases array for FUNCTION)
  if (firstTest.type === 'function' && Array.isArray(firstTest.test_cases)) {
    return testCases; // Already new structure
  }
  
  // Migrate legacy FUNCTION structure: group by function_name
  if (firstTest.type === 'function' || firstTest.function_name) {
    const grouped = {};
    
    testCases.forEach(tc => {
      const fn = tc.function_name || '';
      if (!grouped[fn]) {
        grouped[fn] = {
          type: 'function',
          function_name: fn,
          test_cases: []
        };
      }
      
      grouped[fn].test_cases.push({
        args: tc.args || [],
        expected: tc.expected
      });
    });
    
    // Return as array of grouped functions
    return Object.values(grouped);
  }
  
  // Migrate legacy VARIABLE structure: convert JSON objects to name/value arrays
  const hasNonVariableType = testCases.some(tc => tc.type && tc.type !== 'variable');
  const hasNewVariableStructure = testCases.some(tc => tc.type === 'variable' && (Array.isArray(tc.test_cases) || Array.isArray(tc.init_var_names) || Array.isArray(tc.expected_var_names)));
  if (hasNonVariableType || hasNewVariableStructure) {
    return testCases;
  }
  if (firstTest.type === 'variable' || firstTest.init_vars || firstTest.expected_vars) {
    // Combine all old test cases into ONE new test case with multiple test_cases
    const allTestCases = [];
    let initNames = [];
    let expectedNames = [];
    
    testCases.forEach(tc => {
      const initVars = tc.init_vars || {};
      const expectedVars = tc.expected_vars || {};
      
      // Use names from first test case
      if (initNames.length === 0) {
        initNames = Object.keys(initVars);
      }
      if (expectedNames.length === 0) {
        expectedNames = Object.keys(expectedVars);
      }
      
      const initValues = initNames.map(name => initVars[name]);
      const expectedValues = expectedNames.map(name => expectedVars[name]);
      
      allTestCases.push({
        init_values: initValues,
        expected_values: expectedValues
      });
    });
    
    return [{
      type: 'variable',
      init_var_names: initNames,
      expected_var_names: expectedNames,
      test_cases: allTestCases
    }];
  }
  
  // No migration needed for OUTPUT type
  return testCases;
}

/**
 * Run OUTPUT tests
 * New structure with expected_type:
 * 
 * expected_type options:
 * - "text" (default): Compare against expected string pattern
 * - "solution": Compare against solution_code output
 * - "regex": Match against regex pattern
 * 
 * validation_mode is read from each testCase.validation_mode (default: 'loose')
 * - "strict": exact match (after trim)
 * - "loose": whitespace-normalized comparison
 * - "contains": user output contains expected
 */
async function runOutputTests(pyodide, code, testCases, task = null) {
  const results = [];
  
  // Check if any test case needs solution comparison
  const needsSolution = testCases.some(tc => 
    tc.expected_type === 'solution' || tc.solution_compare === true
  );
  
  const solutionCode = task?.solution_code || '';
  
  // If solution comparison is needed but no solution_code provided, fail gracefully
  if (needsSolution && !solutionCode) {
    return [{
      passed: false,
      testNumber: 1,
      type: 'output',
      output: '',
      expected: '',
      error: 'expected_type="solution" aktiviert, aber keine solution_code vorhanden'
    }];
  }
  
  // Run solution code once if needed
  let solutionOutput = null;
  if (needsSolution && solutionCode) {
    try {
      solutionOutput = await pyodide.runPythonAsync(`
import sys
from io import StringIO

solution_code = ${JSON.stringify(solutionCode)}

output_buffer = StringIO()
old_stdout = sys.stdout
sys.stdout = output_buffer

try:
    exec(compile(solution_code, "<solution>", "exec"), {})
except Exception as e:
    output_buffer.write(f"Error: {e}")
finally:
    sys.stdout = old_stdout

output_buffer.getvalue()
`);
    } catch (e) {
      return [{
        passed: false,
        testNumber: 1,
        type: 'output',
        output: '',
        expected: '',
        error: `Fehler in Musterloesung: ${e.message}`
      }];
    }
  }
  
  for (let idx = 0; idx < testCases.length; idx++) {
    const testCase = testCases[idx];
    const mockInputs = Array.isArray(testCase.mock_inputs)
      ? testCase.mock_inputs.map(v => String(v))
      : [];
    
    try {
      // Run user code and capture output
      const output = await pyodide.runPythonAsync(`
import sys
import json
import builtins
from io import StringIO

user_code = ${JSON.stringify(code)}
mock_inputs_json = ${JSON.stringify(JSON.stringify(mockInputs))}
mock_inputs = json.loads(mock_inputs_json)

output_buffer = StringIO()
old_stdout = sys.stdout
old_input = builtins.input
sys.stdout = output_buffer

def _mock_input(prompt=''):
    if prompt:
        # emulate normal input prompt behavior in output stream
        output_buffer.write(str(prompt))
    if len(mock_inputs) == 0:
        raise EOFError('No more mock_inputs available for this test case')
    return str(mock_inputs.pop(0))

builtins.input = _mock_input

try:
    exec(compile(user_code, "<usercode>", "exec"), {})
except Exception as e:
    output_buffer.write(f"Error: {e}")
finally:
    builtins.input = old_input
    sys.stdout = old_stdout

output_buffer.getvalue()
`);
      
      let passed = false;
      let expectedValue = testCase.expected || '';
      
      // Get validation_mode from testCase (default: 'loose')
      const validationMode = testCase.validation_mode || 'loose';
      
      // Get case_sensitive from testCase (default: false = case-insensitive)
      const caseSensitive = testCase.case_sensitive !== undefined ? testCase.case_sensitive : false;
      
      // Determine expected_type (with backward compatibility)
      let expectedType = testCase.expected_type || 'text';
      if (testCase.solution_compare === true) {
        expectedType = 'solution'; // Backward compat
      }
      
      // Compare based on expected_type
      switch (expectedType) {
        case 'solution':
          // Compare against solution output
          expectedValue = solutionOutput;
          
          if (validationMode === 'strict') {
            passed = output.trim() === solutionOutput.trim();
          } else if (validationMode === 'contains') {
            passed = output.includes(solutionOutput);
          } else {
            // loose: normalize whitespace
            const normalizeWs = (str) => str.replace(/\s+/g, ' ').trim();
            passed = normalizeWs(output) === normalizeWs(solutionOutput);
          }
          break;
          
        case 'regex':
          // Match against regex pattern
          try {
            // Apply case_sensitive setting to regex flags
            const regexFlags = caseSensitive ? '' : 'i';
            const regex = new RegExp(testCase.expected, regexFlags);
            // Trim output to remove trailing newlines/whitespace
            const trimmedOutput = output.trim();
            passed = regex.test(trimmedOutput);
            
            // Debug logging
            console.log('[OUTPUT REGEX TEST]');
            console.log('  Pattern:', testCase.expected);
            console.log('  Output:', JSON.stringify(output));
            console.log('  Trimmed Output:', JSON.stringify(trimmedOutput));
            console.log('  Output length:', output.length);
            console.log('  Passed:', passed);
            
          } catch (e) {
            // Invalid regex, fail the test
            passed = false;
            expectedValue = `Invalid regex: ${testCase.expected}`;
          }
          break;
          
        case 'text':
        default:
          // Use pattern matching (default behavior)
          passed = compareTestOutput(
            output,
            testCase.expected,
            validationMode,
            testCase.case_sensitive !== undefined ? testCase.case_sensitive : false
          );
          break;
      }
      
      results.push({
        passed,
        testNumber: idx + 1,
        type: 'output',
        output: output,
        expected: expectedValue,
        expectedType: expectedType,
        validationMode: validationMode,
        error: null
      });
      
    } catch (e) {
      results.push({
        passed: false,
        testNumber: idx + 1,
        type: 'output',
        output: '',
        expected: testCase.expected || '',
        expectedType: testCase.expected_type || 'text',
        validationMode: testCase.validation_mode || 'loose',
        error: e.message
      });
    }
  }
  
  return results;
}

function createSeededRng(seed) {
  let s = Number(seed);
  if (!Number.isFinite(s)) {
    s = Date.now() & 0xffffffff;
  }
  let t = s >>> 0;
  return () => {
    t += 0x6D2B79F5;
    let r = Math.imul(t ^ (t >>> 15), 1 | t);
    r ^= r + Math.imul(r ^ (r >>> 7), 61 | r);
    return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
  };
}

function generateValue(def, rng) {
  if (def && Array.isArray(def.values) && def.values.length > 0) {
    const idx = Math.floor(rng() * def.values.length);
    return def.values[idx];
  }

  const type = String(def?.type || 'int').toLowerCase();
  if (type === 'choice' || type === 'enum') {
    const values = Array.isArray(def?.values) ? def.values : [];
    if (values.length === 0) return null;
    const idx = Math.floor(rng() * values.length);
    return values[idx];
  }

  if (type === 'list' || type === 'array') {
    const minLen = Number(def?.minLength ?? def?.min_len ?? 1);
    const maxLen = Number(def?.maxLength ?? def?.max_len ?? minLen);
    const len = Math.max(0, Math.floor(minLen + rng() * Math.max(1, maxLen - minLen + 1)));
    const elementDef = def?.element || def?.of || { type: 'int' };
    const out = [];
    for (let i = 0; i < len; i += 1) {
      out.push(generateValue(elementDef, rng));
    }
    return out;
  }

  if (type === 'object' || type === 'dict' || type === 'map') {
    const fields = Array.isArray(def?.fields) ? def.fields : [];
    const out = {};
    fields.forEach((field) => {
      if (field?.name) {
        out[field.name] = generateValue(field, rng);
      }
    });
    return out;
  }
  if (type === 'bool' || type === 'boolean') {
    return rng() < 0.5;
  }

  if (type === 'string' || type === 'str') {
    const minLen = Number(def?.minLength ?? def?.min_len ?? 3);
    const maxLen = Number(def?.maxLength ?? def?.max_len ?? 8);
    const len = Math.max(0, Math.floor(minLen + rng() * Math.max(1, maxLen - minLen + 1)));
    const alphabet = 'abcdefghijklmnopqrstuvwxyz';
    let out = '';
    for (let i = 0; i < len; i += 1) {
      out += alphabet[Math.floor(rng() * alphabet.length)];
    }
    return out;
  }

  if (type === 'float' || type === 'number' || type === 'double') {
    const min = Number(def?.min ?? 0);
    const max = Number(def?.max ?? 1);
    return min + rng() * (max - min);
  }

  // Default: int
  const min = Number(def?.min ?? 0);
  const max = Number(def?.max ?? 10);
  return Math.floor(min + rng() * (max - min + 1));
}

function compareIntelligentValue(actual, expected, type, tolerance) {
  const def = type && typeof type === 'object' ? type : { type };
  const normalizedType = String(def?.type || '').toLowerCase();

  if (normalizedType === 'list' || normalizedType === 'array') {
    if (!Array.isArray(actual) || !Array.isArray(expected)) return false;
    if (actual.length !== expected.length) return false;
    const elementDef = def?.element || def?.of || {};
    return expected.every((val, idx) => compareIntelligentValue(actual[idx], val, elementDef, tolerance));
  }

  if (normalizedType === 'object' || normalizedType === 'dict' || normalizedType === 'map') {
    const fields = Array.isArray(def?.fields) ? def.fields : null;
    if (fields) {
      return fields.every((field) => {
        if (!field?.name) return true;
        return compareIntelligentValue(actual?.[field.name], expected?.[field.name], field, tolerance);
      });
    }
    return compareValues(actual, expected);
  }
  if (normalizedType === 'float' || normalizedType === 'number' || normalizedType === 'double') {
    const actualNum = Number(actual);
    const expectedNum = Number(expected);
    if (!Number.isFinite(actualNum) || !Number.isFinite(expectedNum)) {
      return false;
    }
    return Math.abs(actualNum - expectedNum) <= tolerance;
  }

  if (normalizedType === 'int' || normalizedType === 'integer') {
    return Number(actual) === Number(expected);
  }

  if (normalizedType === 'bool' || normalizedType === 'boolean') {
    return Boolean(actual) === Boolean(expected);
  }

  return compareValues(actual, expected);
}

async function runIntelligentTests(pyodide, code, testCases, solutionCode, randomizerCode) {
  const results = [];
  const testSpec = testCases[0];
  if (!testSpec) return results;

  const mode = testSpec.mode || 'function';
  const testsCount = Number(testSpec.tests ?? 4);
  
  // Extract configuration based on mode
  let functionName = '';
  let params = [];
  let inputs = [];
  let outputs = [];
  
  if (mode === 'function') {
    if (!testSpec.function || !testSpec.function.name) {
      return [{
        passed: false,
        testNumber: 1,
        type: 'intelligent',
        mode,
        error: 'Function mode: function.name fehlt'
      }];
    }
    functionName = testSpec.function.name;
    params = Array.isArray(testSpec.function.params) ? testSpec.function.params : [];
  } else if (mode === 'vars') {
    inputs = Array.isArray(testSpec.inputs) ? testSpec.inputs : [];
    outputs = Array.isArray(testSpec.outputs) ? testSpec.outputs : [];
    
    if (inputs.length === 0) {
      return [{
        passed: false,
        testNumber: 1,
        type: 'intelligent',
        mode,
        error: 'Vars mode: inputs Array fehlt'
      }];
    }
    
    if (outputs.length === 0) {
      return [{
        passed: false,
        testNumber: 1,
        type: 'intelligent',
        mode,
        error: 'Vars mode: outputs Array fehlt'
      }];
    }
  }

  // Check for solution code
  const effectiveSolutionCode = testSpec.solution_code || solutionCode || '';
  if (!effectiveSolutionCode) {
    return [{
      passed: false,
      testNumber: 1,
      type: 'intelligent',
      mode,
      error: 'Musterloesung fehlt (Solution Code ist leer)'
    }];
  }

  // Check for randomizer code
  const effectiveRandomizerCode = testSpec.randomizer_code || randomizerCode || '';
  if (!effectiveRandomizerCode) {
    return [{
      passed: false,
      testNumber: 1,
      type: 'intelligent',
      mode,
      error: 'Randomizer Code fehlt (erforderlich für Intelligent Tests)'
    }];
  }

  console.log('[Intelligent Test] Mode:', mode);
  console.log('[Intelligent Test] Tests Count:', testsCount);
  console.log('[Intelligent Test] Randomizer Code:', effectiveRandomizerCode.substring(0, 100) + '...');

  // Generate test cases by running randomizer code multiple times
  const testOutputs = await pyodide.runPythonAsync(`
import json

user_code = ${JSON.stringify(code)}
solution_code = ${JSON.stringify(effectiveSolutionCode)}
randomizer_code = ${JSON.stringify(effectiveRandomizerCode)}
mode = ${JSON.stringify(mode)}
tests_count = ${testsCount}
function_name = ${JSON.stringify(functionName)}
params = ${JSON.stringify(params)}
inputs = ${JSON.stringify(inputs)}
outputs = ${JSON.stringify(outputs)}

def safe_value(value):
    """Convert Python values to JSON-safe format"""
    if value is None or isinstance(value, (int, float, bool, str)):
        return value
    if isinstance(value, (list, tuple)):
        return [safe_value(v) for v in value]
    if isinstance(value, dict):
        return {str(k): safe_value(v) for k, v in value.items()}
    return repr(value)

def run_randomizer():
    """Execute randomizer code and extract values dict"""
    namespace = {}
    try:
        exec(compile(randomizer_code, "<randomizer>", "exec"), namespace)
    except Exception as e:
        return {"error": f"Randomizer execution error: {e}"}
    
    if 'values' not in namespace:
        return {"error": "Randomizer must create 'values' dict"}
    
    return {"values": namespace['values']}

def run_vars_mode(code, values_dict, output_names):
    """Run code with values injected, extract outputs"""
    namespace = {}
    input_queue = []

    def _extract_input_items(values):
        items = []
        for raw_key, raw_value in values.items():
            key = str(raw_key)
            idx = None

            # Preferred format: INPUT_01, INPUT_02, ...
            if key.startswith('INPUT_'):
                suffix = key.split('_', 1)[1]
                if suffix.isdigit():
                    idx = int(suffix)
            # Backward compatibility: INPUT#1, INPUT#2, ...
            elif key.startswith('INPUT#'):
                suffix = key.split('#', 1)[1]
                if suffix.isdigit():
                    idx = int(suffix)
            # Backward compatibility: INPUT1, INPUT2, ...
            elif key.startswith('INPUT'):
                suffix = key[5:]
                if suffix.isdigit():
                    idx = int(suffix)

            if idx is not None:
                items.append((idx, raw_value))

        items.sort(key=lambda x: x[0])
        return items

    input_items = _extract_input_items(values_dict)
    input_queue = [str(v) for _, v in input_items]

    import builtins
    old_input = builtins.input

    def _mock_input(prompt=''):
        if len(input_queue) == 0:
            raise EOFError("No more INPUT_XX values in randomizer values dict")
        return str(input_queue.pop(0))

    builtins.input = _mock_input
    
    try:
        try:
            exec(compile(code, "<code>", "exec"), namespace)
        except Exception as e:
            return {"error": str(e)}
        
        # Override the initialized variables with randomized values
        namespace.update(values_dict)
        
        # Now we need to re-run the solution code to recalculate outputs based on new values
        # Extract code after INIT block for recalculation
        init_block_end = code.find("#INIT END")
        if init_block_end != -1:
            # Code after INIT block
            calculation_code = code[init_block_end + len("#INIT END"):].strip()
            if calculation_code.strip():
                try:
                    exec(compile(calculation_code, "<calculation>", "exec"), namespace)
                except Exception as e:
                    return {"error": str(e)}
    finally:
        builtins.input = old_input
    
    out = {}
    for name in output_names:
        if name in namespace:
            out[name] = safe_value(namespace[name])
        else:
            return {"error": f"Variable '{name}' not found"}
    return {"output": out}

def run_function_mode(code, values_dict, fn_name, param_names):
    """Run code and call function with values as arguments"""
    namespace = {}
    try:
        exec(compile(code, "<code>", "exec"), namespace)
    except Exception as e:
        return {"error": f"Code execution error: {e}"}
    
    fn = namespace.get(fn_name)
    if not callable(fn):
        return {"error": f"Function '{fn_name}' not found"}
    
    # Build args from values_dict using param_names order
    args = []
    for param_name in param_names:
        if param_name not in values_dict:
            return {"error": f"Parameter '{param_name}' not in values dict"}
        args.append(values_dict[param_name])
    
    try:
        res = fn(*args)
        return {"output": safe_value(res)}
    except Exception as e:
        return {"error": str(e)}

results = []
for test_num in range(tests_count):
    # Generate new random values for this test
    rand_result = run_randomizer()
    if 'error' in rand_result:
        results.append({
            "test": test_num + 1,
            "error": rand_result['error'],
            "values": {}
        })
        continue
    
    values = rand_result['values']
    # DEBUG: Log generated values
    import sys
    print(f"[DEBUG Test #{test_num + 1}] Generated values: {values}", file=sys.stderr)
    
    if mode == 'vars':
        # Vars mode: inject values, compare outputs
        sol = run_vars_mode(solution_code, values, outputs)
        usr = run_vars_mode(user_code, values, outputs)
        results.append({
            "test": test_num + 1,
            "values": safe_value(values),
            "solution": sol,
            "user": usr
        })
    else:
        # Function mode: call functions with values as args
        sol = run_function_mode(solution_code, values, function_name, params)
        usr = run_function_mode(user_code, values, function_name, params)
        results.append({
            "test": test_num + 1,
            "values": safe_value(values),
            "solution": sol,
            "user": usr
        })

json.dumps(results)
`);

  let parsed;
  try {
    parsed = JSON.parse(testOutputs);
    console.log('[Intelligent Test Results] Parsed:', parsed.map(p => ({ test: p.test, values: p.values, error: p.error })));
  } catch (e) {
    return [{
      passed: false,
      testNumber: 1,
      type: 'intelligent',
      mode,
      error: `Failed to parse results: ${e.message}`
    }];
  }

  // Process results
  parsed.forEach((testResult, idx) => {
    const solution = testResult.solution || {};
    const user = testResult.user || {};
    const values = testResult.values || {};
    
    const base = {
      testNumber: idx + 1,
      type: 'intelligent',
      mode,
      values,
      functionName,
      params,
      inputs,
      outputs,
      expected: solution.output,
      actual: user.output
    };

    // Check for randomizer error
    if (testResult.error) {
      results.push({
        ...base,
        passed: false,
        error: testResult.error
      });
      return;
    }

    // Check for solution error
    if (solution.error) {
      results.push({
        ...base,
        passed: false,
        error: `Musterloesung: ${solution.error}`
      });
      return;
    }

    // Check for user error
    if (user.error) {
      results.push({
        ...base,
        passed: false,
        error: `Fehler: ${user.error}`
      });
      return;
    }

    // Compare outputs
    let passed = true;
    const matchDetails = [];

    if (mode === 'vars') {
      // Compare each output variable
      outputs.forEach((outputName) => {
        const expectedValue = solution.output ? solution.output[outputName] : undefined;
        const actualValue = user.output ? user.output[outputName] : undefined;
        const matches = JSON.stringify(actualValue) === JSON.stringify(expectedValue);
        if (!matches) {
          passed = false;
        }
        matchDetails.push({
          name: outputName,
          expected: expectedValue,
          actual: actualValue,
          matches
        });
      });
    } else {
      // Compare function return value
      const matches = JSON.stringify(user.output) === JSON.stringify(solution.output);
      passed = matches;
      matchDetails.push({
        name: functionName,
        expected: solution.output,
        actual: user.output,
        matches
      });
    }

    results.push({
      ...base,
      passed,
      matchDetails
    });
  });

  return results;
}

/**
 * Run FUNCTION tests
 * validation_mode is read from each testCase.validation_mode (default: 'loose')
 */
async function runFunctionTests(pyodide, code, testCases) {
  const results = [];
  
  // Handle NEW structure: testCases = [{type: 'function', function_name: '...', test_cases: [{args, expected}, ...]}]
  const testSpec = testCases[0];
  if (!testSpec) return results;
  
  const functionName = testSpec.function_name || '';
  const isNewStructure = Array.isArray(testSpec.test_cases);
  const casesToRun = isNewStructure ? testSpec.test_cases : testCases;
  
  const testOutputs = await pyodide.runPythonAsync(`
import sys
from io import StringIO
import json

user_code = ${JSON.stringify(code)}
test_cases_json = ${JSON.stringify(JSON.stringify(casesToRun))}
test_cases = json.loads(test_cases_json)

# Execute user code to define functions
namespace = {}
try:
    exec(compile(user_code, "<usercode>", "exec"), namespace)
except Exception as e:
    print(json.dumps({"error": f"Code execution error: {e}"}))
    ""

# Run each test
results = []
for idx, test in enumerate(test_cases):
    result = {"test": idx + 1, "output": "", "error": None}
    
    # Get function name from test or use provided name
    if 'function_name' in test:
        fn_name = test['function_name']
    else:
        fn_name = '${functionName}' or None
        if not fn_name:
            # Legacy: find first function
            for name, obj in namespace.items():
                if callable(obj) and not name.startswith('_'):
                    fn_name = name
                    break
    
    if not fn_name or fn_name not in namespace:
        result["error"] = f"Function '{fn_name}' not found"
        results.append(result)
        continue
    
    # Get args
    if 'args' in test:
        args = test['args'] if isinstance(test['args'], list) else [test['args']]
    elif 'input' in test and test['input']:
        # Legacy: parse comma-separated input
        input_str = test['input']
        if ',' in input_str:
            parts = [p.strip() for p in input_str.split(',')]
            args = []
            for part in parts:
                try:
                    args.append(eval(part))
                except:
                    args.append(part)
        else:
            try:
                args = [eval(input_str)]
            except:
                args = [input_str]
    else:
        args = []
    
    # Call function
    output_buffer = StringIO()
    old_stdout = sys.stdout
    sys.stdout = output_buffer
    
    try:
        if args:
            output = namespace[fn_name](*args)
        else:
            output = namespace[fn_name]()
        
        # Capture printed output or return value
        printed = output_buffer.getvalue()
        if printed:
            result["output"] = printed.strip()
        elif output is not None:
            result["output"] = str(output)
        else:
            result["output"] = ""
            
    except Exception as e:
        result["error"] = str(e)
    finally:
        sys.stdout = old_stdout
    
    results.append(result)

json.dumps(results)
`);
  
  try {
    const testResults = JSON.parse(testOutputs);
    
    if (testResults.error) {
      return [{
        passed: false,
        testNumber: 1,
        type: 'function',
        error: testResults.error
      }];
    }
    
    testResults.forEach((testResult, idx) => {
      const testCase = casesToRun[idx];
      // Get validation_mode from testCase (default: 'loose')
      const validationMode = testCase.validation_mode || testSpec.validation_mode || 'loose';
      const passed = testResult.error ? false : compareTestOutput(
        testResult.output,
        testCase.expected,
        validationMode,
        testCase.case_sensitive !== undefined ? testCase.case_sensitive : false
      );
      
      results.push({
        passed,
        testNumber: idx + 1,
        type: 'function',
        functionName: functionName || '(auto)',
        args: testCase.args || testCase.input,
        output: testResult.output,
        expected: testCase.expected,
        error: testResult.error
      });
    });
    
  } catch (e) {
    results.push({
      passed: false,
      testNumber: 1,
      type: 'function',
      error: `Failed to parse results: ${e.message}`
    });
  }
  
  return results;
}

/**
 * Run VARIABLE tests
 * validation_mode is read from testSpec.validation_mode (default: 'loose')
 */
async function runVariableTests(pyodide, code, testCases) {
  const results = [];
  
  // Handle NEW structure: testCases = [{type: 'variable', init_var_names: [...], expected_var_names: [...], test_cases: [{init_values, expected_values}, ...]}]
  const testSpec = testCases[0];
  if (!testSpec) return results;
  
  const initVarNames = testSpec.init_var_names || [];
  const expectedVarNames = testSpec.expected_var_names || [];
  const isNewStructure = Array.isArray(testSpec.test_cases);
  const casesToRun = isNewStructure ? testSpec.test_cases : testCases;
  
  const testOutputs = await pyodide.runPythonAsync(`
import sys
import json
import re

user_code = ${JSON.stringify(code)}
test_cases_json = ${JSON.stringify(JSON.stringify(casesToRun))}
test_cases = json.loads(test_cases_json)
init_var_names_json = ${JSON.stringify(JSON.stringify(initVarNames))}
init_var_names = json.loads(init_var_names_json)
expected_var_names_json = ${JSON.stringify(JSON.stringify(expectedVarNames))}
expected_var_names = json.loads(expected_var_names_json)
is_new_structure = ${isNewStructure ? 'True' : 'False'}

# Remove #INIT Start# ... #INIT End# blocks for CHECK
# This allows students to test with their own values (RUN)
# but we ignore those values during CHECK
pattern = r'#INIT Start#.*?#INIT End#'
code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)

results = []
for idx, test in enumerate(test_cases):
    result = {"test": idx + 1, "variables": {}, "error": None}
    
    # Create namespace with init vars
    namespace = {}
    
    if is_new_structure:
        # New structure: pair init_values with init_var_names
        init_values = test.get('init_values', [])
        for i, var_name in enumerate(init_var_names):
            if i < len(init_values):
                namespace[var_name] = init_values[i]
    else:
        # Legacy structure: use init_vars object
        if 'init_vars' in test:
            namespace.update(test['init_vars'])
    
    # Execute user code (without INIT blocks)
    try:
        exec(compile(code_without_init, "<usercode>", "exec"), namespace)
        
        # Extract expected variables
        if is_new_structure:
            # New structure: extract by expected_var_names
            for var_name in expected_var_names:
                if var_name in namespace:
                    result["variables"][var_name] = namespace[var_name]
                else:
                    result["error"] = f"Variable '{var_name}' not found after execution"
                    break
        else:
            # Legacy structure: use expected_vars object
            if 'expected_vars' in test:
                for var_name in test['expected_vars'].keys():
                    if var_name in namespace:
                        result["variables"][var_name] = namespace[var_name]
                    else:
                        result["error"] = f"Variable '{var_name}' not found after execution"
                        break
                    
    except Exception as e:
        result["error"] = str(e)
    
    results.append(result)

json.dumps(results)
`);
  
  try {
    const testResults = JSON.parse(testOutputs);
    
    testResults.forEach((testResult, idx) => {
      const testCase = casesToRun[idx];
      let passed = !testResult.error;
      let matchDetails = [];
      
      if (passed) {
        if (isNewStructure) {
          // New structure: compare expected_values with actual values
          const expectedValues = testCase.expected_values || [];
          
          expectedVarNames.forEach((varName, vIdx) => {
            const expectedValue = vIdx < expectedValues.length ? expectedValues[vIdx] : undefined;
            const actualValue = testResult.variables[varName];
            const matches = compareValues(actualValue, expectedValue);
            
            matchDetails.push({
              varName,
              expected: expectedValue,
              actual: actualValue,
              matches
            });
            
            if (!matches) {
              passed = false;
            }
          });
        } else {
          // Legacy structure: compare expected_vars object
          if (testCase.expected_vars) {
            for (const [varName, expectedValue] of Object.entries(testCase.expected_vars)) {
              const actualValue = testResult.variables[varName];
              const matches = compareValues(actualValue, expectedValue);
              
              matchDetails.push({
                varName,
                expected: expectedValue,
                actual: actualValue,
                matches
              });
              
              if (!matches) {
                passed = false;
              }
            }
          }
        }
      }
      
      // Build display info
      let initInfo, expectedInfo;
      
      if (isNewStructure) {
        const initValues = testCase.init_values || [];
        const initPairs = initVarNames.map((name, i) => `${name}=${initValues[i]}`).join(', ');
        const expectedValues = testCase.expected_values || [];
        const expectedPairs = expectedVarNames.map((name, i) => `${name}=${expectedValues[i]}`).join(', ');
        
        initInfo = initPairs;
        expectedInfo = expectedPairs;
      } else {
        initInfo = testCase.init_vars || {};
        expectedInfo = testCase.expected_vars || {};
      }
      
      results.push({
        passed,
        testNumber: idx + 1,
        type: 'variable',
        initVars: initInfo,
        expectedVars: expectedInfo,
        actualVars: testResult.variables,
        matchDetails,
        error: testResult.error
      });
    });
    
  } catch (e) {
    results.push({
      passed: false,
      testNumber: 1,
      type: 'variable',
      error: `Failed to parse results: ${e.message}`
    });
  }
  
  return results;
}

/**
 * Compare two values (supports various types)
 */
function compareValues(actual, expected) {
  // Handle arrays
  if (Array.isArray(expected) && Array.isArray(actual)) {
    if (expected.length !== actual.length) return false;
    return expected.every((val, idx) => compareValues(actual[idx], val));
  }
  
  // Handle objects
  if (typeof expected === 'object' && typeof actual === 'object' && expected !== null && actual !== null) {
    const expectedKeys = Object.keys(expected);
    const actualKeys = Object.keys(actual);
    if (expectedKeys.length !== actualKeys.length) return false;
    return expectedKeys.every(key => compareValues(actual[key], expected[key]));
  }
  
  // Handle primitives
  return actual === expected;
}

/**
 * Display test results in UI - COMPACT VERSION
 */
function displayTestResults(results, testCases, outputEl) {
  // Group results by type
  const groupedByType = {};
  results.forEach((result, idx) => {
    const type = result.type;
    if (!groupedByType[type]) {
      groupedByType[type] = [];
    }
    // For new structure (FUNCTION/VARIABLE), testCases[0] contains the testSpec with test_cases array
    // For legacy structure, testCases[idx] contains the individual test
    const testCase = testCases[0] || testCases[idx];
    groupedByType[type].push({result, testCase, idx});
  });
  
  let html = '';
  
  // ===== DEBUG: Show detailed test-by-test results =====
  html += `<div style="background:#f9fafb; padding:12px; border-radius:6px; margin-bottom:16px; border:1px solid #e5e7eb;">`;
  html += `<div style="font-weight:700; font-size:13px; color:#374151; margin-bottom:10px;">🔍 DEBUG: Einzelne Tests</div>`;
  html += `<div style="font-size:12px;">`;
  
  results.forEach((result, idx) => {
    const status = result.passed ? '✓' : '✗';
    const statusColor = result.passed ? '#10b981' : '#ef4444';
    const statusBg = result.passed ? '#f0fdf4' : '#fef2f2';
    const testCase = testCases[idx] || {};
    
    html += `<div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:8px; padding:8px; background:${statusBg}; border-radius:4px; border-left:3px solid ${statusColor};">`;
    
    // Status indicator
    html += `<span style="color:${statusColor}; font-weight:700; min-width:30px; font-size:14px;">${status}</span>`;
    
    // Test info
    html += `<div style="flex:1;">`;
    html += `<div style="color:#374151; font-size:12px;">Test #${idx + 1} (${result.type.toUpperCase()})`;
    
    // Show input/args
    if (result.type === 'output' && result.testNumber) {
      html += `</div>`;
      html += `<div style="color:#666; font-size:11px; margin-top:2px;">Output: <code style="background:#e5e7eb; padding:1px 4px;">${escapeHtml((result.output || '').substring(0, 50))}</code></div>`;
    } else if (result.type === 'function') {
      html += ` - ${result.functionName}(${(result.args || '').toString()})`;
      html += `</div>`;
      if (result.output !== undefined) {
        const outputStr = String(result.output);
        const expectedStr = String(result.expected);
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Ergebnis: <code style="background:#e5e7eb; padding:1px 4px;">${escapeHtml(outputStr)}</code></div>`;
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Erwartet: <code style="background:#fef3c7; padding:1px 4px;">${escapeHtml(expectedStr)}</code></div>`;
        html += `<div style="color:#888; font-size:10px; margin-top:2px;">Typ: output=${typeof result.output} expected=${typeof result.expected}</div>`;
      }
    } else if (result.type === 'variable') {
      html += `</div>`;
      // result.initVars and result.expectedVars can be strings (new structure) or objects (legacy)
      const initDisplay = typeof result.initVars === 'string' 
        ? result.initVars 
        : (result.initVars ? Object.keys(result.initVars).join(', ') : '');
      const expectedDisplay = typeof result.expectedVars === 'string' 
        ? result.expectedVars 
        : (result.expectedVars ? Object.keys(result.expectedVars).join(', ') : '');
      
      if (initDisplay) {
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Init: <code style="background:#dbeafe; padding:1px 4px;">${escapeHtml(initDisplay)}</code></div>`;
      }
      if (expectedDisplay) {
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Erwartet: <code style="background:#fef3c7; padding:1px 4px;">${escapeHtml(expectedDisplay)}</code></div>`;
      }
    } else if (result.type === 'intelligent') {
      html += `</div>`;
      const inputsDisplay = result.mode === 'function'
        ? JSON.stringify(result.args || [])
        : JSON.stringify(result.inputs || {});
      
      // Build input values display with actual values from result.values
      let inputValuesDisplay = '';
      if (result.mode === 'vars' && result.values && result.inputs) {
        const inputPairs = (result.inputs || []).map(inputName => {
          const value = result.values[inputName];
          return `[${inputName} = ${JSON.stringify(value)}]`;
        }).join(', ');
        inputValuesDisplay = inputPairs;
      }
      
      const expectedDisplay = JSON.stringify(result.expected ?? null);
      const actualDisplay = JSON.stringify(result.actual ?? null);
      if (result.mode === 'function') {
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Funktion: <code style="background:#e5e7eb; padding:1px 4px;">${escapeHtml(result.functionName || '')}</code></div>`;
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Args: <code style="background:#dbeafe; padding:1px 4px;">${escapeHtml(inputsDisplay)}</code></div>`;
      } else {
        html += `<div style="color:#666; font-size:11px; margin-top:2px;">Input-Namen: <code style="background:#dbeafe; padding:1px 4px;">${escapeHtml(inputsDisplay)}</code></div>`;
        if (inputValuesDisplay) {
          html += `<div style="color:#666; font-size:11px; margin-top:2px;">Input-Werte: <code style="background:#d1fae5; padding:1px 4px;">${escapeHtml(inputValuesDisplay)}</code></div>`;
        }
      }
      html += `<div style="color:#666; font-size:11px; margin-top:2px;">Erwartet: <code style="background:#fef3c7; padding:1px 4px;">${escapeHtml(expectedDisplay)}</code></div>`;
      html += `<div style="color:#666; font-size:11px; margin-top:2px;">Ergebnis: <code style="background:#e5e7eb; padding:1px 4px;">${escapeHtml(actualDisplay)}</code></div>`;
    } else if (result.type === 'code_check') {
      html += `</div>`;
      const feedbackText = result.feedback || 'Code-Check';
      html += `<div style="color:#666; font-size:11px; margin-top:2px;">Keywords: <code style="background:#e5e7eb; padding:1px 4px;">${escapeHtml(feedbackText)}</code></div>`;
    }
    
    // Error message
    if (result.error) {
      html += `<div style="color:#c00; font-size:11px; margin-top:2px;"><strong>Fehler:</strong> ${escapeHtml(result.error)}</div>`;
    }
    
    html += `</div>`;
    html += `</div>`;
  });
  
  html += `</div>`;
  html += `</div>`;
  
  // ===== Summary by type =====
  html += `<div style="background:#f3f4f6; padding:12px; border-radius:6px; border:1px solid #d1d5db;">`;
  html += `<div style="font-weight:700; font-size:13px; color:#374151; margin-bottom:12px;">📊 Zusammenfassung nach Typ</div>`;
  
  // Display grouped results
  Object.entries(groupedByType).forEach(([type, items]) => {
    const passedCount = items.filter(item => item.result.passed).length;
    const totalCount = items.length;
    const allPassed = passedCount === totalCount;
    const color = allPassed ? '#10b981' : '#ef4444';
    const bgColor = allPassed ? '#f0fdf4' : '#fef2f2';
    
    html += `<div style="margin-bottom:12px; padding:12px; background:${bgColor}; border-radius:6px; border-left:4px solid ${color};">`;
    html += `<div style="color:${color}; font-weight:700; font-size:14px; margin-bottom:8px;">`;
    html += `${allPassed ? '✓' : '✗'} ${type.toUpperCase()} - ${passedCount}/${totalCount} Tests`;
    html += `</div>`;
    
    // Type-specific compact display
    if (type === 'output') {
      // Show only first output line, full output on hover
      items.forEach(({result}) => {
        if (!result.error && result.output) {
          const firstLine = result.output.split('\n')[0];
          const fullOutput = escapeHtml(result.output);
          html += `<div style="font-size:12px; color:#555; margin-top:4px; cursor:help;" title="${fullOutput}">`;
          html += `📄 ${escapeHtml(firstLine)}${result.output.includes('\n') ? '...' : ''}`;
          html += `</div>`;
        }
      });
      
    } else if (type === 'function') {
      // Show function name and result count
      const funcName = items[0]?.result?.functionName || 'unknown';
      html += `<div style="font-size:12px; color:#555; margin-top:4px;">`;
      html += `⚙️ Funktion: <code style="background:#e5e7eb; padding:2px 6px; border-radius:3px;">${escapeHtml(funcName)}()</code>`;
      html += `</div>`;
      
    } else if (type === 'variable') {
      // Show result variable name(s) and init vars
      const firstResult = items[0]?.result;
      if (!firstResult) return;
      
      // Handle both new (string) and legacy (object) structures
      let expectedVarsDisplay, initVarsNamesDisplay;
      
      if (typeof firstResult.expectedVars === 'string') {
        // New structure: "summe=20, produkt=30" -> extract names only
        expectedVarsDisplay = firstResult.expectedVars.split(',').map(s => s.split('=')[0].trim()).join(', ');
        // Extract init var names from "x=5, y=10" -> "x, y"
        initVarsNamesDisplay = firstResult.initVars.split(',').map(s => s.split('=')[0].trim()).join(', ');
      } else {
        // Legacy structure: object
        expectedVarsDisplay = Object.keys(firstResult.expectedVars || {}).join(', ');
        initVarsNamesDisplay = Object.keys(firstResult.initVars || {}).join(', ');
      }
      
      html += `<div style="font-size:12px; color:#555; margin-top:4px;">`;
      html += `Args: <code style="background:#e5e7eb; padding:2px 6px; border-radius:3px;">${initVarsNamesDisplay}</code>`;
      html += `</div>`;
      html += `<div style="font-size:12px; color:#555; margin-top:2px;">`;
      html += `checked: <code style="background:#e5e7eb; padding:2px 6px; border-radius:3px;">${expectedVarsDisplay}</code>`;
      html += `</div>`;
    } else if (type === 'intelligent') {
      const firstResult = items[0]?.result;
      const mode = firstResult?.mode || 'vars';
      html += `<div style="font-size:12px; color:#555; margin-top:4px;">`;
      html += `Mode: <code style="background:#e5e7eb; padding:2px 6px; border-radius:3px;">${escapeHtml(mode)}</code>`;
      html += `</div>`;
      if (mode === 'function') {
        const fn = firstResult?.functionName || 'function';
        html += `<div style="font-size:12px; color:#555; margin-top:2px;">`;
        html += `Funktion: <code style="background:#e5e7eb; padding:2px 6px; border-radius:3px;">${escapeHtml(fn)}()</code>`;
        html += `</div>`;
      } else {
        const outputs = (firstResult?.outputs || []).join(', ');
        html += `<div style="font-size:12px; color:#555; margin-top:2px;">`;
        html += `Outputs: <code style="background:#e5e7eb; padding:2px 6px; border-radius:3px;">${escapeHtml(outputs)}</code>`;
        html += `</div>`;
      }

      // Show generated cases (safe to display for intelligent randomized tests)
      items.forEach(({result}, itemIdx) => {
        let payload, payloadDisplay;
        if (mode === 'function') {
          payload = JSON.stringify(result.args || []);
          payloadDisplay = payload;
        } else {
          payload = JSON.stringify(result.inputs || {});
          // Build input values display: [INPUT_01 = 123] instead of just ["INPUT_01"]
          if (result.values && result.inputs) {
            const inputPairs = (result.inputs || []).map(inputName => {
              const value = result.values[inputName];
              return `[${inputName} = ${JSON.stringify(value)}]`;
            }).join(', ');
            payloadDisplay = inputPairs;
          } else {
            payloadDisplay = payload;
          }
        }
        
        const expected = JSON.stringify(result.expected ?? null);
        const actual = JSON.stringify(result.actual ?? null);
        const shortened = payloadDisplay.length > 120 ? `${payloadDisplay.slice(0, 120)}...` : payloadDisplay;
        const expectedShort = expected.length > 120 ? `${expected.slice(0, 120)}...` : expected;
        const actualShort = actual.length > 120 ? `${actual.slice(0, 120)}...` : actual;
        
        const passed = result.passed;
        const statusIcon = passed ? '✓' : '✗';
        const statusColor = passed ? '#10b981' : '#ef4444';
        
        html += `<div style="font-size:11px; margin-top:4px;">`;
        html += `<span style="color:${statusColor}; font-weight:700;">${statusIcon}</span> `;
        html += `#${itemIdx + 1}: <code style="background:#eef2ff; padding:1px 4px;">${escapeHtml(shortened)}</code>`;
        html += ` &rarr; <code style="background:#e5e7eb; padding:1px 4px;">${escapeHtml(actualShort)}</code>`;
        html += `<br>`;
        html += `<span style="margin-left:20px; color:#6b7280;">Erwartet: <code style="background:#fef3c7; padding:1px 4px;">${escapeHtml(expectedShort)}</code></span>`;
        html += `</div>`;
      });
    } else if (type === 'code_check') {
      // Show all code_check tests with their feedback
      html += `<div style="font-size:12px; color:#555; margin-top:4px;">`;
      items.forEach((item, itemIdx) => {
        const feedbackText = item.result.feedback || 'Code-Check';
        const statusIcon = item.result.passed ? '✓' : '✗';
        const statusColor = item.result.passed ? '#10b981' : '#ef4444';
        html += `<div style="color:${statusColor}; margin-top:${itemIdx > 0 ? '6px' : '0'};"><strong>${statusIcon}</strong> ${escapeHtml(feedbackText)}</div>`;
      });
      html += `</div>`;
      html += `</div>`;
    }
    
    // Show errors if any
    const errors = items.filter(item => item.result.error);
    if (errors.length > 0) {
      html += `<div style="font-size:11px; color:#c00; margin-top:6px; font-style:italic;">`;
      html += `⚠️ ${errors.length} Test(s) mit Fehler`;
      html += `</div>`;
    }
    
    html += `</div>`;
  });
  
  html += `</div>`;
  
  outputEl.innerHTML = html;
}

/**
 * Convert wildcard pattern to regex
 * * = any characters (including newlines), ? = single character
 * Example: "test*" -> /^test.*$/is, "te?t" -> /^te.t$/is
 * Supports multiline output
 */
function patternToRegex(pattern) {
  // Escape all regex special characters except * and ?
  const escaped = pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&');
  // Replace wildcards with regex equivalents
  const regex = escaped
    .replace(/\*/g, '.*')      // * = any characters (including newlines with 's' flag)
    .replace(/\?/g, '.');      // ? = single character
  return new RegExp(`^${regex}$`, 'is'); // 's' flag enables dotAll (. matches newlines)
}

/**
 * Check if actual output matches a pattern (with wildcard support)
 */
function matchesPattern(actual, pattern) {
  try {
    const regex = patternToRegex(pattern);
    return regex.test(actual);
  } catch {
    // Fallback to exact match if regex fails
    return actual === pattern;
  }
}

/**
 * Remove Python comments from code
 * Preserves string literals that might contain # or quotes
 */
function removePythonComments(code) {
  const lines = code.split('\n');
  return lines.map(line => {
    // Find the first # that's not inside a string
    let inSingleQuote = false;
    let inDoubleQuote = false;
    let inTripleQuote = false;
    let tripleQuoteChar = null;
    
    for (let i = 0; i < line.length; i++) {
      const char = line[i];
      const prev = i > 0 ? line[i - 1] : '';
      const next = i < line.length - 1 ? line[i + 1] : '';
      const nextNext = i < line.length - 2 ? line[i + 2] : '';
      
      // Check for triple quotes
      if ((char === '"' || char === "'") && next === char && nextNext === char) {
        if (inTripleQuote && tripleQuoteChar === char) {
          inTripleQuote = false;
          tripleQuoteChar = null;
          i += 2; // Skip the next two quotes
          continue;
        } else if (!inSingleQuote && !inDoubleQuote && !inTripleQuote) {
          inTripleQuote = true;
          tripleQuoteChar = char;
          i += 2; // Skip the next two quotes
          continue;
        }
      }
      
      // Check for regular quotes
      if (!inTripleQuote) {
        if (char === '"' && prev !== '\\') {
          inDoubleQuote = !inDoubleQuote;
        } else if (char === "'" && prev !== '\\') {
          inSingleQuote = !inSingleQuote;
        }
      }
      
      // Found a comment marker outside of strings
      if (char === '#' && !inSingleQuote && !inDoubleQuote && !inTripleQuote) {
        return line.substring(0, i);
      }
    }
    
    return line;
  }).join('\n');
}

/**
 * Run Code Check tests
 * Checks if code contains required keywords
 * Supports multiple code_check test cases
 */
function runCodeCheck(code, testCases) {
  const results = [];
  
  // Remove comments from code before checking
  const codeWithoutComments = removePythonComments(code);
  
  // Process each code_check test case
  testCases.forEach((testSpec, idx) => {
    if (!testSpec || testSpec.type !== 'code_check') return;
    
    const keywords = testSpec.keywords || [];
    const operator = testSpec.operator || 'AND';
    const feedback = testSpec.feedback || '';
    
    // Check each keyword (supports regex patterns)
    const keywordResults = keywords.map(keyword => {
      let found = false;
      
      // Check if keyword looks like a regex pattern (contains regex special chars)
      const hasRegexChars = /[.*+?^${}()|[\]\\]/.test(keyword);
      
      if (hasRegexChars) {
        // Treat as regex pattern (case-insensitive)
        try {
          const regex = new RegExp(keyword, 'i');
          found = regex.test(codeWithoutComments);
        } catch (e) {
          // If regex is invalid, fall back to literal string search
          found = codeWithoutComments.toUpperCase().includes(keyword.toUpperCase());
        }
      } else {
        // Literal string search (case-insensitive)
        found = codeWithoutComments.toUpperCase().includes(keyword.toUpperCase());
      }
      
      return { keyword, found };
    });
    
    // Determine pass/fail based on operator
    let passed;
    if (operator === 'AND') {
      // ALL keywords must be found
      passed = keywordResults.every(kr => kr.found);
    } else if (operator === 'OR') {
      // OR: at least ONE keyword must be found
      passed = keywordResults.length === 0 || keywordResults.some(kr => kr.found);
    } else if (operator === 'NOT') {
      // NOT: ALL keywords must be ABSENT (none found)
      passed = keywordResults.every(kr => !kr.found);
    } else {
      passed = false;
    }
    
    results.push({
      passed,
      type: 'code_check',
      keywords: keywords,
      operator,
      keywordResults,
      feedback: feedback || `Keywords ${operator === 'AND' ? '(all required)' : operator === 'OR' ? '(at least one)' : '(forbidden)'}: ${keywords.join(', ')}`
    });
  });
  
  return results;
}

/**
 * Compare test output with expected value(s)
 * Supports both single expected values and arrays (OR logic)
 * Supports wildcard patterns: * (any chars) and ? (single char)
 */
function compareTestOutput(actual, expected, mode = 'loose', caseSensitive = false) {
  // Convert to string, handling 0 and false properly (not like || which treats 0 as falsy)
  let actualCleaned = String(actual !== null && actual !== undefined ? actual : '').trim();
  let expectedCleaned;
  
  // Helper function to check if string represents a number
  const isNumericString = (str) => {
    if (str === '' || str === 'NaN' || str === 'Infinity' || str === '-Infinity') return false;
    const num = Number(str);
    return !isNaN(num) && isFinite(num);
  };
  
  // Helper function for numeric comparison with tolerance
  const numericEqual = (actualNum, expectedNum) => {
    // Exact match for integers
    if (Number.isInteger(actualNum) && Number.isInteger(expectedNum)) {
      return actualNum === expectedNum;
    }
    
    // For floats: use relative tolerance + absolute tolerance
    // Relative tolerance: 1e-9 of the expected value
    // Absolute tolerance: 1e-9 (handles very small numbers)
    const relTolerance = Math.abs(expectedNum) * 1e-9;
    const absTolerance = 1e-9;
    const tolerance = Math.max(relTolerance, absTolerance);
    
    return Math.abs(actualNum - expectedNum) <= tolerance;
  };
  
  // Handle array of expected values (OR logic - any match passes)
  if (Array.isArray(expected)) {
    return expected.some(exp => {
      expectedCleaned = String(exp !== null && exp !== undefined ? exp : '').trim();
      
      if (mode === 'contains') {
        const actualCmp = caseSensitive ? actualCleaned : actualCleaned.toLowerCase();
        const expectedCmp = caseSensitive ? expectedCleaned : expectedCleaned.toLowerCase();
        return actualCmp.includes(expectedCmp);
      }
      
      // Check if both values are numeric (handles 19.0 vs 19 case)
      if (isNumericString(actualCleaned) && isNumericString(expectedCleaned)) {
        const actualNum = Number(actualCleaned);
        const expectedNum = Number(expectedCleaned);
        return numericEqual(actualNum, expectedNum);
      }
      
      // Case-insensitive comparison for booleans (True/true, False/false)
      if ((actualCleaned.toLowerCase() === 'true' || actualCleaned.toLowerCase() === 'false') &&
          (expectedCleaned.toLowerCase() === 'true' || expectedCleaned.toLowerCase() === 'false')) {
        return actualCleaned.toLowerCase() === expectedCleaned.toLowerCase();
      }
      
      // Check for wildcard pattern (* or ?)
      if (expectedCleaned.includes('*') || expectedCleaned.includes('?')) {
        return matchesPattern(actualCleaned, expectedCleaned);
      }
      
      if (mode === 'loose') {
        const actualLoose = actualCleaned.replace(/\s+/g, ' ');
        const expectedLoose = expectedCleaned.replace(/\s+/g, ' ');
        return actualLoose === expectedLoose;
      }
      
      return actualCleaned === expectedCleaned;
    });
  }
  
  // Single expected value
  expectedCleaned = String(expected !== null && expected !== undefined ? expected : '').trim();
  
  if (mode === 'contains') {
    const actualCmp = caseSensitive ? actualCleaned : actualCleaned.toLowerCase();
    const expectedCmp = caseSensitive ? expectedCleaned : expectedCleaned.toLowerCase();
    return actualCmp.includes(expectedCmp);
  }
  
  // Check if both values are numeric (handles 19.0 vs 19 case)
  if (isNumericString(actualCleaned) && isNumericString(expectedCleaned)) {
    const actualNum = Number(actualCleaned);
    const expectedNum = Number(expectedCleaned);
    return numericEqual(actualNum, expectedNum);
  }
  
  // Case-insensitive comparison for booleans (True/true, False/false)
  if ((actualCleaned.toLowerCase() === 'true' || actualCleaned.toLowerCase() === 'false') &&
      (expectedCleaned.toLowerCase() === 'true' || expectedCleaned.toLowerCase() === 'false')) {
    return actualCleaned.toLowerCase() === expectedCleaned.toLowerCase();
  }
  
  // Check for wildcard pattern (* or ?)
  if (expectedCleaned.includes('*') || expectedCleaned.includes('?')) {
    return matchesPattern(actualCleaned, expectedCleaned);
  }
  
  if (mode === 'loose') {
    const actualLoose = actualCleaned.replace(/\s+/g, ' ');
    const expectedLoose = expectedCleaned.replace(/\s+/g, ' ');
    return actualLoose === expectedLoose;
  }
  
  return actualCleaned === expectedCleaned;
}

/**
 * Process validation result and update task status
 */
async function processValidationResult(result, task, outputEl, isSubmission = false) {
  // Only commit status changes and lock editor if this is a submission
  if (!isSubmission) {
    // CHECK flow: Just show feedback, don't change status or attempts
    console.log('[CHECK] Showing feedback only - status and attempts unchanged');
    return;
  }

  // SUBMISSION flow: Commit final status and lock editor
  // NOTE: Attempts are already incremented in checkTask(), don't increment again here
  
  // Get current attempts (already incremented)
  const currentAttempts = assignmentState.taskAttempts[task.id] || 0;
  const maxAttempts = task.max_attempts || 10;

  if (result.passed === true) {
    // All tests passed - GREEN
    assignmentState.taskStatuses[task.id] = 'passed';
    
    // FREEZE time tracking - submission complete
    stopActivityTracking(task.id);
    
    // Store completion timestamp
    const now = new Date();
    assignmentState.taskCompletedAt[task.id] = now.toISOString().slice(0, 19).replace('T', ' ');

    // Lock editor after successful submission
    const editor = window.editorInstance;
    if (editor) {
      editor.updateOptions({ readOnly: true });
    }

    // Hide check and submit buttons after successful submission
    const checkBtn = $('check-btn');
    const submitBtn = $('submit-btn');
    if (checkBtn) checkBtn.style.display = 'none';
    if (submitBtn) submitBtn.style.display = 'none';
    
    // Hide save, undo, redo, attempts counter (keep download visible)
    const saveTaskBtn = $('save-task-btn');
    const undoBtn = $('undo-btn');
    const redoBtn = $('redo-btn');
    const attemptsCounter = $('attempts-counter');
    if (saveTaskBtn) saveTaskBtn.style.display = 'none';
    if (undoBtn) undoBtn.style.display = 'none';
    if (redoBtn) redoBtn.style.display = 'none';
    if (attemptsCounter) attemptsCounter.style.display = 'none';
    
    // Show submitted info
    const submittedInfo = $('submitted-info');
    const submittedStatus = $('submitted-status');
    const submittedDate = $('submitted-date');
    if (submittedInfo && submittedStatus && submittedDate) {
      submittedInfo.classList.add('show');
      submittedStatus.className = 'status-passed';
      const formatted = now.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      submittedDate.textContent = formatted;

      updateSubmittedMeta(task);
    }

    // Show success modal with stats
    showSuccessModal(task, currentAttempts, maxAttempts);
  } else {
    // Submission failed - RED (final, regardless of attempts)
    assignmentState.taskStatuses[task.id] = 'failed';
    
    // FREEZE time tracking - submission complete (failed)
    stopActivityTracking(task.id);
    
    // Store completion timestamp
    const now = new Date();
    assignmentState.taskCompletedAt[task.id] = now.toISOString().slice(0, 19).replace('T', ' ');

    // Lock editor after final failed submission
    const editor = window.editorInstance;
    if (editor) {
      editor.updateOptions({ readOnly: true });
    }

    // Hide check and submit buttons after final failed submission
    const checkBtn = $('check-btn');
    const submitBtn = $('submit-btn');
    if (checkBtn) checkBtn.style.display = 'none';
    if (submitBtn) submitBtn.style.display = 'none';
    
    // Hide save, undo, redo, attempts counter (keep download visible)
    const saveTaskBtn = $('save-task-btn');
    const undoBtn = $('undo-btn');
    const redoBtn = $('redo-btn');
    const attemptsCounter = $('attempts-counter');
    if (saveTaskBtn) saveTaskBtn.style.display = 'none';
    if (undoBtn) undoBtn.style.display = 'none';
    if (redoBtn) redoBtn.style.display = 'none';
    if (attemptsCounter) attemptsCounter.style.display = 'none';
    
    // Show submitted info
    const submittedInfo = $('submitted-info');
    const submittedStatus = $('submitted-status');
    const submittedDate = $('submitted-date');
    if (submittedInfo && submittedStatus && submittedDate) {
      submittedInfo.classList.add('show');
      submittedStatus.className = 'status-failed';
      const formatted = now.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      submittedDate.textContent = formatted;

      updateSubmittedMeta(task);
    }
  }

  // Save attempts and status to database (for all status changes)
  try {
    const editor = window.editorInstance;
    const code = editor ? editor.getValue() : '';
    
    console.log('[CHECK] Saving after validation - Status:', assignmentState.taskStatuses[task.id], 'Attempts:', currentAttempts);
    
    const savePayload = {
      task_id: task.id,
      status: assignmentState.taskStatuses[task.id],
      attempts: currentAttempts,
      current_code: code,
      hints_revealed: assignmentState.hintsRevealed[task.id] || []
    };
    
    // Add completed_at if task is finalized
    if (isSubmission && assignmentState.taskCompletedAt[task.id]) {
      savePayload.completed_at = assignmentState.taskCompletedAt[task.id];
    }
    
    console.log('[CHECK] Save payload:', savePayload);
    
    const saveResponse = await requestJson('../api/user_tasks/update.php', {
      method: 'POST',
      body: JSON.stringify(savePayload)
    });
    
    console.log('[CHECK] Database save response:', saveResponse);
  } catch (err) {
    console.error('[CHECK] Failed to save task progress:', err);
  }

  // Update task status display
  updateTaskStatusDisplay(task);
}

function updateTaskStatusDisplay(task) {
  const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';

  // Update status in task navigation
  renderTaskNavigation();
}

function bindAssignmentsEvents() {
  const checkBtn = $('check-btn');
  const submitBtn = $('submit-btn');
  const backToListBtn = $('back-to-list-btn');

  // Back to list button
  backToListBtn?.addEventListener('click', () => {
    backToAssignmentList();
  });

  // Check button
  checkBtn?.addEventListener('click', () => {
    checkTask();
  });

  // Submit button
  submitBtn?.addEventListener('click', () => {
    submitTask();
  });

  // Save task button - Only bind for non-task cases (when task is not loaded)
  // When a task is loaded, loadTaskIntoEditor will override with saveCode()
  const saveTaskBtn = $('save-task-btn');
  if (saveTaskBtn && !saveTaskBtn.onclick) {
    saveTaskBtn.addEventListener('click', () => {
      saveCode();
    });
  }

  // Download button
  const downloadBtn = $('download-btn');
  downloadBtn?.addEventListener('click', () => {
    downloadCode();
  });

  // Share button - Generate admin test link
  const shareBtn = $('share-btn');
  shareBtn?.addEventListener('click', async () => {
    const assignmentId = window.assignmentState?.currentAssignmentId;
    const taskId = window.assignmentState?.currentTask?.id;
    const userId = window.userId; // Should be set from PHP session
    
    if (!assignmentId || !taskId) {
      alert('Keine Aufgabe geladen. Bitte öffnen Sie eine Aufgabe.');
      return;
    }
    
    if (!userId) {
      alert('User-ID nicht verfügbar.');
      return;
    }
    
    const baseUrl = window.location.origin + window.location.pathname.replace(/[^/]*$/, '');
    const shareUrl = `${baseUrl}editor_assignment_test.php?assignment_id=${assignmentId}&task_id=${taskId}&test_user_id=${userId}`;
    
    try {
      await navigator.clipboard.writeText(shareUrl);
      
      // Visual feedback
      const originalTitle = shareBtn.title;
      const originalText = shareBtn.textContent;
      shareBtn.textContent = '✓';
      shareBtn.title = 'Link kopiert!';
      shareBtn.style.background = '#10b981';
      shareBtn.style.color = '#fff';
      
      setTimeout(() => {
        shareBtn.textContent = originalText;
        shareBtn.title = originalTitle;
        shareBtn.style.background = '';
        shareBtn.style.color = '';
      }, 2000);
    } catch (err) {
      console.error('Failed to copy link:', err);
      // Fallback: Show link in prompt
      prompt('Link kopieren (Strg+C):', shareUrl);
    }
  });

  // Undo button
  const undoBtn = $('undo-btn');
  undoBtn?.addEventListener('click', () => {
    const editor = window.editorInstance;
    if (editor) {
      editor.trigger('', 'undo');
    }
  });

  // Redo button
  const redoBtn = $('redo-btn');
  redoBtn?.addEventListener('click', () => {
    const editor = window.editorInstance;
    if (editor) {
      editor.trigger('', 'redo');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindAssignmentsEvents();

  // Bind modal button events
  const okBtn = $('success-ok-btn');
  if (okBtn) {
    okBtn.addEventListener('click', () => {
      closeSuccessModal();
      // Focus back on editor
      const editor = window.editorInstance;
      if (editor) {
        editor.focus();
      }
    });
  }

  const nextTaskBtn = $('success-next-task-btn');
  if (nextTaskBtn) {
    nextTaskBtn.addEventListener('click', () => {
      closeSuccessModal();
      const nextTask = getNextTaskInAssignment();
      if (nextTask) {
        loadTaskIntoEditor(assignmentState.currentAssignmentId, nextTask.id);
        renderAssignments();
      }
    });
  }

  const nextAssignmentBtn = $('success-next-assignment-btn');
  if (nextAssignmentBtn) {
    nextAssignmentBtn.addEventListener('click', () => {
      closeSuccessModal();
      const nextAssignment = getNextAssignment();
      if (nextAssignment) {
        assignmentState.currentAssignmentId = nextAssignment.assignment_id;
        // Get first task of next assignment
        const tasks = assignmentState.tasksByAssignment[nextAssignment.assignment_id] || [];
        if (tasks.length > 0) {
          const firstTask = tasks[0];
          loadTaskIntoEditor(nextAssignment.assignment_id, firstTask.id);
          renderAssignments();
        }
      }
    });
  }
  // Auto-load assignments if on assignments.php or assignment_editor.php or editor_assignment_test.php
  if (window.location.pathname.includes('assignments.php') || window.location.pathname.includes('assignment_editor') || window.location.pathname.includes('editor_assignment_test')) {
    console.log('On assignments page - loading assignments');
    
    // If in editor mode with assignment ID, load only that assignment (optimized)
    if (window.EDITOR_MODE && window.ASSIGNMENT_ID) {
      console.log('Editor mode detected - loading only assignment', window.ASSIGNMENT_ID);
      loadSingleAssignment(window.ASSIGNMENT_ID).then(() => {
        openAssignmentEditor(window.ASSIGNMENT_ID);
      });
    } else {
      // Load all assignments (for list view)
      loadAssignments().then(() => {
        if (window.EDITOR_MODE && window.ASSIGNMENT_ID) {
          console.log('Editor mode detected - loading assignment', window.ASSIGNMENT_ID);
          openAssignmentEditor(window.ASSIGNMENT_ID);
        }
      });
    }
  }

  // Expose functions to window for external access (e.g., from quiz-renderer.js)
  window.showSuccessModal = showSuccessModal;
  window.closeSuccessModal = closeSuccessModal;
  window.renderTaskNavigation = renderTaskNavigation;
});

// Load and display task folder files
async function loadAndDisplayTaskFiles(panelId, taskId, currentPath = '') {
  const panel = document.getElementById(panelId);
  if (!panel) return;

  const isAdminFolderMode = window.testMode === true;
  const testUserParam = window.TEST_USER_ID ? `&test_user_id=${window.TEST_USER_ID}` : '';
  const listEndpoint = isAdminFolderMode
    ? `/pythonIDE/api/tasks/get-folder-files.php?task_id=${taskId}`
    : `/pythonIDE/api/user_tasks/folder-files.php?action=list&task_id=${taskId}${testUserParam}`;
  
  try {
    const response = await requestJson(listEndpoint, {
      method: 'GET'
    });
    
    if (!response.ok) {
      panel.innerHTML = `<div style="color: var(--text-secondary); padding: 4px 8px; font-size: 12px;">Keine Dateien vorhanden</div>`;
      return;
    }
    
    const allFiles = response.files || [];
    const allTasks = Object.values(assignmentState.tasksByAssignment || {}).flat();
    const currentTaskMeta = allTasks.find(t => Number(t.id) === Number(taskId));
    const isCodeUiTask = currentTaskMeta?.task_type === 'code_ui';

    const fileMetaMap = {};
    const collectFileMeta = (items) => {
      (items || []).forEach((item) => {
        if (!item) return;
        if (item.type === 'file' && item.path) {
          fileMetaMap[item.path] = item;
        }
        if (item.type === 'folder' && Array.isArray(item.children)) {
          collectFileMeta(item.children);
        }
      });
    };
    collectFileMeta(allFiles);
    assignmentState.taskFileMeta[String(taskId)] = fileMetaMap;
    
    // Trenne init.py (virtuell) von echten Filesystem-Dateien
    const initPy = allFiles.find(f => f.name === 'init.py' && f.virtual);
    const filesystemFiles = allFiles.filter(f => !f.virtual);
    
    // Store init.py content in window for openTaskFileInEditor to access
    if (initPy) {
      window.taskInitPyContent = window.taskInitPyContent || {};
      window.taskInitPyContent[taskId] = initPy.content || '';
      setTaskSavedSnapshot(taskId, 'init.py', initPy.content || '');
      if (getTaskDraftContent(taskId, 'init.py') === null) {
        setTaskDraftContent(taskId, 'init.py', initPy.content || '');
      }
    }
    
    // Bilde aktuellen Ordner-Inhalt ab
    let currentFiles = filesystemFiles;
    if (currentPath) {
      currentFiles = findFolderContents(filesystemFiles, currentPath);
      if (!currentFiles) {
        currentFiles = filesystemFiles;
        currentPath = '';
      }
    }
    
    const disabledStyle = 'opacity: 0.45; cursor: not-allowed;';
    const createFolderBtnAttrs = isAdminFolderMode
      ? `onclick="createTaskFolder(${taskId}, '${currentPath}')" title="Neuer Ordner"`
      : `title="Nur im Testmodus" disabled style="padding: 2px 6px; font-size: 11px; ${disabledStyle}"`;
    const createFileBtnAttrs = isAdminFolderMode
      ? `onclick="createTaskFile(${taskId}, '${currentPath}')" title="Neue Datei"`
      : `title="Nur im Testmodus" disabled style="padding: 2px 6px; font-size: 11px; ${disabledStyle}"`;
    const uploadBtnAttrs = isAdminFolderMode
      ? `onclick="uploadTaskFile(${taskId}, '${currentPath}')" title="Datei hochladen"`
      : `title="Nur im Testmodus" disabled style="padding: 2px 6px; font-size: 11px; ${disabledStyle}"`;
    const resetCodeUiBtnAttrs = (!isAdminFolderMode && isCodeUiTask)
      ? `onclick="resetCodeUiTemplate(${taskId}, '${currentPath}')" title="Code-UI Template zurücksetzen" style="padding: 2px 6px; font-size: 11px;"`
      : `style="display:none;"`;

    // Build UI with toolbar and file tree
    let html = `
      <div style="display: flex; gap: 4px; padding: 4px; border-bottom: 1px solid var(--border); background: var(--bg);">
        <button type="button" class="hspf-btn hspf-btn-sm" ${createFolderBtnAttrs} ${isAdminFolderMode ? 'style="padding: 2px 6px; font-size: 11px;"' : ''}>📁+</button>
        <button type="button" class="hspf-btn hspf-btn-sm" ${createFileBtnAttrs} ${isAdminFolderMode ? 'style="padding: 2px 6px; font-size: 11px;"' : ''}>📄+</button>
        <button type="button" class="hspf-btn hspf-btn-sm" ${uploadBtnAttrs} ${isAdminFolderMode ? 'style="padding: 2px 6px; font-size: 11px;"' : ''}>⬆️</button>
        <button type="button" class="hspf-btn hspf-btn-sm" ${resetCodeUiBtnAttrs}>♻️</button>
        <button type="button" class="hspf-btn hspf-btn-sm" onclick="openTaskFileInEditor(${taskId}, 'init.py')" style="padding: 2px 6px; font-size: 11px;" title="init.py (Hauptdatei)">🐍</button>
      </div>
    `;
    
    // Breadcrumb Navigation
    if (currentPath) {
      const pathParts = currentPath.split('/').filter(p => p);
      html += `<div style="padding: 4px 8px; font-size: 11px; border-bottom: 1px solid var(--border); background: var(--bg);">
        <span style="cursor: pointer; color: #667eea;" onclick="loadAndDisplayTaskFiles('${panelId}', ${taskId}, '')">📁 Root</span>`;
      let buildPath = '';
      pathParts.forEach((part, idx) => {
        buildPath += (buildPath ? '/' : '') + part;
        html += ` / <span style="cursor: pointer; color: #667eea;" onclick="loadAndDisplayTaskFiles('${panelId}', ${taskId}, '${buildPath}')">${part}</span>`;
      });
      html += `</div>`;
    }
    
    html += `<div id="task-file-tree-${taskId}" style="font-size: 12px; background: var(--panel);" data-current-path="${currentPath}" data-task-id="${taskId}">`;
    if (isAdminFolderMode) {
      html += `<input type="file" id="task-file-upload-${taskId}" style="display: none;" onchange="handleTaskFileUpload(${taskId}, this, '${currentPath}')" />`;
    }
    
    // Render init.py FIRST (from tree root no matter currentPath)
    if (initPy && !currentPath) {
      html += renderTaskFileItem(initPy, taskId, 0, '');
    }
    
    // Render file tree (nur echte Filesystem-Dateien im aktuellen Ordner)
    if (!currentFiles || currentFiles.length === 0) {
      html += `<div style="padding: 4px 8px; color: var(--text-secondary);">Leer</div>`;
    } else {
      currentFiles.forEach(item => {
        html += renderTaskFileItem(item, taskId, 0, currentPath);
      });
    }
    
    html += `</div>`;
    
    panel.innerHTML = html;
    
    // Add event listeners for file items
    setTimeout(() => {
      const fileItems = panel.querySelectorAll('.task-file-item');
      fileItems.forEach(item => {
        const path = item.getAttribute('data-path');
        const type = item.getAttribute('data-type');
        const isVirtual = item.getAttribute('data-virtual') === 'true';
        const isReadOnly = item.getAttribute('data-read-only') === 'true';
        
        // Single click - open file in editor or navigate folder
        item.addEventListener('click', (e) => {
          if (e.detail === 1) {
            if (type === 'folder') {
              loadAndDisplayTaskFiles(panelId, taskId, path);
            } else if (type === 'file') {
              openTaskFileInEditor(taskId, path);
            }
          }
        });
        
        // Double click - rename (nur nicht-virtuelle, nicht-readonly Dateien)
        if (isAdminFolderMode && !isVirtual && !isReadOnly) {
          item.addEventListener('dblclick', (e) => {
            const fileName = path.split('/').pop();
            const fileNameEl = item.querySelector('.file-name');
            startInlineEdit(fileNameEl, taskId, path);
          });
        }

        // Right click - context menu (nur nicht-virtuelle Dateien)
        if (isAdminFolderMode && !isVirtual) {
          item.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            showTaskFileContextMenu(e, taskId, path, type, isReadOnly);
          });
        }
      });
    }, 100);
    
  } catch (error) {
    console.error('Error loading folder files:', error);
    panel.innerHTML = `<div style="color: red; padding: 4px 8px; font-size: 12px;">Fehler beim Laden der Dateien</div>`;
  }
}

// Find folder contents by path
function findFolderContents(files, path) {
  if (!path) return files;
  
  const parts = path.split('/').filter(p => p);
  let current = files;
  
  for (let part of parts) {
    let found = false;
    for (let item of current) {
      if (item.name === part && item.type === 'folder') {
        current = item.children || [];
        found = true;
        break;
      }
    }
    if (!found) return null;
  }
  
  return current;
}

// Render individual file/folder item
function renderTaskFileItem(item, taskId, depth, currentPath = '') {
  const indent = depth * 12;
  const icon = item.type === 'folder' ? '📁' : '📄';
  const virtualBadge = item.virtual ? ' <span style="font-size: 9px; color: #667eea;">(v)</span>' : '';
  const readOnly = !!item.read_only;
  const lockBadge = readOnly ? ' <span style="font-size: 9px; color: #f59e0b;">🔒</span>' : '';
  const itemPath = currentPath ? currentPath + '/' + item.name : item.name;
  const isClickable = item.type === 'file'; // Dateien klickbar machen
  const cursorStyle = isClickable ? 'cursor: pointer;' : 'cursor: default;';
  
  let html = `
    <div class="task-file-item" 
         data-task-id="${taskId}"
         data-path="${itemPath}" 
         data-type="${item.type}"
         data-virtual="${item.virtual || false}"
         data-read-only="${readOnly}"
         style="padding: 2px 4px; padding-left: ${indent}px; user-select: none; display: flex; align-items: center; gap: 4px; font-size: 12px; line-height: 1.4; ${cursorStyle} ${item.type === 'folder' ? 'font-weight: 500;' : ''}">
      <span style="width: 16px; display: flex; justify-content: center;">${icon}</span>
      <span class="file-name">${item.name}</span>${virtualBadge}${lockBadge}
    </div>
  `;
  
  return html;
}

// Create new folder
async function createTaskFolder(taskId, parentPath = '') {
  // Find a unique name like "newfolder", "newfolder2", etc.
  const tree = document.getElementById(`task-file-tree-${taskId}`);
  const existingNames = Array.from(tree.querySelectorAll('.file-name'))
    .map(el => el.textContent)
    .filter(name => name.startsWith('newfolder'));
  
  let name = 'newfolder';
  let counter = 1;
  while (existingNames.includes(name)) {
    counter++;
    name = 'newfolder' + counter;
  }
  
  try {
    const response = await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=create_folder`, {
      method: 'POST',
      body: JSON.stringify({
        task_id: taskId,
        name: name,
        parent_path: parentPath
      })
    });
    
    if (!response.ok) throw new Error(response.error);
    
    // Reload and start editing
    const panelId = `folder-panel-content-${taskId}`;
    loadAndDisplayTaskFiles(panelId, taskId, parentPath);
    
    // Trigger edit after reload
    setTimeout(() => {
      const fileNameEl = tree.querySelector(`[data-path="${parentPath ? parentPath + '/' : ''}${name}"] .file-name`);
      if (fileNameEl) startInlineEdit(fileNameEl, taskId, parentPath ? parentPath + '/' + name : name);
    }, 100);
    
  } catch (error) {
    alert('Fehler: ' + error.message);
  }
}

// Create new file
async function createTaskFile(taskId, parentPath = '') {
  // Find a unique name like "newfile.py", "newfile2.py", etc.
  const tree = document.getElementById(`task-file-tree-${taskId}`);
  const existingNames = Array.from(tree.querySelectorAll('.file-name'))
    .map(el => el.textContent)
    .filter(name => name.startsWith('newfile'));
  
  let name = 'newfile.py';
  let counter = 1;
  while (existingNames.includes(name)) {
    counter++;
    name = 'newfile' + counter + '.py';
  }
  
  try {
    const response = await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=create_file`, {
      method: 'POST',
      body: JSON.stringify({
        task_id: taskId,
        name: name,
        parent_path: parentPath,
        content: ''
      })
    });
    
    if (!response.ok) throw new Error(response.error);
    
    // Reload and start editing
    const panelId = `folder-panel-content-${taskId}`;
    loadAndDisplayTaskFiles(panelId, taskId, parentPath);
    
    // Trigger edit after reload
    setTimeout(() => {
      const fileNameEl = tree.querySelector(`[data-path="${parentPath ? parentPath + '/' : ''}${name}"] .file-name`);
      if (fileNameEl) startInlineEdit(fileNameEl, taskId, parentPath ? parentPath + '/' + name : name);
    }, 100);
    
  } catch (error) {
    alert('Fehler: ' + error.message);
  }
}

// Start inline editing
function startInlineEdit(element, taskId, path) {
  const fileName = path.split('/').pop();
  const container = element.parentElement;
  
  const input = document.createElement('input');
  input.type = 'text';
  input.value = fileName;
  input.style.cssText = `
    flex: 1;
    padding: 0 2px;
    border: 1px solid #667eea;
    border-radius: 2px;
    font-size: 12px;
    font-family: inherit;
  `;
  
  // Replace display element with input
  element.style.display = 'none';
  container.insertBefore(input, element.nextSibling);
  input.focus();
  input.select();
  
  async function finishEdit() {
    const newName = input.value.trim();
    
    if (newName && newName !== fileName && newName !== '') {
      try {
        const response = await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=rename`, {
          method: 'POST',
          body: JSON.stringify({
            task_id: taskId,
            old_path: path,
            new_name: newName
          })
        });
        
        if (!response.ok) throw new Error(response.error);
        
        // Reload
        const panelId = `folder-panel-content-${taskId}`;
        loadAndDisplayTaskFiles(panelId, taskId);
      } catch (error) {
        alert('Fehler beim Umbenennen: ' + error.message);
        element.style.display = '';
        input.remove();
      }
    } else {
      element.style.display = '';
      input.remove();
    }
  }
  
  input.addEventListener('blur', finishEdit);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') finishEdit();
    if (e.key === 'Escape') {
      element.style.display = '';
      input.remove();
    }
  });
}

// Upload file
function uploadTaskFile(taskId, parentPath = '') {
  document.getElementById(`task-file-upload-${taskId}`).click();
}

async function handleTaskFileUpload(taskId, input, parentPath = '') {
  if (!input.files || input.files.length === 0) return;
  
  const formData = new FormData();
  formData.append('file', input.files[0]);
  formData.append('task_id', taskId);
  formData.append('parent_path', parentPath);
  
  try {
    const response = await fetch(`/pythonIDE/api/tasks/folder-manage.php?action=upload`, {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (!result.ok) {
      throw new Error(result.error || 'Upload failed');
    }
    
    // Reload files
    const panelId = `folder-panel-content-${taskId}`;
    loadAndDisplayTaskFiles(panelId, taskId, parentPath);
    
    // Reset input
    input.value = '';
  } catch (error) {
    alert('Fehler beim Hochladen: ' + error.message);
  }
}

// Context menu
function showTaskFileContextMenu(event, taskId, path, type, isReadOnly = false) {
  // Remove existing context menu
  const existingMenu = document.getElementById('task-file-context-menu');
  if (existingMenu) {
    existingMenu.remove();
  }
  
  // Create context menu
  const menu = document.createElement('div');
  menu.id = 'task-file-context-menu';
  menu.style.cssText = `
    position: fixed;
    left: ${event.clientX}px;
    top: ${event.clientY}px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 3px;
    padding: 2px 0;
    z-index: 10000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    font-size: 12px;
  `;
  
  const fileName = path.split('/').pop();
  
  // Umbenennen
  const renameItem = document.createElement('div');
  renameItem.style.cssText = 'padding: 4px 12px; cursor: pointer; white-space: nowrap;';
  renameItem.textContent = '✏️ Umbenennen';
  renameItem.addEventListener('mouseover', () => renameItem.style.background = 'var(--bg)');
  renameItem.addEventListener('mouseout', () => renameItem.style.background = 'transparent');
  renameItem.addEventListener('click', () => {
    const fileNameEl = document.querySelector(`.task-file-item[data-path="${path}"] .file-name`);
    if (fileNameEl) {
      startInlineEdit(fileNameEl, taskId, path);
    }
    menu.remove();
  });
  menu.appendChild(renameItem);
  
  // Duplizieren
  const dupItem = document.createElement('div');
  dupItem.style.cssText = 'padding: 4px 12px; cursor: pointer; white-space: nowrap;';
  dupItem.textContent = '📋 Duplizieren';
  dupItem.addEventListener('mouseover', () => dupItem.style.background = 'var(--bg)');
  dupItem.addEventListener('mouseout', () => dupItem.style.background = 'transparent');
  dupItem.addEventListener('click', () => {
    duplicateTaskItem(taskId, path);
    menu.remove();
  });
  menu.appendChild(dupItem);

  if (type === 'file') {
    const roItem = document.createElement('div');
    roItem.style.cssText = 'padding: 4px 12px; cursor: pointer; white-space: nowrap;';
    roItem.textContent = isReadOnly ? '🔓 Schreibschutz deaktivieren' : '🔒 Schreibschutz aktivieren';
    roItem.addEventListener('mouseover', () => roItem.style.background = 'var(--bg)');
    roItem.addEventListener('mouseout', () => roItem.style.background = 'transparent');
    roItem.addEventListener('click', async () => {
      await toggleTaskFileReadonly(taskId, path, !isReadOnly);
      menu.remove();
    });
    menu.appendChild(roItem);
  }
  
  // Herunterladen (nur für Dateien)
  if (type === 'file') {
    const downItem = document.createElement('div');
    downItem.style.cssText = 'padding: 4px 12px; cursor: pointer; white-space: nowrap;';
    downItem.textContent = '⬇️ Herunterladen';
    downItem.addEventListener('mouseover', () => downItem.style.background = 'var(--bg)');
    downItem.addEventListener('mouseout', () => downItem.style.background = 'transparent');
    downItem.addEventListener('click', () => {
      downloadTaskFile(taskId, path);
      menu.remove();
    });
    menu.appendChild(downItem);
  }
  
  // Löschen
  const delItem = document.createElement('div');
  delItem.style.cssText = 'padding: 4px 12px; cursor: pointer; white-space: nowrap; color: red;';
  delItem.textContent = '🗑️ Löschen';
  delItem.addEventListener('mouseover', () => delItem.style.background = 'var(--bg)');
  delItem.addEventListener('mouseout', () => delItem.style.background = 'transparent');
  delItem.addEventListener('click', () => {
    deleteTaskItem(taskId, path);
    menu.remove();
  });
  menu.appendChild(delItem);
  
  document.body.appendChild(menu);
  
  // Close on click outside
  setTimeout(() => {
    document.addEventListener('click', function closeMenu(e) {
      if (!menu.contains(e.target)) {
        menu.remove();
        document.removeEventListener('click', closeMenu);
      }
    });
  }, 100);
}

async function toggleTaskFileReadonly(taskId, path, readOnly) {
  try {
    const response = await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=set_readonly`, {
      method: 'POST',
      body: JSON.stringify({
        task_id: taskId,
        path,
        read_only: readOnly ? 1 : 0
      })
    });

    if (!response.ok) {
      throw new Error(response.error || 'Readonly-Status konnte nicht gesetzt werden');
    }

    const panelId = `folder-panel-content-${taskId}`;
    const tree = document.getElementById(`task-file-tree-${taskId}`);
    const currentPath = tree ? tree.getAttribute('data-current-path') || '' : '';
    loadAndDisplayTaskFiles(panelId, taskId, currentPath);

    if (window.currentFile && Number(window.currentFile.taskId) === Number(taskId) && String(window.currentFile.path) === String(path)) {
      await openTaskFileInEditor(taskId, path);
    }
  } catch (error) {
    alert('Fehler beim Setzen des Schreibschutzes: ' + error.message);
  }
}

// Open task file in editor
async function openTaskFileInEditor(taskId, path) {
  try {
    cacheCurrentEditorDraft();

    let fileName = path.split('/').pop();
    let language = 'plaintext';
    let content = '';
    
    // Special handling for init.py (virtual file from user_tasks.current_code)
    if (path === 'init.py') {
      const draftContent = getTaskDraftContent(taskId, 'init.py');
      if (draftContent !== null) {
        content = draftContent;
      } else if (window.taskInitPyContent && Object.prototype.hasOwnProperty.call(window.taskInitPyContent, taskId)) {
        content = window.taskInitPyContent[taskId] || '';
        setTaskSavedSnapshot(taskId, 'init.py', content);
      }
      
      // Dispose old model
      if (window.editorInstance && window.editorInstance.getModel()) {
        window.editorInstance.getModel().dispose();
      }
      
      language = 'python';
      
      if (window.editorInstance && window.monaco) {
        const editorModel = window.monaco.editor.createModel(content, language, window.monaco.Uri.parse(`task://task${taskId}/init.py`));
        window.editorInstance.setModel(editorModel);
        window.editorInstance.updateOptions({ readOnly: false });
        window.currentFile = { taskId, path: 'init.py', fileName: 'init.py', isVirtual: true, readOnly: false };
        setTaskDraftContent(taskId, 'init.py', content);
        
        const title = document.querySelector('.editor-title');
        if (title) {
          title.textContent = 'init.py (Hauptdatei)';
        }
      }
      return;
    }
    
    const isAdminFolderMode = window.testMode === true;
    const testUserParam = window.TEST_USER_ID ? `&test_user_id=${window.TEST_USER_ID}` : '';
    const readEndpoint = isAdminFolderMode
      ? `/pythonIDE/api/tasks/folder-manage.php?action=read&task_id=${taskId}&path=${encodeURIComponent(path)}`
      : `/pythonIDE/api/user_tasks/folder-files.php?action=read&task_id=${taskId}&path=${encodeURIComponent(path)}${testUserParam}`;

    const draftContent = getTaskDraftContent(taskId, path);
    if (draftContent !== null) {
      content = draftContent;
    } else {
      // Real file handling
      const response = await fetch(readEndpoint);
      
      if (!response.ok) {
        throw new Error('Datei nicht gefunden: ' + path);
      }
      
      const result = await response.json();
      content = result.content || '';
      setTaskSavedSnapshot(taskId, path, content);
    }
    
    // Language detection
    const ext = fileName.split('.').pop() || 'txt';
    const languageMap = {
      'py': 'python',
      'js': 'javascript',
      'ts': 'typescript',
      'java': 'java',
      'cpp': 'cpp',
      'c': 'c',
      'cs': 'csharp',
      'php': 'php',
      'rb': 'ruby',
      'html': 'html',
      'css': 'css',
      'json': 'json',
      'xml': 'xml',
      'yaml': 'yaml',
      'yml': 'yaml',
      'txt': 'plaintext'
    };
    
    language = languageMap[ext.toLowerCase()] || 'plaintext';
    
    // Dispose old model
    if (window.editorInstance && window.editorInstance.getModel()) {
      window.editorInstance.getModel().dispose();
    }
    
    // Set editor content
    if (window.editorInstance && window.monaco) {
      const editorModel = window.monaco.editor.createModel(content, language, window.monaco.Uri.parse(`task://task${taskId}/${path}`));
      window.editorInstance.setModel(editorModel);

      const taskMeta = assignmentState.taskFileMeta[String(taskId)] || {};
      const fileMeta = taskMeta[path] || {};
      const readOnly = !!fileMeta.read_only;
      window.editorInstance.updateOptions({ readOnly });

      window.currentFile = { taskId, path, fileName, readOnly };
      setTaskDraftContent(taskId, path, content);
      
      const title = document.querySelector('.editor-title');
      if (title) {
        title.textContent = readOnly ? `${fileName} (readonly)` : fileName;
      }
    } else {
      console.warn('Editor nicht initialisiert');
    }
  } catch (error) {
    console.error('Fehler beim Öffnen der Datei:', error);
    alert('Fehler beim Öffnen der Datei: ' + error.message);
  }
}

// Save current file in editor
// silent: true = no alerts or visual feedback (for auto-save)
async function saveTaskFile(silent = false) {
  // In test mode, skip API persistence (keep changes in DOM only)
  if (window.TEST_MODE_NO_PERSIST === true) {
    if (window.currentFile && window.editorInstance) {
      const { taskId, path } = window.currentFile;
      const content = window.editorInstance.getValue();
      setTaskDraftContent(taskId, path, content);
      setTaskSavedSnapshot(taskId, path, content);
    }
    return true;
  }

  if (!window.currentFile) {
    if (!silent) alert('Keine Datei zum Speichern geöffnet');
    return false;
  }

  const { taskId, path, fileName, isVirtual, readOnly } = window.currentFile;

  if (readOnly) {
    if (!silent) alert('Diese Datei ist schreibgeschützt.');
    return false;
  }

  // Get editor content
  if (!window.editorInstance) {
    if (!silent) alert('Editor nicht initialisiert');
    return false;
  }

  const content = window.editorInstance.getValue();
  setTaskDraftContent(taskId, path, content);

  try {
    await persistTaskFileContent(taskId, path, content, !!isVirtual);

    if (!silent) {
      console.log('✅ Datei gespeichert:', fileName);
    }
    
    // Add visual feedback only if not silent
    if (!silent) {
      const title = document.querySelector('.editor-title');
      if (title) {
        const original = title.textContent;
        title.textContent = '✅ ' + original;
        setTimeout(() => {
          title.textContent = original;
        }, 2000);
      }
    }

    if (String(path).toLowerCase().endsWith('index.html')) {
      await renderCodeUiHtml(taskId);
    }

    if (String(path).toLowerCase().endsWith('style.css')) {
      await renderCodeUiHtml(taskId);
    }

    return true;
  } catch (error) {
    console.error('Fehler beim Speichern:', error);
    if (!silent) {
      alert('Fehler beim Speichern: ' + error.message);
    }
    return false;
  }
}

async function deleteTaskItem(taskId, path) {
  if (!confirm(`Wirklich löschen: ${path}?`)) return;
  
  try {
    await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=delete`, {
      method: 'POST',
      body: JSON.stringify({
        task_id: taskId,
        path: path
      })
    });
    
    // Reload files mit aktuellem currentPath
    const panelId = `folder-panel-content-${taskId}`;
    const tree = document.getElementById(`task-file-tree-${taskId}`);
    const currentPath = tree ? tree.getAttribute('data-current-path') || '' : '';
    loadAndDisplayTaskFiles(panelId, taskId, currentPath);
  } catch (error) {
    alert('Fehler beim Löschen: ' + error.message);
  }
}

async function resetCodeUiTemplate(taskId, currentPath = '') {
  const confirmed = confirm('Code-UI auf Standardvorlage zurücksetzen? Eigene Änderungen an Template-Dateien werden verworfen.');
  if (!confirmed) return;

  try {
    const testUserParam = window.TEST_USER_ID ? `&test_user_id=${window.TEST_USER_ID}` : '';
    const response = await requestJson(`/pythonIDE/api/user_tasks/folder-files.php?action=reset_code_ui${testUserParam}`, {
      method: 'POST',
      body: JSON.stringify({ task_id: taskId })
    });

    if (!response.ok) {
      throw new Error(response.error || 'Reset fehlgeschlagen');
    }

    const panelId = `folder-panel-content-${taskId}`;
    loadAndDisplayTaskFiles(panelId, taskId, currentPath);
    await renderCodeUiHtml(taskId);

    if (window.currentFile && Number(window.currentFile.taskId) === Number(taskId)) {
      await openTaskFileInEditor(taskId, 'init.py');
    }
  } catch (error) {
    alert('Fehler beim Zurücksetzen: ' + error.message);
  }
}

// Duplicate item
async function duplicateTaskItem(taskId, path) {
  // Find unique name
  const tree = document.getElementById(`task-file-tree-${taskId}`);
  const fileName = path.split('/').pop();
  const baseName = fileName.includes('.') ? fileName.substring(0, fileName.lastIndexOf('.')) : fileName;
  const ext = fileName.includes('.') ? fileName.substring(fileName.lastIndexOf('.')) : '';
  
  // Extract parent path from full path
  const pathParts = path.split('/');
  pathParts.pop(); // Remove file name
  const parentPath = pathParts.join('/');
  
  const existingNames = Array.from(tree.querySelectorAll('.file-name'))
    .map(el => el.textContent);
  
  let newName = baseName + '_copy' + ext;
  let counter = 2;
  while (existingNames.includes(newName)) {
    newName = baseName + '_copy' + counter + ext;
    counter++;
  }
  
  try {
    // Read content if file
    let content = '';
    const item = tree.querySelector(`[data-path="${path}"]`);
    const isFile = item && item.getAttribute('data-type') === 'file';
    
    if (isFile) {
      try {
        const response = await fetch(`/pythonIDE/api/tasks/folder-manage.php?action=read&task_id=${taskId}&path=${encodeURIComponent(path)}`);
        if (response.ok) {
          const result = await response.json();
          content = result.content || '';
        }
      } catch (e) {
        // Continue without content
      }
    }
    
    // Create new file/folder
    if (isFile) {
      await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=create_file`, {
        method: 'POST',
        body: JSON.stringify({
          task_id: taskId,
          name: newName,
          parent_path: parentPath,
          content: content
        })
      });
    } else {
      await requestJson(`/pythonIDE/api/tasks/folder-manage.php?action=create_folder`, {
        method: 'POST',
        body: JSON.stringify({
          task_id: taskId,
          name: newName,
          parent_path: parentPath
        })
      });
    }
    
    // Reload files
    const panelId = `folder-panel-content-${taskId}`;
    const currentPath = tree ? tree.getAttribute('data-current-path') || '' : '';
    loadAndDisplayTaskFiles(panelId, taskId, currentPath);
  } catch (error) {
    alert('Fehler beim Duplizieren: ' + error.message);
  }
}

// Download file
async function downloadTaskFile(taskId, path) {
  try {
    // Fetch file content
    const response = await fetch(`/pythonIDE/api/tasks/folder-manage.php?action=read&task_id=${taskId}&path=${encodeURIComponent(path)}`);
    
    if (!response.ok) {
      throw new Error('Fehler beim Abrufen der Datei');
    }
    
    const result = await response.json();
    const content = result.content || '';
    const fileName = path.split('/').pop();
    
    // Create blob and download
    const blob = new Blob([content], { type: 'application/octet-stream' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch (error) {
    alert('Fehler beim Herunterladen: ' + error.message);
  }
}

async function refreshStudentLiveState() {
  const refreshBtn = document.getElementById('dashboard-btn');
  const originalLabel = refreshBtn ? refreshBtn.textContent : null;
  const assignmentId = assignmentState.currentAssignmentId || window.ASSIGNMENT_ID;
  const currentTaskId = assignmentState.currentTaskId || window.TASK_ID;

  if (!assignmentId) {
    window.location.reload();
    return;
  }

  try {
    if (refreshBtn) {
      refreshBtn.disabled = true;
      refreshBtn.style.opacity = '0.7';
      refreshBtn.textContent = '⟳';
    }

    await loadSingleAssignment(assignmentId);

    const tasks = assignmentState.tasksByAssignment[assignmentId] || [];
    let targetTaskId = currentTaskId;

    if (!targetTaskId || !tasks.some(t => t.id === targetTaskId)) {
      targetTaskId = tasks.length ? tasks[0].id : null;
    }

    if (targetTaskId) {
      await loadTaskIntoEditor(assignmentId, targetTaskId);
      if (window.renderTaskNavigation) {
        window.renderTaskNavigation();
      }
    }

    const outputEl = document.getElementById('output-container');
    if (outputEl) {
      outputEl.textContent = 'Live-Stand aktualisiert';
    }
  } catch (err) {
    console.error('Failed to refresh student live state:', err);
    const outputEl = document.getElementById('output-container');
    if (outputEl) {
      outputEl.textContent = `Aktualisierung fehlgeschlagen: ${err.message || err}`;
    }
  } finally {
    if (refreshBtn) {
      refreshBtn.disabled = false;
      refreshBtn.style.opacity = '';
      refreshBtn.textContent = originalLabel || '↻';
    }
  }
}

// Make functions global
window.loadAndDisplayTaskFiles = loadAndDisplayTaskFiles;
window.createTaskFolder = createTaskFolder;
window.createTaskFile = createTaskFile;
window.uploadTaskFile = uploadTaskFile;
window.handleTaskFileUpload = handleTaskFileUpload;
window.startInlineEdit = startInlineEdit;
window.openTaskFileInEditor = openTaskFileInEditor;
window.saveTaskFile = saveTaskFile;
window.showTaskFileContextMenu = showTaskFileContextMenu;
window.deleteTaskItem = deleteTaskItem;
window.duplicateTaskItem = duplicateTaskItem;
window.downloadTaskFile = downloadTaskFile;
window.toggleTaskFileReadonly = toggleTaskFileReadonly;
window.resetCodeUiTemplate = resetCodeUiTemplate;
window.refreshStudentLiveState = refreshStudentLiveState;
window.renderCodeUiHtml = renderCodeUiHtml;
window.cacheCurrentEditorDraft = cacheCurrentEditorDraft;
window.getTaskDraftContent = getTaskDraftContent;
window.setTaskSavedSnapshot = setTaskSavedSnapshot;