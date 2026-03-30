<?php
/**
 * Authentication Middleware & Helpers
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Check if user is logged in
 */
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['ok' => false, 'error' => 'Authentication required'], 401);
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'] ?? $_SESSION['username'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Check if user has admin role
 */
function requireAdmin() {
    $user = requireAuth();
    
    if ($user['role'] !== 'admin') {
        jsonResponse(['ok' => false, 'error' => 'Admin access required'], 403);
    }
    
    return $user;
}

/**
 * Require that the current admin owns the assignment.
 */
function requireAdminOwnedAssignment(mysqli $conn, int $assignmentId, ?array $admin = null): array {
    $admin = $admin ?? requireAdmin();

    $stmt = $conn->prepare('SELECT * FROM assignments WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();

    if (!$assignment) {
        jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
    }

    return $assignment;
}

/**
 * Require that the current admin owns the assignment of the given task.
 */
function requireAdminOwnedTask(mysqli $conn, int $taskId, ?array $admin = null): array {
    $admin = $admin ?? requireAdmin();

    $stmt = $conn->prepare('
        SELECT t.*, a.created_by
        FROM tasks t
        INNER JOIN assignments a ON a.id = t.assignment_id
        WHERE t.id = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();

    if (!$task) {
        jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
    }

    return $task;
}

/**
 * Get current user (if logged in)
 */
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'role' => $_SESSION['role']
    ];
}

/**
 * Check if user is logged in (boolean)
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin (boolean)
 */
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
