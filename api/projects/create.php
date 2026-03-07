<?php
/**
 * Create Project API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/templates.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

// Validate input
$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$code = $input['code'] ?? '';
$visibility = $input['visibility'] ?? 'private';
$template = $input['template'] ?? 'empty_python';

$templateData = ProjectTemplates::getTemplate($template);
$projectType = $templateData['project_type'] ?? 'python';

if (empty($name)) {
    jsonResponse(['ok' => false, 'error' => 'Project name is required'], 400);
}

if (!in_array($visibility, ['private', 'public'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid visibility'], 400);
}

if (!in_array($projectType, ['python', 'html', 'mixed'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid project type'], 400);
}

// Check code size limit
$stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
$key = 'project_code_max_size';
$stmt->bind_param('s', $key);
$stmt->execute();
$result = $stmt->get_result();
$maxSize = $result->num_rows > 0 ? (int)$result->fetch_assoc()['setting_value'] : 102400;

if (strlen($code) > $maxSize) {
    jsonResponse(['ok' => false, 'error' => "Code exceeds maximum size of " . ($maxSize/1024) . "KB"], 400);
}

// Check project limit
$key = 'project_limit_per_user';
$stmt->bind_param('s', $key);
$stmt->execute();
$result = $stmt->get_result();
$maxProjects = $result->num_rows > 0 ? (int)$result->fetch_assoc()['setting_value'] : 50;

$stmt = $conn->prepare('SELECT COUNT(*) as count FROM projects WHERE user_id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$currentCount = $result->fetch_assoc()['count'];

if ($currentCount >= $maxProjects) {
    jsonResponse(['ok' => false, 'error' => "Project limit reached ($maxProjects projects)"], 403);
}

// Ensure project files tables exist before using them
ensureProjectFilesTablesExist($conn);

// Generate share token for public projects
$shareToken = null;
if ($visibility === 'public') {
    $shareToken = bin2hex(random_bytes(16));
}

// Create project
$stmt = $conn->prepare('INSERT INTO projects (user_id, name, description, code, project_type, visibility, share_token) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('issssss', $user['id'], $name, $description, $code, $projectType, $visibility, $shareToken);

if ($stmt->execute()) {
    $projectId = $conn->insert_id;
    
    // Initialize project with template
    initializeProjectFromTemplate($conn, $projectId, $name, $template, $templateData);
    
    jsonResponse([
        'ok' => true,
        'project' => [
            'id' => $projectId,
            'name' => $name,
            'description' => $description,
            'project_type' => $projectType,
            'visibility' => $visibility,
            'share_token' => $shareToken,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to create project'], 500);
}

/**
 * Initialize project from template
 */
function initializeProjectFromTemplate($conn, $projectId, $projectName, $templateName, $templateData = null)
{
    try {
        // Get template
        if ($templateData === null) {
            $templateData = ProjectTemplates::getTemplate($templateName);
        }
        
        // Ensure expected files exist for HTML/mixed templates
        $templateProjectType = $templateData['project_type'] ?? 'python';
        if (($templateProjectType === 'html' || $templateProjectType === 'mixed') && isset($templateData['files']) && is_array($templateData['files'])) {
            if (!isset($templateData['files']['index.html'])) {
                $templateData['files']['index.html'] = [
                    'content' => "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>{$projectName}</title>\n  <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n  <h1>{$projectName}</h1>\n</body>\n</html>\n",
                    'mime_type' => 'text/html'
                ];
            }

            if (!isset($templateData['files']['style.css'])) {
                $templateData['files']['style.css'] = [
                    'content' => "body {\n  font-family: system-ui, -apple-system, sans-serif;\n  margin: 0;\n  padding: 20px;\n}\n",
                    'mime_type' => 'text/css'
                ];
            }

            if (!isset($templateData['files']['init.py'])) {
                $templateData['files']['init.py'] = [
                    'content' => "import idegui as ui\n",
                    'mime_type' => 'text/x-python'
                ];
            }
        }
        
        // Create includes folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
        $folderName = 'includes';
        $stmt->bind_param('is', $projectId, $folderName);
        $stmt->execute();
        
        // Create img folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
        $folderName = 'img';
        $stmt->bind_param('is', $projectId, $folderName);
        $stmt->execute();
        
        // Create files from template
        $fileStmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
        
        foreach ($templateData['files'] as $fileName => $fileData) {
            $content = $fileData['content'];
            $mimeType = $fileData['mime_type'];
            $fileSize = strlen($content);
            
            $fileStmt->bind_param('isssi', $projectId, $fileName, $content, $mimeType, $fileSize);
            $fileStmt->execute();
        }
        
    } catch (Exception $e) {
        error_log('Failed to initialize project from template: ' . $e->getMessage());
    }
}

