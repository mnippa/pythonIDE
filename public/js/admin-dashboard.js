const state = {
  assignments: [],
  projects: [],
  tickets: [],
  users: [],
  tasks: [],
  currentAssignmentId: null,
  currentAssignmentTitle: null,
  assignmentsPage: 1,
  assignmentsPerPage: 10,
  assignmentsSortBy: 'id',
  assignmentsSortDir: 'asc',
  assignmentsFilter: '',
  tasksFilterText: '',
  tasksFilterType: 'all',
  projectsFilterText: '',
  projectsFilterUserId: null,
  projectsFilterUserLabel: '',
  projectsFilterTeamId: '',
  projectsFilterSemester: '',
  projectsPage: 1,
  projectsTotalPages: 1,
  projectsTotal: 0,
  projectsLimit: 50,
  projectsLoaded: false,
  ticketsFilter: '',
  ticketsLoaded: false,
  deploySyncBusy: false,
  deploySyncPendingPayload: null
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

function setDeploySyncBusy(isBusy) {
  state.deploySyncBusy = !!isBusy;
  const runBtn = $('deploy-sync-trigger');
  const confirmRunBtn = $('deploy-sync-confirm-run');
  if (runBtn) {
    runBtn.disabled = state.deploySyncBusy;
    runBtn.textContent = state.deploySyncBusy ? 'Laeuft...' : 'Ausfuehren';
  }
  if (confirmRunBtn) {
    confirmRunBtn.disabled = state.deploySyncBusy || ($('deploy-sync-confirm-input')?.value || '').trim() !== 'PROMOTE LIVE';
  }
}

function setDeploySyncStatus(message, type = 'neutral') {
  const el = $('deploy-sync-status');
  if (!el) return;
  el.classList.remove('ok', 'error');
  if (type === 'ok') el.classList.add('ok');
  if (type === 'error') el.classList.add('error');
  el.textContent = message || '';
}

function setDeploySyncOutput(text) {
  const out = $('deploy-sync-output');
  if (!out) return;
  out.textContent = text && String(text).trim() ? String(text) : 'Keine Ausgabe vorhanden.';
}

function getDeploySyncPayloadFromUi() {
  const mode = $('deploy-sync-mode')?.value || 'hydrate-beta';
  const dryRun = !!$('deploy-sync-dry-run')?.checked;
  const syncDb = !!$('deploy-sync-db')?.checked;
  const deleteFlag = !!$('deploy-sync-delete')?.checked;
  return {
    mode,
    dry_run: dryRun,
    sync_db: syncDb,
    delete: deleteFlag
  };
}

function applyDeploySyncModeUi() {
  const mode = $('deploy-sync-mode')?.value || 'hydrate-beta';
  const syncDb = $('deploy-sync-db');
  const deleteFlag = $('deploy-sync-delete');
  const warning = $('deploy-sync-warning');

  if (syncDb) {
    syncDb.disabled = mode !== 'hydrate-beta';
    if (mode !== 'hydrate-beta') syncDb.checked = false;
  }

  if (deleteFlag) {
    deleteFlag.disabled = mode !== 'promote-live';
    if (mode !== 'promote-live') deleteFlag.checked = false;
  }

  if (warning) {
    if (mode === 'promote-live') {
      warning.textContent = 'Achtung: Beta -> Live kann produktiven Code ersetzen. Vorher Dry-Run pruefen. Es folgt eine zusaetzliche Sicherheitsabfrage.';
    } else {
      warning.textContent = 'Hinweis: Live -> Beta aktualisiert die Beta-Umgebung mit persistenten/config Daten aus Live. Optional kann ein DB-Snapshot uebernommen werden.';
    }
  }
}

function openDeploySyncConfirmModal(payload) {
  state.deploySyncPendingPayload = payload;
  const modal = $('deploy-sync-confirm-modal');
  const input = $('deploy-sync-confirm-input');
  const runBtn = $('deploy-sync-confirm-run');
  if (!modal || !input || !runBtn) return;
  input.value = '';
  runBtn.disabled = true;
  modal.classList.add('active');
  input.focus();
}

function closeDeploySyncConfirmModal() {
  state.deploySyncPendingPayload = null;
  const modal = $('deploy-sync-confirm-modal');
  if (modal) {
    modal.classList.remove('active');
  }
}

async function runDeploySync(payload) {
  if (state.deploySyncBusy) return;
  setDeploySyncBusy(true);
  setDeploySyncStatus('Deploy-Sync wird ausgefuehrt...');

  try {
    const result = await requestJson('../api/admin/deploy/sync-live-beta.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    const summary = [
      `Mode: ${result.mode || payload.mode}`,
      `Dry-Run: ${result.dry_run ? 'ja' : 'nein'}`,
      `Exit-Code: ${result.exit_code}`,
      `Ausgefuehrt von: ${(result.executed_by && result.executed_by.email) ? result.executed_by.email : 'unbekannt'}`
    ].join(' | ');

    setDeploySyncStatus(`Erfolgreich: ${summary}`, 'ok');
    setDeploySyncOutput(result.output || 'Ohne Detailausgabe.');
  } catch (err) {
    setDeploySyncStatus(`Fehler: ${err.message || 'Deploy-Sync fehlgeschlagen'}`, 'error');
    setDeploySyncOutput(err.message || 'Keine zusaetzliche Fehlerausgabe.');
  } finally {
    setDeploySyncBusy(false);
  }
}

function bindDeploySyncEvents() {
  const modeSelect = $('deploy-sync-mode');
  const runBtn = $('deploy-sync-trigger');
  const confirmModal = $('deploy-sync-confirm-modal');
  const confirmInput = $('deploy-sync-confirm-input');
  const confirmRunBtn = $('deploy-sync-confirm-run');
  const confirmCancelBtn = $('deploy-sync-confirm-cancel');
  const confirmCloseBtn = $('deploy-sync-confirm-close');

  if (!modeSelect || !runBtn) return;

  applyDeploySyncModeUi();

  modeSelect.addEventListener('change', applyDeploySyncModeUi);

  runBtn.addEventListener('click', async () => {
    const payload = getDeploySyncPayloadFromUi();

    if (payload.mode === 'promote-live') {
      openDeploySyncConfirmModal(payload);
      return;
    }

    await runDeploySync(payload);
  });

  if (confirmInput && confirmRunBtn) {
    confirmInput.addEventListener('input', () => {
      confirmRunBtn.disabled = state.deploySyncBusy || confirmInput.value.trim() !== 'PROMOTE LIVE';
    });
  }

  if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', closeDeploySyncConfirmModal);
  }

  if (confirmCloseBtn) {
    confirmCloseBtn.addEventListener('click', closeDeploySyncConfirmModal);
  }

  if (confirmRunBtn) {
    confirmRunBtn.addEventListener('click', async () => {
      const payload = state.deploySyncPendingPayload;
      closeDeploySyncConfirmModal();
      if (!payload) return;
      await runDeploySync(payload);
    });
  }

  if (confirmModal) {
    confirmModal.addEventListener('click', (e) => {
      if (e.target === confirmModal) {
        closeDeploySyncConfirmModal();
      }
    });
  }
}

