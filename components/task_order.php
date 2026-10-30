<?php

function lockAssignmentTaskOrder(mysqli $conn, int $assignmentId): void {
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ? FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare assignment order lock: ' . $conn->error);
    }
    $stmt->bind_param('i', $assignmentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not lock assignment order: ' . $stmt->error);
    }
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('Assignment not found');
    }
}

/**
 * Caller holds the assignment row lock within a transaction.
 * Equal legacy positions retain a deterministic order by task ID.
 */
function normalizeTaskPositions(mysqli $conn, int $assignmentId, ?int $movedTaskId = null, ?int $targetPosition = null): array {
    $stmt = $conn->prepare('SELECT id, position FROM tasks WHERE assignment_id = ? ORDER BY position ASC, id ASC FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare task order query: ' . $conn->error);
    }
    $stmt->bind_param('i', $assignmentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not read task order: ' . $stmt->error);
    }
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($movedTaskId !== null) {
        $index = array_search($movedTaskId, array_map('intval', array_column($tasks, 'id')), true);
        if ($index === false || $targetPosition === null || $targetPosition < 1) {
            throw new InvalidArgumentException('Invalid task position');
        }
        $moved = array_splice($tasks, $index, 1);
        array_splice($tasks, min($targetPosition - 1, count($tasks)), 0, $moved);
    }

    $update = $conn->prepare('UPDATE tasks SET position = ? WHERE id = ? AND assignment_id = ?');
    if (!$update) {
        throw new RuntimeException('Could not prepare task position update: ' . $conn->error);
    }
    $positions = [];
    foreach ($tasks as $index => $task) {
        $id = (int)$task['id'];
        $position = $index + 1;
        if ((int)$task['position'] !== $position) {
            $update->bind_param('iii', $position, $id, $assignmentId);
            if (!$update->execute()) {
                throw new RuntimeException('Could not update task position: ' . $update->error);
            }
        }
        $positions[$id] = $position;
    }
    return $positions;
}
