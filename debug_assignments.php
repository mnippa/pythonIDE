<?php
/**
 * Debug: Check what the SQL query returns
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== DEBUG: Was die INSERT Query sieht ===\n\n";

// Same query as in the insert
$result = $db->query("
    SELECT u.id as user_id, u.email, a.id as assignment_id, a.title
    FROM users u
    CROSS JOIN assignments a
    WHERE u.role = 'user'
      AND a.is_active = TRUE
    ORDER BY u.id, a.id
");

echo "Anzahl Zeilen: " . $result->num_rows . "\n\n";

while ($row = $result->fetch_assoc()) {
    echo "User {$row['user_id']} ({$row['email']}) => Assignment {$row['assignment_id']} ({$row['title']})\n";
}

echo "\n=== USER PRÜFUNG ===\n";
$users = $db->query("SELECT id, email, role FROM users ORDER BY id");
while ($user = $users->fetch_assoc()) {
    echo "ID: {$user['id']} | Role: {$user['role']} | Email: {$user['email']}\n";
}
