<?php
$token = trim((string)($_GET['token'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Passwort zurücksetzen - Python IDE</title>
  <link rel="stylesheet" href="css/hspf-theme.css" />
  <style>
    body { background: var(--hspf-bg-secondary); min-height: 100vh; display: flex; flex-direction: column; }
    .wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { width: 100%; max-width: 520px; background: var(--hspf-surface); border: 2px solid var(--hspf-border); border-radius: var(--hspf-radius-md); box-shadow: var(--hspf-shadow-lg); padding: var(--hspf-spacing-xl); }
    .alert { margin-top: 12px; }
  </style>
</head>
<body>
<?php
  $pageTitle = 'Passwort zurücksetzen';
  $showUser = false;
  include(__DIR__ . '/../components/header.php');
?>

<div class="wrap">
  <div class="card">
    <h2 style="margin-top:0;">Neues Passwort setzen</h2>
    <p style="color:var(--hspf-text-secondary); margin-bottom:18px;">Bitte ein neues Passwort für diesen Account vergeben.</p>

    <form id="reset-form">
      <input type="hidden" id="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" />

      <div class="hspf-form-group">
        <label class="hspf-label" for="new-password">Neues Passwort</label>
        <input class="hspf-input" type="password" id="new-password" minlength="6" required />
      </div>

      <div class="hspf-form-group">
        <label class="hspf-label" for="confirm-password">Neues Passwort wiederholen</label>
        <input class="hspf-input" type="password" id="confirm-password" minlength="6" required />
      </div>

      <button class="hspf-btn hspf-btn-primary" type="submit" id="submit-btn" style="width:100%;">Passwort setzen</button>
      <div id="msg" class="alert"></div>
    </form>
  </div>
</div>

<script>
  const form = document.getElementById('reset-form');
  const msg = document.getElementById('msg');
  const submitBtn = document.getElementById('submit-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const token = document.getElementById('token').value.trim();
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    if (!token) {
      msg.innerHTML = '<div class="hspf-alert hspf-alert-error">Ungültiger oder fehlender Reset-Link.</div>';
      return;
    }

    if (newPassword !== confirmPassword) {
      msg.innerHTML = '<div class="hspf-alert hspf-alert-error">Die neuen Passwörter stimmen nicht überein.</div>';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Speichere...';

    try {
      const res = await fetch('../api/auth/reset-password.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, new_password: newPassword })
      });

      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Passwort konnte nicht zurückgesetzt werden');
      }

      form.reset();
      msg.innerHTML = '<div class="hspf-alert hspf-alert-success">Passwort wurde gesetzt. Du kannst dich jetzt anmelden.</div>';
      setTimeout(() => {
        window.location.href = 'login.php';
      }, 900);
    } catch (err) {
      msg.innerHTML = `<div class="hspf-alert hspf-alert-error">${err.message}</div>`;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Passwort setzen';
    }
  });
</script>
</body>
</html>