function toDatetimeLocalValue(dateTimeStr) {
  if (!dateTimeStr) return '';
  if (dateTimeStr instanceof Date) {
    const d = dateTimeStr;
    if (Number.isNaN(d.getTime())) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
  }

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

  const d = new Date(normalized.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
}

function fromDatetimeLocalValue(value) {
  if (!value) return null;
  const normalized = String(value).trim();
  const match = normalized.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (match) {
    const [, datePart, hh, mm, ss] = match;
    return `${datePart} ${hh}:${mm}:${ss || '00'}`;
  }

  const d = new Date(normalized);
  if (Number.isNaN(d.getTime())) return null;
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
}

function formatCompactDate(dateTimeStr) {
  if (!dateTimeStr) return '-';
  const d = new Date(dateTimeStr.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '-';
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yy = d.getFullYear();
  return `${dd}.${mm}.${yy}`;
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

  if (tab === 'tickets' && !state.ticketsLoaded) {
    loadTickets().catch((err) => {
      console.error('Failed to load tickets:', err);
    });
  }

  const url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  window.history.replaceState({}, '', url.toString());
}

function formatRelativeTicketTime(dateTimeStr) {
  if (!dateTimeStr) return '-';
  const date = new Date(String(dateTimeStr).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '-';

  const now = new Date();
  const diffSeconds = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 1000));
  if (diffSeconds < 60) return 'gerade eben';
  if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)}m`;
  if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)}h`;
  return `${formatCompactDate(dateTimeStr)} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function getFilteredTickets() {
  const query = (state.ticketsFilter || '').trim().toLowerCase();
  if (!query) return [...state.tickets];

  return state.tickets.filter((ticket) => [
    ticket.user_name,
    ticket.user_email,
    ticket.assignment_title,
    ticket.token,
    ticket.description
  ].some((value) => String(value || '').toLowerCase().includes(query)));
}

function renderTickets() {
  const body = $('tickets-body');
  const statusEl = $('tickets-status');
  if (!body) return;

  const tickets = getFilteredTickets();
  if (statusEl) {
    statusEl.textContent = tickets.length === state.tickets.length
      ? `${state.tickets.length} offene Tickets.`
      : `${tickets.length} von ${state.tickets.length} Tickets sichtbar.`;
  }

  if (!tickets.length) {
    body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Keine offenen Tickets gefunden.</td></tr>';
    return;
  }

  body.innerHTML = tickets.map((ticket) => `
    <tr data-ticket-id="${ticket.id}">
      <td>${escapeHtml(formatRelativeTicketTime(ticket.created_at))}</td>
      <td>${escapeHtml(ticket.user_name || '-')}</td>
      <td>${escapeHtml(ticket.user_email || '-')}</td>
      <td>${escapeHtml(ticket.assignment_title || '-')}</td>
      <td class="ticket-note" title="${escapeHtml(ticket.description || '')}">${escapeHtml(ticket.description || '-')}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn ticket-open-btn" type="button" data-action="open-ticket" data-user-id="${ticket.user_id}" data-assignment-id="${ticket.assignment_id}" title="Testview öffnen">↗</button>
          <button class="icon-btn danger" type="button" data-action="delete-ticket" data-ticket-id="${ticket.id}" title="Ticket löschen">🗑</button>
        </div>
      </td>
    </tr>
  `).join('');
}

async function loadTickets() {
  const body = $('tickets-body');
  const statusEl = $('tickets-status');
  if (body) {
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--hspf-text-secondary);">Tickets werden geladen...</td></tr>';
  }
  if (statusEl) {
    statusEl.textContent = 'Tickets werden geladen...';
  }

  const data = await requestJson('../api/support_tickets/list.php');
  state.tickets = Array.isArray(data.tickets) ? data.tickets : [];
  state.ticketsLoaded = true;
  renderTickets();
}

function openTicketTestView(userId, assignmentId) {
  if (!userId || !assignmentId) return;
  window.open(`editor_assignment_user_test.php?assignment_id=${encodeURIComponent(assignmentId)}&test_user_id=${encodeURIComponent(userId)}`, '_blank');
}

async function deleteAllTickets() {
  const count = state.tickets ? state.tickets.length : 0;
  if (!window.confirm(`Alle ${count} Ticket(s) wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`)) return;

  const statusEl = $('tickets-status');
  if (statusEl) statusEl.textContent = 'Lösche alle Tickets...';

  const formData = new FormData();
  formData.append('all', '1');
  try {
    const response = await fetch('../api/support_tickets/delete.php', {
      method: 'POST',
      body: formData,
      credentials: 'include'
    });
    const raw = await response.text();
    let data = null;
    try { data = raw ? JSON.parse(raw) : null; } catch(e) { data = null; }
    if (!response.ok || !data || data.ok === false) {
      alert((data && data.error) ? data.error : 'Fehler beim Löschen aller Tickets');
    }
    await loadTickets();
  } catch (err) {
    alert('Fehler: ' + err.message);
  }
}

async function deleteTicket(ticketId) {
  if (!ticketId) return;

  const formData = new FormData();
  formData.append('ticket_id', String(ticketId));

  const response = await fetch('../api/support_tickets/delete.php', {
    method: 'POST',
    body: formData,
    credentials: 'include'
  });
  const raw = await response.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    data = null;
  }

  if (!response.ok || !data || data.ok === false) {
    throw new Error((data && data.error) ? data.error : 'Ticket konnte nicht gelöscht werden');
  }

  state.tickets = state.tickets.filter((ticket) => ticket.id !== ticketId);
  renderTickets();
}

function setActiveTaskTab(form, tabName) {
  if (!form) return;
  const buttons = form.querySelectorAll('.task-tab');
  const panels = form.querySelectorAll('.task-tab-panel');
  if (!buttons.length || !panels.length) return;
  const target = tabName || buttons[0].dataset.taskTab;
  buttons.forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.taskTab === target);
  });
  panels.forEach((panel) => {
    panel.classList.toggle('active', panel.dataset.taskTabPanel === target);
  });
  form.dataset.activeTaskTab = target;
}

function initTaskFormTabs(formId) {
  const form = document.getElementById(formId);
  if (!form) return;
  const buttons = form.querySelectorAll('.task-tab');
  if (!buttons.length) return;
  buttons.forEach((btn) => {
    btn.addEventListener('click', () => setActiveTaskTab(form, btn.dataset.taskTab));
  });
  setActiveTaskTab(form, form.dataset.activeTaskTab || buttons[0].dataset.taskTab);
}

async function loadProjects() {
  const statusEl = $('projects-status');
  const body = $('projects-body');
  const query = (state.projectsFilterText || '').trim();
  const userId = state.projectsFilterUserId;
  const teamId = state.projectsFilterTeamId;
  const semester = state.projectsFilterSemester;
  const limit = Math.min(Math.max(parseInt(state.projectsLimit, 10) || 50, 1), 100);

  if (statusEl) {
    if (userId && state.projectsFilterUserLabel) {
      statusEl.textContent = `Lade Projekte von ${state.projectsFilterUserLabel}...`;
    } else {
      statusEl.textContent = 'Lade Projekte...';
    }
  }
  if (body) {
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--text-secondary);">Lade Projekte...</td></tr>';
  }

  const params = new URLSearchParams();
  if (query) params.set('search', query);
  if (userId) params.set('user_id', String(userId));
  if (teamId) params.set('team_id', String(teamId));
  if (semester) params.set('semester', semester);
  params.set('page', String(state.projectsPage || 1));
  params.set('limit', String(limit));

  const data = await requestJson(`../api/admin/projects/list.php?${params.toString()}`);
  state.projects = data.projects || [];
  state.projectsPage = data.page || 1;
  state.projectsTotalPages = data.total_pages || 1;
  state.projectsTotal = data.total || 0;
  state.projectsLoaded = true;

  body.innerHTML = '';

  if (data.requires_search) {
    if (statusEl) {
      statusEl.textContent = 'Bitte zuerst nach Projekten suchen oder einen Nutzer aus der Userliste auswählen.';
    }
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--text-secondary);">Noch keine Suche ausgeführt.</td></tr>';
    updateProjectsPagination();
    return;
  }

  if (statusEl) {
    const scopeLabel = userId && state.projectsFilterUserLabel
      ? ` für ${state.projectsFilterUserLabel}`
      : '';
    const filters = [
      query ? `Suche: "${query}"` : '',
      teamId ? `Team ${teamId}` : '',
      semester ? `Semester ${semester}` : ''
    ].filter(Boolean).join(' · ');
    statusEl.textContent = `${state.projects.length} von ${state.projectsTotal} Projekt(en) geladen${scopeLabel}${filters ? ` – ${filters}` : ''}`;
  }

  if (!state.projects.length) {
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--text-secondary);">Keine Projekte gefunden.</td></tr>';
    updateProjectsPagination();
    return;
  }

  state.projects.forEach((p) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="mono">${p.id}</td>
      <td>${escapeHtml(p.name)}</td>
      <td>${escapeHtml(p.description || '')}</td>
      <td>
        <div>${escapeHtml(p.user_email)}</div>
        <div style="font-size:12px;color:var(--hspf-text-secondary);">${escapeHtml([p.team_name || '-', p.semester || '-'].join(' · '))}</div>
      </td>
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

  updateProjectsPagination();
}

function updateProjectsPagination() {
  const totalPages = Math.max(1, state.projectsTotalPages || 1);
  if ($('projects-page-info')) {
    $('projects-page-info').textContent = `Page ${state.projectsPage} of ${totalPages}`;
  }
  if ($('projects-prev')) {
    $('projects-prev').disabled = state.projectsPage <= 1;
  }
  if ($('projects-next')) {
    $('projects-next').disabled = state.projectsPage >= totalPages;
  }
}

function resetProjectFilters() {
  state.projectsFilterText = '';
  state.projectsFilterUserId = null;
  state.projectsFilterUserLabel = '';
  state.projectsFilterTeamId = '';
  state.projectsFilterSemester = '';
  state.projectsPage = 1;
  state.projectsTotalPages = 1;
  state.projectsTotal = 0;
  state.projectsLimit = 50;
  state.projectsLoaded = false;
  if ($('projects-search')) $('projects-search').value = '';
  if ($('projects-team-filter')) $('projects-team-filter').value = '';
  if ($('projects-semester-filter')) $('projects-semester-filter').value = '';
  if ($('projects-limit')) $('projects-limit').value = '50';
  if ($('projects-status')) $('projects-status').textContent = 'Noch keine Suche ausgeführt.';
  if ($('projects-body')) {
    $('projects-body').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:16px;color:var(--text-secondary);">Bitte Suche starten oder Nutzer-Projekte öffnen.</td></tr>';
  }
  updateProjectsPagination();
}

async function searchProjects(options = {}) {
  if (typeof options.text === 'string') {
    state.projectsFilterText = options.text.trim();
    if ($('projects-search')) $('projects-search').value = state.projectsFilterText;
  }
  if (Object.prototype.hasOwnProperty.call(options, 'userId')) {
    state.projectsFilterUserId = options.userId || null;
  }
  if (Object.prototype.hasOwnProperty.call(options, 'userLabel')) {
    state.projectsFilterUserLabel = options.userLabel || '';
  }
  if (Object.prototype.hasOwnProperty.call(options, 'teamId')) {
    state.projectsFilterTeamId = options.teamId || '';
    if ($('projects-team-filter')) $('projects-team-filter').value = state.projectsFilterTeamId;
  }
  if (Object.prototype.hasOwnProperty.call(options, 'semester')) {
    state.projectsFilterSemester = options.semester || '';
    if ($('projects-semester-filter')) $('projects-semester-filter').value = state.projectsFilterSemester;
  }
  if (Object.prototype.hasOwnProperty.call(options, 'page')) {
    state.projectsPage = Math.max(1, options.page || 1);
  }
  if (Object.prototype.hasOwnProperty.call(options, 'limit')) {
    state.projectsLimit = options.limit || 50;
    if ($('projects-limit')) $('projects-limit').value = String(state.projectsLimit);
  }
  await loadProjects();
}

window.openAdminProjectsForUser = async function openAdminProjectsForUser(userId, userLabel = '') {
  state.projectsFilterUserId = userId;
  state.projectsFilterUserLabel = userLabel;
  state.projectsFilterText = '';
  state.projectsFilterTeamId = '';
  state.projectsFilterSemester = '';
  state.projectsPage = 1;
  if ($('projects-search')) $('projects-search').value = '';
  if ($('projects-team-filter')) $('projects-team-filter').value = '';
  if ($('projects-semester-filter')) $('projects-semester-filter').value = '';
  setActiveTab('projects');
  await loadProjects();
};

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
      <td>
        <div>${escapeHtml(a.created_by_name || a.created_by_email || '-')}</div>
        <div style="font-size:12px;color:var(--hspf-text-secondary);">${escapeHtml(a.created_by_email || '')}</div>
      </td>
      <td>${escapeHtml(a.difficulty)}</td>
      <td>${a.is_active ? '✓' : '✗'}</td>
      <td>${formatCompactDate(a.available_from)}</td>
      <td>${formatCompactDate(a.due_date)}</td>
      <td>${formatCompactDate(a.hard_deadline)}</td>
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
          <button class="icon-btn" data-action="test-assignment" data-id="${a.id}" title="Test Assignment">🧪</button>
          <button class="icon-btn" data-action="edit-assignment" data-id="${a.id}" title="Edit">✏️</button>
          <button class="icon-btn" data-action="clone-assignment" data-id="${a.id}" title="Clone">🗐</button>
          <button class="icon-btn warn" data-action="reset-assignment-attempts" data-id="${a.id}" title="Reset all attempts">↺</button>
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

  const filterText = (state.tasksFilterText || '').toLowerCase();
  const filterType = state.tasksFilterType || 'all';
  const filteredTasks = state.tasks.filter((t) => {
    const matchesText = !filterText || String(t.title || '').toLowerCase().includes(filterText);
    const matchesType = filterType === 'all' || t.task_type === filterType;
    return matchesText && matchesType;
  });

  filteredTasks.forEach((t) => {
    const hasTests = t.test_cases ? '✓' : '✗';
    const hasSolution = t.solution_code ? '✓' : '✗';
    const fullIndex = state.tasks.findIndex((task) => task.id === t.id);
    const isFirst = fullIndex === 0;
    const isLast = fullIndex === state.tasks.length - 1;
    const taskTypeLabel = t.task_type || 'code';
    const difficulty = getTaskDifficultyMeta(t.task_difficulty);
    const isQuizType = !['code', 'code_ui'].includes(taskTypeLabel);

    // Testtypen-Icons
    const testTypeIcons = {
      'output': '🖨️',
      'function': 'ƒ',
      'variable': '𝑥',
      'intelligent': '🧠',
      'code_check': '🔑'
    };
    let testTypes = [];
    try {
      if (t.test_cases) {
        let parsed = JSON.parse(t.test_cases);
        if (!Array.isArray(parsed)) {
          // Single intelligent test as object (has mode field instead of type)
          if (parsed.mode) {
            testTypes = ['intelligent'];
          } else {
            parsed = [parsed];
          }
        }
        if (Array.isArray(parsed)) {
          testTypes = parsed.map(tc => tc.type).filter(Boolean);
        }
      }
    } catch {}
    const testTypeIconHtml = testTypes.map(type => testTypeIcons[type] || '').join(' ');

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
      <td><span class="tag ${isQuizType ? 'quiz' : ''}">${escapeHtml(taskTypeLabel)}</span></td>
      <td><span class="tag" title="${difficulty.level}">${difficulty.stars} ${difficulty.level}</span></td>
      <td>${hasTests}</td>
      <td>${hasSolution}</td>
      <td><span class="tag">${testTypeIconHtml}</span></td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" data-action="view-task" data-id="${t.id}" title="View Task">👁️</button>
          <button class="icon-btn" data-action="edit-task" data-id="${t.id}" title="Edit">✏️</button>
          <button class="icon-btn" data-action="clone-task" data-id="${t.id}" title="Clone">🗐</button>
          <button class="icon-btn danger" data-action="delete-task" data-id="${t.id}" title="Delete">🗑️</button>
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
          <button class="btn" data-action="open-user-projects" data-id="${u.id}" data-name="${escapeHtml(u.email)}">Meine Projekte</button>
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
  const now = new Date();
  const due = new Date(now.getTime() + 14 * 24 * 60 * 60 * 1000);
  const hard = new Date(now.getTime() + 17 * 24 * 60 * 60 * 1000);
  $('assignment-available-from').value = toDatetimeLocalValue(now);
  $('assignment-due-date').value = toDatetimeLocalValue(due);
  $('assignment-hard-deadline').value = toDatetimeLocalValue(hard);
  $('assignment-allow-late').value = 'true';
  const titleEl = $('assignment-modal-title');
  if (titleEl) titleEl.textContent = 'New Assignment';
}

function resetTaskForm() {
  // Re-enable fields first
  if ($('new-task-type')) $('new-task-type').disabled = false;
  if ($('task-title')) $('task-title').disabled = false;
  
  $('task-title').value = '';
  if ($('task-text')) $('task-text').value = '';
  if ($('task-description')) {
    const descEditor = tinymce.get('task-description');
    if (descEditor) {
      descEditor.setContent('');
    } else {
      $('task-description').value = '';
    }
  }
  if ($('task-template')) $('task-template').value = '';
  if ($('task-randomizer-code')) $('task-randomizer-code').value = '';
  if ($('task-hint1')) $('task-hint1').value = '';
  if ($('task-hint2')) $('task-hint2').value = '';
  if ($('task-hint3')) $('task-hint3').value = '';
  if ($('task-stoff')) {
    const stoffEditor = tinymce.get('task-stoff');
    if (stoffEditor) {
      stoffEditor.setContent('');
    } else {
      $('task-stoff').value = '';
    }
  }
  if ($('task-validation-mode')) $('task-validation-mode').value = '';
  if ($('task-test-cases')) $('task-test-cases').value = '';
  if ($('task-solution')) $('task-solution').value = '';
  if ($('task-max-attempts')) $('task-max-attempts').value = '1';
  if ($('task-max-iterations')) $('task-max-iterations').value = '3';
  
  // NEW: Reset quiz fields
  if ($('new-task-type')) $('new-task-type').value = 'code';
  if ($('task-difficulty')) $('task-difficulty').value = 'medium';
  if ($('task-image-url')) $('task-image-url').value = '';
  if ($('task-image-preview')) $('task-image-preview').innerHTML = '';
  if ($('task-image-upload')) $('task-image-upload').value = '';
  if ($('task-keywords')) $('task-keywords').value = '';
  if ($('task-correct-answer')) $('task-correct-answer').value = '';
  if ($('task-var-overrides')) $('task-var-overrides').value = '';
  if (overridesBuilders.task) {
    overridesBuilders.task.iterations = [{ vars: [{ key: '', value: '' }] }];
    renderOverridesBuilder('task');
    syncOverridesJson('task');
    updateMaxIterationsFromBuilder('task');
  }
  
  // Reset options builder
  if (window.currentOptionsBuilder) {
    window.currentOptionsBuilder.setOptions([]);
  }
  
  // Reset test cases
  testCasesData = [];
  renderTestCases(testCasesData, 'tests-container');
  updateSolutionCodeVisibility(); // Update solution code visibility
  updateCodeOnlyOptionsVisibility();
  
  // Reset field visibility
  const taskForm = document.getElementById('task-form');
  if (window.TaskTypeManager && taskForm) {
    TaskTypeManager.updateFieldVisibility(taskForm, 'code');
  }

  // Only set active tab if tabs exist in the form
  if (taskForm && taskForm.querySelectorAll('.task-tab').length > 0) {
    setActiveTaskTab(taskForm, 'base');
  }
}

function getTaskDifficultyMeta(levelRaw) {
  const level = (levelRaw || 'medium').toString().toLowerCase();
  const validLevel = ['basic', 'medium', 'hard'].includes(level) ? level : 'medium';
  const filled = validLevel === 'basic' ? 1 : (validLevel === 'hard' ? 3 : 2);
  return {
    level: validLevel,
    filled,
    stars: '★'.repeat(filled) + '☆'.repeat(3 - filled)
  };
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
  // Open pre-task dialog instead of directly opening task form
  $('pre-task-modal').classList.add('active');
}

function openPreTaskDialog() {
  $('pre-task-modal').classList.add('active');
  $('pre-task-error').style.display = 'none';
  $('pre-task-type').value = 'code'; // Default
  $('pre-task-title').value = '';
  // Focus on title input for quick entry
  setTimeout(() => $('pre-task-title').focus(), 100);
}

function closePreTaskDialog() {
  $('pre-task-modal').classList.remove('active');
}

function continueFromPreTaskDialog() {
  const taskType = $('pre-task-type').value.trim();
  const taskTitle = $('pre-task-title').value.trim();
  const errorEl = $('pre-task-error');
  
  if (!taskType) {
    errorEl.textContent = 'Bitte wählen Sie einen Task-Typ aus';
    errorEl.style.display = 'block';
    return;
  }
  
  if (!taskTitle) {
    errorEl.textContent = 'Bitte geben Sie einen Task-Titel ein';
    errorEl.style.display = 'block';
    return;
  }
  
  // Close pre-dialog and open full form
  closePreTaskDialog();
  
  // Reset and prepare task form
  resetTaskForm();
  
  // Set values from pre-dialog (these will be disabled after)
  $('new-task-type').value = taskType;
  $('task-title').value = taskTitle;

  // Keep options builder in sync even when value is set programmatically.
  if (window.currentOptionsBuilder) {
    window.currentOptionsBuilder.setTaskType(taskType);
  }
  
  // Disable type and title fields
  $('new-task-type').disabled = true;
  $('task-title').disabled = true;
  
  // Update form field visibility based on type
  const taskForm = $('task-form');
  if (taskForm && taskForm.querySelectorAll('.task-tab').length > 0) {
    setActiveTaskTab(taskForm, 'base');
  }
  
  // Update field visibility based on selected task type
  if (window.TaskTypeManager && taskForm) {
    window.TaskTypeManager.updateFieldVisibility(taskForm, taskType);
  }
  updateTestTypeVisibility();
  updateMaxIterationsFromBuilder('task');
  updateRandomButtonVisibility();
  updateCodeOnlyOptionsVisibility();
  updateTestTypeVisibility();
  
  // Show task form
  $('task-create-modal').classList.add('active');
}

function closeNewTaskModal() {
  $('task-create-modal').classList.remove('active');
  // Re-enable type and title fields when closing (for next task)
  $('new-task-type').disabled = false;
  $('task-title').disabled = false;
}

function confirmCloseNewTaskModal() {
  if (confirm('Sind Sie sicher, dass Sie das Modal schließen möchten? Nicht gespeicherte Änderungen werden verworfen.')) {
    closeNewTaskModal();
  }
}

async function handleAssignmentSubmit(e) {
  e.preventDefault();

  const id = $('assignment-id').value.trim();
  const payload = {
    title: $('assignment-title').value.trim(),
    description: $('assignment-description').value.trim(),
    difficulty: $('assignment-difficulty').value,
    is_active: $('assignment-active').value === 'true',
    available_from: fromDatetimeLocalValue($('assignment-available-from').value),
    due_date: fromDatetimeLocalValue($('assignment-due-date').value),
    hard_deadline: fromDatetimeLocalValue($('assignment-hard-deadline').value),
    allow_late_submission: $('assignment-allow-late').value === 'true'
  };

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  if (id) {
    const assignmentId = parseInt(id, 10);
    const existingAssignment = state.assignments.find((x) => x.id === assignmentId);

    // Optional: when due date changes centrally, ask whether linked user due dates should be synced.
    if (existingAssignment && payload.due_date !== existingAssignment.due_date) {
      const shouldPropagate = confirm(
        'Die zentrale Due Date wurde geändert.\n\n' +
        'Sollen bestehende User-Assignment Due Dates ebenfalls aktualisiert werden?\n\n' +
        'Hinweis: Es werden nur Einträge mitgezogen, die bisher exakt der alten zentralen Due Date entsprachen.'
      );
      payload.propagate_due_date_to_user_assignments = shouldPropagate;
      payload.propagate_matching_only = true;
    }

    payload.id = assignmentId;
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
  
  // Get which button was clicked
  const submitter = e.submitter;
  const actionValue = submitter?.getAttribute('value') || 'save';
  const shouldClose = actionValue === 'save-close';
  
  if (!state.currentAssignmentId) {
    alert('Select an assignment first');
    return;
  }

  const taskType = $('new-task-type').value;
  
  const payload = {
    assignment_id: state.currentAssignmentId,
    title: $('task-title').value.trim(),
    max_attempts: $('task-max-attempts').value ? parseInt($('task-max-attempts').value, 10) : 1,
    show_solution: $('task-show-solution').checked ? 1 : 0,
    show_solution_code: $('task-show-solution-code').checked ? 1 : 0,
    folderstructure: $('task-folderstructure').checked ? 1 : 0,
    allowDownload: $('task-allowDownload').checked ? 1 : 0,
    allowCodeUiWebEdit: $('task-allowCodeUiWebEdit').checked ? 1 : 0,
    problem_type: $('task-type').value,
    task_type: taskType, // NEW: Task type (code, single_choice, etc.)
    task_difficulty: ($('task-difficulty')?.value || 'medium'),
    image_url: $('task-image-url') ? ($('task-image-url').value.trim() || null) : null,
    code_template: $('task-template').value,
    randomizer_code: $('task-randomizer-code').value.trim() || null,
    hint1: $('task-hint1').value,
    hint2: $('task-hint2').value,
    hint3: $('task-hint3').value,
    test_cases: $('task-test-cases').value.trim() || null,
    solution_code: $('task-solution').value.trim() || null
  };
  
  // Get stoff from TinyMCE if available, else from textarea
  const stoffEditor = tinymce.get('task-stoff');
  if (stoffEditor) {
    payload.stoff = stoffEditor.getContent().trim();
  } else {
    payload.stoff = $('task-stoff').value;
  }
  
  // For all task types: use task-text (unified field)
  payload.task_text = $('task-text').value.trim();
  
  // Get description from TinyMCE if available, else from textarea
  const descriptionEditor = tinymce.get('task-description');
  if (descriptionEditor) {
    payload.description = descriptionEditor.getContent().trim();
  } else {
    payload.description = $('task-description').value.trim();
  }
  
  // NEW: Add options for single/multiple choice
  if (taskType === 'single_choice' || taskType === 'multiple_choice') {
    if (window.currentOptionsBuilder) {
      payload.options = window.currentOptionsBuilder.getOptions();
      const validationError = validateChoiceOptions(taskType, payload.options);
      if (validationError) {
        setChoiceValidationError('task-options-error', validationError);
        return;
      }
      setChoiceValidationError('task-options-error', '');
    }
  }
  
  // NEW: Handle free text validation options
  // Handle test_cases for code and free_text tasks
  if (taskType === 'code' || taskType === 'code_ui' || taskType === 'free_text') {
    payload.test_cases = $('task-test-cases').value.trim() || null;
  }
  
  // NEW: Add fields for code reading
  if (taskType === 'code_reading' || taskType === 'code_random_complex') {
    payload.correct_answer = $('task-correct-answer').value.trim();
  }

  if (taskType === 'code_reading') {
    const overridesPayload = getOverridesPayload('task');
    if (overridesPayload === null && $('task-var-overrides').value.trim() !== '') {
      return;
    }
    if (overridesPayload) {
      payload.variable_overrides = overridesPayload;
    }
  }

  if (taskType === 'code_random_complex') {
    const overridesPayload = getOverridesPayload('task');
    if (overridesPayload === null && $('task-var-overrides')?.value.trim() !== '') {
      return;
    }
    if (!hasRandomMarkerInOverrides(overridesPayload)) {
      alert('code_random_complex benoetigt variable_overrides mit mindestens einem <random>-Marker in inputs.');
      return;
    }
    const randomizerValue = ($('task-randomizer-code')?.value || '').trim();
    if (!randomizerValue) {
      alert('code_random_complex benoetigt Randomizer-Code.');
      return;
    }
    const templateValue = ($('task-template')?.value || '').trim();
    const hasValuesDict = /\bvalues\b/.test(templateValue);
    const hasPlaceholders = /\{[a-zA-Z_][a-zA-Z0-9_]*\}/.test(templateValue);
    if (!templateValue || (!hasValuesDict && !hasPlaceholders)) {
      alert('code_random_complex benoetigt Code-Template mit values-Dict oder {placeholder}-Syntax.');
      return;
    }
    payload.variable_overrides = overridesPayload;
  }

  if (taskType === 'code_reading') {
    const overridesPayload = getOverridesPayload('task');
    const overridesArray = Array.isArray(overridesPayload)
      ? overridesPayload
      : (overridesPayload ? [overridesPayload] : buildOverridesArray('task'));
    payload.max_iterations = Math.max(1, overridesArray.length || 1);
  } else if (taskType === 'code_random_complex') {
    payload.max_iterations = $('task-max-iterations').value
      ? parseInt($('task-max-iterations').value, 10)
      : 3;
  }

  // If builder has data, prefer it over manual JSON (for code tasks)
  if ((taskType === 'code' || taskType === 'code_ui') && Array.isArray(testCasesData) && testCasesData.length > 0) {
    // Special case: if single intelligent test, save as object (not array)
    if (testCasesData.length === 1 && testCasesData[0].type === 'intelligent') {
      const intelligentConfig = {...testCasesData[0]};
      delete intelligentConfig.type; // Remove 'type' key when serializing as object
      payload.test_cases = JSON.stringify(intelligentConfig);
    } else {
      payload.test_cases = JSON.stringify(testCasesData);
    }
    $('task-test-cases').value = payload.test_cases;
  }

  if (!payload.title) {
    alert('Title is required');
    return;
  }

  // Validate test_cases JSON if provided (for code tasks)
  if ((taskType === 'code' || taskType === 'code_ui') && payload.test_cases) {
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

  setChoiceValidationError('task-options-error', '');
  resetTaskForm();

  await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
  await loadAssignments();
  
  // Only close if user chose "save-close"
  if (shouldClose) {
    closeNewTaskModal();
  }
}

function validateChoiceOptions(taskType, options) {
  if (!Array.isArray(options) || options.length < 2) {
    return 'Bitte mindestens zwei Antwortoptionen hinzufügen';
  }

  const normalizedTexts = options
    .map(opt => (opt.text || '').trim())
    .filter(text => text !== '')
    .map(text => text.toLowerCase());

  if (normalizedTexts.length === 0) {
    return 'Bitte mindestens einen Antworttext angeben';
  }

  const duplicates = normalizedTexts.filter((text, idx) => normalizedTexts.indexOf(text) !== idx);
  if (duplicates.length > 0) {
    return 'Antworttexte duplizieren sich. Bitte eindeutige Texte verwenden.';
  }

  const correctCount = options.filter(opt => opt.is_correct).length;

  if (taskType === 'single_choice' && correctCount !== 1) {
    return 'Single-Choice: Bitte genau eine richtige Antwort markieren';
  }

  return null;
}

const overridesBuilders = {};

function parseOverrideValue(rawValue) {
  const trimmed = String(rawValue ?? '').trim();
  if (trimmed === '') return '';
  if (trimmed === 'true') return true;
  if (trimmed === 'false') return false;
  if (trimmed === 'null') return null;
  if (!Number.isNaN(Number(trimmed))) {
    return trimmed.includes('.') ? parseFloat(trimmed) : parseInt(trimmed, 10);
  }
  if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
    try {
      return JSON.parse(trimmed);
    } catch (err) {
      return trimmed;
    }
  }
  return trimmed;
}

function buildOverridesArray(prefix) {
  const builderState = overridesBuilders[prefix];
  if (!builderState) return [];
  return builderState.iterations.map(iter => {
    // New schema: each iteration has inputs (dict) + expected (variable or value)
    const inputs = {};
    iter.vars.forEach(v => {
      const key = String(v.key ?? '').trim();
      if (!key) return;
      const rawValue = String(v.value ?? '').trim();
      // Keep <random> markers as literal strings for CODE_RANDOM_COMPLEX
      if (rawValue === '<random>') {
        inputs[key] = '<random>';
      } else {
        inputs[key] = parseOverrideValue(rawValue);
      }
    });

    // Build expected object based on expectedType
    const expected = {};
    const expectedType = iter.expectedType ?? 'variable'; // default to variable mode
    if (expectedType === 'variable') {
      const varName = (iter.expectedVariableName ?? '').trim();
      if (varName) {
        expected.variable = varName;
      }
    } else if (expectedType === 'value') {
      const rawValue = (iter.expectedValue ?? '').trim();
      if (rawValue) {
        expected.value = parseOverrideValue(rawValue);
      }
    }

    return {
      inputs: inputs,
      expected: Object.keys(expected).length > 0 ? expected : {}
    };
  });
}

function syncOverridesJson(prefix) {
  const textarea = $(`${prefix}-var-overrides`);
  if (!textarea) return;
  const overridesArray = buildOverridesArray(prefix);
  textarea.value = overridesArray.length ? JSON.stringify(overridesArray, null, 2) : '';
}

function getOverridesPayload(prefix) {
  const textarea = $(`${prefix}-var-overrides`);
  if (!textarea) return null;
  const raw = textarea.value.trim();
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (err) {
    alert('Variable Overrides: Ungültiges JSON-Format');
    return null;
  }
}

function hasRandomMarkerInOverrides(overridesPayload) {
  if (!Array.isArray(overridesPayload) || overridesPayload.length === 0) return false;
  return overridesPayload.some(entry => {
    if (!entry || typeof entry !== 'object' || !entry.inputs || typeof entry.inputs !== 'object') {
      return false;
    }
    return Object.values(entry.inputs).some(v => v === '<random>');
  });
}

function setOverridesFromJson(prefix, rawJson) {
  let parsed = null;
  if (!rawJson) {
    overridesBuilders[prefix].iterations = [];
    renderOverridesBuilder(prefix);
    syncOverridesJson(prefix);
    return;
  }

  try {
    parsed = JSON.parse(rawJson);
  } catch (err) {
    alert('Variable Overrides: Ungültiges JSON-Format');
    return;
  }

  const normalized = Array.isArray(parsed) ? parsed : [parsed];
  overridesBuilders[prefix].iterations = normalized.map(item => {
    const vars = [];
    let expectedType = 'variable';
    let expectedVariableName = '';
    let expectedValue = '';

    if (item && typeof item === 'object') {
      // NEW SCHEMA: {inputs: {...}, expected: {variable: "x"} OR {value: 42}}
      if (item.inputs && typeof item.inputs === 'object') {
        Object.keys(item.inputs).forEach(key => {
          const rawValue = item.inputs[key];
          const displayValue = (rawValue && typeof rawValue === 'object') ? JSON.stringify(rawValue) : rawValue;
          vars.push({ key, value: displayValue });
        });

        // Parse expected field
        if (item.expected && typeof item.expected === 'object') {
          if (item.expected.variable) {
            expectedType = 'variable';
            expectedVariableName = item.expected.variable;
          } else if (item.expected.hasOwnProperty('value')) {
            expectedType = 'value';
            const rawValue = item.expected.value;
            expectedValue = (rawValue && typeof rawValue === 'object') ? JSON.stringify(rawValue) : String(rawValue);
          }
        }
      } else {
        // LEGACY SCHEMA: {A: true, B: false, ...} - convert to new schema
        Object.keys(item).forEach(key => {
          const rawValue = item[key];
          const displayValue = (rawValue && typeof rawValue === 'object') ? JSON.stringify(rawValue) : rawValue;
          vars.push({ key, value: displayValue });
        });
        expectedType = 'variable'; // Default to auto-variable mode
      }
    }

    return {
      vars: vars.length ? vars : [{ key: '', value: '' }],
      expectedType: expectedType,
      expectedVariableName: expectedVariableName,
      expectedValue: expectedValue
    };
  });

  renderOverridesBuilder(prefix);
  syncOverridesJson(prefix);
  updateMaxIterationsFromBuilder(prefix);
}

function renderOverridesBuilder(prefix) {
  const builder = $(`${prefix}-var-overrides-builder`);
  const builderState = overridesBuilders[prefix];
  if (!builder || !builderState) return;

  builder.innerHTML = builderState.iterations.map((iter, iterIdx) => {
    // Input variables section
    const rows = iter.vars.map((v, varIdx) => `
      <div class="override-row">
        <input type="text" placeholder="Variable" data-iter-idx="${iterIdx}" data-var-idx="${varIdx}" data-override-field="key" value="${escapeHtml(v.key)}" />
        <input type="text" placeholder="Wert" data-iter-idx="${iterIdx}" data-var-idx="${varIdx}" data-override-field="value" value="${escapeHtml(v.value)}" />
        <button type="button" class="hspf-btn" data-action="remove-var" data-iter-idx="${iterIdx}" data-var-idx="${varIdx}">✕</button>
      </div>
    `).join('');

    // Expected field - can be variable name OR literal value
    const expectedType = iter.expectedType ?? 'variable';
    const expectedVariableName = iter.expectedVariableName ?? '';
    const expectedValue = iter.expectedValue ?? '';

    return `
      <div class="override-iteration" data-iter="${iterIdx}">
        <div class="override-iteration-header">
          <span>Iteration ${iterIdx + 1}</span>
          <button type="button" class="hspf-btn" data-action="remove-iteration" data-iter-idx="${iterIdx}">Entfernen</button>
        </div>
        
        <div style="margin-bottom: 10px;">
          <strong>Input-Variablen:</strong>
        </div>
        <div class="override-variables">
          ${rows}
        </div>
        <div style="margin-top:6px; margin-bottom: 12px;">
          <button type="button" class="hspf-btn" data-action="add-var" data-iter-idx="${iterIdx}">+ Variable</button>
        </div>
        
        <div style="border-top: 1px solid #ccc; padding-top: 10px;">
          <div style="margin-bottom: 8px;">
            <strong>Erwartetes Ergebnis:</strong>
          </div>
          
          <div style="display: flex; gap: 8px; margin-bottom: 8px; align-items: stretch;">
            <select data-iter-idx="${iterIdx}" data-override-field="expectedType" style="padding: 6px; border: 1px solid #ccc; border-radius: 3px; width: 160px; flex-shrink: 0;">
              <option value="variable" ${expectedType === 'variable' ? 'selected' : ''}>Variablenwert</option>
              <option value="value" ${expectedType === 'value' ? 'selected' : ''}>Direkter Wert</option>
            </select>
            
            <input type="text" 
                   placeholder="${expectedType === 'variable' ? 'z.B. summe, result, x' : 'z.B. 42, false, &quot;text&quot;'}" 
                   data-iter-idx="${iterIdx}" 
                   data-override-field="expectedInput"
                   data-expected-type="${expectedType}"
                   value="${escapeHtml(expectedType === 'variable' ? expectedVariableName : expectedValue)}" 
                   style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box;" />
          </div>
          
          <div style="font-size: 0.9em; color: #666; margin-top: 6px;">
            ${expectedType === 'variable' 
              ? '<em>💡 Script wird ausgeführt, Wert dieser Variable am Ende wird als Ergebnis verwendet</em>'
              : '<em>💡 Dieser Wert wird direkt als Ergebnis verwendet (kein Script-Aufruf nötig)</em>'}
          </div>
        </div>
      </div>
    `;
  }).join('');
}

function getTaskTypeValue(prefix) {
  if (prefix === 'task') {
    return $('new-task-type')?.value || 'code';
  }
  if (prefix === 'edit-task') {
    return $('edit-task-type')?.value || 'code';
  }
  return $(`${prefix}-type`)?.value || 'code';
}

function updateMaxIterationsFromBuilder(prefix) {
  const taskType = getTaskTypeValue(prefix);
  const maxIterInput = $(`${prefix}-max-iterations`);
  if (!maxIterInput) return;

  // Only code_reading derives iteration count from override sets.
  // For code_random_complex and all other types, keep the manually set value.
  if (taskType !== 'code_reading') {
    return;
  }

  const iterationsCount = overridesBuilders[prefix]?.iterations?.length || 0;
  // Always allow manual editing - don't set readOnly
  // Users can manually enter iterations count even for CODE_READING
  maxIterInput.value = Math.max(1, iterationsCount || 1);
}

function initOverridesBuilder(prefix) {
  const builder = $(`${prefix}-var-overrides-builder`);
  const addIterationBtn = $(`${prefix}-add-iteration`);
  const applyJsonBtn = $(`${prefix}-apply-overrides-json`);
  const toggleJsonBtn = $(`${prefix}-toggle-overrides-json`);
  const jsonContainer = $(`${prefix}-var-overrides-json`);
  const jsonTextarea = $(`${prefix}-var-overrides`);

  if (!builder || !addIterationBtn || !jsonTextarea) return;

  overridesBuilders[prefix] = { iterations: [] };

  addIterationBtn.addEventListener('click', () => {
    overridesBuilders[prefix].iterations.push({ 
      vars: [{ key: '', value: '' }], 
      expectedType: 'variable',
      expectedVariableName: '',
      expectedValue: ''
    });
    renderOverridesBuilder(prefix);
    syncOverridesJson(prefix);
    updateMaxIterationsFromBuilder(prefix);
  });

  if (applyJsonBtn) {
    applyJsonBtn.addEventListener('click', () => {
      setOverridesFromJson(prefix, jsonTextarea.value.trim());
    });
  }

  if (toggleJsonBtn && jsonContainer) {
    toggleJsonBtn.addEventListener('click', () => {
      const show = jsonContainer.style.display === 'none';
      jsonContainer.style.display = show ? 'block' : 'none';
      toggleJsonBtn.textContent = show ? '▲ JSON ausblenden' : '▼ JSON manuell bearbeiten';
    });
  }

  builder.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const action = target.dataset.action;
    const iterIdx = parseInt(target.dataset.iterIdx || '-1', 10);
    const varIdx = parseInt(target.dataset.varIdx || '-1', 10);

    // Only handle button clicks with action attribute
    if (!action) return;

    if (action === 'add-var' && iterIdx >= 0) {
      overridesBuilders[prefix].iterations[iterIdx].vars.push({ key: '', value: '' });
    }
    if (action === 'remove-var' && iterIdx >= 0 && varIdx >= 0) {
      overridesBuilders[prefix].iterations[iterIdx].vars.splice(varIdx, 1);
      if (overridesBuilders[prefix].iterations[iterIdx].vars.length === 0) {
        overridesBuilders[prefix].iterations[iterIdx].vars.push({ key: '', value: '' });
      }
    }
    if (action === 'remove-iteration' && iterIdx >= 0) {
      overridesBuilders[prefix].iterations.splice(iterIdx, 1);
    }

    renderOverridesBuilder(prefix);
    syncOverridesJson(prefix);
    updateMaxIterationsFromBuilder(prefix);
  });

  builder.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const field = target.dataset.overrideField;
    const iterIdx = parseInt(target.dataset.iterIdx || '-1', 10);
    
    if (!field || iterIdx < 0) return;

    // Handle expected field with unified "expectedInput" field name
    if (field === 'expectedInput') {
      const expectedType = target.dataset.expectedType;
      if (expectedType === 'variable') {
        overridesBuilders[prefix].iterations[iterIdx].expectedVariableName = target.value;
      } else if (expectedType === 'value') {
        overridesBuilders[prefix].iterations[iterIdx].expectedValue = target.value;
      }
    } else if (field === 'key' || field === 'value') {
      // Handle key/value fields for input variables
      const varIdx = parseInt(target.dataset.varIdx || '-1', 10);
      if (varIdx < 0) return;
      overridesBuilders[prefix].iterations[iterIdx].vars[varIdx][field] = target.value;
    }
    
    // Only sync JSON, don't re-render the builder inputs
    syncOverridesJson(prefix);
  });

  // Handle expectedType dropdown change
  builder.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const field = target.dataset.overrideField;
    const iterIdx = parseInt(target.dataset.iterIdx || '-1', 10);
    
    // Only handle dropdown changes, not input changes
    if (field === 'expectedType' && iterIdx >= 0 && target.tagName === 'SELECT') {
      const newType = target.value;
      overridesBuilders[prefix].iterations[iterIdx].expectedType = newType;
      // Clear the other field when switching mode
      if (newType === 'variable') {
        overridesBuilders[prefix].iterations[iterIdx].expectedValue = '';
      } else {
        overridesBuilders[prefix].iterations[iterIdx].expectedVariableName = '';
      }
      renderOverridesBuilder(prefix);
      syncOverridesJson(prefix);
    }
  });

  overridesBuilders[prefix].iterations.push({ 
    vars: [{ key: '', value: '' }], 
    expectedType: 'variable',
    expectedVariableName: '',
    expectedValue: ''
  });
  renderOverridesBuilder(prefix);
  syncOverridesJson(prefix);
  updateMaxIterationsFromBuilder(prefix);
}

function setChoiceValidationError(elementId, message) {
  const el = document.getElementById(elementId);
  if (!el) return;
  const text = (message || '').trim();
  if (text) {
    el.textContent = text;
    el.style.display = 'block';
  } else {
    el.textContent = '';
    el.style.display = 'none';
  }
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

  for (const test of testCases) {
    if (!test || test.type !== 'intelligent') continue;

    // Mode validation
    const mode = test.mode || 'function';
    if (mode !== 'function' && mode !== 'vars') {
      return 'Intelligent: mode muss "function" oder "vars" sein';
    }

    // Tests count validation
    const testsCount = Number(test.tests ?? 4);
    if (!Number.isFinite(testsCount) || testsCount < 1) {
      return 'Intelligent: tests muss >= 1 sein';
    }

    // Solution code validation
    const effectiveSolution = test.solution_code || solutionCode || '';
    if (!effectiveSolution) {
      return 'Intelligent: Musterloesung fehlt (Solution Code)';
    }

    if (mode === 'function') {
      // Function Mode: validate function.name and function.params
      if (!test.function || typeof test.function !== 'object') {
        return 'Intelligent (function): function-Objekt fehlt';
      }
      
      if (!test.function.name || test.function.name.trim() === '') {
        return 'Intelligent (function): Funktionsname fehlt';
      }

      if (!Array.isArray(test.function.params)) {
        return 'Intelligent (function): params muss ein Array sein';
      }

      // Validate that params is an array of strings
      for (let i = 0; i < test.function.params.length; i++) {
        if (typeof test.function.params[i] !== 'string' || test.function.params[i].trim() === '') {
          return `Intelligent (function): Parameter ${i+1} muss ein nicht-leerer String sein`;
        }
      }

    } else if (mode === 'vars') {
      // Vars Mode: validate inputs and outputs arrays
      if (!Array.isArray(test.inputs)) {
        return 'Intelligent (vars): inputs muss ein Array sein';
      }

      if (test.inputs.length === 0) {
        return 'Intelligent (vars): mindestens 1 Input erforderlich';
      }

      // Validate that inputs is an array of strings
      for (let i = 0; i < test.inputs.length; i++) {
        if (typeof test.inputs[i] !== 'string' || test.inputs[i].trim() === '') {
          return `Intelligent (vars): Input ${i+1} muss ein nicht-leerer String sein`;
        }
      }

      if (!Array.isArray(test.outputs)) {
        return 'Intelligent (vars): outputs muss ein Array sein';
      }

      if (test.outputs.length === 0) {
        return 'Intelligent (vars): mindestens 1 Output erforderlich';
      }

      // Validate that outputs is an array of strings
      for (let i = 0; i < test.outputs.length; i++) {
        if (typeof test.outputs[i] !== 'string' || test.outputs[i].trim() === '') {
          return `Intelligent (vars): Output ${i+1} muss ein nicht-leerer String sein`;
        }
      }
    }
  }

  return null;
}

async function fetchFreshTaskForEdit(taskId) {
  if (!state.currentAssignmentId) {
    return null;
  }

  const response = await requestJson(`../api/tasks/list.php?assignment_id=${state.currentAssignmentId}&include_expected=1`);
  if (!response.ok || !Array.isArray(response.tasks)) {
    return null;
  }

  return response.tasks.find((t) => t.id === taskId) || null;
}

async function openEditTaskModal(taskId) {
  let task = null;

  // Always load a fresh snapshot before opening edit modal to avoid stale code/template values.
  try {
    task = await fetchFreshTaskForEdit(taskId);
  } catch (err) {
    console.warn('Failed to fetch fresh task for edit modal, using local cache:', err);
  }

  if (task) {
    const localTaskIndex = state.tasks.findIndex((t) => t.id === taskId);
    if (localTaskIndex >= 0) {
      state.tasks[localTaskIndex] = task;
    }
  } else {
    task = state.tasks.find((t) => t.id === taskId);
  }

  if (!task) return;

  // Basic fields
  $('edit-task-id').value = task.id;
  $('edit-task-title').value = task.title || '';
  if ($('edit-task-max-attempts')) $('edit-task-max-attempts').value = task.max_attempts ? task.max_attempts : 1;
  if ($('edit-task-max-iterations')) {
    const iterValue = task.max_iterations ? task.max_iterations : 3;
    $('edit-task-max-iterations').value = iterValue;
  }
  if ($('edit-task-show-solution')) {
    $('edit-task-show-solution').checked =
      task.show_solution === 1 ||
      task.show_solution === true ||
      task.show_solution === '1' ||
      task.show_solution === 'true';
  }
  if ($('edit-task-show-solution-code')) {
    $('edit-task-show-solution-code').checked =
      task.show_solution_code === 1 ||
      task.show_solution_code === true ||
      task.show_solution_code === '1' ||
      task.show_solution_code === 'true';
  }
  
  if ($('edit-task-folderstructure')) {
    $('edit-task-folderstructure').checked =
      task.folderstructure === 1 ||
      task.folderstructure === true ||
      task.folderstructure === '1' ||
      task.folderstructure === 'true';
  }
  
  if ($('edit-task-allowDownload')) {
    $('edit-task-allowDownload').checked =
      task.allowDownload === 1 ||
      task.allowDownload === true ||
      task.allowDownload === '1' ||
      task.allowDownload === 'true';
  }
  
  if ($('edit-task-allowCodeUiWebEdit')) {
    $('edit-task-allowCodeUiWebEdit').checked =
      task.allowCodeUiWebEdit === 1 ||
      task.allowCodeUiWebEdit === true ||
      task.allowCodeUiWebEdit === '1' ||
      task.allowCodeUiWebEdit === 'true';
  }
  
  // Task type - use task_type if available, fallback to problem_type
  const taskType = task.task_type || task.problem_type || 'code';
  $('edit-task-type').value = taskType;
  if ($('edit-task-difficulty')) {
    $('edit-task-difficulty').value = task.task_difficulty || 'medium';
  }
  
  // Unified task_text and description fields (same for all task types)
  $('edit-task-text').value = task.task_text || '';
  
  // Load description into TinyMCE editor if available, else into textarea
  const descriptionContent = task.description || '';
  const editDescEditor = tinymce.get('edit-task-description');
  if (editDescEditor) {
    editDescEditor.setContent(descriptionContent);
  } else {
    $('edit-task-description').value = descriptionContent;
  }
  
  // Code fields
  $('edit-task-template').value = task.code_template || '';
  $('edit-task-randomizer-code').value = task.randomizer_code || '';
  $('edit-task-hint1').value = task.hint1 || '';
  $('edit-task-hint2').value = task.hint2 || '';
  $('edit-task-hint3').value = task.hint3 || '';
  
  // Load stoff into TinyMCE editor if available, else into textarea
  const stoffContent = task.stoff || '';
  const editStoffEd = tinymce.get('edit-task-stoff');
  if (editStoffEd) {
    editStoffEd.setContent(stoffContent);
  } else {
    $('edit-task-stoff').value = stoffContent;
  }
  
  $('edit-task-test-cases').value = task.test_cases || '';
  $('edit-task-solution').value = task.solution_code || '';
  
  if ($('edit-task-correct-answer')) $('edit-task-correct-answer').value = task.correct_answer || '';
  if ($('edit-task-var-overrides')) {
    const overridesValue = task.variable_overrides
      ? (typeof task.variable_overrides === 'string' ? task.variable_overrides : JSON.stringify(task.variable_overrides))
      : '';
    $('edit-task-var-overrides').value = overridesValue;
    setOverridesFromJson('edit-task', overridesValue.trim());
  }
  
  // Image
  if ($('edit-task-image-url')) {
    $('edit-task-image-url').value = task.image_url || '';
    if (task.image_url) {
      $('edit-task-image-preview').innerHTML = `
        <img src="${task.image_url}" style="max-width: 300px; max-height: 200px; margin-top: 8px; border: 1px solid #ddd; border-radius: 3px;" />
        <br/><button type="button" onclick="document.getElementById('edit-task-image-url').value=''; document.getElementById('edit-task-image-preview').innerHTML=''; document.getElementById('edit-task-image-upload').value='';" 
          style="margin-top: 4px; font-size: 12px;">Bild entfernen</button>
      `;
    } else {
      $('edit-task-image-preview').innerHTML = '';
    }
  }
  
  // Options for Single/Multiple Choice
  if (window.editOptionsBuilder && (taskType === 'single_choice' || taskType === 'multiple_choice')) {
    window.editOptionsBuilder.setTaskType(taskType);
    window.editOptionsBuilder.setOptions(task.options || []);
  }
  
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
  $('modal-title').textContent = `Edit Task #${task.id}: ${task.title}`;
  
  // Update field visibility based on task type
  const editForm = $('task-edit-form');
  if (window.TaskTypeManager && editForm) {
    window.TaskTypeManager.updateFieldVisibility(editForm, taskType);
  }
  updateTestTypeVisibility(); // Update test type selector visibility for free_text
  
  // Only set active tab if tabs exist in the form
  if (editForm && editForm.querySelectorAll('.task-tab').length > 0) {
    setActiveTaskTab(editForm, 'base');
  }
  
  updateMaxIterationsFromBuilder('edit-task');
  updateRandomButtonVisibility(); // Update randomizer field visibility
}

