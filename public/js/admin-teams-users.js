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

function toMysqlDateTime(localValue) {
  if (!localValue) return null;
  const d = new Date(localValue);
  if (Number.isNaN(d.getTime())) return null;
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
}

function toDatetimeLocalValue(dateTimeStr) {
  if (!dateTimeStr) return '';
  const d = new Date(String(dateTimeStr).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
}

function formatShortDate(dateTimeStr) {
  if (!dateTimeStr) return '-';
  const d = new Date(String(dateTimeStr).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '-';
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  return `${dd}.${mm}.${yyyy}`;
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
      <td class="mono">${team.id}</td>
      <td>${escapeHtml(team.name)}</td>
      <td>${escapeHtml(team.description || '')}</td>
      <td>${team.user_count}</td>
      <td>${linkHtml}</td>
      <td>${team.is_active ? '✓' : '✗'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" data-action="show-team-members" data-id="${team.id}" title="Teilnehmer anzeigen">👥 Teilnehmer</button>
          <button class="icon-btn" data-action="assign-team-assignment" data-id="${team.id}" title="Assignments zuordnen">📚 Assignments</button>
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
    teamMembersData = [];
    teamAssignmentsData = [];
    renderTeamMembers();
    renderTeamAssignments();
    return;
  }

  try {
    const params = new URLSearchParams();
    params.set('team_id', String(teamId));
    params.set('limit', '100');
    params.set('page', '1');
    const [usersResponse, assignmentsResponse] = await Promise.all([
      teamsUsersRequestJson(`../api/admin/users/list.php?${params.toString()}`),
      teamsUsersRequestJson(`../api/admin/teams/assignment-defaults/list.php?team_id=${encodeURIComponent(teamId)}`)
    ]);
    teamMembersData = Array.isArray(usersResponse.users) ? usersResponse.users : [];
    teamAssignmentsData = Array.isArray(assignmentsResponse.items) ? assignmentsResponse.items : [];
    renderTeamMembers();
    renderTeamAssignments();
  } catch (err) {
    console.error('Load team members failed:', err);
    teamMembersData = [];
    teamAssignmentsData = [];
    renderTeamMembers();
    renderTeamAssignments(err.message);
  }
}

async function loadTeamAssignments(teamId) {
  if (!teamId) {
    teamAssignmentsData = [];
    renderTeamAssignments();
    return;
  }

  try {
    const response = await teamsUsersRequestJson(`../api/admin/teams/assignment-defaults/list.php?team_id=${encodeURIComponent(teamId)}`);
    teamAssignmentsData = Array.isArray(response.items) ? response.items : [];
    renderTeamAssignments();
  } catch (err) {
    console.error('Load team assignments failed:', err);
    teamAssignmentsData = [];
    renderTeamAssignments(err.message);
  }
}

function renderTeamMembers() {
  const tbody = $('teams-members-body');
  if (!tbody) return;

  const teamId = $('teams-members-team-filter')?.value || '';
  const search = ($('teams-members-search')?.value || '').trim().toLowerCase();

  if (!teamId) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Bitte oben in der Team-Zeile auf 👥 Teilnehmer oder 📚 Assignments klicken.</td></tr>';
    return;
  }

  const filtered = teamMembersData.filter((u) => {
    if (!search) return true;
    const fullName = [u.first_name, u.last_name].filter(Boolean).join(' ').toLowerCase();
    return String(u.email || '').toLowerCase().includes(search)
      || fullName.includes(search)
      || String(u.first_name || '').toLowerCase().includes(search)
      || String(u.last_name || '').toLowerCase().includes(search);
  });

  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Keine Teilnehmer gefunden.</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map((u) => {
    const fullName = [u.first_name, u.last_name].filter(Boolean).join(' ') || '-';
    const stats = u.assignment_stats || { total: 0, unstarted: 0, in_progress: 0, passed: 0, failed: 0 };
    const statsText = `${stats.total} (⚪:${stats.unstarted} 🟡:${stats.in_progress} 🟢:${stats.passed} ⚫:${stats.failed})`;
    return `
      <tr>
        <td class="mono">${u.id}</td>
        <td>${escapeHtml(u.email)}</td>
        <td>${escapeHtml(fullName)}</td>
        <td>${escapeHtml(u.team_name || '-')}</td>
        <td>${escapeHtml(u.semester || '-')}</td>
        <td>${statsText}</td>
        <td>${u.role === 'admin' ? '🔑 Admin' : 'User'}</td>
        <td>${escapeHtml(u.status || 'aktiv')}</td>
      </tr>
    `;
  }).join('');
}

function renderTeamAssignments(errorMessage = '') {
  const tbody = $('team-assignments-body');
  if (!tbody) return;

  const teamId = $('teams-members-team-filter')?.value || '';
  if (!teamId) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Bitte oben in der Team-Zeile auf 📚 Assignments klicken.</td></tr>';
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

    const selectedTeamId = $('teams-members-team-filter')?.value || '';
    if (selectedTeamId && parseInt(selectedTeamId, 10) === teamId) {
      await loadTeamMembers(teamId);
    } else {
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
  await loadTeamMembers(teamId ? parseInt(teamId, 10) : null);
});

$('teams-members-search')?.addEventListener('input', () => {
  renderTeamMembers();
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
    $('teams-members-team-filter').value = String(id);
    await loadTeamMembers(id);
  }
});

// Open team modal
$('open-team-modal')?.addEventListener('click', () => {
  const name = prompt('Team Name:');
  if (!name) return;
  
  const description = prompt('Description:');
  const isActive = confirm('Is Active?');
  
  createTeam({ name, description, is_active: isActive ? 1 : 0 });
});

// Initial load
if (window.location.pathname.includes('admin.php')) {
  document.addEventListener('DOMContentLoaded', () => {
    loadTeams();
    loadSemesters();
    loadUsers();
  });
}
