<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Python IDE - Login</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .auth-container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 420px;
      padding: 40px;
    }
    h1 {
      font-size: 28px;
      margin-bottom: 8px;
      color: #1f2937;
    }
    .subtitle {
      color: #6b7280;
      margin-bottom: 32px;
      font-size: 14px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
      color: #374151;
      font-size: 14px;
    }
    input {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 15px;
      transition: border-color 0.2s;
    }
    input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    button {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }
    button:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }
    .error {
      background: #fee;
      color: #c00;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      border: 1px solid #fcc;
    }
    .success {
      background: #efe;
      color: #060;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      border: 1px solid #cfc;
    }
    .switch-mode {
      text-align: center;
      margin-top: 24px;
      font-size: 14px;
      color: #6b7280;
    }
    .switch-mode a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
    }
    .switch-mode a:hover {
      text-decoration: underline;
    }
    .free-editor-link {
      text-align: center;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid #e5e7eb;
    }
    .free-editor-link a {
      color: #6b7280;
      text-decoration: none;
      font-size: 14px;
    }
    .free-editor-link a:hover {
      color: #374151;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="auth-container">
    <h1 id="page-title">Anmelden</h1>
    <p class="subtitle" id="page-subtitle">Bei Python IDE anmelden</p>
    
    <div id="message-container"></div>
    
    <form id="auth-form">
      <div class="form-group">
        <label for="email">E-Mail-Adresse</label>
        <input type="email" id="email" placeholder="deine@email.de" required>
      </div>

      <div class="form-group" id="first-name-group" style="display:none;">
        <label for="first-name">Vorname</label>
        <input type="text" id="first-name" placeholder="Max">
      </div>

      <div class="form-group" id="last-name-group" style="display:none;">
        <label for="last-name">Nachname</label>
        <input type="text" id="last-name" placeholder="Mustermann">
      </div>
      
      <div class="form-group">
        <label for="password">Passwort</label>
        <input type="password" id="password" placeholder="••••••••" required>
      </div>
      
      <button type="submit" id="submit-btn">Anmelden</button>
    </form>
    
    <div class="switch-mode">
      <span id="switch-text">Noch kein Konto?</span>
      <a href="#" id="switch-link">Registrieren</a>
    </div>
    
    <div class="free-editor-link">
      <a href="free.php">→ Zum Free Editor (ohne Anmeldung)</a>
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

    // Switch between login and register
    switchLink.addEventListener('click', (e) => {
      e.preventDefault();
      isLoginMode = !isLoginMode;
      
      if (isLoginMode) {
        pageTitle.textContent = 'Anmelden';
        pageSubtitle.textContent = 'Bei Python IDE anmelden';
        firstNameGroup.style.display = 'none';
        lastNameGroup.style.display = 'none';
        submitBtn.textContent = 'Anmelden';
        switchText.textContent = 'Noch kein Konto?';
        switchLink.textContent = 'Registrieren';
      } else {
        pageTitle.textContent = 'Registrieren';
        pageSubtitle.textContent = 'Neues Konto erstellen';
        firstNameGroup.style.display = 'block';
        lastNameGroup.style.display = 'block';
        submitBtn.textContent = 'Registrieren';
        switchText.textContent = 'Bereits registriert?';
        switchLink.textContent = 'Anmelden';
      }
      
      messageContainer.innerHTML = '';
    });

    // Handle form submission
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
          : { email, password, first_name: firstName, last_name: lastName };
        
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (data.ok) {
          messageContainer.innerHTML = `<div class="success">${isLoginMode ? 'Anmeldung' : 'Registrierung'} erfolgreich!</div>`;
          setTimeout(() => {
            window.location.href = 'dashboard.php';
          }, 500);
        } else {
          messageContainer.innerHTML = `<div class="error">${data.error || 'Ein Fehler ist aufgetreten'}</div>`;
          submitBtn.disabled = false;
          submitBtn.textContent = isLoginMode ? 'Anmelden' : 'Registrieren';
        }
      } catch (err) {
        messageContainer.innerHTML = '<div class="error">Verbindungsfehler. Bitte versuche es erneut.</div>';
        submitBtn.disabled = false;
        submitBtn.textContent = isLoginMode ? 'Anmelden' : 'Registrieren';
      }
    });
  </script>
</body>
</html>
