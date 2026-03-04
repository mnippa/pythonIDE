"""idegui - Simple UI Bridge for Code-UI Tasks

Injected by JavaScript at runtime. Allows Python to read/write HTML elements.

API:
  ui.get(name, default="")  - Read value from HTML element
  ui.set(name, value)       - Write value to HTML element (replaces content)
  ui.print(container, ...)  - Print text to container (appends like Python print)
  ui.reset(container)       - Clear container content
  ui.trigger.name           - Name of trigger element that was clicked (data-function or data-run-name)
  ui.trigger.value          - Value of trigger element (data-run-value or value attribute)

Example:
  a = float(ui.get('a'))  # Read from <input data-element="a">
  b = float(ui.get('b'))
  result = a + b
  ui.set('result', result)         # Write to <span data-element="result">
  ui.print('log', 'Calculated:', result)  # Append to <div data-element="log">

Event-Driven Functions:
  When using data-function="myFunc", the function receives the trigger as parameter:
  
  def myFunc(trigger):
      # trigger.name contains "myFunc"
      # trigger.value contains the element's value
      ui.set('output', f"Called by {trigger.name}")
"""

def get(name, default=""):
    """Read value from HTML element (data-element)"""
    return default

def set(name, value):
    """Write value to HTML element (data-element)"""
    return str(value)

def print(container, *args, sep=' ', end='\n'):
    """Print text to container element (appends like Python print)"""
    pass

def reset(container):
    """Clear container content"""
    pass

class _Trigger:
    """Trigger object representing the clicked element"""
    name = ""
    value = ""

trigger = _Trigger()

__all__ = ["get", "set", "print", "reset", "trigger"]
