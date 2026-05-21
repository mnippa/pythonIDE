/**
 * Admin Teams & Users Management
 */

// Helper function to get element by ID
function $(id) {
  return document.getElementById(id);
}

// Helper function to escape HTML
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

// Helper function to make JSON requests
async function teamsUsersRequestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    ...options
  });

  const raw = await response.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    data = null;
  }

  if (!response.ok || (data && data.ok === false)) {
    const msg = (data && (data.error || data.output))
      ? (data.error || data.output)
      : (raw && raw.trim()
          ? `HTTP ${response.status}: ${raw.trim().slice(0, 800)}`
          : response.statusText);
    throw new Error(msg);
  }

  if (!data) {
    throw new Error('Invalid server response');
  }

  return data;
}

// ========== TEAMS ==========

let teamsData = [];
let semestersData = [];
let teamMembersData = [];
let teamAssignmentsData = [];
let teamMatrixDetailCache = new Map();
let teamMatrixDetailBox = null;
let teamMatrixHideTimer = null;
let teamMatrixActiveTrigger = null;

function formatMinutes(seconds) {
  const total = Math.max(0, Number(seconds) || 0);
  return String(Math.round(total / 60));
}

function ensureTeamMatrixDetailBox() {
  if (teamMatrixDetailBox) return teamMatrixDetailBox;
  const box = document.createElement('div');
  box.id = 'team-matrix-detail-box';
  box.style.position = 'fixed';
  box.style.zIndex = '3000';
  box.style.minWidth = '420px';
  box.style.maxWidth = '560px';
  box.style.padding = '14px 16px';
  box.style.borderRadius = '10px';
  box.style.border = '1px solid rgba(148,163,184,0.5)';
  box.style.background = 'rgba(15,23,42,0.6)';
  box.style.backdropFilter = 'blur(4px)';
  box.style.color = '#f8fafc';
  box.style.boxShadow = '0 10px 24px rgba(2,6,23,0.25)';
  box.style.display = 'none';
  box.style.fontSize = '13px';
  box.style.lineHeight = '1.5';

  box.addEventListener('mouseenter', () => {
    if (teamMatrixHideTimer) {
      clearTimeout(teamMatrixHideTimer);
      teamMatrixHideTimer = null;
    }
  });
  box.addEventListener('mouseleave', () => {
    hideTeamMatrixDetailBox();
  });

  document.body.appendChild(box);
  teamMatrixDetailBox = box;
  return box;
}

function hideTeamMatrixDetailBox() {
  if (!teamMatrixDetailBox) return;
  teamMatrixDetailBox.style.display = 'none';
  teamMatrixDetailBox.innerHTML = '';
  teamMatrixActiveTrigger = null;
}

function scheduleHideTeamMatrixDetailBox() {
  if (teamMatrixHideTimer) {
    clearTimeout(teamMatrixHideTimer);
  }
  teamMatrixHideTimer = setTimeout(() => {
    const boxHovered = teamMatrixDetailBox && teamMatrixDetailBox.matches(':hover');
    const triggerHovered = teamMatrixActiveTrigger && teamMatrixActiveTrigger.matches(':hover');
    if (!boxHovered && !triggerHovered) {
      hideTeamMatrixDetailBox();
    }
  }, 120);
}

function positionTeamMatrixDetailBox(trigger) {
  const box = ensureTeamMatrixDetailBox();
  const rect = trigger.getBoundingClientRect();
  const margin = 10;

  box.style.left = '0px';
  box.style.top = '0px';
  box.style.display = 'block';

  const boxRect = box.getBoundingClientRect();
  let left = rect.left + rect.width + margin;
  let top = rect.top - 4;

  if (left + boxRect.width > window.innerWidth - 8) {
    left = rect.left - boxRect.width - margin;
  }
  if (left < 8) left = 8;

  if (top + boxRect.height > window.innerHeight - 8) {
    top = window.innerHeight - boxRect.height - 8;
  }
  if (top < 8) top = 8;

  box.style.left = `${left}px`;
  box.style.top = `${top}px`;
}

function buildTeamMatrixDetailHtml(detail) {
  const user = detail?.user || {};
  const tasks = Array.isArray(detail?.tasks) ? detail.tasks : [];
  let passed = 0;
  let failed = 0;
  let inProgress = 0;
  let open = 0;
  for (const task of tasks) {
    const s = task?.status || 'unbearbeitet';
    if (s === 'passed') passed++;
    else if (s === 'failed') failed++;
    else if (s === 'in-progress') inProgress++;
    else open++;
  }

  const checksTotal = tasks.reduce((sum, t) => sum + (Number(t.attempts) || 0), 0);
  const runsTotal = tasks.reduce((sum, t) => sum + (Number(t.run_count) || 0), 0);
  const hintsTotal = tasks.reduce((sum, t) => sum + (Number(t.hints_count) || 0), 0);
  const activeSecondsTotal = tasks.reduce((sum, t) => sum + (Number(t.active_seconds) || 0), 0);

  const flags = [
    user.is_late ? '🕐 verspätet' : null,
    user.is_rework ? '🔨 Nacharbeit' : null,
  ].filter(Boolean).join(' · ');

  return `
    <div style="font-weight:700;font-size:14px;margin-bottom:6px;">${escapeHtml(detail.assignment_title || 'Assignment')} (${tasks.length} Tasks)</div>
    <div style="margin-bottom:4px;">${escapeHtml(user.full_name || user.email || '-')} (${escapeHtml(user.email || '-')})</div>
    <div style="margin-bottom:4px;">Status: <strong>${escapeHtml(user.status_label || user.status || '-')}</strong></div>
    <div style="margin-bottom:4px;">Hints: <strong>${hintsTotal}</strong> · Checks: <strong>${checksTotal}</strong> · Runs: <strong>${runsTotal}</strong></div>
    <div style="margin-bottom:4px;">Zeit: <strong>${formatMinutes(activeSecondsTotal)} min</strong></div>
    <div style="margin-bottom:2px;">Tasks: ⚪${open} · 🟡${inProgress} · 🟢${passed} · 🔴${failed}</div>
    ${flags ? `<div style="margin-top:4px;">${flags}</div>` : ''}
  `;
}

