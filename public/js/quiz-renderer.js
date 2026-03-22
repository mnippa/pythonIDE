/**
 * Quiz Task Renderer for Single-Choice, Multiple-Choice, Free-Text
 */

window.QuizRenderer = {
  _lightboxInitialized: false,

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
      <div class="code-reading-code">
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
        <div class="quiz-values">
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

      const pyodide = window.pyodide;
      if (!pyodide) {
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

      const pyodide = window.pyodide;
      if (!pyodide) {
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
        const isPassed = data.is_correct;
        let attemptsInfo = '';
        if (typeof data.attempts === 'number' && typeof data.max_attempts === 'number') {
          const isIterative = ['code_reading', 'code_random_complex'].includes(window.assignmentState?.currentTask?.task_type);
          const attemptsLabel = isIterative ? 'Fehlversuch' : 'Versuch';
          attemptsInfo = `<br/>${attemptsLabel}: ${data.attempts}/${data.max_attempts}`;
        }

        feedbackEl.innerHTML = `
          <div class="${isPassed ? 'success' : (data.status === 'in_progress' ? 'warning' : 'error')}">
            ${isPassed ? '✓ Richtig!' : (data.status === 'in_progress' ? 'Noch nicht richtig' : '✗ Leider falsch')}
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
    const isLimitedType = ['single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex'].includes(task.task_type);
    
    // Block if already passed
    if (status === 'passed') {
      return {
        blocked: true,
        isFailed: false,
        isPassed: true
      };
    }
    
    if (!isLimitedType) {
      return { blocked: false, isFailed: false, isPassed: false };
    }

    const blocked = attempts >= maxAttempts;
    const isFailed = status === 'failed';
    const isPassed = status === 'passed';
    return { blocked, isFailed, isPassed };
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
    return { current, max };
  },

  getIterationHtml(iterationInfo) {
    if (!iterationInfo) return '';
    const percentage = Math.round((iterationInfo.current / iterationInfo.max) * 100);
    return `
      <div class="quiz-iteration-bar">
        <div class="iteration-header">
          📊 Iteration ${iterationInfo.current} / ${iterationInfo.max}
        </div>
        <div class="iteration-progress-bar">
          <div class="iteration-progress-fill" style="width: ${percentage}%"></div>
        </div>
      </div>
    `;
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

  async ensureGeneratedValues(task) {
    const currentIteration = this.getCurrentIteration(task);
    const existingAnswer = window.assignmentState?.taskUserAnswers?.[task.id] || {};
    const existing = existingAnswer.variable_values || {};
    const existingIteration = existingAnswer.iteration || currentIteration;
    if (existing && Object.keys(existing).length > 0 && existingIteration === currentIteration) {
      return existing;
    }

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
          const pyodide = window.pyodide;
          if (!pyodide) {
            throw new Error('Pyodide ist noch nicht bereit');
          }

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
    const pyodide = window.pyodide;
    if (!pyodide) {
      throw new Error('Pyodide ist noch nicht bereit');
    }

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