/**
 * Admin Teams & Users Management
 */

// ========== TEAMS ==========

let teamsData = [];

async function loadTeams() {
  try {
    const response = await requestJson('../api/admin/teams/list.php');
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
    tr.innerHTML = `
      <td class="mono">${team.id}</td>
      <td>${escapeHtml(team.name)}</td>
      <td>${escapeHtml(team.description || '')}</td>
      <td>${team.user_count}</td>
      <td>${team.is_active ? '✓' : '✗'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" data-action="edit-team" data-id="${team.id}" title="Edit">✏️</button>
          <button class="icon-btn danger" data-action="delete-team" data-id="${team.id}" title="Delete">🗑️</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function updateTeamFilters() {
  const filter = $('users-team-filter');
  if (!filter) return;
  
  const currentValue = filter.value;
  filter.innerHTML = '<option value="">All Teams</option>';
  
  teamsData.forEach(team => {
    const option = document.createElement('option');
    option.value = team.id;
    option.textContent = team.name;
    filter.appendChild(option);
  });
  
  filter.value = currentValue;
}

async function createTeam(data) {
  try {
    const response = await requestJson('../api/admin/teams/create.php', {
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
    const response = await requestJson(`../api/admin/teams/update.php?id=${id}`, {
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
    const response = await requestJson(`../api/admin/teams/delete.php?id=${id}`, {
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

async function loadUsers() {
  try {
    const teamFilter = $('users-team-filter')?.value || '';
    const search = $('users-search')?.value || '';
    
    const params = new URLSearchParams();
    if (teamFilter) params.append('team_id', teamFilter);
    if (search) params.append('search', search);
    
    const url = '../api/admin/users/list.php' + (params.toString() ? '?' + params.toString() : '');
    const response = await requestJson(url);
    
    if (response.ok) {
      usersData = response.users;
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
  selectedUserIds.clear();
  
  usersData.forEach(user => {
    const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ') || '-';
    
    // Format assignment stats: "2 (⚪:1 🟡:1 🟢:0 ⚫:0)"
    const stats = user.assignment_stats || { total: 0, unstarted: 0, in_progress: 0, passed: 0, failed: 0 };
    const statsText = `${stats.total} (⚪:${stats.unstarted} 🟡:${stats.in_progress} 🟢:${stats.passed} ⚫:${stats.failed})`;
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="checkbox" class="user-checkbox" data-user-id="${user.id}"></td>
      <td class="mono">${user.id}</td>
      <td>${escapeHtml(user.email)}</td>
      <td>${escapeHtml(fullName)}</td>
      <td>${escapeHtml(user.team_name || '-')}</td>
      <td>${statsText}</td>
      <td>${user.role === 'admin' ? '🔑 Admin' : 'User'}</td>
      <td>${user.status || 'aktiv'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" data-action="edit-user" data-id="${user.id}" title="Edit">✏️</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  // Update select-all checkbox
  const selectAll = $('select-all-users');
  if (selectAll) {
    selectAll.checked = false;
  }
}

// Select all users checkbox
document.addEventListener('click', (e) => {
  if (e.target.id === 'select-all-users') {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => {
      cb.checked = e.target.checked;
      const userId = parseInt(cb.dataset.userId);
      if (e.target.checked) {
        selectedUserIds.add(userId);
      } else {
        selectedUserIds.delete(userId);
      }
    });
  } else if (e.target.classList.contains('user-checkbox')) {
    const userId = parseInt(e.target.dataset.userId);
    if (e.target.checked) {
      selectedUserIds.add(userId);
    } else {
      selectedUserIds.delete(userId);
    }
  }
});

// Bulk assign users to assignment
async function bulkAssignUsers() {
  if (selectedUserIds.size === 0) {
    alert('Please select at least one user');
    return;
  }
  
  const assignmentId = prompt('Enter Assignment ID to assign:');
  if (!assignmentId) return;
  
  try {
    const response = await requestJson('../api/admin/assignments/bulk-assign.php', {
      method: 'POST',
      body: JSON.stringify({
        assignment_id: parseInt(assignmentId),
        user_ids: Array.from(selectedUserIds)
      })
    });
    
    if (response.ok) {
      alert(`Assigned to ${response.assigned_count} users`);
      selectedUserIds.clear();
      renderUsers();
    } else {
      alert('Error: ' + response.error);
    }
  } catch (err) {
    alert('Bulk assign failed: ' + err.message);
  }
}

// Update user (change team, role, status)
async function updateUser(userId, data) {
  try {
    const response = await requestJson(`../api/admin/users/update.php?id=${userId}`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
    
    if (response.ok) {
      alert('User updated');
      loadUsers();
    } else {
      alert('Error: ' + response.error);
    }
  } catch (err) {
    alert('Update failed: ' + err.message);
  }
}

// ========== EVENT HANDLERS ==========

// Team filter change
$('users-team-filter')?.addEventListener('change', () => {
  loadUsers();
});

// User search
let searchTimeout;
$('users-search')?.addEventListener('input', () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => loadUsers(), 300);
});

// Bulk assign button
$('bulk-assign-btn')?.addEventListener('click', () => {
  bulkAssignUsers();
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
  } else if (action === 'delete-team') {
    await deleteTeam(id);
  }
  // Users
  else if (action === 'edit-user') {
    const user = usersData.find(u => u.id === id);
    if (!user) return;
    
    // Simple prompt-based edit (can be replaced with modal later)
    const teamId = prompt(`Team ID for ${user.email} (current: ${user.team_id || 'none'}):`, user.team_id || '');
    if (teamId === null) return;
    
    await updateUser(id, { 
      team_id: teamId ? parseInt(teamId) : null 
    });
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
    loadUsers();
  });
}