async function showTeamMatrixDetail(triggerEl) {
  const userId = Number(triggerEl.dataset.userId || 0);
  const assignmentId = Number(triggerEl.dataset.assignmentId || 0);
  if (!userId || !assignmentId) return;

  if (teamMatrixHideTimer) {
    clearTimeout(teamMatrixHideTimer);
    teamMatrixHideTimer = null;
  }

  teamMatrixActiveTrigger = triggerEl;
  const box = ensureTeamMatrixDetailBox();
  box.innerHTML = 'Lade Details...';
  positionTeamMatrixDetailBox(triggerEl);

  const cacheKey = `${assignmentId}:${userId}`;
  if (!teamMatrixDetailCache.has(cacheKey)) {
    try {
      const detail = await teamsUsersRequestJson(`../api/admin/evaluation/user-detail.php?assignment_id=${encodeURIComponent(assignmentId)}&user_id=${encodeURIComponent(userId)}`);
      teamMatrixDetailCache.set(cacheKey, { ok: true, data: detail });
    } catch (err) {
      teamMatrixDetailCache.set(cacheKey, { ok: false, error: err.message || 'Fehler beim Laden' });
    }
  }

  if (teamMatrixActiveTrigger !== triggerEl) return;
  const entry = teamMatrixDetailCache.get(cacheKey);
  box.innerHTML = entry?.ok
    ? buildTeamMatrixDetailHtml(entry.data)
    : `<div style="color:#fecaca;">Detailansicht konnte nicht geladen werden: ${escapeHtml(entry?.error || 'Unbekannter Fehler')}</div>`;
  positionTeamMatrixDetailBox(triggerEl);
}

function bindTeamMatrixDetailHover() {
  const triggers = document.querySelectorAll('.team-matrix-dot-trigger[data-user-id][data-assignment-id]');
  triggers.forEach((trigger) => {
    if (trigger.dataset.boundHover === '1') return;
    trigger.dataset.boundHover = '1';
    trigger.addEventListener('mouseenter', () => {
      showTeamMatrixDetail(trigger);
    });
    trigger.addEventListener('mouseleave', () => {
      scheduleHideTeamMatrixDetailBox();
    });
  });
}

function toMysqlDateTime(localValue) {
  if (!localValue) return null;
  const normalized = String(localValue).trim();
  const match = normalized.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (match) {
    const [, datePart, hh, mm, ss] = match;
    return `${datePart} ${hh}:${mm}:${ss || '00'}`;
  }

  // Fallback for unexpected formats
  const d = new Date(normalized);
  if (Number.isNaN(d.getTime())) return null;
  const yyyy = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${yyyy}-${m}-${dd} ${hh}:${mi}:${ss}`;
}

function toDatetimeLocalValue(dateTimeStr) {
  if (!dateTimeStr) return '';
  const normalized = String(dateTimeStr).trim();
  const mysqlMatch = normalized.match(/^(\d{4}-\d{2}-\d{2})(?:\s+(\d{2}):(\d{2})(?::\d{2})?)?$/);
  if (mysqlMatch) {
    const [, datePart, hh = '00', mm = '00'] = mysqlMatch;
    return `${datePart}T${hh}:${mm}`;
  }

  const localMatch = normalized.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/);
  if (localMatch) {
    const [, datePart, hh, mm] = localMatch;
    return `${datePart}T${hh}:${mm}`;
  }

  // Fallback for unexpected formats
  const d = new Date(normalized.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  const yyyy = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${yyyy}-${m}-${dd}T${hh}:${mi}`;
}

