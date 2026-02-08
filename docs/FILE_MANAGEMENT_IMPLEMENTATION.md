# File Management System - Implementierungsbericht

**Datum**: Januar 2024  
**Status**: ✅ Implementiert und getestet

---

## Zusammenfassung

Das Dateiverwaltungssystem wurde vollständig implementiert und ermöglicht Benutzer und Administratoren, Dateien und Ordner in ihren Projekten zu verwalten. Das System unterstützt drei Hauptfunktionalitäten:

1. **Projekt-Dateiverwaltung**: Vollständiger CRUD-Zugriff auf Dateien/Ordner in benutzer-eigenen Projekten
2. **Assignment-Dateien**: Nur-Lese-Zugriff auf Dateien im Assignment-Kontext
3. **Frontend-Integration**: JavaScript-Bibliothek für einfache API-Nutzung

---

## Neue API-Endpoints

### 1. Ordner Management API
**Datei**: `/api/projects/folders.php`

| Aktion | Methode | Beschreibung |
|--------|---------|-------------|
| create | POST | Erstelle neuen Ordner |
| list | GET | Liste Ordner auf |
| rename | PUT | Benenne Ordner um |
| delete | DELETE | Lösche Ordner |

**Beispiel:**
```bash
POST /api/projects/folders.php?action=create&project_id=1
{
  "name": "src",
  "parent_folder_id": null,
  "description": "Source code"
}
```

### 2. Datei Management API
**Datei**: `/api/projects/files.php`

| Aktion | Methode | Beschreibung |
|--------|---------|-------------|
| create | POST | Erstelle/uploade Datei |
| list | GET | Liste Dateien auf |
| read | GET | Lese Dateiinhalt |
| update | PUT | Aktualisiere Dateiinhalt |
| delete | DELETE | Lösche Datei |

**Unterstützte Dateitypen:**
- `python` (.py)
- `json` (.json)
- `image` (.png, .jpg, .gif, .webp, .svg)
- `text` (.txt, .md, .csv, .log)
- `other` (alle anderen)

### 3. Assignment Files API
**Datei**: `/api/assignments/files.php`

| Aktion | Methode | Beschreibung |
|--------|---------|-------------|
| list | GET | Liste Assignment-Dateien auf (Nur-Lesen) |
| read | GET | Lese Assignment-Datei (Nur-Lesen) |

**Sicherheit:**
- Schüler können keine Assignment-Dateien ändern
- Nur-Lesen für nicht-versteckte Dateien
- Admin-Only: Hidden Files und Write-Access

---

## Datenbank-Schema

### Neue Tabellen

#### `folders`
```sql
CREATE TABLE folders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  path VARCHAR(1024) NOT NULL,
  parent_folder_id INT UNSIGNED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_folder_id) REFERENCES folders(id) ON DELETE CASCADE,
  UNIQUE KEY unique_folder_path (project_id, path)
);
```

#### `files`
```sql
CREATE TABLE files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  folder_id INT UNSIGNED,
  project_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  file_type ENUM('python','json','image','text','other') DEFAULT 'other',
  extension VARCHAR(20),
  mime_type VARCHAR(100),
  content LONGTEXT,
  file_path VARCHAR(1024) NOT NULL,
  file_size INT UNSIGNED,
  is_binary TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  UNIQUE KEY unique_file_path (project_id, file_path)
);
```

#### `assignment_files`
```sql
CREATE TABLE assignment_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  file_type ENUM('python','json','image','text','other') DEFAULT 'other',
  extension VARCHAR(20),
  mime_type VARCHAR(100),
  content LONGTEXT,
  file_path VARCHAR(1024),
  is_template TINYINT DEFAULT 0,
  is_starter_code TINYINT DEFAULT 0,
  is_solution TINYINT DEFAULT 0,
  is_hidden TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
);
```

**Flags in `assignment_files`:**
- `is_template`: Datei ist als Template verfügbar
- `is_starter_code`: Automatisch als Starter-Code bereitstellen
- `is_solution`: Referenz-Lösung (nur Admin sichtbar)
- `is_hidden`: Von Schülern versteckt

---

## Frontend-Integration

### JS-Bibliothek: FileManager

**Datei**: `/public/js/file-manager.js`

```javascript
// Initialisierung
const fm = new FileManager('/api');
fm.setProject(projectId);

// Ordner
await fm.createFolder('src', null, 'Description');
await fm.listFolders(parentId);
await fm.renameFolder(folderId, 'new-name');
await fm.deleteFolder(folderId);

// Dateien
await fm.createFile('script.py', 'python', content, folderId);
await fm.listFiles(folderId);
await fm.readFile(fileId);
await fm.updateFile(fileId, newContent);
await fm.deleteFile(fileId);
await fm.downloadFile(fileId);

// Assignment-Dateien
await fm.listAssignmentFiles(assignmentId, taskId);
await fm.readAssignmentFile(assignmentId, fileId);
```

---

## Fehlerkorrekturen

### 1. Middleware Update
- Hinzugefügt: `email`-Parameter statt `username`
- Alle Sessions verwenden jetzt E-Mail-basierte Authentifizierung
- Default-Werte für fehlende Session-Variablen

### 2. jsonResponse Funktion
- Wurde dupliziert in middlewares
- Konsolidiert zu `config/database.php`
- Middleware importiert sie nun einfach

---

## Validierung und Sicherheit

### Input-Validierung
✅ Dateiname-Validierung (Whitelist-Pattern)
✅ Dateiname-Längenbeschränkung (max 255 Zeichen)
✅ Ordnername-Validierung
✅ Path-Traversal-Schutz (normalisierten Pfade)

