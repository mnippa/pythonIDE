<?php

/**
 * Best-effort audit logging for task template/solution changes.
 * This helper must never break primary write flows.
 */

function taskAuditEnsureTable(mysqli $conn): bool {
    $sql = 'CREATE TABLE IF NOT EXISTS task_code_audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        task_id INT UNSIGNED NOT NULL,
        field_name VARCHAR(64) NOT NULL,
        file_path VARCHAR(1024) NULL,
        change_type VARCHAR(64) NOT NULL,
        old_sha256 CHAR(64) NULL,
        new_sha256 CHAR(64) NULL,
        old_length INT NULL,
        new_length INT NULL,
        actor_user_id INT UNSIGNED NULL,
        actor_role VARCHAR(32) NULL,
        actor_email VARCHAR(255) NULL,
        source_endpoint VARCHAR(255) NULL,
        source_ip VARCHAR(64) NULL,
        user_agent VARCHAR(512) NULL,
        meta_json TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_created (task_id, created_at),
        INDEX idx_actor_created (actor_user_id, created_at),
        INDEX idx_field_created (field_name, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        return (bool)$conn->query($sql);
    } catch (Throwable $e) {
        error_log('taskAuditEnsureTable failed: ' . $e->getMessage());
        return false;
    }
}

function taskAuditLogChange(mysqli $conn, array $entry): bool {
    try {
        $taskId = (int)($entry['task_id'] ?? 0);
        $fieldName = (string)($entry['field_name'] ?? 'unknown');
        $filePath = isset($entry['file_path']) ? (string)$entry['file_path'] : null;
        $changeType = (string)($entry['change_type'] ?? 'unknown');
        $oldContent = array_key_exists('old_content', $entry) ? (string)$entry['old_content'] : null;
        $newContent = array_key_exists('new_content', $entry) ? (string)$entry['new_content'] : null;
        $sourceEndpoint = isset($entry['source_endpoint']) ? (string)$entry['source_endpoint'] : null;

        $actor = is_array($entry['actor'] ?? null) ? $entry['actor'] : [];
        $actorUserId = isset($actor['id']) ? (int)$actor['id'] : null;
        $actorRole = isset($actor['role']) ? (string)$actor['role'] : null;
        $actorEmail = isset($actor['email']) ? (string)$actor['email'] : null;

        $sourceIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null;
        if ($userAgent !== null && strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }

        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : null;
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $oldSha = $oldContent !== null ? hash('sha256', $oldContent) : null;
        $newSha = $newContent !== null ? hash('sha256', $newContent) : null;
        $oldLen = $oldContent !== null ? strlen($oldContent) : null;
        $newLen = $newContent !== null ? strlen($newContent) : null;

        $stmt = $conn->prepare(
            'INSERT INTO task_code_audit_log
            (task_id, field_name, file_path, change_type, old_sha256, new_sha256, old_length, new_length,
             actor_user_id, actor_role, actor_email, source_endpoint, source_ip, user_agent, meta_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            error_log('taskAuditLogChange prepare failed: ' . $conn->error);
            return false;
        }

        $stmt->bind_param(
            'isssssiiissssss',
            $taskId,
            $fieldName,
            $filePath,
            $changeType,
            $oldSha,
            $newSha,
            $oldLen,
            $newLen,
            $actorUserId,
            $actorRole,
            $actorEmail,
            $sourceEndpoint,
            $sourceIp,
            $userAgent,
            $metaJson
        );

        $ok = $stmt->execute();
        if (!$ok) {
            error_log('taskAuditLogChange execute failed: ' . $stmt->error);
        }
        $stmt->close();
        return (bool)$ok;
    } catch (Throwable $e) {
        error_log('taskAuditLogChange failed: ' . $e->getMessage());
        return false;
    }
}
