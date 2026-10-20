# Test Workflows Documentation

## Overview

This directory contains test scripts for verifying assignment editor functionality, particularly focusing on race conditions in template/solution mode switching.

**Location:** `/scripts/test_workflows/` (automatically excluded from `copy-beta` deployment)

## Test Scripts

### Admin simulator versus participant progress

`public/editor_assignment_test.php` is a simulator, both when opened for an
assignment and when opened with a `task_id`. It must not load or write participant
progress: saved answers, completion status, check counts, run counts, and activity
heartbeats stay separate from `user_tasks`. Reopening the simulator starts fresh.
Existing database submissions are not deleted.

This is a view-specific rule, not an admin-role rule. An admin working on an
assigned assignment in the normal participant editor still loads and saves real
progress. The separate `editor_assignment_user_test.php` view retains its
explicit selected-user behavior.

Explicit template and solution saves in the admin task lab continue to update
task definitions. Do not enable the blanket `TEST_MODE_NO_PERSIST` flag to fix
progress isolation: that flag also disables these authoring operations.

Task and assignment locks in this simulator block persistence, not local
experimentation: template and solution code remain editable, Undo/Redo and
checks remain available (subject to the check limit). Explicit read-only file
policies still apply. Saving still requires an unlocked task and assignment,
enforced by both the existing save guard and the backend. Participant and
selected-user editor locking rules are unchanged.

Run the isolated JavaScript regression tests without a database or browser:

```powershell
node --test scripts\test_workflows\admin_simulator_progress.test.cjs
```

### playwright_toggle_workflow.cjs

**Purpose:** Tests the exact user workflow that previously manifested race conditions during template/solution mode toggling.

**What it tests:**
1. Load assignment/task in template mode
2. Run template code → captures output with template markers (🟨)
3. Edit code (simulated)
4. Save template
5. Toggle to solution mode
6. Run solution → captures output with solution markers (🟩)
7. Toggle **back** to template mode
8. Run template **again** → CRITICAL: must show template markers, not solution markers
9. Verify outputs match expected markers

**Why it matters:**
This reproduces the production race condition where:
- Frontend UI shows template mode (sync update)
- But Pyodide filesystem still has solution files (async sync not complete)
- Result: Run executes solution code instead of template code

---

## Running Tests

### Local Environment

```bash
# Navigate to pythonIDE root
cd C:\xampp\htdocs\pythonIDE

# Run test against local Beta
TARGET_URL="http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338" \
node scripts/test_workflows/playwright_toggle_workflow.cjs
```

**Local Configuration:**
- Assignment ID: `32`
- Task ID: `338` (task name: "01_Spielfeld")
- Expected: **PASS** (different timing allows fixes to work)

### Production Environment

```bash
# Run test against production Beta
TARGET_URL="https://winglearning.hs-pforzheim.de/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=38&task_id=325" \
node scripts/test_workflows/playwright_toggle_workflow.cjs
```

**Production Configuration:**
- Assignment ID: `38`
- Task ID: `325` (task name: "01_Spielfeld")
- Expected (as of last test): **FAIL** due to faster execution timing

---

## Test Results

Results are saved to: `/storage/test_results/toggle_workflow_*.json`

### Result Structure

```json
{
  "passed": true,
  "timestamp": "2026-05-22T14:30:45.123Z",
  "url": "http://localhost/pythonIDEBeta/...",
  "markers": {
    "templateRun1": true,
    "solutionRun": true,
    "templateRun2": true,
    "race_condition_detected": false
  },
  "counts": {
    "templateRun1": { "template": 3, "solution": 0 },
    "solutionRun": { "template": 0, "solution": 3 },
    "templateRun2": { "template": 3, "solution": 0 }
  }
}
```

**Key fields:**
- `passed`: Test passed all markers
- `race_condition_detected`: Frontend/Pyodide sync mismatch detected
- `counts`: Color marker counts (🟨 = template, 🟩 = solution)

---

## Pass/Fail Criteria

### ✓ PASS
- Template run 1: Shows **template markers** (🟨)
- Solution run: Shows **solution markers** (🟩)
- Template run 2: Shows **template markers** (🟨) - **CRITICAL**
- No solution markers in final template run

### ✗ FAIL (Race Condition)
- Template run 1: ✓ Template markers
- Solution run: ✓ Solution markers
- Template run 2: ✗ **Solution markers instead of template markers**
  - Indicates: Frontend toggled mode back to template, but Pyodide still executing solution code

---

## Known Issues & Environment Differences

### Production-Specific Race Condition

**Observation:** Production environment fails the test while local passes with identical code.

**Root Cause:** Production's faster execution creates different async timing windows.

**Race Sequence:**
1. Frontend calls `toggleSolutionMode()` → immediately updates UI state (sync)
2. This triggers Pyodide file sync to copy template files (async)
3. User clicks Run (or Run auto-executes)
4. On production (fast): API responds quickly, files syncing, Run executes old code still in Pyodide
5. On local (slow): Sync completes before Run executes

**Timeline Difference:**
- Local XAMPP: ~500ms API response → Async operation completes before Run trigger
- Production winglearning: ~50ms API response → Async operation may not complete

---

## Architecture Solution

See `/docs/ARCHITECTURE_SOLUTIONS.md` for proposed solutions to eliminate competing async scenarios entirely.

### Quick Overview

Four architectural approaches to eliminate the race:

1. **Queue-based Sync**: Explicit barrier waits for all Pyodide file writes before Run
2. **Stateful Snapshot**: Immutable snapshot of "current execution scope files" at Run time
3. **Debounced Mode**: Mode toggle waits for Pyodide sync to complete before allowing Run
4. **Separate Execution Contexts**: Two isolated Pyodide instances (template/solution) with swap-on-toggle

---

## Dependencies

- **Playwright** (already in project)
- **Node.js** (v14+)
- **msedge** channel (uses Chromium-based Edge for automation)

```bash
# Ensure Playwright is installed with browsers
npm install --save-dev playwright
npx playwright install msedge
```

---

## Maintenance Notes

- Test scripts are **automatically excluded** from `copy-beta` deployment
- Stored in `/scripts/test_workflows/` which is not in the deployment whitelist
- Safe to create new test scripts here without affecting production
- Results are saved to `/storage/test_results/` for analysis

---

## Future Improvements

- [ ] Add stress-test mode (run cycle 10x to catch flaky races)
- [ ] Add timing instrumentation to measure API/Pyodide sync durations
- [ ] Add performance profiling comparison (local vs production)
- [ ] Integrate with CI/CD pipeline for automated regression detection
- [ ] Add tests for other state-related race conditions (e.g., file save during mode switch)
