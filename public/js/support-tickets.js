/**
 * Support Ticket Management Utilities
 * Handles ticket creation, display, and deletion
 */

// Modal Styles (inject if not present)
function ensureModalStyles() {
    if (document.getElementById('ticket-modal-styles')) return;
    const style = document.createElement('style');
    style.id = 'ticket-modal-styles';
    style.innerHTML = `
        .ticket-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .ticket-modal-overlay .ticket-modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
        }
        .ticket-modal-overlay .ticket-modal-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ticket-modal-overlay .ticket-modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .ticket-modal-overlay .ticket-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .ticket-modal-overlay .ticket-close-btn:hover {
            color: #1f2937;
        }
        .ticket-modal-overlay .ticket-modal-body {
            padding: 20px;
        }
        .ticket-modal-overlay .ticket-btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ticket-modal-overlay .ticket-btn-primary {
            background: #0ea5e9;
            color: white;
        }
        .ticket-modal-overlay .ticket-btn-primary:hover {
            background: #0284c7;
        }
        .ticket-modal-overlay .ticket-btn-primary:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Open ticket creation modal for student
 */
function openCreateTicketModal(assignmentId) {
    ensureModalStyles();
    const modal = document.createElement('div');
    modal.className = 'ticket-modal-overlay';
    modal.id = 'createTicketModal';
    modal.innerHTML = `
        <div class="ticket-modal-content" style="max-width: 500px;">
            <div class="ticket-modal-header">
                <h2>Support Ticket erstellen</h2>
                <button class="ticket-close-btn" onclick="document.getElementById('createTicketModal').remove()">&times;</button>
            </div>
            <div class="ticket-modal-body">
                <p>Mit diesem Ticket kannst du deinen Code einem Admin zeigen.</p>
                <p style="font-size: 13px; color: #666; margin: 15px 0;">
                    Der Admin erhält einen Link zu deinem aktuellen Testview-Status. Das Ticket wird nach dem Besuch automatisch gelöscht.
                </p>
                <div id="ticketMessage" style="display: none; padding: 15px; background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 4px; margin: 15px 0;">
                    <strong style="color: #0369a1;">✓ Ticket erstellt!</strong>
                    <div id="tokenDisplay" style="margin-top: 10px; word-break: break-all; font-family: monospace; font-size: 12px; background: white; padding: 8px; border-radius: 3px; border: 1px solid #d1d5db;">
                    </div>
                    <button class="ticket-btn ticket-btn-primary" style="margin-top: 10px; width: 100%;" onclick="copyToClipboard(document.getElementById('tokenDisplay').textContent)">
                        Token kopieren
                    </button>
                </div>
                <div id="ticketForm" style="display: block;">
                    <p style="margin-top: 0;"><strong>Assignment:</strong> <span id="assignmentName"></span></p>
                    <div style="margin: 15px 0;">
                        <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px;">
                            Kurzes Problem? (Optional)
                        </label>
                        <textarea id="problemText" placeholder="Z.B. 'Syntax Error bei Zeile 5' oder 'Verstehe nicht wie...'"
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; font-family: system-ui; resize: vertical; min-height: 70px; max-height: 150px; box-sizing: border-box;"></textarea>
                        <p style="font-size: 12px; color: #999; margin: 6px 0 0 0;">Max. 200 Zeichen</p>
                    </div>
                    <button class="ticket-btn ticket-btn-primary" onclick="createSupportTicket(${assignmentId})" style="width: 100%;">
                        Ticket generieren
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.remove();
    });
    
    // Limit textarea to 200 chars
    setTimeout(() => {
        const ta = document.getElementById('problemText');
        if (ta) {
            ta.addEventListener('input', function() {
                if (this.value.length > 200) {
                    this.value = this.value.substring(0, 200);
                }
            });
        }
    }, 0);
    
    // Load assignment title
    fetch(`api/assignments/details.php?id=${assignmentId}`)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('assignmentName').textContent = data.assignment.title;
            }
        })
        .catch(err => console.error('Failed to load assignment:', err));
}

/**
 * Create a support ticket via API
 */
function createSupportTicket(assignmentId) {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Wird erstellt...';
    
    const problemText = document.getElementById('problemText')?.value?.trim() || '';
    
    const formData = new FormData();
    formData.append('assignment_id', assignmentId);
    if (problemText) {
        formData.append('description', problemText);
    }
    
    fetch('api/support_tickets/create.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Show success message with token
            document.getElementById('ticketForm').style.display = 'none';
            document.getElementById('ticketMessage').style.display = 'block';
            document.getElementById('tokenDisplay').textContent = data.token;
        } else {
            alert('Fehler: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = 'Ticket generieren';
        }
    })
    .catch(err => {
        console.error('Error creating ticket:', err);
        alert('Fehler beim Erstellen des Tickets');
        btn.disabled = false;
        btn.textContent = 'Ticket generieren';
    });
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('✓ In Zwischenablage kopiert', 'success');
        });
    } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('✓ In Zwischenablage kopiert', 'success');
    }
}

/**
 * Show temporary notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 16px;
        background: ${type === 'success' ? '#10b981' : '#3b82f6'};
        color: white;
        border-radius: 4px;
        font-size: 14px;
        z-index: 10000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
