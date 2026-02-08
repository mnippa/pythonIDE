# File Management API Dokumentation

## Überblick
- **Projekt Dateien API**: CRUD für Dateien in benutzer-eigenen Projekten (Vollzugriff)
- **Ordner API**: CRUD für Ordnerstruktur in Projekten
- **Assignment Files API**: Nur-Lese-Zugriff auf Dateien im Assignment-Kontext

## Authentifizierung
Alle Endpoints erfordern Session-basierte Authentifizierung.

---

## Ordner Management API
### Basis-URL: `/api/projects/folders.php`

### 1. Ordner erstellen
```
POST /api/projects/folders.php?action=create&project_id=1
Content-Type: application/json

{
  "name": "Mein Ordner",
  "parent_folder_id": null,
  "description": "Optionale Beschreibung"
}
```

**Response (201 Created):**
```json
{
  "ok": true,
  "folder": {
    "id": 5,
    "project_id": 1,
    "name": "Mein Ordner",
    "path": "Mein Ordner",
    "parent_folder_id": null,
    "description": "Optionale Beschreibung",
    "created_at": "2024-01-15 10:30:00"
  }
}
```

### 2. Ordner auflisten
```
GET /api/projects/folders.php?action=list&project_id=1&parent_id=null
```

**Response (200 OK):**
```json
{
  "ok": true,
  "project_id": 1,
  "parent_folder_id": null,
  "folders": [
    {
      "id": 1,
      "name": "src",
      "path": "src",
      "parent_folder_id": null,
      "description": "",
      "subfolder_count": 2,
      "file_count": 5,
      "created_at": "2024-01-15 10:00:00"
    },
    {
      "id": 2,
      "name": "tests",
      "path": "tests",
      "parent_folder_id": null,
      "description": "",
      "subfolder_count": 0,
      "file_count": 3,
      "created_at": "2024-01-15 10:05:00"
    }
  ],
  "count": 2
}
```

### 3. Ordner umbenennen
```
PUT /api/projects/folders.php?action=rename&project_id=1
Content-Type: application/json

{
  "folder_id": 5,
  "name": "Neuer Name"
}
```

**Response (200 OK):**
```json
{
  "ok": true,
  "message": "Folder renamed successfully"
}
```

### 4. Ordner löschen
```
DELETE /api/projects/folders.php?action=delete&project_id=1
Content-Type: application/json

{
  "folder_id": 5
}
```

**Response (200 OK):**
```json
{
  "ok": true,
  "message": "Folder deleted successfully"
}
```

---

## Dateien Management API
### Basis-URL: `/api/projects/files.php`

### 1. Datei erstellen/hochladen
```
POST /api/projects/files.php?action=create&project_id=1
Content-Type: application/json (oder multipart/form-data für Uploads)

{
  "name": "script.py",
  "folder_id": 1,
  "file_type": "python",
  "content": "print('Hello World')"
}
```

**Oder mit Multipart Upload:**
```
POST /api/projects/files.php?action=create&project_id=1

form-data:
- file: <binary file>
- folder_id: 1
```

**Response (201 Created):**
```json
{
  "ok": true,
  "file": {
    "id": 10,
    "project_id": 1,
    "folder_id": 1,
    "name": "script.py",
    "file_type": "python",
    "extension": "py",
    "mime_type": "text/x-python",
    "file_path": "src/script.py",
    "file_size": 22,
    "created_at": "2024-01-15 10:45:00"
  }
}
```

**Unterstützte Dateitypen:**
- `python`: `.py`
- `json`: `.json`
- `image`: `.png, .jpg, .jpeg, .gif, .webp, .svg`
- `text`: `.txt, .md, .csv, .log`
- `other`: Alle anderen

### 2. Dateien auflisten
```
GET /api/projects/files.php?action=list&project_id=1&folder_id=1
```

**Response (200 OK):**
```json
{
  "ok": true,
  "project_id": 1,
  "folder_id": 1,
  "files": [
    {
      "id": 10,
      "name": "script.py",
      "file_type": "python",
      "extension": "py",
      "mime_type": "text/x-python",
      "file_path": "src/script.py",
      "file_size": 22,
      "folder_id": 1,
      "created_at": "2024-01-15 10:45:00"
    }
  ],
  "count": 1
}
```

### 3. Datei lesen
```
GET /api/projects/files.php?action=read&project_id=1&file_id=10
```

**Response (200 OK):**
```json
{
  "ok": true,
  "file": {
    "id": 10,
    "name": "script.py",
    "file_type": "python",
    "extension": "py",
    "mime_type": "text/x-python",
    "file_path": "src/script.py",
    "file_size": 22,
    "content": "print('Hello World')",
    "created_at": "2024-01-15 10:45:00"
  }
}
```

### 4. Datei aktualisieren
```
PUT /api/projects/files.php?action=update&project_id=1
Content-Type: application/json

{
  "file_id": 10,
  "content": "print('Updated')"
}
```

**Response (200 OK):**
```json
{
  "ok": true,
  "message": "File updated successfully"
}
```

### 5. Datei löschen
```
DELETE /api/projects/files.php?action=delete&project_id=1
Content-Type: application/json

{
  "file_id": 10
}
```

**Response (200 OK):**
```json
{
  "ok": true,
  "message": "File deleted successfully"
}
```

