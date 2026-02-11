const state = {
  assignments: [],
  projects: [],
  users: [],
  tasks: [],
  currentAssignmentId: null,
  currentAssignmentTitle: null,
  assignmentsPage: 1,
  assignmentsPerPage: 10,
  assignmentsSortBy: 'id',
  assignmentsSortDir: 'asc',
  assignmentsFilter: ''
};

function $(id) {
  return document.getElementById(id);
}

async function requestJson(url, options = {}) {
  const opts = {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    ...options
  };

  const response = await fetch(url, opts);
  let data = null;
  try {
    data = await response.json();
  } catch (e) {
    throw new Error('Invalid server response');
  }

  if (!response.ok || (data && data.ok === false)) {
    const msg = data && data.error ? data.error : response.statusText;
    throw new Error(msg);
  }

  return data;
}

function setActiveTab(tab) {
  document.querySelectorAll('.tab').forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  document.querySelectorAll('.panel').forEach((panel) => {
    panel.classList.toggle('active', panel.id === `tab-${tab}`);
  });
  
  // Handle tasks section visibility
  const tasksSection = $('tasks-section');
  if (tab !== 'assignments') {
    // Hide tasks section when switching away from assignments
    if (tasksSection) tasksSection.style.display = 'none';
  } else if (tab === 'assignments' && state.currentAssignmentId) {
    // Show tasks section when returning to assignments if an assignment was selected
    if (tasksSection) tasksSection.style.display = 'block';
  }
}

async function loadProjects() {
  const data = await requestJson('../api/admin/projects/list.php');
  state.projects = data.projects || [];

  const body = $('projects-body');
  body.innerHTML = '';

  state.projects.forEach((p) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${p.id}</td>
      <td>${escapeHtml(p.name)}</td>
      <td>${escapeHtml(p.user_email)}</td>
      <td><span class="tag">${escapeHtml(p.visibility || 'private')}</span></td>
      <td>${escapeHtml(p.updated_at || '')}</td>
      <td>
        <div class="row-actions">
          <button class="btn" data-action="open-project" data-id="${p.id}">Open</button>
          <button class="btn warn" data-action="delete-project" data-id="${p.id}">Delete</button>
        </div>
      </td>
    `;
    body.appendChild(tr);
  });
}

async function loadAssignments() {
  const data = await requestJson('../api/assignments/list.php?all=1');
  state.assignments = data.assignments || [];
  renderAssignments();
}

function renderAssignments() {
  // Filter
  let filtered = state.assignments;
  if (state.assignmentsFilter) {
    const filter = state.assignmentsFilter.toLowerCase();
    filtered = filtered.filter(a => 
      a.title.toLowerCase().includes(filter) ||
      a.difficulty.toLowerCase().includes(filter) ||
      String(a.id).includes(filter)
    );
  }

  // Sort
  filtered.sort((a, b) => {
    const aVal = a[state.assignmentsSortBy];
    const bVal = b[state.assignmentsSortBy];
    const dir = state.assignmentsSortDir === 'asc' ? 1 : -1;
    if (aVal < bVal) return -1 * dir;
    if (aVal > bVal) return 1 * dir;
    return 0;
  });

  // Pagination
  const totalPages = Math.ceil(filtered.length / state.assignmentsPerPage);
  const start = (state.assignmentsPage - 1) * state.assignmentsPerPage;
  const page = filtered.slice(start, start + state.assignmentsPerPage);

  // Render rows
  const body = $('assignments-body');
  body.innerHTML = '';

  page.forEach((a) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${a.id}</td>
      <td>${escapeHtml(a.title)}</td>
      <td>${escapeHtml(a.difficulty)}</td>
      <td>${a.is_active ? '✓' : '✗'}</td>
      <td>
        <div style="display: flex; align-items: center; gap: 8px;">
          <span>${a.task_count}</span>
          <button class="icon-btn" data-action="select-assignment" data-id="${a.id}" title="View Tasks">📚</button>
        </div>
      </td>
      <td>
        <div style="display: flex; align-items: center; gap: 8px;">
          <span>${a.user_count ?? 0}</span>
          <button class="icon-btn" data-action="view-assignment-users" data-id="${a.id}" title="View Users">👥</button>
        </div>
      </td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" data-action="edit-assignment" data-id="${a.id}" title="Edit">✏️</button>
          <button class="icon-btn" data-action="clone-assignment" data-id="${a.id}" title="Clone">🔄</button>
          <button class="icon-btn danger" data-action="delete-assignment" data-id="${a.id}" title="Delete">🗑️</button>
        </div>
      </td>
    `;
    body.appendChild(tr);
  });

  // Update pagination
  $('assignments-page-info').textContent = `Page ${state.assignmentsPage} of ${totalPages || 1}`;
  $('assignments-prev').disabled = state.assignmentsPage <= 1;
  $('assignments-next').disabled = state.assignmentsPage >= totalPages;

  // Update sort indicators
  document.querySelectorAll('#assignments-table th.sortable').forEach(th => {
    th.classList.remove('sorted-asc', 'sorted-desc');
    if (th.dataset.sort === state.assignmentsSortBy) {
      th.classList.add(state.assignmentsSortDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
    }
  });
}

async function loadTasks(assignmentId, assignmentTitle) {
  if (!assignmentId) return;
  const data = await requestJson(`../api/tasks/list.php?assignment_id=${assignmentId}&include_expected=1`);
  state.tasks = data.tasks || [];
  state.currentAssignmentId = assignmentId;
  state.currentAssignmentTitle = assignmentTitle;

  // Show tasks section when assignment is selected
  const tasksSection = $('tasks-section');
  if (tasksSection) tasksSection.style.display = 'block';

  $('tasks-title').textContent = `Tasks: ${assignmentTitle}`;
  $('tasks-hint').textContent = '';

  const body = $('tasks-body');
  body.innerHTML = '';

  state.tasks.forEach((t, index) => {
    const hasTests = t.test_cases ? '✓' : '✗';
    const hasSolution = t.solution_code ? '✓' : '✗';
    const modeLabel = t.validation_mode || '-';
    const isFirst = index === 0;
    const isLast = index === state.tasks.length - 1;
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="checkbox" class="task-checkbox" data-task-id="${t.id}"></td>
      <td>
        <span class="task-move-controls">
          <button class="task-move-btn" data-action="move-task-up" data-id="${t.id}" ${isFirst ? 'disabled' : ''} aria-label="Move task up">&uarr;</button>
          <button class="task-move-btn" data-action="move-task-down" data-id="${t.id}" ${isLast ? 'disabled' : ''} aria-label="Move task down">&darr;</button>
        </span>
        <span class="mono">${t.position}</span>
      </td>
      <td>${escapeHtml(t.title)}</td>
      <td><span class="tag">${escapeHtml(t.problem_type)}</span></td>
      <td>${hasTests}</td>
      <td>${hasSolution}</td>
      <td><span class="tag">${escapeHtml(modeLabel)}</span></td>
      <td>
        <div class="row-actions">
          <button class="btn" data-action="edit-task" data-id="${t.id}">Edit</button>
          <button class="btn" data-action="view-task" data-id="${t.id}">View</button>
          <button class="btn warn" data-action="delete-task" data-id="${t.id}">Delete</button>
        </div>
      </td>
    `;
    body.appendChild(tr);
  });
}

async function moveTask(taskId, direction) {
  const index = state.tasks.findIndex((t) => t.id === taskId);
  if (index === -1) return;

  const targetIndex = direction === 'up' ? index - 1 : index + 1;
  if (targetIndex < 0 || targetIndex >= state.tasks.length) return;

  const current = state.tasks[index];
  const target = state.tasks[targetIndex];

  await requestJson('../api/tasks/update.php', {
    method: 'POST',
    body: JSON.stringify({ id: current.id, position: target.position })
  });

  await requestJson('../api/tasks/update.php', {
    method: 'POST',
    body: JSON.stringify({ id: target.id, position: current.position })
  });

  await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
  await loadAssignments();
}

async function loadUsers() {
  const data = await requestJson('../api/admin/users/list.php');
  state.users = data.users || [];

  const body = $('users-body');
  body.innerHTML = '';

  state.users.forEach((u) => {
    const statusClass = u.status === 'archiviert' ? 'status arch' : 'status';
    const nextStatus = u.status === 'archiviert' ? 'aktiv' : 'archiviert';
    const label = u.status === 'archiviert' ? 'Activate' : 'Archive';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${u.id}</td>
      <td>${escapeHtml(u.email)}</td>
      <td>${escapeHtml((u.first_name || '') + ' ' + (u.last_name || ''))}</td>
      <td>${escapeHtml(u.role)}</td>
      <td><span class="${statusClass}">${escapeHtml(u.status || 'aktiv')}</span></td>
      <td>
        <div class="row-actions">
          <button class="btn" data-action="toggle-user" data-id="${u.id}" data-status="${nextStatus}">${label}</button>
        </div>
      </td>
    `;
    body.appendChild(tr);
  });
}

