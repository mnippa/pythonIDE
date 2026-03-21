# Deployment auf Debian (GitHub-Repo, nur geaenderte Dateien)

## Ziel
Sicheres Update auf dem Liveserver mit SSH-Zugriff, ohne sensible lokale Daten zu ueberschreiben.

## Kurzfazit
Ja, ein Linux-Deploy-Script reicht in vielen Faellen aus.

- Nur geaenderte Dateien aktualisieren: ja (Git/rsync arbeiten delta-basiert)
- Lokale Serverdaten behalten: ja (durch Struktur + Excludes)
- Direkter Deploy aus der Web-App: moeglich, aber riskanter

## Empfohlene Strategie
1. Deploy ueber Shell-Script mit dediziertem Deploy-User.
2. Keine Secrets im Repo (z. B. `.env`, produktive DB-Config).
3. Schreibrechte fuer Webserver nur auf Runtime-Ordner (`storage`, `cache`, `uploads`).
4. Code-Verzeichnisse fuer den Webserver nur read-only.

## Verzeichnis-Prinzip (einfach)
- App-Code: `/var/www/pythonIDE`
- Lokale Konfig (nicht im Repo):
  - `.env`
  - ggf. `config/local.php`
- Laufzeitdaten:
  - `storage/`
  - `public/uploads/`

## Minimales Deploy-Script (Git-basiert)
```bash
#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/pythonIDE"
BRANCH="main"

cd "$APP_DIR"

# Optional: lokale Dateien sichern
cp -a .env /tmp/pythonide.env.bak 2>/dev/null || true

# Code aktualisieren (nur geaenderte Dateien)
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

# Lokale Dateien zurueck
if [ -f /tmp/pythonide.env.bak ]; then
  cp -a /tmp/pythonide.env.bak .env
fi

# Rechte
chown -R deploy:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/public/uploads" 2>/dev/null || true

# Optional: OPcache reset
php "$APP_DIR/public/clear_cache.php" >/dev/null 2>&1 || true

echo "Deploy done"
```

## Alternative: rsync statt Git im Ziel
Wenn du aus einem Build/Workspace deployen willst:

- `rsync -az --delete` auf den Server
- Excludes fuer lokale Daten (`.env`, `storage/`, `uploads/`, etc.)
- Vorteil: sehr klar kontrollierbar, ebenfalls nur Deltas

## Sicherheit: Deploy "aus der Software raus"
Direkt aus PHP (Webprozess) zu deployen ist nicht empfohlen:

- Webserver braucht sonst zu viele Schreibrechte auf Code
- Hoeheres Risiko bei Sicherheitsluecken
- Deploy-Secrets liegen in der App

Besser:
- App darf hoechstens einen externen Deploy-Prozess triggern
- Deploy selbst laeuft als separater User/Service (SSH/CI)

## Debian-Hardening (kurz)
- Eigener User `deploy` (kein root fuer Deploy)
- SSH-Key nur fuer Deploy-User
- `sudo` nur fuer explizite Kommandos/Skripte
- Optional Lockfile im Script gegen parallele Deploys

## Naechste Ausbaustufe (spaeter)
1. Script mit Lockfile + Healthcheck + Rollback
2. Optional GitHub Actions `workflow_dispatch` als Trigger
3. Atomic Deploy mit `releases/` + `current` Symlink

---
Stand: 2026-03-21
