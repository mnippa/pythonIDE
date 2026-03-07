<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->query('DELETE FROM project_files WHERE project_id IN (18, 19)');
$conn->query('DELETE FROM projects WHERE id IN (18, 19)');
echo "Old projects deleted\n";