function formatShortDate(dateTimeStr) {
  if (!dateTimeStr) return '-';
  const normalized = String(dateTimeStr).trim();
  const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (match) {
    const [, yyyy, mm, dd] = match;
    return `${dd}.${mm}.${yyyy}`;
  }

  const d = new Date(normalized.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '-';
  const dd = String(d.getDate()).padStart(2, '0');
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  return `${dd}.${m}.${yyyy}`;
}

async function populateAssignmentSelect(selectId) {
  const select = document.getElementById(selectId);
  if (!select) return;

  select.innerHTML = '<option value="">-- Assignment auswählen --</option>';
  const response = await teamsUsersRequestJson('../api/assignments/list.php?all=1');

  if (response.ok && Array.isArray(response.assignments)) {
    response.assignments
      .filter(a => a.is_active)
      .sort((a, b) => a.id - b.id)
      .forEach((assignment) => {
        const option = document.createElement('option');
        option.value = assignment.id;
        option.textContent = `[${assignment.id}] ${assignment.title}`;
        select.appendChild(option);
      });
  }
}

function getInviteLink(token) {
  if (!token) return '';
  const origin = window.location.origin;
  const basePath = window.location.pathname.replace(/\/admin\.php.*/, '');
  return `${origin}${basePath}/login.php?invite=${encodeURIComponent(token)}`;
}

async function loadTeams() {
  try {
    const response = await teamsUsersRequestJson('../api/admin/teams/list.php');
    if (response.ok) {
      teamsData = response.teams;
      renderTeams();
      updateTeamFilters();
    }
  } catch (err) {
    console.error('Load teams failed:', err);
  }
}

function renderTeams() {
  const tbody = $('teams-body');
  if (!tbody) return;
  
  tbody.innerHTML = '';
  
  teamsData.forEach(team => {
    const tr = document.createElement('tr');
    const inviteLink = getInviteLink(team.invite_token);
    const linkHtml = inviteLink
      ? `<a href="${inviteLink}" target="_blank" rel="noopener" class="mono" style="font-size:12px;">${escapeHtml(inviteLink)}</a>`
      : '<span style="color:var(--hspf-text-secondary);">-</span>';

    tr.innerHTML = `
      <td class="mono" style="cursor:pointer;" data-team-click="${team.id}">${team.id}</td>
      <td style="cursor:pointer;" data-team-click="${team.id}">${escapeHtml(team.name)}</td>
      <td style="cursor:pointer;" data-team-click="${team.id}">${escapeHtml(team.description || '')}</td>
      <td>${team.user_count}</td>
      <td>${linkHtml}</td>
      <td>${team.is_active ? '✓' : '✗'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" data-action="copy-team-invite" data-id="${team.id}" title="Copy invite link">🔗</button>
          <button class="icon-btn" data-action="regen-team-invite" data-id="${team.id}" title="Regenerate invite link">♻️</button>
          <button class="icon-btn" data-action="edit-team" data-id="${team.id}" title="Edit">✏️</button>
          <button class="icon-btn danger" data-action="delete-team" data-id="${team.id}" title="Delete">🗑️</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function updateTeamFilters() {
  ['users-team-filter', 'projects-team-filter', 'teams-members-team-filter', 'user-edit-team'].forEach((id) => {
    const filter = $(id);
    if (!filter) return;

    const currentValue = filter.value;
    if (id === 'teams-members-team-filter') {
      filter.innerHTML = '<option value="">Team auswählen</option>';
    } else if (id === 'user-edit-team') {
      filter.innerHTML = '<option value="">Kein Team</option>';
    } else {
      filter.innerHTML = '<option value="">All Teams</option>';
    }

    teamsData.forEach(team => {
      const option = document.createElement('option');
      option.value = team.id;
      option.textContent = team.name;
      filter.appendChild(option);
    });

    filter.value = currentValue;
  });
}

async function loadTeamMembers(teamId) {
  if (!teamId) {
    renderTeamMatrix(null);
    return;
  }

  try {
    const data = await teamsUsersRequestJson(`../api/admin/evaluation/team-matrix.php?team_id=${encodeURIComponent(teamId)}`);
    renderTeamMatrix(data);
  } catch (err) {
    console.error('Load team matrix failed:', err);
    renderTeamMatrix(null, err.message);
  }
}

function renderTeamMatrix(data, errorMessage = null) {
  const head = $('team-matrix-head');
  const body = $('team-matrix-body');
  hideTeamMatrixDetailBox();
  
  if (!head || !body) return;

  if (errorMessage) {
    body.innerHTML = `<tr><td colspan="100" style="text-align:center;padding:16px;color:#dc2626;">${escapeHtml(errorMessage)}</td></tr>`;
    return;
  }

  if (!data || !data.ok) {
    body.innerHTML = '<tr><td colspan="100" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Bitte oben ein Team auswählen.</td></tr>';
    return;
  }

  const assignments = data.assignments || [];
  const users = data.users || [];

  const ampelDot = (status) => {
    const map = {
      passed:          { bg: '#22c55e', title: 'Bestanden' },
      passed_delayed:  { bg: '#10b981', title: 'Bestanden (verspätet)' },
      failed:          { bg: '#ef4444', title: 'Nicht bestanden' },
      rework:          { bg: '#f97316', title: 'Nacharbeit' },
      in_progress:     { bg: '#facc15', title: 'In Bearbeitung' },
      submitted:       { bg: '#38bdf8', title: 'Eingereicht' },
      assigned:        { bg: '#d1d5db', title: 'Zugewiesen' },
    };
    const s = map[status] || map.assigned;
    return `<span title="${s.title}" style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${s.bg};"></span>`;
  };

  // Header row
  let thHtml = '<tr>'
    + '<th>Name</th>'
    + assignments.map(a => `<th style="text-align:center;font-size:11px;max-width:70px;white-space:normal;word-break:break-word;" title="${escapeHtml(a.title)}">${escapeHtml(a.short)}</th>`).join('')
    + '<th style="text-align:center;">Bestanden</th>'
    + '</tr>';
  head.innerHTML = thHtml;

  // Body rows
  if (users.length === 0) {
    body.innerHTML = `<tr><td colspan="${1 + assignments.length + 1}" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Keine Teilnehmer in diesem Team.</td></tr>`;
    return;
  }

  body.innerHTML = users.map(u => {
    const name = [u.last_name, u.first_name].filter(Boolean).join(', ') || u.email || `#${u.id}`;
    const dots = assignments.map(a => {
      const statusObj = (u.statuses || {})[a.id];
      const status = typeof statusObj === 'object' ? statusObj.status : statusObj;
      const isLate = typeof statusObj === 'object' ? statusObj.is_late : false;
      const isRework = typeof statusObj === 'object' ? statusObj.is_rework : false;
      let flags = '';
      if (isLate) flags += '<span title="Verspätet" style="margin-left:2px;">🕐</span>';
      if (isRework) flags += '<span title="Nacharbeit" style="margin-left:2px;">🔨</span>';
      const dotHtml = status
        ? `<span class="team-matrix-dot-trigger" data-user-id="${u.id}" data-assignment-id="${a.id}" style="display:inline-flex;align-items:center;cursor:help;">${ampelDot(status)}</span>`
        : '<span style="color:#e5e7eb;">–</span>';
      return `<td style="text-align:center;white-space:nowrap;">${dotHtml}${flags}</td>`;
    }).join('');
    const summaryColor = u.passed > 0 && u.passed === u.total ? '#15803d' : (u.passed > 0 ? '#b45309' : '#6b7280');
    const summary = `<span style="font-weight:700;color:${summaryColor};">${u.passed}/${u.total}</span>`;
    return `<tr>
      <td style="white-space:nowrap;">${escapeHtml(name)}</td>
      ${dots}
      <td style="text-align:center;">${summary}</td>
    </tr>`;
  }).join('');

  bindTeamMatrixDetailHover();
}

async function loadTeamAssignments(teamId) {
  if (!teamId) {
    teamAssignmentsData = [];
    renderTeamAssignments(null);
    return;
  }

  try {
    const response = await teamsUsersRequestJson(`../api/admin/teams/assignment-defaults/list.php?team_id=${encodeURIComponent(teamId)}`);
    teamAssignmentsData = Array.isArray(response.items) ? response.items : [];
    renderTeamAssignments(teamId);
  } catch (err) {
    console.error('Load team assignments failed:', err);
    teamAssignmentsData = [];
    renderTeamAssignments(null, err.message);
  }
}

function renderTeamAssignments(teamId, errorMessage = '') {
  const tbody = $('team-assignments-body');
  if (!tbody) return;

  if (!teamId) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Keine Standard-Assignments für dieses Team.</td></tr>';
    return;
  }

  if (errorMessage) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:16px;color:#b91c1c;">${escapeHtml(errorMessage)}</td></tr>`;
    return;
  }

  if (!teamAssignmentsData.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Keine Standard-Assignments für dieses Team.</td></tr>';
    return;
  }

  tbody.innerHTML = teamAssignmentsData.map((item) => {
    const deadlineText = item.effective_due_date ? formatShortDate(item.effective_due_date) : '-';
    const deadlineHint = item.team_due_date ? 'Team' : 'Assignment';
    return `
      <tr>
        <td class="mono">${item.assignment_id}</td>
        <td>
          <div>${escapeHtml(item.title)}</div>
          <div style="font-size:12px;color:var(--hspf-text-secondary);">${escapeHtml(item.difficulty || '')} · ${item.task_count || 0} Tasks</div>
        </td>
        <td>${formatShortDate(item.available_from)}</td>
        <td>
          <div>${deadlineText}</div>
          <div style="font-size:12px;color:var(--hspf-text-secondary);">${escapeHtml(deadlineHint)}</div>
        </td>
        <td>${formatShortDate(item.hard_deadline)}</td>
        <td>${item.allow_late_submission ? 'ja' : 'nein'}</td>
        <td>
          <div class="row-actions">
            <button class="icon-btn" data-action="edit-team-assignment" data-team-id="${item.team_id}" data-id="${item.assignment_id}" title="Team- und Zeitangaben bearbeiten">✏️</button>
            <button class="icon-btn" data-action="edit-assignment-settings" data-id="${item.assignment_id}" title="Assignment-Zeiten bearbeiten">⏰</button>
            <button class="icon-btn danger" data-action="delete-team-assignment" data-team-id="${item.team_id}" data-id="${item.assignment_id}" title="Standardzuordnung entfernen">🗑️</button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function openUserEditModal(user) {
  if (!user) return;
  $('user-edit-id').value = String(user.id);
  $('user-edit-email').value = user.email || '';
  $('user-edit-first-name').value = user.first_name || '';
  $('user-edit-last-name').value = user.last_name || '';
  $('user-edit-team').value = user.team_id ? String(user.team_id) : '';
  $('user-edit-role').value = user.role || 'user';
  $('user-edit-status').value = user.status || 'aktiv';
  $('user-edit-modal')?.classList.add('active');
}

function closeUserEditModal() {
  $('user-edit-modal')?.classList.remove('active');
}

async function submitUserEdit(e) {
  e.preventDefault();
  const userId = parseInt($('user-edit-id')?.value || '0', 10);
  if (!userId) return;

  const email = ($('user-edit-email')?.value || '').trim();
  if (!email) {
    alert('E-Mail ist erforderlich.');
    return;
  }

  const payload = {
    email,
    first_name: ($('user-edit-first-name')?.value || '').trim(),
    last_name: ($('user-edit-last-name')?.value || '').trim(),
    team_id: $('user-edit-team')?.value ? parseInt($('user-edit-team').value, 10) : null,
    role: $('user-edit-role')?.value || 'user',
    status: $('user-edit-status')?.value || 'aktiv'
  };

  try {
    await updateUser(userId, payload);
    alert('User updated');
    closeUserEditModal();
    await loadUsers();

    const selectedTeamId = $('teams-members-team-filter')?.value || '';
    if (selectedTeamId) {
      await loadTeamMembers(parseInt(selectedTeamId, 10));
    }
  } catch (err) {
    alert('Update failed: ' + err.message);
  }
}

async function loadSemesters() {
  try {
    const response = await teamsUsersRequestJson('../api/system/semester.php?action=list');
    if (response.ok) {
      semestersData = response.semesters || [];
      updateSemesterFilters();
    }
  } catch (err) {
    console.error('Load semesters failed:', err);
  }
}

function updateSemesterFilters() {
  ['users-semester-filter', 'projects-semester-filter'].forEach((id) => {
    const filter = $(id);
    if (!filter) return;

    const currentValue = filter.value;
    filter.innerHTML = '<option value="">All Semesters</option>';

    semestersData.forEach((entry) => {
      const option = document.createElement('option');
      option.value = entry.semester;
      option.textContent = `${entry.semester} (${entry.count})`;
      filter.appendChild(option);
    });

    filter.value = currentValue;
  });
}

async function createTeam(data) {
  try {
    const response = await teamsUsersRequestJson('../api/admin/teams/create.php', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    
    if (response.ok) {
      alert('Team created');
      loadTeams();
    } else {
      alert('Error: ' + response.error);
    }
  } catch (err) {
    alert('Create failed: ' + err.message);
  }
}

async function updateTeam(id, data) {
  try {
    const response = await teamsUsersRequestJson(`../api/admin/teams/update.php?id=${id}`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
    
    if (response.ok) {
      alert('Team updated');
      loadTeams();
    } else {
      alert('Error: ' + response.error);
    }
  } catch (err) {
    alert('Update failed: ' + err.message);
  }
}

async function deleteTeam(id) {
  if (!confirm('Delete team? Users will be unassigned.')) return;
  
  try {
    const response = await teamsUsersRequestJson(`../api/admin/teams/delete.php?id=${id}`, {
      method: 'POST'
    });
    
    if (response.ok) {
      alert('Team deleted');
      loadTeams();
      loadUsers();
    } else {
      alert('Error: ' + response.error);
    }
  } catch (err) {
    alert('Delete failed: ' + err.message);
  }
}

// ========== USERS ==========

let usersData = [];
let selectedUserIds = new Set();
let selectedUsersCache = new Map();
let usersPagination = {
  page: 1,
  limit: 25,
  totalPages: 1,
  total: 0
};

async function loadUsers() {
  try {
    const teamFilter = $('users-team-filter')?.value || '';
    const semesterFilter = $('users-semester-filter')?.value || '';
    const search = $('users-search')?.value || '';
    const limit = parseInt($('users-limit')?.value, 10) || usersPagination.limit || 25;
    
    const params = new URLSearchParams();
    if (teamFilter) params.append('team_id', teamFilter);
    if (semesterFilter) params.append('semester', semesterFilter);
    if (search) params.append('search', search);
    params.append('page', String(usersPagination.page));
    params.append('limit', String(limit));
    
    const url = '../api/admin/users/list.php' + (params.toString() ? '?' + params.toString() : '');
    const response = await teamsUsersRequestJson(url);
    
    if (response.ok) {
      usersData = response.users;
      usersPagination.page = response.page || 1;
      usersPagination.limit = response.limit || limit;
      usersPagination.totalPages = response.total_pages || 1;
      usersPagination.total = response.total || response.count || 0;
      usersData.forEach((user) => {
        if (selectedUserIds.has(user.id)) {
          selectedUsersCache.set(user.id, user);
        }
      });
      renderUsers();
    }
  } catch (err) {
    console.error('Load users failed:', err);
  }
}

function renderUsers() {
  const tbody = $('users-body');
  if (!tbody) return;
  
  tbody.innerHTML = '';
  // Don't clear selectedUserIds - keep selections across re-renders

  if (!usersData.length) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Keine Benutzer gefunden.</td></tr>';
    updateUsersPagination();
    return;
  }
  
  usersData.forEach(user => {
    const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ') || '-';
    
    // Format assignment stats: "2 (⚪:1 🟡:1 🟢:0 ⚫:0)"
    const stats = user.assignment_stats || { total: 0, unstarted: 0, in_progress: 0, passed: 0, failed: 0 };
    const statsText = `${stats.total} (⚪:${stats.unstarted} 🟡:${stats.in_progress} 🟢:${stats.passed} ⚫:${stats.failed})`;
    
    const isChecked = selectedUserIds.has(user.id);
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="checkbox" class="user-checkbox" data-user-id="${user.id}" ${isChecked ? 'checked' : ''}></td>
      <td class="mono">${user.id}</td>
      <td>${escapeHtml(user.email)}</td>
      <td>${escapeHtml(fullName)}</td>
      <td>${escapeHtml(user.team_name || '-')}</td>
      <td>${escapeHtml(user.semester || '-')}</td>
      <td>${statsText}</td>
      <td>${user.role === 'admin' ? '🔑 Admin' : 'User'}</td>
      <td>${user.status || 'aktiv'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn danger" data-action="delete-user" data-id="${user.id}" title="Delete User">🗑️</button>
          <button class="icon-btn" data-action="edit-user" data-id="${user.id}" title="Edit">✏️</button>
          <button class="icon-btn warn" data-action="create-reset-link" data-id="${user.id}" title="Passwort-Reset-Link erzeugen">🔐</button>
          <button class="icon-btn" data-action="show-user-projects" data-id="${user.id}" data-user-label="${escapeHtml(user.email)}" title="Projekte anzeigen">📁</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  // Update select-all checkbox
  const selectAll = $('select-all-users');
  if (selectAll) {
    selectAll.checked = usersData.length > 0 && usersData.every(user => selectedUserIds.has(user.id));
  }

  updateUsersPagination();
}

function updateUsersPagination() {
  const totalPages = Math.max(1, usersPagination.totalPages || 1);
  if ($('users-page-info')) {
    $('users-page-info').textContent = `Page ${usersPagination.page} of ${totalPages} · ${usersPagination.total} users`;
  }
  if ($('users-prev')) {
    $('users-prev').disabled = usersPagination.page <= 1;
  }
  if ($('users-next')) {
    $('users-next').disabled = usersPagination.page >= totalPages;
  }
}

async function deleteUser(userId) {
  const user = usersData.find(u => u.id === userId) || selectedUsersCache.get(userId);
  const label = user ? `${user.email} (#${userId})` : `User #${userId}`;
  if (!confirm(`Benutzer löschen: ${label}?`)) return;

  try {
    await teamsUsersRequestJson(`../api/admin/users/delete.php?id=${userId}`, {
      method: 'POST'
    });

    selectedUserIds.delete(userId);
    selectedUsersCache.delete(userId);
    await loadUsers();
    alert('Benutzer gelöscht');
  } catch (err) {
    alert('Löschen fehlgeschlagen: ' + err.message);
  }
}

async function bulkDeleteUsers() {
  if (selectedUserIds.size === 0) {
    alert('Bitte mindestens einen Benutzer auswählen');
    return;
  }

  const ids = Array.from(selectedUserIds);
  if (!confirm(`${ids.length} ausgewählte Benutzer löschen?`)) return;

  try {
    const response = await teamsUsersRequestJson('../api/admin/users/bulk-delete.php', {
      method: 'POST',
      body: JSON.stringify({ user_ids: ids })
    });

    if (response.ok) {
      const deletedIds = Array.isArray(response.deleted_ids) ? response.deleted_ids : [];
      deletedIds.forEach((id) => {
        selectedUserIds.delete(id);
        selectedUsersCache.delete(id);
      });

      const blockedCount = Array.isArray(response.blocked) ? response.blocked.length : 0;
      const missingCount = Array.isArray(response.missing_ids) ? response.missing_ids.length : 0;
      const deletedCount = response.deleted_count || deletedIds.length;

      await loadUsers();

      if (blockedCount || missingCount) {
        alert(`Gelöscht: ${deletedCount} · Blockiert: ${blockedCount} · Nicht gefunden: ${missingCount}`);
      } else {
        alert(`${deletedCount} Benutzer gelöscht`);
      }
    }
  } catch (err) {
    alert('Bulk-Löschen fehlgeschlagen: ' + err.message);
  }
}

// Select all users checkbox
document.addEventListener('click', (e) => {
  if (e.target.id === 'select-all-users') {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => {
      cb.checked = e.target.checked;
      const userId = parseInt(cb.dataset.userId);
      const user = usersData.find(u => u.id === userId);
      if (e.target.checked) {
        selectedUserIds.add(userId);
        if (user) selectedUsersCache.set(userId, user);
      } else {
        selectedUserIds.delete(userId);
        selectedUsersCache.delete(userId);
      }
    });
  } else if (e.target.classList.contains('user-checkbox')) {
    const userId = parseInt(e.target.dataset.userId);
    if (e.target.checked) {
      selectedUserIds.add(userId);
      const user = usersData.find(u => u.id === userId);
      if (user) selectedUsersCache.set(userId, user);
    } else {
      selectedUserIds.delete(userId);
      selectedUsersCache.delete(userId);
    }
  }
});

// Bulk assign users to assignment
async function openBulkAssignModal() {
  if (selectedUserIds.size === 0) {
    alert('Bitte mindestens einen Benutzer auswählen');
    return;
  }
  
  const modal = document.getElementById('bulk-assign-modal');
  const usersList = document.getElementById('bulk-assign-users-list');
  const countDiv = document.getElementById('bulk-assign-count');
  
  usersList.innerHTML = '';
  selectedUserIds.forEach(userId => {
    const user = selectedUsersCache.get(userId) || usersData.find(u => u.id === userId);
    if (user) {
      const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ') || '-';
      const div = document.createElement('div');
      div.style.padding = '4px 8px';
      div.style.marginBottom = '4px';
      div.style.backgroundColor = 'var(--hspf-bg-secondary)';
      div.style.borderRadius = '4px';
      div.textContent = `${user.email} (${fullName})`;
      usersList.appendChild(div);
    }
  });
  countDiv.textContent = `${selectedUserIds.size} Benutzer ausgewählt`;

  try {
    await populateAssignmentSelect('bulk-assign-assignment');
  } catch (err) {
    console.error('Load assignments failed:', err);
  }
  
  modal.style.display = 'flex';
}

async function openTeamAssignModal(teamId, existingItem = null) {
  const team = teamsData.find(t => t.id === teamId);
  if (!team) {
    alert('Team nicht gefunden');
    return;
  }

  const modal = document.getElementById('team-assign-modal');
  const statusDiv = document.getElementById('team-assign-status');
  const teamIdInput = document.getElementById('team-assign-team-id');
  const teamNameEl = document.getElementById('team-assign-team-name');
  const dueDateInput = document.getElementById('team-assign-due-date');
  const assignmentDueDateDisplay = document.getElementById('team-assign-assignment-due-date-display');
  const assignmentSelect = document.getElementById('team-assign-assignment');

  teamIdInput.value = String(team.id);
  teamNameEl.textContent = `${team.name} (#${team.id})`;
  dueDateInput.value = existingItem ? toDatetimeLocalValue(existingItem.team_due_date || existingItem.effective_due_date) : '';
  if (assignmentDueDateDisplay) assignmentDueDateDisplay.value = existingItem ? toDatetimeLocalValue(existingItem.assignment_due_date) : '';
  statusDiv.style.display = 'none';
  statusDiv.textContent = '';

  try {
    await populateAssignmentSelect('team-assign-assignment');
    if (existingItem && assignmentSelect) {
      assignmentSelect.value = String(existingItem.assignment_id);
    }
  } catch (err) {
    console.error('Load assignments failed:', err);
    statusDiv.style.display = 'block';
    statusDiv.textContent = 'Assignments konnten nicht geladen werden: ' + err.message;
  }

  modal.style.display = 'flex';
}

async function openAssignmentSettingsModal(assignmentId) {
  try {
    const response = await teamsUsersRequestJson(`../api/assignments/get.php?id=${assignmentId}`);
    const a = response.assignment;
    if (!a) throw new Error('Assignment not found');

    $('assignment-id').value = a.id;
    $('assignment-title').value = a.title || '';
    $('assignment-description').value = a.description || '';
    $('assignment-difficulty').value = a.difficulty || 'beginner';
    $('assignment-active').value = a.is_active ? 'true' : 'false';
    if ($('assignment-available-from')) $('assignment-available-from').value = toDatetimeLocalValue(a.available_from);
    if ($('assignment-due-date')) $('assignment-due-date').value = toDatetimeLocalValue(a.due_date);
    if ($('assignment-hard-deadline')) $('assignment-hard-deadline').value = toDatetimeLocalValue(a.hard_deadline);
    if ($('assignment-allow-late')) $('assignment-allow-late').value = a.allow_late_submission === false ? 'false' : 'true';
    if ($('assignment-modal-title')) $('assignment-modal-title').textContent = `Edit Assignment #${a.id}`;
    $('assignment-modal')?.classList.add('active');
  } catch (err) {
    alert('Assignment konnte nicht geladen werden: ' + err.message);
  }
}

async function deleteTeamAssignmentDefault(teamId, assignmentId) {
  const item = teamAssignmentsData.find(x => x.team_id === teamId && x.assignment_id === assignmentId);
  const title = item?.title || `Assignment #${assignmentId}`;

  if (!confirm(`Standardzuordnung für ${title} aus diesem Team entfernen?\n\nBestehende User-Assignments bleiben erhalten.`)) {
    return;
  }

  await teamsUsersRequestJson('../api/admin/teams/assignment-defaults/delete.php', {
    method: 'POST',
    body: JSON.stringify({ team_id: teamId, assignment_id: assignmentId })
  });

  await loadTeamMembers(teamId);
}

function closeTeamAssignModal() {
  const modal = document.getElementById('team-assign-modal');
  if (modal) modal.style.display = 'none';
}

async function submitTeamAssign(e) {
  e.preventDefault();

  const teamId = parseInt(document.getElementById('team-assign-team-id')?.value || '0', 10);
  const assignmentId = document.getElementById('team-assign-assignment')?.value || '';
  const dueDate = document.getElementById('team-assign-due-date')?.value || '';
  const statusDiv = document.getElementById('team-assign-status');

  if (!teamId || !assignmentId) {
    statusDiv.style.display = 'block';
    statusDiv.className = 'error';
    statusDiv.textContent = 'Bitte Team und Assignment auswählen';
    return;
  }

  statusDiv.style.display = 'block';
  statusDiv.className = 'info';
  statusDiv.textContent = 'Speichere Team-Zuordnung...';

  try {
    const body = {
      assignment_id: parseInt(assignmentId, 10),
      team_id: teamId
    };

    if (dueDate) {
      body.due_date = toMysqlDateTime(dueDate);
    } else {
      body.due_date = null;
    }

    const response = await teamsUsersRequestJson('../api/admin/assignments/bulk-assign.php', {
      method: 'POST',
      body: JSON.stringify(body)
    });

    const defaultsSaved = response.assigned_count || 0;
    const materialized = response.materialized_count || 0;
    statusDiv.className = 'success';
    statusDiv.textContent = `✓ Team-Zuordnung gespeichert | Defaults: ${defaultsSaved} | Aktuelle Mitglieder: ${materialized}`;

    // Reload both matrix and assignments for the team
    if (window.selectedTeamId && window.selectedTeamId === teamId) {
      await loadTeamMembers(teamId);
      await loadTeamAssignments(teamId);
    }
    await loadUsers();

    setTimeout(() => {
      closeTeamAssignModal();
    }, 1200);
  } catch (err) {
    statusDiv.className = 'error';
    statusDiv.textContent = 'Fehler: ' + err.message;
  }
}

async function submitBulkAssign(e) {
  e.preventDefault();
  
  const assignmentId = document.getElementById('bulk-assign-assignment').value;
  const dueDate = document.getElementById('bulk-assign-due-date').value;
  const statusDiv = document.getElementById('bulk-assign-status');
  
  if (!assignmentId) {
    statusDiv.style.display = 'block';
    statusDiv.className = 'error';
    statusDiv.textContent = 'Bitte eine Assignment auswählen';
    return;
  }
  
  statusDiv.style.display = 'block';
  statusDiv.className = 'info';
  statusDiv.textContent = 'Verteile...';
  
  try {
    const body = {
      assignment_id: parseInt(assignmentId),
      user_ids: Array.from(selectedUserIds)
    };
    if (dueDate) {
      body.due_date = toMysqlDateTime(dueDate);
    }
    
    const response = await teamsUsersRequestJson('../api/admin/assignments/bulk-assign.php', {
      method: 'POST',
      body: JSON.stringify(body)
    });
    
    if (response.ok) {
      statusDiv.className = 'success';
      const assigned = response.assigned_count || 0;
      const skipped = response.skipped_count || 0;
      const materialized = response.materialized_count || 0;
      statusDiv.textContent = `✓ Zugewiesen: ${assigned} | Neu materialisiert: ${materialized} | Uebersprungen: ${skipped}`;
      
      setTimeout(() => {
        document.getElementById('bulk-assign-modal').style.display = 'none';
        selectedUserIds.clear();
        selectedUsersCache.clear();
        renderUsers();
      }, 1500);
    } else {
      statusDiv.className = 'error';
      statusDiv.textContent = 'Fehler: ' + response.error;
    }
  } catch (err) {
    statusDiv.className = 'error';
    statusDiv.textContent = 'Fehler: ' + err.message;
  }
}

// Update user (change team, role, status)
async function updateUser(userId, data) {
  const response = await teamsUsersRequestJson(`../api/admin/users/update.php?id=${userId}`, {
    method: 'POST',
    body: JSON.stringify(data)
  });

  if (!response.ok) {
    throw new Error(response.error || 'Update failed');
  }

  return response;
}

async function createResetLinkForUser(userId) {
  const user = usersData.find((u) => u.id === userId) || selectedUsersCache.get(userId);
  const label = user ? `${user.email} (#${userId})` : `User #${userId}`;

  if (!confirm(`Reset-Link für ${label} erzeugen?`)) return;

  const response = await teamsUsersRequestJson('../api/admin/users/create-reset-link.php', {
    method: 'POST',
    body: JSON.stringify({ user_id: userId })
  });

  const link = response.reset_link;
  if (!link) {
    throw new Error('Kein Reset-Link zurückgegeben');
  }

  try {
    await navigator.clipboard.writeText(link);
    alert(`Reset-Link erstellt und kopiert:\n${link}`);
  } catch (_) {
    prompt('Reset-Link (kopieren):', link);
  }
}

// ========== EVENT HANDLERS ==========

// Team filter change
$('users-team-filter')?.addEventListener('change', () => {
  usersPagination.page = 1;
  loadUsers();
});

$('users-semester-filter')?.addEventListener('change', () => {
  usersPagination.page = 1;
  loadUsers();
});

// User search
let searchTimeout;
$('users-search')?.addEventListener('input', () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    usersPagination.page = 1;
    loadUsers();
  }, 300);
});

