# GUI Layout Architecture

## Overview

The IDE editor layout has been restructured to support an interactive Python GUI system (`import idegui as ui`) that allows students to create graphical interfaces without writing HTML/CSS/JavaScript.

## Layout Design

### Right Column Grid (50/50 Split)

```
┌─────────────────────────────────────┐
│  LEFT: Editor | RIGHT: Editor Output│
├─────────────────────────────────────┤
│                                     │ 50%
│  #gui-container (hidden by default) │
│                                     │
├─────────────────────────────────────┤
│  📃 Output | 📊 Plot  [Tab Bar]     │
├─────────────────────────────────────┤
│  #output-container  or              │ 50%
│  #plot-container                    │
│  (switching via tabs)               │
└─────────────────────────────────────┘
```

### CSS Grid Structure

```css
.right {
  display: grid;
  grid-template-rows: 1fr 1fr;  /* Equal 50/50 split */
  gap: 0;
}

/* GUI Container (top, hidden by default) */
#gui-container {
  display: none;
}
#gui-container.active {
  display: block;
}

/* Output/Plot Section (bottom) */
#output-plot-section {
  display: grid;
  grid-template-rows: auto 1fr;  /* Tab bar + panels */
}

/* Tab Navigation */
#output-plot-tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--border);
}

.output-plot-tab {
  flex: 1;
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.2s ease;
}

.output-plot-tab.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}

/* Panels (Output/Plot) */
.output-plot-panel {
  display: none;
}
.output-plot-panel.active {
  display: block;
}
```

## JavaScript Integration

### Tab Navigation (`output-plot-tabs.js`)

Event-driven tab switching:

```javascript
export function initOutputPlotTabs() {
  const tabs = document.querySelectorAll('.output-plot-tab');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      switchToTab(tab.getAttribute('data-tab'));
    });
  });
}

function switchToTab(tabName) {
  document.querySelectorAll('.output-plot-tab').forEach(t => {
    t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
  });
  
  document.querySelectorAll('.output-plot-panel').forEach(p => {
    p.classList.toggle('active', 
      (tabName === 'output' && p.id === 'output-container') ||
      (tabName === 'plot' && p.id === 'plot-container')
    );
  });
}
```

### GUI Bridge (`gui-bridge.js`)

Central manager for Python ↔ JS widget communication:

```javascript
export class GUIBridge {
  showGUI()              // Make GUI container visible
  hideGUI()              // Hide GUI container
  clearGUI()             // Empty GUI container
  getGUIContainer()      // Return DOM element for appending
  appendToGUI(element)   // Add widget to GUI
  switchTab(tabName)     // Switch between 'output' and 'plot'
}

export const guiBridge = new GUIBridge(); // Singleton
```

## Initialization Flow

1. **Page Load**: One of 6 pages (assignment_editor.php, editor.php, free.php, projects.php, share.php, editor_assignment_test.php)

2. **Module Load**: `<script type="module" src="js/editor-setup.js"></script>`

3. **editor-setup.js**:
   ```javascript
   import { initOutputPlotTabs } from './output-plot-tabs.js';
   import { guiBridge } from './gui-bridge.js';
   
   async function initPyodideAndEditor() {
     initOutputPlotTabs();              // Activate tab navigation
     window.guiBridge = guiBridge;      // Make available to Python
     
     // ... Pyodide setup
   }
   ```

4. **Tab System Ready**: Users can click tabs to switch Output ↔ Plot

5. **Wait for GUI**: When Python code `import idegui`, trigger `guiBridge.showGUI()`

## Pages Updated

- ✅ [assignment_editor.php](../public/assignment_editor.php)
- ✅ [editor.php](../public/editor.php)
- ✅ [free.php](../public/free.php)
- ✅ [projects.php](../public/projects.php)
- ✅ [share.php](../public/share.php)
- ✅ [editor_assignment_test.php](../public/editor_assignment_test.php)

All pages share identical CSS/HTML structure for consistency.

## Future: Python Integration

When Python `idegui` module is implemented:

```python
import idegui as ui

# Create widgets
num_input = ui.number(label="Temperature (°C)", value=20)
unit_select = ui.select(label="Convert to", options=["°F", "K"])
convert_btn = ui.button(label="Convert")
result_output = ui.output()

# Callbacks
def convert_temperature():
    celsius = num_input.value
    unit = unit_select.value
    if unit == "°F":
        result = (celsius * 9/5) + 32
    result_output.write(f"Result: {result}")

convert_btn.on_click(convert_temperature)
```

This will:
1. Auto-detect `import idegui`
2. Call `guiBridge.showGUI()` to make top container visible
3. Create `<div class="widget">` elements for each `ui.*` call
4. Append widgets to `guiBridge.getGUIContainer()`
5. Register callbacks for button clicks, output writes, etc.

## Testing

1. Load any editor page (e.g., http://localhost/pythonIDE/public/assignment_editor.php)
2. Click "📃 Output" and "📊 Plot" tabs → should switch panels
3. GUI container should remain hidden (top half shows editor)
4. Console output should appear in Output tab

## Architecture Notes

- **No breaking changes**: GUI hidden by default, existing Output/Plot remains visible
- **Tab navigation is optional**: can be removed if replaced with different UI
- **Responsive**: Grid layout adapts to small screens (Output consumes full height if GUI not active)
- **Performance**: Single DOM element for GUI (#gui-container) reduces memory footprint
- **Reusable module**: `gui-bridge.js` can be extended for other features (console logging, event system, etc.)
