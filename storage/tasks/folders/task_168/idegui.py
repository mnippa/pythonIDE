# CODE_UI_TEMPLATE_VERSION: 1.1.0
# Referenzdatei für die idegui-Struktur.
# Diese Datei zeigt die erwarteten API-Ideen, die Laufzeit kann davon abweichen.

def title(text):
    return {"type": "title", "text": text}

def text(value):
    return {"type": "text", "text": value}