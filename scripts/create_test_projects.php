<?php
/**
 * Create test projects for HTML/CSS testing
 * Run from CLI: php scripts/create_test_projects.php
 */

// Direct database connection for CLI
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error . "\n");
}

$conn->set_charset('utf8mb4');

// Test user: Markus2
$userEmail = 'markus2@example.com';
$firstName = 'Markus';
$lastName = 'Test2';
$userPassword = 'test123';

// Check if user exists
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');

if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

$stmt->bind_param('s', $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();

if (!$user) {
    echo "Creating user Markus2...\n";
    // Create user
    $passwordHash = password_hash($userPassword, PASSWORD_BCRYPT);
    $role = 'user';
    
    $insertStmt = $conn->prepare('INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES (?, ?, ?, ?, ?)');
    $insertStmt->bind_param('sssss', $userEmail, $firstName, $lastName, $passwordHash, $role);
    if ($insertStmt->execute()) {
        $userId = $conn->insert_id;
        echo "✓ User Markus2 created (ID: $userId)\n";
    } else {
        die("Failed to create user: " . $conn->error);
    }
} else {
    $userId = $user['id'];
    echo "Using existing user Markus2 (ID: $userId)\n";
}

// Ensure project_files and project_folders tables exist
ensureProjectFilesTablesExist($conn);

// Project 1: Task 170 - MwSt-Rechner (Single Input)
echo "\n--- Creating Project 1: Task 170 - MwSt-Rechner ---\n";

$projectName1 = 'Task 170 - MwSt-Rechner';
$projectDesc1 = 'Berechne Bruttopreis aus Nettopreis mit 19% MwSt';
$projectType1 = 'html';

// Check if project exists
$stmt = $conn->prepare('SELECT id FROM projects WHERE user_id = ? AND name = ?');
$stmt->bind_param('is', $userId, $projectName1);
$stmt->execute();
$projectCheck = $stmt->get_result();

if ($projectCheck->num_rows > 0) {
    $project1 = $projectCheck->fetch_assoc();
    $projectId1 = $project1['id'];
    echo "Project already exists (ID: $projectId1)\n";
} else {
    // Create project
    $insertStmt = $conn->prepare('
        INSERT INTO projects (user_id, name, description, project_type, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $insertStmt->bind_param('isss', $userId, $projectName1, $projectDesc1, $projectType1);
    
    if ($insertStmt->execute()) {
        $projectId1 = $conn->insert_id;
        echo "✓ Project created (ID: $projectId1)\n";
    } else {
        die("Failed to create project 1: " . $conn->error);
    }
}

// Create files for Project 1
createProjectFile($conn, $projectId1, null, 'init.py', <<<'PYTHON'
# Musterlösung: MwSt-Rechner

# Nettopreis vom Benutzer einlesen
netto = float(input("Nettopreis in Euro: "))

# Bruttopreis berechnen (19% MwSt)
brutto = netto * 1.19

# Ergebnis ausgeben
print(f"Bruttopreis: {brutto:.2f} Euro")
print(f"(enthält {netto * 0.19:.2f} Euro MwSt)")
PYTHON
);

createProjectFile($conn, $projectId1, null, 'index.html', <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MwSt-Rechner</title>
</head>
<body>
    <div class="container">
        <h1>MwSt-Rechner (19%)</h1>
        <p>Geben Sie einen Nettopreis ein, um den Bruttopreis zu berechnen.</p>
        
        <div class="input-group">
            <label for="nettoInput">Nettopreis (€):</label>
            <input type="number" id="nettoInput" placeholder="z.B. 100" step="0.01" min="0">
        </div>
        
        <button id="calculateBtn" data-run-python="true">Berechnen</button>
        
        <div id="result" class="result hidden">
            <h2>Ergebnis:</h2>
            <p>Bruttopreis: <strong id="bruttoPrice">-</strong> €</p>
            <p>MwSt (19%): <strong id="mwstAmount">-</strong> €</p>
        </div>
    </div>

    <script>
    // Handle calculation trigger
    function handleCalculate() {
        const nettoInput = document.getElementById('nettoInput');
        const resultDiv = document.getElementById('result');
        const bruttoPrice = document.getElementById('bruttoPrice');
        const mwstAmount = document.getElementById('mwstAmount');
        
        if (!nettoInput.value) {
            alert('Bitte Nettopreis eingeben');
            return;
        }
        
        const netto = parseFloat(nettoInput.value);
        const brutto = netto * 1.19;
        const mwst = netto * 0.19;
        
        bruttoPrice.textContent = brutto.toFixed(2);
        mwstAmount.textContent = mwst.toFixed(2);
        resultDiv.classList.remove('hidden');
    }
    
    // Bind button to calculate function
    document.getElementById('calculateBtn').addEventListener('click', handleCalculate);
    </script>
</body>
</html>
HTML
);

createProjectFile($conn, $projectId1, null, 'style.css', <<<'CSS'
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    padding: 40px;
    max-width: 500px;
    width: 100%;
}

h1 {
    color: #333;
    margin-bottom: 10px;
    font-size: 28px;
}

p {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

.input-group {
    margin-bottom: 20px;
}

label {
    display: block;
    color: #333;
    font-weight: 600;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    transition: border-color 0.3s;
}

input:focus {
    outline: none;
    border-color: #667eea;
}

button {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

button:active {
    transform: translateY(0);
}

.result {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #667eea;
}

.result.hidden {
    display: none;
}

.result h2 {
    color: #333;
    font-size: 18px;
    margin-bottom: 15px;
}

.result p {
    margin-bottom: 10px;
    color: #555;
}

#bruttoPrice, #mwstAmount {
    color: #667eea;
    font-weight: 700;
}
CSS
);

echo "✓ Files created for Project 1\n";

// Project 2: Task 172 - MwSt-Rechner (2 Inputs)
echo "\n--- Creating Project 2: Task 172 - MwSt-Rechner (2 Eingaben) ---\n";

$projectName2 = 'Task 172 - MwSt-Rechner (2 Eingaben)';
$projectDesc2 = 'Berechne Bruttopreis aus Nettopreis mit variablem MwSt-Satz';
$projectType2 = 'html';

// Check if project exists
$stmt = $conn->prepare('SELECT id FROM projects WHERE user_id = ? AND name = ?');
$stmt->bind_param('is', $userId, $projectName2);
$stmt->execute();
$projectCheck = $stmt->get_result();

if ($projectCheck->num_rows > 0) {
    $project2 = $projectCheck->fetch_assoc();
    $projectId2 = $project2['id'];
    echo "Project already exists (ID: $projectId2)\n";
} else {
    $insertStmt = $conn->prepare('
        INSERT INTO projects (user_id, name, description, project_type, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $insertStmt->bind_param('isss', $userId, $projectName2, $projectDesc2, $projectType2);
    
    if ($insertStmt->execute()) {
        $projectId2 = $conn->insert_id;
        echo "✓ Project created (ID: $projectId2)\n";
    } else {
        die("Failed to create project 2: " . $conn->error);
    }
}

// Create files for Project 2
createProjectFile($conn, $projectId2, null, 'init.py', <<<'PYTHON'
# Musterlösung: MwSt-Rechner mit variablem MwSt-Satz

# Eingaben vom Benutzer
netto = float(input("Nettopreis in Euro: "))
mwst_satz = float(input("MwSt-Satz (%): "))

# Umrechnung: Prozent in Dezimal
mwst_faktor = 1 + (mwst_satz / 100)

# Bruttopreis berechnen
brutto = netto * mwst_faktor

# MwSt-Betrag
mwst_betrag = netto * (mwst_satz / 100)

# Ergebnis ausgeben
print(f"Bruttopreis: {brutto:.2f} Euro")
print(f"MwSt ({mwst_satz}%): {mwst_betrag:.2f} Euro")
PYTHON
);

createProjectFile($conn, $projectId2, null, 'index.html', <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MwSt-Rechner (variabel)</title>
</head>
<body>
    <div class="container">
        <h1>MwSt-Rechner</h1>
        <p>Geben Sie Nettopreis und MwSt-Satz ein.</p>
        
        <div class="input-group">
            <label for="nettoInput">Nettopreis (€):</label>
            <input type="number" id="nettoInput" placeholder="z.B. 100" step="0.01" min="0">
        </div>
        
        <div class="input-group">
            <label for="mwstSatzInput">MwSt-Satz (%):</label>
            <input type="number" id="mwstSatzInput" placeholder="z.B. 19" step="0.01" min="0" max="100" value="19">
        </div>
        
        <button id="calculateBtn" data-run-python="true">Berechnen</button>
        
        <div id="result" class="result hidden">
            <h2>Ergebnis:</h2>
            <p>Bruttopreis: <strong id="bruttoPrice">-</strong> €</p>
            <p>MwSt: <strong id="mwstAmount">-</strong> €</p>
        </div>
    </div>

    <script>
    // Handle calculation trigger
    function handleCalculate() {
        const nettoInput = document.getElementById('nettoInput');
        const mwstSatzInput = document.getElementById('mwstSatzInput');
        const resultDiv = document.getElementById('result');
        const bruttoPrice = document.getElementById('bruttoPrice');
        const mwstAmount = document.getElementById('mwstAmount');
        
        if (!nettoInput.value) {
            alert('Bitte Nettopreis eingeben');
            return;
        }
        
        const netto = parseFloat(nettoInput.value);
        const mwstSatz = parseFloat(mwstSatzInput.value) || 19;
        const faktor = 1 + (mwstSatz / 100);
        const brutto = netto * faktor;
        const mwst = netto * (mwstSatz / 100);
        
        bruttoPrice.textContent = brutto.toFixed(2);
        mwstAmount.textContent = mwst.toFixed(2);
        resultDiv.classList.remove('hidden');
    }
    
    // Bind button to calculate function
    document.getElementById('calculateBtn').addEventListener('click', handleCalculate);
    </script>
</body>
</html>
HTML
);

createProjectFile($conn, $projectId2, null, 'style.css', <<<'CSS'
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    padding: 40px;
    max-width: 500px;
    width: 100%;
}

h1 {
    color: #333;
    margin-bottom: 10px;
    font-size: 28px;
}

p {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

.input-group {
    margin-bottom: 20px;
}

label {
    display: block;
    color: #333;
    font-weight: 600;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    transition: border-color 0.3s;
}

input:focus {
    outline: none;
    border-color: #f5576c;
}

button {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(245, 87, 108, 0.4);
}

button:active {
    transform: translateY(0);
}

.result {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #f5576c;
}

.result.hidden {
    display: none;
}

.result h2 {
    color: #333;
    font-size: 18px;
    margin-bottom: 15px;
}

.result p {
    margin-bottom: 10px;
    color: #555;
}

#bruttoPrice, #mwstAmount {
    color: #f5576c;
    font-weight: 700;
}
CSS
);

echo "✓ Files created for Project 2\n";

echo "\n=== DONE ===\n";
echo "Projects created successfully!\n";
echo "User: Markus2 ($userEmail)\n";
echo "Project 1: Task 170 - MwSt-Rechner (ID: $projectId1)\n";
echo "Project 2: Task 172 - MwSt-Rechner (2 Eingaben) (ID: $projectId2)\n";

// Helper function to create a file
function createProjectFile($conn, $projectId, $folderId, $fileName, $content) {
    // Check if file already exists
    if ($folderId === null || $folderId === '' || (int)$folderId === 0) {
        $checkStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = ?');
        $checkStmt->bind_param('is', $projectId, $fileName);
    } else {
        $folderId = (int)$folderId;
        $checkStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id = ? AND name = ?');
        $checkStmt->bind_param('iis', $projectId, $folderId, $fileName);
    }
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if ($existing) {
        echo "  - File already exists: $fileName\n";
        return;
    }
    
    // Create file
    $insertStmt = $conn->prepare('
        INSERT INTO project_files (project_id, folder_id, name, content, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    
    if ($folderId === null || $folderId === '' || (int)$folderId === 0) {
        $folderId = null;
    }
    
    $insertStmt->bind_param('iiss', $projectId, $folderId, $fileName, $content);
    
    if ($insertStmt->execute()) {
        echo "  ✓ Created: $fileName\n";
    } else {
        echo "  ✗ Failed to create $fileName: " . $conn->error . "\n";
    }
}

// Helper function to ensure tables exist
function ensureProjectFilesTablesExist($conn) {
    // Create project_files table
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS project_files (
        id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        folder_id INT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        content LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
        UNIQUE KEY unique_file_in_folder (project_id, folder_id, name)
    )
SQL;
    
    $conn->query($sql);
    
    // Create project_folders table  
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS project_folders (
        id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        parent_id INT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES project_folders(id) ON DELETE CASCADE
    )
SQL;
    
    $conn->query($sql);
}