function closeEditTaskModal() {
  $('task-modal').classList.remove('active');
  $('edit-task-id').value = '';
  editTestCasesData = [];
}

function confirmCloseEditTaskModal() {
  if (confirm('Sind Sie sicher, dass Sie das Modal schließen möchten? Nicht gespeicherte Änderungen werden verworfen.')) {
    closeEditTaskModal();
  }
}

async function handleEditTaskSubmit(e) {
  e.preventDefault();
  
  // Get which button was clicked
  const submitter = e.submitter;
  const actionValue = submitter?.getAttribute('value') || 'save';
  const shouldClose = actionValue === 'save-close';

  const taskId = parseInt($('edit-task-id').value, 10);
  if (!taskId) return;

  const taskType = $('edit-task-type').value;
  
  
  const payload = {
    id: taskId,
    title: $('edit-task-title').value.trim(),
    max_attempts: $('edit-task-max-attempts').value ? parseInt($('edit-task-max-attempts').value, 10) : 1,
    show_solution: $('edit-task-show-solution').checked ? 1 : 0,
    show_solution_code: $('edit-task-show-solution-code').checked ? 1 : 0,
    folderstructure: $('edit-task-folderstructure').checked ? 1 : 0,
    allowDownload: $('edit-task-allowDownload').checked ? 1 : 0,
    allowCodeUiWebEdit: $('edit-task-allowCodeUiWebEdit').checked ? 1 : 0,
    task_type: taskType,
    task_difficulty: ($('edit-task-difficulty')?.value || 'medium'),
    problem_type: taskType,  // Keep for backwards compatibility
    image_url: $('edit-task-image-url') ? ($('edit-task-image-url').value.trim() || null) : null,
    code_template: $('edit-task-template').value,
    randomizer_code: $('edit-task-randomizer-code').value.trim() || null,
    hint1: $('edit-task-hint1').value,
    hint2: $('edit-task-hint2').value,
    hint3: $('edit-task-hint3').value,
    test_cases: $('edit-task-test-cases').value.trim() || null,
    solution_code: $('edit-task-solution').value.trim() || null
  };
  
  // Get stoff from TinyMCE if available, else from textarea
  const editStoffEditor = tinymce.get('edit-task-stoff');
  if (editStoffEditor) {
    payload.stoff = editStoffEditor.getContent().trim();
  } else {
    payload.stoff = $('edit-task-stoff').value;
  }
  
  // For all task types: use unified task_text field
  payload.task_text = $('edit-task-text').value.trim();
  
  // Get description from TinyMCE if available, else from textarea
  const editDescriptionEditor = tinymce.get('edit-task-description');
  if (editDescriptionEditor) {
    payload.description = editDescriptionEditor.getContent().trim();
  } else {
    payload.description = $('edit-task-description').value.trim();
  }
  
  // Add quiz-specific fields
  payload.keywords = $('edit-task-keywords') ? $('edit-task-keywords').value.trim() : null;
  payload.correct_answer = $('edit-task-correct-answer') ? $('edit-task-correct-answer').value.trim() : null;
  payload.variable_overrides = $('edit-task-var-overrides') ? $('edit-task-var-overrides').value.trim() : null;
  
  // Handle test_cases for code and free_text tasks
  if (taskType === 'code' || taskType === 'code_ui' || taskType === 'free_text') {
    payload.test_cases = $('edit-task-test-cases').value.trim() || null;
  }

  if (taskType === 'code_reading') {
    const overridesPayload = getOverridesPayload('edit-task');
    if (overridesPayload === null && $('edit-task-var-overrides')?.value.trim() !== '') {
      return;
    }
    payload.variable_overrides = overridesPayload || null;
  }

  if (taskType === 'code_random_complex') {
    const overridesPayload = getOverridesPayload('edit-task');
    if (overridesPayload === null && $('edit-task-var-overrides')?.value.trim() !== '') {
      return;
    }
    if (!hasRandomMarkerInOverrides(overridesPayload)) {
      alert('code_random_complex benoetigt variable_overrides mit mindestens einem <random>-Marker in inputs.');
      return;
    }
    const randomizerValue = ($('edit-task-randomizer-code')?.value || '').trim();
    if (!randomizerValue) {
      alert('code_random_complex benoetigt Randomizer-Code.');
      return;
    }
    const templateValue = ($('edit-task-template')?.value || '').trim();
    const hasValuesDict = /\bvalues\b/.test(templateValue);
    const hasPlaceholders = /\{[a-zA-Z_][a-zA-Z0-9_]*\}/.test(templateValue);
    if (!templateValue || (!hasValuesDict && !hasPlaceholders)) {
      alert('code_random_complex benoetigt Code-Template mit values-Dict oder {placeholder}-Syntax.');
      return;
    }
    payload.variable_overrides = overridesPayload;
  }

  if (taskType === 'code_reading') {
    const overridesPayload = getOverridesPayload('edit-task');
    const overridesArray = Array.isArray(overridesPayload)
      ? overridesPayload
      : (overridesPayload ? [overridesPayload] : buildOverridesArray('edit-task'));
    payload.max_iterations = Math.max(1, overridesArray.length || 1);
  } else if (taskType === 'code_random_complex') {
    payload.max_iterations = $('edit-task-max-iterations')?.value
      ? parseInt($('edit-task-max-iterations').value, 10)
      : 3;
  }
  
  // Handle options for Single/Multiple Choice
  if ((taskType === 'single_choice' || taskType === 'multiple_choice') && window.editOptionsBuilder) {
    payload.options = window.editOptionsBuilder.getOptions();
    const validationError = validateChoiceOptions(taskType, payload.options);
    if (validationError) {
      setChoiceValidationError('edit-task-options-error', validationError);
      return;
    }
    setChoiceValidationError('edit-task-options-error', '');
  }

  // If builder has data, prefer it over manual JSON
  if (Array.isArray(editTestCasesData) && editTestCasesData.length > 0) {
    // Special case: if single intelligent test, save as object (not array)
    if (editTestCasesData.length === 1 && editTestCasesData[0].type === 'intelligent') {
      const intelligentConfig = {...editTestCasesData[0]};
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

  // Only close if user chose "save-close"
  if (shouldClose) {
    closeEditTaskModal();
  }
  
  await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
  await loadAssignments();
}

function bindEvents() {
  document.querySelectorAll('.tab').forEach((btn) => {
    btn.addEventListener('click', () => setActiveTab(btn.dataset.tab));
  });

  initTaskFormTabs('task-form');
  initTaskFormTabs('task-edit-form');

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

  // Pre-Task Dialog Handlers
  const preTaskContinueBtn = $('pre-task-continue-btn');
  if (preTaskContinueBtn) {
    preTaskContinueBtn.addEventListener('click', continueFromPreTaskDialog);
  }

  const preTaskCancelBtn = $('pre-task-cancel-btn');
  if (preTaskCancelBtn) {
    preTaskCancelBtn.addEventListener('click', closePreTaskDialog);
  }

  const preTaskCloseBtn = $('pre-task-close-btn');
  if (preTaskCloseBtn) {
    preTaskCloseBtn.addEventListener('click', closePreTaskDialog);
  }

  const preTaskTitleInput = $('pre-task-title');
  if (preTaskTitleInput) {
    preTaskTitleInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        continueFromPreTaskDialog();
      }
    });
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
        // Process import with new importer
        const { tasks, images, manifest, folderFilesMap } = await window.taskImporter.processImport(file);
        
        // Add assignment ID to each task (ensure it's an integer)
        tasks.forEach(task => {
          task.assignment_id = parseInt(state.currentAssignmentId, 10);
        });
        
        // Import tasks with images and folder files
        const results = await window.taskImporter.importTasks(tasks, images, folderFilesMap || {});
        
        // Show results
        const createdCount = results.created.length;
        const failedCount = results.failed.length;
        let message = `✓ ${createdCount} task(s) imported successfully`;
        
        if (failedCount > 0) {
          message += `\n✗ ${failedCount} task(s) failed:\n`;
          results.failed.forEach(f => {
            message += `\n• ${f.title}: ${f.error}`;
          });
        }
        
        alert(message);
        
        // Reload tasks
        await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
        await loadAssignments();
        
      } catch (err) {
        console.error('Import error:', err);
        alert('Import failed: ' + err.message);
      }
      
      // Reset input
      importTaskFileInput.value = '';
    });
  }

  // Import JSON Text button
  const importJsonTextBtn = $('import-json-text-btn');
  const importJsonTextModal = $('import-json-text-modal');
  const importJsonTextInput = $('import-json-text-input');
  const importJsonTextConfirmBtn = $('import-json-text-confirm-btn');
  const importJsonTextCancelBtn = $('import-json-text-cancel-btn');
  const importJsonTextCloseBtn = $('import-json-text-close-btn');
  const importJsonTextError = $('import-json-text-error');

  if (importJsonTextBtn && importJsonTextModal) {
    importJsonTextBtn.addEventListener('click', () => {
      if (!state.currentAssignmentId) {
        alert('Bitte wählen Sie zuerst ein Assignment aus');
        return;
      }
      importJsonTextModal.classList.add('active');
      importJsonTextInput.value = '';
      importJsonTextError.style.display = 'none';
      importJsonTextInput.focus();
    });

    if (importJsonTextCloseBtn) {
      importJsonTextCloseBtn.addEventListener('click', () => {
        importJsonTextModal.classList.remove('active');
      });
    }

    if (importJsonTextCancelBtn) {
      importJsonTextCancelBtn.addEventListener('click', () => {
        importJsonTextModal.classList.remove('active');
      });
    }

    if (importJsonTextConfirmBtn) {
      importJsonTextConfirmBtn.addEventListener('click', async () => {
        const jsonText = importJsonTextInput.value.trim();

        if (!jsonText) {
          importJsonTextError.textContent = '⚠️ Bitte geben Sie JSON ein';
          importJsonTextError.style.display = 'block';
          return;
        }

        let jsonData;
        try {
          jsonData = JSON.parse(jsonText);
        } catch (e) {
          importJsonTextError.textContent = '⚠️ JSON ist nicht valid: ' + e.message;
          importJsonTextError.style.display = 'block';
          return;
        }

        // Validate required fields
        if (!jsonData.version || !jsonData.title) {
          importJsonTextError.textContent = '⚠️ JSON muss "version" und "title" enthalten';
          importJsonTextError.style.display = 'block';
          return;
        }

        try {
          // Add assignment ID
          jsonData.assignment_id = parseInt(state.currentAssignmentId, 10);

          // API expects test_cases as a JSON string
          if (jsonData.test_cases && typeof jsonData.test_cases !== 'string') {
            jsonData.test_cases = JSON.stringify(jsonData.test_cases);
          }

          // Import single task via create endpoint
          const response = await requestJson('../api/tasks/create.php', {
            method: 'POST',
            body: JSON.stringify(jsonData)
          });

          if (response.ok || response.success) {
            alert('✓ Task erfolgreich importiert!');
            importJsonTextModal.classList.remove('active');
            importJsonTextInput.value = '';
            await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
            await loadAssignments();
          } else {
            throw new Error(response.error || 'Import failed');
          }
        } catch (error) {
          importJsonTextError.textContent = '⚠️ Fehler beim Importieren: ' + error.message;
          importJsonTextError.style.display = 'block';
        }
      });
    }

    // Close modal on outside click
    importJsonTextModal.addEventListener('click', (e) => {
      if (e.target === importJsonTextModal) {
        importJsonTextModal.classList.remove('active');
      }
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
  
  // Close modal on background click with confirmation
  $('task-modal').addEventListener('click', (e) => {
    if (e.target === $('task-modal')) {
      confirmCloseEditTaskModal();
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
        confirmCloseNewTaskModal();
      }
    });
  }

  $('logout-btn').addEventListener('click', async () => {
    await requestJson('../api/auth/logout.php', { method: 'POST' });
    window.location.href = 'login.php';
  });

  bindDeploySyncEvents();

  const projectsSearchBtn = $('projects-search-btn');
  if (projectsSearchBtn) {
    projectsSearchBtn.addEventListener('click', async () => {
      state.projectsFilterText = $('projects-search')?.value?.trim() || '';
      state.projectsFilterUserId = state.projectsFilterUserId || null;
      state.projectsFilterTeamId = $('projects-team-filter')?.value || '';
      state.projectsFilterSemester = $('projects-semester-filter')?.value || '';
      state.projectsPage = 1;
      state.projectsLimit = parseInt($('projects-limit')?.value, 10) || 50;
      await loadProjects();
    });
  }

  const projectsClearBtn = $('projects-clear-btn');
  if (projectsClearBtn) {
    projectsClearBtn.addEventListener('click', () => {
      resetProjectFilters();
    });
  }

  const projectsSearchInput = $('projects-search');
  if (projectsSearchInput) {
    projectsSearchInput.addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        state.projectsFilterText = projectsSearchInput.value.trim();
        state.projectsFilterTeamId = $('projects-team-filter')?.value || '';
        state.projectsFilterSemester = $('projects-semester-filter')?.value || '';
        state.projectsPage = 1;
        state.projectsLimit = parseInt($('projects-limit')?.value, 10) || 50;
        await loadProjects();
      }
    });
  }

  $('projects-team-filter')?.addEventListener('change', async () => {
    state.projectsFilterTeamId = $('projects-team-filter')?.value || '';
    state.projectsPage = 1;
    await loadProjects();
  });

  $('projects-semester-filter')?.addEventListener('change', async () => {
    state.projectsFilterSemester = $('projects-semester-filter')?.value || '';
    state.projectsPage = 1;
    await loadProjects();
  });

  $('projects-prev')?.addEventListener('click', async () => {
    if (state.projectsPage <= 1) return;
    state.projectsPage -= 1;
    await loadProjects();
  });

  $('projects-next')?.addEventListener('click', async () => {
    if (state.projectsPage >= state.projectsTotalPages) return;
    state.projectsPage += 1;
    await loadProjects();
  });

  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    const id = parseInt(btn.dataset.id, 10);

    if (action === 'open-project') {
      window.location.href = `editor.php?project_id=${id}`;
    }

    if (action === 'open-user-projects') {
      const userLabel = btn.dataset.name || `User #${id}`;
      await window.openAdminProjectsForUser(id, userLabel);
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
      $('assignment-available-from').value = toDatetimeLocalValue(a.available_from);
      $('assignment-due-date').value = toDatetimeLocalValue(a.due_date);
      $('assignment-hard-deadline').value = toDatetimeLocalValue(a.hard_deadline);
      $('assignment-allow-late').value = a.allow_late_submission === false ? 'false' : 'true';
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

    if (action === 'reset-assignment-attempts') {
      const a = state.assignments.find((x) => x.id === id);
      if (!a) return;
      if (!confirm(`ACHTUNG: Alle Versuche und Fortschritte für "${a.title}" werden zurückgesetzt!\\n\\nSind Sie sicher?`)) return;
      const resetToken = `RESET-${id}`;
      const typedToken = window.prompt(
        `Sicherheitsprüfung: Gib ${resetToken} ein, um den Reset für "${a.title}" auszuführen.`
      );
      if (typedToken === null) return;
      if (String(typedToken).trim() !== resetToken) {
        alert('Abgebrochen: Sicherheitscode stimmt nicht überein.');
        return;
      }
      try {
        const response = await requestJson('../api/assignments/reset_attempts.php', {
          method: 'POST',
          body: JSON.stringify({ assignment_id: id })
        });
        if (response.ok) {
          alert(`Erfolg! ${response.affected_rows} Einträge zurückgesetzt.`);
        } else {
          throw new Error(response.error);
        }
      } catch (err) {
        alert('Fehler beim Zurücksetzen: ' + err.message);
      }
    }

    if (action === 'select-assignment') {
      const a = state.assignments.find((x) => x.id === id);
      if (!a) return;
      await loadTasks(a.id, a.title);
    }

    if (action === 'view-assignment-users') {
      window.location.href = `evaluation.php?assignment_id=${id}`;
    }

    if (action === 'test-assignment') {
      const a = state.assignments.find((x) => x.id === id);
      if (!a) return;
      // Open test view in new tab
      window.open(`editor_assignment_test.php?assignment_id=${id}`, `test_assignment_${id}`, 'width=1200,height=800');
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

    if (action === 'clone-task') {
      if (!state.currentAssignmentId) {
        alert('No assignment selected');
        return;
      }
      if (!confirm('Clone this task?')) return;
      try {
        const response = await requestJson('../api/admin/tasks/clone.php', {
          method: 'POST',
          body: JSON.stringify({ task_id: id, assignment_id: state.currentAssignmentId })
        });
        if (response.ok) {
          alert(`Task cloned successfully!`);
          await loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
          await loadAssignments();
        } else {
          throw new Error(response.error);
        }
      } catch (err) {
        alert('Clone failed: ' + err.message);
      }
    }

    if (action === 'edit-task') {
      await openEditTaskModal(id);
    }

    if (action === 'view-task') {
      if (!state.currentAssignmentId) return;
      // Open test view in new tab/window
      window.open(`editor_assignment_test.php?assignment_id=${state.currentAssignmentId}&task_id=${id}`, '_blank');
    }

    if (action === 'toggle-user') {
      const status = btn.dataset.status;
      await requestJson('../api/admin/users/update.php', {
        method: 'POST',
        body: JSON.stringify({ id, status })
      });
      await loadUsers();
    }

    if (action === 'open-ticket') {
      const userId = parseInt(btn.dataset.userId || '0', 10);
      const assignmentId = parseInt(btn.dataset.assignmentId || '0', 10);
      openTicketTestView(userId, assignmentId);
    }

    if (action === 'delete-ticket') {
      const ticketId = parseInt(btn.dataset.ticketId || '0', 10);
      await deleteTicket(ticketId);
    }
  });

}

