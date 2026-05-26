# Architecture Solutions: Eliminating Async Race Conditions

## Problem Statement

**Current Race Condition (Production-only)**

```
Time │ Frontend             │ Pyodide FS           │ Execution
─────┼──────────────────────┼──────────────────────┼─────────────
 0ms │ User clicks Toggle   │ (has solution files) │
 5ms │ Update UI to "tmpl"  │ Async: copying files │
10ms │ User clicks Run      │ (still copying)      │
15ms │ Execute requested    │ (copy complete)      │ ← Executes solution!
     │                      │ (has template files) │
```

**Why only on production?**
- Local XAMPP: API response ~500ms, async file copy has time to complete before Run executes
- Production: API response ~50ms, async file copy races with Run click

**Solution Goal:** Eliminate competing async scenarios entirely. The frontend state and Pyodide execution context must be guaranteed in sync before any execution occurs.

---

## Four Architectural Approaches

### Option A: Queue-Based Synchronization (Recommended Quick Fix)

**Concept:** Explicit sync barrier that halts execution until all Pyodide file operations complete.

**Implementation:**

```javascript
// In assignments.js

class PyodideSyncQueue {
  constructor() {
    this.pending = [];
    this.isProcessing = false;
  }

  async enqueue(operation) {
    this.pending.push(operation);
    if (!this.isProcessing) {
      await this.process();
    }
  }

  async process() {
    this.isProcessing = true;
    while (this.pending.length > 0) {
      const operation = this.pending.shift();
      await operation();
    }
    this.isProcessing = false;
  }

  async drain() {
    // Explicit wait for all pending operations to complete
    return new Promise((resolve) => {
      const checkInterval = setInterval(() => {
        if (!this.isProcessing && this.pending.length === 0) {
          clearInterval(checkInterval);
          resolve();
        }
      }, 10);
    });
  }
}

const syncQueue = new PyodideSyncQueue();

// Modified mode toggle
async function loadSolutionIntoMainArea() {
  // ... existing code ...
  
  // Enqueue file sync operation
  await syncQueue.enqueue(async () => {
    await persistPyodideFiles(filesForSolution);
  });
}

async function loadTaskIntoEditor() {
  // Enqueue file sync operation
  await syncQueue.enqueue(async () => {
    await persistPyodideFiles(filesForTemplate);
  });
}

// Modified Run execution
async function executeCode() {
  // CRITICAL: Drain queue before executing
  await syncQueue.drain();
  
  // Now safe to execute - all file ops complete
  return await runPyodideCode();
}
```

**Advantages:**
- ✓ Minimal code changes (retrofit to existing flow)
- ✓ Immediate fix for production race
- ✓ Works with current Pyodide sync model
- ✓ No infrastructure changes needed

**Disadvantages:**
- ✗ Still has async operations (just serialized)
- ✗ Potential UI blocking if sync is slow
- ✗ Race still exists if drain() called elsewhere

**Timeline:** ~2 hours to implement

---

### Option B: Stateful Execution Snapshot (Safer, More Complex)

**Concept:** Capture an immutable snapshot of "execution context" at the moment Run is clicked. Pyodide executes from snapshot, not live filesystem.

**Implementation:**

```javascript
// In assignments.js

class ExecutionSnapshot {
  constructor(mode, files, config) {
    this.mode = mode;           // 'template' or 'solution'
    this.files = { ...files };  // Immutable copy
    this.config = { ...config };
    this.timestamp = Date.now();
    this.frozen = true;
  }
}

let currentExecutionSnapshot = null;

async function prepareExecutionSnapshot() {
  const mode = getTaskModeScope();
  const files = {
    init: await getFileForExecution('init.py', mode),
    main: await getFileForExecution('main.py', mode),
    solution: await getFileForExecution('solution.py', mode),
    tests: await getFileForExecution('test_*.py', mode),
  };

  currentExecutionSnapshot = new ExecutionSnapshot(
    mode,
    files,
    { taskId, assignmentId, userId }
  );

  return currentExecutionSnapshot;
}

async function executeCode() {
  // Before every execution, capture fresh snapshot
  const snapshot = await prepareExecutionSnapshot();

  // Pass snapshot to Pyodide, not file handles
  return await runPyodideWithSnapshot(snapshot);
}

// In pyodide-wrapper.js
async function runPyodideWithSnapshot(snapshot) {
  // Clear Pyodide filesystem
  await clearPyodideFS();

  // Populate from snapshot (not async - all data in memory)
  Object.entries(snapshot.files).forEach(([name, content]) => {
    pyodide.FS.writeFile(name, content);
  });

  // Execute with frozen context
  return await pyodide.runPythonAsync(snapshot.files.main);
}
```

