/**
 * Task AI Generator
 * Generates prompts for external AI services to create task JSON definitions
 */

class TaskAIGenerator {
  constructor() {
    this.initEvents();
  }

  initEvents() {
    // Modal controls
    document.getElementById('open-task-ai-modal')?.addEventListener('click', () => this.openModal());
    document.getElementById('task-ai-close-btn')?.addEventListener('click', () => this.closeModal());
    document.getElementById('task-ai-cancel-btn')?.addEventListener('click', () => this.closeModal());

    // Generate prompt
    document.getElementById('generate-prompt-btn')?.addEventListener('click', () => this.generatePrompt());

    // Copy prompt
    document.getElementById('copy-prompt-btn')?.addEventListener('click', () => this.copyPromptToClipboard());

    // Tab switching
    document.getElementById('ai-tab-prompt')?.addEventListener('click', () => this.switchTab('prompt'));
    document.getElementById('ai-tab-import')?.addEventListener('click', () => this.switchTab('import'));

    // JSON Import
    document.getElementById('import-generated-json-btn')?.addEventListener('click', () => this.importGeneratedJSON());


  }

  openModal() {
    const modal = document.getElementById('task-ai-modal');
    if (modal) {
      modal.classList.add('active');
      // Reset form
      this.resetForm();
    }
  }

  closeModal() {
    const modal = document.getElementById('task-ai-modal');
    if (modal) {
      modal.classList.remove('active');
    }
  }

  resetForm() {
    document.getElementById('ai-task-type').value = 'code';
    document.getElementById('ai-task-title').value = '';
    document.getElementById('ai-task-topic').value = '';
    document.getElementById('ai-attempts').value = '3';
    document.getElementById('ai-with-hints').checked = true;
    document.getElementById('ai-with-solution').checked = true;
    document.getElementById('prompt-preview').textContent = '(Prompt wird hier angezeigt)';
  }

  getFormData() {
    return {
      taskType: document.getElementById('ai-task-type').value,
      title: document.getElementById('ai-task-title').value,
      topic: document.getElementById('ai-task-topic').value,
      attempts: parseInt(document.getElementById('ai-attempts').value) || 3,
      withHints: document.getElementById('ai-with-hints').checked,
      withSolution: document.getElementById('ai-with-solution').checked
    };
  }

  generatePrompt() {
    const data = this.getFormData();

    // Validation
    if (!data.topic.trim()) {
      alert('Bitte geben Sie ein Thema/Topic ein');
      return;
    }

    // Generate appropriate prompt based on task type
    let prompt = this.generateSystemPrompt();
    prompt += this.generateTaskSpecificPrompt(data);

    // Display in preview
    document.getElementById('prompt-preview').textContent = prompt;
  }

  generateSystemPrompt() {
    return `Du bist ein Experte für die Erstellung von Programmieraufgaben. Du musst ein gültiges JSON im folgenden Format generieren.

Das JSON wird in ein Python IDE System importiert, um Aufgaben/Übungen zu verwalten.

## WICHTIGER HINWEIS:
- Nur gültiges JSON zurückgeben, keine Erklärungen vorher oder nachher
- Das JSON mit { beginnen und mit } enden
- Alle Strings müssen mit double quotes "" sein
- Neue Zeilen im Code nutzen \\n
- Verwende escapeSequenzen für Sonderzeichen
- Das JSON muss mit einem JSON-Validator validierbar sein
- test_cases NICHT generieren - diese werden manuell hinzugefügt

`;
  }

