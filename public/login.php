<!DOCTYPE html>
<html lang='de'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Login - HS PF Python IDE</title>
  <link rel='stylesheet' href='css/hspf-theme.css'>
  <style>
    body {
      background: linear-gradient(135deg, 
        rgba(255, 190, 49, 0.03) 0%, 
        rgba(125, 115, 105, 0.05) 100%
      ),
      var(--hspf-bg);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .auth-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--hspf-spacing-2xl) var(--hspf-spacing-lg);
    }

    .auth-container {
      background: var(--hspf-surface);
      border-radius: var(--hspf-radius-lg);
      box-shadow: var(--hspf-shadow-xl);
      width: 100%;
      max-width: 450px;
      padding: var(--hspf-spacing-2xl);
      border: 1px solid var(--hspf-border);
    }

    .auth-header {
      text-align: center;
      margin-bottom: var(--hspf-spacing-xl);
    }

    .auth-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--hspf-spacing-md);
      margin-bottom: var(--hspf-spacing-lg);
    }

    .auth-logo img {
      height: 48px;
      width: auto;
    }

    .auth-logo-text {
      font-size: 24px;
      font-weight: 300;
      color: var(--hspf-primary);
    }

    .auth-title {
      font-size: 28px;
      font-weight: 300;
      color: var(--hspf-text-primary);
      margin-bottom: var(--hspf-spacing-xs);
    }

    .auth-subtitle {
      font-size: 15px;
      color: var(--hspf-text-secondary);
    }

    .switch-mode {
      text-align: center;
      margin-top: var(--hspf-spacing-lg);
      padding-top: var(--hspf-spacing-lg);
      border-top: 1px solid var(--hspf-border);
      font-size: 14px;
      color: var(--hspf-text-secondary);
    }

    .switch-mode a {
      color: var(--hspf-accent);
      text-decoration: none;
      font-weight: 600;
      transition: var(--hspf-transition);
    }

    .switch-mode a:hover {
      color: var(--hspf-accent-hover);
      text-decoration: underline;
    }

    .free-editor-link {
      text-align: center;
      margin-top: var(--hspf-spacing-md);
      padding-top: var(--hspf-spacing-md);
    }

    .free-editor-link a {
      color: var(--hspf-text-muted);
      text-decoration: none;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: var(--hspf-transition);
    }

    .free-editor-link a:hover {
      color: var(--hspf-text-secondary);
    }

    @media (max-width: 480px) {
      .auth-container {
        padding: var(--hspf-spacing-lg);
      }
      
      .auth-title {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <?php 
    $pageTitle = 'Python IDE';
    $showUser = false;
    include(__DIR__ . '/../components/header.php');  
  ?>

  <div class='auth-wrapper'>
    <div class='auth-container'>
      <div class='auth-header'>
        <div class='auth-logo'>
          <span class='auth-logo-text'>HS PF</span>
          <img src='assets/logo.svg' alt='HS Pforzheim'>
        </div>
        <h1 class='auth-title' id='page-title'>Anmelden</h1>
        <p class='auth-subtitle' id='page-subtitle'>Bei der Python IDE anmelden</p>
      </div>
      
      <div id='message-container'></div>
      
      <form id='auth-form'>
        <div class='hspf-form-group'>
          <label class='hspf-label' for='email'>E-Mail-Adresse</label>
          <input type='email' id='email' class='hspf-input' placeholder='deine@email.de' required>
        </div>

        <div class='hspf-form-group' id='first-name-group' style='display:none;'>
          <label class='hspf-label' for='first-name'>Vorname</label>
          <input type='text' id='first-name' class='hspf-input' placeholder='Max'>
        </div>

        <div class='hspf-form-group' id='last-name-group' style='display:none;'>
          <label class='hspf-label' for='last-name'>Nachname</label>
          <input type='text' id='last-name' class='hspf-input' placeholder='Mustermann'>
        </div>
        
        <div class='hspf-form-group'>
          <label class='hspf-label' for='password'>Passwort</label>
          <input type='password' id='password' class='hspf-input' placeholder='••••••••' required>
        </div>
        
        <button type='submit' id='submit-btn' class='hspf-btn hspf-btn-primary' style='width: 100%;'>
          Anmelden
        </button>
      </form>
      
      <div class='switch-mode'>
        <span id='switch-text'>Noch kein Konto?</span>
        <a href='#' id='switch-link'>Registrieren</a>
      </div>
      
      <div class='free-editor-link'>
        <a href='free.php'>→ Zum Free Editor (ohne Anmeldung)</a>
      </div>
    </div>
  </div>

  <script>
    let isLoginMode = true;
    const form = document.getElementById('auth-form');
    const pageTitle = document.getElementById('page-title');
    const pageSubtitle = document.getElementById('page-subtitle');
    const firstNameGroup = document.getElementById('first-name-group');
    const lastNameGroup = document.getElementById('last-name-group');
    const submitBtn = document.getElementById('submit-btn');
    const switchText = document.getElementById('switch-text');
    const switchLink = document.getElementById('switch-link');
    const messageContainer = document.getElementById('message-container');
    const inviteToken = new URLSearchParams(window.location.search).get('invite') || '';

    function setMode(loginMode) {
      isLoginMode = loginMode;
      
      if (isLoginMode) {
        pageTitle.textContent = 'Anmelden';
        pageSubtitle.textContent = 'Bei der Python IDE anmelden';
        firstNameGroup.style.display = 'none';
        lastNameGroup.style.display = 'none';
        submitBtn.textContent = 'Anmelden';
        switchText.textContent = 'Noch kein Konto?';
        switchLink.textContent = 'Registrieren';
      } else {
        pageTitle.textContent = 'Registrieren';
        pageSubtitle.textContent = inviteToken
          ? 'Einladung erkannt: Registrierung für ein Team'
          : 'Neues Konto erstellen';
        firstNameGroup.style.display = 'block';
        lastNameGroup.style.display = 'block';
        submitBtn.textContent = 'Registrieren';
        switchText.textContent = 'Bereits registriert?';
        switchLink.textContent = 'Anmelden';
      }

      messageContainer.innerHTML = '';
    }

    switchLink.addEventListener('click', (e) => {
      e.preventDefault();
      setMode(!isLoginMode);
    });

    if (inviteToken) {
      setMode(false);
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const firstName = document.getElementById('first-name').value.trim();
      const lastName = document.getElementById('last-name').value.trim();
      
      messageContainer.innerHTML = '';
      submitBtn.disabled = true;
      submitBtn.textContent = isLoginMode ? 'Anmelden...' : 'Registrieren...';
      
      try {
        const endpoint = isLoginMode ? '../api/auth/login.php' : '../api/auth/register.php';
        const body = isLoginMode 
          ? { email, password }
          : { email, password, first_name: firstName, last_name: lastName, invite_token: inviteToken || undefined };
        
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (data.ok) {
          messageContainer.innerHTML = `<div class='hspf-alert hspf-alert-success'>${isLoginMode ? 'Anmeldung' : 'Registrierung'} erfolgreich!</div>`;
          setTimeout(() => {
            window.location.href = 'dashboard.php';
          }, 500);
        } else {
          messageContainer.innerHTML = `<div class='hspf-alert hspf-alert-error'>${data.error || 'Ein Fehler ist aufgetreten'}</div>`;
          submitBtn.disabled = false;
          submitBtn.textContent = isLoginMode ? 'Anmelden' : 'Registrieren';
        }
      } catch (err) {
        messageContainer.innerHTML = '<div class="hspf-alert hspf-alert-error">Verbindungsfehler. Bitte versuche es erneut.</div>';
        submitBtn.disabled = false;
        submitBtn.textContent = isLoginMode ? 'Anmelden' : 'Registrieren';
      }
    });
  </script>
</body>
</html>
