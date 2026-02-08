# File Management API - Quick Start Guide

## Überblick

Das Dateimanagementsystem bietet drei Haupt-API-Endpoints:
1. **Ordner-API** (`/api/projects/folders.php`) - Erstelle und verwalte Ordner in Projekten
2. **Datei-API** (`/api/projects/files.php`) - Erstelle und verwalte Dateien
3. **Assignment-Datei-API** (`/api/assignments/files.php`) - Nur-Lese-Zugriff auf Assignment-Dateien

---

## JavaScript-Bibliothek Verwenden

Die `FileManager`-Klasse bietet eine einfache Frontend-Integration.

### Initialisierung

```javascript
// Erstelle FileManager-Instanz
const fileManager = new FileManager('/api');

// Setze aktuelles Projekt
fileManager.setProject(projectId);
```

### Ordner verwalten

```javascript
// Erstelle einen Ordner
const folderResult = await fileManager.createFolder('src', null, 'Source code');
console.log(folderResult.folder.id); // Neue Ordner-ID

// Liste Ordner im Projekt auf
const folders = await fileManager.listFolders();
console.log(folders.folders); // Array von Ordnern

// Liste Unterordner auf
const subfolders = await fileManager.listFolders(folderId);

// Benenne Ordner um
await fileManager.renameFolder(folderId, 'new-name');

// Lösche Ordner
await fileManager.deleteFolder(folderId);
```

### Dateien verwalten

```javascript
// Erstelle Textdatei
const fileResult = await fileManager.createFile(
    'hello.py',
    'python',
    "print('Hello World')",
    folderId
);
console.log(fileResult.file.id); // Neue Datei-ID

// Erstelle JSON-Datei
await fileManager.createFile(
    'config.json',
    'json',
    JSON.stringify({ key: 'value' }, null, 2),
    folderId
);

// Uploade Bild
const fileInput = document.querySelector('input[type="file"]');
const file = fileInput.files[0];
await fileManager.createFile(
    file.name,
    'image',
    file,
    folderId
);

// Liste Dateien auf
const files = await fileManager.listFiles(folderId);
console.log(files.files); // Array von Dateien

// Lese Dateiinhalt
const data = await fileManager.readFile(fileId);
console.log(data.file.content); // Dateiinhalt

// Aktualisiere Datei
await fileManager.updateFile(fileId, newContent);

// Lösche Datei
await fileManager.deleteFile(fileId);

// Lade Datei herunter
const download = await fileManager.downloadFile(fileId);
// download.url = Object URL zum Download
// download.filename = Ursprünglicher Dateiname
```

### Ordnerstruktur erstellen

```javascript
// Erstelle komplexe Ordnerstruktur aus Template
const template = {
    folders: [
        {
            name: 'src',
            description: 'Source code',
            subFolders: [
                { name: 'modules', description: 'Python modules' },
                { name: 'utils', description: 'Utility functions' }
            ]
        },
        {
            name: 'tests',
            description: 'Unit tests'
        }
    ]
};

const result = await fileManager.createFromTemplate(template);
console.log(result.folders); // Alle erstellten Ordner
```

### Assignment-Dateien lesen

```javascript
// Liste Assignment-Dateien auf (Nur-Lesen)
const files = await fileManager.listAssignmentFiles(assignmentId);

// Lese einzelne Assignment-Datei
const data = await fileManager.readAssignmentFile(assignmentId, fileId);
console.log(data.file.content); // Content (Read-only)
console.log(data.read_only); // true
```

---

## HTML-Integration Beispiel

### File Explorer

```html
<div id="file-explorer">
    <div class="toolbar">
        <button id="new-folder-btn">Neuer Ordner</button>
        <button id="upload-file-btn">Datei hochladen</button>
    </div>
    
    <div class="folder-tree">
        <ul id="folder-list"></ul>
    </div>
    
    <div class="file-list">
        <div id="breadcrumb"></div>
        <table id="files">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Typ</th>
                    <th>Größe</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="files-body"></tbody>
        </table>
    </div>
</div>

<script src="file-manager.js"></script>
<script>
const fm = new FileManager('/api').setProject(projectId);

// Load files
async function loadFiles(folderId = null) {
    const result = await fm.loadFolderStructure(folderId);
    
    // Render folders and files
    renderFolders(result.folders);
    renderFiles(result.files);
}

// Create folder
document.getElementById('new-folder-btn').addEventListener('click', async () => {
    const name = prompt('Ordnername:');
    if (name) {
        const result = await fm.createFolder(name, currentFolder);
        loadFiles(currentFolder);
    }
});

// Upload file
document.getElementById('upload-file-btn').addEventListener('click', () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.onchange = async (e) => {
        const file = e.target.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        const typeMap = {
            py: 'python',
            json: 'json',
            txt: 'text',
            md: 'text',
            png: 'image',
            jpg: 'image',
            gif: 'image'
        };
        const type = typeMap[ext] || 'other';
        
        await fm.createFile(file.name, type, file, currentFolder);
        loadFiles(currentFolder);
    };
    input.click();
});

// Initial load
loadFiles();
</script>
```

