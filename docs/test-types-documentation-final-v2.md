# Test Types Documentation - Unified Schema v2.0

## Overview

These are the main task types available in the Python IDE:

1. **REGEX** - Text matching with regular expressions
2. **CODE_READING** - Given input, predict output with variables
3. **CODE_RANDOM_COMPLEX** - Given input, predict output with RANDOMIZED variables
4. **INTELLIGENT** - Code execution with parameter-based randomization
5. **EXAM** - General text/code input exercises

---

## 1. REGEX Task Type

Regular expression pattern matching with detailed feedback.

### Schema
```json
{
  "task_type": "regex",
  "task_text": "string (markdown supported)",
  "regex_pattern": "string (ECMAScript regex)",
  "test_string": "string (what to match against)"
}
```

### Example
**Task**: Find HTML tags (simplified)
```regex
<[a-zA-Z]+[^>]*>
```

**Test string**: `<div class="test">Content</div>`

**Matches**:
- `<div class="test">`
- `</div>`

---

## 2. CODE_READING Task Type

Student must read code and predict output for **FIXED INPUT VALUES**.

### Schema
```json
{
  "task_type": "code_reading",
  "task_text": "string",
  "code_template": "string (executable Python code)",
  "solution_code": "string (code to execute)",
  "correct_answer": "string (variable name to check)",
  "variable_overrides": [
    {
      "inputs": {
        "x": "5",
        "y": "10"
      },
      "expected": {
        "variable": "result"
      }
    }
  ]
}
```

### Execution Flow

**Step 1**: Build code with FIXED values
```python
# student sees:
x = 5
y = 10
result = x + y
# Solution code: result = x + y
```

**Step 2**: Student predicts output
```
Input: x=5, y=10
Output should be: [15]
```

**Step 3**: Verify
```python
result = 5 + 10  # = 15
```

### variable_overrides Structure

```json
[
  {
    "inputs": {
      "variable_name": "value",
      "another_var": "value2"
    },
    "expected": {
      "variable": "result_variable_name"
    }
  },
  {
    "inputs": { ... },
    "expected": { ... }
  }
]
```

### Multiple Iterations

If you provide multiple override sets:
```json
[
  { "inputs": {"x": "5", "y": "10"}, "expected": {"variable": "result"} },
  { "inputs": {"x": "2", "y": "3"}, "expected": {"variable": "result"} },
  { "inputs": {"x": "100", "y": "50"}, "expected": {"variable": "result"} }
]
```

Benutzers see **different values** in each iteration attempt!

---

## 3. CODE_RANDOM_COMPLEX Task Type - **UNIFIED SCHEMA**

Student must read code and predict output with **RANDOMLY GENERATED INPUT VALUES**.

### ⚠️ CRITICAL: Unified with CODE_READING

- **Same schema structure** as CODE_READING
- **Same solution_code syntax** (uses `{placeholder}` notation)
- **Only difference**: `<random>` markers in inputs + `randomizer_code`

### Schema

```json
{
  "task_type": "code_random_complex",
  "task_text": "string",
  "code_template": "string (executable Python)",
  "randomizer_code": "string (Python to generate variables)",
  "solution_code": "string (code with {placeholders})",
  "correct_answer": "string (variable name)",
  "variable_overrides": [
    {
      "inputs": {
        "binary": "<random>"
      },
      "expected": {
        "variable": "result"
      }
    }
  ]
}
```

### Example: Binary to Decimal

**Randomizer Code** (Python):
```python
import random
binary = format(random.randint(0, 255), '08b')
```

NOTE: Creates variable directly (NOT wrapped in dict)!

**Solution Code** (same syntax as CODE_READING):
```python
result = int({binary}, 2)
```

**Execution Flow**:

1. **Generate random value** via `randomizer_code`
   ```python
   binary = "10101010"  # Generated randomly
   ```

2. **Build code template** with generated value
   ```python
   binary = "10101010"
   result = int({binary}, 2)
   # Placeholder replaced: result = int(10101010, 2)
   ```

3. **Execute solution code**
   ```python
   result = int(10101010, 2)  # = 170
   ```

4. **Student enters answer** based on the random value
   ```
   Answer: [170]
   ```

5. **Verify** against `correct_answer` variable
   ```python
   # Check: result == "170"
   ✓ Korrekt!
   ```

### Multiple Iterations

Same variables_overrides allows multiple test sets:

```json
[
  {
    "inputs": {"binary": "<random>"},
    "expected": {"variable": "result"}
  },
  {
    "inputs": {"binary": "<random>"},
    "expected": {"variable": "result"}
  },
  {
    "inputs": {"binary": "<random>"},
    "expected": {"variable": "result"}
  }
]
```

Each iteration generates **NEW random values**!

### Randomizer Code Guidelines

**✅ DO**:
```python
import random
binary = format(random.randint(0, 255), '08b')  # Creates "binary" variable
```

**❌ DON'T**:
```python
# Wrong - wrapping in dict (old format):
values = {"binary": binary}

# Wrong - returning JSON:
return json.dumps({"binary": binary})
```

### Solution Code Guidelines

**✅ DO** (Placeholder Syntax):
```python
result = int({binary}, 2)        # Placeholder for variable
fahrenheit = ({celsius} * 9) + 5  # Multiple placeholders OK
```

