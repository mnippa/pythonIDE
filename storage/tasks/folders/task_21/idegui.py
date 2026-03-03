# idegui - Simple Data-Attribute Interface

def get_input_value(name):
    """Get value from data-input="name" element"""
    return {"type": "get_input", "name": name}

def set_output(name, value):
    """Set value in data-output="name" element"""
    return {"type": "set_output", "name": name, "value": str(value)}