$('users-limit')?.addEventListener('change', () => {
  usersPagination.page = 1;
  loadUsers();
});

$('users-prev')?.addEventListener('click', () => {
  if (usersPagination.page <= 1) return;
  usersPagination.page -= 1;
  loadUsers();
});

$('users-next')?.addEventListener('click', () => {
  if (usersPagination.page >= usersPagination.totalPages) return;
  usersPagination.page += 1;
  loadUsers();
});

// Bulk assign button
$('bulk-assign-btn')?.addEventListener('click', () => {
  openBulkAssignModal();
});

$('bulk-delete-users-btn')?.addEventListener('click', () => {
  bulkDeleteUsers();
});

$('teams-members-team-filter')?.addEventListener('change', async () => {
  const teamId = $('teams-members-team-filter')?.value || '';
  if (teamId) {
    const tid = parseInt(teamId, 10);
    await loadTeamMembers(tid);
    await loadTeamAssignments(tid);
  } else {
    await loadTeamMembers(null);
    await loadTeamAssignments(null);
  }
});

document.getElementById('user-edit-form')?.addEventListener('submit', submitUserEdit);
document.getElementById('user-edit-close-btn')?.addEventListener('click', closeUserEditModal);
document.getElementById('user-edit-cancel-btn')?.addEventListener('click', closeUserEditModal);
document.getElementById('user-edit-modal')?.addEventListener('click', (e) => {
  if (e.target.id === 'user-edit-modal') {
    closeUserEditModal();
  }
});

