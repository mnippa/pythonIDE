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
  taskStartTimes: {}, // Track start time for each task: { taskId: timestamp }
  expandedAssignmentId: null, // Track which assignment is expanded
  hintsRevealed: {}, // Track revealed hints per task: { taskId: [1, 2, 3] }
  hasAutoLoaded: false // Flag to prevent multiple auto-loads
};

function $(id) {
  return document.getElementById(id);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

async function requestJson(url, options = {}) {
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

// Display task details in left sidebar
function showTaskDetails(task) {
  assignmentState.currentTask = task;
  
  const titleEl = $('task-details-title');
  const posEl = $('task-details-position');
  const contentEl = $('task-details-content');
  const panel = $('task-details-panel');
  const app = document.querySelector('.app');

  if (!titleEl || !contentEl || !panel) return;

  titleEl.textContent = escapeHtml(task.title);
  posEl.textContent = `Aufgabe ${task.position}`;
  panel.classList.add('active');
  if (app) app.classList.add('with-task-details');

  let html = '';
  
  // Status Light
  const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';
  html += `<div style="display:flex; align-items:center; margin-bottom:12px;">
    <span class="status-light status-${status}"></span>
    <span style="font-size:12px; font-weight:600;">${escapeHtml(getStatusLabel(status))}</span>
  </div>`;

  // Stoff (Learning Content)
  if (task.stoff) {
    html += `<div class="stoff-section">
      <h4>📚 Lerninhalt (Stoff)</h4>
      <p>${escapeHtml(task.stoff)}</p>
    </div>`;
  }
  
  // Description
  if (task.description) {
    html += `<h4>Aufgabenstellung</h4><p>${escapeHtml(task.description)}</p>`;
  }

  // Hint (initial hint, always visible)
  if (task.hint) {
    html += `<h4>Hinweis</h4><div class="task-hint">${escapeHtml(task.hint)}</div>`;
  }

  // Additional hints (only shown after successful check)
  const hasAdditionalHints = task.hint1 || task.hint2 || task.hint3;
  if (hasAdditionalHints) {
    html += `<div class="hints-section" id="hints-container" style="display:none;">
      <h4>Weitere Hinweise</h4>`;
    
    if (task.hint1) {
      html += `<div class="hint-item" data-hint="1">
        <span class="hint-number">Hinweis 1:</span> Klicken um anzuzeigen
      </div>`;
    }
    if (task.hint2) {
      html += `<div class="hint-item" data-hint="2">
        <span class="hint-number">Hinweis 2:</span> Klicken um anzuzeigen
      </div>`;
    }
    if (task.hint3) {
      html += `<div class="hint-item" data-hint="3">
        <span class="hint-number">Hinweis 3:</span> Klicken um anzuzeigen
      </div>`;
    }
    
    html += `</div>`;
  }

  // Expected Output
  if (task.expected_output) {
    html += `<h4>Erwartete Ausgabe</h4><div class="task-expected">${escapeHtml(task.expected_output)}</div>`;
  }

  // Type
  html += `<h4>Typ</h4><p>${escapeHtml(task.problem_type)}</p>`;

  // Max Attempts
  if (task.max_attempts) {
    html += `<h4>Maximale Versuche</h4><p>${task.max_attempts}</p>`;
  }

  contentEl.innerHTML = html;
  
  // Store initial hint data for revealing on click
  task._hint1 = task.hint1;
  task._hint2 = task.hint2;
  task._hint3 = task.hint3;
  
  // Bind hint click handlers
  const hintItems = contentEl.querySelectorAll('.hint-item');
  hintItems.forEach(item => {
    item.addEventListener('click', function() {
      const hintNum = parseInt(this.dataset.hint);
      const hintText = task[`hint${hintNum}`] || task[`_hint${hintNum}`];
      if (hintText && !this.classList.contains('revealed')) {
        this.innerHTML = `<span class="hint-number revealed">✓ Hinweis ${hintNum}:</span> ${escapeHtml(hintText)}`;
        this.classList.add('revealed');
        
        // Track revealed hint
        if (!assignmentState.hintsRevealed[task.id]) {
          assignmentState.hintsRevealed[task.id] = [];
        }
        if (!assignmentState.hintsRevealed[task.id].includes(hintNum)) {
          assignmentState.hintsRevealed[task.id].push(hintNum);
          console.log(`Hint ${hintNum} revealed for task ${task.id}. Total hints revealed: ${assignmentState.hintsRevealed[task.id].length}`);
        }
      }
    });
  });
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

async function loadAssignments() {
  const listEl = $('assignments-list');
  if (!listEl) return;
  listEl.textContent = 'Loading assignments...';

  try {
    const data = await requestJson('../api/user_assignments/list.php');
    assignmentState.assignments = data.items || [];
    
    let lastWorkingTask = null;
    let lastWorkingAssignment = null;
    let lastUpdateTime = null;
    
    // Load all tasks for all assignments
    for (const item of assignmentState.assignments) {
      try {
        const [assignmentRes, tasksRes] = await Promise.all([
          requestJson(`../api/assignments/get.php?id=${item.assignment_id}`),
          requestJson(`../api/tasks/list.php?assignment_id=${item.assignment_id}`)
        ]);
        
        assignmentState.assignmentDetails[item.assignment_id] = assignmentRes.assignment;
        assignmentState.tasksByAssignment[item.assignment_id] = tasksRes.tasks || [];
        
        // Load user_tasks progress for this assignment
        try {
          const userTasksRes = await requestJson(`../api/user_tasks/get.php?assignment_id=${item.assignment_id}`);
          const userTasks = userTasksRes.tasks || [];
          
          // Populate status and attempts from user_tasks
          userTasks.forEach(ut => {
            assignmentState.taskStatuses[ut.task_id] = ut.status;
            assignmentState.taskAttempts[ut.task_id] = ut.attempts;
            if (ut.hints_revealed && Array.isArray(ut.hints_revealed)) {
              assignmentState.hintsRevealed[ut.task_id] = ut.hints_revealed;
            }
            
            // Track last working task (in-progress or most recently updated)
            if (ut.status === 'in-progress' && ut.updated_at) {
              const updateTime = new Date(ut.updated_at).getTime();
              if (!lastUpdateTime || updateTime > lastUpdateTime) {
                lastUpdateTime = updateTime;
                lastWorkingTask = ut.task_id;
                lastWorkingAssignment = item.assignment_id;
              }
            }
          });
        } catch (err) {
          console.warn(`Failed to load user_tasks for assignment ${item.assignment_id}:`, err);
        }
      } catch (err) {
        console.error(`Failed to load tasks for assignment ${item.assignment_id}:`, err);
      }
    }
    
    renderAssignments();
    
    // Auto-load last working task (only once on initial load)
    if (!assignmentState.hasAutoLoaded && lastWorkingTask && lastWorkingAssignment) {
      assignmentState.hasAutoLoaded = true;
      console.log(`Auto-loading last working task: ${lastWorkingTask}`);
      setTimeout(() => {
        loadTaskIntoEditor(lastWorkingAssignment, lastWorkingTask);
      }, 500);
    }
  } catch (err) {
    listEl.innerHTML = `<div style="color:#b91c1c;">Failed to load assignments</div>`;
  }
}

function renderAssignments() {
  const listEl = $('assignments-list');
  if (!listEl) return;

  if (!assignmentState.assignments.length) {
    listEl.innerHTML = '<div style="color:var(--text-secondary); padding:12px;">No assignments yet.</div>';
    return;
  }

  listEl.innerHTML = assignmentState.assignments.map((item) => {
    const tasks = assignmentState.tasksByAssignment[item.assignment_id] || [];
    const completedCount = tasks.filter(t => {
      const status = assignmentState.taskStatuses[t.id];
      return status === 'passed' || status === 'failed';
    }).length;
    
    // Determine if this assignment should be expanded
    const isCurrentAssignment = item.assignment_id === assignmentState.currentAssignmentId;
    const isExpanded = isCurrentAssignment ? 'open' : '';
    
    return `
      <div class="assignment-item${isCurrentAssignment ? ' expanded' : ''}" data-assignment-id="${item.assignment_id}">
        <div class="assignment-header-bar">
          <div class="assignment-header-left">
            <span class="assignment-expand-icon">▶</span>
            <div>
              <div class="assignment-title">${escapeHtml(item.assignment_title)}</div>
              <div class="assignment-status-summary">${completedCount}/${tasks.length} erledigt</div>
            </div>
          </div>
        </div>
        <div class="assignment-tasks-list">
          ${tasks.length ? tasks.map(task => {
            const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';
            const isCurrentTask = task.id === assignmentState.currentTaskId;
            
            return `
              <div class="assignment-task-row" data-task-id="${task.id}" data-assignment-id="${item.assignment_id}" ${isCurrentTask ? 'style="background:#f0fdf4; border-left: 3px solid #10b981; padding-left: 9px;"' : ''}>
                <span class="status-light status-${status}"></span>
                <span style="flex:1; font-size:13px; font-weight: ${isCurrentTask ? '600' : '400'}; color: ${isCurrentTask ? '#059669' : 'inherit'};">${task.position}. ${escapeHtml(task.title)}</span>
              </div>
            `;
          }).join('') : '<div style="padding:12px; color:var(--text-secondary); font-size:12px;">Keine Aufgaben</div>'}
        </div>
      </div>
    `;
  }).join('');
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
    const response = await requestJson(`../api/user_tasks/get.php?task_id=${taskId}`);
    if (response && response.task && response.task.current_code) {
      return response.task.current_code;
    }
    return null;
  } catch (err) {
    console.warn('Failed to load saved code:', err);
    return null;
  }
}

function loadTaskIntoEditor(assignmentId, taskId) {
  const tasks = assignmentState.tasksByAssignment[assignmentId] || [];
  const task = tasks.find((t) => t.id === taskId);
  if (!task) return;

  const editor = window.editorInstance;
  if (!editor) {
    alert('Editor not ready yet');
    return;
  }

  // Store current task info globally for Check button
  assignmentState.currentTask = task;
  assignmentState.currentAssignmentId = assignmentId;
  assignmentState.currentTaskId = taskId;

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

  // Load saved code from user_tasks if available
  loadSavedCode(taskId).then(savedCode => {
    const code = savedCode || task.code_template || '# Start here';
    editor.setValue(code);
  }).catch(err => {
    console.warn('Failed to load saved code, using template:', err);
    const code = task.code_template || '# Start here';
    editor.setValue(code);
  });

  // Show task details
  showTaskDetails(task);

  // Show/update attempts counter if task has test_cases
  updateAttemptsCounter(task);

  // Auto-save when task is loaded (mark as in_progress)
  setTimeout(() => {
    saveCode();
  }, 500);

  // Hide file tree initially, show only if needed
  const fileTreeWrapper = $('file-tree-wrapper');
  if (fileTreeWrapper) {
    fileTreeWrapper.classList.remove('active');
  }

  const outputEl = document.getElementById('output-container');
  if (outputEl) {
    outputEl.textContent = `Task geladen: ${task.title}`;
  }

  // Show check button if test cases exist
  const checkBtn = $('check-btn');
  if (checkBtn) {
    if (task.test_cases && task.validation_mode) {
      checkBtn.style.display = 'inline-block';
      // Disable button if max attempts reached and not passed
      const currentAttempts = assignmentState.taskAttempts[task.id] || 0;
      const maxAttempts = task.max_attempts || 10;
      const currentStatus = assignmentState.taskStatuses[task.id];
      
      if (currentAttempts >= maxAttempts && currentStatus !== 'passed') {
        checkBtn.disabled = true;
        checkBtn.style.opacity = '0.5';
        checkBtn.style.cursor = 'not-allowed';
      } else {
        checkBtn.disabled = false;
        checkBtn.style.opacity = '1';
        checkBtn.style.cursor = 'pointer';
      }
    } else {
      checkBtn.style.display = 'none';
    }
  }

  // Show save and download buttons for tasks
  const saveTaskBtn = $('save-task-btn');
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

  const downloadBtn = $('download-btn');
  if (downloadBtn) {
    downloadBtn.style.display = 'inline-block';
  }

  // Hide project save button
  const saveProjectBtn = $('save-project-btn');
  if (saveProjectBtn) {
    saveProjectBtn.style.display = 'none';
  }

  // Watch for code changes to update status to in-progress (only if unbearbeitet)
  if (editor && !task._changeListenerAdded) {
    editor.onDidChangeModelContent(() => {
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
  const counter = $('attempts-counter');
  const value = $('attempts-value');
  if (!counter || !value) return;

  const maxAttempts = task.max_attempts || 10;
  const attempts = assignmentState.taskAttempts[task.id] || 0;

  value.textContent = `${attempts}/${maxAttempts}`;
  counter.style.display = 'inline-block';
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

async function saveCode() {
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
      saveTaskBtn.textContent = '✗ Fehler: Task ID nicht gefunden';
      saveTaskBtn.style.background = '#ef4444';
      saveTaskBtn.style.color = '#fff';
      setTimeout(() => {
        saveTaskBtn.textContent = '💾 Speichern';
        saveTaskBtn.style.background = '';
        saveTaskBtn.style.color = '';
      }, 3000);
    }
    return false;
  }

  try {
    // Show saving indicator
    if (saveTaskBtn) {
      saveTaskBtn.textContent = '⏳ Speichert...';
      saveTaskBtn.disabled = true;
    }

    // Generate filename from task title
    const filename = generateFilename(task.title);

    // Save to user_tasks API
    const payload = {
      task_id: taskId,
      current_code: code,
      status: 'in-progress',
      hints_revealed: assignmentState.hintsRevealed[taskId] || [],
      started_at: new Date(assignmentState.taskStartTimes[taskId] || Date.now()).toISOString().slice(0, 19).replace('T', ' ')
    };

    console.log('[SAVE] Saving task:', taskId, 'Code length:', code.length, 'chars');
    console.log('[SAVE] Payload:', payload);

    const response = await requestJson('../api/user_tasks/update.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    console.log('[SAVE] API Response:', response);

    if (saveTaskBtn) {
      saveTaskBtn.textContent = '✓ Gespeichert';
      saveTaskBtn.style.background = '#10b981';
      saveTaskBtn.style.color = '#fff';
      setTimeout(() => {
        saveTaskBtn.textContent = '💾 Speichern';
        saveTaskBtn.style.background = '';
        saveTaskBtn.style.color = '';
        saveTaskBtn.disabled = false;
      }, 2000);
    }

    return true;
  } catch (err) {
    console.error('Failed to save code:', err);
    if (saveTaskBtn) {
      saveTaskBtn.textContent = '✗ Fehler';
      saveTaskBtn.style.background = '#ef4444';
      saveTaskBtn.style.color = '#fff';
      setTimeout(() => {
        saveTaskBtn.textContent = '💾 Speichern';
        saveTaskBtn.style.background = '';
        saveTaskBtn.style.color = '';
        saveTaskBtn.disabled = false;
      }, 2000);
    }
    return false;
  }
}

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

  // Build stats HTML (4 stats in 2x2 grid)
  const statsHtml = `
    <div class="success-stat">
      <div class="success-stat-value">✓</div>
      <div class="success-stat-label">Gelöst</div>
    </div>
    <div class="success-stat">
      <div class="success-stat-value">${attempts}/${maxAttempts}</div>
      <div class="success-stat-label">Versuche</div>
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

async function checkTask() {
  const task = assignmentState.currentTask;
  if (!task) {
    alert('No task loaded');
    return;
  }

  if (!task.test_cases || !task.validation_mode) {
    alert('No test cases available');
    return;
  }

  const editor = window.editorInstance;
  if (!editor) {
    alert('Editor not ready');
    return;
  }

  // Auto-save code before checking
  await saveCode();

  // Get code from editor
  const code = editor.getValue();

  // Run code in Pyodide
  const outputEl = $('output-container');
  if (outputEl) {
    outputEl.innerHTML = '<span style="color:#666;">Prüfe Code...</span>';
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
      testCases = JSON.parse(task.test_cases);
    } catch (e) {
      console.error('Failed to parse test cases:', e);
      testCases = [];
    }

    // Detect test type (explicit or auto-detect from legacy format)
    const testType = detectTestType(testCases);
    console.log('[CHECK] Test type detected:', testType);

    let allResults = [];

    // ==========================================
    // ROUTE TO SPECIFIC TEST TYPE HANDLER
    // ==========================================
    
    if (testType === 'output') {
      // OUTPUT TESTING: Run code, capture stdout
      allResults = await runOutputTests(pyodide, code, testCases, task.validation_mode);
    } else if (testType === 'function') {
      // FUNCTION TESTING: Call function with args
      allResults = await runFunctionTests(pyodide, code, testCases, task.validation_mode);
    } else if (testType === 'variable') {
      // VARIABLE TESTING: Set init vars, check expected vars
      allResults = await runVariableTests(pyodide, code, testCases, task.validation_mode);
    } else {
      outputEl.innerHTML = '<span style="color:#c00;">Unknown test type</span>';
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
    
    // Process validation result
    processValidationResult(result, task, outputEl);

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
 * Run OUTPUT tests
 */
async function runOutputTests(pyodide, code, testCases, validationMode) {
  const results = [];
  
  for (let idx = 0; idx < testCases.length; idx++) {
    const testCase = testCases[idx];
    
    try {
      // Run code and capture output
      const output = await pyodide.runPythonAsync(`
import sys
from io import StringIO

user_code = ${JSON.stringify(code)}

output_buffer = StringIO()
old_stdout = sys.stdout
sys.stdout = output_buffer

try:
    exec(compile(user_code, "<usercode>", "exec"), {})
except Exception as e:
    output_buffer.write(f"Error: {e}")
finally:
    sys.stdout = old_stdout

output_buffer.getvalue()
`);
      
      const passed = compareTestOutput(output, testCase.expected, validationMode);
      
      results.push({
        passed,
        testNumber: idx + 1,
        type: 'output',
        output: output,
        expected: testCase.expected,
        error: null
      });
      
    } catch (e) {
      results.push({
        passed: false,
        testNumber: idx + 1,
        type: 'output',
        output: '',
        expected: testCase.expected,
        error: e.message
      });
    }
  }
  
  return results;
}

/**
 * Run FUNCTION tests
 */
async function runFunctionTests(pyodide, code, testCases, validationMode) {
  const results = [];
  
  // Execute code to define functions
  const testOutputs = await pyodide.runPythonAsync(`
import sys
from io import StringIO
import json

user_code = ${JSON.stringify(code)}
test_cases = ${JSON.stringify(testCases)}

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
    
    # Get function name (explicit or auto-detect)
    if 'function_name' in test:
        function_name = test['function_name']
    else:
        # Legacy: find first function
        function_name = None
        for name, obj in namespace.items():
            if callable(obj) and not name.startswith('_'):
                function_name = name
                break
    
    if not function_name or function_name not in namespace:
        result["error"] = f"Function '{function_name}' not found"
        results.append(result)
        continue
    
    # Get args (explicit or legacy format)
    if 'args' in test:
        args = test['args']
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
            output = namespace[function_name](*args)
        else:
            output = namespace[function_name]()
        
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
      const testCase = testCases[idx];
      const passed = testResult.error ? false : compareTestOutput(testResult.output, testCase.expected, validationMode);
      
      results.push({
        passed,
        testNumber: idx + 1,
        type: 'function',
        functionName: testCase.function_name || '(auto)',
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
 */
async function runVariableTests(pyodide, code, testCases, validationMode) {
  const results = [];
  
  const testOutputs = await pyodide.runPythonAsync(`
import sys
import json
import re

user_code = ${JSON.stringify(code)}
test_cases = ${JSON.stringify(testCases)}

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
    if 'init_vars' in test:
        namespace.update(test['init_vars'])
    
    # Execute user code (without INIT blocks)
    try:
        exec(compile(code_without_init, "<usercode>", "exec"), namespace)
        
        # Extract expected variables
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
      const testCase = testCases[idx];
      let passed = !testResult.error;
      let matchDetails = [];
      
      if (passed && testCase.expected_vars) {
        // Check each expected variable
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
      
      results.push({
        passed,
        testNumber: idx + 1,
        type: 'variable',
        initVars: testCase.init_vars || {},
        expectedVars: testCase.expected_vars || {},
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
 * Display test results in UI
 */
function displayTestResults(results, testCases, outputEl) {
  let html = '<div style="margin-bottom:12px;"><strong>Test Ergebnisse:</strong></div>';
  
  results.forEach((result, idx) => {
    const testCase = testCases[idx];
    const icon = result.passed ? '✓' : '✗';
    const color = result.passed ? '#10b981' : '#ef4444';
    const bgColor = result.passed ? '#f0fdf4' : '#fef2f2';
    
    html += `<div style="margin-bottom:8px; padding:8px; background:${bgColor}; border-radius:4px;">`;
    html += `<div style="color:${color}; font-weight:600;">${icon} Test ${result.testNumber}</div>`;
    html += `<div style="font-size:11px; color:#666; margin-top:2px;">Typ: ${result.type.toUpperCase()}</div>`;
    
    // Type-specific display
    if (result.type === 'output') {
      if (result.error) {
        html += `<div style="font-size:12px; color:#c00; margin-top:4px;">Fehler: ${escapeHtml(result.error)}</div>`;
      } else {
        html += `<div style="font-size:12px; margin-top:4px;">Ausgabe: <code>${escapeHtml(result.output || '(leer)')}</code></div>`;
        
        const expectedDisplay = Array.isArray(testCase.expected) 
          ? testCase.expected.join(' ODER ') 
          : testCase.expected;
        html += `<div style="font-size:12px; color:#666; margin-top:2px;">Erwartet: <code>${escapeHtml(expectedDisplay)}</code></div>`;
      }
      
    } else if (result.type === 'function') {
      html += `<div style="font-size:12px; color:#666; margin-top:4px;">`;
      html += `Funktion: <code>${escapeHtml(result.functionName || '?')}(`;
      if (Array.isArray(result.args)) {
        html += result.args.map(a => JSON.stringify(a)).join(', ');
      } else if (result.args) {
        html += escapeHtml(result.args);
      }
      html += `)</code></div>`;
      
      if (result.error) {
        html += `<div style="font-size:12px; color:#c00; margin-top:4px;">Fehler: ${escapeHtml(result.error)}</div>`;
      } else {
        html += `<div style="font-size:12px; margin-top:4px;">Ausgabe: <code>${escapeHtml(result.output)}</code></div>`;
        
        const expectedDisplay = Array.isArray(testCase.expected) 
          ? testCase.expected.join(' ODER ') 
          : testCase.expected;
        html += `<div style="font-size:12px; color:#666; margin-top:2px;">Erwartet: <code>${escapeHtml(expectedDisplay)}</code></div>`;
      }
      
    } else if (result.type === 'variable') {
      // Show init vars
      if (Object.keys(result.initVars).length > 0) {
        html += `<div style="font-size:12px; color:#666; margin-top:4px;">Input-Variablen: `;
        html += Object.entries(result.initVars).map(([k, v]) => `<code>${k}=${JSON.stringify(v)}</code>`).join(', ');
        html += `</div>`;
      }
      
      if (result.error) {
        html += `<div style="font-size:12px; color:#c00; margin-top:4px;">Fehler: ${escapeHtml(result.error)}</div>`;
      } else {
        // Show each variable check
        html += `<div style="font-size:12px; margin-top:4px;">`;
        result.matchDetails.forEach(detail => {
          const varIcon = detail.matches ? '✓' : '✗';
          const varColor = detail.matches ? '#10b981' : '#ef4444';
          html += `<div style="color:${varColor}; margin-top:2px;">`;
          html += `${varIcon} <code>${detail.varName}</code>: `;
          html += `<code>${JSON.stringify(detail.actual)}</code>`;
          if (!detail.matches) {
            html += ` (erwartet: <code>${JSON.stringify(detail.expected)}</code>)`;
          }
          html += `</div>`;
        });
        html += `</div>`;
      }
    }
    
    html += `</div>`;
  });
  
  outputEl.innerHTML = html;
}

/**
 * Compare test output with expected value(s)
 * Supports both single expected values and arrays (OR logic)
 */
function compareTestOutput(actual, expected, mode = 'loose') {
  const actualCleaned = String(actual || '').trim();
  
  // Handle array of expected values (OR logic - any match passes)
  if (Array.isArray(expected)) {
    return expected.some(exp => {
      const expectedCleaned = String(exp || '').trim();
      
      if (mode === 'loose') {
        const actualLoose = actualCleaned.replace(/\s+/g, ' ');
        const expectedLoose = expectedCleaned.replace(/\s+/g, ' ');
        return actualLoose === expectedLoose;
      }
      
      return actualCleaned === expectedCleaned;
    });
  }
  
  // Single expected value
  const expectedCleaned = String(expected || '').trim();
  
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
async function processValidationResult(result, task, outputEl) {
  // Increment attempts
  assignmentState.taskAttempts[task.id] = (assignmentState.taskAttempts[task.id] || 0) + 1;
  updateAttemptsCounter(task);

  // Update task status based on validation result and attempts
  const currentAttempts = assignmentState.taskAttempts[task.id];
  const maxAttempts = task.max_attempts || 10;

  if (result.passed === true) {
    // All tests passed - GREEN
    assignmentState.taskStatuses[task.id] = 'passed';

    // Show success modal with stats
    showSuccessModal(task, currentAttempts, maxAttempts);
  } else if (currentAttempts < maxAttempts) {
    // Some tests failed but still have attempts - YELLOW (in-progress)
    assignmentState.taskStatuses[task.id] = 'in-progress';
  } else {
    // Failed and no attempts left - RED
    assignmentState.taskStatuses[task.id] = 'failed';
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

  // Check if max attempts reached and disable button
  const checkBtn = $('check-btn');
  if (checkBtn) {
    const finalStatus = assignmentState.taskStatuses[task.id];
    console.log('[CHECK] Final status:', finalStatus, 'Attempts:', currentAttempts, '/', maxAttempts);
    
    if (finalStatus === 'passed') {
      // Task passed - keep button enabled (user might want to check again)
      checkBtn.disabled = false;
      checkBtn.style.opacity = '1';
      checkBtn.style.cursor = 'pointer';
      checkBtn.style.display = 'inline-block';
      console.log('[CHECK] Task passed - button kept enabled');
    } else if (currentAttempts >= maxAttempts) {
      // Max attempts reached and not passed - disable button
      checkBtn.disabled = true;
      checkBtn.style.opacity = '0.5';
      checkBtn.style.cursor = 'not-allowed';
      console.log('[CHECK] Max attempts reached - button disabled');
    } else {
      // Still have attempts left - keep button enabled
      checkBtn.disabled = false;
      checkBtn.style.opacity = '1';
      checkBtn.style.cursor = 'pointer';
      console.log('[CHECK] Attempts remaining - button enabled');
    }
  }
}

function updateTaskStatusDisplay(task) {
  const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';

  // Update status in task details panel
  const contentEl = $('task-details-content');
  if (contentEl) {
    const statusLightContainer = contentEl.querySelector('[style*="display:flex"]');
    if (statusLightContainer) {
      statusLightContainer.innerHTML = `
        <span class="status-light status-${status}"></span>
        <span style="font-size:12px; font-weight:600;">${escapeHtml(getStatusLabel(status))}</span>
      `;
    }
  }

  // Update status in assignments sidebar
  const listEl = $('assignments-list');
  if (listEl) {
    const taskRows = listEl.querySelectorAll('.assignment-task-row');
    taskRows.forEach(row => {
      if (parseInt(row.dataset.taskId) === task.id) {
        const light = row.querySelector('.status-light');
        if (light) {
          light.className = `status-light status-${status}`;
        }
      }
    });

    // Update completion count for assignment
    const assignmentId = assignmentState.currentAssignmentId;
    if (assignmentId) {
      const assignmentItem = listEl.querySelector(`[data-assignment-id="${assignmentId}"]`);
      if (assignmentItem) {
        const tasks = assignmentState.tasksByAssignment[assignmentId] || [];
        const completedCount = tasks.filter(t => {
          const s = assignmentState.taskStatuses[t.id];
          return s === 'passed' || s === 'failed';
        }).length;
        
        const summary = assignmentItem.querySelector('.assignment-status-summary');
        if (summary) {
          summary.textContent = `${completedCount}/${tasks.length} erledigt`;
        }
      }
    }
  }
}

function bindAssignmentsEvents() {
  const btn = $('assignments-btn');
  const panel = $('assignments-panel');
  const closeBtn = $('close-assignments');
  const listEl = $('assignments-list');
  const checkBtn = $('check-btn');

  btn?.addEventListener('click', () => {
    panel?.classList.add('open');
    loadAssignments();
  });

  closeBtn?.addEventListener('click', () => {
    panel?.classList.remove('open');
  });

  // Handle expand/collapse and task clicks
  listEl?.addEventListener('click', (e) => {
    // Check if clicking on assignment header (expand/collapse)
    const headerBar = e.target.closest('.assignment-header-bar');
    if (headerBar) {
      const assignmentItem = headerBar.closest('.assignment-item');
      if (assignmentItem) {
        assignmentItem.classList.toggle('expanded');
      }
      return;
    }

    // Check if clicking on a task row
    const taskRow = e.target.closest('.assignment-task-row');
    if (taskRow) {
      const assignmentId = parseInt(taskRow.dataset.assignmentId, 10);
      const taskId = parseInt(taskRow.dataset.taskId, 10);
      if (assignmentId && taskId) {
        loadTaskIntoEditor(assignmentId, taskId);
        panel?.classList.remove('open');
      }
    }
  });

  // Check button
  checkBtn?.addEventListener('click', () => {
    checkTask();
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
}

document.addEventListener('DOMContentLoaded', () => {
  bindAssignmentsEvents();

  // Bind modal button events
  const okBtn = $('success-ok-btn');
  if (okBtn) {
    okBtn.addEventListener('click', () => {
      closeSuccessModal();
      // Focus back on editor
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
  
  // Auto-open assignments panel and load assignments if on assignments.php
  if (window.location.pathname.includes('assignments.php')) {
    console.log('On assignments page - auto-opening panel and loading assignments');
    const panel = $('assignments-panel');
    if (panel) {
      panel.classList.add('open');
    }
    // Load assignments (this will also auto-load the last working task)
    loadAssignments();
  }
});
