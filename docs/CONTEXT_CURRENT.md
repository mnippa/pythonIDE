# PythonIDE Current Context

## Purpose

This document is the current high-level context for PythonIDE.
It is intended as the first stop for new chats, new contributors, and AI assistants.

It describes the current runtime model, assignment/task model, status logic, folder-task file handling, and live/beta deployment flow.

## Core Product Areas

PythonIDE currently has three main areas:

1. Projects
- User-owned projects with file trees and runtime payload support.
- Can include Python-only projects or mixed HTML/CSS/Python projects.

2. Assignments and tasks
- Admins create assignments.
- Assignments contain ordered tasks.
- Students work on tasks in an editor and get status/progress tracking.

3. Code-UI and folder-based tasks
- Some tasks use `folderstructure = 1` and provide additional files.
- `code_ui` is a special folder-based task type for HTML/CSS/Python UI work.

## Current Runtime Model

### Editor runtime

- Pyodide runs in the browser.
- Standard code tasks execute the code currently visible in the editor.
- Project runs may provide a runtime payload containing files that are written into the Pyodide filesystem before execution.

### Folder-based assignment tasks

Folder-based tasks are not purely database-backed.

Storage split:

1. `init.py`
- Virtual file.
- Student-specific content lives in `user_tasks.current_code`.
- Fallback is `tasks.code_template`.

2. Additional files and folders
- Stored physically in `storage/tasks/folders/task_<taskId>/`.
- Student edits to those files are stored as overrides in `user_task_files`.

3. Read-only policy
- Controlled via `.file-policies.json` inside the task folder.

### Important runtime rule

For folder-based tasks, additional files must be loaded into the Pyodide filesystem before RUN, CHECK, or SUBMIT.

Current implementation:

- RUN path builds a runtime payload from folder task files and writes them into Pyodide.
- CHECK/SUBMIT path syncs folder task files into `/task_runtime` and updates `sys.path`.

This is required for imports like:

```python
from temperaturen import temperaturen
```

Without this sync, folder files exist on the server but are not visible inside Pyodide.

## Assignment and Task Model

### Assignments

Assignments are collections of ordered tasks.

Important assignment-level status source:

- `user_assignments.status`
- Values: `assigned`, `in_progress`, `submitted`, `passed`, `failed`

This status is the assignment status, not the per-task progress.

### Tasks

Tasks are the actual units students solve.

Current task progress source:

- `user_tasks.status`

Observed UI values in current code:

- `unbearbeitet`
- `in-progress`
- `passed`
- `failed`

Do not assume older docs using `not_started` or `completed` still reflect the current UI implementation.

### Important task content fields

- `title`: short topic label for task list
- `task_text`: main task instruction shown above the editor
- `description`: details/explanation in sidebar/details area
- `stoff`: learning/topic reference

## Test and Validation Model

Current important code task test types:

- `output`
- `function`
- `variable`
- `code_check`
- `intelligent`
- `code_reading`
- `code_random_complex`

Important rule:

- For code tasks, correctness is not based on one single mechanism.
- It depends on task type plus `test_cases`, `variable_overrides`, `randomizer_code`, and `solution_code`.

### Code check usage

`code_check` validates that student code contains required patterns, for example:

- `for`
- `if`
- `import`

### Variable tests

`variable` tests are often the most robust way to validate calculations.

Typical pattern:

- inject input variables
- execute student code
- compare named result variables

## Dashboard Status Logic

The dashboard currently shows three conceptually different things:

1. Availability
- Derived from timing/phase.
- Examples: not open yet, open, late, closed, hidden.

2. Task processing status
- Derived from task progress.
- Examples: not available, assigned, in progress, completed, completed late.

3. Assignment status
- Based on `user_assignments.status`, but normalized for display so it does not contradict the task progress.
- When no final admin decision exists, assignment status is derived from progress.
- Final admin decisions like `passed` or `failed` override the fallback.

This separation is important and should stay explicit in future UI changes.

## Admin Area

`public/admin.php` is the central admin surface.

Relevant current areas include:

- assignment management
- task management
- user management
- deploy sync live/beta controls

The deploy sync is not just generic deployment documentation.
There is an in-app admin-triggered sync flow for:

- `hydrate-beta`
- `promote-live`

Implemented through:

- `api/admin/deploy/sync-live-beta.php`
- `scripts/sync_live_beta.php`

## Live/Beta Sync Model

There are two distinct directions:

1. `hydrate-beta`
- Copy selected runtime/config state from live to beta.

2. `promote-live`
- Copy changed app files from beta to live.

Important behavior:

- The sync is triggered from the admin UI.
- The actual file copy runs in the web-user execution context unless server-side permissions or an external deploy user change that.
- Whether files can be overwritten depends on ownership/ACLs, not just `755/644` numerically.

## Documentation Guidance

When in doubt, treat this document as the current truth for high-level behavior.

Then drill into these docs depending on topic:

- `code-ui.md`
- `code-ui-architecture.md`
- `test-types-documentation-final-v2.md`
- `ASSIGNMENTS_API_DOCUMENTATION.md`
- `DEPLOYMENT_DEBIAN.md`

If another doc contradicts this one, verify against code before trusting the older doc.