---

## Assignment Files API
### Basis-URL: `/api/assignments/files.php`

Quellcode-Dateien sind im Assignment-Kontext **schreibgeschützt** für Studenten.

### 1. Assignment-Dateien auflisten
```
GET /api/assignments/files.php?action=list&assignment_id=5&task_id=2
```

**Response (200 OK - Student):**
```json
{
  "ok": true,
  "assignment_id": 5,
  "task_id": 2,
  "files": [
    {
      "id": 1,
      "name": "starter_code.py",
      "file_type": "python",
      "is_template": false,
      "is_starter_code": true,
      "is_solution": false,
      "is_hidden": false
    }
  ],
  "count": 1,
  "user_is_admin": false
}
```

### 2. Assignment-Datei lesen
```
GET /api/assignments/files.php?action=read&assignment_id=5&file_id=1
```

**Response (200 OK):**
```json
{
  "ok": true,
  "file": {
    "id": 1,
    "name": "starter_code.py",
    "file_type": "python",
    "content": "def greet(name):\n    return f'Hello {name}!'",
    "is_template": false,
    "is_starter_code": true,
    "is_solution": false,
    "is_hidden": false
  },
  "is_base64": false,
  "read_only": true
}
```

### Schreibgeschützt-Verhalten
```
PUT /api/assignments/files.php?action=update&assignment_id=5
```

**Response (403 Forbidden - Student):**
```json
{
  "ok": false,
  "error": "Assignment files are read-only"
}
```

---

## Fehlerbehandlung

### 400 Bad Request
```json
{
  "ok": false,
  "error": "Project ID required"
}
```

### 403 Forbidden
```json
{
  "ok": false,
  "error": "Access denied"
}
```

### 404 Not Found
```json
{
  "ok": false,
  "error": "File not found"
}
```

### 409 Conflict
```json
{
  "ok": false,
  "error": "File already exists at this path"
}
```

### 500 Internal Server Error
```json
{
  "ok": false,
  "error": "Failed to create file: ..."
}
```

---

## Datenbank-Schema

### folders Tabelle
```
id (int, PK)
project_id (int, FK)
name (varchar 255)
description (text)
path (varchar 255) - vollständiger Pfad
parent_folder_id (int, FK, nullable)
created_at (timestamp)
updated_at (timestamp)
```

### files Tabelle
```
id (int, PK)
folder_id (int, FK, nullable)
project_id (int, FK)
name (varchar 255)
file_type (enum: python, json, image, text, other)
extension (varchar 10)
mime_type (varchar 100)
content (LONGTEXT)
file_path (varchar 255)
file_size (bigint)
is_binary (boolean)
created_at (timestamp)
updated_at (timestamp)
```

### assignment_files Tabelle
```
id (int, PK)
assignment_id (int, FK)
task_id (int, FK, nullable)
name (varchar 255)
file_type (enum: python, json, image, text, other)
content (LONGTEXT)
is_template (boolean) - Als Vorlage verfügbar
is_starter_code (boolean) - Als Startcode bereitstellen
is_solution (boolean) - Als Musterlösung (Admin-only)
is_hidden (boolean) - Vor Studenten verstecken
created_at (timestamp)
updated_at (timestamp)
```

---

## Berechtigungen

### Projekt-Dateien (User Projects)
- **Eigentümer/Admin**: Voller CRUD-Zugriff
- **Andere User**: Keine Berechtigung

### Assignment-Dateien
- **Schüler**: Nur-Lesen auf nicht-verborgene Dateien
- **Admin**: Voller Zugriff einschließlich verborgene Dateien

### Ordnerstruktur
- **Hierarchische Verwaltung**: Eltern-Kind-Beziehungen via `parent_folder_id`
- **Pfad-Tracking**: Vollständiger Dateipfad in `path` Feld
- **Kaskadierendes Löschen**: Ordner-Löschung entfernt auch Dateien (via FK-Constraints)

---

## Implementierungs-Tipps

### Frontend Integration
1. **Datei-Explorer**: Rekursive Ordnernavigation mit Tree-View
2. **Datei-Upload**: Drag-Drop auf Ordner, Multipart-Form-Upload
3. **Quellcode-Editor**: Integration mit Monaco Editor für `.py` und `.json`
4. **Bildvorschau**: Base64-Rendering für Bilder
5. **Kontextmenü**: Umbenennen, Löschen, Download, Share

### Sicherheit
- ✅ Session-basierte Authentifizierung erforderlich
- ✅ Projekteigentüm-Prüfung bei allen Operationen
- ✅ Assignment-Dateien per `is_hidden` Flag ausblendbar
- ✅ Binärdateien als Base64 für sicheren Transport
- ✅ Dateiname-Validierung gegen Path-Traversal

### Performance
- Content nicht in Listviews laden (separat mit `read`-Action abrufen)
- Dateigröße in Bytes speichern für Quota-Management
- Indizes auf `project_id`, `folder_id`, `assignment_id` für schnelle Queries

### Zukünftige Erweiterungen
- [ ] File Versioning (Versionskontrolle)
- [ ] File Sharing (Mehrbenutzer-Projekte)
- [ ] Storage Quota (Speicherlimit pro Project)
- [ ] File Compression (Archivierung)
- [ ] Real-time Collaboration (WebSocket-basiert)
