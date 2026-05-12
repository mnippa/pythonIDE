const state = {
  assignments: [],
  assignmentId: null,
  overview: null,
  participants: [],
  participantFilters: {
    team: '',
    search: ''
  }
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
  $('stat-assigned').textContent = overview.stats.assigned || 0;
  $('stat-in-progress').textContent = overview.stats.in_progress || 0;
  $('stat-rework').textContent = overview.stats.rework || 0;
  $('stat-completed').textContent = overview.stats.completed || 0;
  $('stat-late-completed').textContent = overview.stats.late_completed || 0;
  $('stat-passed').textContent = overview.stats.passed || 0;
  $('stat-passed-delayed').textContent = overview.stats.passed_delayed || 0;
  $('stat-missed').textContent = overview.stats.missed || 0;
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

function fmtDateTime(dtStr) {
  if (!dtStr) return null;
  const d = new Date(dtStr.replace(' ', 'T'));
  if (isNaN(d)) return null;
  const pad = n => String(n).padStart(2, '0');
  return `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${String(d.getFullYear()).slice(-2)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fmtDateOnly(dtStr) {
  if (!dtStr) return null;
  const d = new Date(dtStr.replace(' ', 'T'));
  if (isNaN(d)) return null;
  const pad = n => String(n).padStart(2, '0');
  return `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${String(d.getFullYear()).slice(-2)}`;
}

function cleanLastName(lastName) {
  if (!lastName) return '';
  return String(lastName).replace(/\s*\([^)]*\)\s*/g, '').trim();
}

function fmtRelativeToDue(submittedStr, dueDateStr) {
  if (!submittedStr || !dueDateStr) return null;
  const sub = new Date(submittedStr.replace(' ', 'T'));
  const due = new Date(dueDateStr.replace(' ', 'T'));
  if (isNaN(sub) || isNaN(due)) return null;
  const diffDays = Math.round((sub - due) / 86400000);
  if (diffDays === 0) return { label: '(0T)', late: false };
  if (diffDays > 0) return { label: `(+${diffDays}T)`, late: true };
  return { label: `(${diffDays}T)`, late: false };
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
  renderParticipantTeamFilter();
  renderParticipants();
}

function renderParticipantTeamFilter() {
  const select = $('participants-team-filter');
  if (!select) return;

  const previousValue = state.participantFilters.team || '';
  const teams = Array.from(new Set(
    (state.participants || [])
      .map((participant) => (participant.team_name || '').trim())
      .filter(Boolean)
  )).sort((left, right) => left.localeCompare(right, 'de', { sensitivity: 'base' }));

  select.innerHTML = '<option value="">Alle Teams</option>';
  teams.forEach((teamName) => {
    const option = document.createElement('option');
    option.value = teamName;
    option.textContent = teamName;
    select.appendChild(option);
  });

  if (teams.includes(previousValue)) {
    select.value = previousValue;
  } else {
    state.participantFilters.team = '';
    select.value = '';
  }
}

function getFilteredParticipants() {
  const teamFilter = (state.participantFilters.team || '').trim();
  const searchFilter = (state.participantFilters.search || '').trim().toLocaleLowerCase('de');

  return (state.participants || []).filter((participant) => {
    const matchesTeam = !teamFilter || (participant.team_name || '') === teamFilter;
    if (!matchesTeam) {
      return false;
    }

    if (!searchFilter) {
      return true;
    }

    const haystack = [
      participant.email,
      participant.first_name,
      participant.last_name,
      [participant.first_name, participant.last_name].filter(Boolean).join(' ')
    ]
      .filter(Boolean)
      .join(' ')
      .toLocaleLowerCase('de');

    return haystack.includes(searchFilter);
  });
}

function renderParticipants() {
  const body = $('participants-body');
  body.innerHTML = '';

  const filteredParticipants = getFilteredParticipants();

  if (!state.participants || state.participants.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td colspan="11">Keine Teilnehmer zugewiesen.</td>';
    body.appendChild(tr);
    return;
  }

  if (filteredParticipants.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td colspan="11">Keine Teilnehmer für den aktuellen Filter gefunden.</td>';
    body.appendChild(tr);
    return;
  }

  filteredParticipants.forEach((u) => {
    const fullName = [u.first_name, u.last_name].filter(Boolean).join(' ') || '-';
    const sourceLabel = u.is_direct ? 'direct (User)' : 'team';
    const timeFormatted = formatTime(u.active_seconds || 0);
    const currentRawStatus = u.raw_status || 'assigned';

    // Task-level counts from task_progress
    const tp = u.task_progress || {};
    const total      = tp.total_tasks    || 0;
    const worked     = tp.worked_tasks   || 0;
    const passed     = tp.passed_tasks   || 0;
    const finalized  = tp.finalized_tasks || 0;
    const failed     = finalized - passed;
    const inProgress = Math.max(0, worked - finalized);
    const untouched  = Math.max(0, total - worked);

    const taskBar = total > 0 ? `
      <div style="display:flex;height:5px;border-radius:3px;overflow:hidden;background:#e5e7eb;margin-bottom:4px;">
        <span style="width:${Math.round(passed/total*100)}%;background:#22c55e;"></span>
        <span style="width:${Math.round(failed/total*100)}%;background:#ef4444;"></span>
        <span style="width:${Math.round(inProgress/total*100)}%;background:#facc15;"></span>
      </div>` : '';

    const taskCounts = total > 0
      ? `${taskBar}<span style="font-size:11px;color:#6b7280;">
          ${untouched > 0 ? `<span title="unbearbeitet">⚪${untouched}</span> ` : ''}
          ${inProgress > 0 ? `<span title="laufend">🟡${inProgress}</span> ` : ''}
          ${passed > 0 ? `<span title="bestanden">🟢${passed}</span> ` : ''}
          ${failed > 0 ? `<span title="nacharbeit offen">🔴${failed}</span> ` : ''}
        </span>`
      : '<span style="font-size:11px;color:#9ca3af;">–</span>';

    const tr = document.createElement('tr');

    const subFmt = fmtDateOnly(u.submitted_at);
    const gradFmtDate = fmtDateOnly(u.graded_at);
    const graderLastName = cleanLastName(u.graded_by_last_name);
    const flagsHtml = `
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:6px;">
        ${u.is_late ? '<span title="Verspätet abgegeben" style="font-size:11px;padding:2px 7px;border-radius:999px;background:#fef3c7;color:#b45309;font-weight:700;border:1px solid #fcd34d;">🕐 verspätet</span>' : ''}
        ${u.is_rework ? '<span title="Nacharbeit angefordert" style="font-size:11px;padding:2px 7px;border-radius:999px;background:#fef9c3;color:#854d0e;font-weight:700;border:1px solid #fde047;">🔨 Nacharbeit</span>' : ''}
      </div>
    `;
    const rel = fmtRelativeToDue(u.submitted_at, u.effective_due_date);
    const relBadge = rel
      ? `<span style="padding:1px 5px;border-radius:999px;font-size:10px;font-weight:700;background:${rel.late ? '#fef3c7' : '#f0fdf4'};color:${rel.late ? '#b45309' : '#166534'};">${escapeHtml(rel.label)}</span>`
      : '';
    const submissionHtml = subFmt
      ? `<span style="font-size:11px;color:#374151;font-weight:600;display:inline-flex;align-items:center;gap:4px;">${escapeHtml(subFmt)}${relBadge}</span>`
      : '<span style="font-size:11px;color:#9ca3af;">–</span>';
    const gradedInfoHtml = gradFmtDate
      ? `<div style="margin-top:6px;font-size:10px;color:#6b7280;padding-top:6px;border-top:1px solid #e5e7eb;">
           ${escapeHtml(gradFmtDate)}${graderLastName ? ` / ${escapeHtml(graderLastName)}` : ''}
         </div>`
      : '';

    tr.innerHTML = `
      <td class="mono">${u.id}</td>
      <td>${escapeHtml(u.email)}</td>
      <td>${escapeHtml(fullName)}</td>
      <td>${escapeHtml(u.team_name || '-')}</td>
      <td>${taskCounts}</td>
      <td style="min-width:130px;">
        ${submissionHtml}
      </td>
      <td>
        <select class="assignment-status-select" data-assignment-id="${state.assignmentId}" data-user-id="${u.id}" data-current-status="${escapeHtml(currentRawStatus)}">
          <option value="assigned"      ${currentRawStatus === 'assigned'      ? 'selected' : ''}>Zugewiesen</option>
          <option value="in_progress"   ${currentRawStatus === 'in_progress'   ? 'selected' : ''}>In Bearbeitung</option>
          <option value="rework"        ${currentRawStatus === 'rework'        ? 'selected' : ''}>Nacharbeit</option>
          <option value="submitted"     ${currentRawStatus === 'submitted'     ? 'selected' : ''}>Eingereicht</option>
          <option value="passed"        ${currentRawStatus === 'passed'        ? 'selected' : ''}>Bestanden</option>
          <option value="passed_delayed"${currentRawStatus === 'passed_delayed'? 'selected' : ''}>Bestanden (verspaetet)</option>
          <option value="failed"        ${currentRawStatus === 'failed'        ? 'selected' : ''}>Nicht bestanden</option>
        </select>
        ${flagsHtml}
        ${gradedInfoHtml}
      </td>
      <td class="mono num-right">${formatInt(u.run_count)}</td>
      <td class="mono num-right">${timeFormatted}</td>
      <td>${sourceLabel}</td>
      <td>
        <button class="btn" data-action="view-user" data-user-id="${u.id}">View</button>
        <button class="btn" data-action="test-view" data-user-id="${u.id}" style="margin-left:4px;">Test View</button>
        <button class="btn" data-action="reset-user-attempts" data-user-id="${u.id}" data-user-name="${escapeHtml(fullName)}" style="margin-left:4px;" title="Teilnehmer-Aufgaben zurücksetzen">↺</button>
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
    rework: 'status-in-progress',
    submitted: 'status-in-progress',
    completed: 'status-completed',
    late_completed: 'status-late-completed',
    passed: 'status-passed',
    passed_delayed: 'status-passed-delayed',
    failed: 'status-failed',
    missed: 'status-missed'
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
      <td>
        <button class="btn" data-action="test-task" data-user-id="${data.user.id}" data-task-id="${task.id}">Test</button>
      </td>
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

  $('participants-team-filter')?.addEventListener('change', (e) => {
    state.participantFilters.team = e.target.value || '';
    renderParticipants();
  });

  $('participants-search')?.addEventListener('input', (e) => {
    state.participantFilters.search = e.target.value || '';
    renderParticipants();
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
    const previousStatus = select.dataset.currentStatus || 'assigned';

    if (!assignmentId || !userId) return;

    if (status === 'rework') {
      const confirmed = window.confirm('Nacharbeit starten? Dabei wird die individuelle Frist auf jetzt + 10 Tage gesetzt und nicht bestandene Aufgaben werden wieder auf unbearbeitet gesetzt.');
      if (!confirmed) {
        select.value = previousStatus;
        return;
      }
    }

    try {
      await requestJson('../api/admin/assignments/users/update-status.php', {
        method: 'POST',
        body: JSON.stringify({ assignment_id: assignmentId, user_id: userId, status })
      });
      await loadOverview();
      await loadParticipants();
    } catch (err) {
      select.value = previousStatus;
      alert('Update failed: ' + err.message);
    }
  });

  document.body.addEventListener('click', async (e) => {
    const viewBtn = e.target.closest('button[data-action="view-user"]');
    if (viewBtn) {
      const userId = parseInt(viewBtn.dataset.userId, 10);
      if (!userId) return;
      try {
        await openUserDetail(userId);
      } catch (err) {
        alert('Failed to load user: ' + err.message);
      }
      return;
    }

    const testViewBtn = e.target.closest('button[data-action="test-view"]');
    if (testViewBtn) {
      const userId = parseInt(testViewBtn.dataset.userId, 10);
      if (!userId || !state.assignmentId) return;
      const url = `editor_assignment_user_test.php?assignment_id=${state.assignmentId}&test_user_id=${userId}`;
      window.open(url, '_blank');
      return;
    }

    const resetUserBtn = e.target.closest('button[data-action="reset-user-attempts"]');
    if (resetUserBtn) {
      const userId = parseInt(resetUserBtn.dataset.userId, 10);
      const userName = resetUserBtn.dataset.userName || `#${userId}`;
      if (!userId || !state.assignmentId) return;

      const assignmentTitle = (state.assignments.find(a => a.id === state.assignmentId) || {}).title || `#${state.assignmentId}`;
      const confirmed = window.confirm(`Wirklich alle Aufgaben zurücksetzen?\n\nTeilnehmer: ${userName}\nAssignment: ${assignmentTitle}`);
      if (!confirmed) return;

      try {
        const response = await requestJson('../api/admin/assignments/users/reset-attempts.php', {
          method: 'POST',
          body: JSON.stringify({ assignment_id: state.assignmentId, user_id: userId })
        });
        alert(`Erfolg: ${response.affected_rows || 0} Aufgaben zurückgesetzt.`);
        await loadOverview();
        await loadParticipants();
      } catch (err) {
        alert('Reset failed: ' + err.message);
      }
      return;
    }

    const testTaskBtn = e.target.closest('button[data-action="test-task"]');
    if (testTaskBtn) {
      const userId = parseInt(testTaskBtn.dataset.userId, 10);
      const taskId = parseInt(testTaskBtn.dataset.taskId, 10);
      if (!userId || !taskId || !state.assignmentId) return;
      const url = `editor_assignment_user_test.php?assignment_id=${state.assignmentId}&task_id=${taskId}&test_user_id=${userId}`;
      window.open(url, '_blank');
      return;
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