// Bulk assign modal form
document.getElementById('bulk-assign-form')?.addEventListener('submit', submitBulkAssign);
document.getElementById('team-assign-form')?.addEventListener('submit', submitTeamAssign);

// Close bulk assign modal
document.getElementById('bulk-assign-close-btn')?.addEventListener('click', () => {
  document.getElementById('bulk-assign-modal').style.display = 'none';
});

document.getElementById('bulk-assign-cancel-btn')?.addEventListener('click', () => {
  document.getElementById('bulk-assign-modal').style.display = 'none';
});

document.getElementById('team-assign-close-btn')?.addEventListener('click', closeTeamAssignModal);
document.getElementById('team-assign-cancel-btn')?.addEventListener('click', closeTeamAssignModal);
document.getElementById('team-assign-modal')?.addEventListener('click', (e) => {
  if (e.target.id === 'team-assign-modal') {
    closeTeamAssignModal();
  }
});

// Table click delegation
document.addEventListener('click', async (e) => {
  if (!e.target.hasAttribute('data-action')) return;
  
  const action = e.target.dataset.action;
  const id = parseInt(e.target.dataset.id);
  
  // Teams
  if (action === 'edit-team') {
    const team = teamsData.find(t => t.id === id);
    if (!team) return;
    
    const name = prompt('Team Name:', team.name);
    if (!name) return;
    
    const description = prompt('Description:', team.description || '');
    const isActive = confirm('Is Active?');
    
    await updateTeam(id, { name, description, is_active: isActive ? 1 : 0 });
  } else if (action === 'assign-team-assignment') {
    if ($('teams-members-team-filter')) {
      $('teams-members-team-filter').value = String(id);
    }
    await loadTeamMembers(id);
    await openTeamAssignModal(id);
  } else if (action === 'edit-team-assignment') {
    const teamId = parseInt(e.target.dataset.teamId || '0', 10);
    const item = teamAssignmentsData.find(x => x.team_id === teamId && x.assignment_id === id);
    await openTeamAssignModal(teamId, item || null);
  } else if (action === 'edit-assignment-settings') {
    await openAssignmentSettingsModal(id);
  } else if (action === 'delete-team-assignment') {
    const teamId = parseInt(e.target.dataset.teamId || '0', 10);
    await deleteTeamAssignmentDefault(teamId, id);
  } else if (action === 'delete-team') {
    await deleteTeam(id);
  } else if (action === 'copy-team-invite') {
    const team = teamsData.find(t => t.id === id);
    if (!team || !team.invite_token) {
      alert('Kein Einladungslink vorhanden');
      return;
    }
    const inviteLink = getInviteLink(team.invite_token);
    try {
      await navigator.clipboard.writeText(inviteLink);
      alert('Einladungslink kopiert');
    } catch (_) {
      prompt('Einladungslink kopieren:', inviteLink);
    }
  } else if (action === 'regen-team-invite') {
    if (!confirm('Einladungslink neu erzeugen? Der alte Link wird ungültig.')) return;
    await updateTeam(id, { regenerate_invite: true });
  }
  // Users
  else if (action === 'edit-user') {
    const user = usersData.find(u => u.id === id);
    if (!user) return;
    openUserEditModal(user);
  } else if (action === 'show-user-projects') {
    const userLabel = e.target.dataset.userLabel || `User #${id}`;
    if (typeof window.openAdminProjectsForUser === 'function') {
      await window.openAdminProjectsForUser(id, userLabel);
    }
  } else if (action === 'delete-user') {
    await deleteUser(id);
  } else if (action === 'create-reset-link') {
    try {
      await createResetLinkForUser(id);
    } catch (err) {
      alert('Reset-Link konnte nicht erzeugt werden: ' + err.message);
    }
  } else if (action === 'show-team-members') {
    const team = teamsData.find(t => t.id === id);
    if (!team) return;
    showTeamDetail(id, team.name);
  }
});

