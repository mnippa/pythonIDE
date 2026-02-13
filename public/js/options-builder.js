/**
 * Options Builder for Single/Multiple Choice Tasks
 */

(function() {
  window.OptionsBuilder = class {
    constructor(containerId) {
      this.container = document.getElementById(containerId);
      this.options = [];
      this.taskType = 'single_choice';
      this.render();
    }

    setTaskType(type) {
      this.taskType = type;
      this.render();
      if (window.onOptionsBuilderChange) {
        window.onOptionsBuilderChange(this.taskType, this.options, this.container?.id);
      }
    }

    setOptions(options) {
      this.options = options.map((opt, idx) => ({
        id: opt.id || Date.now() + idx,
        text: opt.text || '',
        image_url: opt.image_url || '',
        is_correct: opt.is_correct || false
      }));
      this.render();
      if (window.onOptionsBuilderChange) {
        window.onOptionsBuilderChange(this.taskType, this.options, this.container?.id);
      }
    }

    getOptions() {
      return this.options;
    }

    addOption() {
      this.options.push({
        id: Date.now(),
        text: '',
        image_url: '',
        is_correct: false
      });
      this.render();
      if (window.onOptionsBuilderChange) {
        window.onOptionsBuilderChange(this.taskType, this.options, this.container?.id);
      }
    }

    removeOption(id) {
      this.options = this.options.filter(opt => opt.id !== id);
      this.render();
      if (window.onOptionsBuilderChange) {
        window.onOptionsBuilderChange(this.taskType, this.options, this.container?.id);
      }
    }

    updateOption(id, field, value) {
      const option = this.options.find(opt => opt.id === id);
      if (option) {
        if (field === 'is_correct' && this.taskType === 'single_choice') {
          // Single choice: uncheck all others
          this.options.forEach(opt => opt.is_correct = false);
        }
        option[field] = value;
        this.render();
        if (window.onOptionsBuilderChange) {
          window.onOptionsBuilderChange(this.taskType, this.options, this.container?.id);
        }
      }
    }

    async uploadImage(id, file) {
      const formData = new FormData();
      formData.append('image', file);

      try {
        const response = await fetch('../api/admin/tasks/upload_image.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();
        
        if (data.ok) {
          this.updateOption(id, 'image_url', data.image_url);
        } else {
          alert('Upload failed: ' + data.error);
        }
      } catch (err) {
        alert('Upload error: ' + err.message);
      }
    }

    render() {
      if (!this.container) return;

      const isSingleChoice = this.taskType === 'single_choice';
      const inputType = isSingleChoice ? 'radio' : 'checkbox';

      this.container.innerHTML = `
        <div style="margin-bottom: 10px;">
          <button type="button" class="hspf-btn hspf-btn-sm" onclick="window.currentOptionsBuilder.addOption()">
            + Antwort hinzufügen
          </button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          ${this.options.map((opt, idx) => `
            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9;">
              <div style="display: flex; gap: 10px; align-items: start;">
                <input 
                  type="${inputType}" 
                  name="correct-option-${this.taskType}" 
                  ${opt.is_correct ? 'checked' : ''}
                  onchange="window.currentOptionsBuilder.updateOption(${opt.id}, 'is_correct', this.checked)"
                  style="margin-top: 8px;"
                  title="Richtige Antwort"
                />
                <div style="flex: 1;">
                  <textarea 
                    placeholder="Antworttext..." 
                    style="width: 100%; min-height: 50px; padding: 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 14px;"
                    onchange="window.currentOptionsBuilder.updateOption(${opt.id}, 'text', this.value)"
                  >${opt.text}</textarea>
                  
                  ${opt.image_url ? `
                    <div style="margin-top: 8px;">
                      <img src="${opt.image_url}" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 3px;" />
                      <button type="button" onclick="window.currentOptionsBuilder.updateOption(${opt.id}, 'image_url', '')" 
                        style="display: block; margin-top: 4px; font-size: 12px; color: red; background: none; border: none; cursor: pointer;">
                        ✕ Bild entfernen
                      </button>
                    </div>
                  ` : `
                    <div style="margin-top: 8px;">
                      <input type="file" accept="image/*" onchange="window.currentOptionsBuilder.uploadImage(${opt.id}, this.files[0]); this.value='';"
                        style="font-size: 12px;" />
                    </div>
                  `}
                </div>
                <button type="button" onclick="window.currentOptionsBuilder.removeOption(${opt.id})"
                  style="background: none; border: none; font-size: 20px; color: #dc3545; cursor: pointer; padding: 0 8px;" 
                  title="Löschen">✕</button>
              </div>
            </div>
          `).join('')}
        </div>
        ${this.options.length === 0 ? '<p style="color: #999; font-size: 13px;">Noch keine Antwortoptionen. Klicke "+ Antwort hinzufügen"</p>' : ''}
      `;
    }
  };
})();
