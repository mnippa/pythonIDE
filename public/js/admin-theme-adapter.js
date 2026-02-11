// Admin Dashboard - HS PF Theme Adapter
// This script updates the modal handling to work with the new CSS classes

(function() {
  // Helper: Update modal visibility using classList instead of style.display
  const originalOpenModal = window.openAssignmentModal;
  const originalCloseModal = window.closeAssignmentModal;
  const originalOpenTaskModal = window.openNewTaskModal;
  const originalCloseTaskModal = window.closeNewTaskModal;

  // Override modal functions
  window.openAssignmentModal = function() {
    if (originalOpenModal) originalOpenModal();
    document.getElementById('assignment-modal').classList.add('active');
  };

  window.closeAssignmentModal = function() {
    document.getElementById('assignment-modal').classList.remove('active');
    if (originalCloseModal) originalCloseModal();
  };

  window.openNewTaskModal = function() {
    if (originalOpenTaskModal) originalOpenTaskModal();
    document.getElementById('task-create-modal').classList.add('active');
  };

  window.closeNewTaskModal = function() {
    document.getElementById('task-create-modal').classList.remove('active');
    if (originalCloseTaskModal) originalCloseTaskModal();
  };

  function openEditTaskModal() {
    document.getElementById('task-modal').classList.add('active');
  }

  function closeEditTaskModal() {
    document.getElementById('task-modal').classList.remove('active');
  }

  // Update button classes dynamically after DOM loads
  document.addEventListener('DOMContentLoaded', function() {
    // Convert old btn classes to hspf-btn
    setInterval(() => {
      document.querySelectorAll('.btn:not(.hspf-btn)').forEach(btn => {
        if (btn.classList.contains('primary')) {
          btn.className = btn.className.replace('btn', 'hspf-btn').replace('primary', 'hspf-btn-primary');
        } else if (btn.classList.contains('warn')) {
          btn.className = btn.className.replace('btn', 'hspf-btn').replace('warn', 'hspf-btn-danger');
        } else if (btn.classList.contains('ghost')) {
          btn.className = btn.className.replace('btn', 'hspf-btn').replace('ghost', 'hspf-btn-ghost');
        } else if (!btn.classList.contains('hspf-btn')) {
          btn.classList.add('hspf-btn');
          btn.classList.remove('btn');
        }
      });

      // Update status badges
      document.querySelectorAll('.status:not(.status-badge)').forEach(badge => {
        badge.classList.add('status-badge');
        if (badge.classList.contains('arch')) {
          badge.classList.add('archived');
          badge.classList.remove('arch');
        }
      });

      // Update badges
      document.querySelectorAll('.badge:not(.hspf-badge)').forEach(badge => {
        badge.classList.add('hspf-badge', 'hspf-badge-primary');
      });
    }, 100);
  });
})();
