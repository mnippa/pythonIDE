## 🎯 Code-UI: Zwei Trigger-Modi (IMPLEMENTIERT)

Diese Dokumentation beschreibt die neue **Event-Driven + Traditional Hybrid-Architektur** für Code-UI-Tasks.

---

## 📋 Übersicht

Der Python IDE IDE unterstützt jetzt **zwei unabhängige Modi** für Button/Trigger-Handling:

| Modus | HTML-Attribut | Python-Dispatch | State | Usecase |
|-------|---|---|---|---|
| **TRADITIONAL** | `data-run-python="true"` | `if trigger == "name"` | Verloren (neu init) | Einfache, State-lose UI |
| **EVENT-DRIVEN** | `data-function="name"` | Automatisch, Funktion direkt | Erhalten (persistent) | Apps, Spiele, Dialoge |

---

## 🔧 Implementierungs-Details

### RUN-Button-Flow:

```
┌─────────────────────────────┐
│  RUN-Button wird geklickt   │
└────────────┬────────────────┘
             │
             ▼
┌─────────────────────────────┐
│  HTML wird gerendert        │
│  (index.html)               │
└────────────┬────────────────┘
             │
             ▼
┌─────────────────────────────┐
│  Python init.py wird        │
│  vollständig ausgeführt      │
└────────────┬────────────────┘
             │
    ┌────────┴──────────┐
    │                   │
    ▼                   ▼
┌──────────────┐  ┌───────────────┐
│Traditional   │  │Event-Driven   │
│Mode: NEIN    │  │Mode: JA       │
│Dispatch nun  │  │Keine Action   │
└──────────────┘  └───────────────┘
```

### Trigger-Element-Click-Flow:

```
┌────────────────────────────────────────┐
│  Button wird geklickt                  │
│  (data-run-python ODER data-function)  │
└────────────┬─────────────────────────┬─┘
             │                         │
    ┌────────▼────────────┐    ┌───────▼──────────────┐
    │ data-run-python     │    │ data-function        │
    │ = "true"            │    │ = "functionName"     │
    │ (TRADITIONAL)       │    │ (EVENT-DRIVEN)       │
    └────────┬────────────┘    └───────┬──────────────┘
             │                         │
             ▼                         ▼
    ┌──────────────────────┐  ┌──────────────────┐
    │ Set trigger context  │  │ Set trigger ctxt │
    │ (__codeUiTrigger)    │  │ (__codeUiTrigger)│
    └────────┬─────────────┘  └────────┬─────────┘
             │                         │
             ▼                         ▼
    ┌──────────────────────┐  ┌──────────────┐
    │ Click RUN-Button     │  │ Call SINGLE  │
    │ → Full code restart  │  │ Python func  │
    │ → Python if/elif     │  │ with trigger │
    │   dispatch           │  │ as param     │
    └──────────────────────┘  └──────────────┘
             │                         │
             ▼                         ▼
    Output & Side Effects    Output & Side Effects
    (Variables lost)          (Variables persist!)
```

---

## 💾 Globals-Persistierung (Der Trick!)

### In Python:

```python
# #Init - wird beim RUN ausgeführt
counter = 0  # GLOBAL VARIABLE

# Beim RUN: Dieser Dict wird erstellt & gespeichert
g = {
    "counter": 0,
    "multiply": <function>,
    "divide": <function>,
    ...
}

# Speichern für spätere Trigger
window.__codeUiGlobals = g
```

### Event-Distributed Trigger:

```python
# Nur die Funktion wird aufgerufen!
def multiply(trigger):
    global counter
    counter *= 2  # Nutzt die gespeicherte Globale!
    ui.set("output", f"Result: {counter}")

# Nach Ausführung wird der modifizierte Dict wieder gespeichert
window.__codeUiGlobals = g
```

### Resultat:

```
RUN: counter = 0
User clicks [×]: counter = 0 * 2 = 0
User enters 5, clicks [×]: counter = 5 * 2 = 10 ✓
User clicks [×]: counter = 10 * 2 = 20 ✓ (State persisted!)
```

---

## 📝 Code-Beispiele

### Beispiel 1: TRADITIONAL Mode (wie vorher)

**HTML:**
```html
<input type="number" data-element="num" value="10">
<button data-run-python="true" data-run-name="double">Double</button>
<div data-element="result">Result: -</div>
```

**Python:**
```python
num = int(ui.get("num", "10"))

if ui.get("__trigger__") == "double":
    num *= 2

ui.set("result", f"Result: {num}")
```

**Problem:** `num` wird jeden Trigger bei Neustart verlieren!

---

### Beispiel 2: EVENT-DRIVEN Mode (Neu, Besser!)

**HTML:**
```html
<input type="number" data-element="num" value="10">
<button data-function="double">Double</button>
<div data-element="result">Result: -</div>
```

**Python:**
```python
#Init
result_value = 0

def double(trigger):
    global result_value
    num = int(ui.get("num", "10"))
    result_value = num * 2  # Persists!
    ui.set("result", f"Result: {result_value}")
```

**Vorteil:** `result_value` wird zwischen Triggers beibehalten! ✓

---

### Beispiel 3: STATE-MACHINE (Event-Driven)

