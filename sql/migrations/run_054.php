<?php
/**
 * Migration 054: Backfill is_late flag based on submitted_at > effective_due_date
 * 
 * Updates all user_assignments where:
 *   - submitted_at is not null (item was submitted)
 *   - submitted_at > effective_due_date
 *   - is_late is currently 0 or NULL
 * 
 * To set: is_late = 1
 */

require_once __DIR__ . '/../../config/database.php';

// Allow override via environment variable for beta/live
$useBetaLiveDb = getenv('USE_BETA_LIVE_DB');
if ($useBetaLiveDb) {
    require_once __DIR__ . '/../../config/database.beta_live.local.php';
}

try {
    $conn = $useBetaLiveDb ? getBetaLiveDbConnection() : getDbConnection();
    
    echo "=== Migration 054: Backfill is_late flag ===\n";
    
    // Find count of rows that need updating
    $countSql = '
        SELECT COUNT(*) as cnt FROM user_assignments ua
        WHERE ua.submitted_at IS NOT NULL
        AND COALESCE(ua.is_late, 0) = 0
        AND ua.submitted_at > COALESCE(ua.due_date, (
            SELECT a.due_date FROM assignments a WHERE a.id = ua.assignment_id
        ))
    ';
    
    $countResult = $conn->query($countSql);
    if (!$countResult) {
        throw new Exception('Count query failed: ' . $conn->error);
    }
    
    $countRow = $countResult->fetch_assoc();
    $rowsToUpdate = (int)($countRow['cnt'] ?? 0);
    
    if ($rowsToUpdate === 0) {
        echo "✓ No rows need updating (all late submissions already flagged)\n";
        exit(0);
    }
    
    echo "Found $rowsToUpdate rows to update\n";
    
    // Update rows
    $updateSql = '
        UPDATE user_assignments ua
        SET ua.is_late = 1
        WHERE ua.submitted_at IS NOT NULL
        AND COALESCE(ua.is_late, 0) = 0
        AND ua.submitted_at > COALESCE(ua.due_date, (
            SELECT a.due_date FROM assignments a WHERE a.id = ua.assignment_id
        ))
    ';
    
    $result = $conn->query($updateSql);
    if (!$result) {
        throw new Exception('Update query failed: ' . $conn->error);
    }
    
    $affectedRows = $conn->affected_rows;
    echo "✓ Updated $affectedRows rows\n";
    
    if ($useBetaLiveDb) {
        echo "✓ Updated on BETA/LIVE database\n";
    } else {
        echo "✓ Updated on LOCAL database\n";
    }
    
} catch (Exception $e) {
    echo "✗ Migration 054 failed: " . $e->getMessage() . "\n";
    exit(1);
}
