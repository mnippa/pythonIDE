# UI Smoke Test - Quick Start

## Zweck
Automatisierte Smoke-Tests für alle UI-Seiten (Status, Redirects, Titel, Content-Marker).  
Läuft in <30 Sekunden und gibt sofort Pass/Fail zurück.

## Voraussetzungen
- Apache/XAMPP läuft
- pythonIDE unter http://localhost/pythonIDE erreichbar
- PowerShell (Windows)

## Ausführung

```powershell
cd c:\xampp\htdocs\pythonIDE\scripts
.\ui-smoke-test.ps1
```

## Was wird getestet?

### 1. Guest Access (11 Tests)
- Öffentliche Seiten erreichbar (200): index, login, register, free, share
- Geschützte Seiten redirecten (302): dashboard, assignments, projects, admin, evaluation, editor

### 2. Authenticated User (6 Tests)
- Erstellt automatisch Test-User via API
- Prüft Zugriff auf: dashboard, assignments, projects
- Prüft Zugriffssperre für: admin, evaluation (403 Forbidden)
- Prüft Editor-Redirect (302 ohne project_id)

### 3. Content Integrity (5 Tests)
- Login-Form hat E-Mail/Passwort-Felder
- Index-Seite hat Navigation
- Dashboard/Projects haben erwartete UI-Elemente

## Ergebnis

### Erfolg (Exit Code 0)
```
========================================
SMOKE TEST RESULTS
========================================
Total Tests: 22
Passed: 22
Failed: 0
Success Rate: 100%
========================================

ALL SMOKE TESTS PASSED - UI is healthy!
```

### Fehler (Exit Code 1)
```
Total Tests: 22
Passed: 18
Failed: 4
Success Rate: 81.82%

SOME TESTS FAILED - Review output above
```

## Wann ausführen?

- **Pflicht:** Nach jedem Deployment (CI/CD-Pipeline)
- **Empfohlen:** Vor jedem Git-Push mit UI-Änderungen
- **Optional:** Täglich im Beta-Test

## Limitierungen

Was NICHT getestet wird (→ manuelle Tests):
- Monaco-Editor Interaktionen (Tippen, Autocomplete)
- Unsaved-Changes-Dialoge (Save/Discard/Cancel)
- Dateibaum-UX (Expand/Collapse)
- Pyodide-Code-Ausführung
- Button-Enable/Disable-Logik

Siehe: `UI-TEST-MANUAL-CHECKLIST.md` für manuelle Tests.

## Troubleshooting

**Fehler: "Der Remoteserver hat einen Fehler zurückgegeben"**
→ Apache läuft nicht. Starte XAMPP und versuche erneut.

**Fehler: "Test user creation failed"**
→ Datenbank nicht erreichbar oder Registrierung blockiert. Prüfe config/database.php.

**Fehler: "Content marker not found"**
→ HTML-Struktur hat sich geändert. Passe Marker in Skript an (Funktion `Test-ContentMarker`).

## Integration in CI/CD

```yaml
# .github/workflows/test.yml (Beispiel)
test-ui:
  runs-on: windows-latest
  steps:
    - name: Start Apache
      run: net start Apache2.4
    - name: Run UI Smoke Tests
      run: |
        cd c:\xampp\htdocs\pythonIDE\scripts
        .\ui-smoke-test.ps1
      shell: powershell
```

## Reporting

Bei Fehler: Siehe Console-Output für Details zu jedem fehlgeschlagenen Test.

**Beispiel:**
```
[FAIL] Guest: dashboard.php (redirect)
       Expected redirect: login.php, Got: (none)
       Expected status: 302, Got: 200
```

→ Zeigt an: dashboard.php gibt kein Redirect zurück (Auth-Prüfung fehlt).

---

**Skript-Pfad:** `c:\xampp\htdocs\pythonIDE\scripts\ui-smoke-test.ps1`  
**Dokumentation:** `docs/UI-TEST-PLAN.md`  
**Manuelle Tests:** `docs/UI-TEST-MANUAL-CHECKLIST.md`
