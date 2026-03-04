"""
idegui - Code-UI API für Python
Ermöglicht Interaktion zwischen Python-Code und HTML-Elementen
"""

def get(name: str, default: str = "") -> str:
    """
    Liest den Wert eines HTML-Elements.
    
    Args:
        name: Name des data-element Attributs
        default: Rückgabewert wenn Element leer ist (Standard: "")
    
    Returns:
        String mit dem Wert des Elements
    
    Beispiel:
        value = ui.get("input_field")
    """
    pass

def set(name: str, value) -> None:
    """
    Schreibt einen Wert in ein HTML-Element (ersetzt bestehenden Inhalt).
    
    Args:
        name: Name des data-element Attributs
        value: Wert zum Schreiben (wird automatisch in String konvertiert)
    
    Beispiel:
        ui.set("output", "Hallo Welt")
        ui.set("result", 42)
    """
    pass

def print(container_name: str, *args, sep: str = " ", end: str = "\n") -> None:
    """
    Fügt Text zu einem Container hinzu (wie Python's print).
    
    Args:
        container_name: Name des data-element Container-Elements
        args: Beliebig viele Werte zum Ausgeben
        sep: Trennzeichen zwischen Werten (Standard: " ")
        end: Zeichen am Ende (Standard: "\\n")
    
    Beispiel:
        ui.print("log", "Schritt 1")
        ui.print("log", "Wert:", 42)
    """
    pass

def reset(container_name: str) -> None:
    """
    Löscht den Inhalt eines Containers.
    
    Args:
        container_name: Name des data-element Container-Elements
    
    Beispiel:
        ui.reset("log")
        ui.print("log", "Neuer Start")
    """
    pass