/**
 * Initialize default project structure (deprecated - kept for backwards compatibility)
 * Creates: includes/, img/ folders and files based on project type
 */
function initializeDefaultProjectStructure($conn, $projectId, $projectName, $projectType = 'python')
{
    try {
        // Create includes folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
        $folderName = 'includes';
        $stmt->bind_param('is', $projectId, $folderName);
        $stmt->execute();
        
        // Create img folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
        $folderName = 'img';
        $stmt->bind_param('is', $projectId, $folderName);
        $stmt->execute();
        
        $safeName = preg_replace('/\s+/', '_', trim($projectName));
        $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '', $safeName);
        if ($safeName === '') {
            $safeName = 'project';
        }

        $initContent = "# " . $projectName . "\n\n# Start coding here!\n";
        
        // Create files based on project type
        if ($projectType === 'html' || $projectType === 'mixed') {
            // Create index.html (matching code_ui structure)
            $htmlContent = <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$projectName}</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="code-ui-wrapper">
    <h3>{$projectName}</h3>
    <p>idegui-Projekt - HTML und Python können frei bearbeitet werden.</p>
    <div id="idegui-root" data-idegui-root="true"></div>
    <div id="idegui-output" data-idegui-output="true"></div>
  </div>
</body>
</html>
HTML;
            
            $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
            $fileName = 'index.html';
            $mimeType = 'text/html';
            $fileSize = strlen($htmlContent);
            $stmt->bind_param('isssi', $projectId, $fileName, $htmlContent, $mimeType, $fileSize);
            $stmt->execute();
            
            // Create style.css
            $cssContent = <<<CSS
.code-ui-wrapper {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    margin: 0;
    padding: 16px;
}

#idegui-root {
    min-height: 180px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
}

#idegui-output {
    margin-top: 12px;
    font-size: 14px;
    color: #374151;
    padding: 8px;
    background: #f9fafb;
    border-radius: 4px;
}
CSS;
            
            $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
            $fileName = 'style.css';
            $mimeType = 'text/css';
            $fileSize = strlen($cssContent);
            $stmt->bind_param('isssi', $projectId, $fileName, $cssContent, $mimeType, $fileSize);
            $stmt->execute();
            
            // Create main.py with idegui import
            $pythonContent = <<<PYTHON
# {$projectName}
import idegui as ui

# GUI Title
ui.title("{$projectName}")

# Beispiel: Text-Element
ui.text("Willkommen bei {$projectName}!")

# Beispiel: Button
button = ui.button("Klick mich!")

# Beispiel: Input
name_input = ui.input("Name:", "Ihr Name")

# Event-Handler (wird bei Button-Klick aufgerufen)
def button_clicked(trigger):
    name = ui.get("Name:")
    ui.text(f"Hallo, {name}!")

PYTHON;
            
            $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
            $fileName = 'main.py';
            $mimeType = 'text/plain';
            $fileSize = strlen($pythonContent);
            $stmt->bind_param('isssi', $projectId, $fileName, $pythonContent, $mimeType, $fileSize);
            $stmt->execute();
            
            // Create idegui.py (reference file)
            $ideguiContent = <<<PYTHON
