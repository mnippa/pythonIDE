# PythonIDE Architecture

## Purpose

This document gives a compact architectural overview of PythonIDE.
For current product logic and operational details, read [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md) first.

## Main layers

### 1. Frontend

- Monaco-based editor
- Assignment UI in `public/js/assignments.js`
- Runtime/editor setup in `public/js/editor-setup.js`
- Admin UI in `public/admin.php` plus related JS

### 2. Browser runtime

- Pyodide executes Python in the browser
- Some runs are main-thread based, some use the worker runner
- Additional project or folder-task files must be written into the Pyodide filesystem before imports work

### 3. Backend APIs

- Auth and session middleware
- Assignment/task CRUD APIs
- User progress APIs
- Folder/file APIs for task files and project files
- Admin deploy/sync APIs

### 4. Persistence

- MySQL for users, assignments, tasks, progress, and overrides
- Filesystem for folder-based task content and deploy/runtime assets

## Assignment/task architecture

Assignments are collections of ordered tasks.

Important distinction:

- Assignment status comes from `user_assignments`
- Task progress comes from `user_tasks`

These two levels must not be conflated in UI or docs.

## Folder-task architecture

Folder-based tasks use split persistence:

1. `init.py`
- virtual file
- source: `user_tasks.current_code`
- fallback: `tasks.code_template`

2. other files
- source: `storage/tasks/folders/task_<id>/`
- student overrides: `user_task_files`

3. read-only behavior
- source: `.file-policies.json`

### Runtime implication

Additional folder files are not automatically importable just because they exist on the server.
They must be copied into the Pyodide runtime and added to `sys.path` before RUN/CHECK/SUBMIT.

## Admin and deployment architecture

Admin operations live primarily in `public/admin.php` and `/api/admin/**`.

The platform also contains an in-app live/beta sync flow:

- `hydrate-beta`
- `promote-live`

This is separate from generic shell deployment documentation.

## Documentation map

- Current product context: [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
- Code-UI specifics: [code-ui.md](code-ui.md)
- Code-UI runtime/folder model: [code-ui-architecture.md](code-ui-architecture.md)
- Assignment APIs: [ASSIGNMENTS_API_DOCUMENTATION.md](ASSIGNMENTS_API_DOCUMENTATION.md)
- Deployment notes: [DEPLOYMENT_DEBIAN.md](DEPLOYMENT_DEBIAN.md)
