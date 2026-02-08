// assignments.js - Assigned tasks panel for logged-in users

const assignmentState = {
  assignments: [],
  tasksByAssignment: {},
  assignmentDetails: {},
  currentTask: null,
  currentAssignmentId: null
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
  
  // Description
  if (task.description) {
    html += `<h4>Aufgabenstellung</h4><p>${escapeHtml(task.description)}</p>`;
  }

  // Hint
  if (task.hint) {
    html += `<h4>Hinweis</h4><div class="task-hint">${escapeHtml(task.hint)}</div>`;
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
}

async function loadAssignments() {
  const listEl = $('assignments-list');
  if (!listEl) return;
  listEl.textContent = 'Loading assignments...';

  try {
    const data = await requestJson('../api/user_assignments/list.php');
    assignmentState.assignments = data.items || [];
    renderAssignments();
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
    return `
      <div class="assignment-item">
        <div class="assignment-title">${escapeHtml(item.assignment_title)}</div>
        <div class="assignment-meta">
          <span class="${statusClass(item.status)}">${escapeHtml(item.status || 'assigned')}</span>
          <span>${escapeHtml(item.assignment_difficulty || 'beginner')}</span>
        </div>
        <div class="assignment-actions">
          <button class="btn" data-action="show-tasks" data-assignment-id="${item.assignment_id}">Show tasks</button>
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
    ${tasks.length ? tasks.map((task) => `
      <div class="task-item">
        <div class="task-title">${escapeHtml(task.position)}. ${escapeHtml(task.title)}</div>
        ${task.description ? `<div style="font-size:12px; color:var(--text-secondary);">${escapeHtml(task.description.substring(0, 80)) || ''}${task.description.length > 80 ? '...' : ''}</div>` : ''}
        <div class="task-actions">
          <button class="btn" data-action="load-task" data-task-id="${task.id}" data-assignment-id="${assignmentId}">Load in editor</button>
        </div>
      </div>
    `).join('') : '<div style="color:var(--text-secondary);">No tasks found.</div>'}
  `;
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

  // Set code template
  const code = task.code_template || '# Start here';
  editor.setValue(code);

  // Show task details
  showTaskDetails(task);

  // Show/update attempts counter if task has test_cases
  updateAttemptsCounter(task);

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
    } else {
      checkBtn.style.display = 'none';
    }
  }
}

function updateAttemptsCounter(task) {
  const counter = $('attempts-counter');
  const value = $('attempts-value');
  if (!counter || !value) return;

  const maxAttempts = task.max_attempts || 10;
  const attempts = assignmentState.currentAttempts || 0;

  value.textContent = `${attempts}/${maxAttempts}`;
  counter.style.display = 'inline-block';
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

    // Run user code and capture output
    const capturedOutput = await pyodide.runPythonAsync(`
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

    // Parse test cases
    let testCases = [];
    try {
      testCases = JSON.parse(task.test_cases);
    } catch (e) {
      console.error('Failed to parse test cases:', e);
      testCases = [];
    }
    
    // Validate using CodeValidator
    let validator = window.validator;
    if (!validator && window.CodeValidator) {
      validator = new window.CodeValidator();
      window.validator = validator;
    }

    if (!validator) {
      outputEl.innerHTML = '<span style="color:#c00;">Validator not available</span>';
      return;
    }

    const result = validator.validate(capturedOutput, testCases, task.validation_mode);
    let html = '<div style="margin-bottom:12px;"><strong>Ausgabe:</strong></div>';
    html += '<pre style="background:#f3f4f6; padding:8px; border-radius:4px; max-height:150px; overflow:auto;">' + 
            escapeHtml(String(capturedOutput)) + '</pre>';
    html += '<div style="margin-top:12px; margin-bottom:12px;"><strong>Validierungsergebnisse:</strong></div>';
    html += validator.formatResults(result);

    outputEl.innerHTML = html;

    // Increment attempts
    assignmentState.currentAttempts = (assignmentState.currentAttempts || 0) + 1;
    updateAttemptsCounter(task);

    // Check if max attempts reached
    if (assignmentState.currentAttempts >= task.max_attempts) {
      const checkBtn = $('check-btn');
      if (checkBtn) {
        checkBtn.disabled = true;
        checkBtn.style.opacity = '0.5';
        checkBtn.style.cursor = 'not-allowed';
      }
    }
    
    // TODO: POST to /api/user_assignments/update.php to increment attempts and update status
  } catch (err) {
    outputEl.innerHTML = `<div style="color:#c00;"><strong>Fehler:</strong> ${escapeHtml(String(err))}</div>`;
  }
}

function bindAssignmentsEvents() {
  const btn = $('assignments-btn');
  const panel = $('assignments-panel');
  const closeBtn = $('close-assignments');
  const listEl = $('assignments-list');
  const detailEl = $('assignment-detail');
  const checkBtn = $('check-btn');

  btn?.addEventListener('click', () => {
    panel?.classList.add('open');
    loadAssignments();
  });

  closeBtn?.addEventListener('click', () => {
    panel?.classList.remove('open');
  });

  listEl?.addEventListener('click', (e) => {
    const btnEl = e.target.closest('button[data-action="show-tasks"]');
    if (!btnEl) return;
    const assignmentId = parseInt(btnEl.dataset.assignmentId, 10);
    if (!assignmentId) return;
    loadAssignmentDetails(assignmentId);
  });

  detailEl?.addEventListener('click', (e) => {
    const btnEl = e.target.closest('button[data-action="load-task"]');
    if (!btnEl) return;
    const assignmentId = parseInt(btnEl.dataset.assignmentId, 10);
    const taskId = parseInt(btnEl.dataset.taskId, 10);
    if (!assignmentId || !taskId) return;
    loadTaskIntoEditor(assignmentId, taskId);
  });

  // Check button
  checkBtn?.addEventListener('click', () => {
    checkTask();
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindAssignmentsEvents();
});
