# Python Web IDE — Aktueller Projektstand

Kurzbeschreibung
-----------------
Eine browserbasierte Python-Web-IDE (Prototyp) mit integriertem Monaco Editor, lokalem PHP-Backend und Pyodide für Python-Ausführung im Browser.

Aktueller Status
---------------
- Grundgerüst vorhanden: `public/` (Frontend), `api/` (Backend-APIs), `config/`, `sql/`.
- Prototype: Editor, Autocomplete und Pyodide-Integration funktionieren im Browser.
- Auth-API (`api/auth/`) vorhanden, Datenbankanbindung in `config/database.php` konfigurieren.
- Viele Pyodide-Pakete liegen bereits unter `pyodide/`.

Hauptfunktionen
---------------
- Monaco-basierter Code-Editor mit Autocomplete und Laufzeit-Ausgabe.
- Ausführung von Python im Browser via Pyodide.
- Einfache Benutzer-Authentifizierung (API-Endpunkte in `api/auth/`).

Projektstruktur (Auszug)
------------------------
- `public/` — Webfront (HTML/PHP), Assets (CSS, JS), `monaco/` Editor-Bundles.
- `public/js/` — Editor-Integration (`editor.js`, `editor-api.js`, `autocomplete.js`, ...).
- `api/` — Backend-Endpunkte (z.B. `auth/` mit `login.php`, `register.php`, `logout.php`).
- `config/` — Konfiguration (`database.php`).
- `pyodide/` — Vorgepackte Pyodide- und Wheel-Dateien.
- `sql/` — `schema.sql` für die Datenbank.
- `scripts/` — Hilfs-Skripte (z.B. `scrape_w3schools.php`).
- `storage/` — Persistente oder beispielhafte Dateien.

Einrichtung (lokal, XAMPP)
--------------------------
1. XAMPP (Apache + PHP) installieren und Apache starten.
2. Projektordner in `htdocs` legen: `c:\xampp\htdocs\pythonIDE`.
3. `config/database.php` anpassen (Datenbankzugang).
4. Datenbank anlegen und `sql/schema.sql` importieren (z.B. via phpMyAdmin).
5. Browser öffnen: `http://localhost/pythonIDE/public/`

Entwicklungshinweise
--------------------
- Frontend: Änderungen in `public/js/` und `public/css/`.
- Editor: `public/monaco/` enthält die Editor-Distribution; Anpassungen in `editor-*.js`.
- Pyodide: Zusätzliche Pakete in `pyodide/` ablegen und ggf. `pyodide-init` anpassen.
- API: PHP-Endpoints unter `api/` erweitern; DB-Verbindungen über `config/database.php`.

Bekannte TODOs
---------------
- Konfiguration für Produktionsbetrieb (Sicherheit, CORS, HTTPS).
- Tests und Fehlerbehandlung für API-Endpunkte erweitern.
- Benutzer-Session-/Token-Handling härten.

Kontakt / Weiteres
------------------
Bei Fragen oder Wunsch nach Live-Demo bitte melden.

---
Stand: 06.02.2026

\# Python Web IDE



Browserbasierte Web-IDE für Python mit Monaco Editor und Pyodide.



\## Tech Stack

\- Monaco Editor

\- Pyodide (Python WASM)

\- PHP 7.4

\- MariaDB (später)



\## Ziel

Studierende schreiben und testen Python-Code direkt im Browser.