**Advantages:**
- ✓ No race condition possible - context is immutable and complete
- ✓ Decouples frontend async operations from execution
- ✓ Can capture timing/diagnostic info in snapshot
- ✓ Easier to debug ("what code actually executed?")

**Disadvantages:**
- ✗ More refactoring required (Pyodide integration)
- ✗ Snapshots add memory overhead (small, but present)
- ✗ Requires careful snapshot cleanup

**Timeline:** ~4 hours

---

### Option C: Debounced Mode Transitions (Balanced)

**Concept:** Don't immediately update execution context when user toggles mode. Instead, debounce the toggle until Pyodide file sync is provably complete.

**Implementation:**

```javascript
// In assignments.js

let modeTransitionInProgress = false;

async function toggleSolutionMode() {
  if (modeTransitionInProgress) {
    console.log('[mode] Transition in progress, ignoring toggle');
    return;
  }

  modeTransitionInProgress = true;

  try {
    // Update UI immediately (user feedback)
    assignmentState.solutionMode = !assignmentState.solutionMode;
    updateModeIndicator();

    if (assignmentState.solutionMode) {
      await loadSolutionIntoMainArea();
      // Wait for Pyodide to acknowledge file write
      await waitForPyodideSync('solution');
    } else {
      await loadTaskIntoEditor();
      // Wait for Pyodide to acknowledge file write
      await waitForPyodideSync('template');
    }

    console.log('[mode] Transition complete, safe to execute');
  } finally {
    modeTransitionInProgress = false;
  }
}

async function waitForPyodideSync(expectedMode) {
  const maxWait = 5000; // 5 seconds
  const startTime = Date.now();

  while (Date.now() - startTime < maxWait) {
    try {
      // Verify Pyodide has expected files
      const files = await getPyodideFileList();
      if (isModeConsistent(files, expectedMode)) {
        return; // Safe to proceed
      }
    } catch (e) {
      console.warn('[sync] Check failed:', e);
    }

    await sleep(100); // Poll every 100ms
  }

  console.error('[sync] Timeout waiting for Pyodide sync');
  throw new Error(`Pyodide sync timeout for ${expectedMode}`);
}
```

**Advantages:**
- ✓ Conservative (waits for confirmation, not just timing)
- ✓ Catches edge cases where sync fails silently
- ✓ Relatively simple to add to current code
- ✓ Better UX than queue blocking

**Disadvantages:**
- ✗ Polling is wasteful
- ✗ Network error propagation unclear
- ✗ Requires "sync status" API from Pyodide

**Timeline:** ~3 hours

---

### Option D: Separate Execution Contexts (Most Robust)

**Concept:** Maintain two isolated Pyodide instances (one for template, one for solution). Toggle simply switches which instance runs.

**Implementation:**