  generateTaskSpecificPrompt(data) {
    const taskTypeNames = {
      'code': 'Code (Python)',
      'code_ui': 'Code + UI',
      'single_choice': 'Single-Choice',
      'multiple_choice': 'Multiple-Choice',
      'free_text': 'Freitext',
      'code_reading': 'Code-Lesequest',
      'code_random_complex': 'Code (versteckt)'
    };

    let prompt = `
## AUFGABENSTELLUNG:

Erstelle eine ${taskTypeNames[data.taskType]}-Aufgabe zum folgenden Thema:

**Thema:** ${data.topic}
${data.title ? `**Titel (verwenden):** ${data.title}` : '**Titel:** (von dir zu erstellen)'}
**Max. Versuche:** ${data.attempts}

**Wichtig:** Erstelle eine vollständige, lehrreiche Aufgabe mit aussagekräftiger Beschreibung zum Thema.

## JSON-FORMAT ANFORDERUNGEN:

### Grundstruktur:
\`\`\`json
{
  "version": "1.0",
  "title": "string - Aufgabentitel",
  "problem_type": "code|code_ui|single_choice|multiple_choice|free_text|code_reading|code_random_complex",
  "description": "string - Aufgabenbeschreibung/Kontext",
  "max_attempts": ${data.attempts}
}
\`\`\`

`;

    // Add task-type specific guidance
    if (data.taskType === 'code' || data.taskType === 'code_ui') {
      prompt += this.generateCodeTaskPrompt(data);
    } else if (data.taskType === 'single_choice') {
      prompt += this.generateSingleChoicePrompt(data);
    } else if (data.taskType === 'multiple_choice') {
      prompt += this.generateMultipleChoicePrompt(data);
    } else if (data.taskType === 'code_reading') {
      prompt += this.generateCodeReadingPrompt(data);
    } else if (data.taskType === 'free_text') {
      prompt += this.generateFreeTextPrompt(data);
    }

    // Add full example library for all task/test combinations
    prompt += this.generateExampleLibrary();

    // Common instructions
    prompt += `
## ALLGEMEINE ANFORDERUNGEN:

1. **Titel & Beschreibung:**
   - Aussagekräftig und präzise
   - Deutsche Sprache

2. **Test Cases:**
  - test_cases werden NICHT generiert
  - Diese werden manuell hinzugefügt
  - Feld weglassen oder leer lassen

${data.withHints ? `3. **Hinweise (optional):**
   - hint1, hint2, hint3 (jeweils kurz und hilfreich)
   - Nicht die Lösung verraten
` : ''}

${data.withSolution ? `4. **Lösungscode:**
   - Vollständig funktionsfähig
   - Saubere, lesbare Formatierung
   - Mit erklärenden Kommentaren
` : ''}

5. **Validierung:**
  - JSON muss komplett und gültig sein
  - Alle erforderlichen Felder müssen vorhanden sein
  - Test Cases müssen dem dokumentierten Format entsprechen
  - CODE_CHECK nutzt "must contain:" oder "must not contain:"

## JETZT GENERIEREN:

Erstelle ein gültiges JSON basierend auf den obigen Anforderungen.
Antworte NUR mit dem JSON, keine Erklärungen oder Markdown-Code-Blöcke.
Das JSON sollte direkt in einen JSON-Validator passen.
`;

    return prompt;
  }

  generateCodeTaskPrompt(data) {
    return `
### CODE TASK FORMAT:

\`\`\`json
{
  "version": "1.0",
  "title": "Dein Title",
  "problem_type": "code",
  "description": "Beschreibung der Aufgabe",
  "code_template": "def my_function():\\n    pass",
  "solution_code": "def my_function():\\n    return result",
  "max_attempts": 3
}
\`\`\`

**HINWEIS:** test_cases müssen manuell im Admin-Panel hinzugefügt werden.
`;
  }

  generateSingleChoicePrompt(data) {
    return `
### SINGLE-CHOICE FORMAT:

\`\`\`json
{
  "version": "1.0",
  "title": "Dein Title",
  "problem_type": "single_choice",
  "description": "Kontext/Beschreibung",
  "question": "Die eigentliche Frage",
  "options": [
    {"text": "Option A", "correct": true},
    {"text": "Option B", "correct": false},
    {"text": "Option C", "correct": false},
    {"text": "Option D", "correct": false}
  ],
  "max_attempts": 3
}
\`\`\`

**Anforderungen:**
- Genau eine Option mit "correct": true
- Mindestens 3-4 Optionen
- Fragen sollten nicht zu trivial sein
`;
  }

