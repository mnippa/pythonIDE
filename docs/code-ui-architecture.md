# Code-UI Architecture Guide

## Overview

**Code-UI** is a task type that combines HTML/CSS frontend with Python backend, allowing students to build interactive applications using web technologies while writing Python logic. The system handles the plumbing between HTML elements and Python code automatically.

## Key Components

### 1. HTML Container Elements

#### `idegui-root`
**Purpose:** Container for dynamically-generated idegui widgets.

When Python code calls functions like `ui.number()`, `ui.text()`, `ui.button()`, etc., they generate HTML elements that are inserted into this container. This keeps auto-generated UI separate from student-authored HTML.

```html
<div id="idegui-root"></div>
```

The idegui module manages this container at runtime. Students can style it with CSS:

```css
#idegui-root {
    max-width: 500px;
    margin: 20px auto;
}
```

#### `idegui-output`
**Purpose:** System output container for Python print() statements.

All output from Python's `print()` function is captured and displayed here. Each print adds a new line.

```html
<div id="idegui-output" style="border: 1px solid #ccc; padding: 10px; margin-top: 10px;"></div>
```

### 2. Data Attributes

HTML elements use special attributes to bind to Python variables. The idegui module automatically detects and manages these.

#### `data-element` (Bidirectional)
**Most versatile:** Supports both reading and writing. A single element can function as input (read by Python) or output (written by Python) or both.

```html
<!-- Student enters a number -->
<input type="number" id="number_a" data-element="a" placeholder="Enter A">

<!-- Python reads and writes to same element -->
<span data-element="result" style="font-weight: bold;"></span>
```

```python
a = float(ui.get('a'))      # Read from input
result = a * 2
ui.set('result', result)     # Write to span
```

### 3. Interactive Triggers

#### `data-run-python="true"`
**Purpose:** Mark an HTML element (button, form) as a trigger for Python execution.

When clicked/submitted, the entire Python code runs (simulating RUN button press).

```html
<button data-run-python="true">Calculate</button>
<form data-run-python="true">
    <input type="text" data-element="name">
    <input type="submit" value="Submit">
</form>
```

#### `data-run-name` (Multi-Button Scenarios)
**Purpose:** Identify which button was clicked so Python can distinguish between multiple triggers.

Store the clicked button's identifier in the hidden `__trigger__` input. Python reads it via `ui.get('__trigger__')`.

```html
<button data-run-python="true" data-run-name="add">+ Add</button>
<button data-run-python="true" data-run-name="remove">- Remove</button>
```

```python
trigger = ui.get('__trigger__')

if trigger == 'add':
    # Handle add logic
    pass
elif trigger == 'remove':
    # Handle remove logic
    pass
```

### 4. Simple Python API

The idegui module provides a minimal, intuitive API:

```python
# Read a value
value = ui.get('element_name')

# Write a value (replaces content)
ui.set('element_name', 'new value')

# Print text to a container (appends, like Python print)
ui.print('output_container', 'Line 1')
ui.print('output_container', 'Line 2')

# Clear a container
ui.reset('output_container')

# Output text to idegui-output (legacy)
print("This appears in the output box")
```

**Key Differences:**

- **`ui.set(name, value)`** → Replaces content in element (for displaying single values)
- **`ui.print(container, ...)`** → Appends text with line breaks (for building logs/output)
- **`ui.reset(container)`** → Clears container completely

**Example:**
```python
# Set replaces content each time
ui.set('result', 10)
ui.set('result', 20)  # Now shows: 20

# Print appends each time
ui.print('log', 'Starting...')
ui.print('log', 'Processing...')
ui.print('log', 'Done!')
# Now shows:
# Starting...
# Processing...
# Done!

# Reset clears everything
ui.reset('log')  # Now empty
```

## Execution Flow

### Step 1: HTML Rendering
When a Code-UI task loads, the system:
1. Reads `index.html` and `style.css` from the task folder
2. Injects HTML into the page container
3. **Preserves any existing input values** (from previous RUN)

### Step 2: Trigger Setup
The system scans the HTML for elements with `data-run-python="true"` and attaches click/submit handlers.

### Step 3: Python Execution
When a trigger fires (button click or form submit):
1. **Multi-button case:** If the trigger has `data-run-name`, store it in hidden `data-element="__trigger__"`
2. Run the Python code
3. Python can read `ui.get('__trigger__')` to know which button was clicked

### Step 4: I/O Binding
As Python runs:
- `ui.get(name)` reads from `[data-element="name"]`
- `ui.set(name, value)` writes to `[data-element="name"]` (replaces content)
- `ui.print(container, ...)` appends text to `[data-element="container"]`
- `ui.reset(container)` clears `[data-element="container"]`
- Built-in `print()` appends to `#idegui-output`

