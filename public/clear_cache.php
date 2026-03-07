<?php
// Clear PHP OpCache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OpCache geleert<br>";
} else {
    echo "ℹ️ OpCache nicht aktiv<br>";
}

// Clear APCu cache if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✅ APCu Cache geleert<br>";
} else {
    echo "ℹ️ APCu nicht aktiv<br>";
}

echo "<br><a href='assignment_editor.php?id=21'>→ Zurück zum Editor</a>";
