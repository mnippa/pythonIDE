<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

$sql = "UPDATE tasks
        SET task_text = question_text,
            updated_at = NOW()
        WHERE assignment_id = 33
          AND (task_text IS NULL OR TRIM(task_text) = '')
          AND question_text IS NOT NULL
          AND TRIM(question_text) <> ''";

if (!$conn->query($sql)) {
    fwrite(STDERR, "ERROR|" . $conn->error . PHP_EOL);
    exit(1);
}

echo "UPDATED|" . $conn->affected_rows . PHP_EOL;
