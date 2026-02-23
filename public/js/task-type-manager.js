/**
 * Task Type Manager for Admin UI
 * Handles dynamic field display based on selected task type
 */

(function() {
  const TASK_TYPES = {
    code: {
      label: 'Code (Python)',
      fields: ['code_template', 'randomizer_code', 'test_cases', 'solution', 'hints']
    },
    single_choice: {
      label: 'Single-Choice',
      fields: ['image', 'options', 'hints']
    },
    multiple_choice: {
      label: 'Multiple-Choice',
      fields: ['image', 'options', 'hints']
    },
    free_text: {
      label: 'Freitext',
      fields: ['image', 'test_cases', 'hints']
    },
    code_reading: {
      label: 'Code-Lesequest',
      fields: ['code_template', 'variable_overrides', 'correct_answer', 'hints', 'max_iterations', 'show-solution-code']
    },
    code_random_complex: {
      label: 'Code mit zufälligen Werten',
      fields: ['code_template', 'randomizer_code', 'solution', 'correct_answer', 'hints', 'show-solution-code']
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
      this.hideField(form, 'image-upload');
      this.hideField(form, 'options-builder');
      this.hideField(form, 'keywords');
      this.hideField(form, 'variable-overrides');
      this.hideField(form, 'code-template');
      this.hideField(form, 'randomizer-code');
      this.hideField(form, 'hints-section');
      this.hideField(form, 'test-cases-section');
      this.hideField(form, 'validation-mode');
      this.hideField(form, 'solution');
      this.hideField(form, 'correct-answer');
      this.hideField(form, 'max-iterations');
      this.hideField(form, 'show-solution-code');
      
      // Show required fields
      config.fields.forEach(field => {
        switch (field) {
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
          case 'randomizer_code':
            this.showField(form, 'randomizer-code');
            break;
          case 'hints':
            this.showField(form, 'hints-section');
            break;
          case 'test_cases':
            this.showField(form, 'test-cases-section');
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
          case 'max_iterations':
            this.showField(form, 'max-iterations');
            break;
        }
      });
      
      // Show code toggle for code_random_complex and code_reading
      if (taskType === 'code_random_complex' || taskType === 'code_reading') {
        this.showField(form, 'show-solution-code');
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
