const state = {
  assignments: [],
  assignmentId: null,
  overview: null,
  participants: []
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

function getAssignmentIdFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('assignment_id');
  return id ? parseInt(id, 10) : null;
}

function setAssignmentIdInUrl(id) {
  const params = new URLSearchParams(window.location.search);
  params.set('assignment_id', String(id));
  const newUrl = `${window.location.pathname}?${params.toString()}`;
  window.history.replaceState({}, '', newUrl);
}

async function loadAssignments() {
  const data = await requestJson('../api/assignments/list.php?all=1');
  state.assignments = data.assignments || [];
  renderAssignmentSelect();
}

function renderAssignmentSelect() {
  const select = $('assignment-select');
  select.innerHTML = '';

  state.assignments.forEach((assignment) => {
    const option = document.createElement('option');
    option.value = assignment.id;
    option.textContent = `#${assignment.id} - ${assignment.title}`;
    select.appendChild(option);
  });

  if (!state.assignmentId && state.assignments.length > 0) {
    state.assignmentId = state.assignments[0].id;
  }

  if (state.assignmentId) {
    select.value = String(state.assignmentId);
  }
}

async function loadOverview() {
  if (!state.assignmentId) return;
  const data = await requestJson(`../api/admin/evaluation/overview.php?assignment_id=${state.assignmentId}`);
  state.overview = data;
  renderOverview();
}

function renderOverview() {
  const overview = state.overview;
  if (!overview) return;

  $('assignment-title').textContent = overview.title || '-';
  $('stat-total-users').textContent = overview.stats.total || 0;
  $('stat-unstarted').textContent = overview.stats.unstarted || 0;
  $('stat-in-progress').textContent = overview.stats.in_progress || 0;
  $('stat-passed').textContent = overview.stats.passed || 0;
  $('stat-failed').textContent = overview.stats.failed || 0;
  $('stat-avg-runs').textContent = formatAvg(overview.stats.avg_runs || 0);

  const body = $('tasks-overview-body');
  body.innerHTML = '';

  if (!overview.tasks || overview.tasks.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td colspan="7">Keine Aufgaben vorhanden.</td>';
    body.appendChild(tr);
    return;
  }

  overview.tasks.forEach((task) => {
    const total = overview.stats.total || 0;
    const unstarted = task.stats.unstarted || 0;
    const inProgress = task.stats.in_progress || 0;
    const passed = task.stats.passed || 0;
    const failed = task.stats.failed || 0;
    const sumChecks = formatInt(task.sum_checks);
    const avgChecks = formatAvg(task.avg_checks);
    const sumRuns = formatInt(task.sum_runs);
    const avgRuns = formatAvg(task.avg_runs);
    const sumHints = formatInt(task.sum_hints);
    const avgHints = formatAvg(task.avg_hints);
    const sumTime = formatTime(task.sum_active_seconds);
    const avgTime = formatTime(task.avg_active_seconds);

    const segments = buildStatusSegments(total, { unstarted, inProgress, passed, failed });

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${task.position}</td>
      <td>${escapeHtml(task.title)}</td>
      <td>
        <div class="status-bar">${segments}</div>
        <div class="status-legend">
          <span><span class="status-dot status-unstarted"></span>${unstarted}</span>
          <span><span class="status-dot status-in-progress"></span>${inProgress}</span>
          <span><span class="status-dot status-passed"></span>${passed}</span>
          <span><span class="status-dot status-failed"></span>${failed}</span>
        </div>
      </td>
      <td class="mono num-right">${sumChecks}/${avgChecks}</td>
      <td class="mono num-right">${sumRuns}/${avgRuns}</td>
      <td class="mono num-right">${sumHints}/${avgHints}</td>
      <td class="mono num-right">${sumTime}/${avgTime}</td>
    `;
    body.appendChild(tr);
  });
}

function formatAvg(value) {
  if (value === null || value === undefined) return '0.0';
  const num = Number(value);
  if (!Number.isFinite(num)) return '0.0';
  return num.toFixed(1);
}

function formatInt(value) {
  if (value === null || value === undefined) return '0';
  const num = Number(value);
  if (!Number.isFinite(num)) return '0';
  return String(Math.round(num));
}

function formatTime(seconds) {
  if (seconds === null || seconds === undefined || seconds === 0) return '0:00';
  const sec = Number(seconds);
  if (!Number.isFinite(sec) || sec < 0) return '0:00';
  
  const mins = Math.floor(sec / 60);
  const secs = sec % 60;
  return `${mins}:${String(secs).padStart(2, '0')}`;
}

function buildStatusSegments(total, stats) {
  if (!total || total <= 0) {
    return '<span class="status-seg-unstarted" style="width: 100%;"></span>';
  }

  const unstartedPct = Math.round((stats.unstarted / total) * 100);
  const inProgressPct = Math.round((stats.inProgress / total) * 100);
  const passedPct = Math.round((stats.passed / total) * 100);
  const failedPct = Math.max(0, 100 - unstartedPct - inProgressPct - passedPct);

  return [
    `<span class="status-seg-unstarted" style="width: ${unstartedPct}%;"></span>`,
    `<span class="status-seg-in-progress" style="width: ${inProgressPct}%;"></span>`,
    `<span class="status-seg-passed" style="width: ${passedPct}%;"></span>`,
    `<span class="status-seg-failed" style="width: ${failedPct}%;"></span>`
  ].join('');
}

async function loadParticipants() {
  if (!state.assignmentId) return;
  const data = await requestJson(`../api/admin/assignments/users/list.php?assignment_id=${state.assignmentId}`);
  state.participants = data.users || [];
  renderParticipants();
}

function renderParticipants() {
  const body = $('participants-body');
  body.innerHTML = '';

  if (!state.participants || state.participants.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td colspan="9">Keine Teilnehmer zugewiesen.</td>';
    body.appendChild(tr);
    return;
  }

  state.participants.forEach((u) => {
    const fullName = [u.first_name, u.last_name].filter(Boolean).join(' ') || '-';
    const sourceLabel = u.is_direct ? 'direct (User)' : 'team';
    const statusIcon = getStatusDot(u.status);
    const timeFormatted = formatTime(u.active_seconds || 0);

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${u.id}</td>
      <td>${escapeHtml(u.email)}</td>
      <td>${escapeHtml(fullName)}</td>
      <td>${escapeHtml(u.team_name || '-')}</td>
      <td>
        <div style="display:flex; align-items:center; gap:6px;">
          ${statusIcon}
          <select class="assignment-status-select" data-assignment-id="${state.assignmentId}" data-user-id="${u.id}">
            <option value="assigned" ${u.status === 'assigned' ? 'selected' : ''}>unbearbeitet</option>
            <option value="in_progress" ${u.status === 'in_progress' ? 'selected' : ''}>in Bearbeitung</option>
            <option value="submitted" ${u.status === 'submitted' ? 'selected' : ''}>submitted</option>
            <option value="passed" ${u.status === 'passed' ? 'selected' : ''}>success</option>
            <option value="failed" ${u.status === 'failed' ? 'selected' : ''}>failed</option>
          </select>
        </div>
      </td>
      <td class="mono num-right">${formatInt(u.run_count)}</td>
      <td class="mono num-right">${timeFormatted}</td>
      <td>${sourceLabel}</td>
      <td>
        <button class="btn" data-action="view-user" data-user-id="${u.id}">View</button>
      </td>
    `;
    body.appendChild(tr);
  });
}

