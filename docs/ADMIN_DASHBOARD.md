# Admin Dashboard

Hinweis: Diese Datei beschreibt nur die wichtigsten Admin-Bereiche in Kurzform.
Für den aktuellen Gesamtzusammenhang siehe [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md).

## Zugriff
- URL: /public/admin.php
- Login als Admin: admin@pythonide.local / admin123

## Funktionen

### Projects
- Liste aller Projekte mit Owner
- Project loeschen (Admin-only)

### Assignments
- Liste aller Assignments
- Create, Update, Delete
- Tasks pro Assignment verwalten
- Evaluation- und Status-bezogene Ansichten
- Dashboard-/Assignment-bezogene Statuslogik sollte getrennt nach Verfügbarkeit, Task-Fortschritt und Assignment-Status gedacht werden

### Users
- Liste aller Benutzer
- Status setzen: aktiv oder archiviert

### Deploy Sync (Live/Beta)
- Admin-UI zum Anstoßen von Live/Beta-Synchronisation
- Modus `hydrate-beta`
- Modus `promote-live`
- Zusätzliche Sicherheitsabfrage für Beta -> Live
- Technischer Einstieg: `/api/admin/deploy/sync-live-beta.php`

## APIs

### Admin Projects
- GET /api/admin/projects/list.php

### Admin Users
- GET /api/admin/users/list.php
- POST /api/admin/users/update.php
  - Body: { "id": 2, "status": "archiviert" }

### Assignments
- POST /api/assignments/create.php
- GET /api/assignments/list.php?all=1
- GET /api/assignments/get.php?id=1
- POST /api/assignments/update.php
- DELETE /api/assignments/delete.php?id=1

### Tasks
- POST /api/tasks/create.php
- GET /api/tasks/list.php?assignment_id=1&include_expected=1
- POST /api/tasks/update.php
- DELETE /api/tasks/delete.php?id=10

### Deploy
- POST /api/admin/deploy/sync-live-beta.php

## Hinweise
- Der Admin-Button erscheint im Editor oben rechts.
- Zugriff auf admin.php ist nur fuer Admins erlaubt.
- Der Deploy-Sync aus der Admin-Oberfläche ist nicht dasselbe wie ein klassisches Shell-Deploy und hängt von den Serverrechten des auslösenden Prozesses ab.
