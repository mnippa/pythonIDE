/**
 * Quiz Task Renderer for Single-Choice, Multiple-Choice, Free-Text
 */

window.QuizRenderer = {
  getTaskDetailsAndHints(task) {
    let html = '';
    
    // Stoff (Learning Content)
    if (task.stoff) {
      html += `<div class="stoff-section">
        <h4>📚 Lerninhalt (Stoff)</h4>
        <p>${this.escapeHtml(task.stoff)}</p>
      </div>`;
    }
    
    // Description
    if (task.description) {
      html += `<div class="task-description-section">
        <h4>Aufgabenstellung</h4>
        <p>${this.escapeHtml(task.description)}</p>
      </div>`;
    }
    
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
          ${task.question_text ? `<div class="question-text">${this.formatText(task.question_text)}</div>` : ''}
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
    
    // Calculate keywords info
    const keywords = task.correct_answer ? task.correct_answer.split(',').map(k => k.trim()).filter(k => k !== '') : [];
    const totalKeywords = keywords.length;
    const minRequired = (task.min_keywords_required !== null && task.min_keywords_required !== undefined) 
      ? task.min_keywords_required 
      : totalKeywords;
    
    let keywordsHint = '';
    if (totalKeywords > 0) {
      if (minRequired === totalKeywords) {
        keywordsHint = `<div class="quiz-hint">Alle ${totalKeywords} Schlüsselwörter müssen in der Antwort vorkommen.</div>`;
      } else {
        keywordsHint = `<div class="quiz-hint">Mindestens ${minRequired} von ${totalKeywords} Schlüsselwörtern müssen in der Antwort vorkommen.</div>`;
      }
    }
    
    // Show solution if max attempts reached AND show_solution is enabled
    let solutionHtml = '';
    if (attemptsInfo.blocked && attemptsInfo.isFailed && task.show_solution !== 0 && task.correct_answer) {
      solutionHtml = `
        <div class="quiz-solution">
          <h4>Erwartete Schlüsselwörter:</h4>
          <p>${this.escapeHtml(task.correct_answer)}</p>
        </div>
      `;
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
          ${task.question_text ? `<div class="question-text">${this.formatText(task.question_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
          ${keywordsHint}
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
    
    // Get previous answer if task was already attempted
    const userAnswer = window.assignmentState?.taskUserAnswers?.[task.id];
    const userTextAnswer = userAnswer?.text_answer || '';
    const answerClass = isCompleted && !isPassed ? 'user-answer-incorrect' : (isCompleted && isPassed ? 'user-answer-correct' : '');
    
    // Generate random variable values if not already stored
    let varValues = {};
    if (task.variable_overrides) {
      try {
        const overrides = typeof task.variable_overrides === 'string' 
          ? JSON.parse(task.variable_overrides) 
          : task.variable_overrides;
        
        // Handle array of objects (e.g., [{"start":1,"end":5}, {...}])
        if (Array.isArray(overrides) && overrides.length > 0) {
          const selectedSet = overrides[Math.floor(Math.random() * overrides.length)];
          varValues = selectedSet;
        } 
        // Handle object with array values (e.g., {"x": [1,2,3], "y": [4,5,6]})
        else if (typeof overrides === 'object' && !Array.isArray(overrides)) {
          for (const varName in overrides) {
            const possibleValues = overrides[varName];
            if (Array.isArray(possibleValues) && possibleValues.length > 0) {
              varValues[varName] = possibleValues[Math.floor(Math.random() * possibleValues.length)];
            }
          }
        }
      } catch (err) {
        console.error('Failed to parse variable_overrides:', err);
      }
    }
    
    // Store var values for later verification
    window.currentCodeReadingVars = varValues;
    
    // Build code display with template string replacement AND variable highlighting
    let codeDisplay = task.code_template || '';
    
    // FIRST: Replace template strings {varName} with actual values
    for (const varName in varValues) {
      const placeholder = `{${varName}}`;
      const value = varValues[varName];
      const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
      codeDisplay = codeDisplay.replace(regex, String(value));
    }
    
    // SECOND: Highlight variables in the code
    for (const varName in varValues) {
      codeDisplay = codeDisplay.replace(
        new RegExp(`\\b${varName}\\b`, 'g'),
        `<span class="var-highlight" title="${varName} = ${varValues[varName]}">${varName}</span>`
      );
    }
    
    container.innerHTML = `
      <div class="quiz-container">
        <div class="code-reading-vars">
          <strong>Variablenwerte:</strong>
          <ul>
            ${Object.entries(varValues).map(([name, value]) => 
              `<li><code>${name} = ${value}</code></li>`
            ).join('')}
          </ul>
        </div>
        
        <div class="code-reading-code">
          <pre><code>${codeDisplay}</code></pre>
        </div>
        
        <div class="quiz-question">
          <label for="code-reading-answer-${task.id}">Was ist der Wert von <code>${this.escapeHtml(task.correct_answer || '?')}</code> am Ende?</label>
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
    const values = userAnswer?.variable_values || {};
    const hasValues = values && Object.keys(values).length > 0;

    if (!hasValues) {
      container.innerHTML = `
        <div class="quiz-container">
          <div class="quiz-question">
            ${task.question_text ? `<div class="question-text">${this.formatText(task.question_text)}</div>` : ''}
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
    
    // Show solution code only if show_generator_code is enabled
    const showGenerator =
      task.show_generator_code === 1 ||
      task.show_generator_code === true ||
      task.show_generator_code === '1' ||
      task.show_generator_code === 'true';
    
    // Build code display with solution or template code (formatted like code_reading)
    let codeDisplay = '';
    const rawCode = task.solution_code || task.code_template || '';
    if (showGenerator && rawCode) {
      codeDisplay = rawCode;
      
      // Replace placeholders with actual values
      for (const [varName, value] of Object.entries(values)) {
        const placeholder = `{${varName}}`;
        const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
        codeDisplay = codeDisplay.replace(regex, String(value));
      }
      
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
          ${task.question_text ? `<div class="question-text">${this.formatText(task.question_text)}</div>` : ''}
          ${task.image_url ? `<img src="${task.image_url}" class="question-image" alt="Question image" />` : ''}
        </div>
        
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
      if (!task || !task.code_template || !task.correct_answer) {
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
      try {
        computedValue = await this.evaluateCodeReading(
          pyodide,
          codeToEvaluate,
          task.correct_answer,
          varValues
        );
      } catch (err) {
        feedbackEl.innerHTML = `<div class="error">Code-Auswertung fehlgeschlagen: ${this.escapeHtml(String(err))}</div>`;
        return;
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
      const payload = {
        task_id: taskId,
        ...answer
      };
      console.log('[QUIZ] Submitting quiz answer - Payload:', payload);
      const response = await fetch('../api/user_tasks/submit_quiz.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      console.log('[QUIZ] Response:', data);
      
      if (data.ok) {
        const isPassed = data.is_correct;
        let attemptsInfo = '';
        if (typeof data.attempts === 'number' && typeof data.max_attempts === 'number') {
          attemptsInfo = `<br/>Versuch: ${data.attempts}/${data.max_attempts}`;
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
        if (data.status === 'passed' || (data.status === 'failed' && data.attempts >= data.max_attempts)) {
          const task = window.assignmentState?.currentTask;
          const quizContainer = document.getElementById('quiz-container');
          if (task && quizContainer) {
            console.log('[QUIZ] Re-rendering quiz after submission');
            setTimeout(() => this.render(task, quizContainer), 100);
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
    const existing = window.assignmentState?.taskUserAnswers?.[task.id]?.variable_values || {};
    if (existing && Object.keys(existing).length > 0) {
      return existing;
    }

    // Check if variable_overrides exist (alternative to generator code)
    if (task.variable_overrides) {
      const overrides = typeof task.variable_overrides === 'string' 
        ? JSON.parse(task.variable_overrides) 
        : task.variable_overrides;
      
      const values = {};
      for (const varName in overrides) {
        const possibleValues = overrides[varName];
        if (Array.isArray(possibleValues) && possibleValues.length > 0) {
          values[varName] = possibleValues[Math.floor(Math.random() * possibleValues.length)];
        }
      }
      
      if (Object.keys(values).length > 0) {
        if (!window.assignmentState.taskUserAnswers[task.id]) {
          window.assignmentState.taskUserAnswers[task.id] = {};
        }
        window.assignmentState.taskUserAnswers[task.id].variable_values = values;

        const payload = {
          task_id: task.id,
          variable_values: values,
          started_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };
        console.log('[CODE_RANDOM] Saving generated values (from overrides) - Payload:', payload);
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

    const pyodide = window.pyodide;
    if (!pyodide) {
      throw new Error('Pyodide ist noch nicht bereit');
    }

    const code = (task.code_template || '').trim();
    if (!code) {
      throw new Error('Kein Generator-Code oder variable_overrides hinterlegt');
    }

    const python = `
import json
values = {}
${code}
json.dumps(values)
`;

    const resultJson = await pyodide.runPythonAsync(python);
    let values = {};
    try {
      values = JSON.parse(resultJson);
    } catch (err) {
      throw new Error('Generator muss ein JSON-dict liefern');
    }

    if (!values || typeof values !== 'object' || Array.isArray(values)) {
      throw new Error('Generator muss ein dict liefern');
    }

    if (!window.assignmentState.taskUserAnswers[task.id]) {
      window.assignmentState.taskUserAnswers[task.id] = {};
    }
    window.assignmentState.taskUserAnswers[task.id].variable_values = values;

    const payload = {
      task_id: task.id,
      variable_values: values,
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
  },

  async evaluateHiddenSolution(pyodide, code, varName, varValues) {
    const varsJson = JSON.stringify(varValues || {});
    const varsB64 = btoa(unescape(encodeURIComponent(varsJson)));
    const safeVarName = String(varName || 'result').trim() || 'result';

    // Replace template strings {variable} with actual values
    let processedCode = code;
    for (const [key, value] of Object.entries(varValues || {})) {
      const placeholder = `{${key}}`;
      // For strings from variable_overrides, use the value directly (template already has quotes)
      // Escape any quotes within the value itself
      const escapedValue = typeof value === 'string' ? value.replace(/"/g, '\\"') : String(value);
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