```javascript
// In assignments.js

class ExecutionContextManager {
  constructor() {
    this.contexts = {
      template: null,
      solution: null,
    };
    this.activeContext = 'template';
  }

  async initializeContext(mode) {
    if (this.contexts[mode] !== null) {
      return; // Already initialized
    }

    this.contexts[mode] = new PyodideExecutionContext(mode);
    const files = await fetchFilesForMode(mode);
    await this.contexts[mode].loadFiles(files);
  }

  async switchContext(newMode) {
    await this.initializeContext(newMode);
    this.activeContext = newMode;
  }

  async execute() {
    return await this.contexts[this.activeContext].run();
  }
}

class PyodideExecutionContext {
  constructor(mode) {
    this.mode = mode;
    this.pyodideInstance = null; // Separate instance per context
    this.files = {};
  }

  async loadFiles(files) {
    this.files = files;
    // Load into this context's isolated filesystem
    if (!this.pyodideInstance) {
      // Lazy initialization of Pyodide instance
      this.pyodideInstance = await this.createPyodideInstance();
    }
    Object.entries(files).forEach(([name, content]) => {
      this.pyodideInstance.FS.writeFile(name, content);
    });
  }

  async run() {
    return await this.pyodideInstance.runPythonAsync(this.files.main);
  }

  async createPyodideInstance() {
    // Separate Pyodide for this context
    return await createNewPyodideWorker();
  }
}

const executionManager = new ExecutionContextManager();

async function toggleSolutionMode() {
  const newMode = !assignmentState.solutionMode ? 'solution' : 'template';
  await executionManager.switchContext(newMode);
  assignmentState.solutionMode = (newMode === 'solution');
  updateModeIndicator();
}

async function executeCode() {
  return await executionManager.execute();
}
```

**Advantages:**
- ✓ **Zero race conditions** - each mode has isolated, pre-loaded context
- ✓ No competing async operations between modes
- ✓ Toggle is truly instant (just switch context)
- ✓ Can run parallel stress tests without interference

**Disadvantages:**
- ✗ Double memory usage (two Pyodide instances)
- ✗ Significant refactoring required
- ✗ Browser resource constraints (Pyodide is heavy)
- ✗ Startup overhead (initialization both contexts on load)

**Timeline:** ~8 hours

---

## Recommendation Matrix

| Approach | Speed | Complexity | Memory | Risk | Recommended For |
|----------|-------|-----------|--------|------|-----------------|
| **A: Queue** | Quick fix | Low | None | Low | Immediate hotfix, testing |
| **B: Snapshot** | Medium | Medium | Low | Medium | Production after A validates |
| **C: Debounce** | Balanced | Medium | None | Medium | Alternative if A doesn't work |
| **D: Separate** | Best | High | High | High | Future refactor (v2.0) |

---

## Phased Implementation Plan

### Phase 1: Quick Fix (A - Queue-Based, 1-2 days)
1. Implement `PyodideSyncQueue`
2. Wrap mode-change operations
3. Add `drain()` before Run execution
4. Test on production
5. **Decision point:** If race is eliminated, proceed to Phase 2. If flaky, escalate to Phase 2 immediately.

### Phase 2: Robust Fix (B - Stateful Snapshot, 3-5 days)
1. Design `ExecutionSnapshot` class
2. Modify `executeCode()` to capture snapshot before run
3. Integrate Pyodide wrapper with snapshot consumption
4. Comprehensive testing (local + production)
5. Deploy with cache-bust

### Phase 3: Future Refactor (D - Separate Contexts, v2.0)
- Only if memory/performance becomes an issue
- Provides ultimate race-free guarantee
- Higher cost, lower benefit in practice

---

## Testing Strategy

For each approach, verify:

1. **Race condition eliminated:**
   ```
   Run toggle_workflow test 20 times on production
   Expected: 100% pass rate (was ~30% before)
   ```

2. **No new issues introduced:**
   ```
   Run existing assignment tests
   Verify no regressions in save, edit, file operations
   ```

3. **Performance acceptable:**
   ```
   Measure: Time from Run click to first output
   Acceptable: < 1000ms (was ~300-500ms before race fixes)
   ```

---

## Deployment Checklist

- [ ] Approach selected
- [ ] Code implemented + peer review
- [ ] Tested locally (20+ cycles)
- [ ] Tested on beta production (20+ cycles)
- [ ] Cache-bust version incremented (`v=20260522x`)
- [ ] All 5 editor pages script includes updated
- [ ] Deployment to live production
- [ ] Monitor production logs for errors
- [ ] User verification (manual workflow test)