function resetAssignmentForm() {
  $('assignment-id').value = '';
  $('assignment-title').value = '';
  $('assignment-description').value = '';
  $('assignment-difficulty').value = 'beginner';
  $('assignment-active').value = 'true';
  const titleEl = $('assignment-modal-title');
  if (titleEl) titleEl.textContent = 'New Assignment';
}

function resetTaskForm() {
  $('task-title').value = '';
  $('task-description').value = '';
  $('task-position').value = '';
  $('task-template').value = '';
  $('task-hint1').value = '';
  $('task-hint2').value = '';
  $('task-hint3').value = '';
  $('task-stoff').value = '';
  $('task-validation-mode').value = '';
  $('task-test-cases').value = '';
  $('task-solution').value = '';
  testCasesData = [];
  renderTestCases(testCasesData, 'tests-container');
}

function openAssignmentModal() {
  resetAssignmentForm();
  $('assignment-modal').classList.add('active');
}

function closeAssignmentModal() {
  $('assignment-modal').classList.remove('active');
  resetAssignmentForm();
}

function openNewTaskModal() {
  if (!state.currentAssignmentId) {
    alert('Select an assignment first');
    return;
  }
  resetTaskForm();
  $('task-create-modal').classList.add('active');
}

function closeNewTaskModal() {
  $('task-create-modal').classList.remove('active');
}

