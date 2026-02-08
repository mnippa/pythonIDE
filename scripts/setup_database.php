<?php
/**
 * Database Setup Script
 * Run this once to create the pythonide database and tables
 */

$host = 'localhost';
$user = 'root';
$pass = 'start123';
$dbname = 'pythonide';

// Connect without database first to create it
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to MySQL server\n\n";

// Create database first
echo "Creating database '$dbname'...\n";
if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    echo "✓ Database created or already exists\n\n";
} else {
    die("✗ Error creating database: " . $conn->error . "\n");
}

// Select database
if (!$conn->select_db($dbname)) {
    die("✗ Error selecting database: " . $conn->error . "\n");
}

// Read and execute schema
$schema = file_get_contents(__DIR__ . '/../sql/schema.sql');

if ($schema === false) {
    die("Could not read schema.sql\n");
}

// Remove comments and empty lines
$lines = explode("\n", $schema);
$filteredLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    // Skip comments, empty lines, and database management statements
    if (empty($trimmed) || 
        $trimmed[0] === '-' || 
        stripos($trimmed, 'CREATE DATABASE') === 0 || 
        stripos($trimmed, 'USE ') === 0) {
        continue;
    }
    $filteredLines[] = $line;
}
$schema = implode("\n", $filteredLines);

echo "Parsing SQL schema...\n";
// Split by semicolon and execute each statement
$statements = explode(';', $schema);
echo "Found " . count($statements) . " potential statements\n\n";

$success = 0;
$errors = 0;
$skipped = 0;

foreach ($statements as $i => $statement) {
    $statement = trim($statement);
    
    // Skip empty statements and comments
    if (empty($statement) || preg_match('/^\s*--/', $statement)) {
        $skipped++;
        continue;
    }
    
    // Debug first statement
    if ($i == 0 && !empty($statement)) {
        echo "First non-empty statement preview:\n";
        echo substr($statement, 0, 200) . "\n\n";
    }
    
    if ($conn->query($statement)) {
        $success++;
        $preview = strlen($statement) > 60 ? substr($statement, 0, 60) . '...' : $statement;
        echo "✓ " . str_replace("\n", " ", $preview) . "\n";
    } else {
        $errors++;
        echo "✗ Error: " . $conn->error . "\n";
        echo "  Statement: " . substr($statement, 0, 150) . "...\n\n";
    }
}

$conn->close();

echo "\n========================================\n";
echo "Database setup completed!\n";
echo "Success: $success statements\n";
echo "Errors: $errors statements\n";
echo "Skipped: $skipped (empty/comments)\n";
echo "========================================\n";
echo "\nDefault accounts created:\n";
echo "Admin: admin / admin123\n";
echo "Demo:  demo / user123\n";