**❌ DON'T** (Old values dict syntax):
```python
# Wrong - references values["key"] dict:
result = int(values["binary"], 2)
```

---

## 4. INTELLIGENT Task Type

Complex code execution with function parameters randomly varied.

### Schema
```json
{
  "task_type": "intelligent",
  "task_text": "string",
  "code_template": "string (Python with INIT block)",
  "randomizer_code": "string (generates values dict)",
  "solution_code": "string (calls user_function)",
  "function": {
    "name": "user_function",
    "params": ["param1", "param2"]
  },
  "correct_answer": "string | regex pattern"
}
```

### INIT Block Format

**Required** for INTELLIGENT type:

```python
#INIT START
a = 0
b = 0
#INIT END

# Calculation code (uses echte Werte from randomizer)
result = a + b
```

---

## 5. EXAM Task Type

Free-form assignment with text/code answers.

### Schema
```json
{
  "task_type": "exam",
  "task_text": "string (can be very long)",
  "show_solution": bool,
  "solution_code": "string (answer key)"
}
```

---

## Comparison: CODE_READING vs CODE_RANDOM_COMPLEX

| Aspect | CODE_READING | CODE_RANDOM_COMPLEX |
|--------|--------------|-------------------|
| Variable Values | Feste Werte | Generiert via randomizer_code |
| Schema | Identisch | Identisch |
| Variable Override Format | `{"var": "value"}` | `{"var": "<random>"}` |
| Solution Code | `{placeholder}` Syntax | `{placeholder}` Syntax |
| Randomizer Output | N/A | **Direkte Variablen** |
| **Execution** | Fixed values alle Iterationen | Neue zufällige Werte jede Iteration |

**✅ KEY INSIGHT**: Beide Typen verwenden **IDENTISCHE** solution_code Logik!

---

## Frontend Execution (quiz-renderer.js)

### ensureGeneratedValues() Workflow

1. **Check if variable_overrides exist**
2. **Detect `<random>` markers** in inputs
3. **If random markers found**:
   - Run `randomizer_code` in Pyodide
   - Extract generated variables from Pyodide namespace
   - Return variable dict
4. **If no random markers** (CODE_READING):
   - Select override set based on iteration
   - Return fixed values
5. **Replace placeholders** in solution_code
   - `{binary}` → actual value like `"10101010"`
   - Execute modified code

---

## Backend Validation (submit_quiz.php)

### Code Submission Flow

1. **For CODE_RANDOM_COMPLEX**:
   - Client sends: `variable_values` (computed from randomizer)
   - Client sends: `computed_value` (from solution_code execution)
   - Backend verifies: `computed_value` matches expected

2. **For CODE_READING**:
   - Client sends: `variable_values` (from overrides)
   - Client sends: `computed_value` (from solution_code execution)
   - Backend verifies: `computed_value` matches expected

---

## Best Practices

### Code_Reading & Code_Random_Complex

- **variable_overrides** MUST be valid JSON
- **solution_code** must use `{varname}` for placeholders
- **correct_answer** must match a variable name in code
- Multiple overrides = Multiple iterations automatically

### Randomizer Code

- Must create variables directly (not return JSON)
- Variables must be JSON-serializable
- No print statements (output isn't captured)
- Import required modules (random, etc.)

### Testing Randomization

Monitor browser console:
```javascript
[CODE_RANDOM] Saving generated values - Payload: {...}
[CODE_RANDOM] Response: {...}
```

---

## Database Schema

```sql
-- Core fields for all task types
id INT PRIMARY KEY
assignment_id INT
task_type ENUM('regex', 'code_reading', 'code_random_complex', 'intelligent', 'exam')
task_text LONGTEXT
solution_code TEXT
correct_answer VARCHAR(255)

-- Code types (reading, random, intelligent)
code_template TEXT
variable_overrides LONGTEXT (JSON)

-- Random/Intelligent specific
randomizer_code TEXT

-- Function-based (intelligent)
function_config JSON
```

---

## End-to-End Example: Binary to Decimal

### Task Configuration

```json
{
  "id": 147,
  "task_type": "code_random_complex",
  "task_text": "Convert an 8-bit binary number to decimal",
  "code_template": "binary = ...\nresult = int(binary, 2)",
  "randomizer_code": "import random\nbinary = format(random.randint(0, 255), '08b')",
  "solution_code": "result = int({binary}, 2)",
  "correct_answer": "result",
  "variable_overrides": [{"inputs": {"binary": "<random>"}, "expected": {"variable": "result"}}]
}
```

### Iteration 1

```
Frontend: Execute randomizer_code
  → binary = "10101010"

Show task:
  Input: binary = "10101010"
  Code: result = int({binary}, 2)
  Student: [170]

Backend: Verify
  Placeholder replaced: result = int(10101010, 2)
  Execution: result = 170
  Check: 170 == 170 ✓
```

### Iteration 2

```
Frontend: Execute randomizer_code again
  → binary = "11001100"  (NEW random value!)

Show task:
  Input: binary = "11001100"
  Code: result = int({binary}, 2)
  Student: [204]

Backend: Verify
  Placeholder replaced: result = int(11001100, 2)
  Execution: result = 204
  Check: 204 == 204 ✓
```

---

**Last Updated**: 2025
**Version**: 2.0 (Unified Schema with Placeholder Syntax)
