# MVP Plan: Tasktypen Datenbank + Dateiabgabe

## Ziel

Einfuehrung von zwei neuen Tasktypen innerhalb des bestehenden Assignment-Systems:

1. `db_model` (automatische Pruefung, strukturorientiert)
2. `file_submission` (manuelle Pruefung, Datei-Upload)

Dieser Plan ist bewusst als MVP geschnitten, damit er zeitnah auslieferbar bleibt.

## Snapshot vor Umsetzung

Definierter Basisstand wurde als Git-Snapshot gesichert:

- Branch: `snapshot/2026-05-28-pre-db-fileupload`
- Tag: `snapshot-2026-05-28-pre-db-fileupload`
- Commit: `4b74bc2`

Nutzung:

- Hotfix auf Snapshot-Basis: `git checkout snapshot/2026-05-28-pre-db-fileupload`
- Vergleich aktuell vs Snapshot: `git diff snapshot-2026-05-28-pre-db-fileupload..main`

## Tasktyp 1: file_submission

### Functional Scope (MVP)

- Student sieht `task_text` und optionales Bild.
- Student gibt genau eine Datei ab (ersetzbar bis finaler Submit).
- Validierung durch konfigurierte Dateitypen und max. Dateigroesse.
- Bewertung ist immer manuell.
- Admin kann Datei herunterladen, Status setzen (`passed` / `failed`) und Kommentar hinterlegen.

### Task-Konfiguration

Neue Felder in `tasks`:

- `file_submission_allowed_types` (VARCHAR, CSV-Liste, z. B. `pdf,png,jpg,zip`)
- `file_submission_max_size_bytes` (INT UNSIGNED)

Vorgaben fuer Groessenauswahl:

- 51200 (50 KB)
- 102400 (100 KB, Default)
- 256000 (250 KB)
- 1048576 (1 MB)
- 2097152 (2 MB)
- 5242880 (5 MB)

Tasktyp:

- `task_type = file_submission`
- `manual_review_required = 1` erzwungen

### Persistenz fuer Uploads

Neue Tabelle `user_task_submissions_files`:

- `id` INT PK AI
- `user_id` INT NOT NULL
- `task_id` INT NOT NULL
- `assignment_id` INT NOT NULL
- `original_name` VARCHAR(255) NOT NULL
- `stored_name` VARCHAR(255) NOT NULL
- `mime_type` VARCHAR(120) NOT NULL
- `file_ext` VARCHAR(20) NOT NULL
- `file_size` INT UNSIGNED NOT NULL
- `file_hash_sha256` CHAR(64) NOT NULL
- `storage_path` VARCHAR(500) NOT NULL
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

Indexes:

- `idx_utf_user_task (user_id, task_id, is_active)`
- `idx_utf_assignment (assignment_id, task_id)`

Unique-Regel MVP:

- genau eine aktive Datei pro User+Task
- bei Neu-Upload alte Datei `is_active = 0`

Dateispeicher:

- `storage/user_task_submissions/task_<taskId>/user_<userId>/...`

### API-Endpunkte (neu)

- `POST /api/user_tasks/upload_submission_file.php`
  - multipart upload
  - validiert Typ + Groesse + Assignment-Zugriff
  - speichert Datei + Metadaten

- `GET /api/user_tasks/get_submission_file.php?task_id=...`
  - liefert Metadaten der aktiven Datei fuer den User

- `GET /api/admin/tasks/download_submission_file.php?user_id=...&task_id=...`
  - admin-only Download

- `POST /api/admin/assignments/users/review_submission_file.php`
  - payload: `user_id`, `task_id`, `status`, `comment`
  - setzt `user_tasks.status` auf `passed` oder `failed`
  - optional `submission_comment`

### UI-Anpassungen

Admin (`public/admin.php`, `public/js/admin-dashboard.js`, `public/js/task-type-manager.js`):

- Neuer Tasktyp in Dropdowns: `file_submission`
- Felder sichtbar:
  - `task_text` (Pflicht)
  - `image_url` (optional)
  - erlaubte Dateitypen (Mehrfachauswahl/CSV)
  - max. Dateigroesse (Select mit festen Stufen)
- `manual_review_required` fuer diesen Typ automatisch aktivieren und sperren

Student (`public/js/assignments.js`):

- Renderer fuer `file_submission`
- Upload-Control + Statusanzeige der hochgeladenen Datei
- Vor finalem Submit sicherstellen: Datei vorhanden
- Kein Auto-Check, stattdessen Hinweis: "Manuelle Pruefung durch Lehrperson"

## Tasktyp 2: db_model

### Functional Scope (MVP)

Frontend-Modellierung einer relationalen Struktur:

- Tabellen
- Spalten
- Datentyp
- PK/FK Markierung

Automatische Pruefung gegen Musterstruktur:

- Tabellencheck
- Feldcheck
- PK/FK-Check

