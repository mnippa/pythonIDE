# Assignments and Tasks API Documentation

## Overview
This document covers:
- Assignments (admin create/update/delete; user list/read)
- Tasks (admin create/update/delete; user list)
- User Assignments (assign users, track progress, update status)

All endpoints require a logged-in session.

---

## Assignments API

### Create Assignment (Admin)
```
POST /api/assignments/create.php
Content-Type: application/json

{
  "title": "Bedingungen Grundlagen",
  "description": "Einstieg in If/Else",
  "code_template": "# optional",
  "difficulty": "beginner",
  "time_limit_minutes": 20,
  "is_active": true
}
```

### List Assignments
```
GET /api/assignments/list.php
GET /api/assignments/list.php?all=1   # admin can include inactive
```

### Get Assignment
```
GET /api/assignments/get.php?id=1
```

### Update Assignment (Admin)
```
POST /api/assignments/update.php
Content-Type: application/json

{
  "id": 1,
  "title": "Bedingungen Basics",
  "is_active": false
}
```

### Delete Assignment (Admin)
```
DELETE /api/assignments/delete.php?id=1
```

---

## Tasks API

### Create Task (Admin)
```
POST /api/tasks/create.php
Content-Type: application/json

{
  "assignment_id": 1,
  "title": "Gerade oder ungerade",
  "description": "Gib odd/even aus",
  "position": 2,
  "problem_type": "code_completion",
  "code_template": "n = 7\nif ___:\n    print('odd')\nelse:\n    print('even')",
  "hint": "Nutze n % 2",
  "expected_output": "odd"
}
```

### List Tasks for Assignment
```
GET /api/tasks/list.php?assignment_id=1
GET /api/tasks/list.php?assignment_id=1&include_expected=1  # admin only
```

### Update Task (Admin)
```
POST /api/tasks/update.php
Content-Type: application/json

{
  "id": 12,
  "title": "Gerade oder ungerade (leicht)",
  "position": 1
}
```

### Delete Task (Admin)
```
DELETE /api/tasks/delete.php?id=12
```

---

## User Assignments API

### Assign User to Assignment (Admin)
```
POST /api/user_assignments/assign.php
Content-Type: application/json

{
  "assignment_id": 1,
  "email": "max.mueller@example.com",
  "status": "assigned"
}
```

### List User Assignments
```
GET /api/user_assignments/list.php                # current user
GET /api/user_assignments/list.php?user_id=2      # admin
GET /api/user_assignments/list.php?assignment_id=1# admin
GET /api/user_assignments/list.php?status=passed  # optional filter
```

### Get User Assignment
```
GET /api/user_assignments/get.php?id=5
GET /api/user_assignments/get.php?assignment_id=1 # current user only
```

### Update User Assignment
```
POST /api/user_assignments/update.php
Content-Type: application/json

{
  "id": 5,
  "status": "in_progress",
  "current_code": "print('hello')"
}
```

Notes:
- Admin can set `passed` or `failed` and update `test_results`, `attempts`.
- Regular users can set status to `assigned`, `in_progress`, `submitted` and update `current_code`.

### Delete User Assignment (Admin)
```
DELETE /api/user_assignments/delete.php?id=5
```

---

## Status Values

- `assigned`
- `in_progress`
- `submitted`
- `passed`
- `failed`

---

## Common Errors

### 400 Bad Request
```
{ "ok": false, "error": "Assignment ID required" }
```

### 403 Forbidden
```
{ "ok": false, "error": "Access denied" }
```

### 404 Not Found
```
{ "ok": false, "error": "Assignment not found" }
```

### 409 Conflict
```
{ "ok": false, "error": "User already assigned" }
```