async function init() {
  bindEvents();
  resetProjectFilters();
  await Promise.all([loadAssignments()]);
  const initialTab = new URLSearchParams(window.location.search).get('tab');
  if (initialTab && document.querySelector(`.tab[data-tab="${initialTab}"]`)) {
    setActiveTab(initialTab);
  }
}

init().catch((err) => {
  console.error(err);
  alert(err.message || 'Failed to load admin dashboard');
});

// ===================================================================
// SOLUTION CODE VISIBILITY HELPER
// ===================================================================

// Helper function to check if Solution Code is needed based on task type
function updateSolutionCodeVisibility() {
  // Create form
  const newTaskType = $('new-task-type')?.value;
  const newTaskForm = $('task-form');
  if (newTaskForm) {
    let needsSolution = false;
    
    // Solution Code is always shown for:
    // 1. code tasks (any type of tests)
    // 2. code_random_complex tasks
    // 3. code_reading tasks
    if (newTaskType === 'code' || newTaskType === 'code_ui' || newTaskType === 'code_random_complex' || newTaskType === 'code_reading') {
      needsSolution = true;
    }
    
    const newSolutionField = newTaskForm.querySelector('[data-field="solution"]');
    if (newSolutionField) {
      newSolutionField.style.display = needsSolution ? 'block' : 'none';
    }
  }
  
  // Edit form
  const editTaskType = $('edit-task-type')?.value;
  const editTaskForm = $('task-edit-form');
  if (editTaskForm) {
    let needsSolution = false;
    
    // Solution Code is always shown for:
    // 1. code tasks (any type of tests)
    // 2. code_random_complex tasks
    // 3. code_reading tasks
    if (editTaskType === 'code' || editTaskType === 'code_ui' || editTaskType === 'code_random_complex' || editTaskType === 'code_reading') {
      needsSolution = true;
    }
    
    const editSolutionField = editTaskForm.querySelector('[data-field="solution"]');
    if (editSolutionField) {
      editSolutionField.style.display = needsSolution ? 'block' : 'none';
    }
  }
}