Teilfeedback als Bereiche:

- Tabellen ok/nicht ok
- Felder ok/nicht ok
- Schluessel ok/nicht ok

### Datenmodell in Task

Tasktyp:

- `task_type = db_model`

Konfiguration in `test_cases` als JSON (MVP-Variante ohne neue Tasks-Spalten):

- `db_schema_solution` (kanonische Sollstruktur)
- `db_schema_rules` (Pruefregeln, z. B. case-sensitive false)

Beispielstruktur:

- `tables[].name`
- `tables[].columns[].name`
- `tables[].columns[].type`
- `tables[].columns[].is_pk`
- `tables[].columns[].is_nullable`
- `tables[].foreign_keys[]` mit `column`, `ref_table`, `ref_column`

### Persistenz Studentenantwort

MVP in bestehender Tabelle `user_tasks.current_code` als JSON-String
(analog zu strukturierten Inhalten, ohne sofort neue Tabelle).

Format:

- `version`
- `tables[]`
- optional `layout` (nur UI, nicht pruefungsrelevant)

### API-Endpunkte

Erweiterung bestehender Flows:

- `POST /api/user_tasks/update.php`
  - fuer `db_model` wird JSON-Modell in `current_code` gespeichert

- `POST /api/user_tasks/submit_quiz.php` oder eigener Endpoint `submit_db_model.php`
  - empfange Schueler-Modell
  - normalisiere Soll/Ist
  - pruefe Bereiche
  - liefere Teilfeedback + is_correct
  - update `user_tasks.status`

Empfehlung MVP:

- separater Endpoint `submit_db_model.php`, damit bestehende Quiz-Logik nicht ueberladen wird.

### UI-Anpassungen

Admin:

- Tasktyp `db_model` in Auswahllisten
- Editorfeld fuer Sollstruktur (JSON) mit Vorlage-Generator
- optional Taskbild wie bei anderen Quiztypen

Student:

- einfacher DB-Canvas im Taskbereich
- Tabelle hinzufuegen, Spalten editieren, PK/FK setzen
- "Pruefen" laeuft gegen Sollstruktur
- "Abgeben" setzt finalen Status wie bestehende Typen

## Migrationen

## 1) Tasktyp-Enum erweitern

Migration z. B. `sql/migrations/0xx_add_task_types_db_and_file.sql`:

- erweitert `tasks.task_type` um:
  - `db_model`
  - `file_submission`

## 2) Neue Spalten fuer file_submission in tasks

- `file_submission_allowed_types` VARCHAR(255) NULL
- `file_submission_max_size_bytes` INT UNSIGNED NOT NULL DEFAULT 102400

## 3) Neue Tabelle fuer Upload-Metadaten

- `user_task_submissions_files` (siehe oben)

## 4) Optional spaeter

- eigene Tabelle fuer `db_model`-Antworten, falls `current_code` nicht reicht.

## Security und Validierung

file_submission:

- Endung + MIME-Type gegen Allowlist
- Groessenlimit serverseitig erzwingen
- Dateiname sanitizen
- nur Download, keine serverseitige Ausfuehrung
- ZIP nicht entpacken im MVP

db_model:

- JSON-Parser hart validieren
- Normalisierung gegen Name-Varianten
- klare Fehlermeldungen ohne interne Stacktraces

## Rollout-Reihenfolge

1. Migrationen + Enum + Admin-Dropdowns
2. `file_submission` Ende-zu-Ende (Upload, Anzeige, Admin-Review)
3. `db_model` MVP-Canvas + Submit + Auto-Check
4. Import/Export anpassen
5. Doku aktualisieren (`docs/taskexport.md`, `docs/TASK_AUTHORING_GUIDE.md`)

## Ticket-Schnitt (empfohlen)

- T1: Migrationen + Enum-Erweiterung
- T2: Admin UI fuer `file_submission`
- T3: User Upload + Dateispeicher + Download
- T4: Admin Review fuer Dateiabgabe
- T5: Admin UI fuer `db_model`
- T6: Student DB-Canvas MVP
- T7: DB-Pruefengine + Submit-Endpunkt
- T8: Import/Export + Doku

## Abnahmekriterien

file_submission:

- Task laesst sich erstellen mit Typen/Groesse
- Student kann gueltige Datei hochladen und ersetzen
- Ungueltiger Typ/Groesse wird blockiert
- Admin kann Datei herunterladen und Status setzen

 db_model:

- Student kann Tabellen/Felder/PK/FK modellieren
- Pruefung liefert Teilfeedback in 3 Bereichen
- Statuswechsel `in-progress` -> `passed/failed` funktioniert

## Hinweis zu UML-Workaround

Mit `file_submission` koennen UML-Aufgaben sofort als Bild/PDF/ZIP abgegeben und manuell bewertet werden,
falls die native UML-Canvas-Umsetzung spaeter erfolgt.
