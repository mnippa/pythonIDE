# Free-Text Task Validation Enhancement

## Overview
Extended the free_text task type to support the same validation logic as OUTPUT tests:
- **Text Pattern** with validation modes (strict/loose/contains)
- **Regex Pattern** with case-insensitive matching
- **Keywords** (backward compatible legacy mode)

## Changes Made

### 1. Backend API Updates

#### `/api/user_tasks/submit_quiz.php` (Lines 151-234)
Enhanced free-text answer validation to support:
- `expected_type: 'regex'` - Regex pattern matching (case-insensitive)
- `expected_type: 'text'` - Text pattern with validation_mode (strict/loose/contains)
- `expected_type: 'keywords'` - Legacy keyword matching (default for backward compatibility)

**Validation Modes for Text Pattern:**
- `strict`: Exact match after trim
- `loose`: Whitespace-normalized comparison
- `contains`: Expected text must be contained in answer

**Key Fields:** `expected`, `expected_type`, `validation_mode`

#### `/api/user_tasks/test_submission.php` (Lines 143-214)
Identical validation logic applied for code test tasks.

### 2. Admin Dashboard (UI & Form Logic)

#### `/public/admin.php`
**New HTML Sections:**

1. **Validation Mode Radio Selection**
   - Schlüsselwörter (Standard)
   - Text Pattern
   - Regex Pattern

2. **Conditional Field Display**
   - Keywords section: IDs `freetext-keywords-section`, `edit-freetext-keywords-section`
   - Text section: IDs `freetext-text-section`, `edit-freetext-text-section`
   - Regex section: IDs `freetext-regex-section`, `edit-freetext-regex-section`

3. **Keywords Mode Fields**
   - `task-keywords` / `edit-task-keywords`: Keyword list
   - `task-min-keywords` / `edit-task-min-keywords`: Minimum keywords required (optional)

4. **Text Pattern Mode Fields**
   - `task-freetext-expected` / `edit-task-freetext-expected`: Expected text
   - `task-freetext-mode` / `edit-task-freetext-mode`: Validation mode (loose/strict/contains)

5. **Regex Pattern Mode Fields**
   - `task-freetext-regex` / `edit-task-freetext-regex`: Regex pattern

#### `/public/js/admin-dashboard.js`

1. **Form Load Logic** (Lines 1100-1133)
   - Detects `expected_type` from task
   - Sets appropriate radio button
   - Populates corresponding fields
   - Triggers change event to show/hide field groups

2. **Form Save Logic** (Lines 560-588, 1280-1308)
   - Reads selected validation type from radio button
   - Saves to payload with correct field structure:
     - Keywords: `correct_answer`, `expected_type: 'keywords'`, optional `min_keywords_required`
     - Text: `expected`, `expected_type: 'text'`, `validation_mode`
     - Regex: `expected`, `expected_type: 'regex'`

3. **Field Toggle Function** (Lines 3619-3668)
   - `initFreeTextTypeToggle()`: Manages visibility of validation type sections
   - Runs on DOMContentLoaded for both create and edit forms
   - Hides/shows field groups based on radio selection

### 3. Frontend Rendering

#### `/public/js/quiz-renderer.js` (Lines 200-305)
Updated `renderFreeText()` to:
- Detect validation type from `task.expected_type`
- Display appropriate hint text:
  - Keywords: "Alle X Schlüsselwörter müssen..." or "Mindestens X von Y..."
  - Text: "Text Pattern (Exakte Übereinstimmung / Leerzeichen normalisiert / Text muss vorhanden sein)"
  - Regex: "Die Antwort muss einem Regex-Muster entsprechen (Case-insensitive)"
- Show solution based on validation type:
  - Keywords: Shows keyword list
  - Text: Shows expected text with mode notation
  - Regex: Shows regex pattern in code format

## Database Fields

### Tasks Table Additions
For free_text tasks, the following fields are used:

| Field | Type | Purpose | Default |
|-------|------|---------|---------|
| `expected_type` | string | Validation type (keywords/text/regex) | 'keywords' |
| `expected` | text | Expected text or regex pattern | NULL |
| `validation_mode` | string | For text mode: strict/loose/contains | 'loose' |
| `correct_answer` | text | For keywords mode: comma-separated list | NULL |
| `min_keywords_required` | int | For keywords mode: minimum match count | NULL |

## Backward Compatibility

✅ **Fully Backward Compatible**

All code defaults to `expected_type: 'keywords'` when not specified:
```php
$expectedType = $task['expected_type'] ?? 'keywords';
```

Existing free_text tasks with only `correct_answer` field:
- Automatically treated as keyword matching
- Render with keyword validation hint
- Pass validation using keyword matching logic

## Usage Examples

### Create New Free-Text Task - Keywords Mode
```javascript
{
  task_type: 'free_text',
  expected_type: 'keywords',  // Optional, default
  correct_answer: 'variable, speicher, wert',
  min_keywords_required: 2
}
```

### Create New Free-Text Task - Text Pattern Mode
```javascript
{
  task_type: 'free_text',
  expected_type: 'text',
  expected: 'Eine Variable ist ein Speicherbereich',
  validation_mode: 'loose'  // or 'strict' or 'contains'
}
```

### Create New Free-Text Task - Regex Mode
```javascript
{
  task_type: 'free_text',
  expected_type: 'regex',
  expected: '^[a-z_][a-z0-9_]*$'  // Valid Python identifier pattern
}
```

## Testing Checklist

- [x] Create free_text task with keywords validation
- [x] Create free_text task with text pattern validation
- [x] Create free_text task with regex validation
- [x] Edit existing free_text task and change validation type
- [x] Verify old free_text tasks still work (backward compatibility)
- [x] Test student submission with each validation type
- [x] Verify solution display for each validation type
- [x] Check form field visibility toggle on create form
- [x] Check form field visibility toggle on edit form

## Files Modified

1. `/api/user_tasks/submit_quiz.php` - Backend validation
2. `/api/user_tasks/test_submission.php` - Test validation  
3. `/public/admin.php` - Admin form UI
4. `/public/js/admin-dashboard.js` - Form logic, save/load
5. `/public/js/quiz-renderer.js` - Student-facing hints and solutions

## Notes

- Regex patterns use JavaScript/PHP regex syntax with case-insensitive flag
- Text pattern validation modes mirror the OUTPUT test type behavior
- Admin UI smoothly toggles between three distinct configuration interfaces
- All existing free_text data remains valid and functional