### Authentifizierung & Autorisierung
✅ Session-basierte Authentifizierung erforderlich
✅ Projekteigentümer-Prüfung auf Projekt-APIs
✅ Assignment-Zugriffsschutz
✅ Admin-Only Operations
✅ Nur-Lese-Schutz für Assignment-Dateien

### Datenbank-Constraints
✅ Foreign Key Constraints mit CASCADE-Löschung
✅ UNIQUE-Constraints auf Dateipfade
✅ ENUM-Typen für Dateitypen
✅ Timestamps für Audit-Trail

---

## Test-Skripte

### PHP-Test
**Datei**: `/scripts/test_file_apis.php`

```bash
php scripts/test_file_apis.php
```

**Überprüft:**
- ✅ Tabellen existieren
- ✅ Tabellenstruktur ist korrekt
- ✅ Ordnerstruktur in Datenbank
- ✅ Dateiintegrität
- ✅ Foreign Key Relationships
- ✅ API-Endpoints vorhanden
- ✅ Middleware-Funktionen

### JavaScript-Test
**Datei**: `/scripts/test_file_apis.js`

```javascript
// In Browser-Console unter eingeloggtem Nutzer ausführen:
const tester = new FileManagerTest();
await tester.run();
```

**Testet:**
- ✅ Ordnererstellung
- ✅ Ordnerlisting
- ✅ Dateierstellung (Python, JSON, Text)
- ✅ Dateilisting
- ✅ Dateiinhalt lesen
- ✅ Dateiupdate
- ✅ Fehlerbehandlung

---

## Dokumentation

### 1. FILE_API_DOCUMENTATION.md
Umfassende API-Dokumentation mit:
- Alle Endpoints mit Beispielen
- Response-Schemas
- Error Codes
- Datenbankschema
- Berechtigungsmodell
- Implementierungs-Tipps

### 2. FILE_API_QUICKSTART.md
Praktischer Leitfaden mit:
- JS-Bibliothek Verwendung
- HTML-Integration Beispiele
- Drag-Drop Upload
- Code Editor Integration
- cURL-Beispiele
- Fehlerbehandlung
- Troubleshooting

---

## Funktionalitäts-Matrix

| Feature | Projekt-Dateien | Assignment-Dateien | Admin-Only |
|---------|-----------------|-------------------|-----------|
| Ordner erstellen | ✅ | ❌ | ❌ |
| Ordner löschen | ✅ | ❌ | ❌ |
| Datei erstellen | ✅ | ❌ | ❌ |
| Datei hochladen | ✅ | ❌ | ❌ |
| Datei aktualisieren | ✅ | ❌ | ❌ |
| Datei löschen | ✅ | ❌ | ❌ |
| Datei lesen | ✅ | ✅ (public) | ✅ (all) |
| Versteckte Dateien | ❌ | ✅ | ✅ |
| Starter-Code | ✅ (create) | ✅ (read) | ✅ |
| Vorlagen | ✅ (create) | ✅ (read) | ✅ |
| Musterlösungen | ❌ | ❌ | ✅ |

---

## Performance-Überlegungen

### Datenbank-Optimierungen
- `LONGTEXT` für Content (max 4GB)
- Indizes auf `project_id`, `folder_id`, `assignment_id`
- Unique Constraints auf Pfade
- CASCADE-Deletes für Integrität

### Skalierbarkeit
- Content in DB speichern (MVP-okay, Scale → S3/MinIO)
- Base64-Encoding für Binärdateien
- Pagination für große Dateilisten (zukünftig)

### Sicherheit
- Kein Path-Traversal möglich (genormalisierte Pfade)
- Content nicht in Listviews (separater Read-Endpoint)
- Binary-Detection für sichere Verarbeitung

---

## Zukünftige Erweiterungen

```
[ ] File Versioning (Git-ähnlich)
[ ] File Sharing (Multi-User-Projekte)
[ ] Storage Quota (Limits pro Projekt)
[ ] File Compression (Archivierung)
[ ] Real-time Collaboration (WebSocket)
[ ] Code Syntax Highlighting (Monaco)
[ ] Image Metadata (EXIF)
[ ] Full-Text Search (Files)
[ ] File Diff/Merge Tools
[ ] External Storage (S3, MinIO)
```

---

## Installation/Migration

Nicht erforderlich - Tabellen wurden mit `scripts/add_files_structure.php` automatisch erstellt.

```bash
# Validierung durchführen
php scripts/test_file_apis.php
```

**Voraussetzungen:**
- MySQL 8.0+
- PHP 8.0+
- Session-Unterstützung

---

## Zusammenfassung der Änderungen

| Komponente | Status | Details |
|-----------|--------|---------|
| API Endpoints | ✅ | 3 neue APIs (folders, files, assignment_files) |
| Frontend Library | ✅ | FileManager JS-Klasse |
| Middleware | ✅ | Email-basierte Authentifizierung |
| Datenbank | ✅ | 3 neue Tabellen mit Constraints |
| Dokumentation | ✅ | 2 umfassende Guides |
| Tests | ✅ | PHP + JavaScript Test-Suites |
| Berechtigungen | ✅ | Role-based Access Control |

---

## Kontakt & Support

Alle APIs benötigen Session-basierte Authentifizierung.  
Siehe `FILE_API_DOCUMENTATION.md` für vollständige API-Referenz.  
Siehe `FILE_API_QUICKSTART.md` für praktische Beispiele.

---

**Letzte Aktualisierung**: Januar 2024  
**Version**: 1.0.0