# idegui Referenzdatei
# Diese Datei zeigt die erwarteten API-Ideen, die Laufzeit kann davon abweichen.

def title(text):
    """Setzt den Titel der GUI"""
    return {"type": "title", "text": text}

def text(label="", value=""):
    """Erstellt ein Text-Eingabefeld mit optionalem Label"""
    return {"type": "text", "label": label, "value": value}

def input(label="", value=""):
    """Alias für text() - erstellt ein Text-Eingabefeld"""
    return text(label, value)

def number(label="", value=0):
    """Erstellt ein Zahlen-Eingabefeld"""
    return {"type": "number", "label": label, "value": value}

def select(label="", options=None, value=None):
    """Erstellt ein Dropdown-Auswahlfeld"""
    return {"type": "select", "label": label, "options": options or [], "value": value}

def button(label):
    """Erstellt einen Button"""
    return {"type": "button", "label": label}

def output():
    """Gibt ein Output-Widget zurück"""
    return {"type": "output"}

def get(name):
    """Liest den Wert eines Elements mit data-element Attribut"""
    pass

def set(name, value):
    """Setzt den Wert eines Elements mit data-element Attribut"""
    pass

def print(container_name, *args, sep=" ", end="\\n"):
    """Schreibt Text in einen Container (wie Python print)"""
    pass

def reset(container_name=""):
    """Löscht den Inhalt eines Containers"""
    pass

PYTHON;
            
            $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
            $fileName = 'idegui.py';
            $mimeType = 'text/plain';
            $fileSize = strlen($ideguiContent);
            $stmt->bind_param('isssi', $projectId, $fileName, $ideguiContent, $mimeType, $fileSize);
            $stmt->execute();
            
        } else {
            // Python project: single .py file
            $fileName = $safeName . '.py';
            $content = "# " . $projectName . "\n\n# Start coding here!\n";
            $mimeType = 'text/plain';
            $fileSize = strlen($content);
            
            $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
            $stmt->bind_param('isssi', $projectId, $fileName, $content, $mimeType, $fileSize);
            $stmt->execute();
        }

        // Always create init.py as start script
        $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
        $initName = 'init.py';
        $initMimeType = 'text/plain';
        $initSize = strlen($initContent);
        $stmt->bind_param('isssi', $projectId, $initName, $initContent, $initMimeType, $initSize);
        $stmt->execute();
        
    } catch (Exception $e) {
        // Log error but don't fail project creation
        error_log('Failed to initialize project structure: ' . $e->getMessage());
    }
}

/**
 * Ensure required tables exist - creates them if missing (idempotent)
 */
function ensureProjectFilesTablesExist($conn) {
    try {
        // Check if project_folders table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_folders'");
        if($result->num_rows == 0) {
            // Create project_folders table
            $conn->query("
                CREATE TABLE IF NOT EXISTS project_folders (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    project_id INT UNSIGNED NOT NULL,
                    parent_folder_id INT UNSIGNED,
                    name VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                    FOREIGN KEY (parent_folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
                    INDEX (project_id),
                    INDEX (parent_folder_id),
                    UNIQUE KEY unique_folder_name (project_id, parent_folder_id, name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Check if project_files table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_files'");
        if($result->num_rows == 0) {
            // Create project_files table
            $conn->query("
                CREATE TABLE IF NOT EXISTS project_files (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    project_id INT UNSIGNED NOT NULL,
                    folder_id INT UNSIGNED,
                    name VARCHAR(255) NOT NULL,
                    content MEDIUMTEXT,
                    mime_type VARCHAR(100) DEFAULT 'text/plain',
                    file_size INT UNSIGNED DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                    FOREIGN KEY (folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
                    INDEX (project_id),
                    INDEX (folder_id),
                    UNIQUE KEY unique_file_name (project_id, folder_id, name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Exception $e) {
        error_log('Failed to ensure project files tables: ' . $e->getMessage());
    }
}