// Team click handler - show detail cards
document.addEventListener('click', (e) => {
  const teamClick = e.target.dataset.teamClick;
  if (!teamClick) return;
  
  const teamId = parseInt(teamClick, 10);
  const team = teamsData.find(t => t.id === teamId);
  if (!team) return;
  
  showTeamDetail(teamId, team.name);
});

// Show team detail cards and load data
async function showTeamDetail(teamId, teamName) {
  const detailCard = $('team-detail-card');
  const assignmentCard = $('team-assignments-detail-card');
  
  if (!detailCard || !assignmentCard) return;
  
  // Show cards
  detailCard.style.display = 'block';
  assignmentCard.style.display = 'block';
  
  // Update team name labels
  $('selected-team-name').textContent = escapeHtml(teamName);
  $('selected-team-name-2').textContent = escapeHtml(teamName);
  
  // Store team ID globally for plus button handler
  window.selectedTeamId = teamId;

  // Highlight selected team row
  document.querySelectorAll('#teams-body tr').forEach(row => row.style.backgroundColor = '');
  const activeRow = document.querySelector(`#teams-body td[data-team-click="${teamId}"]`)?.closest('tr');
  if (activeRow) activeRow.style.backgroundColor = '#dbeafe';
  
  // Show plus button for new assignment
  const addBtn = $('add-team-assignment-btn');
  if (addBtn) addBtn.style.display = 'inline-block';
  
  // Load team matrix
  await loadTeamMembers(teamId);
  
  // Load team assignments
  await loadTeamAssignments(teamId);
  
  // Scroll to detail cards
  setTimeout(() => {
    assignmentCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 100);
}

// Open team modal
$('open-team-modal')?.addEventListener('click', () => {
  const name = prompt('Team Name:');
  if (!name) return;
  
  const description = prompt('Description:');
  const isActive = confirm('Is Active?');
  
  createTeam({ name, description, is_active: isActive ? 1 : 0 });
});

// Add new team assignment button
$('add-team-assignment-btn')?.addEventListener('click', async () => {
  const teamName = $('selected-team-name-2')?.textContent || '';
  const teamIdInput = $('team-assign-team-id');
  
  if (!teamIdInput || !teamName) {
    alert('Kein Team ausgewählt');
    return;
  }
  
  // Extract team ID from the currently displayed team
  // The team ID should be stored somewhere - let's use a global variable for now
  if (!window.selectedTeamId) {
    alert('Fehler: Team-ID nicht gefunden');
    return;
  }
  
  await openTeamAssignModal(window.selectedTeamId);
});

// Initial load
if (window.location.pathname.includes('admin.php')) {
  document.addEventListener('DOMContentLoaded', () => {
    loadTeams();
    loadSemesters();
    loadUsers();
  });
}