function setCodeOnlyOptionVisibility(inputId, isVisible) {
  const input = $(inputId);
  if (!input) return;

  const field = input.closest('.field');
  if (field) {
    field.style.display = isVisible ? '' : 'none';
  }

  input.disabled = !isVisible;
  if (!isVisible) {
    input.checked = false;
  }
}

function updateCodeOnlyOptionsVisibility() {
  const isNewTaskCode = ($('new-task-type')?.value || 'code') === 'code';
  const isEditTaskCode = ($('edit-task-type')?.value || 'code') === 'code';

  setCodeOnlyOptionVisibility('task-folderstructure', isNewTaskCode);
  setCodeOnlyOptionVisibility('task-allowDownload', isNewTaskCode);
  setCodeOnlyOptionVisibility('task-allowCodeUiWebEdit', isNewTaskCode);

  setCodeOnlyOptionVisibility('edit-task-folderstructure', isEditTaskCode);
  setCodeOnlyOptionVisibility('edit-task-allowDownload', isEditTaskCode);
  setCodeOnlyOptionVisibility('edit-task-allowCodeUiWebEdit', isEditTaskCode);
}

// ===================================================================
// RANDOMIZER & FIELD VISIBILITY HELPERS
// ===================================================================

// Helper function to update Random Numbers button and field visibility
function updateRandomButtonVisibility() {
  const newTaskType = $('new-task-type')?.value;
  const editTaskType = $('edit-task-type')?.value;
  
  const taskRandomSnippetBtn = $('task-random-snippet');
  if (taskRandomSnippetBtn) {
    taskRandomSnippetBtn.style.display = newTaskType === 'code_random_complex' ? 'block' : 'none';
  }
  
  const editTaskRandomSnippetBtn = $('edit-task-random-snippet');
  if (editTaskRandomSnippetBtn) {
    editTaskRandomSnippetBtn.style.display = editTaskType === 'code_random_complex' ? 'block' : 'none';
  }
  
  // Update Randomizer Code field visibility
  // Only show when it's actually needed:
  // 1. For code_random_complex tasks (always)
  // 2. For code tasks with intelligent tests
  const newTaskForm = $('task-form');
  if (newTaskForm) {
    let showRandomizer = false;
    if (newTaskType === 'code_random_complex') {
      showRandomizer = true;
    } else if (newTaskType === 'code' || newTaskType === 'code_ui') {
      showRandomizer = testCasesData.some(tc => tc.type === 'intelligent');
    }

    const newTaskRandomizerField = newTaskForm.querySelector('[data-field="randomizer-code"]');
    if (newTaskRandomizerField) {
      newTaskRandomizerField.style.display = showRandomizer ? 'block' : 'none';
    }
  }

  const editTaskForm = $('task-edit-form');
  if (editTaskForm) {
    let showRandomizer = false;
    if (editTaskType === 'code_random_complex') {
      showRandomizer = true;
    } else if (editTaskType === 'code' || editTaskType === 'code_ui') {
      showRandomizer = editTestCasesData.some(tc => tc.type === 'intelligent');
    }

    const editTaskRandomizerField = editTaskForm.querySelector('[data-field="randomizer-code"]');
    if (editTaskRandomizerField) {
      editTaskRandomizerField.style.display = showRandomizer ? 'block' : 'none';
    }
  }
  
  // Update Solution Code field visibility
  // Show only for code_random_complex OR for code tasks with intelligent tests
  updateSolutionCodeVisibility();
  updateCodeOnlyOptionsVisibility();
}

