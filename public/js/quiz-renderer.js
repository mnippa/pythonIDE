/**
 * Quiz Task Renderer for Single-Choice, Multiple-Choice, Free-Text
 */

window.QuizRenderer = {
  _lightboxInitialized: false,

  async getPyodideOrThrow(timeoutMs = 15000) {
    if (window.pyodide) {
      return window.pyodide;
    }

    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
      if (window.pyodide) {
        return window.pyodide;
      }
      await new Promise((resolve) => setTimeout(resolve, 100));
    }

    throw new Error('Pyodide ist noch nicht bereit');
  },

  ensureImageLightbox() {
    if (this._lightboxInitialized) return;

    let lightbox = document.getElementById('quiz-image-lightbox');
    if (!lightbox) {
      lightbox = document.createElement('div');
      lightbox.id = 'quiz-image-lightbox';
      lightbox.className = 'quiz-image-lightbox';
      lightbox.innerHTML = `
        <button type="button" class="quiz-image-lightbox-close" aria-label="Schließen">&times;</button>
        <img alt="Quiz Bild" />
      `;
      document.body.appendChild(lightbox);
    }

    const imgEl = lightbox.querySelector('img');
    const closeBtn = lightbox.querySelector('.quiz-image-lightbox-close');

    const closeLightbox = () => {
      lightbox.classList.remove('open');
      if (imgEl) {
        imgEl.src = '';
      }
    };

    closeBtn?.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        closeLightbox();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && lightbox.classList.contains('open')) {
        closeLightbox();
      }
    });

    document.addEventListener('click', (e) => {
      const imageEl = e.target.closest('.question-image, .option-image');
      if (!imageEl) return;

      e.preventDefault();
      e.stopPropagation();

      if (!imgEl) return;
      imgEl.src = imageEl.getAttribute('src') || '';
      lightbox.classList.add('open');
    });

    this._lightboxInitialized = true;
  },

  getTaskDetailsAndHints(task) {
    let html = '';
    
    // Stoff (Learning Content)
    if (task.stoff) {
      html += `<div class="stoff-section">
        <h4>📚 Lerninhalt (Stoff)</h4>
        <div>${task.stoff}</div>
      </div>`;
    }
    
    // NOTE: task_text is shown centrally in renderChoice/renderFreeText/etc,
    // not in this details panel. Description field is only for Code-task metadata.
    
    // Hints
    const availableHints = [];
    if (task.hint1) availableHints.push({ id: 1, text: task.hint1 });
    if (task.hint2) availableHints.push({ id: 2, text: task.hint2 });
    if (task.hint3) availableHints.push({ id: 3, text: task.hint3 });
    
    if (availableHints.length > 0) {
      const revealedRaw = window.assignmentState?.hintsRevealed?.[task.id] || [];
      const revealedSet = new Set(revealedRaw);
      const revealedHints = availableHints.filter(hint => revealedSet.has(hint.id));
      const nextHint = availableHints.find(hint => !revealedSet.has(hint.id));
      
      html += `<div class="task-hints-section">
        <h4>💡 Hinweise (${revealedHints.length}/${availableHints.length})</h4>`;
      
      if (revealedHints.length === 0) {
        html += '<p style="color:var(--text-secondary); font-size:14px;">Noch keine Hinweise freigeschaltet.</p>';
      }
      
      revealedHints.forEach((hint) => {
        const displayIndex = availableHints.findIndex(item => item.id === hint.id) + 1;
        html += `<div class="hint-item" style="padding:8px; margin:8px 0; background:var(--bg-secondary); border-left:3px solid var(--accent); border-radius:4px;">
          <strong>Hinweis ${displayIndex}:</strong> ${this.escapeHtml(hint.text)}
        </div>`;
      });
      
      if (nextHint) {
        const nextIndex = availableHints.findIndex(item => item.id === nextHint.id) + 1;
        html += `<button type="button" class="hint-reveal-btn-inline" data-task-id="${task.id}" data-hint-id="${nextHint.id}" style="margin-top:8px; padding:6px 12px; background:var(--accent); color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px;">Hinweis ${nextIndex} freischalten</button>`;
      }
      
      html += '</div>';
    }
    
    return html;
  },

  render(task, container) {
    this.ensureImageLightbox();

    const taskType = task.task_type;
    
    if (taskType === 'single_choice' || taskType === 'multiple_choice') {
      this.renderChoice(task, container, taskType === 'multiple_choice');
    } else if (taskType === 'free_text') {
      this.renderFreeText(task, container);
    } else if (taskType === 'db_model') {
      this.renderDbModel(task, container);
    } else if (taskType === 'uml') {
      window.UmlRenderer?.render(task, container);
    } else if (taskType === 'code_reading') {
      this.renderCodeReading(task, container);
    } else if (taskType === 'code_random_complex') {
      this.renderHiddenCode(task, container);
    }
  },
  
  renderSolution(task, container) {
    // Render quiz with correct answers marked (readonly, no interaction)
    const taskType = task.task_type;
    const isMultiple = taskType === 'multiple_choice';
    const inputType = isMultiple ? 'checkbox' : 'radio';
    const inputName = `quiz-solution-${task.id}`;
    
    container.innerHTML = `
      <div class="quiz-container solution-mode">
        <div class="quiz-question">
          ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
        </div>
        
        <div class="quiz-options">
          ${(task.options || []).map((option, idx) => {
            const isCorrect = option.is_correct;
            let optionClass = 'quiz-option';
            
            if (isCorrect) {
              optionClass += ' user-answer-correct';
            }
            
            return `
            <label class="${optionClass}">
              <input 
                type="${inputType}" 
                name="${inputName}" 
                value="${option.id}" 
                ${isCorrect ? 'checked' : ''}
                disabled
              />
              <span class="option-text">${this.escapeHtml(option.text)}</span>
              ${option.image_url ? `<img src="${option.image_url}" class="option-image" alt="Option image" />` : ''}
            </label>
            `;
          }).join('')}
        </div>
      </div>
    `;
  },

  renderChoice(task, container, isMultiple) {
    const inputType = isMultiple ? 'checkbox' : 'radio';
    const inputName = `quiz-answer-${task.id}`;
    const attemptsInfo = this.getAttemptsInfo(task);
    const disableSubmit = attemptsInfo.blocked;
    
    // Get user answer if task is completed
    const userAnswer = window.assignmentState?.taskUserAnswers?.[task.id];
    const userSelectedIds = userAnswer?.selected_options || [];
    const isCompleted = attemptsInfo.blocked || attemptsInfo.isPassed;
    const isPassed = attemptsInfo.isPassed;
    const isFailed = attemptsInfo.isFailed;
    
    console.log('[renderChoice] DEBUG:', {
      task_id: task.id,
      task_type: task.task_type,
      attemptsInfo,
      userAnswer,
      userSelectedIds,
      isCompleted,
      isPassed,
      isFailed
    });
    
    // Solution block disabled for choice tasks (highlighting is sufficient)
    let solutionHtml = '';
    
    container.innerHTML = `
      <div class="quiz-container">
        <div class="quiz-question">
          ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
        </div>
        
        <div class="quiz-options">
          ${(task.options || []).map((option, idx) => {
            const isCorrect = option.is_correct;
            const wasSelected = userSelectedIds.includes(option.id);
            let optionClass = 'quiz-option';
            
            console.log(`[Option ${option.id}] text="${option.text}" isCorrect=${isCorrect} wasSelected=${wasSelected} isCompleted=${isCompleted}`);
            
            // Add highlighting classes for completed tasks
            if (isCompleted) {
              if (isCorrect) {
                // This option is correct - always show GREEN
                optionClass += ' user-answer-correct';
                console.log(`  -> GREEN (correct)`);
              } else if (wasSelected) {
                // This option is wrong but user selected it - show RED
                optionClass += ' user-answer-incorrect';
                console.log(`  -> RED (wrong but selected)`);
              } else {
                console.log(`  -> No highlight (wrong and not selected)`);
              }
            } else {
              console.log(`  -> No highlight (not completed yet)`);
            }
            
            return `
            <label class="${optionClass}">
              <input 
                type="${inputType}" 
                name="${inputName}" 
                value="${option.id}" 
                data-option-id="${option.id}"
                ${wasSelected ? 'checked' : ''}
                ${disableSubmit ? 'disabled' : ''}
              />
              <span class="option-text">${this.escapeHtml(option.text)}</span>
              ${option.image_url ? `<img src="${option.image_url}" class="option-image" alt="Option image" />` : ''}
            </label>
            `;
          }).join('')}
        </div>
        
        <div class="quiz-actions">
          <button id="quiz-submit-${task.id}" class="hspf-btn hspf-btn-primary" onclick="window.QuizRenderer.submitQuiz(${task.id}, '${task.task_type}')" ${disableSubmit ? 'disabled' : ''}>
            Absenden
          </button>
        </div>
        ${solutionHtml}
        <div id="quiz-feedback-${task.id}" class="quiz-feedback"></div>
      </div>
    `;
  },

  renderFreeText(task, container) {
    const attemptsInfo = this.getAttemptsInfo(task);
    const disableSubmit = attemptsInfo.blocked;
    
    // Get user answer if task is completed
    const userAnswer = window.assignmentState?.taskUserAnswers?.[task.id];
    const userTextAnswer = userAnswer?.text_answer || '';
    const isCompleted = attemptsInfo.blocked || attemptsInfo.isPassed;
    const isPassed = attemptsInfo.isPassed;
    const isFailed = attemptsInfo.isFailed;
    
    // Parse test_cases array (unified structure like OUTPUT tests)
    let testCases = [];
    if (task.test_cases) {
      try {
        testCases = JSON.parse(task.test_cases);
        if (!Array.isArray(testCases)) {
          testCases = [];
        }
      } catch (e) {
        testCases = [];
      }
    }
    
    // Build validation hint based on test_cases or legacy fields
    let validationHint = '';
    let solutionHtml = '';
    
    if (Array.isArray(testCases) && testCases.length > 0) {
      // New test_cases based hints (unified with OUTPUT tests)
      const hintParts = [];
      testCases.forEach((testCase, idx) => {
        const expectedType = testCase.expected_type || 'text';
        const expected = testCase.expected || '';
        const validationMode = testCase.validation_mode || 'loose';
        const caseSensitive = testCase.case_sensitive || false;
        
        if (expectedType === 'regex') {
          const sensitivity = caseSensitive ? ' (case-sensitive)' : '';
          hintParts.push(`<div class="quiz-hint">Test #${idx + 1} - Regex Pattern${sensitivity}</div>`);
        } else {
          const modeLabels = {
            'strict': 'Exakte Übereinstimmung',
            'loose': 'Leerzeichen normalisiert',
            'contains': 'Text enthalten'
          };
          const sensitivity = caseSensitive ? ', case-sensitive' : '';
          hintParts.push(`<div class="quiz-hint">Test #${idx + 1} - Text (${modeLabels[validationMode] || validationMode}${sensitivity})</div>`);
        }
      });
      validationHint = hintParts.join('');
      
      // Solution display for test_cases
      if (attemptsInfo.blocked && attemptsInfo.isFailed && task.show_solution !== 0) {
        const solutionParts = ['<div class="quiz-solution"><h4>Erwartete Muster:</h4>'];
        testCases.forEach((testCase, idx) => {
          const expectedType = testCase.expected_type || 'text';
          const expected = testCase.expected || '';
          const validationMode = testCase.validation_mode || 'loose';
          const caseSensitive = testCase.case_sensitive || false;
          const sensitivity = caseSensitive ? ' (case-sensitive)' : '';
          
          if (expectedType === 'regex') {
            solutionParts.push(`<p><strong>Test #${idx + 1} (Regex${sensitivity}):</strong> <code>${this.escapeHtml(expected)}</code></p>`);
          } else {
            solutionParts.push(`<p><strong>Test #${idx + 1} (Text - ${validationMode}${sensitivity}):</strong> ${this.escapeHtml(expected)}</p>`);
          }
        });
        solutionParts.push('</div>');
        solutionHtml = solutionParts.join('');
      }
    } else if (task.correct_answer) {
      // Legacy keyword matching (backward compatibility)
      const keywords = task.correct_answer.split(',').map(k => k.trim()).filter(k => k !== '');
      const totalKeywords = keywords.length;
      const minRequired = (task.min_keywords_required !== null && task.min_keywords_required !== undefined) 
        ? task.min_keywords_required 
        : totalKeywords;
      
      if (totalKeywords > 0) {
        if (minRequired === totalKeywords) {
          validationHint = `<div class="quiz-hint">Alle ${totalKeywords} Schlüsselwörter müssen in der Antwort vorkommen.</div>`;
        } else {
          validationHint = `<div class="quiz-hint">Mindestens ${minRequired} von ${totalKeywords} Schlüsselwörtern müssen in der Antwort vorkommen.</div>`;
        }
      }
      
      // Solution for legacy keywords
      if (attemptsInfo.blocked && attemptsInfo.isFailed && task.show_solution !== 0) {
        solutionHtml = `
          <div class="quiz-solution">
            <h4>Erwartete Schlüsselwörter:</h4>
            <p>${this.escapeHtml(task.correct_answer)}</p>
          </div>
        `;
      }
    }
    
    // Show user's answer if completed
    let userAnswerDisplay = '';
    if (isCompleted && userTextAnswer) {
      const answerClass = isPassed ? 'user-answer-correct' : 'user-answer-incorrect';
      userAnswerDisplay = `
        <div class="user-answer-display ${answerClass}">
          <strong>${isPassed ? '✓ Deine Antwort (richtig):' : '✗ Deine Antwort (nicht ausreichend):'}</strong>
          <p>${this.escapeHtml(userTextAnswer)}</p>
        </div>
      `;
    }
    
    container.innerHTML = `
      <div class="quiz-container">
        <div class="quiz-question">
          ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
          ${validationHint}
        </div>
        
        ${userAnswerDisplay}
        
        <div class="quiz-freetext ${isCompleted && !isPassed ? 'user-answer-incorrect' : (isCompleted && isPassed ? 'user-answer-correct' : '')}">
          <textarea 
            id="freetext-answer-${task.id}" 
            placeholder="Deine Antwort..."
            rows="8"
            ${disableSubmit ? 'disabled' : ''}
          >${isCompleted ? userTextAnswer : ''}</textarea>
        </div>
        
        <div class="quiz-actions">
          <button id="quiz-submit-${task.id}" class="hspf-btn hspf-btn-primary" onclick="window.QuizRenderer.submitQuiz(${task.id}, 'free_text')" ${disableSubmit ? 'disabled' : ''}>
            Absenden
          </button>
        </div>
        ${solutionHtml}
        <div id="quiz-feedback-${task.id}" class="quiz-feedback"></div>
      </div>
    `;
  },

  getDbModelTemplateFromTask(task) {
    const fallback = {
      tables: [
        {
          name: 'kunden',
          columns: [
            { name: 'id', type: 'INTEGER', size: 11, pk: true, nullable: false, default: '' },
            { name: 'name', type: 'VARCHAR', size: 100, pk: false, nullable: false, default: '' }
          ]
        }
      ]
    };

    const raw = task?.solution_code || task?.db_template || '';
    if (typeof raw !== 'string' || !raw.trim()) {
      return fallback;
    }

    try {
      const parsed = JSON.parse(raw);
      if (parsed && Array.isArray(parsed.tables)) {
        return parsed;
      }
    } catch (_err) {
      // Fallback to the built-in starter schema if the admin template is not valid JSON.
    }

    return fallback;
  },

  getDbModelTaskState(task) {
    const fallback = this.getDbModelTemplateFromTask(task);
    const key = `__dbModelTaskState_${task?.id || 'default'}`;
    const existing = window[key];

    if (existing && Array.isArray(existing.tables)) {
      return existing;
    }

    const seeded = JSON.parse(JSON.stringify(fallback));
    window[key] = seeded;
    return seeded;
  },

  persistDbModelTaskAnswer(task, state) {
    if (!task?.id || !window.assignmentState) return;
    if (!window.assignmentState.taskUserAnswers) {
      window.assignmentState.taskUserAnswers = {};
    }
    if (!window.assignmentState.taskUserAnswers[task.id]) {
      window.assignmentState.taskUserAnswers[task.id] = {};
    }
    window.assignmentState.taskUserAnswers[task.id].text_answer = JSON.stringify(state);
  },

  setDbModelTaskState(task, state) {
    if (!task?.id) return;
    const historyKey = `__dbModelTaskHistory_${task.id}`;
    const history = window[historyKey] || { entries: [], index: -1 };
    const serialized = JSON.stringify(state);
    const lastEntry = history.entries[history.index];
    if (lastEntry !== serialized) {
      history.entries = history.entries.slice(0, history.index + 1);
      history.entries.push(serialized);
      history.index = history.entries.length - 1;
      window[historyKey] = history;
    }
    window[`__dbModelTaskState_${task.id}`] = state;
    this.persistDbModelTaskAnswer(task, state);
  },

  createDbModelEmptyTable() {
    return {
      name: 'neue_tabelle',
      rows: [],
      columns: [
        { name: 'id', type: 'INTEGER', size: 11, pk: true, nullable: false, default: '' }
      ]
    };
  },

  createDbModelEmptyColumn() {
    return { name: 'neue_spalte', type: 'VARCHAR', size: 100, pk: false, nullable: true, default: '' };
  },

  getDbModelTypeOptionsMarkup(selectedType) {
    const types = ['AUTO', 'INTEGER', 'BOOLEAN', 'DATE', 'DATETIME', 'TIME', 'VARCHAR', 'FLOAT'];
    const normalizedType = (() => {
      const value = String(selectedType || '').toUpperCase();
      if (value === 'TEXT') return 'VARCHAR';
      if (value === 'REAL' || value === 'NUMERIC') return 'FLOAT';
      return value;
    })();
    return types.map((type) => `<option value="${type}" ${normalizedType === type ? 'selected' : ''}>${type}</option>`).join('');
  },

  getDbModelColumnSizeMarkup(column, tableIndex, colIndex) {
    const type = String(column?.type || '').toUpperCase();
    const size = column?.size;
    if (type === 'AUTO') {
      return `<input value="3" disabled style="width:58px; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 6px; opacity:0.6; box-sizing:border-box;">`;
    }
    if (type === 'VARCHAR') {
      return `<input data-db-model-field="column-size" data-table-index="${tableIndex}" data-column-index="${colIndex}" value="${this.escapeHtml(String(Number.isFinite(Number(size)) ? Number(size) : 50))}" style="width:58px; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 6px; box-sizing:border-box;">`;
    }
    if (type === 'INTEGER') {
      const options = [1, 2, 3, 4, 8].map((value) => `<option value="${value}" ${Number(size || 2) === value ? 'selected' : ''}>${value}</option>`).join('');
      return `<select data-db-model-field="column-size" data-table-index="${tableIndex}" data-column-index="${colIndex}" style="width:58px; margin:0; border-radius:10px; border:1px solid var(--border); background:var(--bg); padding:5px 6px; box-sizing:border-box;">${options}</select>`;
    }
    return '';
  },

  getDbModelColumnDefaultPlaceholder(column) {
    const type = String(column?.type || '').toUpperCase();
    if (type === 'AUTO') return '';
    if (type === 'BOOLEAN') return '0 / 1';
    if (type === 'DATE') return 'YYYY-MM-DD';
    if (type === 'DATETIME') return 'YYYY-MM-DD HH:MM:SS';
    if (type === 'TIME') return 'HH:MM:SS';
    if (type === 'INTEGER') return '0';
    if (type === 'FLOAT') return '0.0';
    return '';
  },

  getDbModelRowPendingEdits(tableIndex, rowIndex) {
    const key = `${tableIndex}:${rowIndex}`;
    const pending = this.dbModelTaskPendingRowEdits || {};
    if (!pending[key] || typeof pending[key] !== 'object') {
      pending[key] = {};
      this.dbModelTaskPendingRowEdits = pending;
    }
    return pending[key];
  },

  clearDbModelRowPendingEdits(tableIndex, rowIndex) {
    const key = `${tableIndex}:${rowIndex}`;
    const pending = this.dbModelTaskPendingRowEdits || {};
    if (pending[key]) {
      delete pending[key];
      this.dbModelTaskPendingRowEdits = pending;
    }
  },

  getDbModelRowActionMarkup(tableIndex, rowIndex, isDirty = false) {
    if (isDirty) {
      return `<span style="display:inline-flex; gap:2px; align-items:center;"><button type="button" data-db-model-action="commit-row-edit" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile speichern" aria-label="Zeile speichern" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; padding:0;">✓</button><button type="button" data-db-model-action="discard-row-edit" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Änderungen verwerfen" aria-label="Änderungen verwerfen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #fecaca; background:#fff1f2; color:#b42318; border-radius:6px; padding:0;">⛔</button></span><button type="button" data-db-model-action="duplicate-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile duplizieren" aria-label="Zeile duplizieren" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">⧉</button><button type="button" data-db-model-action="delete-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" class="db-data-grid-inline-add-btn" title="Zeile löschen" aria-label="Zeile löschen" style="width:24px; height:24px;">-</button>`;
    }

    return `<button type="button" data-db-model-action="duplicate-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" title="Zeile duplizieren" aria-label="Zeile duplizieren" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background:var(--panel); border-radius:6px; padding:0;">⧉</button><button type="button" data-db-model-action="delete-row" data-table-index="${tableIndex}" data-row-index="${rowIndex}" class="db-data-grid-inline-add-btn" title="Zeile löschen" aria-label="Zeile löschen" style="width:24px; height:24px;">-</button>`;
  },

  syncDbModelRowActionButtons(container, tableIndex, rowIndex, inputEl) {
    const row = inputEl?.closest?.('tr');
    if (!row) return;
    const actionCell = row.querySelector('td:last-child');
    if (!actionCell) return;

    const key = String(inputEl?.getAttribute('data-db-input') || '');
    const pending = this.getDbModelRowPendingEdits(tableIndex, rowIndex);
    const columnName = String(inputEl?.getAttribute('data-column-name') || '').trim();
    const currentValue = inputEl?.value ?? '';
    const isDirty = Object.keys(pending || {}).length > 0 || (key === 'row-value' && columnName && String(currentValue ?? '').trim() !== '');

    if (key === 'row-new-value') {
      const shouldShowActions = String(currentValue ?? '').trim() !== '';
      row.classList.toggle('db-data-grid-row-dirty', shouldShowActions);
      actionCell.innerHTML = shouldShowActions
        ? `<span style="display:inline-flex; gap:2px; align-items:center;"><button type="button" data-db-model-action="add-row-inline" data-table-index="${tableIndex}" title="Zeile einfügen" aria-label="Zeile einfügen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; padding:0;">✓</button><button type="button" data-db-model-action="discard-inline-row" data-table-index="${tableIndex}" title="Änderungen verwerfen" aria-label="Änderungen verwerfen" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #fecaca; background:#fff1f2; color:#b42318; border-radius:6px; padding:0;">⛔</button></span>`
        : `<button type="button" data-db-model-action="add-row-inline" data-table-index="${tableIndex}" title="Zeile einfügen" style="border:1px solid #bfdbfe; background:#eff6ff; color:#2563eb; border-radius:6px; width:26px; height:26px; line-height:1; font-weight:700;">+</button>`;
      return;
    }

    row.classList.toggle('db-data-grid-row-dirty', isDirty);
    actionCell.innerHTML = this.getDbModelRowActionMarkup(tableIndex, rowIndex, isDirty);
  },

  syncDbModelRowVisualState(container, focusedInput) {
    if (!container) return;
    const activeRow = focusedInput?.closest?.('tr');
    const activeCell = focusedInput?.closest?.('td');
    container.querySelectorAll('.db-data-grid-row').forEach((row) => {
      const rowHasFocus = row === activeRow;
      row.classList.toggle('db-data-grid-row-active', rowHasFocus);
      row.querySelectorAll('td').forEach((cell) => {
        cell.classList.toggle('db-data-grid-cell-active', rowHasFocus && cell === activeCell);
      });
    });
  },

  focusDbModelGridCell(container, tableIndex, rowIndex, colIndex) {
    window.setTimeout(() => {
      if (!container) return;
      const selector = `.db-data-grid-input[data-db-input="row-value"][data-table-index="${tableIndex}"][data-row-index="${rowIndex}"][data-column-name]`;
      const inputs = Array.from(container.querySelectorAll(selector));
      const target = inputs[Math.max(0, Math.min(colIndex, inputs.length - 1))];
      if (target instanceof HTMLInputElement) {
        target.focus();
        target.select();
      }
    }, 0);
  },

  getDbModelAutoGeneratedValue(table, column, rowIndexToExclude = null) {
    const columnName = String(column?.name || '').trim();
    if (!columnName || String(column?.type || '').toUpperCase() !== 'AUTO') {
      return '';
    }

    const rows = Array.isArray(table?.rows) ? table.rows : [];
    const numericValues = rows
      .map((row, index) => {
        if (index === rowIndexToExclude) return null;
        const rawValue = row?.[columnName];
        if (rawValue === null || rawValue === undefined || rawValue === '') return null;
        const parsed = Number(String(rawValue).trim());
        return Number.isFinite(parsed) ? parsed : null;
      })
      .filter((value) => Number.isFinite(value));

    const nextValue = numericValues.length ? Math.max(...numericValues) + 1 : 1;
    return String(nextValue);
  },

  applyDbModelColumnTypeBehavior(column) {
    if (!column) return column;
    const normalizedType = String(column.type || '').toUpperCase();
    if (normalizedType === 'AUTO') {
      column.size = 3;
      column.pk = true;
      column.fk = false;
      column.nullable = false;
      column.default = '';
    } else if (normalizedType === 'VARCHAR') {
      column.size = Number.isFinite(Number(column.size)) && Number(column.size) > 0 ? Math.floor(Number(column.size)) : 50;
    } else if (normalizedType === 'INTEGER') {
      column.size = [1, 2, 3, 4, 8].includes(Math.floor(Number(column.size))) ? Math.floor(Number(column.size)) : 2;
    } else {
      column.size = null;
    }
    return column;
  },

  _dbModelTreeContextMenuState: null,

  getDbModelTreeContextMenuElement() {
    if (this._dbModelTreeContextMenuState?.menuEl && document.body.contains(this._dbModelTreeContextMenuState.menuEl)) {
      return this._dbModelTreeContextMenuState.menuEl;
    }

    const menuEl = document.createElement('div');
    menuEl.id = 'db-model-tree-context-menu';
    menuEl.style.position = 'fixed';
    menuEl.style.minWidth = '190px';
    menuEl.style.background = '#ffffff';
    menuEl.style.border = '1px solid #d1d5db';
    menuEl.style.borderRadius = '10px';
    menuEl.style.boxShadow = '0 8px 24px rgba(0,0,0,0.14)';
    menuEl.style.padding = '6px';
    menuEl.style.display = 'none';
    menuEl.style.zIndex = '9999';
    document.body.appendChild(menuEl);

    const onPointerDown = (event) => {
      if (!menuEl.contains(event.target)) {
        this.hideDbModelTreeContextMenu();
      }
    };

    const onEscape = (event) => {
      if (event.key === 'Escape') {
        this.hideDbModelTreeContextMenu();
      }
    };

    document.addEventListener('mousedown', onPointerDown, true);
    document.addEventListener('keydown', onEscape, true);

    this._dbModelTreeContextMenuState = {
      menuEl,
      dispose: () => {
        document.removeEventListener('mousedown', onPointerDown, true);
        document.removeEventListener('keydown', onEscape, true);
      }
    };

    return menuEl;
  },

  hideDbModelTreeContextMenu() {
    const menuEl = this._dbModelTreeContextMenuState?.menuEl;
    if (!menuEl) return;
    menuEl.style.display = 'none';
    menuEl.innerHTML = '';
  },

  findNextDuplicatedDbModelTableName(tables, sourceName) {
    const existingNames = new Set((tables || []).map((table) => String(table?.name || '').trim().toLowerCase()));
    const baseName = String(sourceName || '').trim() || 'tabelle';
    for (let suffix = 2; suffix < 1000; suffix += 1) {
      const candidate = `${baseName}${suffix}`;
      if (!existingNames.has(candidate.toLowerCase())) {
        return candidate;
      }
    }
    return `${baseName}${Date.now()}`;
  },

  showDbModelTreeContextMenu(tableIndex, pageX, pageY, task, state, container) {
    const menuEl = this.getDbModelTreeContextMenuElement();
    menuEl.innerHTML = '';

    const entries = [
      { label: 'Umbenennen', action: 'context-rename-table' },
      { label: 'Neue Tabelle', action: 'context-add-table' },
      { label: 'Tabellendaten löschen', action: 'context-clear-table-data' },
      { label: 'Tabelle löschen', action: 'context-delete-table', danger: true },
      { label: 'Tabelle duplizieren', action: 'context-duplicate-table' }
    ];

    entries.forEach((entry) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = entry.label;
      btn.style.display = 'block';
      btn.style.width = '100%';
      btn.style.textAlign = 'left';
      btn.style.border = 'none';
      btn.style.background = 'transparent';
      btn.style.padding = '8px 10px';
      btn.style.borderRadius = '8px';
      btn.style.cursor = 'pointer';
      btn.style.fontSize = '13px';
      btn.style.color = entry.danger ? '#b91c1c' : '#111827';
      btn.addEventListener('mouseenter', () => {
        btn.style.background = entry.danger ? '#fff1f2' : '#f3f4f6';
      });
      btn.addEventListener('mouseleave', () => {
        btn.style.background = 'transparent';
      });
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        this.hideDbModelTreeContextMenu();
        this.handleDbModelAction(task, entry.action, { getAttribute: (name) => (name === 'data-table-index' ? String(tableIndex) : null) }, state, container);
      });
      menuEl.appendChild(btn);
    });

    menuEl.style.display = 'block';
    menuEl.style.left = `${Math.max(8, Number(pageX) || 0)}px`;
    menuEl.style.top = `${Math.max(8, Number(pageY) || 0)}px`;

    const rect = menuEl.getBoundingClientRect();
    const maxLeft = window.innerWidth - rect.width - 8;
    const maxTop = window.innerHeight - rect.height - 8;
    menuEl.style.left = `${Math.min(Math.max(8, Number(pageX) || 0), Math.max(8, maxLeft))}px`;
    menuEl.style.top = `${Math.min(Math.max(8, Number(pageY) || 0), Math.max(8, maxTop))}px`;
  },

  handleDbModelAction(task, action, buttonEl, state, container) {
    const tables = Array.isArray(state?.tables) ? state.tables : [];
    const tableIndex = Number(buttonEl?.getAttribute('data-table-index') || -1);
    const columnIndex = Number(buttonEl?.getAttribute('data-column-index') || -1);
    const table = tables[tableIndex];

    if (action === 'select-table') {
      state.selectedTableIndex = tableIndex >= 0 ? tableIndex : 0;
      this.setDbModelTaskState(task, state);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'add-table' || action === 'context-add-table') {
      tables.push(this.createDbModelEmptyTable());
      state.selectedTableIndex = tables.length - 1;
      this.setDbModelTaskState(task, state);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'save-structure') {
      this.setDbModelTaskState(task, state);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'discard') {
      const resetState = this.getDbModelTemplateFromTask(task);
      resetState.tables = Array.isArray(resetState.tables) ? resetState.tables : [];
      resetState.selectedTableIndex = 0;
      this.setDbModelTaskState(task, resetState);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'undo') {
      const historyKey = `__dbModelTaskHistory_${task.id}`;
      const history = window[historyKey] || { entries: [], index: -1 };
      if (history.index > 0) {
        history.index -= 1;
        window[historyKey] = history;
        const restored = JSON.parse(history.entries[history.index]);
        this.setDbModelTaskState(task, restored);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'redo') {
      const historyKey = `__dbModelTaskHistory_${task.id}`;
      const history = window[historyKey] || { entries: [], index: -1 };
      if (history.index >= 0 && history.index < history.entries.length - 1) {
        history.index += 1;
        window[historyKey] = history;
        const restored = JSON.parse(history.entries[history.index]);
        this.setDbModelTaskState(task, restored);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'delete-table' || action === 'context-delete-table') {
      if (tableIndex >= 0 && tables[tableIndex]) {
        tables.splice(tableIndex, 1);
        state.selectedTableIndex = Math.max(0, Math.min(tableIndex, tables.length - 1));
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'context-rename-table' && table) {
      const currentName = String(table?.name || '').trim() || `Tabelle ${tableIndex + 1}`;
      const renamed = window.prompt('Neuer Tabellenname:', currentName);
      if (renamed == null) return;
      const nextName = String(renamed || '').trim();
      if (!nextName) return;
      table.name = nextName;
      this.setDbModelTaskState(task, state);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'context-clear-table-data' && table) {
      table.rows = [];
      this.setDbModelTaskState(task, state);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'context-duplicate-table' && table) {
      const clone = JSON.parse(JSON.stringify(table));
      clone.name = this.findNextDuplicatedDbModelTableName(tables, table.name);
      tables.splice(tableIndex + 1, 0, clone);
      state.selectedTableIndex = tableIndex + 1;
      this.setDbModelTaskState(task, state);
      this.renderDbModel(task, container);
      return;
    }

    if (action === 'add-column-inline') {
      if (tableIndex >= 0 && tables[tableIndex]) {
        const newColumn = this.createDbModelEmptyColumn();
        const nameInput = container.querySelector('[data-db-model-field="new-column-name"]');
        const typeInput = container.querySelector('[data-db-model-field="new-column-type"]');
        const sizeInput = container.querySelector('[data-db-model-field="new-column-size"]');
        const pkInput = container.querySelector('[data-db-model-field="new-column-pk"]');
        const fkInput = container.querySelector('[data-db-model-field="new-column-fk"]');
        const nullableInput = container.querySelector('[data-db-model-field="new-column-nullable"]');
        const defaultInput = container.querySelector('[data-db-model-field="new-column-default"]');

        newColumn.name = String(nameInput?.value || '').trim() || newColumn.name;
        newColumn.type = String(typeInput?.value || 'VARCHAR').toUpperCase();
        const sizeValue = Number(sizeInput?.value);
        if (newColumn.type === 'AUTO') {
          this.applyDbModelColumnTypeBehavior(newColumn);
        } else {
          newColumn.size = Number.isFinite(sizeValue) && sizeValue > 0 ? Math.floor(sizeValue) : 100;
          newColumn.pk = Boolean(pkInput?.checked);
          newColumn.fk = Boolean(fkInput?.checked);
          newColumn.nullable = Boolean(nullableInput?.checked);
          newColumn.default = String(defaultInput?.value ?? '');
        }

        tables[tableIndex].columns = Array.isArray(tables[tableIndex].columns) ? tables[tableIndex].columns : [];
        tables[tableIndex].columns.push(newColumn);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'add-row') {
      if (tableIndex >= 0 && tables[tableIndex]) {
        tables[tableIndex].rows = Array.isArray(tables[tableIndex].rows) ? tables[tableIndex].rows : [];
        const newRow = {};
        (tables[tableIndex].columns || []).forEach((column) => {
          const colName = String(column?.name || '').trim();
          if (!colName) return;
          const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
          newRow[colName] = isAutoColumn ? this.getDbModelAutoGeneratedValue(tables[tableIndex], column) : '';
        });
        tables[tableIndex].rows.push(newRow);
        const rowIndex = tables[tableIndex].rows.length - 1;
        const pending = this.getDbModelRowPendingEdits(tableIndex, rowIndex);
        pending.__new__ = true;
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
        this.focusDbModelGridCell(container, tableIndex, rowIndex, 0);
      }
      return;
    }

    if (action === 'add-row-inline') {
      if (tableIndex >= 0 && tables[tableIndex]) {
        const rowValues = {};
        const columnInputs = container.querySelectorAll('[data-db-input="row-new-value"][data-table-index="' + tableIndex + '"]');
        columnInputs.forEach((input) => {
          const columnName = String(input.getAttribute('data-column-name') || '').trim();
          if (!columnName) return;
          const column = (tables[tableIndex].columns || []).find((entry) => String(entry?.name || '').trim() === columnName);
          const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
          const rawValue = input.value;
          rowValues[columnName] = isAutoColumn
            ? (String(rawValue).trim() !== '' ? rawValue : this.getDbModelAutoGeneratedValue(tables[tableIndex], column))
            : rawValue;
        });
        tables[tableIndex].rows = Array.isArray(tables[tableIndex].rows) ? tables[tableIndex].rows : [];
        tables[tableIndex].rows.push(rowValues);
        const rowIndex = tables[tableIndex].rows.length - 1;
        this.clearDbModelRowPendingEdits(tableIndex, rowIndex);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
        this.focusDbModelGridCell(container, tableIndex, rowIndex, 0);
      }
      return;
    }

    if (action === 'commit-row-edit') {
      if (tableIndex >= 0 && tables[tableIndex] && Number.isInteger(columnIndex) && columnIndex >= 0) {
        const row = tables[tableIndex].rows?.[columnIndex];
        if (row && typeof row === 'object') {
          const pending = this.getDbModelRowPendingEdits(tableIndex, columnIndex);
          const pendingEntries = Object.entries(pending || {}).filter(([colName]) => colName !== '__new__');
          (tables[tableIndex].columns || []).forEach((column) => {
            const colName = String(column?.name || '').trim();
            if (!colName) return;
            const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
            const pendingValue = pendingEntries.find(([name]) => name === colName)?.[1];
            const nextValue = isAutoColumn
              ? (pendingValue !== undefined && String(pendingValue).trim() !== '' ? pendingValue : this.getDbModelAutoGeneratedValue(tables[tableIndex], column, columnIndex))
              : (pendingValue !== undefined ? pendingValue : row[colName]);
            row[colName] = String(nextValue ?? '');
          });
        }
        this.clearDbModelRowPendingEdits(tableIndex, columnIndex);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'discard-row-edit') {
      if (tableIndex >= 0 && tables[tableIndex] && Number.isInteger(columnIndex) && columnIndex >= 0) {
        const pending = this.getDbModelRowPendingEdits(tableIndex, columnIndex);
        if (pending?.__new__) {
          tables[tableIndex].rows.splice(columnIndex, 1);
        }
        this.clearDbModelRowPendingEdits(tableIndex, columnIndex);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'discard-inline-row') {
      if (tableIndex >= 0 && tables[tableIndex]) {
        this.clearDbModelRowPendingEdits(tableIndex, tables[tableIndex].rows.length);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'duplicate-row') {
      if (tableIndex >= 0 && tables[tableIndex] && Number.isInteger(columnIndex) && columnIndex >= 0) {
        const source = tables[tableIndex].rows?.[columnIndex];
        if (source && typeof source === 'object') {
          const clone = JSON.parse(JSON.stringify(source));
          (tables[tableIndex].columns || []).forEach((column) => {
            const colName = String(column?.name || '').trim();
            if (!colName) return;
            if (String(column?.type || '').toUpperCase() === 'AUTO') {
              clone[colName] = this.getDbModelAutoGeneratedValue(tables[tableIndex], column, columnIndex);
            }
          });
          tables[tableIndex].rows.splice(columnIndex + 1, 0, clone);
          this.setDbModelTaskState(task, state);
          this.renderDbModel(task, container);
          this.focusDbModelGridCell(container, tableIndex, columnIndex + 1, 0);
        }
      }
      return;
    }

    if (action === 'delete-row') {
      if (tableIndex >= 0 && tables[tableIndex] && Number.isInteger(columnIndex) && columnIndex >= 0) {
        tables[tableIndex].rows.splice(columnIndex, 1);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }

    if (action === 'delete-column') {
      if (tableIndex >= 0 && columnIndex >= 0 && tables[tableIndex] && Array.isArray(tables[tableIndex].columns)) {
        tables[tableIndex].columns.splice(columnIndex, 1);
        this.setDbModelTaskState(task, state);
        this.renderDbModel(task, container);
      }
      return;
    }
  },

  getDbModelTreeMarkup(task, state, selectedTableIndex = 0) {
    const tables = Array.isArray(state?.tables) ? state.tables : [];
    const listItems = tables.map((table, tableIndex) => {
      const isActive = tableIndex === selectedTableIndex;
      return `
        <button type="button" class="db-tree-item ${isActive ? 'active' : ''}" data-db-model-action="select-table" data-table-index="${tableIndex}">
          <span>📄</span>
          <span>${this.escapeHtml(String(table.name || 'neue_tabelle'))}</span>
        </button>
      `;
    }).join('');

    return `
      <div class="db-model-task-tree-shell">
        <div class="db-model-task-panel-header">
          <span>DB Struktur</span>
          <span style="font-size:11px; font-weight:600; color:var(--text-secondary);">DB</span>
        </div>
        <div class="db-model-task-list">
          ${listItems || '<div class="db-model-task-empty-state">Noch keine Tabellen vorhanden.</div>'}
        </div>
        <div class="db-model-task-sidebar-actions">
          <button type="button" data-db-model-action="add-table" class="hspf-btn hspf-btn-primary">+ Tabelle</button>
        </div>
      </div>
    `;
  },

  getDbModelTaskQuestionMarkup(task, stateHint = '') {
    return `
      <div class="db-model-task-question-shell">
        <div class="db-model-task-panel-header">
          <span>Aufgabe</span>
          <span style="font-size:11px; font-weight:600; color:var(--text-secondary);">Frage</span>
        </div>
        <div class="db-model-task-question-content">
          ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
          ${stateHint}
          <div class="quiz-hint">Die Vorlage aus dem Admin-Template wird als Startzustand geladen. Die Bearbeitung erfolgt im linken Baum und im mittleren Entwurfsbereich.</div>
        </div>
      </div>
    `;
  },

  renderDbModelSidebar(task, container) {
    if (!container) return;
    this.ensureDbModelTaskStyles();

    const state = this.getDbModelTaskState(task);
    const tables = Array.isArray(state?.tables) ? state.tables : [];
    const selectedTableIndex = Math.max(0, Number(state.selectedTableIndex || 0));
    const listItems = tables.map((table, tableIndex) => {
      const isActive = tableIndex === selectedTableIndex;
      return `
        <button type="button" class="db-tree-item ${isActive ? 'active' : ''}" data-db-model-action="select-table" data-table-index="${tableIndex}">
          <span>📄</span>
          <span>${this.escapeHtml(String(table.name || 'neue_tabelle'))}</span>
        </button>
      `;
    }).join('');

    container.innerHTML = `
      <div class="db-model-task-sidebar-panel">
        <div class="db-model-task-panel-header">
          <span>🗄️ Struktur</span>
          <span style="display:inline-flex; align-items:center; gap:6px; margin-left:auto;">
            <button type="button" data-db-model-action="add-table" title="Neue Tabelle" aria-label="Neue Tabelle" style="width:26px; height:26px; min-width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #93c5fd; background:#2563eb; color:#ffffff; font-weight:700;">+</button>
            <button type="button" data-db-model-action="delete-table" data-table-index="${selectedTableIndex}" title="Markierte Tabelle löschen" aria-label="Markierte Tabelle löschen" style="width:26px; height:26px; min-width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #fecaca; background:#fff1f2; color:#dc2626; font-weight:700;">✕</button>
          </span>
        </div>
        <div class="db-model-task-list">
          ${listItems || '<div class="db-model-task-empty-state">Noch keine Tabellen vorhanden.</div>'}
        </div>
        <div class="db-model-task-sidebar-actions">
          <button type="button" data-db-model-action="add-table" class="hspf-btn hspf-btn-primary">+ Tabelle</button>
        </div>
      </div>
    `;

    container.querySelectorAll('[data-db-model-action="add-table"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        this.handleDbModelAction(task, 'add-table', btn, state, document.getElementById('quiz-container'));
        this.renderDbModelSidebar(task, container);
      });
    });

    container.querySelectorAll('[data-db-model-action="select-table"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        this.handleDbModelAction(task, 'select-table', btn, state, document.getElementById('quiz-container'));
        this.renderDbModelSidebar(task, container);
      });
    });

    container.querySelectorAll('.db-tree-item').forEach((btn) => {
      btn.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        const tableIndex = Number(btn.getAttribute('data-table-index') || -1);
        if (tableIndex < 0) return;
        state.selectedTableIndex = tableIndex;
        this.setDbModelTaskState(task, state);
        this.showDbModelTreeContextMenu(tableIndex, event.pageX, event.pageY, task, state, document.getElementById('quiz-container'));
      });
    });

    container.querySelectorAll('[data-db-model-action="delete-table"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        this.handleDbModelAction(task, 'delete-table', btn, state, document.getElementById('quiz-container'));
        this.renderDbModelSidebar(task, container);
      });
    });
  },

  ensureDbModelTaskStyles() {
    if (document.getElementById('db-model-task-styles')) return;

    const style = document.createElement('style');
    style.id = 'db-model-task-styles';
    style.textContent = `
      .quiz-container.db-model-task-quiz {
        max-width: none;
        width: 100%;
        height: 100%;
        min-height: 0;
        margin: 0;
        padding: 0;
        border-radius: 0;
        border: none;
        background: transparent;
        box-shadow: none;
      }

      .db-model-task-shell {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        min-height: 0;
        border: 1px solid var(--border);
        border-radius: 0;
        overflow: hidden;
        background: var(--bg);
        box-sizing: border-box;
      }

      .db-model-task-sidebar,
      .db-model-task-center {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
        box-sizing: border-box;
      }

      .db-model-task-sidebar {
        border-right: 1px solid var(--border);
        background: var(--panel);
        overflow: hidden;
      }

      .db-model-task-center {
        background: var(--bg);
        overflow: hidden;
      }

      .db-model-task-tree-shell {
        display: flex;
        flex-direction: column;
        height: 100%;
      }

      .db-model-sidebar-slot {
        margin-top: 10px;
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: var(--panel);
      }

      .db-model-task-sidebar-panel {
        display: flex;
        flex-direction: column;
        min-height: 180px;
      }

      .db-tree-item {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        margin: 2px 0;
        border-radius: 4px;
        background: var(--panel);
        color: var(--text-primary);
        cursor: pointer;
        text-align: left;
        font-size: 12px;
        border: 1px solid transparent;
      }

      .db-tree-item:hover {
        background: var(--bg);
      }

      .db-tree-item.active {
        background: #dbeafe;
        color: #0c4a6e;
        font-weight: 600;
      }

      .db-model-task-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: var(--text-primary);
        background: var(--panel);
      }

      .db-model-task-list {
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        overflow-y: auto;
        flex: 1 1 auto;
      }

      .db-model-task-list-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        border-radius: 10px;
        border: 1px solid var(--border);
        padding: 8px 10px;
        background: var(--bg);
        color: var(--text-primary);
        cursor: pointer;
        text-align: left;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }

      .db-model-task-list-item:hover {
        border-color: #93c5fd;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.12);
      }

      .db-model-task-list-item.active {
        background: #dbeafe;
        border-color: #667eea;
        color: #0c4a6e;
        font-weight: 600;
        box-shadow: inset 0 0 0 1px rgba(102, 126, 234, 0.15);
      }

      .db-model-task-sidebar-actions {
        padding: 10px 12px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        background: rgba(255, 255, 255, 0.45);
      }

      .db-model-task-main {
        display: grid;
        grid-template-rows: minmax(0, 1fr) minmax(220px, 0.85fr);
        min-height: 0;
        height: 100%;
        min-width: 0;
      }

      .db-model-task-design-pane,
      .db-model-task-data-pane {
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
      }

      .db-model-task-design-pane {
        border-bottom: 1px solid var(--border);
      }

      .db-model-task-design-content,
      .db-model-task-data-content {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow: auto;
        flex: 1 1 auto;
      }

      .db-model-task-editor-card {
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--panel);
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
      }

      .db-model-task-editor-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
      }

      .db-model-task-editor-top input {
        flex: 1;
        padding: 6px 8px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg);
      }

      .db-model-task-editor-toolbar {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
      }

      .db-model-task-column-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 1px;
        font-size: 12px;
      }

      .db-model-task-column-table {
        table-layout: fixed;
        width: 100%;
        min-width: 0;
      }

      .db-model-task-column-table th {
        text-align: left;
        padding: 0 1px 1px 1px;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .db-model-task-column-table td {
        padding: 0 1px;
        min-width: 0;
      }

      .db-model-task-column-table td > input,
      .db-model-task-column-table td > select,
      .db-model-task-column-table td > div {
        min-width: 0;
        width: 100%;
      }

      .db-model-task-column-table input,
      .db-model-task-column-table select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--bg);
        box-sizing: border-box;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
      }

      .db-model-task-column-table .db-model-task-inline-add {
        background: #f0fdf4;
        border-style: dashed;
        border-color: #86efac;
      }

      .db-model-task-column-table .db-model-task-checkbox-cell {
        width: 34px;
        text-align: center;
      }

      .db-model-task-column-table .db-model-task-action-cell {
        width: 36px;
      }

      .db-model-task-data-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
      }

      .db-model-task-data-toolbar input {
        width: 180px;
        max-width: 220px;
        padding: 4px 6px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg);
        font-size: 12px;
      }

      .db-model-task-data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
      }

      .db-model-task-data-table th,
      .db-model-task-data-table td {
        text-align: left;
        padding: 4px 6px;
        border-bottom: 1px solid #e5e7eb;
      }

      .db-model-task-data-table th {
        color: var(--text-secondary);
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
      }

      .db-model-task-data-table tbody tr:nth-child(even) {
        background: #f8fafc;
      }

      .db-data-grid-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
      }

      .db-data-grid-table th,
      .db-data-grid-table td {
        text-align: left;
        padding: 4px 6px;
        border-bottom: 1px solid #e5e7eb;
      }

      .db-data-grid-table th {
        color: var(--text-secondary);
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
      }

      .db-data-grid-input {
        width: 100%;
        padding: 4px 6px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg);
        box-sizing: border-box;
      }

      .db-data-grid-inline-row {
        background: #fbfdff;
      }

      .db-data-grid-row-dirty > td {
        background: #fefce8;
      }

      .db-data-grid-row-active > td {
        background: #f3f8ff;
      }

      .db-data-grid-cell-active {
        background: #fff7cc !important;
        box-shadow: inset 0 0 0 1px #60a5fa;
      }

      .db-data-grid-row td {
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
      }

      .db-data-grid-row:hover td,
      .db-data-grid-row:focus-within td {
        background: #f8fafc;
        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.16);
      }

      .db-data-grid-input {
        width: 100%;
        border: 1px solid transparent;
        border-radius: 4px;
        padding: 4px 6px;
        background: transparent;
        color: inherit;
      }

      .db-data-grid-input:focus {
        outline: none;
        border-color: #2563eb;
        background: #fef3c7;
        box-shadow: inset 0 0 0 1px #2563eb;
      }

      .db-data-grid-input[readonly] {
        background: #f8fafc;
        color: #64748b;
        cursor: not-allowed;
      }

      .db-data-grid-inline-add-btn {
        width: 26px;
        height: 26px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #2563eb;
        border-radius: 6px;
        font-weight: 700;
      }

      .db-model-task-column-row label {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        color: var(--text-secondary);
        white-space: nowrap;
      }

      .db-model-task-preview textarea {
        width: 100%;
        min-height: 140px;
        padding: 8px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg);
        font-family: ui-monospace, monospace;
        font-size: 12px;
        resize: vertical;
      }

      .db-model-task-question-content {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow: auto;
        flex: 1 1 auto;
      }

      .db-model-task-question-content .question-text {
        font-size: 14px;
        line-height: 1.5;
      }

      .db-model-task-question-content .question-image {
        width: 100%;
        height: auto;
        border-radius: 8px;
        border: 1px solid var(--border);
      }

      .db-model-task-empty-state {
        padding: 12px;
        border: 1px dashed var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        background: var(--bg);
      }

      @media (max-width: 900px) {
        .db-model-task-shell {
          display: flex;
          flex-direction: column;
        }
        .db-model-task-sidebar,
        .db-model-task-center {
          border: none;
          border-bottom: 1px solid var(--border);
        }
      }
    `;
    document.head.appendChild(style);
  },

  renderDbModel(task, container) {
    this.ensureDbModelTaskStyles();

    const attemptsInfo = this.getAttemptsInfo(task);
    const disableSubmit = attemptsInfo.blocked;

    const userAnswer = window.assignmentState?.taskUserAnswers?.[task.id];
    const userTextAnswer = userAnswer?.text_answer || '';

    const existingInMemoryState = window[`__dbModelTaskState_${task?.id || 'default'}`];
    let state = existingInMemoryState && Array.isArray(existingInMemoryState.tables)
      ? JSON.parse(JSON.stringify(existingInMemoryState))
      : null;

    if (!state && typeof userTextAnswer === 'string' && userTextAnswer.trim()) {
      try {
        const parsed = JSON.parse(userTextAnswer);
        if (parsed && Array.isArray(parsed.tables)) {
          state = parsed;
        }
      } catch (_err) {
        // Fall back to the current in-memory editor state.
      }
    }

    if (!state) {
      state = this.getDbModelTaskState(task);
    }

    state.tables = Array.isArray(state.tables) ? state.tables : [];
    state.selectedTableIndex = Number.isInteger(state.selectedTableIndex) ? state.selectedTableIndex : 0;
    if (state.selectedTableIndex < 0) state.selectedTableIndex = 0;
    if (state.selectedTableIndex >= state.tables.length) {
      state.selectedTableIndex = Math.max(0, state.tables.length - 1);
    }
    this.setDbModelTaskState(task, state);

    let stateHint = '<div class="quiz-hint">Diese Aufgabe wird manuell bewertet.</div>';
    if (attemptsInfo.isSubmitted) {
      stateHint = '<div class="quiz-hint">Antwort abgegeben. Bewertung erfolgt manuell.</div>';
    }

    const selectedTableIndex = Math.max(0, Number(state.selectedTableIndex || 0));
    const selectedTable = state.tables[selectedTableIndex] || null;
    const selectedRowsMarkup = selectedTable
      ? (() => {
          const rows = Array.isArray(selectedTable.rows) ? selectedTable.rows : [];
          return rows.map((row, rowIndex) => {
            const cells = (selectedTable.columns || []).map((column) => {
              const columnName = String(column?.name || '').trim();
              const value = columnName ? (row?.[columnName] ?? '') : '';
              const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
              const readOnlyAttr = isAutoColumn ? 'readonly' : '';
              const styleAttr = isAutoColumn ? 'background:#f8fafc; color:#64748b;' : '';
              return `<td><input class="db-data-grid-input" data-db-model-field="row-value" data-db-input="row-value" data-table-index="${selectedTableIndex}" data-row-index="${rowIndex}" data-column-name="${this.escapeHtml(columnName)}" value="${this.escapeHtml(String(value ?? ''))}" placeholder="Wert" ${disableSubmit ? 'disabled' : ''} ${readOnlyAttr} style="${styleAttr}"></td>`;
            }).join('');
            return `
              <tr class="db-data-grid-row">
                <td style="padding:4px 6px; color:#666; border-right:1px solid #eee;">${rowIndex + 1}</td>
                ${cells}
                <td style="padding:2px; display:flex; gap:2px; align-items:center; justify-content:flex-end;">${this.getDbModelRowActionMarkup(selectedTableIndex, rowIndex, false)}</td>
              </tr>
            `;
          }).join('');
        })()
      : '';
    const inlineRowMarkup = selectedTable
      ? (() => {
          const columns = selectedTable.columns || [];
          const cells = columns.map((column) => {
            const columnName = String(column?.name || '').trim();
            const isAutoColumn = String(column?.type || '').toUpperCase() === 'AUTO';
            const readOnlyAttr = isAutoColumn ? 'readonly' : '';
            const defaultValue = isAutoColumn ? this.getDbModelAutoGeneratedValue(selectedTable, column) : '';
            const styleAttr = isAutoColumn ? 'background:#f8fafc; color:#64748b;' : '';
            return `<td><input class="db-data-grid-input" data-db-model-field="row-value" data-db-input="row-new-value" data-table-index="${selectedTableIndex}" data-row-index="${Array.isArray(selectedTable.rows) ? selectedTable.rows.length : 0}" data-column-name="${this.escapeHtml(columnName)}" value="${this.escapeHtml(String(defaultValue))}" placeholder="neu..." ${disableSubmit ? 'disabled' : ''} ${readOnlyAttr} style="${styleAttr}"></td>`;
          }).join('');
          return `
            <tr class="db-data-grid-row db-data-grid-inline-row">
              <td style="padding:4px 6px; color:#3b82f6; border-right:1px solid #eee;">+</td>
              ${cells}
              <td style="padding:2px;">
                <button type="button" data-db-model-action="add-row-inline" data-table-index="${selectedTableIndex}" class="db-data-grid-inline-add-btn" ${disableSubmit ? 'disabled' : ''}>+</button>
              </td>
            </tr>
          `;
        })()
      : '';
    const selectedColumnsMarkup = selectedTable
      ? (selectedTable.columns || []).map((column, columnIndex) => {
          const columnType = String(column.type || 'VARCHAR').toUpperCase();
          const defaultPlaceholder = this.getDbModelColumnDefaultPlaceholder(column);
          return `
            <tr>
              <td class="db-model-task-checkbox-cell"><input data-db-model-field="column-pk" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" type="checkbox" ${column.pk ? 'checked' : ''} ${disableSubmit ? 'disabled' : ''}></td>
              <td class="db-model-task-checkbox-cell"><input data-db-model-field="column-fk" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" type="checkbox" ${column.fk ? 'checked' : ''} ${disableSubmit ? 'disabled' : ''}></td>
              <td class="db-model-task-checkbox-cell"><input data-db-model-field="column-nullable" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" type="checkbox" ${column.nullable ? 'checked' : ''} ${disableSubmit ? 'disabled' : ''}></td>
              <td><input data-db-model-field="column-name" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" value="${this.escapeHtml(String(column.name || ''))}" placeholder="Spaltenname" ${disableSubmit ? 'disabled' : ''} style="width:100%; min-width:0; box-sizing:border-box;"></td>
              <td><div style="display:flex; align-items:center; gap:0; flex-wrap:nowrap; min-width:0; width:100%;"><select data-db-model-field="column-type" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" ${disableSubmit ? 'disabled' : ''} style="flex:1 1 0; min-width:0; width:100%; box-sizing:border-box;">${this.getDbModelTypeOptionsMarkup(columnType)}</select>${this.getDbModelColumnSizeMarkup(column, selectedTableIndex, columnIndex)}</div></td>
              <td><input data-db-model-field="column-default" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" value="${this.escapeHtml(String(column.default ?? ''))}" placeholder="${this.escapeHtml(defaultPlaceholder)}" ${disableSubmit ? 'disabled' : ''} style="width:100%; min-width:0; box-sizing:border-box;"></td>
              <td class="db-model-task-action-cell"><button type="button" data-db-model-action="delete-column" data-table-index="${selectedTableIndex}" data-column-index="${columnIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>✕</button></td>
            </tr>
          `;
        }).join('')
      : '<tr><td colspan="7" style="padding:8px; color:var(--text-secondary);">Noch keine Tabelle ausgewählt.</td></tr>';

    const addColumnRowMarkup = selectedTable ? `
      <tr class="db-model-task-inline-add" style="background:#f0fdf4;">
        <td class="db-model-task-checkbox-cell"><input data-db-model-field="new-column-pk" type="checkbox" ${disableSubmit ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td class="db-model-task-checkbox-cell"><input data-db-model-field="new-column-fk" type="checkbox" ${disableSubmit ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td class="db-model-task-checkbox-cell"><input data-db-model-field="new-column-nullable" type="checkbox" checked ${disableSubmit ? 'disabled' : ''} style="margin:0; width:12px; height:12px; padding:0;"></td>
        <td><input data-db-model-field="new-column-name" placeholder="neu..." ${disableSubmit ? 'disabled' : ''} style="width:100%; min-width:0; margin:0; border-radius:10px; border:1px dashed #86efac; background:#ffffff; padding:5px 8px; box-sizing:border-box;"></td>
        <td><div style="display:flex; align-items:center; gap:0; flex-wrap:nowrap; min-width:0; width:100%;"><select data-db-model-field="new-column-type" ${disableSubmit ? 'disabled' : ''} style="flex:1 1 0; min-width:0; width:100%; margin:0; border-radius:10px 0 0 10px; border:1px solid var(--border); border-right:none; background:#ffffff; padding:4px 6px; box-sizing:border-box;">${this.getDbModelTypeOptionsMarkup('VARCHAR')}</select><input data-db-model-field="new-column-size" type="number" min="1" value="100" style="width:58px; flex:0 0 58px; margin:0; border-radius:0 10px 10px 0; border:1px solid var(--border); background:#ffffff; padding:4px 6px; box-sizing:border-box;" ${disableSubmit ? 'disabled' : ''}></div></td>
        <td><input data-db-model-field="new-column-default" placeholder="Default" ${disableSubmit ? 'disabled' : ''} style="width:100%; min-width:0; margin:0; border-radius:10px; border:1px dashed #86efac; background:#ffffff; padding:4px 6px; box-sizing:border-box;"></td>
        <td class="db-model-task-action-cell"><button type="button" data-db-model-action="add-column-inline" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''} style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; min-width:32px; border:1px solid #93c5fd; background:#2563eb; color:#ffffff; border-radius:10px; padding:0; font-size:18px; font-weight:700; box-shadow:0 1px 2px rgba(0,0,0,0.06);">+</button></td>
      </tr>
    ` : '';

    const jsonPreview = JSON.stringify(state, null, 2);

    container.innerHTML = `
      <div class="quiz-container db-model-task-quiz">
        <div class="db-model-task-shell">
          <section class="db-model-task-center">
            <div class="db-model-task-main">
              <div class="db-model-task-design-pane">
                <div class="db-model-task-panel-header" style="border-bottom:none; padding-bottom:0; background:transparent;">
                  <span style="display:none;">Entwurf</span>
                </div>
                <div class="db-model-task-design-content">
                  ${selectedTable ? `
                    <div class="db-model-task-editor-card">
                      <div class="db-model-task-editor-top">
                        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:16px; font-weight:700; line-height:1.1; min-width:0; flex:1 1 auto;">
                          <span>Entwurf:</span>
                          <span style="min-width:140px; max-width:220px; font-size:16px; font-weight:700; color:#2563eb; padding:6px 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${this.escapeHtml(String(selectedTable.name || ''))}</span>
                        </div>
                        <div class="db-model-task-editor-toolbar">
                          <button type="button" data-db-model-action="save-structure" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>Speichern</button>
                          <button type="button" data-db-model-action="undo" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>↺</button>
                          <button type="button" data-db-model-action="redo" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>↻</button>
                          <button type="button" data-db-model-action="discard" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>Verwerfen</button>
                        </div>
                      </div>
                      <table class="db-model-task-column-table">
                        <thead>
                          <tr>
                            <th class="db-model-task-checkbox-cell" style="width:28px;">PK</th>
                            <th class="db-model-task-checkbox-cell" style="width:28px;">FK</th>
                            <th class="db-model-task-checkbox-cell" style="width:34px;">Null</th>
                            <th style="width:auto;">Name</th>
                            <th style="width:210px;">Typ</th>
                            <th style="width:64px;">Default</th>
                            <th class="db-model-task-action-cell" style="width:40px;"></th>
                          </tr>
                        </thead>
                        <tbody>
                          ${selectedColumnsMarkup}
                          ${addColumnRowMarkup}
                        </tbody>
                      </table>
                    </div>
                  ` : '<div class="db-model-task-editor-card"><div class="db-model-task-empty-state">Füge oben eine Tabelle hinzu, um mit dem Entwurf zu starten.</div></div>'}
                </div>
              </div>

              <div class="db-model-task-data-pane">
                <div class="db-model-task-panel-header" style="border-bottom:none; padding-bottom:0; background:transparent;">
                  <span style="display:none;">Daten</span>
                </div>
                <div class="db-model-task-data-content">
                  <div class="db-model-task-editor-card">
                    <div class="db-model-task-data-toolbar">
                      <strong>Daten (${this.escapeHtml(String(selectedTable?.name || 'Tabelle'))})</strong>
                      <div class="db-model-task-editor-toolbar">
                        <input type="text" placeholder="Filter..." data-db-model-field="data-filter" data-db-model-action="data-filter" value="" style="min-width:170px; max-width:220px; padding:6px 8px; border:1px solid var(--border); border-radius:8px; background:var(--bg);">
                        <button type="button" data-db-model-action="save-structure" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>💾</button>
                        <button type="button" data-db-model-action="add-row" data-table-index="${selectedTableIndex}" class="hspf-btn hspf-btn-sm" ${disableSubmit ? 'disabled' : ''}>＋</button>
                      </div>
                    </div>
                    <div style="overflow:auto;">
                      <table class="db-data-grid-table">
                        <thead>
                          <tr>
                            <th style="width:42px;">#</th>
                            ${selectedTable ? (selectedTable.columns || []).map((column) => `<th>${this.escapeHtml(String(column.name || ''))}</th>`).join('') : '<th>Spalte</th>'}
                            <th style="width:56px;">Aktion</th>
                          </tr>
                        </thead>
                        <tbody>
                          ${selectedTable
                            ? `${selectedRowsMarkup}${inlineRowMarkup}`
                            : '<tr><td colspan="3" style="padding:8px; color:var(--text-secondary);">Noch keine Tabelle ausgewählt.</td></tr>'}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="quiz-actions">
          <button id="quiz-submit-${task.id}" class="hspf-btn hspf-btn-primary" onclick="window.QuizRenderer.submitQuiz(${task.id}, 'db_model')" ${disableSubmit ? 'disabled' : ''}>
            Abgeben
          </button>
        </div>
        <div id="quiz-feedback-${task.id}" class="quiz-feedback"></div>
      </div>
    `;

    const previewEl = container.querySelector('[data-db-model-preview]');
    const updatePreview = () => {
      if (previewEl) {
        previewEl.value = JSON.stringify(this.getDbModelTaskState(task), null, 2);
      }
    };

    if (!container.__dbModelActionHandlerBound) {
      container.addEventListener('click', (event) => {
        const actionEl = event.target.closest('[data-db-model-action]');
        if (!actionEl || !container.contains(actionEl)) return;
        const action = actionEl.getAttribute('data-db-model-action');
        if (!action) return;
        event.preventDefault();
        this.handleDbModelAction(task, action, actionEl, this.getDbModelTaskState(task), container);
      });
      container.__dbModelActionHandlerBound = true;
    }

    const sidebarEl = document.getElementById(`db-model-sidebar-${task.id}`);
    if (sidebarEl) {
      this.renderDbModelSidebar(task, sidebarEl);
    }

    container.querySelectorAll('[data-db-model-field]').forEach((input) => {
      const updateFromField = () => {
        const field = input.getAttribute('data-db-model-field');
        const tableIndex = Number(input.getAttribute('data-table-index') || -1);
        const columnIndex = Number(input.getAttribute('data-column-index') || -1);
        if (field === 'table-name' && tableIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex]) {
          state.tables[tableIndex].name = input.value.trim() || 'neue_tabelle';
        } else if (field === 'column-name' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          state.tables[tableIndex].columns[columnIndex].name = input.value.trim() || 'neue_spalte';
        } else if (field === 'column-type' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          const column = state.tables[tableIndex].columns[columnIndex];
          column.type = String(input.value || 'VARCHAR').toUpperCase();
          this.applyDbModelColumnTypeBehavior(column);
          this.renderDbModel(task, container);
        } else if (field === 'column-size' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          const sizeValue = Number(input.value);
          state.tables[tableIndex].columns[columnIndex].size = Number.isFinite(sizeValue) && sizeValue > 0 ? Math.floor(sizeValue) : 100;
        } else if (field === 'column-default' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          state.tables[tableIndex].columns[columnIndex].default = input.value;
        } else if (field === 'row-value' && tableIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].rows)) {
          const rowIndex = Number(input.getAttribute('data-row-index') || -1);
          const columnName = String(input.getAttribute('data-column-name') || '').trim();
          if (rowIndex >= 0 && columnName) {
            state.tables[tableIndex].rows[rowIndex] = state.tables[tableIndex].rows[rowIndex] || {};
            state.tables[tableIndex].rows[rowIndex][columnName] = input.value;
            const pending = this.getDbModelRowPendingEdits(tableIndex, rowIndex);
            pending[columnName] = input.value;
          }
        } else if (field === 'column-pk' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          state.tables[tableIndex].columns[columnIndex].pk = input.checked;
        } else if (field === 'column-fk' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          state.tables[tableIndex].columns[columnIndex].fk = input.checked;
        } else if (field === 'column-nullable' && tableIndex >= 0 && columnIndex >= 0 && Array.isArray(state.tables) && state.tables[tableIndex] && Array.isArray(state.tables[tableIndex].columns)) {
          state.tables[tableIndex].columns[columnIndex].nullable = input.checked;
        }
        this.setDbModelTaskState(task, state);
        updatePreview();
      };
      input.addEventListener('focusin', () => {
        this.syncDbModelRowVisualState(container, input);
      });
      input.addEventListener('focusout', () => {
        window.setTimeout(() => {
          this.syncDbModelRowVisualState(container, container.querySelector('.db-data-grid-input:focus'));
        }, 0);
      });
      input.addEventListener('input', () => {
        updateFromField();
        if (input.getAttribute('data-db-model-field') === 'data-filter') {
          const filterValue = String(input.value || '').trim().toLowerCase();
          container.querySelectorAll('.db-data-grid-row').forEach((row) => {
            const rowText = row.textContent.toLowerCase();
            row.style.display = rowText.includes(filterValue) ? '' : 'none';
          });
          return;
        }
        this.syncDbModelRowActionButtons(container, Number(input.getAttribute('data-table-index') || -1), Number(input.getAttribute('data-row-index') || -1), input);
      });
      input.addEventListener('change', updateFromField);
      if (input.getAttribute('data-db-input') === 'row-new-value') {
        input.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            this.handleDbModelAction(task, 'add-row-inline', input, state, container);
          }
        });
      }
    });
  },

  renderCodeReading(task, container) {
    const attemptsInfo = this.getAttemptsInfo(task);
    const disableSubmit = attemptsInfo.blocked;
    const isCompleted = attemptsInfo.blocked || attemptsInfo.isPassed;
    const isPassed = attemptsInfo.isPassed;
    const iterationInfo = this.getIterationInfo(task);
    const currentIteration = iterationInfo ? iterationInfo.current : 1;
    
    // Get previous answer if task was already attempted
    const userAnswer = window.assignmentState?.taskUserAnswers?.[task.id];
    const userTextAnswer = userAnswer?.text_answer || '';
    const answerClass = isCompleted && !isPassed ? 'user-answer-incorrect' : (isCompleted && isPassed ? 'user-answer-correct' : '');
    
    // Generate random variable values if not already stored
    let varValues = {};
    let expectedVariableName = task.correct_answer; // Default fallback
    let expectedType = 'variable';
    let expectedValue = '';
    let shouldSendComputedValue = true; // Whether to send computed_value to backend
    
    if (task.task_type === 'code_random_complex' && task.variable_overrides) {
      throw new Error('code_random_complex erlaubt keine festen Wertepaare (variable_overrides)');
    }

    if (task.variable_overrides) {
      try {
        const overrides = typeof task.variable_overrides === 'string'
          ? JSON.parse(task.variable_overrides)
          : task.variable_overrides;

        // Array of fixed sets (preferred format)
        if (Array.isArray(overrides) && overrides.length > 0) {
          const idx = Math.max(0, currentIteration - 1) % overrides.length;
          const selectedSet = overrides[idx];
          if (selectedSet && typeof selectedSet === 'object') {
            // NEW SCHEMA: {inputs: {...}, expected: {...}}
            if (selectedSet.inputs && typeof selectedSet.inputs === 'object') {
              varValues = selectedSet.inputs;
              // Extract expected configuration
              if (selectedSet.expected && typeof selectedSet.expected === 'object') {
                if (selectedSet.expected.variable) {
                  expectedVariableName = selectedSet.expected.variable;
                  expectedType = 'variable';
                  shouldSendComputedValue = true; // Variable mode: send computed value
                } else if (selectedSet.expected.hasOwnProperty('value')) {
                  // If value is set directly, no need to compute
                  expectedType = 'value';
                  expectedValue = selectedSet.expected.value;
                  shouldSendComputedValue = false;
                }
              }
            } else {
              // LEGACY SCHEMA: inputs directly in object
              varValues = selectedSet;
            }
          }
        }
        // Legacy object with arrays: pick deterministic value per iteration
        else if (typeof overrides === 'object' && overrides !== null) {
          for (const varName in overrides) {
            const possibleValues = overrides[varName];
            if (Array.isArray(possibleValues) && possibleValues.length > 0) {
              const idx = Math.max(0, currentIteration - 1) % possibleValues.length;
              varValues[varName] = possibleValues[idx];
            } else {
              varValues[varName] = possibleValues;
            }
          }
        }
      } catch (err) {
        console.error('Failed to parse variable_overrides:', err);
      }
    }
    
    // Store var values for later verification
    window.currentCodeReadingVars = varValues;
    if (window.assignmentState && task) {
      if (!window.assignmentState.taskUserAnswers[task.id]) {
        window.assignmentState.taskUserAnswers[task.id] = {};
      }
      window.assignmentState.taskUserAnswers[task.id].variable_values = varValues;
      window.assignmentState.taskUserAnswers[task.id].iteration = currentIteration;
      window.assignmentState.taskUserAnswers[task.id].expectedVariableName = expectedVariableName;
      window.assignmentState.taskUserAnswers[task.id].expectedType = expectedType;
      window.assignmentState.taskUserAnswers[task.id].expectedValue = expectedValue;
      window.assignmentState.taskUserAnswers[task.id].shouldSendComputedValue = shouldSendComputedValue;
    }
    
    const showCode =
      task.show_solution_code === 1 ||
      task.show_solution_code === true ||
      task.show_solution_code === '1' ||
      task.show_solution_code === 'true';

    // Only show code block if code_template is set
    const hasCodeTemplate = task.code_template && task.code_template.trim() !== '';
    
    let codeBlock = '';
    if (hasCodeTemplate) {
      // Build code display without replacing placeholders; only highlight variables
      let codeDisplay = task.code_template;
      
      // Remove curly braces from placeholders
      codeDisplay = codeDisplay.replace(/\{(\w+)\}/g, '$1');

      // Highlight variables in the code
      for (const varName in varValues) {
        codeDisplay = codeDisplay.replace(
          new RegExp(`\\b${varName}\\b`, 'g'),
          `<span class="var-highlight" title="${varName} = ${varValues[varName]}">${varName}</span>`
        );
      }
      
      codeBlock = showCode
        ? `<div class="code-reading-code">
            <pre><code>${codeDisplay}</code></pre>
          </div>`
        : `<div class="code-reading-code">
            <em>Code ist ausgeblendet (Algorithmus ist bekannt).</em>
          </div>`;
    }

    container.innerHTML = `
      <div class="quiz-container">
        <div class="quiz-question">
          ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
        </div>
        
        ${iterationInfo ? this.getIterationHtml(iterationInfo) : ''}
        <div class="code-reading-vars">
          <strong>Variablenwerte:</strong>
          <ul>
            ${Object.entries(varValues).map(([name, value]) => 
              `<li><code>${name} = ${value}</code></li>`
            ).join('')}
          </ul>
        </div>
        ${codeBlock}
        
        <div class="quiz-question">
          <label for="code-reading-answer-${task.id}">${expectedType === 'value'
            ? 'Was ist das Ergebnis?'
            : `Was ist der Wert von <code>${this.escapeHtml(expectedVariableName || '?')}</code> am Ende?`}</label>
          <input 
            type="text" 
            id="code-reading-answer-${task.id}" 
            class="${answerClass}"
            value="${this.escapeHtml(isCompleted ? userTextAnswer : '')}"
            placeholder="Ergebnis eingeben..."
            ${disableSubmit ? 'disabled' : ''}
          />
        </div>
        
        <div class="quiz-actions">
          <button id="quiz-submit-${task.id}" class="hspf-btn hspf-btn-primary" onclick="window.QuizRenderer.submitQuiz(${task.id}, 'code_reading')" ${disableSubmit ? 'disabled' : ''}>
            Absenden
          </button>
        </div>
        <div id="quiz-feedback-${task.id}" class="quiz-feedback"></div>
      </div>
    `;
  },

  renderHiddenCode(task, container) {
    const attemptsInfo = this.getAttemptsInfo(task);
    const disableSubmit = attemptsInfo.blocked;
    const userAnswer = window.assignmentState?.taskUserAnswers?.[task.id];
    const userTextAnswer = userAnswer?.text_answer || '';
    const isCompleted = attemptsInfo.blocked || attemptsInfo.isPassed;
    const isPassed = attemptsInfo.isPassed;
    const iterationInfo = this.getIterationInfo(task);
    const currentIteration = iterationInfo ? iterationInfo.current : 1;
    const values = userAnswer?.variable_values || {};
    const valuesIteration = userAnswer?.iteration || currentIteration;
    const hasValues = values && Object.keys(values).length > 0 && valuesIteration === currentIteration;

    if (!hasValues) {
      container.innerHTML = `
        <div class="quiz-container">
          ${iterationInfo ? this.getIterationHtml(iterationInfo) : ''}
          <div class="quiz-question">
            ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
            ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
          </div>
          <div class="quiz-values loading">Werte werden geladen...</div>
        </div>
      `;

      this.ensureGeneratedValues(task)
        .then(() => this.renderHiddenCode(task, container))
        .catch(err => {
          container.innerHTML = `
            <div class="quiz-container">
              <div class="quiz-feedback">
                <div class="error">Generator-Fehler: ${this.escapeHtml(String(err))}</div>
              </div>
            </div>
          `;
        });
      return;
    }

    const answerClass = isCompleted ? (isPassed ? 'user-answer-correct' : 'user-answer-incorrect') : '';
    const valuesHtml = Object.entries(values).map(([key, value]) => {
      if (key === 'coins') {
        return '';
      }

      if (key === 'start_dir') {
        const arrowMap = {
          east: '➡️',
          south: '⬇️',
          west: '⬅️',
          north: '⬆️'
        };
        const arrow = arrowMap[String(value).toLowerCase()] || String(value);
        return `<li><code>start_dir = ${this.escapeHtml(arrow)}</code></li>`;
      }

      if (key === 'board_lines' && Array.isArray(value) && value.every(line => typeof line === 'string')) {
        const symbolMap = {
          '#': '⬛',
          '.': '⬜',
          'S': '🟦',
          'C': '🪙',
          'G': '🚪'
        };
        const board = value
          .map(line => Array.from(line).map(ch => symbolMap[ch] || ch).join(''))
          .join('\n');
        return `
          <li class="quiz-value-board">
            <details>
              <summary>Spielfeld anzeigen</summary>
              <pre>${this.escapeHtml(board)}</pre>
            </details>
          </li>
        `;
      }

      const formatted = typeof value === 'object' ? JSON.stringify(value) : String(value);
      return `<li><code>${this.escapeHtml(key)} = ${this.escapeHtml(formatted)}</code></li>`;
    }).join('');
    
    // Show solution code only if show_solution_code is enabled
    const showGenerator =
      task.show_solution_code === 1 ||
      task.show_solution_code === true ||
      task.show_solution_code === '1' ||
      task.show_solution_code === 'true';
    
    // Build code display without replacing placeholders (formatted like code_reading)
    let codeDisplay = '';
    const rawCode = task.solution_code || task.code_template || '';
    if (showGenerator && rawCode) {
      // Convert escaped newlines to actual newlines (safeguard for older data)
      codeDisplay = rawCode.replace(/\\n/g, '\n');
      
      // Remove curly braces from placeholders
      codeDisplay = codeDisplay.replace(/\{(\w+)\}/g, '$1');

      // Highlight variables in the code
      for (const varName in values) {
        codeDisplay = codeDisplay.replace(
          new RegExp(`\\b${varName}\\b`, 'g'),
          `<span class="var-highlight" title="${varName} = ${values[varName]}">${varName}</span>`
        );
      }
    }
    
    const solutionCodeHtml = showGenerator && rawCode ? `
      <div class="code-random-complex-code">
        <pre><code>${codeDisplay}</code></pre>
      </div>
    ` : '';

    container.innerHTML = `
      <div class="quiz-container">
        <div class="quiz-question">
          ${task.task_text ? `<div class="question-text">${this.formatText(task.task_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
        </div>
        
        ${iterationInfo ? this.getIterationHtml(iterationInfo) : ''}
        <div class="quiz-values quiz-values--rc">
          <strong>Gegebene Werte:</strong>
          <ul>
            ${valuesHtml}
          </ul>
        </div>
        
        ${solutionCodeHtml}

        <div class="quiz-answer ${answerClass}">
          <label for="code-hidden-answer-${task.id}">Antwort</label>
          <input 
            type="text" 
            id="code-hidden-answer-${task.id}" 
            value="${this.escapeHtml(isCompleted ? userTextAnswer : '')}"
            placeholder="Ergebnis eingeben..."
            ${disableSubmit ? 'disabled' : ''}
          />
        </div>

        <div class="quiz-actions">
          <button id="quiz-submit-${task.id}" class="hspf-btn hspf-btn-primary" onclick="window.QuizRenderer.submitQuiz(${task.id}, 'code_random_complex')" ${disableSubmit ? 'disabled' : ''}>
            Absenden
          </button>
        </div>
        <div id="quiz-feedback-${task.id}" class="quiz-feedback"></div>
      </div>
    `;
  },

  async submitQuiz(taskId, taskType) {
    const feedbackEl = document.getElementById(`quiz-feedback-${taskId}`);
    const submitBtn = document.getElementById(`quiz-submit-${taskId}`);
    
    let answer = null;
    
    if (taskType === 'single_choice') {
      const selected = document.querySelector(`input[name="quiz-answer-${taskId}"]:checked`);
      if (!selected) {
        feedbackEl.innerHTML = '<div class="error">Bitte eine Antwort auswählen</div>';
        return;
      }
      answer = { selected_options: [parseInt(selected.value)] };
    } else if (taskType === 'multiple_choice') {
      const selected = Array.from(document.querySelectorAll(`input[name="quiz-answer-${taskId}"]:checked`));
      if (selected.length === 0) {
        feedbackEl.innerHTML = '<div class="error">Bitte mindestens eine Antwort auswählen</div>';
        return;
      }
      answer = { selected_options: selected.map(el => parseInt(el.value)) };
    } else if (taskType === 'free_text') {
      const textarea = document.getElementById(`freetext-answer-${taskId}`);
      const text = textarea.value.trim();
      if (!text) {
        feedbackEl.innerHTML = '<div class="error">Bitte eine Antwort eingeben</div>';
        return;
      }
      answer = { text_answer: text };
    } else if (taskType === 'db_model') {
      const state = window[`__dbModelTaskState_${taskId}`];
      const text = state ? JSON.stringify(state) : '';
      if (!text || text === '{}') {
        feedbackEl.innerHTML = '<div class="error">Bitte ein DB-Modell anlegen</div>';
        return;
      }
      answer = { text_answer: text };
    } else if (taskType === 'uml') {
      const state = window.UmlRenderer?.getState(taskId);
      const text = state ? JSON.stringify(state) : '';
      if (!text || text === '{}') {
        feedbackEl.innerHTML = '<div class="error">Bitte ein UML-Diagramm anlegen</div>';
        return;
      }
      answer = { text_answer: text };
    } else if (taskType === 'code_reading') {
      const input = document.getElementById(`code-reading-answer-${taskId}`);
      const value = input.value.trim();
      if (!value) {
        feedbackEl.innerHTML = '<div class="error">Bitte das Ergebnis eingeben</div>';
        return;
      }
      const task = window.assignmentState?.currentTask;
      if (!task || !task.code_template) {
        feedbackEl.innerHTML = '<div class="error">Code-Reading Aufgabe ist unvollstaendig</div>';
        return;
      }

      let pyodide = null;
      try {
        pyodide = await this.getPyodideOrThrow();
      } catch (_err) {
        feedbackEl.innerHTML = '<div class="error">Pyodide ist noch nicht bereit</div>';
        return;
      }

      const varValues = window.currentCodeReadingVars || {};
      let codeToEvaluate = task.code_template;
      
      // Replace template placeholders with actual values
      for (const varName in varValues) {
        const placeholder = `{${varName}}`;
        const value = varValues[varName];
        const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
        codeToEvaluate = codeToEvaluate.replace(regex, String(value));
      }

      let computedValue = null;
      
      // Only compute value if this iteration expects a variable-based expected value
      const shouldSendComputedValue = window.assignmentState?.taskUserAnswers?.[taskId]?.shouldSendComputedValue !== false;
      if (shouldSendComputedValue) {
        try {
          const expectedVarName = window.assignmentState?.taskUserAnswers?.[taskId]?.expectedVariableName || task.correct_answer;
          computedValue = await this.evaluateCodeReading(
            pyodide,
            codeToEvaluate,
            expectedVarName,
            varValues
          );
        } catch (err) {
          feedbackEl.innerHTML = `<div class="error">Code-Auswertung fehlgeschlagen: ${this.escapeHtml(String(err))}</div>`;
          return;
        }
      }

      answer = {
        text_answer: value,
        variable_values: window.currentCodeReadingVars,
        computed_value: computedValue
      };
    } else if (taskType === 'code_random_complex') {
      const input = document.getElementById(`code-hidden-answer-${taskId}`);
      const value = input.value.trim();
      if (!value) {
        feedbackEl.innerHTML = '<div class="error">Bitte das Ergebnis eingeben</div>';
        return;
      }

      const task = window.assignmentState?.currentTask;
      if (!task || !task.solution_code) {
        feedbackEl.innerHTML = '<div class="error">Loesungs-Code fehlt</div>';
        return;
      }

      const values = window.assignmentState?.taskUserAnswers?.[taskId]?.variable_values || {};
      if (!values || Object.keys(values).length === 0) {
        feedbackEl.innerHTML = '<div class="error">Werte sind noch nicht geladen</div>';
        return;
      }

      let pyodide = null;
      try {
        pyodide = await this.getPyodideOrThrow();
      } catch (_err) {
        feedbackEl.innerHTML = '<div class="error">Pyodide ist noch nicht bereit</div>';
        return;
      }

      let computedValue = null;
      try {
        computedValue = await this.evaluateHiddenSolution(
          pyodide,
          task.solution_code,
          task.correct_answer,
          values
        );
      } catch (err) {
        feedbackEl.innerHTML = `<div class="error">Code-Auswertung fehlgeschlagen: ${this.escapeHtml(String(err))}</div>`;
        return;
      }

      answer = {
        text_answer: value,
        variable_values: values,
        computed_value: computedValue
      };
    }
    
    // Submit to API
    try {
      // Determine API endpoint and payload based on mode
      const isTestMode = window.testMode === true;
      const apiEndpoint = isTestMode ? '../api/user_tasks/test_submission.php' : '../api/user_tasks/submit_quiz.php';
      
      const payload = {
        task_id: taskId,
        ...answer
      };
      
      // In test mode, include current state from TestMode
      if (isTestMode && typeof TestMode !== 'undefined') {
        const taskState = TestMode.getTaskState(taskId);
        if (taskState) {
          payload.current_attempts = taskState.attempts || 0;
          payload.current_iteration = taskState.current_iteration || 1;
          payload.current_status = taskState.status || 'unbearbeitet';
        }
      }

      // Flush partial seconds to DB at the moment of submission
      if (!window.testMode) window.flushHeartbeat?.(taskId);

      console.log('[QUIZ] Submitting quiz answer - Endpoint: ' + apiEndpoint + ' - Payload:', payload);
      const response = await fetch(apiEndpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      console.log('[QUIZ] Response:', data);
      
      // In test mode, record submission
      if (isTestMode && typeof TestMode !== 'undefined') {
        TestMode.recordSubmission(taskId, payload, data);
      }
      
      if (data.ok) {
        const isSubmitted = data.status === 'submitted';
        const isPassed = data.is_correct;
        const feedbackClass = isSubmitted
          ? 'success'
          : (isPassed ? 'success' : (data.status === 'in_progress' ? 'warning' : 'error'));
        const feedbackTitle = isSubmitted
          ? 'Antwort abgegeben'
          : (isPassed ? '✓ Richtig!' : (data.status === 'in_progress' ? 'Noch nicht richtig' : '✗ Leider falsch'));
        let attemptsInfo = '';
        if (typeof data.attempts === 'number' && typeof data.max_attempts === 'number') {
          const isIterative = ['code_reading', 'code_random_complex'].includes(window.assignmentState?.currentTask?.task_type);
          const attemptsLabel = isIterative ? 'Fehlversuch' : 'Versuch';
          attemptsInfo = `<br/>${attemptsLabel}: ${data.attempts}/${data.max_attempts}`;
        }

        feedbackEl.innerHTML = `
          <div class="${feedbackClass}">
            ${feedbackTitle}
            ${data.message ? `<br/>${data.message}` : ''}
            ${attemptsInfo}
          </div>
        `;

        if (submitBtn && data.status === 'failed' && typeof data.attempts === 'number' && typeof data.max_attempts === 'number') {
          if (data.attempts >= data.max_attempts) {
            submitBtn.disabled = true;
          }
        }
        
        // Update task attempts and status in state
        if (window.assignmentState && taskId) {
          window.assignmentState.taskAttempts[taskId] = data.attempts;
          window.assignmentState.taskStatuses[taskId] = data.status;
          const currentTaskType = window.assignmentState?.currentTask?.task_type;
          if (typeof data.current_iteration === 'number') {
            window.assignmentState.taskIterations[taskId] = data.current_iteration;
          }
          
          // In test mode, also update TestMode storage
          if (window.testMode === true && typeof TestMode !== 'undefined') {
            TestMode.setTaskState(taskId, {
              status: data.status,
              attempts: data.attempts,
              current_iteration: typeof data.current_iteration === 'number' ? data.current_iteration : undefined
            });
          }
          
          // Update options with is_correct from response (for choice tasks)
          if (data.options) {
            console.log('[submitQuiz] Updating task options with is_correct from response:', data.options);
            
            // Update in tasks dictionary
            if (window.assignmentState.tasks && window.assignmentState.tasks[taskId]) {
              window.assignmentState.tasks[taskId].options = data.options;
            }
            
            // Update in currentTask if this is the current task
            if (window.assignmentState.currentTask && window.assignmentState.currentTask.id === taskId) {
              window.assignmentState.currentTask.options = data.options;
              console.log('[submitQuiz] Updated currentTask.options:', window.assignmentState.currentTask.options);
            }
          }
          
          // Store user answer for display
          if (!window.assignmentState.taskUserAnswers[taskId]) {
            window.assignmentState.taskUserAnswers[taskId] = {};
          }
          if (typeof data.current_iteration === 'number') {
            window.assignmentState.taskUserAnswers[taskId].iteration = data.current_iteration;
          }
          
          // Save the submitted answer
          if (answer.selected_options) {
            window.assignmentState.taskUserAnswers[taskId].selected_options = answer.selected_options;
          }
          if (answer.text_answer) {
            window.assignmentState.taskUserAnswers[taskId].text_answer = answer.text_answer;
          }
          if (answer.variable_values) {
            window.assignmentState.taskUserAnswers[taskId].variable_values = answer.variable_values;
          }
          if (data.is_correct && data.status === 'in_progress' && ['code_reading', 'code_random_complex'].includes(currentTaskType)) {
            window.assignmentState.taskUserAnswers[taskId].text_answer = '';
          }
          if (data.reset_values) {
            window.assignmentState.taskUserAnswers[taskId].variable_values = {};
            window.assignmentState.taskUserAnswers[taskId].text_answer = '';
          }
        }
        
        // Update task details panel to show new attempts count
        if (window.assignmentState && window.assignmentState.currentTask) {
          const currentTask = window.assignmentState.currentTask;
          if (window.showTaskDetails) {
            window.showTaskDetails(currentTask, 'details');
          }
        }
        
        // Update task status in state and refresh task navigation list immediately
        console.log('[QUIZ] Task status updated - Status:', data.status, 'Attempts:', data.attempts);
        window.assignmentState.taskStatuses[taskId] = data.status;
        window.assignmentState.taskAttempts[taskId] = data.attempts;
        if (window.renderTaskNavigation) {
          console.log('[QUIZ] Refreshing task navigation list');
          window.renderTaskNavigation();
        }

        // Heartbeat: stop on final, reset counter on continued attempts
        if (!window.testMode) {
          if (data.status === 'passed' || data.status === 'failed' || data.status === 'submitted') {
            window.stopActivityTracking?.(taskId);
          } else if (data.status === 'in-progress' || data.status === 'in_progress') {
            window.resetHeartbeatCounter?.(taskId);
          }
        }

        // Show success modal for passed quiz tasks
        if (data.status === 'passed' && window.showSuccessModal) {
          const task = window.assignmentState?.currentTask;
          if (task) {
            console.log('[QUIZ] Showing success modal for task:', task.id);
            window.showSuccessModal(task, data.attempts, data.max_attempts);
          }
        }
        
        // Re-render quiz to show solution or disable form after submission
        // For code_random_complex, keep feedback but re-render on success to disable form
        const taskType = window.assignmentState?.currentTask?.task_type;
        if (
          data.status === 'submitted' ||
          data.status === 'passed' ||
          (data.status === 'failed' && data.attempts >= data.max_attempts) ||
          data.reset_values ||
          (data.is_correct && data.status === 'in_progress' && ['code_reading', 'code_random_complex'].includes(taskType))
        ) {
          const task = window.assignmentState?.currentTask;
          const quizContainer = document.getElementById('quiz-container');
          if (task && quizContainer) {
            console.log('[QUIZ] Re-rendering quiz after submission');
            // Show feedback for 2 seconds before re-rendering to allow user to see result
            const delay = (data.is_correct && data.status === 'in_progress') ? 2000 : 1500;
            setTimeout(() => this.render(task, quizContainer), delay);
          }
        }
        
        // Reload assignment in background to update any other data
        if (window.loadAssignments) {
          console.log('[QUIZ] Reloading assignments in background');
          // Don't wait - just trigger in background
          window.loadAssignments().catch(err => console.error('Failed to reload assignments:', err));
        }
      } else {
        feedbackEl.innerHTML = `<div class="error">Fehler: ${data.error}</div>`;
        if (submitBtn && data.error && data.error.toLowerCase().includes('maximale')) {
          submitBtn.disabled = true;
        }
      }
    } catch (err) {
      feedbackEl.innerHTML = `<div class="error">Netzwerkfehler: ${err.message}</div>`;
    }
  },

  getAttemptsInfo(task) {
    const maxAttempts = task && typeof task.max_attempts === 'number' ? task.max_attempts : 1;
    const attempts = window.assignmentState && task ? (window.assignmentState.taskAttempts[task.id] || 0) : 0;
    const status = window.assignmentState && task ? (window.assignmentState.taskStatuses[task.id] || '') : '';
    const isLimitedType = ['single_choice', 'multiple_choice', 'free_text', 'db_model', 'code_reading', 'code_random_complex'].includes(task.task_type);
    
    // Block if already passed
    if (status === 'passed') {
      return {
        blocked: true,
        isFailed: false,
        isPassed: true,
        isSubmitted: false
      };
    }

    if (status === 'submitted') {
      return {
        blocked: true,
        isFailed: false,
        isPassed: false,
        isSubmitted: true
      };
    }
    
    if (!isLimitedType) {
      return { blocked: false, isFailed: false, isPassed: false, isSubmitted: false };
    }

    const blocked = attempts >= maxAttempts;
    const isFailed = status === 'failed';
    const isPassed = status === 'passed';
    const isSubmitted = status === 'submitted';
    return { blocked, isFailed, isPassed, isSubmitted };
  },

  getCurrentIteration(task) {
    const iter = window.assignmentState && task
      ? window.assignmentState.taskIterations[task.id]
      : null;
    return iter && iter > 0 ? iter : 1;
  },

  getMaxIterations(task) {
    if (!task) return 1;
    const maxFromTask = task.max_iterations;
    if (typeof maxFromTask === 'number' && maxFromTask > 0) {
      return maxFromTask;
    }
    if (task.variable_overrides) {
      try {
        const overrides = typeof task.variable_overrides === 'string'
          ? JSON.parse(task.variable_overrides)
          : task.variable_overrides;
        if (Array.isArray(overrides) && overrides.length > 0) {
          return overrides.length;
        }
      } catch (err) {
        console.warn('Failed to parse variable_overrides for max_iterations:', err);
      }
    }
    return 1;
  },

  getIterationInfo(task) {
    const isIterative = task && (task.task_type === 'code_reading' || task.task_type === 'code_random_complex');
    if (!isIterative) return null;
    const current = this.getCurrentIteration(task);
    const max = this.getMaxIterations(task);
    const storedValues = window.assignmentState?.taskUserAnswers?.[task.id]?.iteration_values || null;
    return { current, max, taskId: task.id, storedValues };
  },

  getIterationHtml(iterationInfo) {
    if (!iterationInfo) return '';
    const { current, max, taskId, storedValues } = iterationInfo;
    const percentage = Math.round((current / max) * 100);

    // Build pagination buttons for all iterations up to current
    let buttonsHtml = '';
    for (let i = 1; i <= max; i++) {
      const isCurrent = i === current;
      const isPast = i < current;
      const isFuture = i > current;
      let btnClass = 'iter-btn';
      if (isCurrent) btnClass += ' iter-btn--active';
      if (isPast) btnClass += ' iter-btn--done';
      if (isFuture) btnClass += ' iter-btn--locked';

      const clickable = isPast && storedValues;
      const onclick = clickable
        ? `onclick="window.QuizRenderer.showIterationReview(${taskId}, ${i - 1})" title="Iteration ${i} ansehen"`
        : isCurrent ? `title="Aktuelle Iteration"` : `title="Noch gesperrt" disabled`;

      buttonsHtml += `<button class="${btnClass}" ${clickable ? '' : 'disabled'} ${onclick}>${i}</button>`;
    }

    return `
      <div class="quiz-iteration-bar">
        <div class="iteration-header">
          <span>📊 Iteration ${current} / ${max}</span>
          <div class="iter-pagination">${buttonsHtml}</div>
        </div>
        <div class="iteration-progress-bar">
          <div class="iteration-progress-fill" style="width: ${percentage}%"></div>
        </div>
        <div id="iter-review-${taskId}" class="iter-review" style="display:none;"></div>
      </div>
    `;
  },

  showIterationReview(taskId, idx) {
    const reviewEl = document.getElementById(`iter-review-${taskId}`);
    if (!reviewEl) return;

    const iv = window.assignmentState?.taskUserAnswers?.[taskId]?.iteration_values;
    if (!Array.isArray(iv) || !iv[idx]) {
      reviewEl.style.display = 'none';
      return;
    }

    // Toggle: clicking the same iteration again hides the panel
    const currentlyShowing = reviewEl.dataset.showing === String(idx);
    if (currentlyShowing) {
      reviewEl.style.display = 'none';
      reviewEl.dataset.showing = '';
      // Reset all buttons highlight
      const bar = reviewEl.closest('.quiz-iteration-bar');
      if (bar) bar.querySelectorAll('.iter-btn--reviewing').forEach(b => b.classList.remove('iter-btn--reviewing'));
      return;
    }

    const entry = iv[idx];
    const inputs = entry.inputs;
    const answer = entry.answer;

    let inputsHtml = '';
    if (inputs && Object.keys(inputs).length > 0) {
      inputsHtml = Object.entries(inputs).map(([k, v]) => {
        const formatted = typeof v === 'object' ? JSON.stringify(v) : String(v);
        return `<li><code>${this.escapeHtml(k)} = ${this.escapeHtml(formatted)}</code></li>`;
      }).join('');
      inputsHtml = `<ul>${inputsHtml}</ul>`;
    } else {
      inputsHtml = `<em>(Werte nicht verfügbar – Legacy-Iteration)</em>`;
    }

    const answerHtml = answer !== null && answer !== undefined
      ? `<span class="iter-review-answer">${this.escapeHtml(String(answer))}</span>`
      : `<em>keine Antwort</em>`;

    reviewEl.innerHTML = `
      <div class="iter-review-inner">
        <strong>Iteration ${entry.iteration} – Rückblick</strong>
        <div class="iter-review-inputs"><strong>Werte:</strong>${inputsHtml}</div>
        <div class="iter-review-answer-row"><strong>Antwort:</strong> ${answerHtml}</div>
      </div>
    `;
    reviewEl.style.display = 'block';
    reviewEl.dataset.showing = String(idx);

    // Highlight the clicked button
    const bar = reviewEl.closest('.quiz-iteration-bar');
    if (bar) {
      bar.querySelectorAll('.iter-btn--reviewing').forEach(b => b.classList.remove('iter-btn--reviewing'));
      const allBtns = bar.querySelectorAll('.iter-btn--done');
      if (allBtns[idx]) allBtns[idx].classList.add('iter-btn--reviewing');
    }
  },

  escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  formatText(text) {
    if (!text) return '';
    // First escape HTML special characters
    const escaped = this.escapeHtml(text);
    // Then convert newlines (both \n and actual newlines) to <br>
    return escaped.replace(/\n/g, '<br>').replace(/\r\n/g, '<br>');
  },

  /**
   * Generate input values for ALL iterations of a task and persist them to DB.
   * For code_random_complex: runs randomizer_code N times via Pyodide.
   * For code_reading: reads deterministic values from variable_overrides.
   * Legacy users (current_iteration > 1, no iteration_values): past iterations
   * get placeholder entries for code_random_complex (random values are gone),
   * but code_reading can reconstruct all entries deterministically.
   * Never overwrites existing iteration_values in DB (server enforces this).
   */
  async initAllIterations(task) {
    const maxIterations = this.getMaxIterations(task);
    const currentIteration = this.getCurrentIteration(task);
    const iterationValues = [];

    const isRandomComplex = task.task_type === 'code_random_complex';
    const isCodeReading = task.task_type === 'code_reading';

    if ((isRandomComplex || isCodeReading) && task.variable_overrides) {
      const overrides = typeof task.variable_overrides === 'string'
        ? JSON.parse(task.variable_overrides)
        : task.variable_overrides;

      if (Array.isArray(overrides) && overrides.length > 0) {
        const firstSet = overrides[0];
        const hasRandomMarkers = firstSet && firstSet.inputs &&
          Object.values(firstSet.inputs).some(v => v === '<random>');

        if (isRandomComplex && hasRandomMarkers && task.randomizer_code) {
          // Past iterations for legacy users cannot be recovered — add placeholders
          for (let i = 1; i < currentIteration; i++) {
            iterationValues.push({ iteration: i, inputs: null, answer: '(nicht verfügbar)' });
          }

          // Generate values for remaining iterations (currentIteration to max) via Pyodide
          const pyodide = await this.getPyodideOrThrow();
          const requestedRandomKeys = Object.entries(firstSet.inputs || {})
            .filter(([, val]) => val === '<random>')
            .map(([key]) => key);
          const safeCode = task.randomizer_code.replace(/"/g, '\\"').replace(/\n/g, '\\n');

          for (let i = currentIteration; i <= maxIterations; i++) {
            const python = `
import sys
__randomizer_namespace = {}
exec("""${safeCode}""", __randomizer_namespace)
__randomizer_namespace
`;
            const resultObj = await pyodide.runPythonAsync(python);
            const allVariables = resultObj.toJs();

            const inputs = {};
            Object.entries(allVariables).forEach(([rawKey, val]) => {
              const key = String(rawKey ?? '');
              if (!key || !requestedRandomKeys.includes(key)) return;
              try {
                const serialized = JSON.stringify(val);
                if (serialized !== undefined) inputs[key] = val;
              } catch (e) { /* skip non-serializable */ }
            });

            iterationValues.push({ iteration: i, inputs, answer: null });
          }
        } else {
          // code_reading or fixed code_random_complex: deterministic, reconstruct all
          for (let i = 0; i < maxIterations; i++) {
            const idx = i % overrides.length;
            const selectedSet = overrides[idx];
            const inputs = {};
            if (selectedSet?.inputs) {
              Object.entries(selectedSet.inputs).forEach(([key, val]) => {
                if (val !== '<random>') inputs[key] = val;
              });
            }
            iterationValues.push({ iteration: i + 1, inputs, answer: null });
          }
        }
      }
    }

    if (iterationValues.length === 0) return null;

    if (window.testMode === true || window.TEST_MODE_NO_PERSIST === true) {
      if (!window.assignmentState.taskUserAnswers[task.id]) {
        window.assignmentState.taskUserAnswers[task.id] = {};
      }
      window.assignmentState.taskUserAnswers[task.id].iteration_values = iterationValues;
      return iterationValues;
    }

    try {
      const response = await fetch('../api/user_tasks/init_iterations.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: task.id, iteration_values: iterationValues })
      });
      const data = await response.json();
      // Server returns canonical values — may differ if row was already set between our check and POST
      const storedValues = (data.ok && Array.isArray(data.iteration_values))
        ? data.iteration_values
        : iterationValues;

      if (!window.assignmentState.taskUserAnswers[task.id]) {
        window.assignmentState.taskUserAnswers[task.id] = {};
      }
      window.assignmentState.taskUserAnswers[task.id].iteration_values = storedValues;
      return storedValues;
    } catch (err) {
      console.warn('[ITER] init_iterations fetch failed, using locally generated values:', err);
      if (!window.assignmentState.taskUserAnswers[task.id]) {
        window.assignmentState.taskUserAnswers[task.id] = {};
      }
      window.assignmentState.taskUserAnswers[task.id].iteration_values = iterationValues;
      return iterationValues;
    }
  },

  async ensureGeneratedValues(task) {
    const currentIteration = this.getCurrentIteration(task);
    const existingAnswer = window.assignmentState?.taskUserAnswers?.[task.id] || {};
    const existing = existingAnswer.variable_values || {};
    const existingIteration = existingAnswer.iteration || currentIteration;
    if (existing && Object.keys(existing).length > 0 && existingIteration === currentIteration) {
      return existing;
    }

    // --- NEW: check stored iteration_values first ---
    const storedIterValues = existingAnswer.iteration_values;
    if (Array.isArray(storedIterValues)) {
      const entry = storedIterValues.find(e => e.iteration === currentIteration);
      if (entry && entry.inputs && Object.keys(entry.inputs).length > 0) {
        if (!window.assignmentState.taskUserAnswers[task.id]) {
          window.assignmentState.taskUserAnswers[task.id] = {};
        }
        window.assignmentState.taskUserAnswers[task.id].variable_values = entry.inputs;
        window.assignmentState.taskUserAnswers[task.id].iteration = currentIteration;
        return entry.inputs;
      }
    }

    // If iteration_values is null: generate and persist all iterations now
    if (!storedIterValues) {
      try {
        const allValues = await this.initAllIterations(task);
        if (Array.isArray(allValues)) {
          const entry = allValues.find(e => e.iteration === currentIteration);
          if (entry && entry.inputs && Object.keys(entry.inputs).length > 0) {
            if (!window.assignmentState.taskUserAnswers[task.id]) {
              window.assignmentState.taskUserAnswers[task.id] = {};
            }
            window.assignmentState.taskUserAnswers[task.id].variable_values = entry.inputs;
            window.assignmentState.taskUserAnswers[task.id].iteration = currentIteration;
            return entry.inputs;
          }
        }
      } catch (e) {
        console.warn('[ITER] initAllIterations failed, falling back to single-iteration generation:', e);
      }
    }
    // --- END NEW ---

    // Check if variable_overrides exist (can be either CODE_READING feste Werte OR CODE_RANDOM_COMPLEX with <random> markers)
    if (task.variable_overrides) {
      const overrides = typeof task.variable_overrides === 'string'
        ? JSON.parse(task.variable_overrides)
        : task.variable_overrides;

      let values = {};

      // NEW SCHEMA: Array of {inputs: {...}, expected: {...}} 
      if (Array.isArray(overrides) && overrides.length > 0) {
        const idx = Math.max(0, currentIteration - 1) % overrides.length;
        const selectedSet = overrides[idx];

        // Check if inputs have <random> markers (CODE_RANDOM_COMPLEX)
        const hasRandomMarkers = selectedSet && selectedSet.inputs &&
          Object.values(selectedSet.inputs).some(v => v === '<random>');
        
        if (hasRandomMarkers && task.randomizer_code) {
          // CODE_RANDOM_COMPLEX: Execute randomizer_code to generate values DIRECTLY (no values dict)
          const pyodide = await this.getPyodideOrThrow();

          // Create an isolated namespace for the randomizer
          const python = `
import sys
__randomizer_namespace = {}
exec("""${task.randomizer_code.replace(/"/g, '\\"').replace(/\n/g, '\\n')}""", __randomizer_namespace)
__randomizer_namespace
`;
          const resultObj = await pyodide.runPythonAsync(python);
          const allVariables = resultObj.toJs();
          
          const requestedRandomKeys = Object.entries(selectedSet?.inputs || {})
            .filter(([, val]) => val === '<random>')
            .map(([key]) => key);

          // Extract only variables explicitly requested via <random> markers
          Object.entries(allVariables).forEach(([rawKey, val]) => {
            const key = String(rawKey ?? '');
            if (!key || !requestedRandomKeys.includes(key)) {
              return;
            }

            // Only add JSON-serializable values.
            try {
              const serialized = JSON.stringify(val);
              if (serialized === undefined) return;
              values[key] = val;
            } catch (e) {
              // Skip non-serializable types
            }
          });

          if (Object.keys(values).length === 0) {
            throw new Error('Randomizer muss mindestens eine Variable erstellen');
          }
        } else {
          // CODE_READING or fixed CODE_RANDOM_COMPLEX: Use feste values from variable_overrides
          if (selectedSet && typeof selectedSet === 'object') {
            // NEW SCHEMA: extract inputs from {inputs: {...}, expected: {...}}
            if (selectedSet.inputs && typeof selectedSet.inputs === 'object') {
              // Filter out <random> markers (shouldn't happen for CODE_READING)
              Object.entries(selectedSet.inputs).forEach(([key, val]) => {
                if (val !== '<random>') {
                  values[key] = val;
                }
              });
            } else {
              // LEGACY: direct dict
              values = selectedSet;
            }
          }
        }
      } else if (overrides && typeof overrides === 'object' && !Array.isArray(overrides)) {
        // LEGACY SCHEMA: Direct object with value arrays
        for (const varName in overrides) {
          const possibleValues = overrides[varName];
          if (Array.isArray(possibleValues) && possibleValues.length > 0) {
            values[varName] = possibleValues[Math.floor(Math.random() * possibleValues.length)];
          } else if (possibleValues !== undefined) {
            values[varName] = possibleValues;
          }
        }
      }

      if (Object.keys(values).length > 0) {
        if (!window.assignmentState.taskUserAnswers[task.id]) {
          window.assignmentState.taskUserAnswers[task.id] = {};
        }
        window.assignmentState.taskUserAnswers[task.id].variable_values = values;
        window.assignmentState.taskUserAnswers[task.id].iteration = currentIteration;

        const isTestMode = window.testMode === true || window.TEST_MODE_NO_PERSIST === true;
        if (isTestMode) {
          return values;
        }

        const payload = {
          task_id: task.id,
          variable_values: values,
          current_iteration: currentIteration,
          started_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };
        console.log('[CODE_RANDOM] Saving generated values - Payload:', payload);
        const response = await fetch('../api/user_tasks/update.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await response.json();
        console.log('[CODE_RANDOM] Response:', data);

        return values;
      }
    }

    // Fallback: Use randomizer_code (NEW SCHEMA for code_random_complex)
    const pyodide = await this.getPyodideOrThrow();

    const code = (task.randomizer_code || '').trim();
    if (!code) {
      throw new Error('Kein randomizer_code hinterlegt');
    }

    // NEW SCHEMA: randomizer_code creates variables directly (no 'values' dict)
    const python = `
__randomizer_namespace = {}
exec("""${code.replace(/"/g, '\\"').replace(/\n/g, '\\n')}""", __randomizer_namespace)
__randomizer_namespace
`;
    const resultObj = await pyodide.runPythonAsync(python);
    const allVariables = resultObj.toJs();

    // Extract all variables from namespace (except builtins)
    let values = {};
    Object.entries(allVariables).forEach(([rawKey, val]) => {
      const key = String(rawKey ?? '');
      if (!key || key.startsWith('__') || key === 'random') {
        return;
      }
      values[key] = val;
    });

    if (!values || typeof values !== 'object' || Array.isArray(values)) {
      throw new Error('Generator muss ein dict liefern');
    }
    if (Object.keys(values).length === 0) {
      throw new Error('Generator liefert keine Variablen');
    }

    if (!window.assignmentState.taskUserAnswers[task.id]) {
      window.assignmentState.taskUserAnswers[task.id] = {};
    }
    window.assignmentState.taskUserAnswers[task.id].variable_values = values;
    window.assignmentState.taskUserAnswers[task.id].iteration = currentIteration;

    const isTestMode = window.testMode === true || window.TEST_MODE_NO_PERSIST === true;
    if (isTestMode) {
      return values;
    }

    const payload = {
      task_id: task.id,
      variable_values: values,
      current_iteration: currentIteration,
      started_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    };
    console.log('[CODE_RANDOM] Saving generated values (legacy fallback) - Payload:', payload);
    const response = await fetch('../api/user_tasks/update.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();
    console.log('[CODE_RANDOM] Response:', data);

    return values;
  },

  async evaluateHiddenSolution(pyodide, code, varName, varValues) {
    const varsJson = JSON.stringify(varValues || {});
    const varsB64 = btoa(unescape(encodeURIComponent(varsJson)));
    const safeVarName = String(varName || 'result').trim() || 'result';

    const toPythonLiteral = (value) => {
      if (value === null || value === undefined) return 'None';
      if (typeof value === 'string') return JSON.stringify(value);
      if (typeof value === 'number') return String(value);
      if (typeof value === 'boolean') return value ? 'True' : 'False';
      try {
        return JSON.stringify(value);
      } catch (err) {
        return JSON.stringify(String(value));
      }
    };

    // Replace template strings {variable} with actual values
    let processedCode = code;
    for (const [key, value] of Object.entries(varValues || {})) {
      const placeholder = `{${key}}`;
      const escapedValue = toPythonLiteral(value);
      const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
      processedCode = processedCode.replace(regex, escapedValue);
    }

    console.log('[CODE_RANDOM] Processed code:', processedCode);

    const python = `
import base64, json
_vars = json.loads(base64.b64decode('${varsB64}').decode('utf-8'))
values = _vars  # Make values dict available to solution_code
for k, v in _vars.items():
    globals()[k] = v

${processedCode}

_value = globals().get('${safeVarName}', None)
json.dumps(_value)
`;

    const resultJson = await pyodide.runPythonAsync(python);
    try {
      return JSON.parse(resultJson);
    } catch (err) {
      return resultJson;
    }
  },

  async evaluateCodeReading(pyodide, code, varName, varValues) {
    const varsJson = JSON.stringify(varValues || {});
    const varsB64 = btoa(unescape(encodeURIComponent(varsJson)));
    const safeVarName = String(varName || '').trim();
    if (!safeVarName) {
      throw new Error('Kein Variablenname gesetzt');
    }

    const python = `
import base64, json
_vars = json.loads(base64.b64decode('${varsB64}').decode('utf-8'))
values = _vars  # Make values dict available
for k, v in _vars.items():
    globals()[k] = v

${code}

_value = globals().get('${safeVarName}', None)
json.dumps(_value)
`;

    const resultJson = await pyodide.runPythonAsync(python);
    try {
      return JSON.parse(resultJson);
    } catch (err) {
      return resultJson;
    }
  },

  attachHintRevealListeners(container) {
    const hintBtns = container.querySelectorAll('.hint-reveal-btn-inline');
    hintBtns.forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const taskId = parseInt(btn.dataset.taskId);
        const hintId = parseInt(btn.dataset.hintId);
        
        if (!window.assignmentState?.hintsRevealed) {
          window.assignmentState.hintsRevealed = {};
        }
        if (!window.assignmentState.hintsRevealed[taskId]) {
          window.assignmentState.hintsRevealed[taskId] = [];
        }
        
        if (!window.assignmentState.hintsRevealed[taskId].includes(hintId)) {
          window.assignmentState.hintsRevealed[taskId].push(hintId);
        }
        
        try {
          const payload = {
            task_id: taskId,
            hints_revealed: window.assignmentState.hintsRevealed[taskId]
          };
          console.log('[HINT] Revealing hint - Payload:', payload);
          const response = await fetch('../api/user_tasks/update.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const data = await response.json();
          console.log('[HINT] Response:', data);
          
          // Re-render the task to show newly revealed hint
          const task = window.assignmentState?.currentTask;
          if (task && task.id === taskId) {
            this.render(task, container);
          }
        } catch (err) {
          console.error('Failed to save hints progress:', err);
        }
      });
    });  }
};