  generateMultipleChoicePrompt(data) {
    return `
### MULTIPLE-CHOICE FORMAT:

\`\`\`json
{
  "version": "1.0",
  "title": "Dein Title",
  "problem_type": "multiple_choice",
  "description": "Kontext/Beschreibung",
  "question": "Die eigentliche Frage (mehrere richtige Antworten möglich)",
  "options": [
    {"text": "Option A", "correct": true},
    {"text": "Option B", "correct": true},
    {"text": "Option C", "correct": false},
    {"text": "Option D", "correct": false}
  ],
  "max_attempts": 3
}
\`\`\`

**Anforderungen:**
- Es können mehrere Optionen mit "correct": true sein
- Mindestens 3-4 Optionen insgesamt
- Fragen sollten nicht zu trivial sein
`;
  }

  generateExampleLibrary() {
    return `
## BEISPIELE FUER TASK-TYPEN (OHNE test_cases):

### 1) CODE-AUFGABE
\`\`\`json
{
  "version": "1.0",
  "title": "Quadrat-Funktion",
  "problem_type": "code",
  "description": "Schreibe eine Funktion square(n), die das Quadrat einer Zahl berechnet.",
  "code_template": "def square(n):\n    pass",
  "solution_code": "def square(n):\n    return n * n",
  "max_attempts": 3,
  "hints": [
    {"hint_text": "Multipliziere n mit sich selbst", "penalty": 5}
  ]
}
\`\`\`

### 2) SINGLE-CHOICE
\`\`\`json
{
  "version": "1.0",
  "title": "Hex Wert",
  "problem_type": "single_choice",
  "description": "Waehle die richtige Antwort.",
  "question": "Was ist 255 in Hex?",
  "options": [
    {"text": "FF", "correct": true},
    {"text": "FE", "correct": false},
    {"text": "F0", "correct": false}
  ],
  "max_attempts": 2
}
\`\`\`

### 3) MULTIPLE-CHOICE
\`\`\`json
{
  "version": "1.0",
  "title": "Primzahlen",
  "problem_type": "multiple_choice",
  "description": "Mehrere Antworten moeglich.",
  "question": "Welche Zahlen sind Primzahlen?",
  "options": [
    {"text": "2", "correct": true},
    {"text": "3", "correct": true},
    {"text": "4", "correct": false}
  ],
  "max_attempts": 2
}
\`\`\`

### 4) FREE-TEXT
\`\`\`json
{
  "version": "1.0",
  "title": "Python Begriffe",
  "problem_type": "free_text",
  "description": "Nenne drei wichtige Begriffe aus Python.",
  "question": "Welche Begriffe sind wichtig?",
  "keywords": "variable, function, loop",
  "max_attempts": 2
}
\`\`\`
`;
  }

  generateCodeReadingPrompt(data) {
    return `
### CODE READING FORMAT:

\`\`\`json
{
  "version": "1.0",
  "title": "Dein Title",
  "problem_type": "code_reading",
  "description": "Lesen Sie den Code und bestimmen Sie...",
  "code_template": "# Code zum Lesen\\nvariable = 42",
  "correct_answer": "variable",
  "max_iterations": 1
}
\`\`\`

**Anforderungen:**
- Code_template sollte klar und lesbar sein
- correct_answer ist die Variable, deren Wert geprüft wird
- max_iterations: normalerweise 1 (bzw. Anzahl der Aufgaben-Variationen)
- test_cases werden NICHT generiert - diese muessen manuell im Admin-Panel hinzugefuegt werden
`;
  }

  generateFreeTextPrompt(data) {
    return `
### FREITEXT FORMAT:

\`\`\`json
{
  "version": "1.0",
  "title": "Dein Title",
  "problem_type": "free_text",
  "description": "Beschreiben Sie...",
  "question": "Die Frage/Aufgabe",
  "keywords": "keyword1, keyword2, keyword3",
  "max_attempts": 3
}
\`\`\`

**Anforderungen:**
- keywords: Komma-getrennte Liste von Suchbegriffen
- Diese Begriffe müssen in der Studentenantwort vorkommen
- Mindestens 3-5 relevante Keywords definieren
`;
  }

  copyPromptToClipboard() {
    const promptText = document.getElementById('prompt-preview').textContent;
    
    if (promptText === '(Prompt wird hier angezeigt)') {
      alert('Bitte generieren Sie zuerst einen Prompt');
      return;
    }

    navigator.clipboard.writeText(promptText).then(() => {
      const btn = document.getElementById('copy-prompt-btn');
      const originalText = btn.textContent;
      btn.textContent = '✓ Kopiert!';
      setTimeout(() => {
        btn.textContent = originalText;
      }, 2000);
    }).catch(err => {
      alert('Fehler beim Kopieren: ' + err);
    });
  }

