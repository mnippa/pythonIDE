# Delta-Pakete fuer Beta und Live

Delta-Pakete enthalten vollstaendige neue Versionen der geaenderten Dateien, kein zeilenbasiertes Patchformat. `manifest.json` beschreibt Pfad, Dateigroesse und SHA-256-Pruefsumme.

## Paket erzeugen

Alle Aenderungen gegen den aktuellen Git-Stand:

```powershell
.\scripts\create_delta_package.ps1 -Mode Git
```

Alle deploybaren Dateien, die in den letzten 120 Minuten geaendert wurden:

```powershell
.\scripts\create_delta_package.ps1 -Mode Recent -SinceMinutes 120
```

Eine bekannte Liste aktuell bearbeiteter Dateien ohne Git-Vergleich:

```powershell
.\scripts\create_delta_package.ps1 -Mode Paths -Paths `
  'public/dashboard.php', `
  'public/js/assignments.js'
```

Die ZIP-Dateien werden unter `deploy-packages/` erzeugt. Der Ordner wird nicht in Git aufgenommen.

## Paket einspielen

1. Fuer Live-Hotfixes das Live-Admin-Dashboard oeffnen. Die Beta-Instanz darf keine Live-Pakete anwenden.
2. Unter **Meine Projekte** die Karte **Delta-Paket einspielen** verwenden.
3. ZIP und Ziel auswaehlen.
4. Zuerst den aktivierten Dry-Run ausfuehren.
5. Dry-Run ausschalten und nach Beta einspielen.
6. Fuer Live zusaetzlich exakt `APPLY LIVE HOTFIX` eingeben.

Vor jedem echten Schreibvorgang wird unter `storage/deploy-backups/` ein Backup der ersetzten Dateien mit Manifest erstellt. Bei einem Fehler waehrend des Deployments werden bereits angefasste Dateien automatisch zurueckgerollt.

## Schutzregeln

Pakete duerfen nur Anwendungscode aus `api/`, `public/`, `config/` und `components/` sowie ausgewaehlte Root-Dateien enthalten. Folgende Inhalte werden unter anderem blockiert:

- `config/database.php` und Datenbankvarianten
- lokale `*.local.php`-Konfigurationen
- `.env`-Dateien
- `storage/`
- Pfade ausserhalb des Anwendungsverzeichnisses
- Symlink-Zielpfade

Der erste Rollout der Delta-Funktion selbst erfolgt weiterhin ueber den normalen Beta-Copy mit anschliessendem **Hydrate Beta**. Danach koennen weitere Aenderungen direkt als Delta-Paket eingespielt werden.