async function handleAssignmentSubmit(e) {
  e.preventDefault();

  const id = $('assignment-id').value.trim();
  const payload = {
    title: $('assignment-title').value.trim(),
    description: $('assignment-description').value.trim(),
    difficulty: $('assignment-difficulty').value,
    is_active: $('assignment-active').value === 'true'
  };

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  if (id) {
    payload.id = parseInt(id, 10);
    await requestJson('../api/assignments/update.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  } else {
    await requestJson('../api/assignments/create.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  resetAssignmentForm();
  await loadAssignments();
  closeAssignmentModal();
}

async function handleTaskSubmit(e) {
  e.preventDefault();
  if (!state.currentAssignmentId) {
    alert('Select an assignment first');
    return;
  }

  const payload = {
    assignment_id: state.currentAssignmentId,
    title: $('task-title').value.trim(),
    description: $('task-description').value.trim(),
    position: $('task-position').value ? parseInt($('task-position').value, 10) : null,
    problem_type: $('task-type').value,
    code_template: $('task-template').value,
    hint1: $('task-hint1').value,
    hint2: $('task-hint2').value,
    hint3: $('task-hint3').value,
    stoff: $('task-stoff').value,
    validation_mode: $('task-validation-mode').value || null,
    test_cases: $('task-test-cases').value.trim() || null,
    solution_code: $('task-solution').value.trim() || null
  };

  // If builder has data, prefer it over manual JSON
  if (Array.isArray(testCasesData) && testCasesData.length > 0) {
    // Special case: if single intelligent test, save as object (not array)
    if (testCasesData.length === 1 && testCasesData[0].type === 'intelligent') {
      const intelligentConfig = {...testCasesData[0]};
      delete intelligentConfig.type; // Remove 'type' key when serializing as object
      payload.test_cases = JSON.stringify(intelligentConfig);
      payload.validation_mode = 'intelligent'; // Auto-set validation mode
    } else {
      payload.test_cases = JSON.stringify(testCasesData);
    }
    $('task-test-cases').value = payload.test_cases;
  }

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  // Validate test_cases JSON if provided
  if (payload.test_cases) {
    try {
      const parsed = JSON.parse(payload.test_cases);
      const error = validateIntelligentTests(parsed, payload.solution_code);
      if (error) {
        alert(error);
        return;
      }
    } catch (err) {
      alert('Invalid test_cases JSON: ' + err.message);
      return;
    }
  }

  await requestJson('../api/tasks/create.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  });

  resetTaskForm();

  await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
  await loadAssignments();
  closeNewTaskModal();
}

function escapeHtml(input) {
  if (input === null || input === undefined) return '';
  return String(input)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function validateIntelligentTests(testCases, solutionCode) {
  if (!Array.isArray(testCases)) return null;

  const allowedTypes = ['int', 'integer', 'float', 'number', 'double', 'bool', 'boolean', 'string', 'str', 'list', 'array', 'choice', 'enum', 'object', 'dict', 'map'];

  for (const test of testCases) {
    if (!test || test.type !== 'intelligent') continue;

    const mode = test.mode || 'vars';
    const testsCount = Number(test.tests ?? 5);
    if (!Number.isFinite(testsCount) || testsCount < 1) {
      return 'Intelligent: tests muss >= 1 sein';
    }

    const effectiveSolution = test.solution_code || solutionCode || '';
    if (!effectiveSolution) {
      return 'Intelligent: Musterloesung fehlt (Solution Code)';
    }

    const inputs = Array.isArray(test.inputs) ? test.inputs : [];
    for (const input of inputs) {
      if (!input || !input.name) {
        return 'Intelligent: Inputs brauchen name';
      }
      const type = String(input.type || 'int').toLowerCase();
      if (!allowedTypes.includes(type)) {
        return `Intelligent: Unbekannter Input-Typ '${type}'`;
      }
      if ((type === 'choice' || type === 'enum') && (!Array.isArray(input.values) || input.values.length === 0)) {
        return 'Intelligent: choice/enum braucht values';
      }
      if ((type === 'list' || type === 'array') && !input.element && !input.of) {
        return 'Intelligent: list/array braucht element/of Definition';
      }
      if ((type === 'object' || type === 'dict' || type === 'map') && !Array.isArray(input.fields)) {
        return 'Intelligent: object braucht fields Array';
      }
    }

    if (mode === 'vars') {
      const outputs = Array.isArray(test.outputs) ? test.outputs : [];
      if (outputs.length === 0) {
        return 'Intelligent (vars): Outputs fehlen';
      }
      for (const output of outputs) {
        if (!output || !output.name) {
          return 'Intelligent (vars): Output braucht name';
        }
        const type = String(output.type || 'int').toLowerCase();
        if (!allowedTypes.includes(type)) {
          return `Intelligent: Unbekannter Output-Typ '${type}'`;
        }
        if ((type === 'list' || type === 'array') && !output.element && !output.of) {
          return 'Intelligent: list/array Output braucht element/of';
        }
      }
    } else if (mode === 'function') {
      // Check new structure: test.function.name
      if (!test.function || !test.function.name || test.function.name.trim() === '') {
        return 'Intelligent (function): function_name fehlt';
      }
      // Validate inputs array
      if (!test.function.inputs || !Array.isArray(test.function.inputs)) {
        return 'Intelligent (function): function.inputs Array fehlt';
      }
      for (const input of test.function.inputs) {
        if (!input || !input.name) {
          return 'Intelligent (function): Input braucht name';
        }
        const type = String(input.type || 'int').toLowerCase();
        if (!allowedTypes.includes(type)) {
          return `Intelligent (function): Unbekannter Input-Typ '${type}'`;
        }
      }
      // Validate output
      if (!test.function.output || !test.function.output.type) {
        return 'Intelligent (function): function.output.type fehlt';
      }
    }
  }

  return null;
}

function openEditTaskModal(taskId) {
  const task = state.tasks.find((t) => t.id === taskId);
  if (!task) return;

  $('edit-task-id').value = task.id;
  $('edit-task-title').value = task.title || '';
  $('edit-task-description').value = task.description || '';
  $('edit-task-position').value = task.position || '';
  $('edit-task-type').value = task.problem_type || 'code_completion';
  $('edit-task-template').value = task.code_template || '';
  $('edit-task-hint1').value = task.hint1 || '';
  $('edit-task-hint2').value = task.hint2 || '';
  $('edit-task-hint3').value = task.hint3 || '';
  $('edit-task-stoff').value = task.stoff || '';
  $('edit-task-validation-mode').value = task.validation_mode || '';
  $('edit-task-test-cases').value = task.test_cases || '';
  $('edit-task-solution').value = task.solution_code || '';
  
  // Initialize editTestCasesData from JSON
  try {
    let parsedData = task.test_cases && task.test_cases.trim() 
      ? JSON.parse(task.test_cases) 
      : [];
    
    // Handle intelligent test config (single object with mode, tests, etc.)
    if (!Array.isArray(parsedData) && parsedData.mode) {
      parsedData = [{type: 'intelligent', ...parsedData}];
    }
    
    // Migrate legacy FUNCTION structure to new structure
    parsedData = migrateLegacyTestCases(parsedData);
    editTestCasesData = Array.isArray(parsedData) ? parsedData : [];
  } catch (e) {
    console.error('Failed to parse test cases:', e);
    editTestCasesData = [];
  }
  
  // Render test cases in the builder
  renderTestCases(editTestCasesData, 'edit-tests-container');

  $('task-modal').classList.add('active');
  $('modal-title').textContent = `Edit Task: ${task.title}`;
}

function closeEditTaskModal() {
  $('task-modal').classList.remove('active');
  $('edit-task-id').value = '';
  editTestCasesData = [];
}

async function handleEditTaskSubmit(e) {
  e.preventDefault();

  const taskId = parseInt($('edit-task-id').value, 10);
  if (!taskId) return;

  const payload = {
    id: taskId,
    title: $('edit-task-title').value.trim(),
    description: $('edit-task-description').value.trim(),
    position: $('edit-task-position').value ? parseInt($('edit-task-position').value, 10) : null,
    problem_type: $('edit-task-type').value,
    code_template: $('edit-task-template').value,
    hint1: $('edit-task-hint1').value,
    hint2: $('edit-task-hint2').value,
    hint3: $('edit-task-hint3').value,
    stoff: $('edit-task-stoff').value,
    validation_mode: $('edit-task-validation-mode').value || null,
    test_cases: $('edit-task-test-cases').value.trim() || null,
    solution_code: $('edit-task-solution').value.trim() || null
  };

  // If builder has data, prefer it over manual JSON
  if (Array.isArray(editTestCasesData) && editTestCasesData.length > 0) {
    // Special case: if single intelligent test, save as object (not array)
    if (editTestCasesData.length === 1 && editTestCasesData[0].type === 'intelligent') {
      const intelligentConfig = {...editTestCasesData[0]};
        payload.validation_mode = 'intelligent'; // Auto-set validation mode
      delete intelligentConfig.type; // Remove 'type' key when serializing as object
      payload.test_cases = JSON.stringify(intelligentConfig);
    } else {
      payload.test_cases = JSON.stringify(editTestCasesData);
    }
    $('edit-task-test-cases').value = payload.test_cases;
  }

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  // Validate test_cases JSON if provided
  if (payload.test_cases) {
    try {
      const parsed = JSON.parse(payload.test_cases);
      const error = validateIntelligentTests(parsed, payload.solution_code);
      if (error) {
        alert(error);
        return;
      }
    } catch (err) {
      alert('Invalid test_cases JSON: ' + err.message);
      return;
    }
  }

  await requestJson('../api/tasks/update.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  });

  closeEditTaskModal();
  await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
  await loadAssignments();
}

function bindEvents() {
  document.querySelectorAll('.tab').forEach((btn) => {
    btn.addEventListener('click', () => setActiveTab(btn.dataset.tab));
  });

  $('assignment-form').addEventListener('submit', handleAssignmentSubmit);
  $('task-form').addEventListener('submit', handleTaskSubmit);
  $('task-edit-form').addEventListener('submit', handleEditTaskSubmit);

  const openAssignmentBtn = $('open-assignment-modal');
  if (openAssignmentBtn) {
    openAssignmentBtn.addEventListener('click', openAssignmentModal);
  }

  const assignmentCloseBtn = $('assignment-close-btn');
  if (assignmentCloseBtn) {
    assignmentCloseBtn.addEventListener('click', closeAssignmentModal);
  }

  const assignmentCancelBtn = $('assignment-cancel');
  if (assignmentCancelBtn) {
    assignmentCancelBtn.addEventListener('click', closeAssignmentModal);
  }

  const openTaskBtn = $('open-task-modal');
  if (openTaskBtn) {
    openTaskBtn.addEventListener('click', openNewTaskModal);
  }

  // Import task button (only visible when tasks are loaded)
  const importTaskBtn = $('import-task-btn');
  const importTaskFileInput = $('import-task-file-input');
  
  if (importTaskBtn && importTaskFileInput) {
    importTaskBtn.addEventListener('click', () => {
      if (!state.currentAssignmentId) {
        alert('Bitte wählen Sie zuerst ein Assignment aus (Tasks-Button klicken)');
        return;
      }
      importTaskFileInput.click();
    });
    
    importTaskFileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      if (!state.currentAssignmentId) {
        alert('Kein Assignment ausgewählt');
        importTaskFileInput.value = '';
        return;
      }
      
      try {
        const text = await file.text();
        const jsonData = JSON.parse(text);
        await importTask(jsonData, state.currentAssignmentId);
      } catch (err) {
        alert('Fehler beim Lesen der Datei: ' + err.message);
      }
      
      // Reset input
      importTaskFileInput.value = '';
    });
  }

  const taskCreateCloseBtn = $('task-create-close-btn');
  if (taskCreateCloseBtn) {
    taskCreateCloseBtn.addEventListener('click', closeNewTaskModal);
  }

  const taskCreateCancelBtn = $('task-create-cancel-btn');
  if (taskCreateCancelBtn) {
    taskCreateCancelBtn.addEventListener('click', closeNewTaskModal);
  }

  // Modal close buttons
  $('close-modal-btn').addEventListener('click', closeEditTaskModal);
  $('cancel-modal-btn').addEventListener('click', closeEditTaskModal);
  
  // Close modal on background click
  $('task-modal').addEventListener('click', (e) => {
    if (e.target === $('task-modal')) {
      closeEditTaskModal();
    }
  });

  const assignmentModal = $('assignment-modal');
  if (assignmentModal) {
    assignmentModal.addEventListener('click', (e) => {
      if (e.target === assignmentModal) {
        closeAssignmentModal();
      }
    });
  }

  const taskCreateModal = $('task-create-modal');
  if (taskCreateModal) {
    taskCreateModal.addEventListener('click', (e) => {
      if (e.target === taskCreateModal) {
        closeNewTaskModal();
      }
    });
  }

  $('logout-btn').addEventListener('click', async () => {
    await requestJson('../api/auth/logout.php', { method: 'POST' });
    window.location.href = 'login.php';
  });

  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    const id = parseInt(btn.dataset.id, 10);

    if (action === 'open-project') {
      window.location.href = `editor.php?project_id=${id}`;
    }

    if (action === 'delete-project') {
      if (!confirm('Delete project?')) return;
      await requestJson('../api/projects/delete.php', {
        method: 'POST',
        body: JSON.stringify({ id })
      });
      await loadProjects();
    }

    if (action === 'edit-assignment') {
      const a = state.assignments.find((x) => x.id === id);
      if (!a) return;
      $('assignment-id').value = a.id;
      $('assignment-title').value = a.title || '';
      $('assignment-description').value = a.description || '';
      $('assignment-difficulty').value = a.difficulty || 'beginner';
      $('assignment-active').value = a.is_active ? 'true' : 'false';
      $('assignment-modal-title').textContent = `Edit Assignment #${a.id}`;
      $('assignment-modal').classList.add('active');
    }

    if (action === 'clone-assignment') {
      if (!confirm('Clone this assignment with all tasks?')) return;
      try {
        const response = await requestJson(`../api/admin/assignments/clone.php?id=${id}`);
        if (response.ok) {
          alert(`Assignment cloned successfully!\\n${response.task_count} tasks copied.`);
          await loadAssignments();
        } else {
          throw new Error(response.error);
        }
      } catch (err) {
        alert('Clone failed: ' + err.message);
      }
    }

    if (action === 'delete-assignment') {
      if (!confirm('Delete assignment?')) return;
      await requestJson(`../api/assignments/delete.php?id=${id}`, { method: 'DELETE' });
      await loadAssignments();
    }

    if (action === 'select-assignment') {
      const a = state.assignments.find((x) => x.id === id);
      if (!a) return;
      await loadTasks(a.id, a.title);
    }

    if (action === 'view-assignment-users') {
      window.location.href = `evaluation.php?assignment_id=${id}`;
    }

    if (action === 'move-task-up') {
      await moveTask(id, 'up');
    }

    if (action === 'move-task-down') {
      await moveTask(id, 'down');
    }

    if (action === 'delete-task') {
      if (!confirm('Delete task?')) return;
      await requestJson(`../api/tasks/delete.php?id=${id}`, { method: 'DELETE' });
      await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
      await loadAssignments();
    }

    if (action === 'edit-task') {
      openEditTaskModal(id);
    }

    if (action === 'view-task') {
      const task = state.tasks.find((t) => t.id === id);
      if (!task) return;
      
      const details = `
TASK DETAILS
============
Title: ${task.title}
Position: ${task.position}
Type: ${task.problem_type}
Validation Mode: ${task.validation_mode || 'none'}

Description:
${task.description || '(no description)'}

Code Template:
${task.code_template || '(none)'}

Hints:
Hint 1: ${task.hint1 || '(none)'}
Hint 2: ${task.hint2 || '(none)'}
Hint 3: ${task.hint3 || '(none)'}

Test Cases:
${task.test_cases ? JSON.stringify(JSON.parse(task.test_cases), null, 2) : '(none)'}

Solution Code:
${task.solution_code ? task.solution_code.substring(0, 200) + '...' : '(none)'}
      `;
      
      alert(details);
    }

    if (action === 'toggle-user') {
      const status = btn.dataset.status;
      await requestJson('../api/admin/users/update.php', {
        method: 'POST',
        body: JSON.stringify({ id, status })
      });
      await loadUsers();
    }
  });

}

async function init() {
  bindEvents();
  await Promise.all([loadProjects(), loadAssignments(), loadUsers()]);
}

init().catch((err) => {
  console.error(err);
  alert(err.message || 'Failed to load admin dashboard');
});

// ===================================================================
// TEST CASES BUILDER GUI
// ===================================================================

let testCasesData = []; // CREATE form
let editTestCasesData = []; // EDIT form

// Initialize Test Cases Builder for CREATE form
function initTestCasesBuilder() {
  const addBtn = document.getElementById('add-test-btn');
  const generateBtn = document.getElementById('generate-json-btn');
  const typeSelector = document.getElementById('test-type-selector');
  
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      const type = typeSelector.value;
      addTestCase(type, testCasesData, 'tests-container');
    });
  }
  
  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      generateJSON(testCasesData, 'task-test-cases');
    });
  }
}

