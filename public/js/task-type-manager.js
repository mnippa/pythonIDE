/**
 * Task Type Manager for Admin UI
 * Handles dynamic field display based on selected task type
 */

(function() {
  const TASK_TYPES = {
    code: {
      label: 'Code (Python)',
      fields: ['code_template', 'hints', 'test_cases', 'validation_mode', 'solution']
    },
    single_choice: {
      label: 'Single-Choice',
      fields: ['question', 'image', 'options']
    },
    multiple_choice: {
      label: 'Multiple-Choice',
      fields: ['question', 'image', 'options']
    },
    free_text: {
      label: 'Freitext',
      fields: ['question', 'image', 'keywords']
    },
    code_reading: {
      label: 'Code-Lesequest',
      fields: ['question', 'code_template', 'variable_overrides', 'correct_answer', 'hints', 'solution']
    },
    code_random_complex: {
      label: 'Code mit zufälligen Werten',
      fields: ['question', 'code_template', 'variable_overrides', 'solution', 'correct_answer', 'hints']
    }
  };

  window.TaskTypeManager = {
    init(formId) {
      const form = document.getElementById(formId);
      if (!form) return;

      const typeSelect = form.querySelector('[id$="-task-type"]');
      if (!typeSelect) return;

      typeSelect.addEventListener('change', () => {
        this.updateFieldVisibility(form, typeSelect.value);
      });

      // Initialize on load
      this.updateFieldVisibility(form, typeSelect.value);
    },

    updateFieldVisibility(form, taskType) {
      const config = TASK_TYPES[taskType] || TASK_TYPES.code;
      
      // Hide all dynamic fields first
      this.hideField(form, 'description');  // Show only for code tasks
      this.hideField(form, 'question');     // Show only for quiz tasks
      this.hideField(form, 'image-upload');
      this.hideField(form, 'options-builder');
      this.hideField(form, 'keywords');
      this.hideField(form, 'variable-overrides');
      this.hideField(form, 'code-template');
      this.hideField(form, 'hints-section');
      this.hideField(form, 'test-cases-section');
      this.hideField(form, 'validation-mode');
      this.hideField(form, 'solution');
      this.hideField(form, 'correct-answer');
      this.hideField(form, 'show-generator-code');
      
      // Handle description vs question based on task type
      if (taskType === 'code') {
        // Code task: show description, hide question
        this.showField(form, 'description');
        this.hideField(form, 'question');
      } else {
        // Quiz tasks: show question, hide description
        this.hideField(form, 'description');
        this.showField(form, 'question');
      }

      // Show required fields
      config.fields.forEach(field => {
        switch (field) {
          case 'question':
            this.showField(form, 'question');
            break;
          case 'image':
            this.showField(form, 'image-upload');
            break;
          case 'options':
            this.showField(form, 'options-builder');
            break;
          case 'keywords':
            this.showField(form, 'keywords');
            break;
          case 'code_template':
            this.showField(form, 'code-template');
            break;
          case 'hints':
            this.showField(form, 'hints-section');
            break;
          case 'test_cases':
            this.showField(form, 'test-cases-section');
            break;
          case 'validation_mode':
            this.showField(form, 'validation-mode');
            break;
          case 'solution':
            this.showField(form, 'solution');
            break;
          case 'variable_overrides':
            this.showField(form, 'variable-overrides');
            break;
          case 'correct_answer':
            this.showField(form, 'correct-answer');
            break;
        }
      });
      
      // Show generator code option only for code_random_complex
      if (taskType === 'code_random_complex') {
        this.showField(form, 'show-generator-code');
      }
    },

    hideField(form, fieldId) {
      const container = form.querySelector(`[data-field="${fieldId}"]`);
      if (container) container.style.display = 'none';
    },

    showField(form, fieldId) {
      const container = form.querySelector(`[data-field="${fieldId}"]`);
      if (container) container.style.display = '';  // Clear inline style to restore CSS (display: flex)
    }
  };
})();