### Code Editor Integration

```html
<div id="code-editor">
    <div class="editor-toolbar">
        <span id="filename"></span>
        <button id="save-btn">Speichern</button>
    </div>
    <textarea id="code-content" style="width: 100%; height: 400px;"></textarea>
</div>

<script src="file-manager.js"></script>
<script src="monaco/min/vs/loader.min.js"></script>
<script>
const fm = new FileManager('/api').setProject(projectId);

// Load file in editor
async function openFile(fileId) {
    const result = await fm.readFile(fileId);
    const file = result.file;
    
    document.getElementById('filename').textContent = file.name;
    document.getElementById('code-content').value = file.content;
    document.getElementById('code-content').dataset.fileId = fileId;
}

// Save file
document.getElementById('save-btn').addEventListener('click', async () => {
    const fileId = document.getElementById('code-content').dataset.fileId;
    const content = document.getElementById('code-content').value;
    
    await fm.updateFile(fileId, content);
    alert('Datei gespeichert!');
});
</script>
```

### Drag & Drop Upload

```html
<div id="drop-zone" style="border: 2px dashed #ccc; padding: 20px; text-align: center;">
    Dateien hier ablegen
</div>

<script src="file-manager.js"></script>
<script>
const fm = new FileManager('/api').setProject(projectId);
const dropZone = document.getElementById('drop-zone');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.style.background = '#e0e0e0';
}

function unhighlight(e) {
    dropZone.style.background = '#fff';
}

dropZone.addEventListener('drop', handleDrop, false);

async function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    for (let file of files) {
        const ext = file.name.split('.').pop().toLowerCase();
        const typeMap = {
            py: 'python', json: 'json', txt: 'text',
            md: 'text', png: 'image', jpg: 'image'
        };
        const type = typeMap[ext] || 'other';
        
        await fm.createFile(file.name, type, file, currentFolder);
    }
    
    loadFiles(currentFolder);
}
</script>
```

---

## cURL-Beispiele

### Mit Session

```bash
# Login first
curl -c cookies.txt \
  -d "email=test@example.com&password=test123" \
  http://localhost/api/auth/login.php

# Dann API-Calls mit Cookies
curl -b cookies.txt \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"name":"src","parent_folder_id":null}' \
  "http://localhost/api/projects/folders.php?action=create&project_id=1"
```

### Datei hochladen

```bash
curl -b cookies.txt \
  -F "name=script.py" \
  -F "file_type=python" \
  -F "folder_id=1" \
  -F "file=@script.py" \
  "http://localhost/api/projects/files.php?action=create&project_id=1"
```

---

## Fehlerbehandlung

```javascript
try {
    await fileManager.createFolder('test');
} catch (error) {
    console.error('Fehler:', error.message);
    // '400: Folder already exists at this path'
    // '403: Access denied'
    // '404: Parent folder not found'
}
```

---

## Performance-Tipps

1. **Batch-Operationen**: Gruppiere mehrere createFile/createFolder-Calls
2. **Lazy Loading**: Lade nur aktuelle Ordner und dessen Dateien
3. **Caching**: Speichere Ordnerstruktur lokal
4. **Kompression**: Große Dateien vor Upload komprimieren
5. **Progress**: Implementiere XHR Upload Progress für große Datei

---

## Sicherheitsaspekte

- ✅ Sessions erforderlich (CSRF-Schutz)
- ✅ Projekt-Ownership-Validierung auf Server
- ✅ Assignment-Dateien schreibgeschützt für Schüler
- ✅ Dateiname-Validierung gegen Path Traversal
- ✅ Binary-Dateien Base64-kodiert

---

## Troubleshooting

### "Authentication required" (401)
- Session ist abgelaufen
- Nutzer ist nicht eingeloggt
- Lösung: Neu einloggen

### "Access denied" (403)
- Projekt gehört nicht dem Nutzer
- Schüler versucht Assignment-Datei zu schreiben
- Lösung: Überprüfe Berechtigung

### "File already exists" (409)
- Datei/Ordner existiert bereits
- Lösung: Anderer Name erforderlich

### Große Dateien zeitlich überschritten
- PHP `upload_max_filesize` erhöhen
- `php.ini` anpassen: `upload_max_filesize = 100M`