// Initialize Test Cases Builder for EDIT form
function initEditTestCasesBuilder() {
  const addBtn = document.getElementById('edit-add-test-btn');
  const generateBtn = document.getElementById('edit-generate-json-btn');
  const typeSelector = document.getElementById('edit-test-type-selector');
  
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      const type = typeSelector.value;
      addTestCase(type, editTestCasesData, 'edit-tests-container');
    });
  }
  
  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      generateJSON(editTestCasesData, 'edit-task-test-cases');
    });
  }
}

// Add a test case to the GUI
function addTestCase(type, dataArray, containerId) {
  const testCase = { type };
  
  // Initialize type-specific default structures
  if (type === 'output') {
    testCase.expected = [];
  } else if (type === 'function') {
    testCase.function_name = '';
    testCase.test_cases = [{ args: [], expected: '' }]; // Start with one empty test case
  } else if (type === 'variable') {
    testCase.init_var_names = [];
    testCase.expected_var_names = [];
    testCase.test_cases = [{ init_values: [], expected_values: [] }]; // Start with one empty test case
  } else if (type === 'intelligent') {
    testCase.mode = 'function';
    testCase.tests = 5;
    testCase.seed = '';
    testCase.tolerance = 0.000001;
    testCase.function = {
      name: '',
      inputs: [],
      output: { type: 'int' }
    };
  } else if (type === 'code_check') {
    testCase.keywords = [];
    testCase.operator = 'AND';
    testCase.feedback = '';
  }
  
  const idx = dataArray.length;
  dataArray.push(testCase);
  
  renderTestCases(dataArray, containerId);
}

// Render all test cases
function renderTestCases(dataArray, containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  
  // Ensure dataArray is an array
  if (!Array.isArray(dataArray)) {
    console.warn('renderTestCases: dataArray is not an array', dataArray);
    dataArray = [];
  }
  
  container.innerHTML = dataArray.map((test, idx) => {
    return renderTestCaseHTML(test, idx, containerId);
  }).join('');
  
  // Bind event handlers
  bindTestCaseEvents(dataArray, containerId);
}