// ===================================================================
// TEST CASES BUILDER GUI
// ===================================================================

let testCasesData = []; // CREATE form
let editTestCasesData = []; // EDIT form

// Initialize TinyMCE WYSIWYG Editors
function initTinyMCEEditors() {
  if (typeof tinymce !== 'undefined') {
    tinymce.init({
      selector: '.tinymce-editor',
      height: 250,
      plugins: 'table lists link image code',
      toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | table | link image | code',
      menubar: 'edit insert view table format tools',
      content_css: false,
      skin: 'oxide',
      body_class: 'tinymce-body',
      statusbar: true,
      branding: false,
      mobile: {
        toolbar: 'undo redo | formatselect | bold italic | bullist numlist | table'
      }
    });
  }
}

// Initialize Test Cases Builder for CREATE form
function initTestCasesBuilder() {
  const addBtn = document.getElementById('add-test-btn');
  const generateBtn = document.getElementById('generate-json-btn');
  const autoDescBtn = document.getElementById('auto-description-btn');
  const typeSelector = document.getElementById('test-type-selector');
  
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      // For free_text tasks, always use 'output' type
      const taskType = $('new-task-type')?.value || 'code';
      const type = (taskType === 'free_text') ? 'output' : typeSelector.value;
      addTestCase(type, testCasesData, 'tests-container');
    });
  }
  
  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      generateJSON(testCasesData, 'task-test-cases');
    });
  }
  
  if (autoDescBtn) {
    autoDescBtn.addEventListener('click', () => {
      generateAutoDescription(testCasesData, 'task-description');
    });
  }
}

// Initialize Test Cases Builder for EDIT form
function initEditTestCasesBuilder() {
  const addBtn = document.getElementById('edit-add-test-btn');
  const generateBtn = document.getElementById('edit-generate-json-btn');
  const autoDescBtn = document.getElementById('edit-auto-description-btn');
  const typeSelector = document.getElementById('edit-test-type-selector');
  
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      // For free_text tasks, always use 'output' type
      const taskType = $('edit-task-type')?.value || 'code';
      const type = (taskType === 'free_text') ? 'output' : typeSelector.value;
      addTestCase(type, editTestCasesData, 'edit-tests-container');
    });
  }
  
  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      generateJSON(editTestCasesData, 'edit-task-test-cases');
    });
  }
  
  if (autoDescBtn) {
    autoDescBtn.addEventListener('click', () => {
      generateAutoDescription(editTestCasesData, 'edit-task-description');
    });
  }
}

// Add a test case to the GUI
function addTestCase(type, dataArray, containerId) {
  const testCase = { type };
  
  // Initialize type-specific default structures
  if (type === 'output') {
    testCase.expected = [];
    testCase.expected_type = 'text'; // Default: compare against text patterns
    testCase.validation_mode = 'loose'; // Default: loose whitespace matching
    testCase.case_sensitive = false; // Default: case-insensitive
  } else if (type === 'function') {
    testCase.function_name = '';
    testCase.test_cases = [{ args: [], expected: '' }]; // Start with one empty test case
    testCase.validation_mode = 'loose'; // Default: loose whitespace matching
  } else if (type === 'variable') {
    testCase.init_var_names = [];
    testCase.expected_var_names = [];
    testCase.tolerance_percent = '';
    testCase.test_cases = [{ init_values: [], expected_values: [] }]; // Start with one empty test case
  } else if (type === 'intelligent') {
    testCase.mode = 'function';
    testCase.tests = 5;
    testCase.function = {
      name: '',
      params: []
    };
    testCase.inputs = [];  // For vars mode
    testCase.outputs = [];  // For vars mode
  } else if (type === 'code_check') {
    testCase.keywords = [];
    testCase.operator = 'AND';
    testCase.feedback = '';
  }
  
  const idx = dataArray.length;
  dataArray.push(testCase);
  
  renderTestCases(dataArray, containerId);
}

// ===================================================================
// HELPER: Update test type selector visibility based on task type
// ===================================================================
function updateTestTypeVisibility() {
  // For CREATE form
  const newTaskFormElement = $('task-form');
  if (newTaskFormElement) {
    const newTaskType = $('new-task-type')?.value || 'code';
    const newBuilder = newTaskFormElement.querySelector('.test-cases-builder');
    if (newBuilder) {
      const newTypeLabel = newBuilder.querySelector('.builder-header label');
      if (newTypeLabel) {
        newTypeLabel.style.display = (newTaskType === 'free_text') ? 'none' : '';
      }
    }
  }
  
  // For EDIT form
  const editTaskFormElement = $('task-edit-form');
  if (editTaskFormElement) {
    const editTaskType = $('edit-task-type')?.value || 'code';
    const editBuilder = editTaskFormElement.querySelector('.test-cases-builder');
    if (editBuilder) {
      const editTypeLabel = editBuilder.querySelector('.builder-header label');
      if (editTypeLabel) {
        editTypeLabel.style.display = (editTaskType === 'free_text') ? 'none' : '';
      }
    }
  }
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
  
  // Determine task type
  let taskType = 'code'; // Default
  if (containerId === 'tests-container') {
    taskType = $('new-task-type')?.value || 'code';
  } else if (containerId === 'edit-tests-container') {
    taskType = $('edit-task-type')?.value || 'code';
  }
  
  container.innerHTML = dataArray.map((test, idx) => {
    return renderTestCaseHTML(test, idx, containerId);
  }).join('');
  
  // Bind event handlers
  bindTestCaseEvents(dataArray, containerId);
  
  // Update test type selector visibility for free_text tasks
  updateTestTypeVisibility();
  
  // For free_text tasks, find and hide the test-type-selector label
  const builderSection = container.closest('.test-cases-builder');
  if (builderSection) {
    const builderHeader = builderSection.querySelector('.builder-header');
    if (builderHeader) {
      const typeLabel = builderHeader.querySelector('label');
      if (typeLabel) {
        typeLabel.style.display = (taskType === 'free_text') ? 'none' : '';
      }
    }
  }
  
  // Update solution code visibility based on test types
  updateSolutionCodeVisibility();

  // Update randomizer visibility when intelligent tests are added/removed
  updateRandomButtonVisibility();
}

