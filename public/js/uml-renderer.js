(function () {
  const UML_VERSION = 1;
  const NODE_WIDTH = 200;
  const NODE_HEIGHT = 120;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function getTypeSuggestions(nodes = []) {
    const baseSuggestions = ['String', 'Integer', 'Float', 'Boolean', 'Date', 'List', 'Map', 'Text', 'Object'];
    const nodeSuggestions = Array.isArray(nodes) ? nodes
      .filter((node) => node?.type === 'enum' || node?.type === 'structure')
      .map((node) => node?.name)
      .filter(Boolean) : [];
    return Array.from(new Set(baseSuggestions.concat(nodeSuggestions).filter(Boolean)));
  }

  function normalizeAttribute(rawAttribute, index) {
    if (typeof rawAttribute === 'string') {
      const value = String(rawAttribute || '').trim();
      return value ? { name: value, type: '' } : { name: '', type: '' };
    }
    if (rawAttribute && typeof rawAttribute === 'object') {
      const name = String(rawAttribute.name || rawAttribute.value || '').trim();
      const type = String(rawAttribute.type || rawAttribute.datatype || '').trim();
      return { name, type };
    }
    return { name: '', type: '' };
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
        id: node?.id || `uml-node-${index + 1}`,
        type: node?.type === 'enum' ? 'enum' : node?.type === 'structure' ? 'structure' : 'class',
        name: String(node?.name || (node?.type === 'enum' ? 'Enum' : 'Klasse')),
        attributes: Array.isArray(node?.attributes) ? node.attributes.map((attr, index) => normalizeAttribute(attr, index)).filter((attr) => attr.name || attr.type) : [],
        x: Number(node?.x) || 80 + (index * 40),
        y: Number(node?.y) || 80 + (index * 40)
      })),
      relationships: relationships.map((relation, index) => ({
        id: relation?.id || `uml-relationship-${index + 1}`,
        sourceId: String(relation?.sourceId || ''),
        targetId: String(relation?.targetId || ''),
        label: String(relation?.label || ''),
        points: Array.isArray(relation?.points) ? relation.points.map((point) => ({ x: Number(point?.x), y: Number(point?.y) })) : []
      })).filter((relation) => relation.sourceId && relation.targetId)
    };
  }

  function createNode(type, index = 0) {
    return {
      id: `uml-node-${Date.now()}-${index}`,
      type: type === 'enum' ? 'enum' : type === 'structure' ? 'structure' : 'class',
      name: type === 'enum' ? 'Enum' : type === 'structure' ? 'Struktur' : 'Klasse',
      attributes: type === 'enum' ? [{ name: 'WERT_A', type: '' }, { name: 'WERT_B', type: '' }] : [{ name: 'attribute', type: 'String' }],
      x: 80 + (index * 50),
      y: 80 + (index * 40)
    };
  }

  window.UmlRenderer = {
    _stateCache: {},
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
          height: 1px;
          padding: 0;
          margin: 0;
          background: #64748b;
          border-radius: 999px;
          transform-origin: 0 0;
          pointer-events: auto;
          cursor: pointer;
          z-index: 4;
          box-sizing: border-box;
        }
        .uml-relationship-line.selected {
          background: #ef4444;
          height: 2px;
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
          transform: translateY(-1px) scale(1.01);
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
          cursor: default;
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
        }
        .uml-attr-text {
          flex: 1;
          cursor: default;
          min-height: 18px;
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

    getState(taskId) {
      if (this._stateCache[taskId]) {
        return JSON.parse(JSON.stringify(this._stateCache[taskId]));
      }

      const taskAnswers = window.assignmentState?.taskUserAnswers?.[taskId] || {};
      const serialized = taskAnswers.current_code || taskAnswers.text_answer || '';
      const state = this.normalizeTaskState(taskId, serialized);
      this._stateCache[taskId] = state;
      return JSON.parse(JSON.stringify(state));
    },

    setState(taskId, state) {
      this._stateCache[taskId] = state;
    },

    async persistState(taskId, state, skipHistoryPush = false) {
      const normalizedState = this.normalizeTaskState(taskId, state);
      if (!skipHistoryPush) {
        this.pushHistory(taskId, normalizedState);
      }

      const payload = {
        task_id: taskId,
        current_code: JSON.stringify(normalizedState),
        status: 'in-progress'
      };

      try {
        if (!window.assignmentState?.taskUserAnswers?.[taskId]) {
          window.assignmentState.taskUserAnswers[taskId] = {};
        }
        window.assignmentState.taskUserAnswers[taskId].current_code = JSON.stringify(normalizedState);

        const response = await fetch('../api/user_tasks/update.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        await response.json();
      } catch (_err) {
        // Fail silently for now; the editor remains usable locally.
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
        this.render(window.assignmentState?.currentTask || { id: taskId }, host);
      }
    },

    render(task, container) {
      this.ensureStyles();
      const state = this.getState(task.id);
      this.setState(task.id, state);
      this.seedHistory(task.id, state);
      container.innerHTML = this.buildHtml(task, state);
      this.renderInspector(task, state);
      this.attachEvents(task, container, state);
      this.renderConnections(task, container, state);
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
      const selectedAttrs = selectedNode ? selectedNode.attributes.map((attr) => attr.name || '').join('\n') : '';
      const selectedAttribute = selectedNode && this._selectedAttributePath?.nodeId === selectedNode.id
        ? selectedNode.attributes[Number(this._selectedAttributePath.index)]
        : null;
      const selectedAttributeValue = selectedAttribute ? escapeHtml(selectedAttribute.name || '') : '';
      const selectedAttributeTypeValue = selectedAttribute ? escapeHtml(selectedAttribute.type || '') : '';
      const typeOptions = getTypeSuggestions(nodes);
      const typeOptionsMarkup = typeOptions.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
      const selectedRelationLabel = selectedRelation ? escapeHtml(selectedRelation.label || '') : '';
      const selectedRelationSource = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.sourceId)) : '';
      const selectedRelationTarget = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.targetId)) : '';
      const hintBoxText = this._associationMode?.phase === 'awaiting-target' ? 'Klasse B wählen' : 'Klasse A wählen';
      const hintBoxX = Number(this._associationCursor?.x || 40);
      const hintBoxY = Number(this._associationCursor?.y || 40);
      const hintBoxMarkup = this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target'
        ? `<div class="uml-association-hint-box" style="left:${hintBoxX}px; top:${hintBoxY}px;">${hintBoxText}</div>`
        : '';
      const nodeMarkup = nodes.map((node) => {
        const nodeClass = `uml-node ${node.type === 'enum' ? 'enum' : ''} ${selectedNode && selectedNode.id === node.id ? 'selected' : ''} ${this._associationMode?.phase === 'awaiting-target' && this._associationMode.startNodeId === node.id ? 'association-target' : ''}`;
        const attrsMarkup = (node.attributes || []).map((attr, index) => {
          const attrName = typeof attr === 'string' ? attr : (attr?.name || '');
          const attrType = typeof attr === 'object' && attr?.type ? attr.type : '';
          const attrInputValue = `${attrName}${attrType ? `:${attrType}` : ''}`;
          const isEditing = this._editState?.mode === 'attribute' && this._editState.nodeId === node.id && this._editState.index === index;
          if (isEditing) {
            return `
              <div class="uml-attr-row">
                <input class="uml-inline-input" data-uml-inline-attribute="${escapeHtml(node.id)}" data-uml-attribute-index="${index}" value="${escapeHtml(attrInputValue)}" list="uml-inline-type-options" placeholder="name:String" spellcheck="false" />
              </div>
            `;
          }
          const typeSuffix = (typeof attr === 'object' && attr?.type) ? ` : ${escapeHtml(attr.type)}` : '';
          return `
            <div class="uml-attr-row">
              <div class="uml-attr-text" data-uml-edit-attribute="${escapeHtml(node.id)}:${index}">${escapeHtml(attrName)}${typeSuffix}</div>
            </div>
          `;
        }).join('');
        const stereoMarkup = node.type === 'enum' ? '<div class="uml-node-stereotype">«Enumeration»</div>' : '';
        const titleMarkup = this._editState?.mode === 'name' && this._editState.nodeId === node.id
          ? `<input class="uml-inline-input" data-uml-inline-name="${escapeHtml(node.id)}" value="${escapeHtml(node.name)}" />`
          : `<div class="uml-node-title" data-uml-edit-name="${escapeHtml(node.id)}">${escapeHtml(node.name)}</div>`;
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
          <div class="uml-toolbar">
            <button type="button" data-uml-action="add-class">+ Klasse</button>
            <button type="button" data-uml-action="add-enum">+ Enumeration</button>
            <button type="button" data-uml-action="add-association">Assoziation</button>
            <button type="button" data-uml-action="delete-selected">Löschen</button>
            <button type="button" data-uml-action="reset">Leeren</button>
            <span class="uml-shortcut-hint">F2 = neues Attribut, Shift+F2 = Literal</span>
            <span class="uml-hint">Klicken Sie zuerst auf die erste Klasse, danach auf die zweite Klasse, um eine Assoziation zu zeichnen.</span>
          </div>
          <div class="uml-workspace">
            <div class="uml-canvas-area${this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target' || this._associationMode?.phase === 'drawing' ? ' association-mode' : ''}">
              ${hintBoxMarkup}
              <svg class="uml-canvas-svg" viewBox="0 0 1000 1000" preserveAspectRatio="none"></svg>
              <div class="uml-relationship-layer"></div>
              <div class="uml-node-layer">${nodeMarkup}</div>
            </div>
          </div>
          <datalist id="uml-inline-type-options">${typeOptionsMarkup}</datalist>
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
      const selectedAttrs = selectedNode ? selectedNode.attributes.map((attr) => attr.name || '').join('\n') : '';
      const selectedAttribute = selectedNode && this._selectedAttributePath?.nodeId === selectedNode.id
        ? selectedNode.attributes[Number(this._selectedAttributePath.index)]
        : null;
      const selectedAttributeValue = selectedAttribute ? escapeHtml(selectedAttribute.name || '') : '';
      const selectedAttributeTypeValue = selectedAttribute ? escapeHtml(selectedAttribute.type || '') : '';
      const typeOptions = getTypeSuggestions(nodes);
      const typeOptionsMarkup = typeOptions.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
      const selectedRelationLabel = selectedRelation ? escapeHtml(selectedRelation.label || '') : '';
      const selectedRelationSource = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.sourceId)) : '';
      const selectedRelationTarget = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.targetId)) : '';

      outputEl.innerHTML = `
        <div class="uml-inspector">
          <div class="uml-section">
            <strong>Output / Eigenschaften</strong>
            ${this._associationMode ? `<div class="uml-hint">${this._associationMode.phase === 'awaiting-target' ? 'Jetzt klicken Sie auf die zweite Klasse.' : this._associationMode.phase === 'awaiting-source' ? 'Klicken Sie zuerst auf die erste Klasse.' : 'Beziehungsmodus aktiv.'}</div>` : ''}
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
                <input type="text" data-uml-relation-field="sourceCardinality" value="${escapeHtml(selectedRelation.sourceCardinality || '')}">
              </label>
              <label>Rechte Kardinalität
                <input type="text" data-uml-relation-field="targetCardinality" value="${escapeHtml(selectedRelation.targetCardinality || '')}">
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
              <label>Attribute (je Zeile)
                <textarea data-uml-editor-field="attributes">${selectedAttrs}</textarea>
              </label>
              <div class="uml-hint">Die Attribute werden direkt im Diagramm angezeigt. Für Datentypen nutzen Sie das Attribut-Feld im Abschnitt unten.</div>
            </div>
          ` : ''}
          ${selectedNode && selectedAttribute != null ? `
            <div class="uml-section">
              <strong>Attribut</strong>
              <label>Attributname
                <input type="text" data-uml-attribute-field="name" value="${selectedAttributeValue}" placeholder="z. B. Nachname">
              </label>
              <label>Datentyp
                <input type="text" data-uml-attribute-field="type" value="${selectedAttributeTypeValue}" list="uml-type-options" placeholder="z. B. String" spellcheck="false">
                <datalist id="uml-type-options">${typeOptionsMarkup}</datalist>
              </label>
              <div class="uml-hint">Wählen Sie einen Datentyp aus der Liste oder geben Sie einen eigenen ein.</div>
            </div>
          ` : ''}
        </div>
      `;
    },

    attachEvents(task, container, state) {
      const self = this;

      container.querySelectorAll('[data-uml-action]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = button.getAttribute('data-uml-action');
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
          if (event.target.closest('input, textarea, select, [data-uml-edit-name], [data-uml-edit-attribute], [data-uml-add-attribute]')) return;
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
          self.render(task, container);
          event.preventDefault();
        });
        container.dataset.umlNodeDelegationBound = '1';
      }

      const inspectorHosts = [container, document.getElementById('output-container')].filter(Boolean);
      inspectorHosts.forEach((host) => {
        host.querySelectorAll('[data-uml-editor-field]').forEach((input) => {
          input.addEventListener('input', () => {
            self.updateSelectedNodeFromInspector(task.id, container);
          });
          input.addEventListener('change', () => {
            self.updateSelectedNodeFromInspector(task.id, container);
          });
        });

        host.querySelectorAll('[data-uml-attribute-field]').forEach((input) => {
          input.addEventListener('input', () => {
            self.updateSelectedAttributeFromInspector(task.id, container);
          });
          input.addEventListener('focus', () => {
            if (input.getAttribute('data-uml-attribute-field') === 'type') {
              input.setAttribute('list', 'uml-type-options');
            }
          });
          input.addEventListener('click', () => {
            if (input.getAttribute('data-uml-attribute-field') === 'type') {
              input.setAttribute('list', 'uml-type-options');
            }
          });
          input.addEventListener('change', () => {
            self.updateSelectedAttributeFromInspector(task.id, container);
          });
        });

        host.querySelectorAll('[data-uml-relation-field]').forEach((input) => {
          input.addEventListener('input', () => {
            self.updateSelectedRelationFromInspector(task.id, container);
          });
          input.addEventListener('change', () => {
            self.updateSelectedRelationFromInspector(task.id, container);
          });
        });
      });

      container.querySelectorAll('[data-uml-edit-name]').forEach((element) => {
        element.addEventListener('click', (event) => {
          event.stopPropagation();
          const nodeId = element.getAttribute('data-uml-edit-name');
          self._selectedNodeId = nodeId;
          self._selectedRelationId = null;
          self._selectedAttributePath = null;
          self.render(window.assignmentState?.currentTask || { id: task.id }, container);
        });
        element.addEventListener('dblclick', (event) => {
          event.stopPropagation();
          self.beginInlineEdit(task.id, 'name', element.getAttribute('data-uml-edit-name'), null, container);
        });
      });

      container.querySelectorAll('[data-uml-edit-attribute]').forEach((element) => {
        element.addEventListener('click', (event) => {
          event.stopPropagation();
          const parts = element.getAttribute('data-uml-edit-attribute').split(':');
          self._selectedNodeId = parts[0];
          self._selectedRelationId = null;
          self._selectedAttributePath = { nodeId: parts[0], index: Number(parts[1] || 0) };
          self.render(window.assignmentState?.currentTask || { id: task.id }, container);
        });
        element.addEventListener('dblclick', (event) => {
          event.stopPropagation();
          const parts = element.getAttribute('data-uml-edit-attribute').split(':');
          self.beginInlineEdit(task.id, 'attribute', parts[0], Number(parts[1] || 0), container);
        });
      });

      container.querySelectorAll('[data-uml-add-attribute]').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.stopPropagation();
          self.addAttribute(task.id, button.getAttribute('data-uml-add-attribute'), container);
        });
      });

      container.querySelectorAll('[data-uml-inline-name], [data-uml-inline-attribute]').forEach((input) => {
        input.addEventListener('input', () => {
          input.setAttribute('list', 'uml-inline-type-options');
          const value = input.value || '';
          const colonIndex = value.indexOf(':');
          if (colonIndex >= 0) {
            const partialType = value.slice(colonIndex + 1).trim();
            if (partialType) {
              const suggestion = getTypeSuggestions().find((option) => option.toLowerCase().startsWith(partialType.toLowerCase()));
              if (suggestion && suggestion.toLowerCase() !== partialType.toLowerCase()) {
                const completedValue = `${value.slice(0, colonIndex + 1)}${suggestion}`;
                input.value = completedValue;
                const caretPosition = completedValue.length;
                input.setSelectionRange(caretPosition, caretPosition);
              }
            }
          }
        });
        input.addEventListener('focus', () => {
          input.setAttribute('list', 'uml-inline-type-options');
        });
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
          setTimeout(() => self.commitInlineEdit(task.id, container), 0);
        });
      });

      if (!container.dataset.umlBound) {
        document.addEventListener('click', (event) => {
          const undoBtn = event.target.closest('#undo-btn');
          const redoBtn = event.target.closest('#redo-btn');
          if (!undoBtn && !redoBtn) return;
          if (window.assignmentState?.currentTask?.task_type !== 'uml') return;
          event.preventDefault();
          event.stopPropagation();
          if (undoBtn) {
            self.undo(window.assignmentState.currentTask.id, container);
          } else if (redoBtn) {
            self.redo(window.assignmentState.currentTask.id, container);
          }
        }, true);
        document.addEventListener('keydown', (event) => {
          const isDelete = event.key === 'Delete' || event.key === 'Backspace';
          if (isDelete && !event.target.closest('input, textarea, select')) {
            event.preventDefault();
            self.handleDeleteSelection(task.id, container);
          }
        });
        document.addEventListener('keydown', (event) => {
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
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
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

    beginInlineEdit(taskId, mode, nodeId, index, container) {
      this._editState = { mode, nodeId, index };
      if (mode === 'name' && nodeId) {
        this._selectedNodeId = nodeId;
        this._selectedRelationId = null;
        this._selectedAttributePath = null;
      }
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      setTimeout(() => {
        const input = container.querySelector('[data-uml-inline-name], [data-uml-inline-attribute]');
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
        const input = container.querySelector(`[data-uml-inline-attribute="${editState.nodeId}"]`);
        const value = (input?.value || '').trim();
        const colonIndex = value.indexOf(':');
        const name = colonIndex >= 0 ? value.slice(0, colonIndex).trim() : value;
        const previousType = node.attributes[editState.index]?.type || '';
        const type = colonIndex >= 0 ? value.slice(colonIndex + 1).trim() : previousType;
        if (name) {
          node.attributes[editState.index] = { name, type };
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
      node.attributes.push({ name: '', type: '' });
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
      selectedNode.attributes.push({ name: value, type: '' });
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
        id: `uml-rel-${Date.now()}`,
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
      if (this._selectedAttributePath && selectedNode && this._selectedAttributePath.nodeId === selectedNode.id) {
        const index = Number(this._selectedAttributePath.index);
        if (!Number.isNaN(index)) {
          selectedNode.attributes = selectedNode.attributes.filter((_, attributeIndex) => attributeIndex !== index);
          this._selectedAttributePath = null;
        }
      } else if (this._selectedRelationId) {
        state.relationships = state.relationships.filter((relation) => relation.id !== this._selectedRelationId);
        this._selectedRelationId = null;
      } else if (this._selectedNodeId) {
        state.nodes = state.nodes.filter((node) => node.id !== this._selectedNodeId);
        state.relationships = state.relationships.filter((relation) => relation.sourceId !== this._selectedNodeId && relation.targetId !== this._selectedNodeId);
        this._selectedNodeId = null;
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
    },

    handleAction(taskId, action, container) {
      if (!taskId) return;

      if (action === 'add-class') {
        const state = this.getState(taskId);
        const nextIndex = state.nodes.length;
        const node = createNode('class', nextIndex);
        state.nodes.push(node);
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.beginInlineEdit(taskId, 'name', node.id, null, container);
      } else if (action === 'add-enum') {
        const state = this.getState(taskId);
        const nextIndex = state.nodes.length;
        const node = createNode('enum', nextIndex);
        state.nodes.push(node);
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.beginInlineEdit(taskId, 'name', node.id, null, container);
      } else if (action === 'add-association') {
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
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      }
    },

    updateSelectedNodeFromInspector(taskId, container) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode) return;
      const inspectorHost = document.getElementById('output-container') || container;
      const typeInput = inspectorHost.querySelector('[data-uml-editor-field="type"]');
      const nameInput = inspectorHost.querySelector('[data-uml-editor-field="name"]');
      const attributesInput = inspectorHost.querySelector('[data-uml-editor-field="attributes"]');
      if (typeInput) {
        selectedNode.type = typeInput.value === 'enum' ? 'enum' : 'class';
      }
      if (nameInput) {
        selectedNode.name = nameInput.value.trim() || (selectedNode.type === 'enum' ? 'Enum' : 'Klasse');
      }
      if (attributesInput) {
        selectedNode.attributes = attributesInput.value
          .split('\n')
          .map((attr) => attr.trim())
          .map((attr) => {
            const parts = attr.split(':');
            const name = parts.shift()?.trim() || '';
            const type = parts.join(':').trim();
            return name ? { name, type } : null;
          })
          .filter(Boolean);
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
    },

    updateSelectedRelationFromInspector(taskId, container) {
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
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
    },

    updateSelectedAttributeFromInspector(taskId, container) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode || !this._selectedAttributePath || this._selectedAttributePath.nodeId !== selectedNode.id) return;
      const inspectorHost = document.getElementById('output-container') || container;
      const nameInput = inspectorHost.querySelector('[data-uml-attribute-field="name"]');
      const typeInput = inspectorHost.querySelector('[data-uml-attribute-field="type"]');
      const name = (nameInput?.value || '').trim();
      const type = (typeInput?.value || '').trim();
      const index = Number(this._selectedAttributePath.index);
      if (Number.isNaN(index)) return;
      if (!name && !type) {
        selectedNode.attributes = selectedNode.attributes.filter((_, attributeIndex) => attributeIndex !== index);
      } else if (name) {
        selectedNode.attributes[index] = { name, type };
      } else {
        selectedNode.attributes = selectedNode.attributes.filter((_, attributeIndex) => attributeIndex !== index);
      }
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
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
        line.style.top = `${startPoint.y}px`;
        line.style.width = `${Math.max(28, distance)}px`;
        line.style.transform = `rotate(${angle}deg)`;
        line.style.padding = '0';
        line.style.margin = '0';
        line.style.minHeight = '2px';
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
      });
    }
  };
})();