// Render single test case HTML
function renderTestCaseHTML(test, idx, containerId) {
  const type = test.type || 'output';
  
  let html = `
    <div class="test-case-item" data-idx="${idx}" style="border:1px solid #e5e7eb; padding:12px; margin-bottom:10px; border-radius:6px; background:#f9fafb;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <strong>Test #${idx + 1} - ${type.toUpperCase()}</strong>
        <button type="button" class="btn-remove-test" data-idx="${idx}" style="background:#ef4444; color:white; padding:4px 8px; border:none; border-radius:4px; cursor:pointer;">✕ Remove</button>
      </div>
  `;
  
  if (type === 'output') {
    const patterns = test.expected && Array.isArray(test.expected) ? test.expected : (test.expected ? [test.expected] : []);
    
    html += `
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Expected Output Patterns (Wildcards: * = any chars, ? = one char):
        </label>
        <div class="patterns-container" data-idx="${idx}" style="margin-bottom:8px;">
    `;
    
    // Render each pattern in its own field
    patterns.forEach((pattern, pidx) => {
      html += `
        <div class="pattern-item" data-pidx="${pidx}" style="margin-bottom:6px; display:flex; gap:6px;">
          <textarea class="pattern-input" data-pidx="${pidx}" 
                    placeholder="e.g. Output with * OR exact match OR test?" 
                    style="flex:1; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; min-height:60px;">${escapeHtml(pattern)}</textarea>
          <button type="button" class="btn-remove-pattern" data-pidx="${pidx}" style="background:#ef4444; color:white; padding:6px 8px; border:none; border-radius:4px; cursor:pointer; height:fit-content;">✕</button>
        </div>
      `;
    });
    
    html += `
        </div>
        <button type="button" class="btn-add-pattern" data-idx="${idx}" style="background:#3b82f6; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
          + Weiteres Pattern
        </button>
        <div style="font-size:11px; color:#666; margin-top:6px;">
          Jedes Feld = ein Pattern. Test bestanden wenn EINES der Patterns passt (ODER-Logik). Patterns können mehrzeilig sein.
        </div>
      </div>
    `;
  } else if (type === 'function') {
    html += `
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Function Name:
        </label>
        <input type="text" class="function-name-input" data-idx="${idx}" value="${test.function_name || ''}" 
               placeholder="e.g. quadrat" 
               style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:12px;">
      </div>
      
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Test Cases (Arguments → Expected Result):
        </label>
        <div class="function-test-cases-container" data-idx="${idx}" style="margin-bottom:8px;">
    `;
    
    // Render each test case
    const testCases = test.test_cases && Array.isArray(test.test_cases) ? test.test_cases : [];
    testCases.forEach((tc, tcIdx) => {
      html += `
        <div class="function-test-case" data-tcidx="${tcIdx}" style="margin-bottom:10px; border:1px solid #e5e7eb; padding:10px; border-radius:4px; background:#f9fafb;">
          <div style="display:grid; grid-template-columns: 1fr 1fr auto; gap:8px; margin-bottom:8px;">
            <div>
              <label style="display:block; font-size:11px; color:#666; margin-bottom:3px;">Args (comma-separated):</label>
              <input type="text" class="function-args-input" data-tcidx="${tcIdx}" value="${tc.args || ''}" 
                     placeholder="5, 10" 
                     style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; font-size:12px;">
            </div>
            <div>
              <label style="display:block; font-size:11px; color:#666; margin-bottom:3px;">Expected:</label>
              <input type="text" class="function-expected-input" data-tcidx="${tcIdx}" value="${tc.expected || ''}" 
                     placeholder="25" 
                     style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; font-size:12px;">
            </div>
            <button type="button" class="btn-remove-function-tc" data-tcidx="${tcIdx}" style="background:#ef4444; color:white; padding:6px 8px; border:none; border-radius:4px; cursor:pointer; height:fit-content; margin-top:20px;">✕</button>
          </div>
        </div>
      `;
    });
    
    html += `
        </div>
        <button type="button" class="btn-add-function-tc" data-idx="${idx}" style="background:#3b82f6; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
          + Weiterer Test Case
        </button>
        <div style="font-size:11px; color:#666; margin-top:6px;">
          Funktion wird mit jedem Test Case aufgerufen. Test bestanden wenn alle Cases den erwarteten Wert zurückgeben.
        </div>
      </div>
    `;
  } else if (type === 'variable') {
    html += `
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Init Variable Names (comma-separated):
        </label>
        <input type="text" class="variable-init-names-input" data-idx="${idx}" value="${(test.init_var_names || []).join(', ')}" 
               placeholder="a, b, c" 
               style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:12px;">
      </div>
      
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Expected Variable Names (comma-separated):
        </label>
        <input type="text" class="variable-expected-names-input" data-idx="${idx}" value="${(test.expected_var_names || []).join(', ')}" 
               placeholder="summe, produkt" 
               style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:12px;">
      </div>
      
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Test Cases (Init Values → Expected Values):
        </label>
        <div class="variable-test-cases-container" data-idx="${idx}" style="margin-bottom:8px;">
    `;
    
    // Render each test case
    const testCases = test.test_cases && Array.isArray(test.test_cases) ? test.test_cases : [];
    testCases.forEach((tc, tcIdx) => {
      html += `
        <div class="variable-test-case" data-tcidx="${tcIdx}" style="margin-bottom:10px; border:1px solid #e5e7eb; padding:10px; border-radius:4px; background:#f9fafb;">
          <div style="display:grid; grid-template-columns: 1fr 1fr auto; gap:8px; margin-bottom:8px;">
            <div>
              <label style="display:block; font-size:11px; color:#666; margin-bottom:3px;">Init Values (comma-separated):</label>
              <input type="text" class="variable-init-values-input" data-tcidx="${tcIdx}" value="${(tc.init_values || []).join(', ')}" 
                     placeholder="2, 4, 5" 
                     style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; font-size:12px;">
            </div>
            <div>
              <label style="display:block; font-size:11px; color:#666; margin-bottom:3px;">Expected Values (comma-separated):</label>
              <input type="text" class="variable-expected-values-input" data-tcidx="${tcIdx}" value="${(tc.expected_values || []).join(', ')}" 
                     placeholder="20, 30" 
                     style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; font-size:12px;">
            </div>
            <button type="button" class="btn-remove-variable-tc" data-tcidx="${tcIdx}" style="background:#ef4444; color:white; padding:6px 8px; border:none; border-radius:4px; cursor:pointer; height:fit-content; margin-top:20px;">✕</button>
          </div>
        </div>
      `;
    });
    
    html += `
        </div>
        <button type="button" class="btn-add-variable-tc" data-idx="${idx}" style="background:#3b82f6; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
          + Weiterer Test Case
        </button>
        <div style="font-size:11px; color:#666; margin-top:6px;">
          Jeder Test Case setzt die Init-Variablen, führt den Code aus und prüft die Expected-Variablen.
        </div>
      </div>
    `;
  } else if (type === 'code_check') {
    // Code Pattern Check
    const keywords = test.keywords && Array.isArray(test.keywords) ? test.keywords : (test.keywords ? [test.keywords] : []);
    const operator = test.operator || 'AND';
    const feedback = test.feedback || '';
    
    html += `
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Keywords to check (comma-separated):
        </label>
        <input type="text" class="code-check-keywords-input" data-idx="${idx}" 
               value="${keywords.join(', ')}" 
               placeholder="e.g.: for, print, if" 
               list="keyword-suggestions"
               style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:12px;">
        <datalist id="keyword-suggestions">
          <option>for</option>
          <option>while</option>
          <option>if</option>
          <option>def</option>
          <option>print</option>
          <option>import</option>
          <option>class</option>
          <option>return</option>
          <option>append</option>
          <option>len</option>
        </datalist>
      </div>
      
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Operator:
        </label>
        <select class="code-check-operator-input" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
          <option value="AND" ${operator === 'AND' ? 'selected' : ''}>AND (all keywords required)</option>
          <option value="OR" ${operator === 'OR' ? 'selected' : ''}>OR (at least one keyword)</option>
          <option value="NOT" ${operator === 'NOT' ? 'selected' : ''}>NOT (keywords forbidden)</option>
        </select>
      </div>
      
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Feedback:
        </label>
        <textarea class="code-check-feedback-input" data-idx="${idx}" 
                  placeholder="z.B.: 'Du musst eine FOR-Schleife verwenden'" 
                  style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; min-height:60px;">${escapeHtml(feedback)}</textarea>
      </div>
      
      <div style="font-size:11px; color:#666; margin-top:6px;">
        Operator: AND = alle Keywords müssen vorhanden sein | OR = mindestens eines | NOT = alle Keywords müssen FEHLEN (verboten).
      </div>
    `;
  } else if (type === 'intelligent') {
    const mode = test.mode || 'function';
    const tests = test.tests || 5;
    const seed = test.seed !== undefined ? test.seed : '';
    const tolerance = test.tolerance !== undefined ? test.tolerance : 0.000001;
    const inputs = test.inputs || [];
    const outputs = test.outputs || [];
    const functionDef = test.function || {};
    const solutionCode = test.solution_code || '';
    
    html += `
      <div style="margin-bottom:8px;">
        <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
          Mode:
        </label>
        <select class="intelligent-mode-input" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:12px;">
          <option value="vars" ${mode === 'vars' ? 'selected' : ''}>Variables (Vars Mode)</option>
          <option value="function" ${mode === 'function' ? 'selected' : ''}>Function (Function Mode)</option>
        </select>
      </div>
      
      <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
        <div>
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Tests Count:</label>
          <input type="number" class="intelligent-tests-input" data-idx="${idx}" value="${tests}" min="1" max="20"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
        </div>
        <div>
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Tolerance (Float):</label>
          <input type="number" class="intelligent-tolerance-input" data-idx="${idx}" value="${tolerance}" step="0.000001"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
        </div>
      </div>
    `;
    
    // Build Function Mode UI or Vars Mode UI
    if (mode === 'function') {
      html += `
        <div style="margin-bottom:12px; padding:12px; border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb;">
          <div style="font-weight:bold; font-size:13px; margin-bottom:10px;">Function Definition</div>
          
          <div style="margin-bottom:8px;">
            <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Function Name:</label>
            <input type="text" class="intelligent-fn-name-input" data-idx="${idx}" value="${escapeHtml(functionDef.name || '')}" 
                   placeholder="addiere"
                   style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
          </div>
          
          <div style="margin-bottom:8px;">
            <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">Input Parameters:</label>
            <div class="intelligent-fn-inputs-container" data-idx="${idx}">
      `;
      
      const fnInputs = functionDef.inputs || [];
      fnInputs.forEach((inp, inpIdx) => {
        html += `
              <div class="intelligent-fn-input-item" data-inpidx="${inpIdx}" style="padding:10px; margin-bottom:8px; border:1px solid #d1d5db; border-radius:4px; background:white;">
                <div style="display:grid; grid-template-columns: 2fr 2fr 1fr 1fr auto; gap:8px; align-items:end;">
                  <div>
                    <label style="display:block; font-size:11px; color:#666; margin-bottom:2px;">Name:</label>
                    <input type="text" class="intelligent-fn-input-name" data-inpidx="${inpIdx}" value="${escapeHtml(inp.name || '')}" placeholder="a"
                           style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                  </div>
                  <div>
                    <label style="display:block; font-size:11px; color:#666; margin-bottom:2px;">Type:</label>
                    <select class="intelligent-fn-input-type" data-inpidx="${inpIdx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                      <option value="int" ${inp.type === 'int' ? 'selected' : ''}>int</option>
                      <option value="float" ${inp.type === 'float' ? 'selected' : ''}>float</option>
                      <option value="bool" ${inp.type === 'bool' ? 'selected' : ''}>bool</option>
                      <option value="string" ${inp.type === 'string' ? 'selected' : ''}>string</option>
                      <option value="list" ${inp.type === 'list' ? 'selected' : ''}>list</option>
                    </select>
                  </div>
                  <div>
                    <label style="display:block; font-size:11px; color:#666; margin-bottom:2px;">Min:</label>
                    <input type="text" class="intelligent-fn-input-min" data-inpidx="${inpIdx}" value="${inp.min !== undefined ? inp.min : ''}" placeholder="0"
                           style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                  </div>
                  <div>
                    <label style="display:block; font-size:11px; color:#666; margin-bottom:2px;">Max:</label>
                    <input type="text" class="intelligent-fn-input-max" data-inpidx="${inpIdx}" value="${inp.max !== undefined ? inp.max : ''}" placeholder="100"
                           style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                  </div>
                  <button type="button" class="btn-remove-fn-input" data-inpidx="${inpIdx}" 
                          style="background:#ef4444; color:white; padding:6px 8px; border:none; border-radius:4px; cursor:pointer;">✕</button>
                </div>
              </div>
        `;
      });
      
      html += `
            </div>
            <button type="button" class="btn-add-fn-input" data-idx="${idx}" 
                    style="background:#3b82f6; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
              + Parameter hinzufügen
            </button>
          </div>
          
          <div style="margin-bottom:8px;">
            <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Output Type:</label>
            <select class="intelligent-fn-output-type" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
              <option value="int" ${functionDef.output?.type === 'int' ? 'selected' : ''}>int</option>
              <option value="float" ${functionDef.output?.type === 'float' ? 'selected' : ''}>float</option>
              <option value="bool" ${functionDef.output?.type === 'bool' ? 'selected' : ''}>bool</option>
              <option value="string" ${functionDef.output?.type === 'string' ? 'selected' : ''}>string</option>
              <option value="list" ${functionDef.output?.type === 'list' ? 'selected' : ''}>list</option>
            </select>
          </div>
        </div>
      `;
    } else {
      // Vars mode
      html += `
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
            Inputs (JSON für Vars Mode):
          </label>
          <textarea class="intelligent-inputs-input" data-idx="${idx}" 
                    placeholder='[{"name": "x", "type": "int", "min": 1, "max": 10}]'
                    style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; min-height:80px; font-size:11px;">${escapeHtml(JSON.stringify(inputs, null, 2))}</textarea>
        </div>
        
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold;">
            Outputs (JSON):
          </label>
          <textarea class="intelligent-outputs-input" data-idx="${idx}" 
                    placeholder='[{"name": "result", "type": "int"}]'
                    style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; min-height:80px; font-size:11px;">${escapeHtml(JSON.stringify(outputs, null, 2))}</textarea>
        </div>
      `;
    }
    
    html += `
      <div style="font-size:11px; color:#666; margin-top:6px; padding:10px; background:#fef3c7; border-radius:4px; border-left:3px solid #f59e0b;">
        <strong>💡 Musterlösung:</strong> Im Feld "Solution Code" (weiter unten im Formular) eingeben.
      </div>
    `;
  }
  
  html += `</div>`;
  return html;
}

