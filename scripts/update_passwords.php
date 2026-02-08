<?php
/**
 * Update default user passwords with correct hashes
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Update admin password
$adminHash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
$stmt->bind_param('ss', $adminHash, $username);
$username = 'admin';
$stmt->execute();
echo "✓ Updated admin password hash\n";

// Update demo password
$demoHash = password_hash('user123', PASSWORD_DEFAULT);
$stmt->bind_param('ss', $demoHash, $username);
$username = 'demo';
$stmt->execute();
echo "✓ Updated demo password hash\n";

echo "\nPasswords updated:\n";
echo "Admin: admin / admin123\n";
echo "Demo:  demo / user123\n";