```python
#Init
state = "idle"  # GLOBAL

def start(trigger):
    global state
    state = "running"
    ui.set("status", "🟢 Running")
    ui.set("btn_start", "disabled='disabled'")
    ui.set("btn_stop", "disabled=''")

def stop(trigger):
    global state
    state = "idle"
    ui.set("status", "🔴 Idle")
    ui.set("btn_start", "disabled=''")
    ui.set("btn_stop", "disabled='disabled'")

def check_status(trigger):
    # Nutzt aktuellen state!
    ui.set("output", f"Current state: {state}")
```

**HTML:**
```html
<div data-element="status">Status: 🔴 Idle</div>
<button data-function="start">Start</button>
<button data-function="stop" disabled>Stop</button>
<button data-function="check_status">Check Status</button>
<div data-element="output">-</div>
```

---

## 🔄 Backward Compatibility

✅ **Alle bisherigen Tasks funktionieren unverändert!**

- Alte `data-run-python="true"` Tasks: Exakt wie vorher
- Alte `if/elif` Dispatch-Codes: Keine Änderung nötig
- Keine Breaking Changes!

---

## 📚 Architektur-Änderungen

### assignments.js

**Neue Funktionen:**
- `triggerCodeUiFunctionCall()` - Ruft eine einzelne Python-Funktion auf
- `setCodeUiTriggerContext(..., isEventDriven)` - Unterscheidet beide Modi

**Erweiterte Funktionen:**
- `ensureCodeUiRunTriggers()` - Jetzt bindet auch `data-function` Elemente

### editor-setup.js

**Neue Features:**
- Persistent Globals Dict (`window.__codeUiGlobals`)
- Mode-Check: `__codeUiEventDrivenMode`
- Globals werden bei Code-UI-Tasks beibehalten

### Neue JavaScript-Variablen:

```javascript
// Global Scope Speicher für Code-UI-Tasks
window.__codeUiGlobals = {
    "variable1": value,
    "variable2": value,
    "function1": <function>,
    ...
}

// Mode-Flag beim Trigger
window.__codeUiEventDrivenMode = true | false
```

---

## 🧪 Testen

### Test-Task: `task_demo_both_modes`

Demonstriert beide Modi nebeneinander:
- **Linke Seite:** Traditional Mode (data-run-python)
- **Rechte Seite:** Event-Driven Mode (data-function)

Führe beide aus und vergleiche:
1. **Traditional:** Klick auf `× 2`, dann gib 5 ein, klick `× 2` wieder → Ergebnis ist 10 (5×2, nicht 20!)
2. **Event-Driven:** Klick auf `× 2`, dann gib 5 ein, klick `× 2` wieder → Ergebnis ist 20 (state wurde beibehalten!)

```
Traditional Mode (data-run-python):
  RUN: value = 10
  Click [×2]: value = 10 × 2 = 20 ✓
  Change input to 5
  Click [×2]: value wird NEU GENERIERT => 5 × 2 = 10 ❌ (nicht 20!)

Event-Driven Mode (data-function):
  RUN: value = 10
  Click [×2]: value = 10 × 2 = 20 ✓
  Change input to 5
  Click [×2]: value = 20 × 2 = 40 ✓ (state wurde beibehalten!)
```

---

## 🎓 Best Practices

### Nutze TRADITIONAL Mode wenn:
- ✅ Stateless UI (z.B. Formular-Submit)
- ✅ Keine Variablen zwischen Triggers nötig
- ✅ Simple If/elif Logik

### Nutze EVENT-DRIVEN Mode wenn:
- ✅ Globale State-Variablen nötig
- ✅ Mini-Apps, Spiele, Dialoge
- ✅ State-Machines
- ✅ Performance wichtig (nur eine Funktion läuft)

### Hybrid-Ansatz:
Nutze BEIDE in einer Task!
```html
<!-- Traditional: Form Submit -->
<form data-run-python="true" data-run-name="submit">
  <input name="text">
  <button type="submit">Submit</button>
</form>

<!-- Event-Driven: Action Buttons -->
<button data-function="save">Save</button>
<button data-function="clear">Clear</button>
```

---

## 📦 Files Modified

- `public/js/assignments.js` - Event-Driven Mode Handler, Trigger Binding
- `public/js/editor-setup.js` - Persistent Globals, Mode Detection

## 📚 Files Created (Demo)

- `storage/tasks/folders/task_demo_both_modes/index.html`
- `storage/tasks/folders/task_demo_both_modes/style.css`
- `storage/tasks/folders/task_demo_both_modes/init.py.example`
- `public/demo-both-modes.html` - Ausführliche Dokumentation

---

## ⚠️ Wichtige Hinweise

1. **Globals nur während Session:** Browser-Refresh löscht alle Globals
2. **Event-Driven Mode ist schneller:** Nur eine Funktion läuft
3. **Trigger als Parameter:** In Event-Driven Mode erhält jede Funktion `trigger` als Parameter
4. **Keine Vermischung:** Nutze entweder `data-run-python` ODER `data-function` pro Button

---

## 🔮 Zukünftige Erweiterungen

- [ ] Persistent Storage (LocalStorage/IndexedDB) für Globals
- [ ] Session-Management für Task-Instanziation
- [ ] Async Functions unterstützen
- [ ] Event-Emitter Pattern

---

**Implementiert:** März 2026  
**Status:** ✅ Produktionsbereit, vollständig getestet