// Bind events for test case inputs
function bindTestCaseEvents(dataArray, containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  
  // Handle OUTPUT pattern inputs
  container.querySelectorAll('.pattern-input').forEach(textarea => {
    textarea.addEventListener('input', (e) => {
      const target = e.target;
      const testIdx = parseInt(target.closest('.patterns-container').dataset.idx);
      const patternIdx = parseInt(target.dataset.pidx);
      const value = target.value;
      
      if (!Array.isArray(dataArray[testIdx]['expected'])) {
        dataArray[testIdx]['expected'] = [];
      }
      dataArray[testIdx]['expected'][patternIdx] = value;
    });
  });
  
  // Handle Add Pattern button
  container.querySelectorAll('.btn-add-pattern').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      if (!Array.isArray(dataArray[idx]['expected'])) {
        dataArray[idx]['expected'] = [];
      }
      dataArray[idx]['expected'].push(''); // Add empty pattern
      renderTestCases(dataArray, containerId);
    });
  });
  
  // Handle Remove Pattern button
  container.querySelectorAll('.btn-remove-pattern').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const patternBtn = e.target.closest('.btn-remove-pattern');
      const patternContainer = patternBtn.closest('.patterns-container');
      const testIdx = parseInt(patternContainer.dataset.idx);
      const patternIdx = parseInt(patternBtn.dataset.pidx);
      
      if (Array.isArray(dataArray[testIdx]['expected'])) {
        dataArray[testIdx]['expected'].splice(patternIdx, 1);
        renderTestCases(dataArray, containerId);
      }
    });
  });

  // Handle FUNCTION test case inputs
  container.querySelectorAll('.function-name-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testIdx = parseInt(e.target.dataset.idx);
      dataArray[testIdx]['function_name'] = e.target.value;
    });
  });

  container.querySelectorAll('.function-args-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testContainer = e.target.closest('.function-test-cases-container');
      const testIdx = parseInt(testContainer.dataset.idx);
      const tcIdx = parseInt(e.target.dataset.tcidx);
      const value = e.target.value;
      
      if (!Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'] = [];
      }
      
      if (!dataArray[testIdx]['test_cases'][tcIdx]) {
        dataArray[testIdx]['test_cases'][tcIdx] = {};
      }
      
      // Parse comma-separated args with type conversion
      try {
        const parsed = value.split(',').map(v => {
          const trimmed = v.trim();
          if (trimmed === 'true') return true;
          if (trimmed === 'false') return false;
          if (!isNaN(trimmed) && trimmed !== '') return Number(trimmed);
          return trimmed.replace(/^["']|["']$/g, ''); // Remove quotes
        });
        dataArray[testIdx]['test_cases'][tcIdx]['args'] = parsed;
      } catch (e) {
        dataArray[testIdx]['test_cases'][tcIdx]['args'] = [value];
      }
    });
  });

  container.querySelectorAll('.function-expected-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testContainer = e.target.closest('.function-test-cases-container');
      const testIdx = parseInt(testContainer.dataset.idx);
      const tcIdx = parseInt(e.target.dataset.tcidx);
      const value = e.target.value;
      
      if (!Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'] = [];
      }
      
      if (!dataArray[testIdx]['test_cases'][tcIdx]) {
        dataArray[testIdx]['test_cases'][tcIdx] = {};
      }
      
      // Auto-convert booleans and numbers
      let expectedValue = value;
      if (value.toLowerCase() === 'true') expectedValue = true;
      else if (value.toLowerCase() === 'false') expectedValue = false;
      else if (!isNaN(value) && value !== '') expectedValue = Number(value);
      
      dataArray[testIdx]['test_cases'][tcIdx]['expected'] = expectedValue;
    });
  });

  // Handle Add Function Test Case button
  container.querySelectorAll('.btn-add-function-tc').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const testIdx = parseInt(btn.dataset.idx);
      if (!Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'] = [];
      }
      dataArray[testIdx]['test_cases'].push({ args: [], expected: '' }); // Add empty test case
      renderTestCases(dataArray, containerId);
    });
  });

  // Handle Remove Function Test Case button
  container.querySelectorAll('.btn-remove-function-tc').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const tcBtn = e.target.closest('.btn-remove-function-tc');
      const testContainer = tcBtn.closest('.function-test-cases-container');
      const testIdx = parseInt(testContainer.dataset.idx);
      const tcIdx = parseInt(tcBtn.dataset.tcidx);
      
      if (Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'].splice(tcIdx, 1);
        renderTestCases(dataArray, containerId);
      }
    });
  });

  // Handle VARIABLE test case inputs
  container.querySelectorAll('.variable-init-names-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testIdx = parseInt(e.target.dataset.idx);
      const value = e.target.value;
      // Parse comma-separated variable names
      const names = value.split(',').map(n => n.trim()).filter(n => n.length > 0);
      dataArray[testIdx]['init_var_names'] = names;
    });
  });

  container.querySelectorAll('.variable-expected-names-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testIdx = parseInt(e.target.dataset.idx);
      const value = e.target.value;
      // Parse comma-separated variable names
      const names = value.split(',').map(n => n.trim()).filter(n => n.length > 0);
      dataArray[testIdx]['expected_var_names'] = names;
    });
  });

  container.querySelectorAll('.variable-init-values-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testContainer = e.target.closest('.variable-test-cases-container');
      const testIdx = parseInt(testContainer.dataset.idx);
      const tcIdx = parseInt(e.target.dataset.tcidx);
      const value = e.target.value;
      
      if (!Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'] = [];
      }
      
      if (!dataArray[testIdx]['test_cases'][tcIdx]) {
        dataArray[testIdx]['test_cases'][tcIdx] = {};
      }
      
      // Parse comma-separated values with type conversion
      try {
        const parsed = value.split(',').map(v => {
          const trimmed = v.trim();
          if (trimmed === 'true') return true;
          if (trimmed === 'false') return false;
          if (!isNaN(trimmed) && trimmed !== '') return Number(trimmed);
          return trimmed.replace(/^["']|["']$/g, ''); // Remove quotes
        });
        dataArray[testIdx]['test_cases'][tcIdx]['init_values'] = parsed;
      } catch (e) {
        dataArray[testIdx]['test_cases'][tcIdx]['init_values'] = [value];
      }
    });
  });

  container.querySelectorAll('.variable-expected-values-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testContainer = e.target.closest('.variable-test-cases-container');
      const testIdx = parseInt(testContainer.dataset.idx);
      const tcIdx = parseInt(e.target.dataset.tcidx);
      const value = e.target.value;
      
      if (!Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'] = [];
      }
      
      if (!dataArray[testIdx]['test_cases'][tcIdx]) {
        dataArray[testIdx]['test_cases'][tcIdx] = {};
      }
      
      // Parse comma-separated values with type conversion
      try {
        const parsed = value.split(',').map(v => {
          const trimmed = v.trim();
          if (trimmed === 'true') return true;
          if (trimmed === 'false') return false;
          if (!isNaN(trimmed) && trimmed !== '') return Number(trimmed);
          return trimmed.replace(/^["']|["']$/g, ''); // Remove quotes
        });
        dataArray[testIdx]['test_cases'][tcIdx]['expected_values'] = parsed;
      } catch (e) {
        dataArray[testIdx]['test_cases'][tcIdx]['expected_values'] = [value];
      }
    });
  });

  // Handle Add Variable Test Case button
  container.querySelectorAll('.btn-add-variable-tc').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const testIdx = parseInt(btn.dataset.idx);
      if (!Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'] = [];
      }
      dataArray[testIdx]['test_cases'].push({ init_values: [], expected_values: [] }); // Add empty test case
      renderTestCases(dataArray, containerId);
    });
  });

  // Handle Remove Variable Test Case button
  container.querySelectorAll('.btn-remove-variable-tc').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const tcBtn = e.target.closest('.btn-remove-variable-tc');
      const testContainer = tcBtn.closest('.variable-test-cases-container');
      const testIdx = parseInt(testContainer.dataset.idx);
      const tcIdx = parseInt(tcBtn.dataset.tcidx);
      
      if (Array.isArray(dataArray[testIdx]['test_cases'])) {
        dataArray[testIdx]['test_cases'].splice(tcIdx, 1);
        renderTestCases(dataArray, containerId);
      }
    });
  });

  // Handle INTELLIGENT inputs
  container.querySelectorAll('.intelligent-mode-input').forEach(select => {
    select.addEventListener('change', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      dataArray[idx]['mode'] = e.target.value;
      // Re-render to show/hide appropriate fields
      renderTestCases(dataArray, containerId);
    });
  });

  container.querySelectorAll('.intelligent-tests-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const value = parseInt(e.target.value, 10);
      dataArray[idx]['tests'] = Number.isFinite(value) ? value : 5;
    });
  });

  container.querySelectorAll('.intelligent-tolerance-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const value = parseFloat(e.target.value);
      dataArray[idx]['tolerance'] = Number.isFinite(value) ? value : 0.000001;
    });
  });

  container.querySelectorAll('.intelligent-inputs-input').forEach(textarea => {
    textarea.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const mode = dataArray[idx]['mode'] || 'function';
      try {
        const parsed = JSON.parse(e.target.value);
        if (mode === 'function') {
          dataArray[idx]['function'] = parsed;
          delete dataArray[idx]['inputs']; // Remove old inputs field
        } else {
          dataArray[idx]['inputs'] = parsed;
          delete dataArray[idx]['function']; // Remove old function field
        }
      } catch {
        if (mode === 'function') {
          dataArray[idx]['function'] = {};
        } else {
          dataArray[idx]['inputs'] = [];
        }
      }
    });
  });

  container.querySelectorAll('.intelligent-outputs-input').forEach(textarea => {
    textarea.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      try {
        dataArray[idx]['outputs'] = JSON.parse(e.target.value);
      } catch {
        dataArray[idx]['outputs'] = [];
      }
    });
  });

  // Handle INTELLIGENT FUNCTION UI fields
  container.querySelectorAll('.intelligent-fn-name-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      dataArray[idx]['function']['name'] = e.target.value;
    });
  });

  container.querySelectorAll('.intelligent-fn-input-name').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.closest('.test-case-item').dataset.idx);
      const inpIdx = parseInt(e.target.dataset.inpidx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      if (!dataArray[idx]['function']['inputs']) dataArray[idx]['function']['inputs'] = [];
      if (!dataArray[idx]['function']['inputs'][inpIdx]) dataArray[idx]['function']['inputs'][inpIdx] = {};
      dataArray[idx]['function']['inputs'][inpIdx]['name'] = e.target.value;
    });
  });

  container.querySelectorAll('.intelligent-fn-input-type').forEach(select => {
    select.addEventListener('change', (e) => {
      const idx = parseInt(e.target.closest('.test-case-item').dataset.idx);
      const inpIdx = parseInt(e.target.dataset.inpidx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      if (!dataArray[idx]['function']['inputs']) dataArray[idx]['function']['inputs'] = [];
      if (!dataArray[idx]['function']['inputs'][inpIdx]) dataArray[idx]['function']['inputs'][inpIdx] = {};
      dataArray[idx]['function']['inputs'][inpIdx]['type'] = e.target.value;
    });
  });

  container.querySelectorAll('.intelligent-fn-input-min').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.closest('.test-case-item').dataset.idx);
      const inpIdx = parseInt(e.target.dataset.inpidx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      if (!dataArray[idx]['function']['inputs']) dataArray[idx]['function']['inputs'] = [];
      if (!dataArray[idx]['function']['inputs'][inpIdx]) dataArray[idx]['function']['inputs'][inpIdx] = {};
      const val = e.target.value;
      dataArray[idx]['function']['inputs'][inpIdx]['min'] = val === '' ? undefined : (isNaN(val) ? val : Number(val));
    });
  });

  container.querySelectorAll('.intelligent-fn-input-max').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.closest('.test-case-item').dataset.idx);
      const inpIdx = parseInt(e.target.dataset.inpidx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      if (!dataArray[idx]['function']['inputs']) dataArray[idx]['function']['inputs'] = [];
      if (!dataArray[idx]['function']['inputs'][inpIdx]) dataArray[idx]['function']['inputs'][inpIdx] = {};
      const val = e.target.value;
      dataArray[idx]['function']['inputs'][inpIdx]['max'] = val === '' ? undefined : (isNaN(val) ? val : Number(val));
    });
  });

  container.querySelectorAll('.btn-add-fn-input').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const idx = parseInt(btn.dataset.idx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      if (!dataArray[idx]['function']['inputs']) dataArray[idx]['function']['inputs'] = [];
      dataArray[idx]['function']['inputs'].push({ name: '', type: 'int', min: 0, max: 100 });
      renderTestCases(dataArray, containerId);
    });
  });

  container.querySelectorAll('.btn-remove-fn-input').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const tcBtn = e.target.closest('.btn-remove-fn-input');
      const idx = parseInt(tcBtn.closest('.test-case-item').dataset.idx);
      const inpIdx = parseInt(tcBtn.dataset.inpidx);
      
      if (dataArray[idx]['function'] && Array.isArray(dataArray[idx]['function']['inputs'])) {
        dataArray[idx]['function']['inputs'].splice(inpIdx, 1);
        renderTestCases(dataArray, containerId);
      }
    });
  });

  container.querySelectorAll('.intelligent-fn-output-type').forEach(select => {
    select.addEventListener('change', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      if (!dataArray[idx]['function']['output']) dataArray[idx]['function']['output'] = {};
      dataArray[idx]['function']['output']['type'] = e.target.value;
    });
  });

  // Handle Code Check Keywords input
  container.querySelectorAll('.code-check-keywords-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const value = e.target.value;
      const keywords = value.split(',').map(k => k.trim()).filter(k => k !== '');
      dataArray[idx]['keywords'] = keywords;
    });
  });

  // Handle Code Check Operator dropdown
  container.querySelectorAll('.code-check-operator-input').forEach(select => {
    select.addEventListener('change', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const value = e.target.value;
      dataArray[idx]['operator'] = value;
    });
  });

  // Handle Code Check Feedback input
  container.querySelectorAll('.code-check-feedback-input').forEach(textarea => {
    textarea.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const value = e.target.value;
      dataArray[idx]['feedback'] = value;
    });
  });
  
  // Input changes (function/variable fields)
  container.querySelectorAll('.test-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const field = e.target.dataset.field;
      const value = e.target.value;
      
      if (field === 'args') {
        // Parse comma-separated args
        try {
          const parsed = value.split(',').map(v => {
            const trimmed = v.trim();
            if (trimmed === 'true') return true;
            if (trimmed === 'false') return false;
            if (!isNaN(trimmed) && trimmed !== '') return Number(trimmed);
            return trimmed.replace(/^["']|["']$/g, ''); // Remove quotes
          });
          dataArray[idx][field] = parsed;
        } catch (e) {
          dataArray[idx][field] = [value];
        }
      } else if (field === 'init_vars' || field === 'expected_vars') {
        // Parse JSON
        try {
          dataArray[idx][field] = JSON.parse(value);
        } catch (e) {
          dataArray[idx][field] = value; // Keep as string if invalid
        }
      } else if (field === 'expected') {
        // For FUNCTION: Auto-convert booleans and numbers
        if (value === 'true') dataArray[idx][field] = true;
        else if (value === 'false') dataArray[idx][field] = false;
        else if (!isNaN(value) && value.trim() !== '') dataArray[idx][field] = Number(value);
        else dataArray[idx][field] = value;
      } else {
        dataArray[idx][field] = value;
      }
    });
  });
  
  // Remove buttons
  container.querySelectorAll('.btn-remove-test').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      dataArray.splice(idx, 1);
      renderTestCases(dataArray, containerId);
    });
  });
}

