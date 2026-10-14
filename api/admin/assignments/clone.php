<?php
/**
 * Clone Assignment with all Tasks
 */

header('Content-Type: application/json; charset=utf-8');

// Manual authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../auth/middleware.php';
    require_once __DIR__ . '/../../../components/assignment_semesters.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Config load failed: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    $input = [];
}

$assignmentId = $_GET['id'] ?? $input['id'] ?? null;
$hasTargetSemester = array_key_exists('semester', $input) || array_key_exists('semester', $_GET);
$targetSemesterRaw = array_key_exists('semester', $input) ? $input['semester'] : ($_GET['semester'] ?? null);
$targetSemester = $hasTargetSemester ? normalizeAssignmentSemester($targetSemesterRaw) : null;

if ($hasTargetSemester && $targetSemesterRaw !== null && trim((string)$targetSemesterRaw) !== '' && $targetSemester === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid semester. Expected WS2627 or SS27']);
    exit;
}

if (!$assignmentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Assignment ID required. Use ?id=X']);
    exit;
}

try {
    $admin = requireAdmin();
    $conn = getDbConnection();
    requireAdminOwnedAssignment($conn, (int)$assignmentId, $admin);
    
    // Start transaction
    $conn->begin_transaction();

    if ($hasTargetSemester) {
        registerAssignmentSemester($conn, $targetSemester);
    }
    
    // Get original assignment
    $stmt = $conn->prepare('SELECT * FROM assignments WHERE id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        throw new Exception('Assignment not found');
    }
    
    // Create new assignment with "(Copy)" suffix and preserve every current schema field.
    $newTitle = $assignment['title'] . ' (Copy)';
    $assignmentColumns = getCloneableColumns($conn, 'assignments', ['id', 'title', 'created_by', 'created_at', 'updated_at']);
    $quotedAssignmentColumns = implode(', ', array_map('quoteIdentifier', $assignmentColumns));
    $stmt = $conn->prepare(
        'INSERT INTO assignments (`title`, `created_by`, ' . $quotedAssignmentColumns . ')
         SELECT ?, ?, ' . $quotedAssignmentColumns . ' FROM assignments WHERE id = ?'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $userId = $admin['id'];
    $sourceAssignmentId = (int)$assignmentId;
    $stmt->bind_param('sii', $newTitle, $userId, $sourceAssignmentId);
    if (!$stmt->execute()) {
        throw new Exception('Insert assignment failed: ' . $stmt->error);
    }
    
    $newAssignmentId = $conn->insert_id;
    if ($newAssignmentId === 0) {
        throw new Exception('Failed to get new assignment ID');
    }
    
    $stmt->close();

    if ($hasTargetSemester) {
        $stmt = $conn->prepare('UPDATE assignments SET semester = ? WHERE id = ?');
        $stmt->bind_param('si', $targetSemester, $newAssignmentId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to set cloned assignment semester: ' . $stmt->error);
        }
        $stmt->close();
    }
    
    // Get all tasks from original assignment
    $stmt = $conn->prepare('SELECT * FROM tasks WHERE assignment_id = ? ORDER BY position ASC');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $tasksResult = $stmt->get_result();
    $tasks = [];
    while ($row = $tasksResult->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
    
    $createdTaskDirectories = [];
    $taskIdMap = [];
    $taskColumns = getCloneableColumns($conn, 'tasks', ['id', 'assignment_id', 'created_at', 'updated_at']);

    // Clone each task with every current schema column.
    foreach ($tasks as $task) {
        $quotedColumns = implode(', ', array_map('quoteIdentifier', $taskColumns));
        $stmt = $conn->prepare(
            'INSERT INTO tasks (`assignment_id`, ' . $quotedColumns . ')
             SELECT ?, ' . $quotedColumns . ' FROM tasks WHERE id = ?'
        );
        $oldTaskId = (int)$task['id'];
        $stmt->bind_param('ii', $newAssignmentId, $oldTaskId);
        if (!$stmt->execute()) {
            throw new Exception('Task clone failed: ' . $stmt->error);
        }
        $newTaskId = $conn->insert_id;
        $stmt->close();

        if ($newTaskId <= 0) {
            throw new Exception('Failed to get cloned task ID');
        }
        $taskIdMap[$oldTaskId] = $newTaskId;

        cloneTaskOptions($conn, $oldTaskId, $newTaskId);

        $sourceDirectory = __DIR__ . '/../../../storage/tasks/folders/task_' . $oldTaskId;
        $targetDirectory = __DIR__ . '/../../../storage/tasks/folders/task_' . $newTaskId;
        if (is_dir($sourceDirectory)) {
            copyDirectoryRecursive($sourceDirectory, $targetDirectory);
            $createdTaskDirectories[] = $targetDirectory;
        }
    }

    cloneAssignmentFiles($conn, (int)$assignmentId, $newAssignmentId, $taskIdMap);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Assignment cloned successfully',
        'new_assignment_id' => $newAssignmentId,
        'task_count' => count($tasks),
        'semester' => $hasTargetSemester ? $targetSemester : ($assignment['semester'] ?? null)
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    foreach (array_reverse($createdTaskDirectories ?? []) as $directory) {
        removeDirectoryRecursive($directory);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function getCloneableColumns(mysqli $conn, string $table, array $excluded): array
{
    $result = $conn->query('SHOW COLUMNS FROM ' . quoteIdentifier($table));
    if (!$result) {
        throw new Exception("Could not inspect {$table} schema");
    }

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $column = (string)$row['Field'];
        if (!in_array($column, $excluded, true)) {
            $columns[] = $column;
        }
    }
    if (count($columns) === 0) {
        throw new Exception("No cloneable columns found for {$table}");
    }
    return $columns;
}

function cloneTaskOptions(mysqli $conn, int $oldTaskId, int $newTaskId): void
{
    if (!dbTableExists($conn, 'task_options')) {
        return;
    }

    $columns = getCloneableColumns($conn, 'task_options', ['id', 'task_id', 'created_at', 'updated_at']);
    $quotedColumns = implode(', ', array_map('quoteIdentifier', $columns));
    $stmt = $conn->prepare(
        'INSERT INTO task_options (`task_id`, ' . $quotedColumns . ')
         SELECT ?, ' . $quotedColumns . ' FROM task_options WHERE task_id = ?'
    );
    $stmt->bind_param('ii', $newTaskId, $oldTaskId);
    if (!$stmt->execute()) {
        throw new Exception('Task option clone failed: ' . $stmt->error);
    }
    $stmt->close();
}

function cloneAssignmentFiles(mysqli $conn, int $oldAssignmentId, int $newAssignmentId, array $taskIdMap): void
{
    if (!dbTableExists($conn, 'assignment_files')) {
        return;
    }

    $columns = getCloneableColumns($conn, 'assignment_files', ['id', 'assignment_id', 'task_id', 'created_at', 'updated_at']);
    $quotedColumns = implode(', ', array_map('quoteIdentifier', $columns));
    $select = $conn->prepare('SELECT id, task_id FROM assignment_files WHERE assignment_id = ? ORDER BY id');
    $select->bind_param('i', $oldAssignmentId);
    $select->execute();
    $rows = $select->get_result()->fetch_all(MYSQLI_ASSOC);
    $select->close();

    foreach ($rows as $row) {
        $oldTaskId = $row['task_id'] !== null ? (int)$row['task_id'] : null;
        $newTaskId = $oldTaskId !== null ? ($taskIdMap[$oldTaskId] ?? null) : null;
        if ($oldTaskId !== null && $newTaskId === null) {
            throw new Exception('Could not map assignment file task ID');
        }

        $stmt = $conn->prepare(
            'INSERT INTO assignment_files (`assignment_id`, `task_id`, ' . $quotedColumns . ')
             SELECT ?, ?, ' . $quotedColumns . ' FROM assignment_files WHERE id = ?'
        );
        $fileId = (int)$row['id'];
        $stmt->bind_param('iii', $newAssignmentId, $newTaskId, $fileId);
        if (!$stmt->execute()) {
            throw new Exception('Assignment file clone failed: ' . $stmt->error);
        }
        $stmt->close();
    }
}

function copyDirectoryRecursive(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
        throw new Exception("Could not create task directory: {$target}");
    }

    $iterator = new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $item) {
        $targetPath = $target . DIRECTORY_SEPARATOR . $item->getFilename();
        if ($item->isLink()) {
            throw new Exception('Symlinks are not supported in task folders');
        }
        if ($item->isDir()) {
            copyDirectoryRecursive($item->getPathname(), $targetPath);
        } elseif (!copy($item->getPathname(), $targetPath)) {
            throw new Exception("Could not copy task file: {$item->getPathname()}");
        }
    }
}

function removeDirectoryRecursive(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            removeDirectoryRecursive($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($directory);
}
