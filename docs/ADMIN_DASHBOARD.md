# Admin Dashboard

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

### Users
- Liste aller Benutzer
- Status setzen: aktiv oder archiviert

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

## Hinweise
- Der Admin-Button erscheint im Editor oben rechts.
- Zugriff auf admin.php ist nur fuer Admins erlaubt.