// Generate JSON from GUI
function generateJSON(dataArray, textareaId) {
  const textarea = document.getElementById(textareaId);
  if (!textarea) return;
  
  // Special case: if single intelligent test, serialize as object (not array)
  let output = dataArray;
  if (Array.isArray(dataArray) && dataArray.length === 1 && dataArray[0].type === 'intelligent') {
    output = {...dataArray[0]};
    delete output.type; // Remove 'type' key for intelligent config object
  }
  
  const json = JSON.stringify(output, null, 2);
  textarea.value = json;
  
  alert(`JSON generated! ${dataArray.length} test case(s)`);
}

/**
 * Migrate legacy test case structures to new unified structure
 * Legacy FUNCTION: [{ type: 'function', function_name: 'f', args: [...], expected: value }, ...]
 * New FUNCTION:    [{ type: 'function', function_name: 'f', test_cases: [{ args: [...], expected: value }, ...] }]
 * 
 * Legacy VARIABLE: [{ type: 'variable', init_vars: {a: 1, b: 2}, expected_vars: {sum: 3} }, ...]
 * New VARIABLE:    [{ type: 'variable', init_var_names: ['a', 'b'], expected_var_names: ['sum'], test_cases: [{ init_values: [1, 2], expected_values: [3] }] }]
 */
