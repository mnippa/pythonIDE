# PHP-Bridge Konfiguration für GitHub Copilot

Um sicherzustellen, dass PHP-Befehle immer über eine Web-Bridge (localhost) statt via `php.exe` ausgeführt werden:

## Lösung 1: VS Code Settings (empfohlen)

Erstelle oder aktualisiere `.vscode/settings.json`:

```json
{
  "php-cli-bridge.enabled": true,
  "php-cli-bridge.localServerUrl": "http://localhost",
  "php-cli-bridge.useLocalServer": true,
  "php.exec": "/usr/bin/env php",
  "terminal.integrated.defaultProfile.windows": "PowerShell",
  "terminal.integrated.profiles.windows": {
    "PowerShell": {
      "source": "PowerShell",
      "icon": "terminal-powershell"
    }
  }
}
```

## Lösung 2: Environment Variable setzen

Stelle sicher, dass diese Variable in PowerShell gesetzt ist:

```powershell
// In PS-Profil hinzufügen (~\Documents\PowerShell\profile.ps1)
[System.Environment]::SetEnvironmentVariable('PHP_USE_BRIDGE', 'true', 'User')
```

## Lösung 3: Benutzerdefinierte Funktion im PS-Profil

```powershell
function php-bridge {
    param([string]$script)
    $encodedScript = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($script))
    $response = Invoke-WebRequest -Uri "http://localhost:8000/run.php?code=$encodedScript" -UseBasicParsing
    Write-Output $response.Content
}

Set-Alias -Name php -Value php-bridge
```

## Lösung 4: Instruktion für Copilot

Du kannst mir folgende Instruktion geben:

**"Verwende immer die PHP-HTTP-Bridge für PHP-Befehle, niemals php.exe direkt"**

Oder erstelle eine `.copilot-instructions.md`:

```markdown
# Copilot Instruktionen

- Verwende IMMER eine PHP-Bridge statt php.exe
- PHP-Befehle sollten über HTTP (localhost) ausgeführt werden
- Verwende curl oder Invoke-WebRequest für PHP-API-Tests
- Nur für Test-Screenshots: Verwende php.exe Fallback
```

## Beste Methode für dieses Projekt

Da du xampp läufst, empfehle ich:

```bash
# In PowerShell-Profil (~\Documents\PowerShell\profile.ps1)

# PHP über XAMPP-HTTP
function php-bridge {
    param([string]$filePath)
    if (Test-Path $filePath) {
        curl.exe -s "http://localhost/run-php.php?file=$filePath"
    } else {
        Write-Error "Datei nicht gefunden: $filePath"
    }
}

Set-Alias -Name php -Value php-bridge -Force -Option AllScope
```

Dann erstelle in `public/run-php.php`:

```php
<?php
$file = $_GET['file'] ?? null;
if ($file && file_exists($file)) {
    include $file;
} else {
    echo "Error: File not found\n";
}
?>
```

Frage einfach nach, wenn du das einrichten möchtest!
