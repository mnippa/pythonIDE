# Delta-Pakete fuer Beta und Live

Delta-Pakete enthalten vollstaendige neue Versionen der geaenderten Dateien, kein zeilenbasiertes Patchformat. `manifest.json` beschreibt Pfad, Dateigroesse und SHA-256-Pruefsumme.

## Paket erzeugen

Vollstaendiges Manifest / kompletten Soll-Stand gegen Live oder Lokal vergleichen:

```powershell
.\scripts\create_delta_package.ps1 -Mode Full
```

Entwicklungs-Scope: nur die realistisch bearbeiteten Bereiche, ohne externer Bibliotheken und Build-Artefakte:

```powershell
.\scripts\create_delta_package.ps1 -Mode Development
```

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

### Scope-Logik

- `Full`: alles deploybare Material
- `Development`: alle normalen Projekt- und Programmierbereiche, aber ohne externe Bibliotheken / Build-Artefakte / bekannte "nie anzufassende" Bereiche
- `Paths`: manuell gewaehlte Dateien oder Ordner
- `Git`: Git-Stand als Sollbasis
- `Recent`: nur geaenderte Dateien der letzten Minuten

Der Manifest-Block merkt sich dabei den gewaehlten Scope in `scope`, damit die reine Manifest-Pruefung und die ZIP-Variante denselben Grundsatz nutzen.

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

Hinweis: Da `config/database.php` nie mit ausgeliefert wird, darf dort kein neuer Anwendungscode (Funktionen, Helper) landen, von dem andere Dateien abhaengen. Solcher Code gehoert in eigene Dateien wie `config/session.php` (`configureApplicationSession()`), die direkt eingebunden werden.

`config/session.php` muss vor dem ersten Aufruf von `startApplicationSession()` geladen sein. Eine erst danach eingebundene Auth-Middleware reicht nicht aus. Alle Session-Einstiege verwenden diesen zentralen Start statt direkter `session_start()`-Aufrufe. Er setzt die PHP-Laufzeit auf 7200 Sekunden, prueft bei authentifizierten Anfragen das Inaktivitaetslimit und erneuert Zeitstempel sowie Cookie um 120 Minuten. Bestehende Sitzungen ohne Zeitstempel werden beim ersten Aufruf uebernommen. Login und Registrierung setzen den ersten Aktivitaetszeitstempel.

`ini_set('session.gc_maxlifetime', ...)` steuert nur die PHP-Bereinigung dieses Requests. Serverweite Cronjobs oder andere Anwendungen im selben Session-Verzeichnis koennen Session-Dateien trotzdem frueher loeschen. Auf dem Server muss deshalb auch die tatsaechliche Aufbewahrung mindestens 7200 Sekunden betragen; bei vorzeitigen Logouts Session-Speicher und externe Bereinigungsregeln pruefen.

Der Test `node scripts/test_session_lifetime.js` prueft Cookie-Erneuerung und Inaktivitaetsablauf mit einem isolierten PHP-Testserver. Der CLI-Test `php scripts/test_clone_session_bootstrap.php` prueft die beiden Klon-Endpoints mit einer isolierten `database.php` ohne Session-Helper, ohne die lokale Konfiguration oder Datenbank zu veraendern.

Der erste Rollout der Delta-Funktion selbst erfolgt weiterhin ueber den normalen Beta-Copy mit anschliessendem **Hydrate Beta**. Danach koennen weitere Aenderungen direkt als Delta-Paket eingespielt werden.