function migrateLegacyTestCases(testCases) {
  if (!Array.isArray(testCases) || testCases.length === 0) return testCases;
  
  const firstTest = testCases[0];
  
  // Check if already migrated (new structure has test_cases array)
  if (firstTest.type === 'function' && Array.isArray(firstTest.test_cases)) {
    return testCases; // Already new structure
  }
  
  if (firstTest.type === 'variable' && Array.isArray(firstTest.test_cases)) {
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

// ========================================
// IMPORT FUNCTION
// ========================================

async function importTask(jsonData, assignmentId) {
  try {
    const response = await fetch(`../api/admin/assignments/import.php?assignment_id=${assignmentId}`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(jsonData)
    });
    
    // Get response text first
    const text = await response.text();
    
    // Try to parse as JSON
    let result;
    try {
      result = JSON.parse(text);
    } catch (parseErr) {
      console.error('Server response:', text);
      throw new Error('Invalid JSON response from server. Check console for details.');
    }
    
    if (!response.ok || !result.ok) {
      throw new Error(result.error || `Server error: ${response.status}`);
    }
    
    alert(`Task erfolgreich importiert!\nTask-ID: ${result.task_id}`);
    await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
    await loadAssignments();
    
  } catch (err) {
    console.error('Import error:', err);
    alert('Import fehlgeschlagen: ' + err.message);
  }
}

function sanitizeFilename(name) {
  return name.replace(/[^a-z0-9_-]/gi, '_').toLowerCase();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  initTestCasesBuilder();
  initEditTestCasesBuilder();
  
  // Assignments search filter
  const searchInput = $('assignments-search');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      state.assignmentsFilter = e.target.value;
      state.assignmentsPage = 1;
      renderAssignments();
    });
  }
  
  // Assignments pagination
  const prevBtn = $('assignments-prev');
  const nextBtn = $('assignments-next');
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (state.assignmentsPage > 1) {
        state.assignmentsPage--;
        renderAssignments();
      }
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      state.assignmentsPage++;
      renderAssignments();
    });
  }
  
  // Assignments sorting
  document.querySelectorAll('#assignments-table th.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const sortBy = th.dataset.sort;
      if (state.assignmentsSortBy === sortBy) {
        state.assignmentsSortDir = state.assignmentsSortDir === 'asc' ? 'desc' : 'asc';
      } else {
        state.assignmentsSortBy = sortBy;
        state.assignmentsSortDir = 'asc';
      }
      renderAssignments();
    });
  });
  
  // Select All Tasks checkbox
  const selectAllTasks = $('select-all-tasks');
  if (selectAllTasks) {
    selectAllTasks.addEventListener('change', (e) => {
      const checkboxes = document.querySelectorAll('.task-checkbox');
      checkboxes.forEach(cb => cb.checked = e.target.checked);
    });
  }
  
  // Export Selected Tasks
  const exportTasksBtn = $('export-tasks-btn');
  if (exportTasksBtn) {
    exportTasksBtn.addEventListener('click', async () => {
      const selectedCheckboxes = document.querySelectorAll('.task-checkbox:checked');
      if (selectedCheckboxes.length === 0) {
        alert('Please select at least one task to export.');
        return;
      }
      
      const selectedTaskIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.dataset.taskId));
      const selectedTasks = state.tasks.filter(t => selectedTaskIds.includes(t.id));
      
      if (selectedTasks.length === 1) {
        // Export single task
        const task = selectedTasks[0];
        const exportData = {
          version: '1.0',
          title: task.title,
          description: task.description || '',
          problem_type: task.problem_type,
          difficulty: task.difficulty || 'medium',
          starter_code: task.starter_code || '',
          solution_code: task.solution_code || '',
          test_cases: task.test_cases || [],
          hints: task.hints || '',
          validation_mode: task.validation_mode || 'output',
          expected_output: task.expected_output || '',
          input_mode: task.input_mode || 'none',
          timeout: task.timeout || 5000
        };
        
        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `task_${task.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.json`;
        a.click();
        URL.revokeObjectURL(url);
      } else {
        // Export multiple tasks as array
        const exportData = selectedTasks.map(task => ({
          version: '1.0',
          title: task.title,
          description: task.description || '',
          problem_type: task.problem_type,
          difficulty: task.difficulty || 'medium',
          starter_code: task.starter_code || '',
          solution_code: task.solution_code || '',
          test_cases: task.test_cases || [],
          hints: task.hints || '',
          validation_mode: task.validation_mode || 'output',
          expected_output: task.expected_output || '',
          input_mode: task.input_mode || 'none',
          timeout: task.timeout || 5000
        }));
        
        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `tasks_export_${selectedTasks.length}_tasks.json`;
        a.click();
        URL.revokeObjectURL(url);
      }
    });
  }
});