### Step 5: State Persistence
Form values are preserved between RUNs (you can modify an input and click another button; your change won't disappear).

## Code-UI Task Folder Structure

```
/storage/tasks/folders/task_{id}/
├── index.html              # Student's HTML (editable by default)
├── style.css               # Student's styles (editable by default)
├── init.py                 # Initialization code (usually empty)
├── idegui.py               # UI bridge module (READONLY by default)
├── ui-runtime.readonly.js  # Documentation (READONLY)
└── .file-policies.json     # Per-file readonly overrides
```

### `.file-policies.json` Example

```json
{
    "files": {
        "idegui.py": {
            "read_only": true
        },
        "ui-runtime.readonly.js": {
            "read_only": true
        },
        "index.html": {
            "read_only": false
        }
    }
}
```

**Default Readonly Files:**
- `idegui.py` - Always readonly (managed by system)
- `ui-runtime.readonly.js` - Always readonly (documentation only)
- `index.html`, `style.css` - Readonly unless task has `allow_code_ui_web_edit=1` in database

## Access Control

### Admin Level (`api/tasks/`)
Admins can manage all Code-UI files including `idegui.py` via `.file-policies.json` overrides in the task folder.

### Student Level (`api/user_tasks/`)
Students see:
- `index.html`, `style.css` - Usually editable
- `idegui.py`, `ui-runtime.readonly.js` - Always readonly
- `init.py` - Usually readonly (teacher-provided setup)

Students cannot edit readonly files even if they try direct API calls. The enforcement happens on the server.

## Planned Features

### `data-function-python` (Future)
**Goal:** Call specific Python functions directly from HTML elements, not just the full RUN.

**Proposed Syntax:**
```html
<!-- Call handle_click(element) when button clicked -->
<button data-function-python="handle_click">Click me</button>
```

**Python:**
```python
def handle_click(element):
    """Called when button is clicked"""
    return "Button was clicked!"  # Result appears as message
```

**Benefits:**
- Faster response (don't re-run all code)
- Cleaner separation of concerns
- Progressive disclosure (button → function → result)

**Implementation Notes:**
- Registry of callable functions in Python
- Button click passes element reference to function
- Return value displayed as toast/message or set to output element
- Requires new handler in `assignments.js` `ensureCodeUiRunTriggers()`

## Best Practices

### 1. Use `data-element` for Bidirectional Binding
```html
<!-- GOOD: Single attribute for both input and feedback -->
<input type="number" data-element="value">
<span data-element="result"></span>
```

### 2. Named Triggers for Multi-Button Logic
```python
# Good: Clear logic flow
trigger = ui.get('__trigger__')
if trigger == 'plus':
    result = a + b
elif trigger == 'minus':
    result = a - b
```

### 3. Use `ui.print()` for Building Logs, `ui.set()` for Values
```python
# Good: ui.set() for single values that change
ui.set('temperature', '23°C')
ui.set('status', 'Ready')

# Good: ui.print() for building output logs
ui.reset('debug')  # Clear first
ui.print('debug', 'Starting calculation...')
ui.print('debug', f'Input: {value}')
ui.print('debug', f'Result: {result}')
```

### 4. Always Use `idegui-output` for Diagnostics
```python
# Good: Visible feedback
print(f"Processing {count} items...")
result = process(items)
print(f"Done! Results: {result}")
```

### 4. Readonly Files are Scaffolding
- `idegui.py` provides the bridge - don't edit it
- `ui-runtime.readonly.js` documents the event system - read it for understanding
- Focus on `index.html`, `style.css`, and `init.py`

## Debugging

### "Value disappeared after clicking RUN"
→ Used to happen! Now fixed. Values are preserved between runs.

### "Button click doesn't work"
→ Check that button has `data-run-python="true"`

### "Can't read the value Python wrote"
→ Use same element name in both `ui.set()` and `data-element` attribute:
```python
ui.set('result', 42)  # Make sure HTML has data-element="result"
```

### "Can't tell which button was clicked"
→ Add `data-run-name` to each button and read `ui.get('__trigger__')`

## Examples

### Example 1: Simple Calculator
```html
<input type="number" data-element="a" placeholder="A">
<input type="number" data-element="b" placeholder="B">
<button data-run-python="true" data-run-name="add">Add</button>
<button data-run-python="true" data-run-name="multiply">Multiply</button>
<span data-element="result"></span>
```

```python
trigger = ui.get('__trigger__')
a = float(ui.get('a'))
b = float(ui.get('b'))

if trigger == 'add':
    result = a + b
else:
    result = a * b

ui.set('result', result)
```

### Example 2: Text Processing with Log Output
```html
<textarea data-element="input_text" placeholder="Enter text"></textarea>
<button data-run-python="true">Analyze</button>
<div>
  <strong>Word Count:</strong> <span data-element="word_count">-</span>
</div>
<div class="log" data-element="log" style="background: #f5f5f5; padding: 10px; margin-top: 10px;"></div>
<button type="button" onclick="document.querySelector('[data-element=log]').textContent=''">Clear Log</button>
```

```python
text = ui.get('input_text')

# Clear previous log
ui.reset('log')

# Build output with ui.print (appends)
ui.print('log', '=== Analysis Started ===')
ui.print('log', f'Input length: {len(text)} characters')

words = text.split()
word_count = len(words)

ui.print('log', f'Found {word_count} words')
ui.print('log', '=== Done ===')

# Set the result (replaces content)
ui.set('word_count', word_count)
```

### Example 3: Using Auto-Generated Widgets
```html
<div id="idegui-root"></div>  <!-- Python will insert widgets here -->
<div id="idegui-output"></div> <!-- Python output goes here -->
```

```python
ui.number('age', min=0, max=150, label="How old are you?")
ui.button("Calculate", on_click="on_submit")  # Future feature
age = ui.get('age')
ui.set('message', f"You are {age} years old")
```

---

**Last Updated:** 2025-01-15  
**Maintained by:** IDE Development Team
