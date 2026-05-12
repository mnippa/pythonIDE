<?php
/**
 * Migration 053: Backfill explicit flags in user_assignments.
 *
 * Purpose:
 * - Ensure rework rows carry is_rework=1
 * - Map legacy late statuses to explicit flags
 *
 * Default target: local DB via config/database.php
 * Optional live target: set USE_BETA_LIVE_DB=1 for this process.
 *
 * Run local:
 *   php sql/migrations/run_053.php
 * Run live (local helper only):
 *   USE_BETA_LIVE_DB=1 php sql/migrations/run_053.php
 */

$useLive = getenv('USE_BETA_LIVE_DB') === '1';

if ($useLive) {
    if (!defined('BETA_LIVE_ALLOW_WRITE')) {
        define('BETA_LIVE_ALLOW_WRITE', true);
    }
    require_once __DIR__ . '/../../config/database.beta_live.local.php';
    $conn = getBetaLiveDbConnection();
    echo "Target DB: LIVE (beta_live helper)\n";
} else {
    require_once __DIR__ . '/../../config/database.php';
    $conn = getDbConnection();
    echo "Target DB: LOCAL\n";
}

try {
    echo "Starting migration 053: backfill assignment flags...\n";

    $conn->begin_transaction();

    // 1) Current workflow rows: explicit rework flag for rework status.
    $sqlRework = "
        UPDATE user_assignments
        SET is_rework = 1
        WHERE status = 'rework'
          AND COALESCE(is_rework, 0) = 0
    ";
    $conn->query($sqlRework);
    $reworkUpdated = (int)$conn->affected_rows;

    // 2) Legacy status: late_completed -> submitted + is_late=1.
    $sqlLateCompleted = "
        UPDATE user_assignments
        SET status = 'submitted',
            is_late = 1
        WHERE status = 'late_completed'
    ";
    $conn->query($sqlLateCompleted);
    $lateCompletedUpdated = (int)$conn->affected_rows;

    // 3) Legacy status: passed_delayed -> passed + is_late=1.
    $sqlPassedDelayed = "
        UPDATE user_assignments
        SET status = 'passed',
            is_late = 1
        WHERE status = 'passed_delayed'
    ";
    $conn->query($sqlPassedDelayed);
    $passedDelayedUpdated = (int)$conn->affected_rows;

    $conn->commit();

    echo "✓ Rework rows backfilled: {$reworkUpdated}\n";
    echo "✓ Legacy late_completed mapped: {$lateCompletedUpdated}\n";
    echo "✓ Legacy passed_delayed mapped: {$passedDelayedUpdated}\n";
    echo "\n✅ Migration 053 completed successfully.\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo "\n❌ Migration 053 failed: " . $e->getMessage() . "\n";
    exit(1);
}
