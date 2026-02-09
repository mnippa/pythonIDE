const state = {
  assignments: [],
  projects: [],
  users: [],
  tasks: [],
  currentAssignmentId: null,
  currentAssignmentTitle: null
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

  const body = $('assignments-body');
  body.innerHTML = '';

  state.assignments.forEach((a) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${a.id}</td>
      <td>${escapeHtml(a.title)}</td>
      <td>${escapeHtml(a.difficulty)}</td>
      <td>${a.is_active ? 'true' : 'false'}</td>
      <td>${a.task_count}</td>
      <td>
        <div class="row-actions">
          <button class="btn" data-action="select-assignment" data-id="${a.id}">Tasks</button>
          <button class="btn" data-action="edit-assignment" data-id="${a.id}">Edit</button>
          <button class="btn warn" data-action="delete-assignment" data-id="${a.id}">Delete</button>
        </div>
      </td>
    `;
    body.appendChild(tr);
  });
}

async function loadTasks(assignmentId, assignmentTitle) {
  if (!assignmentId) return;
  const data = await requestJson(`../api/tasks/list.php?assignment_id=${assignmentId}&include_expected=1`);
  state.tasks = data.tasks || [];
  state.currentAssignmentId = assignmentId;
  state.currentAssignmentTitle = assignmentTitle;

  $('tasks-title').textContent = `Tasks: ${assignmentTitle}`;
  $('tasks-hint').textContent = `Assignment ID ${assignmentId}`;

  const body = $('tasks-body');
  body.innerHTML = '';

  state.tasks.forEach((t) => {
    const hasTests = t.test_cases ? '✓' : '✗';
    const hasSolution = t.solution_code ? '✓' : '✗';
    const modeLabel = t.validation_mode || '-';
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${t.position}</td>
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
  $('assignment-form-title').textContent = 'New Assignment';
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
    hint: $('task-hint').value,
    expected_output: $('task-expected').value,
    validation_mode: $('task-validation-mode').value || null,
    test_cases: $('task-test-cases').value.trim() || null,
    solution_code: $('task-solution').value.trim() || null
  };

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  // Validate test_cases JSON if provided
  if (payload.test_cases) {
    try {
      JSON.parse(payload.test_cases);
    } catch (err) {
      alert('Invalid test_cases JSON: ' + err.message);
      return;
    }
  }

  await requestJson('../api/tasks/create.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  });

  $('task-title').value = '';
  $('task-description').value = '';
  $('task-position').value = '';
  $('task-template').value = '';
  $('task-hint').value = '';
  $('task-expected').value = '';
  $('task-validation-mode').value = '';
  $('task-test-cases').value = '';
  $('task-solution').value = '';

  await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
  await loadAssignments();
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

function openEditTaskModal(taskId) {
  const task = state.tasks.find((t) => t.id === taskId);
  if (!task) return;

  $('edit-task-id').value = task.id;
  $('edit-task-title').value = task.title || '';
  $('edit-task-description').value = task.description || '';
  $('edit-task-position').value = task.position || '';
  $('edit-task-type').value = task.problem_type || 'code_completion';
  $('edit-task-template').value = task.code_template || '';
  $('edit-task-hint').value = task.hint || '';
  $('edit-task-hint1').value = task.hint1 || '';
  $('edit-task-hint2').value = task.hint2 || '';
  $('edit-task-hint3').value = task.hint3 || '';
  $('edit-task-stoff').value = task.stoff || '';
  $('edit-task-expected').value = task.expected_output || '';
  $('edit-task-validation-mode').value = task.validation_mode || '';
  $('edit-task-test-cases').value = task.test_cases || '';
  $('edit-task-solution').value = task.solution_code || '';

  $('task-modal').style.display = 'block';
  $('modal-title').textContent = `Edit Task: ${task.title}`;
}

function closeEditTaskModal() {
  $('task-modal').style.display = 'none';
  $('edit-task-id').value = '';
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
    hint: $('edit-task-hint').value,
    hint1: $('edit-task-hint1').value,
    hint2: $('edit-task-hint2').value,
    hint3: $('edit-task-hint3').value,
    stoff: $('edit-task-stoff').value,
    expected_output: $('edit-task-expected').value,
    validation_mode: $('edit-task-validation-mode').value || null,
    test_cases: $('edit-task-test-cases').value.trim() || null,
    solution_code: $('edit-task-solution').value.trim() || null
  };

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  // Validate test_cases JSON if provided
  if (payload.test_cases) {
    try {
      JSON.parse(payload.test_cases);
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
  $('assignment-reset').addEventListener('click', resetAssignmentForm);
  $('task-form').addEventListener('submit', handleTaskSubmit);
  $('task-edit-form').addEventListener('submit', handleEditTaskSubmit);

  // Modal close buttons
  $('close-modal-btn').addEventListener('click', closeEditTaskModal);
  $('cancel-modal-btn').addEventListener('click', closeEditTaskModal);
  
  // Close modal on background click
  $('task-modal').addEventListener('click', (e) => {
    if (e.target === $('task-modal')) {
      closeEditTaskModal();
    }
  });

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
      $('assignment-form-title').textContent = `Edit Assignment #${a.id}`;
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

Hint:
${task.hint || '(none)'}

Expected Output:
${task.expected_output || '(none)'}

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