function getStatusDot(status) {
  const map = {
    assigned: 'status-unstarted',
    unbearbeitet: 'status-unstarted',
    in_progress: 'status-in-progress',
    'in-progress': 'status-in-progress',
    submitted: 'status-in-progress',
    passed: 'status-passed',
    failed: 'status-failed'
  };
  const cls = map[status] || 'status-unstarted';
  return `<span class="status-dot ${cls}" title="Status"></span>`;
}

async function openUserDetail(userId) {
  const modal = $('user-detail-modal');
  modal.classList.add('active');

  const data = await requestJson(`../api/admin/evaluation/user-detail.php?assignment_id=${state.assignmentId}&user_id=${userId}`);

  $('user-detail-title').textContent = `${data.user.full_name} (#${data.user.id})`;
  $('user-detail-meta').textContent = `${data.user.email} | Team: ${data.user.team_name || '-'} | Source: ${data.user.source}`;
  $('user-detail-status').innerHTML = `
    ${getStatusDot(data.user.status)}
    <strong>${data.user.status_label}</strong>
  `;

  const body = $('user-detail-tasks');
  body.innerHTML = '';

  data.tasks.forEach((task) => {
    const tr = document.createElement('tr');
    const activeSeconds = task.active_seconds || 0;
    const timeFormatted = formatTime(activeSeconds);
    tr.innerHTML = `
      <td class="mono">${task.position}</td>
      <td>${escapeHtml(task.title)}</td>
      <td>${getStatusDot(task.status)} ${task.status_label}</td>
      <td class="num-right">${task.attempts}</td>
      <td class="num-right">${task.run_count}</td>
      <td class="num-right">${timeFormatted}</td>
    `;
    body.appendChild(tr);
  });
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

function bindEvents() {
  document.querySelectorAll('.tab').forEach((btn) => {
    btn.addEventListener('click', () => setActiveTab(btn.dataset.tab));
  });

  $('assignment-select').addEventListener('change', async (e) => {
    state.assignmentId = parseInt(e.target.value, 10);
    setAssignmentIdInUrl(state.assignmentId);
    await Promise.all([loadOverview(), loadParticipants()]);
  });

  $('back-to-admin').addEventListener('click', () => {
    window.location.href = 'admin.php';
  });

  $('user-detail-close').addEventListener('click', () => {
    $('user-detail-modal').classList.remove('active');
  });

  $('user-detail-modal').addEventListener('click', (e) => {
    if (e.target === $('user-detail-modal')) {
      $('user-detail-modal').classList.remove('active');
    }
  });

  document.body.addEventListener('change', async (e) => {
    const select = e.target.closest('.assignment-status-select');
    if (!select) return;

    const assignmentId = parseInt(select.dataset.assignmentId, 10);
    const userId = parseInt(select.dataset.userId, 10);
    const status = select.value;

    if (!assignmentId || !userId) return;

    try {
      await requestJson('../api/admin/assignments/users/update-status.php', {
        method: 'POST',
        body: JSON.stringify({ assignment_id: assignmentId, user_id: userId, status })
      });
      await loadOverview();
      await loadParticipants();
    } catch (err) {
      alert('Update failed: ' + err.message);
    }
  });

  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action="view-user"]');
    if (!btn) return;
    const userId = parseInt(btn.dataset.userId, 10);
    if (!userId) return;
    try {
      await openUserDetail(userId);
    } catch (err) {
      alert('Failed to load user: ' + err.message);
    }
  });
}

async function init() {
  state.assignmentId = getAssignmentIdFromUrl();
  bindEvents();
  await loadAssignments();
  if (state.assignmentId) {
    setAssignmentIdInUrl(state.assignmentId);
    await Promise.all([loadOverview(), loadParticipants()]);
  }
}

init().catch((err) => {
  console.error(err);
  alert(err.message || 'Failed to load evaluation');
});
