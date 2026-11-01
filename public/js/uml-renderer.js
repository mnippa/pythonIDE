(function () {
  const UML_VERSION = 2;
  const NODE_WIDTH = 200;
  const NODE_HEIGHT = 120;
  const SIMPLE_TYPE_OPTIONS = ['String', 'Integer', 'Boolean', 'Float', 'Date', 'Currency', 'Time', 'Datetime'];
  const CARDINALITY_OPTIONS = [
    { value: '1', label: '1' },
    { value: '0..1', label: '0..1' },
    { value: '1..*', label: '1..* (1:n)' },
    { value: '0..*', label: '0..* (0:n)' }
  ];
  let generatedId = 0;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function createEntityId(kind) {
    generatedId += 1;
    return `uml-${kind}-${Date.now()}-${generatedId}`;
  }

  function createAttributeId() {
    return createEntityId('attribute');
  }

  function getCardinalityOptionsMarkup(selectedValue) {
    const selected = String(selectedValue || '');
    const options = CARDINALITY_OPTIONS.map(({ value, label }) => (
      `<option value="${value}" ${selected === value ? 'selected' : ''}>${label}</option>`
    ));
    if (selected && !CARDINALITY_OPTIONS.some(({ value }) => value === selected)) {
      options.unshift(`<option value="${escapeHtml(selected)}" selected>${escapeHtml(selected)} (bisheriger Wert)</option>`);
    }
    return `<option value="" ${selected ? '' : 'selected'}>Bitte auswählen</option>${options.join('')}`;
  }

  function getUmlTypeConfig(task) {
    let template = task?.uml_template;
    if (typeof template === 'string') {
      try {
        template = JSON.parse(template);
      } catch (_err) {
        template = null;
      }
    }
    const config = template?.type_config || template?.typeConfig || template;
    const configuredTypes = Array.isArray(config?.simple_types)
      ? config.simple_types.filter((type) => SIMPLE_TYPE_OPTIONS.includes(type))
      : SIMPLE_TYPE_OPTIONS;
    const parsedLength = Number.parseInt(config?.string_length, 10);
    return {
      simpleTypes: configuredTypes,
      stringLength: Number.isInteger(parsedLength) && parsedLength > 0 ? parsedLength : 30
    };
  }

  function getTypeSuggestions(nodes = [], task = null) {
    const typeConfig = getUmlTypeConfig(task);
    const baseSuggestions = typeConfig.simpleTypes.map((type) => (
      type === 'String' ? `String(${typeConfig.stringLength})` : type
    ));
    const nodeSuggestions = Array.isArray(nodes) ? nodes
      .filter((node) => node?.type === 'enum' || node?.type === 'structure')
      .map((node) => node?.name)
      .filter(Boolean) : [];
    return Array.from(new Set(baseSuggestions.concat(nodeSuggestions).filter(Boolean)));
  }

  function getUmlStarterModel(task) {
    let template = task?.uml_template;
    if (typeof template === 'string') {
      try {
        template = JSON.parse(template);
      } catch (_err) {
        return null;
      }
    }
    const starterModel = template?.starter_model;
    return starterModel && typeof starterModel === 'object' && Array.isArray(starterModel.nodes)
      ? starterModel
      : null;
  }

  function getTypeComboMarkup(value, suggestions, fieldAttributes) {
    return `
      <div class="uml-type-combo">
        <div class="uml-type-combo-control">
          <input type="text" value="${escapeHtml(value || '')}" ${fieldAttributes} autocomplete="off" spellcheck="false">
          <button type="button" class="uml-type-combo-toggle" data-uml-type-combo-toggle aria-label="Datentypen anzeigen" title="Datentypen anzeigen">▾</button>
        </div>
        <div class="uml-type-combo-options" data-uml-type-combo-options role="listbox" hidden>
          ${suggestions.map((suggestion) => `<button type="button" role="option" data-uml-type-combo-option="${escapeHtml(suggestion)}">${escapeHtml(suggestion)}</button>`).join('')}
        </div>
      </div>
    `;
  }

  function normalizeAttribute(rawAttribute, nodeIndex, index) {
    const id = rawAttribute && typeof rawAttribute === 'object'
      ? String(rawAttribute.id || `uml-attribute-${nodeIndex + 1}-${index + 1}`)
      : `uml-attribute-${nodeIndex + 1}-${index + 1}`;
    if (typeof rawAttribute === 'string') {
      const value = String(rawAttribute || '').trim();
      const derived = value.startsWith('/');
      return { id, name: derived ? value.slice(1).trim() : value, type: '', derived };
    }
    if (rawAttribute && typeof rawAttribute === 'object') {
      const rawName = String(rawAttribute.name || rawAttribute.value || '').trim();
      const derived = Boolean(rawAttribute.derived) || rawName.startsWith('/');
      const name = derived && rawName.startsWith('/') ? rawName.slice(1).trim() : rawName;
      const type = String(rawAttribute.type || rawAttribute.datatype || '').trim();
      return { id, name, type, derived };
    }
    return { id, name: '', type: '', derived: false };
  }

  function normalizeState(raw) {
    const fallback = { version: UML_VERSION, nodes: [], relationships: [] };

    if (!raw) {
      return fallback;
    }

    if (typeof raw === 'string') {
      try {
        const parsed = JSON.parse(raw);
        return normalizeState(parsed);
      } catch (_err) {
        return fallback;
      }
    }

    if (!raw || typeof raw !== 'object') {
      return fallback;
    }

    const nodes = Array.isArray(raw.nodes) ? raw.nodes : [];
    const relationships = Array.isArray(raw.relationships) ? raw.relationships : [];

    return {
      version: UML_VERSION,
      nodes: nodes.map((node, index) => ({
        id: String(node?.id || `uml-node-${index + 1}`),
        type: node?.type === 'enum' ? 'enum' : node?.type === 'structure' ? 'structure' : 'class',
        name: String(node?.name || (node?.type === 'enum' ? 'Enum' : 'Klasse')),
        attributes: Array.isArray(node?.attributes) ? node.attributes.map((attr, attributeIndex) => normalizeAttribute(attr, index, attributeIndex)).filter((attr) => attr.name || attr.type) : [],
        x: Number(node?.x) || 80 + (index * 40),
        y: Number(node?.y) || 80 + (index * 40)
      })),
      relationships: relationships.map((relation, index) => ({
        id: String(relation?.id || `uml-relationship-${index + 1}`),
        sourceId: String(relation?.sourceId || ''),
        targetId: String(relation?.targetId || ''),
        label: String(relation?.label || ''),
        sourceCardinality: String(relation?.sourceCardinality || ''),
        targetCardinality: String(relation?.targetCardinality || ''),
        points: Array.isArray(relation?.points) ? relation.points.map((point) => ({ x: Number(point?.x), y: Number(point?.y) })) : []
      })).filter((relation) => relation.sourceId && relation.targetId)
    };
  }

  function getNewNodePosition(nodes = [], canvasWidth = 1000) {
    const columnWidth = NODE_WIDTH + 40;
    const rowHeight = NODE_HEIGHT + 30;
    const columnCount = Math.max(1, Math.floor((Math.max(canvasWidth, NODE_WIDTH) - 48 + 40) / columnWidth));
    const candidateCount = nodes.length + 1;

    for (let index = 0; index <= candidateCount; index += 1) {
      const position = {
        x: 24 + (index % columnCount) * columnWidth,
        y: 24 + Math.floor(index / columnCount) * rowHeight
      };
      const overlapsNode = nodes.some((node) => (
        position.x < Number(node.x || 0) + NODE_WIDTH
        && position.x + NODE_WIDTH > Number(node.x || 0)
        && position.y < Number(node.y || 0) + NODE_HEIGHT
        && position.y + NODE_HEIGHT > Number(node.y || 0)
      ));
      if (!overlapsNode) return position;
    }

    return { x: 24, y: 24 + candidateCount * rowHeight };
  }

  function createNode(type, position = { x: 24, y: 24 }) {
    return {
      id: createEntityId('node'),
      type: type === 'enum' ? 'enum' : type === 'structure' ? 'structure' : 'class',
      name: type === 'enum' ? 'Enum' : type === 'structure' ? 'Struktur' : 'Klasse',
      attributes: type === 'enum'
        ? [{ id: createAttributeId(), name: 'WERT_A', type: '', derived: false }, { id: createAttributeId(), name: 'WERT_B', type: '', derived: false }]
        : [{ id: createAttributeId(), name: 'attribute', type: 'String', derived: false }],
      x: position.x,
      y: position.y
    };
  }

  window.UmlRenderer = {
    _stateCache: {},
    _renderTasks: {},
    _saveStatusCache: {},
    _associationMode: null,
    _associationPreview: null,
    _associationDrag: null,
    _associationCursor: null,
    _dragState: null,
    _relationHandleDrag: null,
    _selectedNodeId: null,
    _selectedRelationId: null,
    _selectedAttributePath: null,
    _editState: null,

    getNewNodePosition,

    ensureStyles() {
      if (document.getElementById('uml-renderer-style')) return;
      const style = document.createElement('style');
      style.id = 'uml-renderer-style';
      style.textContent = `
        .uml-editor-shell {
          display: flex;
          flex-direction: column;
          gap: 10px;
          padding: 10px;
          border: 1px solid var(--border);
          border-radius: 12px;
          background: var(--bg-secondary);
        }
        .uml-task-prompt {
          padding: 12px;
          border: 1px solid var(--border);
          border-radius: 8px;
          background: var(--bg);
          line-height: 1.5;
        }
        .uml-task-image {
          display: block;
          max-width: 100%;
          max-height: 320px;
          margin-top: 10px;
          border-radius: 8px;
        }
        .uml-task-save-status {
          min-height: 1.2em;
          color: var(--text-secondary);
          font-size: 12px;
        }
        .uml-toolbar {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          align-items: center;
        }
        .uml-toolbar button {
          padding: 6px 10px;
          border: 1px solid var(--border);
          border-radius: 8px;
          background: var(--bg);
          cursor: pointer;
        }
        .uml-toolbar button.active {
          background: var(--accent);
          color: white;
          border-color: var(--accent);
        }
        .uml-shortcut-hint {
          font-size: 12px;
          color: var(--text-secondary);
          margin-left: auto;
        }
        .uml-workspace {
          display: flex;
          flex-direction: row;
          align-items: flex-start;
          gap: 12px;
        }
        .uml-canvas-area {
          flex: 1 1 auto;
          min-height: 420px;
          border: 1px solid var(--border);
          border-radius: 12px;
          background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
          overflow: hidden;
          position: relative;
        }
        .uml-canvas-area.association-mode {
          cursor: crosshair;
        }
        .uml-association-hint-box {
          position: absolute;
          z-index: 20;
          pointer-events: none;
          padding: 6px 10px;
          border-radius: 999px;
          border: 1px solid var(--accent);
          background: rgba(0, 0, 0, 0.82);
          color: white;
          font-size: 12px;
          white-space: nowrap;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
          transform: translate(12px, 12px);
        }
        .uml-node-properties {
          width: 280px;
          min-width: 280px;
          flex-shrink: 0;
          display: flex;
          flex-direction: column;
          gap: 8px;
          font-size: 12px;
        }
        .uml-section {
          background: white;
          border: 1px solid var(--border);
          border-radius: 10px;
          padding: 8px;
          display: flex;
          flex-direction: column;
          gap: 6px;
        }
        .uml-canvas-svg {
          position: absolute;
          inset: 0;
          width: 100%;
          height: 100%;
          pointer-events: none;
        }
        .uml-relationship-layer {
          position: absolute;
          inset: 0;
          pointer-events: none;
          z-index: 4;
        }
        .uml-relationship-line {
          position: absolute;
          height: 12px;
          padding: 0;
          margin: 0;
          background: transparent;
          border-radius: 999px;
          transform-origin: 0 50%;
          pointer-events: auto;
          cursor: pointer;
          z-index: 4;
          box-sizing: border-box;
        }
        .uml-relationship-line::before {
          content: '';
          position: absolute;
          top: 50%;
          left: 0;
          right: 0;
          height: 1px;
          background: #64748b;
          transform: translateY(-50%);
        }
        .uml-relationship-line.selected {
          z-index: 6;
        }
        .uml-relationship-line.selected::before {
          height: 3px;
          background: #ef4444;
        }
        .uml-cardinality-label {
          position: absolute;
          z-index: 5;
          padding: 1px 4px;
          border-radius: 4px;
          background: rgba(255, 255, 255, 0.94);
          color: #334155;
          font: 12px ui-monospace, monospace;
          line-height: 1.3;
          pointer-events: none;
          transform: translate(-50%, -50%);
        }
        .uml-relationship-name {
          position: absolute;
          z-index: 5;
          max-width: 220px;
          padding: 2px 6px;
          border: 1px solid #cbd5e1;
          border-radius: 6px;
          background: rgba(255, 255, 255, 0.96);
          color: #334155;
          font-size: 12px;
          line-height: 1.3;
          text-align: center;
          overflow-wrap: anywhere;
          pointer-events: none;
          transform: translate(-50%, -50%);
        }
        .uml-relationship-name.selected {
          border-color: #ef4444;
          color: #b91c1c;
        }
        .uml-relationship {
          pointer-events: stroke;
          cursor: pointer;
        }
        .uml-relationship.selected {
          stroke: #ef4444;
          stroke-width: 3;
        }
        .uml-relationship-handle {
          cursor: grab;
          pointer-events: all;
        }
        .uml-node-layer {
          position: absolute;
          inset: 0;
          pointer-events: none;
          z-index: 3;
        }
        .uml-node {
          position: absolute;
          width: 200px;
          min-height: 90px;
          border: 2px solid #2563eb;
          border-radius: 12px;
          background: white;
          box-shadow: 0 4px 12px rgba(0,0,0,0.08);
          user-select: none;
          cursor: grab;
          display: flex;
          flex-direction: column;
          overflow: hidden;
          transition: box-shadow 120ms ease, border-color 120ms ease, transform 120ms ease;
          pointer-events: auto;
        }
        .uml-node:active {
          cursor: grabbing;
        }
        .uml-node:hover {
          cursor: grab;
        }
        .uml-node:hover {
          box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.28), 0 10px 22px rgba(37, 99, 235, 0.18);
          transform: translateY(-1px) scale(1.01);
        }
        .uml-node:hover .uml-node-title {
          background: #bfdbfe;
        }
        .uml-node.selected {
          border-color: #ef4444;
          box-shadow: 0 0 0 2px rgba(239,68,68,0.18);
        }
        .uml-node.association-target {
          border-color: #10b981;
          box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.24);
        }
        .uml-node.association-target .uml-node-title {
          background: #d1fae5;
        }
        .uml-node.enum {
          border-color: #7c3aed;
        }
        .uml-node-title {
          background: #dbeafe;
          padding: 8px 10px;
          font-weight: 700;
          font-size: 14px;
          cursor: text;
        }
        .uml-canvas-area.association-mode .uml-node-title,
        .uml-canvas-area.association-mode .uml-attr-text {
          cursor: default;
          pointer-events: none;
        }
        .uml-node-stereotype {
          font-size: 11px;
          font-weight: 600;
          color: #6b7280;
          padding: 4px 10px 0;
          font-style: italic;
        }
        .uml-inline-input {
          width: 100%;
          border: 1px solid #bfdbfe;
          border-radius: 6px;
          padding: 4px 6px;
          font-size: 13px;
          font-family: inherit;
        }
        .uml-attr-row {
          display: flex;
          align-items: center;
          gap: 6px;
          position: relative;
        }
        .uml-inline-attribute-name {
          width: 43%;
          min-width: 0;
          border: 1px solid #bfdbfe;
          border-radius: 6px;
          padding: 4px 6px;
          font-size: 13px;
          font-family: inherit;
        }
        .uml-inline-attribute-separator {
          font-size: 13px;
        }
        .uml-type-combo {
          position: relative;
          min-width: 0;
          width: 100%;
        }
        .uml-type-combo-control {
          display: flex;
          width: 100%;
        }
        .uml-type-combo-control input {
          min-width: 0;
          flex: 1;
          border: 1px solid var(--border);
          border-radius: 6px 0 0 6px;
          padding: 4px 6px;
          font: inherit;
        }
        .uml-type-combo-toggle {
          flex: 0 0 auto;
          border: 1px solid var(--border);
          border-left: 0;
          border-radius: 0 6px 6px 0;
          background: white;
          padding: 2px 6px;
          cursor: pointer;
        }
        .uml-type-combo-options {
          position: absolute;
          z-index: 30;
          top: calc(100% + 2px);
          left: 0;
          right: 0;
          max-height: 180px;
          overflow-y: auto;
          padding: 3px;
          border: 1px solid var(--border);
          border-radius: 6px;
          background: white;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }
        .uml-type-combo-options[hidden] {
          display: none;
        }
        .uml-type-combo-options button {
          display: block;
          width: 100%;
          border: 0;
          border-radius: 4px;
          padding: 5px 7px;
          background: white;
          text-align: left;
          cursor: pointer;
        }
        .uml-type-combo-options button:hover,
        .uml-type-combo-options button:focus {
          background: #eff6ff;
        }
        .uml-node.uml-type-combo-open {
          z-index: 10;
          overflow: visible;
        }
        .uml-attr-text {
          flex: 1;
          cursor: text;
          min-height: 18px;
          border-radius: 4px;
          padding: 1px 4px;
          margin: -1px -4px;
        }
        .uml-attr-text.selected {
          background: #dbeafe;
          outline: 1px solid #60a5fa;
          color: #1e3a8a;
        }
        .uml-inline-add-btn {
          border: 1px dashed #94a3b8;
          background: #f8fafc;
          border-radius: 6px;
          padding: 3px 6px;
          font-size: 12px;
          cursor: pointer;
          align-self: flex-start;
        }
        .uml-node.enum .uml-node-title {
          background: #ede9fe;
        }
        .uml-node-body {
          padding: 8px 10px;
          font-size: 13px;
          color: var(--text-secondary);
          display: flex;
          flex-direction: column;
          gap: 4px;
          flex: 1;
          justify-content: flex-start;
        }
        .uml-node-properties {
          margin-top: 8px;
          border-top: 1px solid #e5e7eb;
          padding-top: 8px;
          display: flex;
          flex-direction: column;
          gap: 8px;
        }
        .uml-node-attr {
          padding: 2px 0;
          border-top: 1px solid #e5e7eb;
          font-family: ui-monospace, monospace;
        }
        .uml-inspector {
          border: 1px solid var(--border);
          border-radius: 12px;
          padding: 10px;
          background: white;
          display: flex;
          flex-direction: column;
          gap: 8px;
        }
        .uml-inspector input,
        .uml-inspector select,
        .uml-inspector textarea {
          width: 100%;
          border: 1px solid var(--border);
          border-radius: 8px;
          padding: 6px 8px;
        }
        .uml-inspector textarea {
          min-height: 120px;
          resize: vertical;
        }
        .uml-inspector .uml-section {
          border-top: 1px solid #e5e7eb;
          padding-top: 8px;
          display: flex;
          flex-direction: column;
          gap: 6px;
        }
        .uml-inspector .uml-section:first-child {
          border-top: 0;
          padding-top: 0;
        }
        .uml-hint {
          font-size: 12px;
          color: var(--text-secondary);
          line-height: 1.4;
        }
        @media (max-width: 900px) {
          .uml-workspace {
            grid-template-columns: 1fr;
          }
        }
      `;
      document.head.appendChild(style);
    },

    normalizeTaskState(taskId, raw) {
      return normalizeState(raw);
    },

    normalizeModel(raw) {
      return normalizeState(raw);
    },

    getState(taskId) {
      if (this._stateCache[taskId]) {
        return JSON.parse(JSON.stringify(this._stateCache[taskId]));
      }

      const taskAnswers = window.assignmentState?.taskUserAnswers?.[taskId] || {};
      const serialized = taskAnswers.current_code || taskAnswers.text_answer || '';
      const task = this._renderTasks[String(taskId)];
      const starterModel = serialized ? null : getUmlStarterModel(task);
      const state = this.normalizeTaskState(taskId, serialized || starterModel);
      this._stateCache[taskId] = state;
      return JSON.parse(JSON.stringify(state));
    },

    setState(taskId, state) {
      this._stateCache[taskId] = state;
    },

    setSaveStatus(taskId, message) {
      const task = this._renderTasks[String(taskId)];
      if (task?.umlProject) {
        task.setSaveStatus(message);
        return;
      }

      this._saveStatusCache[String(taskId)] = message;
      const statusElement = task?.container?.querySelector('[data-uml-save-status]');
      if (statusElement) {
        statusElement.textContent = message;
      }
    },

    async persistState(taskId, state, skipHistoryPush = false, saveImmediately = false) {
      const normalizedState = this.normalizeTaskState(taskId, state);
      if (!skipHistoryPush) {
        this.pushHistory(taskId, normalizedState);
      }

      const renderTask = this._renderTasks[String(taskId)];
      if (renderTask?.umlProject) {
        renderTask.setSaveStatus('Speichern …');
        try {
          await renderTask.persistModel(normalizedState, saveImmediately);
          renderTask.setSaveStatus('Entwurf gespeichert');
          return true;
        } catch (error) {
          renderTask.setSaveStatus(`Speichern fehlgeschlagen: ${error?.message || error}`);
          console.error('UML project model save failed:', error);
          return false;
        }
      }

      const payload = {
        task_id: taskId,
        current_code: JSON.stringify(normalizedState),
        status: 'in-progress'
      };

      this.setSaveStatus(taskId, 'Speichern …');
      try {
        if (!window.assignmentState) {
          throw new Error('Aufgabenstatus ist nicht verfügbar.');
        }
        if (!window.assignmentState.taskUserAnswers[taskId]) {
          window.assignmentState.taskUserAnswers[taskId] = {};
        }
        window.assignmentState.taskUserAnswers[taskId].current_code = JSON.stringify(normalizedState);

        if (window.testMode === true || window.TEST_MODE_NO_PERSIST === true) {
          if (typeof TestMode !== 'undefined' && typeof TestMode.setTaskState === 'function') {
            TestMode.setTaskState(taskId, { current_code: JSON.stringify(normalizedState) });
          }
          this.setSaveStatus(taskId, 'Entwurf im Testmodus gespeichert');
          return true;
        }

        const response = await fetch('../api/user_tasks/update.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || result?.ok !== true) {
          throw new Error(result?.error || 'Speichern des Zwischenstands fehlgeschlagen.');
        }
        this.setSaveStatus(taskId, 'Entwurf gespeichert');
        return true;
      } catch (error) {
        this.setSaveStatus(taskId, `Speichern fehlgeschlagen: ${error?.message || error}`);
        console.error('UML task answer save failed:', error);
        return false;
      }
    },

    getHistory(taskId) {
      const key = `__umlTaskHistory_${taskId}`;
      const existing = window[key];
      if (existing && Array.isArray(existing.entries)) {
        return existing;
      }
      const history = { entries: [], index: -1 };
      window[key] = history;
      return history;
    },

    seedHistory(taskId, state) {
      const history = this.getHistory(taskId);
      if (history.entries.length > 0) return;
      history.entries = [JSON.stringify(this.normalizeTaskState(taskId, state))];
      history.index = 0;
      window[`__umlTaskHistory_${taskId}`] = history;
    },

    pushHistory(taskId, state) {
      const history = this.getHistory(taskId);
      const serialized = JSON.stringify(this.normalizeTaskState(taskId, state));
      const currentEntry = history.entries[history.index];
      if (currentEntry === serialized) {
        return;
      }
      history.entries = history.entries.slice(0, history.index + 1);
      history.entries.push(serialized);
      history.index = history.entries.length - 1;
      window[`__umlTaskHistory_${taskId}`] = history;
    },

    undo(taskId, container) {
      const history = this.getHistory(taskId);
      if (!history.entries.length || history.index <= 0) return;
      history.index -= 1;
      const snapshot = history.entries[history.index];
      this.applyHistorySnapshot(taskId, snapshot, container);
    },

    redo(taskId, container) {
      const history = this.getHistory(taskId);
      if (!history.entries.length || history.index >= history.entries.length - 1) return;
      history.index += 1;
      const snapshot = history.entries[history.index];
      this.applyHistorySnapshot(taskId, snapshot, container);
    },

    applyHistorySnapshot(taskId, snapshot, container) {
      const state = this.normalizeTaskState(taskId, snapshot);
      this._stateCache[taskId] = state;
      this._selectedNodeId = null;
      this._selectedRelationId = null;
      this._selectedAttributePath = null;
      this._associationMode = { phase: 'ready', points: [], startNodeId: null };
      this._associationHint = '';
      this.persistState(taskId, state, true);
      const host = container || document.querySelector('.uml-editor-shell');
      if (host) {
        this.render(this.getRenderTask(taskId), host);
      }
    },

    getRenderTask(taskId) {
      return this._renderTasks[String(taskId)]
        || (window.assignmentState?.currentTask?.id === taskId ? window.assignmentState.currentTask : null)
        || { id: taskId };
    },

    render(task, container) {
      const previousTask = this._renderTasks[String(task.id)];
      if (previousTask?.umlProject && !task.umlProject) {
        task = previousTask;
      }
      task.container = container;
      container.dataset.umlTaskId = String(task.id);
      this._renderTasks[String(task.id)] = task;
      this.ensureStyles();
      const state = this.getState(task.id);
      this.setState(task.id, state);
      this.seedHistory(task.id, state);
      container.innerHTML = this.buildHtml(task, state);
      this.renderInspector(task, state);
      this.attachEvents(task, container, state);
      this.renderConnections(task, container, state);
    },

    refreshNodeText(taskId, container, nodeId) {
      const state = this.getState(taskId);
      const node = state.nodes.find((candidate) => candidate.id === nodeId);
      if (!node || !container) return;
      const nodeElement = Array.from(container.querySelectorAll('.uml-node'))
        .find((element) => element.getAttribute('data-uml-node-id') === nodeId);
      if (!nodeElement) return;
      const title = nodeElement.querySelector('[data-uml-edit-name]');
      if (title) title.textContent = node.name;

      const body = nodeElement.querySelector('.uml-node-body');
      if (body) {
        const attributesMarkup = node.attributes.map((attribute, index) => `
          <div class="uml-attr-row">
            <div class="uml-attr-text ${this._selectedAttributePath?.nodeId === nodeId && Number(this._selectedAttributePath.index) === index ? 'selected' : ''}" data-uml-edit-attribute="${escapeHtml(nodeId)}:${index}" title="Klicken zum Auswählen, Doppelklick zum Bearbeiten" aria-selected="${this._selectedAttributePath?.nodeId === nodeId && Number(this._selectedAttributePath.index) === index}">${attribute.derived ? '/' : ''}${escapeHtml(attribute.name)}${attribute.type ? ` : ${escapeHtml(attribute.type)}` : ''}</div>
          </div>
        `).join('');
        const addLabel = node.type === 'enum' ? '+ Wert' : '+ Attribut';
        body.innerHTML = `${attributesMarkup || '<div class="uml-node-attr">(keine Attribute)</div>'}<button type="button" class="uml-inline-add-btn" data-uml-add-attribute="${escapeHtml(nodeId)}">${addLabel}</button>`;
      }
      this.renderConnections(this.getRenderTask(taskId), container, state);
    },

    renderProject(project, container, model, persistModel, setSaveStatus) {
      const contextId = `project:${project.id}`;
      const task = {
        id: contextId,
        umlProject: true,
        persistModel,
        setSaveStatus
      };
      this._stateCache[contextId] = this.normalizeModel(model);
      this.render(task, container);
    },

    async flushProject(projectId) {
      const taskId = `project:${projectId}`;
      const task = this._renderTasks[taskId];
      if (!task?.umlProject) return;
      const saved = await this.persistState(taskId, this.getState(taskId), true, true);
      if (!saved) {
        throw new Error('Das UML-Modell konnte vor dem Projektwechsel nicht gespeichert werden.');
      }
    },

    buildHtml(task, state) {
      const contextMenuMarkup = `
        <div id="uml-context-menu" style="display:none; position:fixed; z-index:9999; background:white; border:1px solid var(--border); border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.15); min-width:160px; padding:6px;">
          <button type="button" data-uml-context-action="delete" style="width:100%; text-align:left; padding:6px 8px; border:none; background:transparent; cursor:pointer;">🗑️ Löschen</button>
        </div>
      `;
      const nodes = Array.isArray(state.nodes) ? state.nodes : [];
      const relationships = Array.isArray(state.relationships) ? state.relationships : [];
      const selectedNode = nodes.find((node) => node.id === this._selectedNodeId) || null;
      const selectedRelation = (state.relationships || []).find((relation) => relation.id === this._selectedRelationId) || null;
      const selectedNodeName = selectedNode ? escapeHtml(selectedNode.name) : '';
      const selectedNodeType = selectedNode ? selectedNode.type : 'class';
      const selectedAttribute = selectedNode && this._selectedAttributePath?.nodeId === selectedNode.id
        ? selectedNode.attributes[Number(this._selectedAttributePath.index)]
        : null;
      const selectedAttributeValue = selectedAttribute ? escapeHtml(selectedAttribute.name || '') : '';
      const selectedRelationLabel = selectedRelation ? escapeHtml(selectedRelation.label || '') : '';
      const selectedRelationSource = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.sourceId)) : '';
      const selectedRelationTarget = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.targetId)) : '';
      const hintBoxText = this._associationMode?.phase === 'awaiting-target' ? 'Klasse B wählen' : 'Klasse A wählen';
      const hintBoxX = Number(this._associationCursor?.x || 40);
      const hintBoxY = Number(this._associationCursor?.y || 40);
      const hintBoxMarkup = this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target'
        ? `<div class="uml-association-hint-box" style="left:${hintBoxX}px; top:${hintBoxY}px;">${hintBoxText}</div>`
        : '';
      const taskQuestion = !task.umlProject && task.task_text
        ? `<div class="uml-task-question">${escapeHtml(task.task_text).replace(/\r\n|\n/g, '<br>')}</div>`
        : '';
      const taskImage = !task.umlProject && task.image_url
        ? `<img class="uml-task-image" src="${escapeHtml(task.image_url)}" alt="Aufgabenbild">`
        : '';
      const taskSaveStatus = !task.umlProject
        ? `<div class="uml-task-save-status" data-uml-save-status role="status" aria-live="polite">${escapeHtml(this._saveStatusCache[String(task.id)] || '')}</div>`
        : '';
      const nodeMarkup = nodes.map((node) => {
        const nodeClass = `uml-node ${node.type === 'enum' ? 'enum' : ''} ${selectedNode && selectedNode.id === node.id ? 'selected' : ''} ${this._associationMode?.phase === 'awaiting-target' && this._associationMode.startNodeId === node.id ? 'association-target' : ''}`;
        const attrsMarkup = (node.attributes || []).map((attr, index) => {
          const attrName = typeof attr === 'string' ? attr : (attr?.name || '');
          const attrType = typeof attr === 'object' && attr?.type ? attr.type : '';
          const isEditing = this._editState?.mode === 'attribute' && this._editState.nodeId === node.id && this._editState.index === index;
          if (isEditing) {
            return `
              <div class="uml-attr-row">
                <input class="uml-inline-attribute-name" data-uml-inline-attribute-name="${escapeHtml(node.id)}" data-uml-attribute-index="${index}" value="${escapeHtml(attrName)}" placeholder="Attributname" spellcheck="false" />
                <span class="uml-inline-attribute-separator">:</span>
                ${getTypeComboMarkup(attrType, getTypeSuggestions(nodes, task), `data-uml-inline-attribute-type="${escapeHtml(node.id)}" data-uml-attribute-index="${index}" placeholder="Datentyp"`)}
              </div>
            `;
          }
          const typeSuffix = (typeof attr === 'object' && attr?.type) ? ` : ${escapeHtml(attr.type)}` : '';
          const derivedPrefix = typeof attr === 'object' && attr?.derived ? '/' : '';
          const isSelectedAttribute = this._selectedAttributePath?.nodeId === node.id
            && Number(this._selectedAttributePath.index) === index;
          return `
            <div class="uml-attr-row">
              <div class="uml-attr-text ${isSelectedAttribute ? 'selected' : ''}" data-uml-edit-attribute="${escapeHtml(node.id)}:${index}" title="Klicken zum Auswählen, Doppelklick zum Bearbeiten" aria-selected="${isSelectedAttribute}">${derivedPrefix}${escapeHtml(attrName)}${typeSuffix}</div>
            </div>
          `;
        }).join('');
        const stereoMarkup = node.type === 'enum' ? '<div class="uml-node-stereotype">«Enumeration»</div>' : '';
        const titleMarkup = this._editState?.mode === 'name' && this._editState.nodeId === node.id
          ? `<input class="uml-inline-input" data-uml-inline-name="${escapeHtml(node.id)}" value="${escapeHtml(node.name)}" />`
          : `<div class="uml-node-title" data-uml-edit-name="${escapeHtml(node.id)}" title="Doppelklicken zum Bearbeiten">${escapeHtml(node.name)}</div>`;
        const addLabel = node.type === 'enum' ? '+ Wert' : '+ Attribut';
        return `
          <div class="${nodeClass}" data-uml-node-id="${escapeHtml(node.id)}" style="left:${Number(node.x || 0)}px; top:${Number(node.y || 0)}px;">
            ${stereoMarkup}
            ${titleMarkup}
            <div class="uml-node-body">
              ${attrsMarkup || '<div class="uml-node-attr">(keine Attribute)</div>'}
              <button type="button" class="uml-inline-add-btn" data-uml-add-attribute="${escapeHtml(node.id)}">${addLabel}</button>
            </div>
          </div>
        `;
      }).join('');

      return `
        <div class="uml-editor-shell">
          ${contextMenuMarkup}
          ${taskQuestion || taskImage ? `<div class="uml-task-prompt">${taskQuestion}${taskImage}</div>` : ''}
          ${taskSaveStatus}
          <div class="uml-toolbar">
            <button type="button" data-uml-action="add-class">+ Klasse</button>
            <button type="button" data-uml-action="add-enum">+ Enumeration</button>
            <button type="button" data-uml-action="add-association">Assoziation</button>
            <button type="button" data-uml-action="delete-selected">Löschen</button>
            <button type="button" data-uml-action="reset">Leeren</button>
            ${task.umlProject ? '<button type="button" data-uml-action="save">Speichern</button>' : ''}
            <span class="uml-shortcut-hint">Klick: auswählen · Entf: löschen · Doppelklick: bearbeiten · F2: Attribut hinzufügen</span>
            <span class="uml-hint">Attribut: name:Datentyp (z. B. email:String). Beziehung: Bezeichner und Kardinalitäten im Inspector festlegen.</span>
          </div>
          <div class="uml-workspace">
            <div class="uml-canvas-area${this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target' || this._associationMode?.phase === 'drawing' ? ' association-mode' : ''}">
              ${hintBoxMarkup}
              <svg class="uml-canvas-svg" viewBox="0 0 1000 1000" preserveAspectRatio="none"></svg>
              <div class="uml-relationship-layer"></div>
              <div class="uml-node-layer">${nodeMarkup}</div>
            </div>
          </div>
        </div>
      `;
    },

    renderInspector(task, state) {
      const outputEl = document.getElementById('output-container');
      if (!outputEl) return;

      const nodes = Array.isArray(state.nodes) ? state.nodes : [];
      const relationships = Array.isArray(state.relationships) ? state.relationships : [];
      const selectedNode = nodes.find((node) => node.id === this._selectedNodeId) || null;
      const selectedRelation = relationships.find((relation) => relation.id === this._selectedRelationId) || null;
      const selectedNodeName = selectedNode ? escapeHtml(selectedNode.name) : '';
      const selectedNodeType = selectedNode ? selectedNode.type : 'class';
      const selectedAttribute = selectedNode && this._selectedAttributePath?.nodeId === selectedNode.id
        ? selectedNode.attributes[Number(this._selectedAttributePath.index)]
        : null;
      const selectedAttributeValue = selectedAttribute ? escapeHtml(selectedAttribute.name || '') : '';
      const typeOptions = getTypeSuggestions(nodes, task);
      const selectedRelationLabel = selectedRelation ? escapeHtml(selectedRelation.label || '') : '';
      const selectedRelationSource = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.sourceId)) : '';
      const selectedRelationTarget = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.targetId)) : '';

      outputEl.innerHTML = `
        <div class="uml-inspector">
          <div class="uml-section">
            <strong>Output / Eigenschaften</strong>
            ${this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target' ? `<div class="uml-hint">${this._associationMode.phase === 'awaiting-target' ? 'Jetzt klicken Sie auf die zweite Klasse. Esc bricht ab.' : 'Klicken Sie zuerst auf die erste Klasse. Esc bricht ab.'}</div>` : ''}
            ${selectedNode || selectedRelation ? '' : '<div class="uml-hint">Wählen Sie eine Tabelle oder Beziehung, um die Eigenschaften hier zu bearbeiten.</div>'}
          </div>
          ${selectedRelation ? `
            <div class="uml-section">
              <strong>Beziehung</strong>
              <div>${selectedRelationSource} → ${selectedRelationTarget}</div>
              <label>Bezeichner
                <input type="text" data-uml-relation-field="label" value="${selectedRelationLabel}">
              </label>
              <div class="uml-hint">Klasse A: ${selectedRelationSource} · Klasse B: ${selectedRelationTarget}</div>
              <label>Linke Kardinalität
                <select data-uml-relation-field="sourceCardinality">
                  ${getCardinalityOptionsMarkup(selectedRelation.sourceCardinality)}
                </select>
              </label>
              <label>Rechte Kardinalität
                <select data-uml-relation-field="targetCardinality">
                  ${getCardinalityOptionsMarkup(selectedRelation.targetCardinality)}
                </select>
              </label>
            </div>
          ` : ''}
          ${selectedNode ? `
            <div class="uml-section">
              <strong>Klasseneigenschaften</strong>
              <label>Typ
                <select data-uml-editor-field="type">
                  <option value="class" ${selectedNodeType === 'class' ? 'selected' : ''}>Klasse</option>
                  <option value="enum" ${selectedNodeType === 'enum' ? 'selected' : ''}>Enumeration</option>
                </select>
              </label>
              <label>Name
                <input type="text" data-uml-editor-field="name" value="${selectedNodeName}">
              </label>
              <div class="uml-hint">Attribut anklicken zum Auswählen; mit Entf oder „Löschen“ entfernen. Doppelklick öffnet die schnelle Bearbeitung.</div>
            </div>
          ` : ''}
          ${selectedNode && selectedAttribute != null ? `
            <div class="uml-section">
              <strong>Attribut</strong>
              <label>Attributname
                <input type="text" data-uml-attribute-field="name" value="${selectedAttributeValue}" placeholder="z. B. Nachname">
              </label>
              <label>Datentyp
                ${getTypeComboMarkup(selectedAttribute.type || '', typeOptions, 'data-uml-attribute-field="type" placeholder="z. B. String"')}
              </label>
              <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" data-uml-attribute-field="derived" ${selectedAttribute.derived ? 'checked' : ''}>
                Abgeleitet
              </label>
              <div class="uml-hint">Vorschlag auswählen oder eigenen Datentyp eingeben. Alternativ direkt in der Attributzeile als name:Datentyp, z. B. email:String.</div>
            </div>
          ` : ''}
        </div>
      `;
    },

    attachInspectorEvents(task, container) {
      const outputEl = document.getElementById('output-container');
      if (!outputEl) return;
      outputEl.dataset.umlTaskId = String(task.id);
      if (outputEl.dataset.umlInspectorEventsBound) return;

      const updateInspectorField = (event, renderCanvas) => {
        const field = event.target.closest('[data-uml-editor-field], [data-uml-attribute-field], [data-uml-relation-field]');
        if (!field) return;
        const taskId = outputEl.dataset.umlTaskId;
        const activeTask = this._renderTasks[taskId] || task;
        const activeContainer = activeTask.container || container;
        if (field.hasAttribute('data-uml-editor-field')) {
          const shouldRender = renderCanvas && field.getAttribute('data-uml-editor-field') === 'type';
          this.updateSelectedNodeFromInspector(taskId, activeContainer, shouldRender);
        } else if (field.hasAttribute('data-uml-attribute-field')) {
          const shouldRender = renderCanvas && field.type === 'checkbox';
          this.updateSelectedAttributeFromInspector(taskId, activeContainer, shouldRender);
        } else if (field.tagName === 'SELECT') {
          if (event.type === 'change') {
            this.updateSelectedRelationFromInspector(taskId, activeContainer);
          }
        } else {
          this.updateSelectedRelationFromInspector(taskId, activeContainer, false);
        }
      };

      outputEl.addEventListener('input', (event) => updateInspectorField(event, false));
      outputEl.addEventListener('change', (event) => updateInspectorField(event, true));
      this.attachTypeComboEvents(outputEl, () => {
        const taskId = outputEl.dataset.umlTaskId;
        return this._renderTasks[taskId] || task;
      });
      outputEl.dataset.umlInspectorEventsBound = '1';
    },

    attachEvents(task, container, state) {
      const self = this;
      this.attachInspectorEvents(task, container);

      container.querySelectorAll('[data-uml-action]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = button.getAttribute('data-uml-action');
          if (action === 'save' && task.umlProject) {
            self.persistState(task.id, self.getState(task.id), true, true);
            return;
          }
          self.handleAction(task.id, action, container);
        });
      });

      container.querySelectorAll('[data-uml-context-action]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = button.getAttribute('data-uml-context-action');
          if (action === 'delete') {
            self.handleDeleteSelection(task.id, container);
            const menu = document.getElementById('uml-context-menu');
            if (menu) {
              menu.style.display = 'none';
            }
          }
        });
      });

      if (!container.dataset.umlNodeDelegationBound) {
        container.addEventListener('pointerdown', (event) => {
          const relationEl = event.target.closest('[data-uml-relation-id]');
          if (relationEl) {
            event.stopPropagation();
            event.preventDefault();
            self._associationMode = { phase: 'ready', points: [], startNodeId: null };
            self._associationHint = '';
            self._associationCursor = null;
            self._selectedNodeId = null;
            self._selectedAttributePath = null;
            self._selectedRelationId = relationEl.getAttribute('data-uml-relation-id');
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            self.renderInspector(window.assignmentState?.currentTask || { id: task.id }, self.getState(task.id));
            return;
          }
          const nodeEl = event.target.closest('.uml-node');
          if (!nodeEl && !event.target.closest('.uml-canvas-area')) {
            return;
          }
          if (event.target.closest('.uml-attr-text')) return;
          if (event.target.closest('input, textarea, select, [data-uml-add-attribute], [data-uml-type-combo-toggle], [data-uml-type-combo-option]')) return;
          const nodeId = self.resolveNodeIdFromEvent(task.id, container, event);
          if (!nodeId) {
            self._selectedNodeId = null;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            return;
          }
          self._selectedNodeId = nodeId;
          self._selectedRelationId = null;
          self._selectedAttributePath = null;
          if (self._associationMode?.phase === 'awaiting-source') {
            const canvasPoint = self.getCanvasPoint(container, event.clientX, event.clientY);
            const node = self.getState(task.id).nodes.find((candidate) => candidate.id === nodeId);
            const nodeElement = node ? container.querySelector(`.uml-node[data-uml-node-id="${node.id}"]`) : null;
            const startPoint = node ? self.getNodeAnchor(node, canvasPoint.x, canvasPoint.y, nodeElement) : canvasPoint;
            self._associationMode = {
              phase: 'awaiting-target',
              points: [startPoint],
              startNodeId: nodeId
            };
            self._selectedNodeId = nodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            self._associationHint = 'Jetzt klicken Sie auf die zweite Klasse.';
            self._associationCursor = { x: 40, y: 40 };
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            event.preventDefault();
            event.stopPropagation();
            return;
          }
          if (self._associationMode?.phase === 'awaiting-target') {
            if (nodeId === self._associationMode.startNodeId) {
              self._associationHint = 'Bitte wählen Sie eine zweite Klasse.';
              self.render(window.assignmentState?.currentTask || { id: task.id }, container);
              event.preventDefault();
              event.stopPropagation();
              return;
            }
            const associationStartNodeId = self._associationMode.startNodeId || null;
            self._associationMode = {
              phase: 'awaiting-source',
              points: [],
              startNodeId: null
            };
            self._associationCursor = null;
            self.completeAssociation(task.id, container, nodeId, associationStartNodeId);
            self._selectedNodeId = nodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            event.preventDefault();
            event.stopPropagation();
            return;
          }
          self._dragState = {
            nodeId,
            startX: event.clientX,
            startY: event.clientY,
            originX: Number(self.getState(task.id).nodes.find((node) => node.id === nodeId)?.x || 0),
            originY: Number(self.getState(task.id).nodes.find((node) => node.id === nodeId)?.y || 0)
          };
          container.querySelectorAll('.uml-node.selected').forEach((selectedNode) => {
            selectedNode.classList.remove('selected');
          });
          nodeEl?.classList.add('selected');
          self.renderInspector(task, self.getState(task.id));
        });
        container.addEventListener('click', (event) => {
          const addButton = event.target.closest('[data-uml-add-attribute]');
          if (addButton) {
            event.stopPropagation();
            self.addAttribute(task.id, addButton.getAttribute('data-uml-add-attribute'), container);
            return;
          }
          const title = event.target.closest('[data-uml-edit-name]');
          const attribute = event.target.closest('[data-uml-edit-attribute]');
          if (title) {
            event.stopPropagation();
            self._selectedNodeId = title.getAttribute('data-uml-edit-name');
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
          } else if (attribute) {
            event.stopPropagation();
            const [nodeId, index] = attribute.getAttribute('data-uml-edit-attribute').split(':');
            self._selectedNodeId = nodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = { nodeId, index: Number(index || 0) };
          } else {
            return;
          }
          container.querySelectorAll('.uml-attr-text.selected').forEach((selectedAttribute) => {
            selectedAttribute.classList.remove('selected');
            selectedAttribute.setAttribute('aria-selected', 'false');
          });
          if (attribute) {
            attribute.classList.add('selected');
            attribute.setAttribute('aria-selected', 'true');
          }
          container.querySelectorAll('.uml-node.selected').forEach((selectedNode) => {
            selectedNode.classList.remove('selected');
          });
          container.querySelector(`.uml-node[data-uml-node-id="${self._selectedNodeId}"]`)?.classList.add('selected');
          self.renderInspector(task, self.getState(task.id));
        });
        container.addEventListener('dblclick', (event) => {
          const title = event.target.closest('[data-uml-edit-name]');
          const attribute = event.target.closest('[data-uml-edit-attribute]');
          if (title) {
            event.stopPropagation();
            self.beginInlineEdit(task.id, 'name', title.getAttribute('data-uml-edit-name'), null, container);
          } else if (attribute) {
            event.stopPropagation();
            const [nodeId, index] = attribute.getAttribute('data-uml-edit-attribute').split(':');
            self.beginInlineEdit(task.id, 'attribute', nodeId, Number(index || 0), container);
          }
        });
        container.dataset.umlNodeDelegationBound = '1';
      }

      this.attachTypeComboEvents(container, () => task);
      container.querySelectorAll('[data-uml-inline-name], [data-uml-inline-attribute-name], [data-uml-inline-attribute-type]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            self.commitInlineEdit(task.id, container);
          } else if (event.key === 'Escape') {
            self._editState = null;
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
          }
        });
        input.addEventListener('blur', () => {
          setTimeout(() => {
            const row = input.closest('.uml-attr-row');
            if (!row?.contains(document.activeElement)) {
              self.commitInlineEdit(task.id, container);
            }
          }, 0);
        });
      });

      if (!container.dataset.umlBound) {
        document.addEventListener('click', (event) => {
          const undoBtn = event.target.closest('#undo-btn');
          const redoBtn = event.target.closest('#redo-btn');
          if (!undoBtn && !redoBtn) return;
          if (!task.umlProject && window.assignmentState?.currentTask?.task_type !== 'uml') return;
          event.preventDefault();
          event.stopPropagation();
          if (undoBtn) {
            self.undo(task.id, container);
          } else if (redoBtn) {
            self.redo(task.id, container);
          }
        }, true);
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !event.target.closest('input, textarea, select')
            && (self._associationMode?.phase === 'awaiting-source' || self._associationMode?.phase === 'awaiting-target')) {
            event.preventDefault();
            self._associationMode = { phase: 'ready', points: [], startNodeId: null };
            self._associationHint = '';
            self._associationCursor = null;
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            return;
          }
          const isDelete = event.key === 'Delete' || event.key === 'Backspace';
          if (isDelete && !event.target.closest('input, textarea, select')) {
            event.preventDefault();
            const activeTaskId = container.dataset.umlTaskId || task.id;
            self.handleDeleteSelection(activeTaskId, self._renderTasks[String(activeTaskId)]?.container || container);
          }
        });
        document.addEventListener('keydown', (event) => {
          if (event.target.closest('input, textarea, select, [contenteditable="true"]')) return;
          const isF2 = event.key === 'F2' || event.code === 'F2';
          if (isF2 && !event.shiftKey) {
            event.preventDefault();
            self.handleInlineShortcut(task.id, container, false);
          } else if (isF2 && event.shiftKey) {
            event.preventDefault();
            self.handleInlineShortcut(task.id, container, true);
          }
        });

        container.addEventListener('pointermove', (event) => {
          if (self._associationMode?.phase === 'awaiting-source' || self._associationMode?.phase === 'awaiting-target') {
            const canvasPoint = self.getCanvasPoint(container, event.clientX, event.clientY);
            self._associationCursor = canvasPoint;
            if (self._associationMode?.phase === 'awaiting-target' && self._associationMode.startNodeId) {
              const state = self.getState(task.id);
              const sourceNode = state.nodes.find((candidate) => candidate.id === self._associationMode.startNodeId);
              if (!sourceNode) return;
              const nodeElement = container.querySelector(`.uml-node[data-uml-node-id="${sourceNode.id}"]`);
              const startPoint = self.getNodeAnchor(sourceNode, canvasPoint.x, canvasPoint.y, nodeElement);
              self._associationMode.points = [startPoint, canvasPoint];
            }
            const hintBox = container.querySelector('.uml-association-hint-box');
            if (hintBox) {
              hintBox.style.left = `${canvasPoint.x}px`;
              hintBox.style.top = `${canvasPoint.y}px`;
            }
            return;
          }
          if (self._dragState) {
            const currentState = self.getState(task.id);
            const node = currentState.nodes.find((candidate) => candidate.id === self._dragState.nodeId);
            if (!node) return;
            const deltaX = event.clientX - self._dragState.startX;
            const deltaY = event.clientY - self._dragState.startY;
            node.x = Math.max(0, self._dragState.originX + deltaX);
            node.y = Math.max(0, self._dragState.originY + deltaY);
            self.setState(task.id, currentState);
            const nodeElement = container.querySelector(`.uml-node[data-uml-node-id="${node.id}"]`);
            if (nodeElement) {
              nodeElement.style.left = `${node.x}px`;
              nodeElement.style.top = `${node.y}px`;
            }
            self.renderConnections(task, container, currentState);
            return;
          }
          if (self._relationHandleDrag) {
            const canvasPoint = self.getCanvasPoint(container, event.clientX, event.clientY);
            const state = self.getState(task.id);
            const relation = state.relationships.find((candidate) => candidate.id === self._relationHandleDrag.relationId);
            if (!relation) return;
            const pointIndex = self._relationHandleDrag.pointIndex;
            if (Number.isInteger(pointIndex)) {
              relation.points[pointIndex] = { x: canvasPoint.x, y: canvasPoint.y };
            }
            self.setState(task.id, state);
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            return;
          }
        });

        container.addEventListener('pointerup', (event) => {
          if (self._dragState) {
            self.persistState(task.id, self.getState(task.id));
            self._dragState = null;
            return;
          }
          if (self._relationHandleDrag) {
            self.persistState(task.id, self.getState(task.id));
            self._relationHandleDrag = null;
            return;
          }
        });

        const canvasArea = container.querySelector('.uml-canvas-area');
        canvasArea?.addEventListener('pointerdown', (event) => {
          const relationEl = event.target.closest('[data-uml-relation-id]');
          if (relationEl) {
            event.stopPropagation();
            event.preventDefault();
            self._associationMode = { phase: 'ready', points: [], startNodeId: null };
            self._associationHint = '';
            self._associationCursor = null;
            self._selectedNodeId = null;
            self._selectedAttributePath = null;
            self._selectedRelationId = relationEl.getAttribute('data-uml-relation-id');
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            self.renderInspector(window.assignmentState?.currentTask || { id: task.id }, self.getState(task.id));
            return;
          }
          const targetNodeId = self.resolveNodeIdFromEvent(task.id, container, event);
          if (!targetNodeId) {
            if (self._associationMode?.phase === 'awaiting-source') {
              self._associationHint = 'Bitte wählen Sie zuerst eine Klasse.';
              self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            }
            event.preventDefault();
            event.stopPropagation();
            return;
          }
          if (self._associationMode?.phase === 'awaiting-source') {
            const canvasPoint = self.getCanvasPoint(container, event.clientX, event.clientY);
            const node = self.getState(task.id).nodes.find((candidate) => candidate.id === targetNodeId);
            const nodeElement = node ? container.querySelector(`.uml-node[data-uml-node-id="${node.id}"]`) : null;
            const startPoint = node ? self.getNodeAnchor(node, canvasPoint.x, canvasPoint.y, nodeElement) : canvasPoint;
            self._associationMode = {
              phase: 'awaiting-target',
              points: [startPoint],
              startNodeId: targetNodeId
            };
            self._selectedNodeId = targetNodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            self._associationHint = 'Jetzt klicken Sie auf die zweite Klasse.';
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            event.preventDefault();
            event.stopPropagation();
            return;
          }
          if (self._associationMode?.phase === 'awaiting-target') {
            if (targetNodeId === self._associationMode.startNodeId) {
              self._associationHint = 'Bitte wählen Sie eine zweite Klasse.';
              self.render(window.assignmentState?.currentTask || { id: task.id }, container);
              event.preventDefault();
              event.stopPropagation();
              return;
            }
            const associationStartNodeId = self._associationMode.startNodeId || null;
            self._associationMode = {
              phase: 'ready',
              points: [],
              startNodeId: null
            };
            self._associationCursor = null;
            self.completeAssociation(task.id, container, targetNodeId, associationStartNodeId);
            self._selectedNodeId = targetNodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            event.preventDefault();
            event.stopPropagation();
          }
        });
        canvasArea?.addEventListener('click', (event) => {
          if (event.target.closest('.uml-node')) return;
          if (event.target.closest('.uml-relationship')) return;
          if (event.target.closest('[data-uml-relation-id]')) return;
          self._selectedNodeId = null;
          self._selectedRelationId = null;
          self._selectedAttributePath = null;
          self.render(task, container);
        });

        canvasArea?.addEventListener('contextmenu', (event) => {
          event.preventDefault();
          const nodeEl = event.target.closest('.uml-node');
          const relationEl = event.target.closest('[data-uml-relation-id]');
          const selectedId = nodeEl?.getAttribute('data-uml-node-id') || relationEl?.getAttribute('data-uml-relation-id') || null;
          if (nodeEl) {
            self._selectedNodeId = selectedId;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
          } else if (relationEl) {
            self._selectedRelationId = selectedId;
            self._selectedNodeId = null;
            self._selectedAttributePath = null;
          } else {
            self._selectedNodeId = null;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
          }
          self.render(task, container);
          const menu = document.getElementById('uml-context-menu');
          if (menu) {
            menu.style.left = `${event.clientX}px`;
            menu.style.top = `${event.clientY}px`;
            menu.style.display = 'block';
          }
        });

        document.addEventListener('click', (event) => {
          if (!event.target.closest('#uml-context-menu')) {
            const menu = document.getElementById('uml-context-menu');
            if (menu) menu.style.display = 'none';
          }
        });

        container.dataset.umlBound = '1';
      }
    },

    updateTypeComboOptions(input, task, open = true) {
      const combo = input?.closest('.uml-type-combo');
      const options = combo?.querySelector('[data-uml-type-combo-options]');
      if (!combo || !options) return;
      const suggestions = getTypeSuggestions(this.getState(task.id).nodes, task);
      const query = String(input.value || '').trim().toLocaleLowerCase();
      const matches = query
        ? suggestions.filter((suggestion) => suggestion.toLocaleLowerCase().startsWith(query))
        : suggestions;
      options.innerHTML = matches.length
        ? matches.map((suggestion) => (
          `<button type="button" role="option" data-uml-type-combo-option="${escapeHtml(suggestion)}">${escapeHtml(suggestion)}</button>`
        )).join('')
        : '<div class="uml-hint">Keine passenden Vorschläge</div>';
      options.hidden = !open;
      combo.closest('.uml-node')?.classList.toggle('uml-type-combo-open', open);
    },

    attachTypeComboEvents(root, getTask) {
      if (root.dataset.umlTypeComboEventsBound) return;
      root.addEventListener('focusin', (event) => {
        const input = event.target.closest('.uml-type-combo input');
        if (input) this.updateTypeComboOptions(input, getTask(), true);
      });
      root.addEventListener('input', (event) => {
        const input = event.target.closest('.uml-type-combo input');
        if (input) this.updateTypeComboOptions(input, getTask(), true);
      });
      root.addEventListener('keydown', (event) => {
        const input = event.target.closest('.uml-type-combo input');
        if (!input) return;
        if (event.key === 'Escape') {
          this.updateTypeComboOptions(input, getTask(), false);
        } else if (event.key === 'ArrowDown') {
          const firstOption = input.closest('.uml-type-combo')?.querySelector('[data-uml-type-combo-option]');
          if (firstOption) {
            event.preventDefault();
            firstOption.focus();
          }
        }
      });
      root.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-uml-type-combo-toggle]');
        if (toggle) {
          event.preventDefault();
          event.stopPropagation();
          const input = toggle.closest('.uml-type-combo')?.querySelector('input');
          const options = toggle.closest('.uml-type-combo')?.querySelector('[data-uml-type-combo-options]');
          if (input) this.updateTypeComboOptions(input, getTask(), Boolean(options?.hidden));
          return;
        }
        const option = event.target.closest('[data-uml-type-combo-option]');
        if (option) {
          event.preventDefault();
          event.stopPropagation();
          const combo = option.closest('.uml-type-combo');
          const input = combo?.querySelector('input');
          if (input) {
            input.value = option.getAttribute('data-uml-type-combo-option') || '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
            this.updateTypeComboOptions(input, getTask(), false);
          }
          return;
        }
        root.querySelectorAll('.uml-type-combo-options:not([hidden])').forEach((options) => {
          options.hidden = true;
          options.closest('.uml-type-combo')?.closest('.uml-node')?.classList.remove('uml-type-combo-open');
        });
      });
      root.dataset.umlTypeComboEventsBound = '1';
    },

    beginInlineEdit(taskId, mode, nodeId, index, container) {
      this._editState = { mode, nodeId, index };
      if (mode === 'name' && nodeId) {
        this._selectedNodeId = nodeId;
        this._selectedRelationId = null;
        this._selectedAttributePath = null;
      }
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      setTimeout(() => {
        const input = container.querySelector('[data-uml-inline-name], [data-uml-inline-attribute-name]');
        input?.focus();
        input?.select();
      }, 0);
    },

    commitInlineEdit(taskId, container) {
      const editState = this._editState;
      if (!editState) return;
      const state = this.getState(taskId);
      const node = state.nodes.find((candidate) => candidate.id === editState.nodeId);
      if (!node) {
        this._editState = null;
        return;
      }
      if (editState.mode === 'name') {
        const input = container.querySelector('[data-uml-inline-name]');
        node.name = (input?.value || '').trim() || (node.type === 'enum' ? 'Enum' : 'Klasse');
      } else if (editState.mode === 'attribute') {
        const nameInput = container.querySelector(`[data-uml-inline-attribute-name="${editState.nodeId}"]`);
        const typeInput = container.querySelector(`[data-uml-inline-attribute-type="${editState.nodeId}"]`);
        const name = (nameInput?.value || '').trim();
        const previousAttribute = node.attributes[editState.index];
        const type = (typeInput?.value || '').trim();
        if (name) {
          node.attributes[editState.index] = {
            id: previousAttribute?.id || createAttributeId(),
            name,
            type,
            derived: Boolean(previousAttribute?.derived)
          };
        } else {
          node.attributes = node.attributes.filter((_, attributeIndex) => attributeIndex !== editState.index);
        }
      }
      this._editState = null;
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
    },

    addAttribute(taskId, nodeId, container) {
      const state = this.getState(taskId);
      const node = state.nodes.find((candidate) => candidate.id === nodeId);
      if (!node) return;
      node.attributes.push({ id: createAttributeId(), name: '', type: '' });
      this._selectedNodeId = nodeId;
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.beginInlineEdit(taskId, 'attribute', nodeId, node.attributes.length - 1, container);
    },

    handleInlineShortcut(taskId, container, useLiteral) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode) {
        return;
      }
      const value = useLiteral ? 'literal' : '';
      selectedNode.attributes.push({ id: createAttributeId(), name: value, type: '' });
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.beginInlineEdit(taskId, 'attribute', selectedNode.id, selectedNode.attributes.length - 1, container);
    },

    resolveNodeIdFromEvent(taskId, container, event) {
      const targetEl = event?.target;
      if (targetEl?.closest?.('.uml-node')) {
        return targetEl.closest('.uml-node').getAttribute('data-uml-node-id') || null;
      }

      const point = this.getCanvasPoint(container, event?.clientX, event?.clientY);
      if (!Number.isFinite(point.x) || !Number.isFinite(point.y)) {
        return null;
      }

      const hitEl = document.elementFromPoint(event?.clientX, event?.clientY);
      const nodeEl = hitEl?.closest?.('.uml-node');
      if (nodeEl) {
        return nodeEl.getAttribute('data-uml-node-id') || null;
      }

      const state = this.getState(taskId);
      const node = (state.nodes || []).find((candidate) => {
        const left = Number(candidate.x || 0);
        const top = Number(candidate.y || 0);
        return point.x >= left && point.x <= left + NODE_WIDTH && point.y >= top && point.y <= top + NODE_HEIGHT;
      });
      return node?.id || null;
    },

    getNodeAnchor(node, targetX, targetY, nodeEl = null) {
      const canvasArea = nodeEl?.closest('.uml-canvas-area');
      const canvasRect = canvasArea?.getBoundingClientRect();
      if (nodeEl && canvasRect) {
        const rect = nodeEl.getBoundingClientRect();
        const centerX = rect.left - canvasRect.left + rect.width / 2;
        const centerY = rect.top - canvasRect.top + rect.height / 2;
        const width = rect.width || NODE_WIDTH;
        const height = rect.height || NODE_HEIGHT;
        const dx = Number(targetX) - centerX;
        const dy = Number(targetY) - centerY;
        if (dx === 0 && dy === 0) {
          return { x: centerX, y: centerY };
        }
        if (Math.abs(dx) * height > Math.abs(dy) * width) {
          const sign = dx >= 0 ? 1 : -1;
          return {
            x: centerX + sign * width / 2,
            y: centerY + (dy / Math.abs(dx || 1)) * width / 2
          };
        }
        const sign = dy >= 0 ? 1 : -1;
        return {
          x: centerX + (dx / Math.abs(dy || 1)) * height / 2,
          y: centerY + sign * height / 2
        };
      }

      const centerX = Number(node.x || 0) + NODE_WIDTH / 2;
      const centerY = Number(node.y || 0) + NODE_HEIGHT / 2;
      const dx = Number(targetX) - centerX;
      const dy = Number(targetY) - centerY;
      if (dx === 0 && dy === 0) {
        return { x: centerX, y: centerY };
      }
      if (Math.abs(dx) * NODE_HEIGHT > Math.abs(dy) * NODE_WIDTH) {
        const sign = dx >= 0 ? 1 : -1;
        return {
          x: centerX + sign * NODE_WIDTH / 2,
          y: centerY + (dy / Math.abs(dx || 1)) * NODE_WIDTH / 2
        };
      }
      const sign = dy >= 0 ? 1 : -1;
      return {
        x: centerX + (dx / Math.abs(dy || 1)) * NODE_HEIGHT / 2,
        y: centerY + sign * NODE_HEIGHT / 2
      };
    },

    getNodeAnchorFromRect(nodeRect, targetX, targetY) {
      const width = Number(nodeRect?.width || NODE_WIDTH);
      const height = Number(nodeRect?.height || NODE_HEIGHT);
      const left = Number(nodeRect?.left || 0);
      const top = Number(nodeRect?.top || 0);
      const centerX = left + width / 2;
      const centerY = top + height / 2;
      const dx = Number(targetX) - centerX;
      const dy = Number(targetY) - centerY;
      if (dx === 0 && dy === 0) {
        return { x: centerX, y: centerY };
      }
      if (Math.abs(dx) * height > Math.abs(dy) * width) {
        const sign = dx >= 0 ? 1 : -1;
        return {
          x: centerX + sign * width / 2,
          y: centerY + (dy / Math.abs(dx || 1)) * width / 2
        };
      }
      const sign = dy >= 0 ? 1 : -1;
      return {
        x: centerX + (dx / Math.abs(dy || 1)) * height / 2,
        y: centerY + sign * height / 2
      };
    },

    getCanvasPoint(container, clientX, clientY) {
      const canvasArea = container.querySelector('.uml-canvas-area');
      if (!canvasArea) return { x: clientX, y: clientY };
      const rect = canvasArea.getBoundingClientRect();
      return { x: clientX - rect.left, y: clientY - rect.top };
    },

    completeAssociation(taskId, container, targetNodeId, startNodeId = this._associationMode?.startNodeId || null) {
      const state = this.getState(taskId);
      if (!startNodeId || !targetNodeId || targetNodeId === startNodeId) {
        this._associationMode = { phase: 'ready', points: [], startNodeId: null };
        this._associationHint = '';
        this._associationCursor = null;
        if (container) {
          this.render(window.assignmentState?.currentTask || { id: taskId }, container);
        }
        return;
      }

      const startNode = state.nodes.find((candidate) => candidate.id === startNodeId);
      const targetNode = state.nodes.find((candidate) => candidate.id === targetNodeId);
      const startNodeElement = startNode ? container?.querySelector(`.uml-node[data-uml-node-id="${startNode.id}"]`) : null;
      const targetNodeElement = targetNode ? container?.querySelector(`.uml-node[data-uml-node-id="${targetNode.id}"]`) : null;
      const fallbackStartAnchor = startNode ? { x: Number(startNode.x || 0) + NODE_WIDTH / 2, y: Number(startNode.y || 0) + NODE_HEIGHT / 2 } : null;
      const fallbackTargetAnchor = targetNode ? { x: Number(targetNode.x || 0) + NODE_WIDTH / 2, y: Number(targetNode.y || 0) + NODE_HEIGHT / 2 } : null;
      const startAnchor = startNode ? this.getNodeAnchor(startNode, Number(targetNode?.x || 0) + NODE_WIDTH / 2, Number(targetNode?.y || 0) + NODE_HEIGHT / 2, startNodeElement) : fallbackStartAnchor;
      const targetAnchor = targetNode ? this.getNodeAnchor(targetNode, Number(startNode?.x || 0) + NODE_WIDTH / 2, Number(startNode?.y || 0) + NODE_HEIGHT / 2, targetNodeElement) : fallbackTargetAnchor;
      state.relationships.push({
        id: createEntityId('relationship'),
        sourceId: startNodeId,
        targetId: targetNodeId,
        label: '',
        points: [startAnchor, targetAnchor].filter(Boolean)
      });
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this._associationMode = { phase: 'ready', points: [], startNodeId: null };
      this._associationHint = '';
      this._associationCursor = null;
      this._selectedRelationId = state.relationships[state.relationships.length - 1]?.id || null;
      this._selectedNodeId = null;
      this._selectedAttributePath = null;
      if (container) {
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      }
    },

    getNodeAtPoint(taskId, container, clientX, clientY) {
      const point = this.getCanvasPoint(container, clientX, clientY);
      const state = this.getState(taskId);
      const node = (state.nodes || []).find((candidate) => {
        const left = Number(candidate.x || 0);
        const top = Number(candidate.y || 0);
        return point.x >= left && point.x <= left + NODE_WIDTH && point.y >= top && point.y <= top + NODE_HEIGHT;
      });
      return node?.id || null;
    },

    getNodeNameById(state, nodeId) {
      return (state.nodes || []).find((node) => node.id === nodeId)?.name || 'Unbekannt';
    },

    handleDeleteSelection(taskId, container) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (this._selectedAttributePath) {
        const index = Number(this._selectedAttributePath.index);
        if (!selectedNode || this._selectedAttributePath.nodeId !== selectedNode.id
          || !Number.isInteger(index) || index < 0 || index >= selectedNode.attributes.length) return;
        selectedNode.attributes.splice(index, 1);
        this._selectedAttributePath = null;
      } else if (this._selectedRelationId) {
        if (!state.relationships.some((relation) => relation.id === this._selectedRelationId)) return;
        state.relationships = state.relationships.filter((relation) => relation.id !== this._selectedRelationId);
        this._selectedRelationId = null;
      } else if (this._selectedNodeId) {
        if (!selectedNode) return;
        const relationCount = state.relationships.filter((relation) => (
          relation.sourceId === this._selectedNodeId || relation.targetId === this._selectedNodeId
        )).length;
        const relationWarning = relationCount === 1
          ? ' Dabei wird auch eine verbundene Beziehung gelöscht.'
          : relationCount > 1
            ? ` Dabei werden auch ${relationCount} verbundene Beziehungen gelöscht.`
            : '';
        if (!window.confirm(`Klasse „${selectedNode?.name || 'Unbekannt'}“ wirklich löschen?${relationWarning}`)) return;
        state.nodes = state.nodes.filter((node) => node.id !== this._selectedNodeId);
        state.relationships = state.relationships.filter((relation) => relation.sourceId !== this._selectedNodeId && relation.targetId !== this._selectedNodeId);
        this._selectedNodeId = null;
      } else {
        return;
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
    },

    handleAction(taskId, action, container) {
      if (!taskId) return;

      if (action === 'add-class') {
        const state = this.getState(taskId);
        const canvasWidth = container?.querySelector('.uml-canvas-area')?.clientWidth || 1000;
        const node = createNode('class', getNewNodePosition(state.nodes, canvasWidth));
        state.nodes.push(node);
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.beginInlineEdit(taskId, 'name', node.id, null, container);
      } else if (action === 'add-enum') {
        const state = this.getState(taskId);
        const canvasWidth = container?.querySelector('.uml-canvas-area')?.clientWidth || 1000;
        const node = createNode('enum', getNewNodePosition(state.nodes, canvasWidth));
        state.nodes.push(node);
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.beginInlineEdit(taskId, 'name', node.id, null, container);
      } else if (action === 'add-association') {
        if (this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target') {
          this._associationMode = { phase: 'ready', points: [], startNodeId: null };
          this._associationHint = '';
          this._associationCursor = null;
          this.render(window.assignmentState?.currentTask || { id: taskId }, container);
          return;
        }
        this._associationMode = { phase: 'awaiting-source', points: [], startNodeId: null };
        this._associationPreview = null;
        this._associationHint = 'Klicken Sie zuerst auf die erste Klasse.';
        this._associationCursor = { x: 40, y: 40 };
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      } else if (action === 'delete-selected') {
        this.handleDeleteSelection(taskId, container);
      } else if (action === 'reset') {
        const state = { version: UML_VERSION, nodes: [], relationships: [] };
        this._selectedNodeId = null;
        this._selectedRelationId = null;
        this._selectedAttributePath = null;
        this._associationMode = { phase: 'ready', points: [], startNodeId: null };
        this._associationPreview = null;
        this._associationHint = '';
        this._associationCursor = null;
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      }
    },

    updateSelectedNodeFromInspector(taskId, container, renderCanvas = true) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode) return;
      const inspectorHost = document.getElementById('output-container') || container;
      const typeInput = inspectorHost.querySelector('[data-uml-editor-field="type"]');
      const nameInput = inspectorHost.querySelector('[data-uml-editor-field="name"]');
      if (typeInput) {
        selectedNode.type = typeInput.value === 'enum' ? 'enum' : 'class';
      }
      if (nameInput) {
        selectedNode.name = nameInput.value.trim() || (selectedNode.type === 'enum' ? 'Enum' : 'Klasse');
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      if (renderCanvas) {
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      } else {
        this.refreshNodeText(taskId, container, selectedNode.id);
      }
    },

    updateSelectedRelationFromInspector(taskId, container, renderCanvas = true) {
      const state = this.getState(taskId);
      const selectedRelation = state.relationships.find((relation) => relation.id === this._selectedRelationId);
      if (!selectedRelation) return;
      const inspectorHost = document.getElementById('output-container') || container;
      const labelInput = inspectorHost.querySelector('[data-uml-relation-field="label"]');
      const sourceInput = inspectorHost.querySelector('[data-uml-relation-field="sourceCardinality"]');
      const targetInput = inspectorHost.querySelector('[data-uml-relation-field="targetCardinality"]');
      if (labelInput) {
        selectedRelation.label = labelInput.value.trim();
      }
      if (sourceInput) {
        selectedRelation.sourceCardinality = sourceInput.value.trim();
      }
      if (targetInput) {
        selectedRelation.targetCardinality = targetInput.value.trim();
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      if (renderCanvas) {
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      } else {
        this.renderConnections(this.getRenderTask(taskId), container, state);
      }
    },

    updateSelectedAttributeFromInspector(taskId, container, renderCanvas = true) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode || !this._selectedAttributePath || this._selectedAttributePath.nodeId !== selectedNode.id) return;
      const inspectorHost = document.getElementById('output-container') || container;
      const nameInput = inspectorHost.querySelector('[data-uml-attribute-field="name"]');
      const typeInput = inspectorHost.querySelector('[data-uml-attribute-field="type"]');
      const derivedInput = inspectorHost.querySelector('[data-uml-attribute-field="derived"]');
      const name = (nameInput?.value || '').trim();
      const type = (typeInput?.value || '').trim();
      const index = Number(this._selectedAttributePath.index);
      if (Number.isNaN(index)) return;
      if (!name && !type) {
        selectedNode.attributes = selectedNode.attributes.filter((_, attributeIndex) => attributeIndex !== index);
      } else if (name) {
        selectedNode.attributes[index] = {
          id: selectedNode.attributes[index]?.id || createAttributeId(),
          name,
          type,
          derived: derivedInput instanceof HTMLInputElement
            ? derivedInput.checked
            : Boolean(selectedNode.attributes[index]?.derived)
        };
      } else {
        selectedNode.attributes = selectedNode.attributes.filter((_, attributeIndex) => attributeIndex !== index);
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      if (renderCanvas) {
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      } else {
        this.refreshNodeText(taskId, container, selectedNode.id);
      }
    },

    renderConnections(task, container, state) {
      const canvasArea = container.querySelector('.uml-canvas-area');
      const relationshipLayer = container.querySelector('.uml-relationship-layer');
      if (!canvasArea || !relationshipLayer) return;

      relationshipLayer.innerHTML = '';

      const createConnectorLine = (startPoint, endPoint, relationId = null, isSelected = false) => {
        const deltaX = endPoint.x - startPoint.x;
        const deltaY = endPoint.y - startPoint.y;
        const distance = Math.max(1, Math.hypot(deltaX, deltaY));
        const angle = Math.atan2(deltaY, deltaX) * 180 / Math.PI;
        const line = document.createElement('div');
        line.className = `uml-relationship-line${isSelected ? ' selected' : ''}`;
        line.style.left = `${startPoint.x}px`;
        line.style.top = `${startPoint.y - 6}px`;
        line.style.width = `${Math.max(28, distance)}px`;
        line.style.transform = `rotate(${angle}deg)`;
        line.style.padding = '0';
        line.style.margin = '0';
        if (relationId) {
          line.setAttribute('data-uml-relation-id', relationId);
          line.addEventListener('pointerdown', (event) => {
            event.stopPropagation();
            event.preventDefault();
            this._selectedNodeId = null;
            this._selectedAttributePath = null;
            this._selectedRelationId = relationId;
            this.render(window.assignmentState?.currentTask || { id: task.id }, container);
            if (container) {
              this.renderInspector(window.assignmentState?.currentTask || { id: task.id }, this.getState(task.id));
            }
          });
        }
        return line;
      };

      if (this._associationMode?.phase === 'drawing' && Array.isArray(this._associationMode.points) && this._associationMode.points.length >= 2) {
        const [startPoint, endPoint] = this._associationMode.points;
        relationshipLayer.appendChild(createConnectorLine(startPoint, endPoint));
      }

      const nodeElements = Array.from(container.querySelectorAll('.uml-node'));
      const nodeElementMap = new Map(nodeElements.map((nodeEl) => [nodeEl.getAttribute('data-uml-node-id'), nodeEl]));
      const canvasRect = canvasArea.getBoundingClientRect();

      (state.relationships || []).forEach((relation) => {
        const sourceNode = (state.nodes || []).find((node) => node.id === relation.sourceId);
        const targetNode = (state.nodes || []).find((node) => node.id === relation.targetId);
        if (!sourceNode || !targetNode) return;

        const sourceElement = nodeElementMap.get(relation.sourceId);
        const targetElement = nodeElementMap.get(relation.targetId);
        const sourceRect = sourceElement ? sourceElement.getBoundingClientRect() : null;
        const targetRect = targetElement ? targetElement.getBoundingClientRect() : null;

        const fallbackSourcePoint = this.getNodeAnchor(sourceNode, Number(targetNode.x || 0) + NODE_WIDTH / 2, Number(targetNode.y || 0) + NODE_HEIGHT / 2);
        const fallbackTargetPoint = this.getNodeAnchor(targetNode, Number(sourceNode.x || 0) + NODE_WIDTH / 2, Number(sourceNode.y || 0) + NODE_HEIGHT / 2);

        const sourceBox = sourceRect ? {
          width: sourceRect.width,
          height: sourceRect.height,
          left: sourceRect.left - canvasRect.left,
          top: sourceRect.top - canvasRect.top
        } : null;
        const targetBox = targetRect ? {
          width: targetRect.width,
          height: targetRect.height,
          left: targetRect.left - canvasRect.left,
          top: targetRect.top - canvasRect.top
        } : null;

        const sourceCenterX = sourceBox ? sourceBox.left + sourceBox.width / 2 : fallbackSourcePoint.x;
        const sourceCenterY = sourceBox ? sourceBox.top + sourceBox.height / 2 : fallbackSourcePoint.y;
        const targetCenterX = targetBox ? targetBox.left + targetBox.width / 2 : fallbackTargetPoint.x;
        const targetCenterY = targetBox ? targetBox.top + targetBox.height / 2 : fallbackTargetPoint.y;

        const sourcePoint = sourceBox
          ? this.getNodeAnchorFromRect(sourceBox, targetCenterX, targetCenterY)
          : fallbackSourcePoint;
        const targetPoint = targetBox
          ? this.getNodeAnchorFromRect(targetBox, sourceCenterX, sourceCenterY)
          : fallbackTargetPoint;

        const points = Array.isArray(relation.points) && relation.points.length > 1
          ? relation.points
          : [sourcePoint, targetPoint];
        const isSelected = this._selectedRelationId === relation.id;
        const line = createConnectorLine(points[0] || sourcePoint, points[1] || targetPoint, relation.id, isSelected);
        relationshipLayer.appendChild(line);

        const start = points[0] || sourcePoint;
        const end = points[1] || targetPoint;
        const deltaX = end.x - start.x;
        const deltaY = end.y - start.y;
        const distance = Math.max(1, Math.hypot(deltaX, deltaY));
        const labelOffset = Math.min(20, distance / 3);
        if (relation.label) {
          const label = document.createElement('span');
          label.className = `uml-relationship-name${isSelected ? ' selected' : ''}`;
          label.textContent = relation.label;
          label.style.left = `${(start.x + end.x) / 2}px`;
          label.style.top = `${(start.y + end.y) / 2 - 12}px`;
          relationshipLayer.appendChild(label);
        }
        [
          { value: relation.sourceCardinality, point: start, direction: 1 },
          { value: relation.targetCardinality, point: end, direction: -1 }
        ].forEach(({ value, point, direction }) => {
          if (!value) return;
          const label = document.createElement('span');
          label.className = 'uml-cardinality-label';
          label.textContent = value;
          label.style.left = `${point.x + (deltaX / distance) * labelOffset * direction}px`;
          label.style.top = `${point.y + (deltaY / distance) * labelOffset * direction}px`;
          relationshipLayer.appendChild(label);
        });
      });
    }
  };
})();
