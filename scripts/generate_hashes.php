<?php
// Generate password hashes for default users

echo "Admin (admin123): " . password_hash('admin123', PASSWORD_DEFAULT) . "\n";
echo "Demo (user123): " . password_hash('user123', PASSWORD_DEFAULT) . "\n";
