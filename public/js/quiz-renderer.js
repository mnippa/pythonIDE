/**
 * Quiz Task Renderer for Single-Choice, Multiple-Choice, Free-Text
 */

window.QuizRenderer = {
  render(task, container) {
    const taskType = task.task_type;
    
    if (taskType === 'single_choice' || taskType === 'multiple_choice') {
      this.renderChoice(task, container, taskType === 'multiple_choice');
    } else if (taskType === 'free_text') {
      this.renderFreeText(task, container);
    } else if (taskType === 'code_reading') {
      this.renderCodeReading(task, container);
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
          ${task.question_text ? `<div class="question-text">${this.escapeHtml(task.question_text)}</div>` : ''}
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
          ${task.question_text ? `<div class="question-text">${this.escapeHtml(task.question_text)}</div>` : ''}
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
    // Generate random variable values if not already stored
    let varValues = {};
    if (task.variable_overrides) {
      try {
        const overrides = typeof task.variable_overrides === 'string' 
          ? JSON.parse(task.variable_overrides) 
          : task.variable_overrides;
        
        for (const varName in overrides) {
          const possibleValues = overrides[varName];
          if (Array.isArray(possibleValues) && possibleValues.length > 0) {
            varValues[varName] = possibleValues[Math.floor(Math.random() * possibleValues.length)];
          }
        }
      } catch (err) {
        console.error('Failed to parse variable_overrides:', err);
      }
    }
    
    // Store var values for later verification
    window.currentCodeReadingVars = varValues;
    
    // Build code display with highlighted variables
    let codeDisplay = task.code_template || '';
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
            placeholder="Ergebnis eingeben..."
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

      let computedValue = null;
      try {
        computedValue = await this.evaluateCodeReading(
          pyodide,
          task.code_template,
          task.correct_answer,
          window.currentCodeReadingVars || {}
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
    }
    
    // Submit to API
    try {
      const response = await fetch('../api/user_tasks/submit_quiz.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          task_id: taskId,
          ...answer
        })
      });
      
      const data = await response.json();
      
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
        
        // Re-render quiz to show final state (passed, or solution if max attempts reached)
        if (data.status === 'passed' || (data.status === 'failed' && data.attempts >= data.max_attempts)) {
          const task = window.assignmentState?.currentTask;
          const quizContainer = document.getElementById('quiz-container');
          if (task && quizContainer) {
            setTimeout(() => this.render(task, quizContainer), 100);
          }
        }
        
        // Reload assignment to update status in list
        if (window.loadAssignments) {
          setTimeout(() => window.loadAssignments(), 1000);
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
    const isLimitedType = ['single_choice', 'multiple_choice', 'free_text', 'code_reading'].includes(task.task_type);
    
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
  }
};