// Render single test case HTML
function renderTestCaseHTML(test, idx, containerId) {
  const type = test.type || 'output';
  
  // Determine which form this is and get the task type
  let taskType = 'code'; // Default
  if (containerId === 'tests-container') {
    taskType = $('new-task-type')?.value || 'code';
  } else if (containerId === 'edit-tests-container') {
    taskType = $('edit-task-type')?.value || 'code';
  }
  
  let html = `
    <div class="test-case-item" data-idx="${idx}" style="border:1px solid #e5e7eb; padding:12px; margin-bottom:10px; border-radius:6px; background:#f9fafb;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <strong>Test #${idx + 1} - ${type.toUpperCase()}</strong>
        <button type="button" class="btn-remove-test" data-idx="${idx}" style="background:#ef4444; color:white; padding:4px 8px; border:none; border-radius:4px; cursor:pointer;">✕ Remove</button>
      </div>
  `;
  
  if (type === 'output') {
    const patterns = test.expected && Array.isArray(test.expected) ? test.expected : (test.expected ? [test.expected] : []);
    const expectedType = test.expected_type || 'text';
    const validationMode = test.validation_mode || 'loose';
    const caseSensitive = test.case_sensitive !== undefined ? test.case_sensitive : false; // Default: false (case-insensitive)
    
    // For free_text tasks, don't show the "solution" option
    const showSolutionOption = (taskType !== 'free_text');
    
    html += `
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
        <div>
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Expected Type:</label>
          <select class="expected-type-select" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
            <option value="text" ${expectedType === 'text' ? 'selected' : ''}>Text Pattern</option>
            ${showSolutionOption ? `<option value="solution" ${expectedType === 'solution' ? 'selected' : ''}>Solution Code Output</option>` : ''}
            <option value="regex" ${expectedType === 'regex' ? 'selected' : ''}>Regex Pattern</option>
          </select>
          <div style="font-size:10px; color:#666; margin-top:2px;">
            Text | Solution | Regex
          </div>
        </div>
        
        <div>
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Validation Mode:</label>
          <select class="validation-mode-select" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
            <option value="strict" ${validationMode === 'strict' ? 'selected' : ''}>Strict (exakt)</option>
            <option value="loose" ${validationMode === 'loose' ? 'selected' : ''}>Loose (Leerzeichen)</option>
            <option value="contains" ${validationMode === 'contains' ? 'selected' : ''}>Contains (Substring)</option>
          </select>
          <div style="font-size:10px; color:#666; margin-top:2px;">
            Exakt | Normalisiert | Enthalten
          </div>
        </div>

        <div>
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Case Sensitive:</label>
          <select class="case-sensitive-select" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
            <option value="false" ${!caseSensitive ? 'selected' : ''}>Nein (Standard)</option>
            <option value="true" ${caseSensitive ? 'selected' : ''}>Ja</option>
          </select>
          <div style="font-size:10px; color:#666; margin-top:2px;">
            Groß/Klein egal | Beachten
          </div>
        </div>
      </div>
      
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
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">
          Function Name:
        </label>
        <input type="text" class="function-name-input" data-idx="${idx}" value="${test.function_name || ''}" 
               placeholder="e.g. quadrat" 
               style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
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
    const tolerancePercent = (test.tolerance_percent ?? '').toString();
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
          Abweichung in Prozent (optional):
        </label>
        <input type="number" class="variable-tolerance-input" data-idx="${idx}" value="${escapeHtml(tolerancePercent)}"
               placeholder="z.B. 1 für ±1%" step="0.01" min="0"
               style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:6px;">
        <div style="font-size:11px; color:#666;">
          Leer = exakter Vergleich. Wenn gesetzt, gilt für numerische Werte: erwartet ± x%.
        </div>
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
    const tests = test.tests || 4;
    
    // Extract params/inputs/outputs as comma-separated strings
    let paramsStr = '';
    let inputsStr = '';
    let outputsStr = '';
    let functionName = '';
    
    if (mode === 'function' && test.function) {
      functionName = test.function.name || '';
      paramsStr = (test.function.params || []).join(', ');
    } else if (mode === 'vars') {
      inputsStr = (test.inputs || []).join(', ');
      outputsStr = (test.outputs || []).join(', ');
    }
    
    html += `
      <div style="margin-bottom:12px; padding:12px; background:#eff6ff; border:1px solid #3b82f6; border-radius:6px;">
        <div style="font-weight:bold; font-size:13px; margin-bottom:10px; color:#1e40af;">
          ✨ Intelligent Test (vereinfacht mit Randomizer)
        </div>
        
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Mode:</label>
          <select class="intelligent-mode-input" data-idx="${idx}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:12px;">
            <option value="function" ${mode === 'function' ? 'selected' : ''}>Function (Funktions-Test)</option>
            <option value="vars" ${mode === 'vars' ? 'selected' : ''}>Vars (Variablen-Test)</option>
          </select>
        </div>
        
        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Tests Count:</label>
           <input type="number" class="intelligent-tests-input" data-idx="${idx}" value="${tests}" step="1" inputmode="numeric"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
           <div style="font-size:10px; color:#666; margin-top:2px;">Anzahl der Testdurchlaeufe mit verschiedenen Zufallswerten (mindestens 1)</div>
        </div>
    `;
    
    // Function Mode UI
    html += `
      <div class="intelligent-function-mode" data-idx="${idx}" style="display:${mode === 'function' ? 'block' : 'none'};">
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Function Name:</label>
          <input type="text" class="intelligent-fn-name-input" data-idx="${idx}" value="${escapeHtml(functionName)}" 
                 placeholder="z.B.: addiere, quadrat, verdoppeln"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
        </div>
        
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Parameter (comma-separated):</label>
          <input type="text" class="intelligent-fn-params-input" data-idx="${idx}" value="${escapeHtml(paramsStr)}" 
                 placeholder="z.B.: a, b, c"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
          <div style="font-size:10px; color:#666; margin-top:2px;">
            Namen der Parameter durch Komma getrennt. Reihenfolge wichtig!<br>
            <strong>Hinweis:</strong> Randomizer Code muss diese Namen als keys im values-Dict verwenden.
          </div>
        </div>
      </div>
    `;
    
    // Vars Mode UI
    html += `
      <div class="intelligent-vars-mode" data-idx="${idx}" style="display:${mode === 'vars' ? 'block' : 'none'};">
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Input-Variablen (comma-separated):</label>
          <input type="text" class="intelligent-vars-inputs-input" data-idx="${idx}" value="${escapeHtml(inputsStr)}" 
                 placeholder="z.B.: a, b, c"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
          <div style="font-size:10px; color:#666; margin-top:2px;">
            Variablen die im Code-Template initialisiert werden (#INIT START/END)<br>
            <strong>Hinweis:</strong> Randomizer Code setzt diese Variablennamen neu.
          </div>
        </div>
        
        <div style="margin-bottom:8px;">
          <label style="display:block; font-size:12px; margin-bottom:4px; font-weight:bold;">Output-Variablen (comma-separated):</label>
          <input type="text" class="intelligent-vars-outputs-input" data-idx="${idx}" value="${escapeHtml(outputsStr)}" 
                 placeholder="z.B.: result, summe, produkt"
                 style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
          <div style="font-size:10px; color:#666; margin-top:2px;">
            Variablen die am Ende des Codes geprüft werden
          </div>
        </div>
      </div>
    `;
    
    html += `
      </div>
      
      <div style="font-size:11px; color:#666; margin-top:12px; padding:10px; background:#fef3c7; border-radius:4px; border-left:3px solid #f59e0b;">
        <strong>📝 Wichtig:</strong><br>
        • <strong>Randomizer Code:</strong> Separates Feld unten (generiert "values" dict)<br>
        • <strong>Solution Code:</strong> Musterlösung (Function oder Code mit Result-Variablen)<br>
        • <strong>Code Template:</strong> Nur bei Vars Mode (mit #INIT START/END Block)
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
  
  // Handle Expected Type dropdown for OUTPUT tests
  container.querySelectorAll('.expected-type-select').forEach(select => {
    select.addEventListener('change', (e) => {
      const testIdx = parseInt(e.target.dataset.idx);
      dataArray[testIdx]['expected_type'] = e.target.value;
    });
  });
  
  // Handle Validation Mode dropdown for OUTPUT tests
  container.querySelectorAll('.validation-mode-select').forEach(select => {
    select.addEventListener('change', (e) => {
      const testIdx = parseInt(e.target.dataset.idx);
      dataArray[testIdx]['validation_mode'] = e.target.value;
    });
  });
  
  // Handle Case Sensitive dropdown for OUTPUT tests
  container.querySelectorAll('.case-sensitive-select').forEach(select => {
    select.addEventListener('change', (e) => {
      const testIdx = parseInt(e.target.dataset.idx);
      dataArray[testIdx]['case_sensitive'] = e.target.value === 'true';
    });
  });
  
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

  container.querySelectorAll('.variable-tolerance-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const testIdx = parseInt(e.target.dataset.idx, 10);
      const raw = String(e.target.value || '').trim();
      if (raw === '') {
        dataArray[testIdx]['tolerance_percent'] = '';
        return;
      }

      const parsed = Number(raw);
      if (Number.isFinite(parsed) && parsed >= 0) {
        dataArray[testIdx]['tolerance_percent'] = parsed;
      }
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
      
      // Clean up data based on mode
      if (e.target.value === 'function') {
        delete dataArray[idx]['inputs'];
        delete dataArray[idx]['outputs'];
        if (!dataArray[idx]['function']) dataArray[idx]['function'] = { name: '', params: [] };
      } else {
        delete dataArray[idx]['function'];
        if (!dataArray[idx]['inputs']) dataArray[idx]['inputs'] = [];
        if (!dataArray[idx]['outputs']) dataArray[idx]['outputs'] = [];
      }
      
      // Re-render to show/hide appropriate fields
      renderTestCases(dataArray, containerId);
    });
  });

  container.querySelectorAll('.intelligent-tests-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      const value = parseInt(e.target.value, 10);
      dataArray[idx]['tests'] = Number.isFinite(value) && value > 0 ? value : 4;
    });
  });

  // Handle INTELLIGENT FUNCTION MODE fields
  container.querySelectorAll('.intelligent-fn-name-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      dataArray[idx]['function']['name'] = e.target.value.trim();
    });
  });

  container.querySelectorAll('.intelligent-fn-params-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      if (!dataArray[idx]['function']) dataArray[idx]['function'] = {};
      
      // Parse comma-separated params into array
      const paramsStr = e.target.value.trim();
      const params = paramsStr ? paramsStr.split(',').map(p => p.trim()).filter(p => p) : [];
      dataArray[idx]['function']['params'] = params;
    });
  });

  // Handle INTELLIGENT VARS MODE fields
  container.querySelectorAll('.intelligent-vars-inputs-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      
      // Parse comma-separated inputs into array
      const inputsStr = e.target.value.trim();
      const inputs = inputsStr ? inputsStr.split(',').map(v => v.trim()).filter(v => v) : [];
      dataArray[idx]['inputs'] = inputs;
    });
  });

  container.querySelectorAll('.intelligent-vars-outputs-input').forEach(input => {
    input.addEventListener('input', (e) => {
      const idx = parseInt(e.target.dataset.idx);
      
      // Parse comma-separated outputs into array
      const outputsStr = e.target.value.trim();
      const outputs = outputsStr ? outputsStr.split(',').map(v => v.trim()).filter(v => v) : [];
      dataArray[idx]['outputs'] = outputs;
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

  const hasLegacyIntelligentEntries = testCases.some(tc =>
    tc && !tc.type && tc.mode && (tc.mode === 'function' || tc.mode === 'vars')
  );
  if (hasLegacyIntelligentEntries) {
    return testCases.map(tc => {
      if (!tc || tc.type || !tc.mode) return tc;
      if (tc.mode !== 'function' && tc.mode !== 'vars') return tc;
      return {
        type: 'intelligent',
        ...tc
      };
    });
  }

  // Migrate legacy CODE_CHECK structure:
  // [{ type: 'code_check', pattern: '...', hint: '...' }]
  // to
  // [{ type: 'code_check', keywords: ['...'], operator: 'AND', feedback: '...' }]
  const hasLegacyCodeCheck = testCases.some(tc =>
    tc && tc.type === 'code_check' && !Array.isArray(tc.keywords) && (tc.pattern || tc.hint || tc.description)
  );
  if (hasLegacyCodeCheck) {
    return testCases.map(tc => {
      if (!tc || tc.type !== 'code_check') return tc;

      if (Array.isArray(tc.keywords) && tc.keywords.length > 0) {
        return tc;
      }

      const legacyPattern = typeof tc.pattern === 'string' ? tc.pattern.trim() : '';
      const feedback = tc.feedback || tc.hint || tc.description || 'Code-Check';

      return {
        ...tc,
        keywords: legacyPattern ? [legacyPattern] : [],
        operator: tc.operator || 'AND',
        feedback
      };
    });
  }
  
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

function sanitizeFilename(name) {
  return name.replace(/[^a-z0-9_-]/gi, '_').toLowerCase();
}

// Auto-generate description from test cases
function generateAutoDescription(testCasesData, descFieldId) {
  if (!Array.isArray(testCasesData) || testCasesData.length === 0) {
    alert('Keine Test Cases vorhanden um Beschreibung zu generieren');
    return;
  }
  
  const descField = document.getElementById(descFieldId);
  if (!descField) return;
  
  let tableRows = '';
  const seenTypes = new Set();
  
  // Process each test case and collect table rows
  testCasesData.forEach(testCase => {
    if (testCase.type === 'function' && !seenTypes.has('function')) {
      seenTypes.add('function');
      
      // Extract function info
      const funcName = testCase.function_name || 'Funktion';
      let paramCount = 0;
      
      // Count parameters from test cases
      if (testCase.test_cases && testCase.test_cases.length > 0) {
        const firstCase = testCase.test_cases[0];
        if (firstCase.args && Array.isArray(firstCase.args)) {
          paramCount = firstCase.args.length;
        }
      }
      
      tableRows += `<tr><td>Funktionsname</td><td>${funcName}</td></tr>`;
      tableRows += `<tr><td>Parameter</td><td>${paramCount}</td></tr>`;
    }
    
    if (testCase.type === 'variable' && !seenTypes.has('variable')) {
      seenTypes.add('variable');
      
      // Extract variable info
      const initVars = testCase.init_var_names || [];
      const checkingVars = testCase.expected_var_names || [];
      
      tableRows += `<tr><td>Input-Variablen</td><td>${initVars.join(', ') || 'keine'}</td></tr>`;
      tableRows += `<tr><td>Checking</td><td>${checkingVars.join(', ') || 'keine'}</td></tr>`;
    }
    
    if (testCase.type === 'intelligent' && !seenTypes.has('intelligent')) {
      seenTypes.add('intelligent');
      
      const mode = testCase.mode || 'unknown';
      const testCount = testCase.tests || 0;
      
      if (mode === 'function' && testCase.function) {
        const funcName = testCase.function.name || 'Funktion';
        const paramCount = (testCase.function.params || []).length;
        tableRows += `<tr><td>Funktionsname</td><td>${funcName}</td></tr>`;
        tableRows += `<tr><td>Parameter</td><td>${paramCount}</td></tr>`;
      } else if (mode === 'vars') {
        // For vars mode, show inputs and checking like static variable tests
        const inputs = testCase.inputs || [];
        const checking = testCase.outputs || [];
        tableRows += `<tr><td>INPUTS erwartet</td><td>${inputs.length}</td></tr>`;
        tableRows += `<tr><td>Input-Variablen</td><td>${inputs.join(', ') || 'keine'}</td></tr>`;
        tableRows += `<tr><td>Checking</td><td>${checking.join(', ') || 'keine'}</td></tr>`;
      }
    }
    
    if (testCase.type === 'output' && !seenTypes.has('output')) {
      seenTypes.add('output');
      
      // Determine output test type
      const expectedType = testCase.expected_type || 'text';
      const validationMode = testCase.validation_mode || 'default';
      
      // Map expected_type to description
      const typeDescs = {
        'regex': 'Regex Pattern',
        'solution': 'Solution Code Output',
        'text': 'Text Pattern'
      };
      const typeDescription = typeDescs[expectedType] || expectedType;
      
      // Map validation mode to user-friendly description
      const validationModes = {
        'strict': 'Exact Match',
        'loose': 'Flexible Match',
        'contains': 'Contains Check',
        'forbidden': 'Pattern Forbidden'
      };
      const modeDescription = validationModes[validationMode] || validationMode;
      
      // Show TYPE if it's not default 'text', otherwise show validation mode
      let outputDescription = typeDescription;
      if (expectedType === 'text' && validationMode !== 'default') {
        outputDescription = modeDescription;
      }
      
      tableRows += `<tr><td>OUTPUT</td><td>${outputDescription}</td></tr>`;
    }
    
    if (testCase.type === 'code_check' && !seenTypes.has('code_check')) {
      seenTypes.add('code_check');
      
      const keywords = testCase.keywords || [];
      const forbidden = testCase.forbidden || [];
      
      tableRows += `<tr><td>Erforderliche Keywords</td><td>${keywords.join(', ') || 'keine'}</td></tr>`;
      tableRows += `<tr><td>Verbotene Keywords</td><td>${forbidden.join(', ') || 'keine'}</td></tr>`;
    }
  });
  
  // Build single table with all rows
  let description = '';
  if (tableRows) {
    description += `<div class="test-requirements-section"><h3>Test-Anforderungen</h3>`;
    description += `<table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody>`;
    description += tableRows;
    description += `</tbody></table></div>`;
  }
  
  // Insert into TinyMCE editor or textarea
  const editorId = descFieldId.replace('task-', '').replace('edit-', '');
  const tinymceEditor = tinymce.get(descFieldId);
  
  if (tinymceEditor) {
    // If TinyMCE is active, insert into editor
    const currentContent = tinymceEditor.getContent();
    const newContent = currentContent ? currentContent + description : description;
    tinymceEditor.setContent(newContent);
  } else {
    // Fallback to textarea
    if (descField.value.trim()) {
      descField.value += description;
    } else {
      descField.value = description;
    }
  }
  
  alert('✓ Beschreibung automatisch generiert!');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  initTestCasesBuilder();
  initEditTestCasesBuilder();
  
  // Initialize TinyMCE WYSIWYG Editors for description fields
  initTinyMCEEditors();
  
  // Initialize Task Form Tabs
  initTaskFormTabs('task-form');
  initTaskFormTabs('task-edit-form');
  
  // Set proper placeholders with line breaks
  const placeholders = {
    'task-template': 'Für code: def hello():\n    pass\n\nFür Code Reading: {var} wird mit Wert aus variable_overrides ersetzt',
    'task-randomizer-code': 'import random\nbinary = format(random.randint(0, 255), \'08b\')\ncelsius = random.randint(-50, 50)',
    'task-solution': 'Beispiel Random Complex:\nresult = int({binary}, 2)',
    'edit-task-template': 'Für code: def hello():\n    pass\n\nFür Code Reading: {var} wird mit Wert aus variable_overrides ersetzt',
    'edit-task-randomizer-code': 'import random\nbinary = format(random.randint(0, 255), \'08b\')\ncelsius = random.randint(-50, 50)',
    'edit-task-solution': 'Beispiel Random Complex:\nresult = int({binary}, 2)'
  };
  
  Object.entries(placeholders).forEach(([id, text]) => {
    const el = $(id);
    if (el) el.placeholder = text;
  });
  
  // Initialize Task Type Manager for new task modal
  if (window.TaskTypeManager) {
    TaskTypeManager.init('task-form');
    TaskTypeManager.init('task-edit-form');  // Also for edit modal
  }
  
  // Initialize Options Builder for NEW task modal
  if (window.OptionsBuilder) {
    window.currentOptionsBuilder = new OptionsBuilder('task-options-container');
    window.editOptionsBuilder = new OptionsBuilder('edit-task-options-container');
  }

  // Initialize variable overrides builders
  initOverridesBuilder('task');
  initOverridesBuilder('edit-task');

  // Clear options validation errors on change
  window.onOptionsBuilderChange = (taskType, options, containerId) => {
    if (containerId === 'task-options-container') {
      setChoiceValidationError('task-options-error', '');
    }
    if (containerId === 'edit-task-options-container') {
      setChoiceValidationError('edit-task-options-error', '');
    }
  };
  
  // Image Upload Handler (NEW task)
  const taskImageUpload = $('task-image-upload');
  if (taskImageUpload) {
    taskImageUpload.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      const formData = new FormData();
      formData.append('image', file);
      
      try {
        const response = await fetch('../api/admin/tasks/upload_image.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();
        
        if (data.ok) {
          $('task-image-url').value = data.image_url;
          $('task-image-preview').innerHTML = `
            <img src="${data.image_url}" style="max-width: 300px; max-height: 200px; margin-top: 8px; border: 1px solid #ddd; border-radius: 3px;" />
            <br/><button type="button" onclick="document.getElementById('task-image-url').value=''; document.getElementById('task-image-preview').innerHTML=''; document.getElementById('task-image-upload').value='';" 
              style="margin-top: 4px; font-size: 12px;">Bild entfernen</button>
          `;
        } else {
          alert('Upload failed: ' + data.error);
        }
      } catch (err) {
        alert('Upload error: ' + err.message);
      }
      
      e.target.value = ''; // Reset input
    });
  }
  
  // Image Upload Handler (EDIT task)
  const editTaskImageUpload = $('edit-task-image-upload');
  if (editTaskImageUpload) {
    editTaskImageUpload.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      const formData = new FormData();
      formData.append('image', file);
      
      try {
        const response = await fetch('../api/admin/tasks/upload_image.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();
        
        if (data.ok) {
          $('edit-task-image-url').value = data.image_url;
          $('edit-task-image-preview').innerHTML = `
            <img src="${data.image_url}" style="max-width: 300px; max-height: 200px; margin-top: 8px; border: 1px solid #ddd; border-radius: 3px;" />
            <br/><button type="button" onclick="document.getElementById('edit-task-image-url').value=''; document.getElementById('edit-task-image-preview').innerHTML=''; document.getElementById('edit-task-image-upload').value='';" 
              style="margin-top: 4px; font-size: 12px;">Bild entfernen</button>
          `;
        } else {
          alert('Upload failed: ' + data.error);
        }
      } catch (err) {
        alert('Upload error: ' + err.message);
      }
      
      e.target.value = ''; // Reset input
    });
  }
  
  // Task Type Change Handler (NEW task)
  const newTaskType = $('new-task-type');
  if (newTaskType) {
    newTaskType.addEventListener('change', () => {
      const taskType = newTaskType.value;
      
      // Update OptionsBuilder task type
      if (window.currentOptionsBuilder) {
        window.currentOptionsBuilder.setTaskType(taskType);
      }
      
      // Update legacy problem_type for compatibility
      $('task-type').value = (taskType === 'code' || taskType === 'code_ui') ? 'code_completion' : taskType;
      if (taskType === 'code_reading' || taskType === 'code_random_complex') {
        const attemptsInput = $('task-max-attempts');
        if (attemptsInput && (!attemptsInput.value || attemptsInput.value === '1')) {
          attemptsInput.value = '5';
        }
      }
      updateMaxIterationsFromBuilder('task');
      updateCodeOnlyOptionsVisibility();
    });
  }
  
  // Task Type Change Handler (EDIT task)
    const randomSnippet = "import random\nmin_val = 1\nmax_val = 10\nvalues = {\"num\": random.randint(min_val, max_val)}";
    
    // Listen for task type changes
    const newTaskTypeSelect = $('new-task-type');
    if (newTaskTypeSelect) {
      newTaskTypeSelect.addEventListener('change', updateRandomButtonVisibility);
    }
    
    const editTaskTypeSelect = $('edit-task-type');
    if (editTaskTypeSelect) {
      editTaskTypeSelect.addEventListener('change', updateRandomButtonVisibility);
    }
    
    // Handle CREATE form Random Numbers button
    const taskRandomSnippetBtn = $('task-random-snippet');
    if (taskRandomSnippetBtn) {
      taskRandomSnippetBtn.addEventListener('click', () => {
        const textarea = $('task-template');
        if (!textarea) return;
        textarea.value = randomSnippet;
        textarea.focus();
      });
    }
    
    // Handle EDIT form Random Numbers button
    const editTaskRandomSnippetBtn = $('edit-task-random-snippet');
    if (editTaskRandomSnippetBtn) {
      editTaskRandomSnippetBtn.addEventListener('click', () => {
        const textarea = $('edit-task-template');
        if (!textarea) return;
        textarea.value = randomSnippet;
        textarea.focus();
      });
    }
    
    // Initial visibility update
    updateRandomButtonVisibility();

  // ==================== RANDOMIZER CODE GENERATOR BUTTONS ====================
  
  // Helper function to generate randomizer code from test cases
  function generateRandomizerCode(testCasesArray) {
    if (!Array.isArray(testCasesArray) || testCasesArray.length === 0) {
      alert('Keine Test Cases definiert. Bitte Test Cases erstellen (Typ: Intelligent).');
      return null;
    }

    const intelligentTest = testCasesArray.find(tc => tc.type === 'intelligent');
    if (!intelligentTest) {
      alert('Kein Intelligent Test gefunden. Randomizer nur für Intelligent Tests.');
      return null;
    }

    const mode = intelligentTest.mode || 'function';
    let variables = [];

    if (mode === 'function') {
      // Function Mode: extract params from function.params array
      if (intelligentTest.function && Array.isArray(intelligentTest.function.params)) {
        variables = intelligentTest.function.params;
      }
    } else if (mode === 'vars') {
      // Vars Mode: extract inputs from inputs array
      if (Array.isArray(intelligentTest.inputs)) {
        variables = intelligentTest.inputs;
      }
    }

    variables = variables
      .map((name) => String(name || '').trim())
      .filter((name) => name !== '');

    if (variables.length === 0) {
      alert('Keine Variablen/Parameter definiert im Intelligent Test.');
      return null;
    }

    // Generate Python code (NEW SCHEMA: Direct variables, no 'values' dict)
    let code = 'import random\n';
    variables.forEach((varName) => {
      code += `${varName} = random.randint(1, 50)\n`;
    });

    return code;
  }

  // Handle CREATE form Randomizer Generator button
  const taskRandomizerGenBtn = $('task-randomizer-generator');
  if (taskRandomizerGenBtn) {
    taskRandomizerGenBtn.addEventListener('click', () => {
      const textarea = $('task-randomizer-code');
      if (!textarea) return;

      const generatedCode = generateRandomizerCode(testCasesData);
      
      if (generatedCode) {
        textarea.value = generatedCode;
        textarea.focus();
      }
    });
  }

  // Handle EDIT form Randomizer Generator button
  const editTaskRandomizerGenBtn = $('edit-task-randomizer-generator');
  if (editTaskRandomizerGenBtn) {
    editTaskRandomizerGenBtn.addEventListener('click', () => {
      const textarea = $('edit-task-randomizer-code');
      if (!textarea) return;

      const generatedCode = generateRandomizerCode(editTestCasesData);
      
      if (generatedCode) {
        textarea.value = generatedCode;
        textarea.focus();
      }
    });
  }

  // ==================== INIT-BLOCK GENERATOR BUTTONS ====================
  
  // Helper function to generate init-block from test cases
  function generateInitBlock(testCasesArray) {
    console.log('[Init-Block] generateInitBlock called with:', testCasesArray);
    
    if (!Array.isArray(testCasesArray) || testCasesArray.length === 0) {
      alert('Keine Test Cases definiert. Bitte Test Cases erstellen (Typ: Intelligent).');
      return null;
    }

    const intelligentTest = testCasesArray.find(tc => tc.type === 'intelligent');
    console.log('[Init-Block] Found intelligent test:', intelligentTest);
    
    if (!intelligentTest) {
      alert('Kein Intelligent Test gefunden. Init-Block nur für Intelligent Tests (Vars Mode).');
      return null;
    }

    const mode = intelligentTest.mode || 'function';
    console.log('[Init-Block] Mode:', mode);
    
    if (mode !== 'vars') {
      alert('Init-Block nur für Intelligent Vars Mode. Aktuell: ' + mode);
      return null;
    }

    const inputs = (intelligentTest.inputs || [])
      .map(v => String(v || '').trim())
      .filter(v => v !== '');
    const outputs = (intelligentTest.outputs || [])
      .map(v => String(v || '').trim())
      .filter(v => v !== '');
    
    console.log('[Init-Block] Inputs:', inputs, 'Outputs:', outputs);
    
    if (inputs.length === 0 && outputs.length === 0) {
      alert('Keine Inputs/Outputs definiert im Intelligent Vars Test.');
      return null;
    }

    // Generate Init-Block
    let code = '#INIT START\n';
    
    // Initialize inputs
    inputs.forEach(varName => {
      code += `${varName} = 0\n`;
    });
    
    // Initialize outputs
    outputs.forEach(varName => {
      code += `${varName} = 0\n`;
    });
    
    code += '#INIT END\n';

    return code;
  }

  // Handle CREATE form Init-Block Generator button
  const taskInitBlockGenBtn = $('task-init-block-generator');
  if (taskInitBlockGenBtn) {
    taskInitBlockGenBtn.addEventListener('click', () => {
      console.log('[Init-Block] CREATE button clicked, testCasesData:', testCasesData);
      const textarea = $('task-template');
      if (!textarea) {
        console.error('[Init-Block] textarea not found!');
        return;
      }

      const generatedBlock = generateInitBlock(testCasesData);
      
      if (generatedBlock) {
        // Insert at beginning of existing code
        const currentCode = textarea.value.trim();
        textarea.value = generatedBlock + (currentCode ? '\n\n' + currentCode : '');
        textarea.focus();
        console.log('[Init-Block] Code inserted successfully');
      }
    });
  } else {
    console.warn('[Init-Block] CREATE button not found in DOM');
  }

  // Handle EDIT form Init-Block Generator button
  const editTaskInitBlockGenBtn = $('edit-task-init-block-generator');
  if (editTaskInitBlockGenBtn) {
    editTaskInitBlockGenBtn.addEventListener('click', () => {
      console.log('[Init-Block] EDIT button clicked, editTestCasesData:', editTestCasesData);
      const textarea = $('edit-task-template');
      if (!textarea) {
        console.error('[Init-Block] EDIT textarea not found!');
        return;
      }

      const generatedBlock = generateInitBlock(editTestCasesData);
      
      if (generatedBlock) {
        // Insert at beginning of existing code
        const currentCode = textarea.value.trim();
        textarea.value = generatedBlock + (currentCode ? '\n\n' + currentCode : '');
        textarea.focus();
        console.log('[Init-Block] Code inserted successfully in EDIT form');
      }
    });
  } else {
    console.warn('[Init-Block] EDIT button not found in DOM');
  }

  // ===========================================================================

  const editTaskType = $('edit-task-type');
  if (editTaskType) {
    editTaskType.addEventListener('change', () => {
      const taskType = editTaskType.value;
      
      // Update OptionsBuilder task type
      if (window.editOptionsBuilder) {
        window.editOptionsBuilder.setTaskType(taskType);
      }
      updateMaxIterationsFromBuilder('edit-task');
      updateCodeOnlyOptionsVisibility();
    });
  }

  // Sync solution code changes to DOM immediately (for test window)
  const editTaskSolutionField = $('edit-task-solution');
  if (editTaskSolutionField) {
    editTaskSolutionField.addEventListener('input', (e) => {
      // Solution code input field is synced - changes will be picked up on form submit
      // This ensures the field is marked as "dirty" so changes aren't lost
      console.log('[Solution Code] Updated:', e.target.value.substring(0, 50) + '...');
    });
  }

  // Tasks filter
  const tasksFilterText = $('tasks-filter-text');
  const tasksFilterType = $('tasks-filter-type');
  if (tasksFilterText) {
    tasksFilterText.addEventListener('input', (e) => {
      state.tasksFilterText = e.target.value || '';
      if (state.currentAssignmentId) {
        loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
      }
    });
  }
  if (tasksFilterType) {
    tasksFilterType.addEventListener('change', (e) => {
      state.tasksFilterType = e.target.value || 'all';
      if (state.currentAssignmentId) {
        loadTasks(state.currentAssignmentId, state.currentAssignmentTitle);
      }
    });
  }
  
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

  const ticketsSearch = $('tickets-search');
  if (ticketsSearch) {
    ticketsSearch.addEventListener('input', (e) => {
      state.ticketsFilter = e.target.value || '';
      renderTickets();
    });
  }

  const ticketsRefreshBtn = $('tickets-refresh-btn');
  if (ticketsRefreshBtn) {
    ticketsRefreshBtn.addEventListener('click', async () => {
      await loadTickets();
    });
  }

  const ticketsDeleteAllBtn = $('tickets-delete-all-btn');
  if (ticketsDeleteAllBtn) {
    ticketsDeleteAllBtn.addEventListener('click', async () => {
      await deleteAllTickets();
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
        await window.taskExporter.exportSingleTask(selectedTasks[0]);
      } else {
        // Export multiple tasks
        await window.taskExporter.exportMultipleTasks(selectedTasks);
      }
    });
  }

  // ============================================================================
  // APPEND TEST INFO BUTTON: Extract function names, parameters, and variables
  // ============================================================================
  const appendTestInfoBtn = $('append-test-info-btn');
  if (appendTestInfoBtn) {
    appendTestInfoBtn.addEventListener('click', (e) => {
      e.preventDefault();
      
      const testCasesField = $('edit-task-test-cases');
      const descriptionField = $('edit-task-description');
      
      if (!testCasesField || !descriptionField) {
        alert('beschreibungsfeld oder test_cases Feld nicht gefunden');
        return;
      }
      
      const testCasesStr = testCasesField.value.trim();
      if (!testCasesStr) {
        alert('Keine test_cases vorhanden. Bitte erst test_cases definieren.');
        return;
      }
      
      try {
        const testCases = JSON.parse(testCasesStr);
        const info = extractTestInfo(testCases);
        
        if (!info.functions.length && !info.variables.length && !info.resultVars.length) {
          alert('Keine Funktionen, Variablen oder Rückgabewerte in test_cases gefunden.');
          return;
        }
        
        let appendText = '\n\n--- Test Info (automatisch generiert) ---\n';
        
        if (info.functions.length > 0) {
          appendText += '\nFunktionen:\n';
          info.functions.forEach(func => {
            appendText += `  • ${func}\n`;
          });
        }
        
        if (info.variables.length > 0) {
          appendText += '\nVariablen Init:\n';
          appendText += `  ${info.variables.join(', ')}\n`;
        }
        
        if (info.resultVars.length > 0) {
          appendText += '\nErgebnisvariablen:\n';
          appendText += `  ${info.resultVars.join(', ')}\n`;
        }
        
        descriptionField.value += appendText;
        alert('Test-Info erfolgreich angehängt!');
        
      } catch (err) {
        alert('Fehler beim Parsen von test_cases:\n' + err.message);
      }
    });
  }

  /**
   * Extract function names, parameters, and variables from test_cases
   */
  function extractTestInfo(testCases) {
    const info = {
      functions: [],
      variables: [],
      resultVars: new Set()
    };
    
    if (!Array.isArray(testCases)) {
      // Single test case object
      testCases = [testCases];
    }
    
    testCases.forEach(tc => {
      // Extract function info
      if (tc.type === 'function' && tc.func_name) {
        let funcSignature = tc.func_name;
        if (tc.args && Array.isArray(tc.args) && tc.args.length > 0) {
          const paramNames = tc.args.map(arg => {
            if (typeof arg === 'object' && arg.name) return arg.name;
            return String(arg);
          });
          funcSignature += `(${paramNames.join(', ')})`;
          
          // Track variable names from args
          paramNames.forEach(name => {
            if (name && !info.variables.includes(name)) {
              info.variables.push(name);
            }
          });
        } else {
          funcSignature += '(...)';
        }
        
        if (!info.functions.includes(funcSignature)) {
          info.functions.push(funcSignature);
        }
      }
      
      // Extract input variables from input object
      if (tc.input && typeof tc.input === 'object') {
        Object.keys(tc.input).forEach(key => {
          if (!info.variables.includes(key)) {
            info.variables.push(key);
          }
        });
      }
    });
    
    // Extract result variables from description if present
    // Common patterns: result, result1, result2, output, etc.
    const descField = $('edit-task-description');
    if (descField) {
      const descText = descField.value;
      const patterns = ['result', 'output', 'value', 'answer'];
      patterns.forEach(pattern => {
        const regex = new RegExp('\\b' + pattern + '\\d*\\b', 'gi');
        let match;
        while ((match = regex.exec(descText)) !== null) {
          info.resultVars.add(match[0].toLowerCase());
        }
      });
    }
    
    return {
      functions: info.functions,
      variables: info.variables,
      resultVars: Array.from(info.resultVars)
    };
}

// Close DOMContentLoaded event listener
});