  switchTab(tab) {
    const promptSection = document.getElementById('ai-prompt-section');
    const importSection = document.getElementById('ai-import-section');
    const tabPromptBtn = document.getElementById('ai-tab-prompt');
    const tabImportBtn = document.getElementById('ai-tab-import');

    if (tab === 'prompt') {
      // Show prompt section
      promptSection.style.display = 'block';
      importSection.style.display = 'none';
      
      // Style prompt button (active)
      tabPromptBtn.style.background = 'var(--hspf-accent)';
      tabPromptBtn.style.color = 'white';
      tabPromptBtn.style.fontWeight = 'bold';
      tabPromptBtn.style.border = 'none';
      
      // Style import button (inactive)
      tabImportBtn.style.background = 'var(--hspf-bg-secondary)';
      tabImportBtn.style.color = 'var(--hspf-text-primary)';
      tabImportBtn.style.fontWeight = 'normal';
      tabImportBtn.style.border = '1px solid var(--hspf-border)';
      
      console.log('Switched to Prompt tab');
    } else if (tab === 'import') {
      // Show import section
      promptSection.style.display = 'none';
      importSection.style.display = 'block';
      
      // Style import button (active)
      tabImportBtn.style.background = 'var(--hspf-accent)';
      tabImportBtn.style.color = 'white';
      tabImportBtn.style.fontWeight = 'bold';
      tabImportBtn.style.border = 'none';
      
      // Style prompt button (inactive)
      tabPromptBtn.style.background = 'var(--hspf-bg-secondary)';
      tabPromptBtn.style.color = 'var(--hspf-text-primary)';
      tabPromptBtn.style.fontWeight = 'normal';
      tabPromptBtn.style.border = '1px solid var(--hspf-border)';
      
      // Clear error message when switching to import tab
      document.getElementById('ai-json-error').style.display = 'none';
      
      console.log('Switched to Import tab');
    }
  }

  importGeneratedJSON() {
    const jsonText = document.getElementById('ai-generated-json').value.trim();
    const errorDiv = document.getElementById('ai-json-error');

    if (!jsonText) {
      this.showError('Bitte kopieren Sie das JSON in das Textfeld', errorDiv);
      return;
    }

    let jsonData;
    try {
      jsonData = JSON.parse(jsonText);
    } catch (e) {
      this.showError('JSON ist nicht valid: ' + e.message, errorDiv);
      return;
    }

    // Validate required fields
    if (!jsonData.version || !jsonData.title) {
      this.showError('JSON muss "version" und "title" enthalten', errorDiv);
      return;
    }

    // If we have an existing import-tasks.js, use its logic
    if (typeof window.importTasksFromJSON === 'function') {
      try {
        window.importTasksFromJSON(jsonData);
        alert('✓ Task erfolgreich importiert!');
        this.closeModal();
      } catch (error) {
        this.showError('Fehler beim Importieren: ' + error.message, errorDiv);
      }
    } else {
      // Fallback: trigger import via file
      this.importJSONAsFile(jsonData);
    }
  }

  importJSONAsFile(jsonData) {
    // Create a blob from the JSON
    const jsonString = JSON.stringify(jsonData, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    
    // Create a temporary file input and trigger it
    const input = document.createElement('input');
    input.type = 'file';
    input.setAttribute('accept', '.json');
    
    // Simulate file selection
    const dt = new DataTransfer();
    const file = new File([blob], 'task.json', { type: 'application/json' });
    dt.items.add(file);
    input.files = dt.files;
    
    // Trigger the import-tasks handler
    const fileInput = document.getElementById('import-task-file-input');
    if (fileInput) {
      fileInput.files = dt.files;
      fileInput.dispatchEvent(new Event('change', { bubbles: true }));
      alert('✓ Task wird importiert...');
      this.closeModal();
    } else {
      alert('Import-Handler nicht gefunden');
    }
  }

  showError(message, errorDiv) {
    errorDiv.textContent = '⚠️ ' + message;
    errorDiv.style.display = 'block';
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new TaskAIGenerator();
});
