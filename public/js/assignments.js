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
  
  const contentEl = $('task-details-content');
  const panel = $('task-details-panel');
  const app = document.querySelector('.app');

  if (!contentEl || !panel) return;

  panel.classList.add('active');
  if (app) app.classList.add('with-task-details');

  let html = '';

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

  // Additional hints (reveal one by one after clicking)
  const hasAdditionalHints = task.hint1 || task.hint2 || task.hint3;
  if (hasAdditionalHints) {
    // Get revealed hints for this task
    const revealedHints = assignmentState.hintsRevealed[task.id] || [];
    const nextHintToReveal = revealedHints.length + 1; // 1, 2, or 3
    
    html += `<div class="hints-section" id="hints-container">
      <h4>Weitere Hinweise <span id="hints-counter" style="color:#888; font-size:11px;">(${revealedHints.length}/3 genutzt)</span></h4>`;
    
    // Hinweis 1
    if (task.hint1) {
      if (revealedHints.includes(1)) {
        // Already revealed
        html += `<div class="hint-item revealed" data-hint="1">
          <span class="hint-number">✓ Hinweis 1:</span> ${escapeHtml(task.hint1)}
        </div>`;
      } else if (nextHintToReveal === 1) {
        // Next to reveal
        html += `<div class="hint-item clickable" data-hint="1" style="cursor:pointer; background:#fef3c7; padding:8px; border-radius:4px; margin:6px 0;">
          <span class="hint-number">💡 Hinweis 1:</span> <em>Klicken zum Anzeigen</em>
        </div>`;
      }
    }
    
    // Hinweis 2
    if (task.hint2) {
      if (revealedHints.includes(2)) {
        // Already revealed
        html += `<div class="hint-item revealed" data-hint="2">
          <span class="hint-number">✓ Hinweis 2:</span> ${escapeHtml(task.hint2)}
        </div>`;
      } else if (nextHintToReveal === 2) {
        // Next to reveal (only after hint 1)
        html += `<div class="hint-item clickable" data-hint="2" style="cursor:pointer; background:#fef3c7; padding:8px; border-radius:4px; margin:6px 0;">
          <span class="hint-number">💡 Hinweis 2:</span> <em>Klicken zum Anzeigen</em>
        </div>`;
      } else if (nextHintToReveal < 2) {
        // Locked (hint 1 not revealed yet)
        html += `<div class="hint-item locked" data-hint="2" style="color:#999; padding:8px; margin:6px 0;">
          <span class="hint-number">🔒 Hinweis 2:</span> <em>Erst Hinweis 1 anzeigen</em>
        </div>`;
      }
    }
    
    // Hinweis 3
    if (task.hint3) {
      if (revealedHints.includes(3)) {
        // Already revealed
        html += `<div class="hint-item revealed" data-hint="3">
          <span class="hint-number">✓ Hinweis 3:</span> ${escapeHtml(task.hint3)}
        </div>`;
      } else if (nextHintToReveal === 3) {
        // Next to reveal (only after hint 2)
        html += `<div class="hint-item clickable" data-hint="3" style="cursor:pointer; background:#fef3c7; padding:8px; border-radius:4px; margin:6px 0;">
          <span class="hint-number">💡 Hinweis 3:</span> <em>Klicken zum Anzeigen</em>
        </div>`;
      } else if (nextHintToReveal < 3) {
        // Locked (previous hints not revealed yet)
        html += `<div class="hint-item locked" data-hint="3" style="color:#999; padding:8px; margin:6px 0;">
          <span class="hint-number">🔒 Hinweis 3:</span> <em>Erst Hinweis ${nextHintToReveal} anzeigen</em>
        </div>`;
      }
    }
    
    html += `</div>`;
  }

  // Expected Output
  if (task.expected_output) {
    html += `<h4>Erwartete Ausgabe</h4><div class="task-expected">${escapeHtml(task.expected_output)}</div>`;
  }

  contentEl.innerHTML = html;
  
  // Store initial hint data for revealing on click
  task._hint1 = task.hint1;
  task._hint2 = task.hint2;
  task._hint3 = task.hint3;
  
  // Bind hint click handlers (only for clickable hints)
  const hintItems = contentEl.querySelectorAll('.hint-item.clickable');
  hintItems.forEach(item => {
    item.addEventListener('click', function() {
      const hintNum = parseInt(this.dataset.hint);
      const hintText = task[`hint${hintNum}`];
      
      if (hintText && !this.classList.contains('revealed')) {
        // Reveal hint
        this.innerHTML = `<span class="hint-number">✓ Hinweis ${hintNum}:</span> ${escapeHtml(hintText)}`;
        this.classList.remove('clickable');
        this.classList.add('revealed');
        this.style.cursor = 'default';
        this.style.background = 'transparent';
        
        // Track revealed hint
        if (!assignmentState.hintsRevealed[task.id]) {
          assignmentState.hintsRevealed[task.id] = [];
        }
        if (!assignmentState.hintsRevealed[task.id].includes(hintNum)) {
          assignmentState.hintsRevealed[task.id].push(hintNum);
          
          // Update counter
          const counter = $('hints-counter');
          if (counter) {
            counter.textContent = `(${assignmentState.hintsRevealed[task.id].length}/3 genutzt)`;
          }
          
          // Reload task details to show next hint
          renderTaskDetails(task);
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

  navEl.innerHTML = tasks.map((task, idx) => {
    const status = assignmentState.taskStatuses[task.id] || 'unbearbeitet';
    const isActive = task.id === assignmentState.currentTaskId;
    
    return `
      <div class="task-nav-item ${isActive ? 'active' : ''}" data-task-id="${task.id}">
        <span class="task-nav-position">${idx + 1}.</span>
        <span class="task-nav-status status-${status}"></span>
        <span class="task-nav-title">${escapeHtml(task.title)}</span>
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
  const listView = $('assignment-list-view');
  const editorView = $('editor-view');
  const dashboardBtn = $('dashboard-btn');
  const backToListBtn = $('back-to-list-btn');
  const runBtn = $('run-btn');
  
  // Hide list, show editor
  if (listView) listView.style.display = 'none';
  if (editorView) editorView.style.display = 'grid';
  
  // Switch buttons
  if (dashboardBtn) dashboardBtn.style.display = 'none';
  if (backToListBtn) backToListBtn.style.display = 'inline-block';
  if (runBtn) runBtn.style.display = 'inline-block';
  
  // Set current assignment
  assignmentState.currentAssignmentId = assignmentId;
  
  // Get tasks for this assignment
  const tasks = assignmentState.tasksByAssignment[assignmentId] || [];
  
  if (tasks.length === 0) {
    console.warn('No tasks found for assignment', assignmentId);
    return;
  }
  
  // Load first task
  const firstTask = tasks[0];
  loadTaskIntoEditor(assignmentId, firstTask.id);
}

// Go back to assignment list
function backToAssignmentList() {
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

  // Render task navigation
  renderTaskNavigation();

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

  // Show check button if test cases OR validation mode exist
  const checkBtn = $('check-btn');
  if (checkBtn) {
    // Debug: Log task data
    console.log(`[RENDER] Task ${task.id} (${task.title}): test_cases=${!!task.test_cases} validation_mode='${task.validation_mode}'`);
    
    // Show button if either test cases exist OR validation mode is set
    if (task.test_cases || task.validation_mode) {
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

  // Only show error if BOTH test_cases AND validation_mode are missing
  if (!task.test_cases && !task.validation_mode) {
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
      // Migrate legacy FUNCTION structure to new structure
      testCases = migrateLegacyTestCases(testCases);
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
        allResults.push(...await runOutputTests(pyodide, code, cases, task.validation_mode));
      } else if (type === 'function') {
        // FUNCTION TESTING: Call function with args
        allResults.push(...await runFunctionTests(pyodide, code, cases, task.validation_mode));
      } else if (type === 'variable') {
        // VARIABLE TESTING: Set init vars, check expected vars
        allResults.push(...await runVariableTests(pyodide, code, cases, task.validation_mode));
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
 * Migrate legacy test case structures to new unified structure
 * Legacy FUNCTION: [{ type: 'function', function_name: 'f', args: [...], expected: value }, ...]
 * New FUNCTION:    [{ type: 'function', function_name: 'f', test_cases: [{ args: [...], expected: value }, ...] }]
 */
function migrateLegacyTestCases(testCases) {
  if (!Array.isArray(testCases) || testCases.length === 0) return testCases;
  
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
 *
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
      const passed = testResult.error ? false : compareTestOutput(testResult.output, testCase.expected, validationMode);
      
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
 */
async function runVariableTests(pyodide, code, testCases, validationMode) {
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
 * Run Code Check tests
 * Checks if code contains required keywords
 * Supports multiple code_check test cases
 */
function runCodeCheck(code, testCases) {
  const results = [];
  
  // Process each code_check test case
  testCases.forEach((testSpec, idx) => {
    if (!testSpec || testSpec.type !== 'code_check') return;
    
    const keywords = testSpec.keywords || [];
    const operator = testSpec.operator || 'AND';
    const feedback = testSpec.feedback || '';
    
    // Check each keyword
    const keywordResults = keywords.map(keyword => ({
      keyword,
      found: code.toUpperCase().includes(keyword.toUpperCase())
    }));
    
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
function compareTestOutput(actual, expected, mode = 'loose') {
  // Convert to string, handling 0 and false properly (not like || which treats 0 as falsy)
  let actualCleaned = String(actual !== null && actual !== undefined ? actual : '').trim();
  let expectedCleaned;
  
  // Handle array of expected values (OR logic - any match passes)
  if (Array.isArray(expected)) {
    return expected.some(exp => {
      expectedCleaned = String(exp !== null && exp !== undefined ? exp : '').trim();
      
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

  // Update status in task navigation
  renderTaskNavigation();
}

function bindAssignmentsEvents() {
  const checkBtn = $('check-btn');
  const backToListBtn = $('back-to-list-btn');

  // Back to list button
  backToListBtn?.addEventListener('click', () => {
    backToAssignmentList();
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
  // Auto-load assignments if on assignments.php
  if (window.location.pathname.includes('assignments.php')) {
    console.log('On assignments page - loading assignments');
    // Load assignments (this will show the list)
    loadAssignments();
  }
});
