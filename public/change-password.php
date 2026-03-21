<?php
require_once __DIR__ . '/../api/auth/middleware.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = $user['email'] ?? 'Benutzer';
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Passwort ändern - Python IDE</title>
  <link rel="stylesheet" href="css/hspf-theme.css" />
  <style>
    body { background: var(--hspf-bg-secondary); }
    .wrap { max-width: 700px; margin: 36px auto; padding: 0 16px; }
    .card { background: var(--hspf-surface); border: 2px solid var(--hspf-border); border-radius: var(--hspf-radius-md); padding: var(--hspf-spacing-xl); box-shadow: var(--hspf-shadow); }
    .actions { display: flex; gap: 10px; margin-top: 16px; }
    .alert { margin-top: 12px; }
  </style>
</head>
<body>
<?php
  $pageTitle = 'Passwort ändern';
  $showUser = true;
  $userInfo = ['name' => $displayName, 'role' => $user['role'] ?? 'user'];
  $headerActions = '<a class="hspf-btn hspf-btn-ghost" href="dashboard.php">← Dashboard</a>';
  include(__DIR__ . '/../components/header.php');
?>

<div class="wrap">
  <div class="card">
    <h2 style="margin-top:0;">Eigenes Passwort ändern</h2>
    <p style="color:var(--hspf-text-secondary); margin-bottom:18px;">Zur Sicherheit bitte das aktuelle Passwort eingeben.</p>

    <form id="change-password-form">
      <div class="hspf-form-group">
        <label class="hspf-label" for="current-password">Aktuelles Passwort</label>
        <input class="hspf-input" type="password" id="current-password" required />
      </div>

      <div class="hspf-form-group">
        <label class="hspf-label" for="new-password">Neues Passwort</label>
        <input class="hspf-input" type="password" id="new-password" minlength="6" required />
      </div>

      <div class="hspf-form-group">
        <label class="hspf-label" for="confirm-password">Neues Passwort wiederholen</label>
        <input class="hspf-input" type="password" id="confirm-password" minlength="6" required />
      </div>

      <div class="actions">
        <button class="hspf-btn hspf-btn-primary" type="submit" id="save-btn">Passwort speichern</button>
        <a class="hspf-btn" href="dashboard.php">Abbrechen</a>
      </div>
      <div id="msg" class="alert"></div>
    </form>
  </div>
</div>

<script>
  const form = document.getElementById('change-password-form');
  const msg = document.getElementById('msg');
  const saveBtn = document.getElementById('save-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    msg.innerHTML = '';

    if (newPassword !== confirmPassword) {
      msg.innerHTML = '<div class="hspf-alert hspf-alert-error">Die neuen Passwörter stimmen nicht überein.</div>';
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Speichere...';

    try {
      const res = await fetch('../api/auth/change-password.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          current_password: currentPassword,
          new_password: newPassword
        })
      });

      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Fehler beim Ändern des Passworts');
      }

      form.reset();
      msg.innerHTML = '<div class="hspf-alert hspf-alert-success">Passwort erfolgreich geändert.</div>';
    } catch (err) {
      msg.innerHTML = `<div class="hspf-alert hspf-alert-error">${err.message}</div>`;
    } finally {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Passwort speichern';
    }
  });
</script>
</body>
</html>
