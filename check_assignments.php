<?php
/**
 * Check all assignments in database
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

// Get all assignments
$result = $db->query("
    SELECT id, title, is_active 
    FROM assignments 
    ORDER BY id
");

echo "=== ALLE ASSIGNMENTS IN DER DATENBANK ===\n\n";
while ($row = $result->fetch_assoc()) {
    $status = $row['is_active'] ? '✓' : '✗';
    echo "ID: {$row['id']} | Status: $status | Titel: {$row['title']}\n";
}

echo "\n=== ANZAHL ===\n";
$count = $db->query("SELECT COUNT(*) as total FROM assignments")->fetch_assoc();
echo "Gesamt: {$count['total']}\n";

$active = $db->query("SELECT COUNT(*) as total FROM assignments WHERE is_active = TRUE")->fetch_assoc();
echo "Aktiv: {$active['total']}\n";

$inactive = $db->query("SELECT COUNT(*) as total FROM assignments WHERE is_active = FALSE")->fetch_assoc();
echo "Inaktiv: {$inactive['total']}\n";
