(function () {
  const UML_VERSION = 3;
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
    const rawPrimitiveTypes = Array.isArray(config?.primitive_types)
      ? config.primitive_types
      : config?.simple_types;
    const configuredTypes = Array.isArray(rawPrimitiveTypes)
      ? rawPrimitiveTypes.map((type) => String(type || '').trim()).filter(Boolean)
      : SIMPLE_TYPE_OPTIONS;
    const legacyDefaults = !config
      || (typeof config.primitive_types_enabled !== 'boolean'
        && typeof config.complex_types_enabled !== 'boolean'
        && typeof config.enumeration_types_enabled !== 'boolean');
    const parsedLength = Number.parseInt(config?.string_length, 10);
    return {
      simpleTypes: configuredTypes,
      stringLength: Number.isInteger(parsedLength) && parsedLength > 0 ? parsedLength : 30,
      primitiveTypesEnabled: typeof config?.primitive_types_enabled === 'boolean'
        ? config.primitive_types_enabled
        : legacyDefaults,
      complexTypesEnabled: typeof config?.complex_types_enabled === 'boolean'
        ? config.complex_types_enabled
        : legacyDefaults,
      enumerationTypesEnabled: typeof config?.enumeration_types_enabled === 'boolean'
        ? config.enumeration_types_enabled
        : legacyDefaults,
      methodsEnabled: config?.methods_enabled === true,
      structuresEnabled: task?.umlProject === true || config?.structures_enabled === true,
      customSimpleTypesEnabled: task?.umlProject === true || config?.custom_simple_types_enabled === true,
      generalizationEnabled: task?.umlProject === true || config?.generalization_enabled === true
    };
  }

  function getTypeSuggestions(nodes = [], task = null) {
    const typeConfig = getUmlTypeConfig(task);
    const baseSuggestions = typeConfig.primitiveTypesEnabled ? typeConfig.simpleTypes : [];
    const nodeSuggestions = Array.isArray(nodes) ? nodes
      .filter((node) => (
        (typeConfig.complexTypesEnabled && (node?.type === 'class' || node?.type === 'structure'))
        || (typeConfig.customSimpleTypesEnabled && node?.type === 'primitive')
        || (typeConfig.enumerationTypesEnabled && node?.type === 'enum')
      ))
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
      return { id, name: derived ? value.slice(1).trim() : value, type: '', stringLength: null, defaultValue: '', derived };
    }
    if (rawAttribute && typeof rawAttribute === 'object') {
      const rawName = String(rawAttribute.name || rawAttribute.value || '').trim();
      const derived = Boolean(rawAttribute.derived) || rawName.startsWith('/');
      const name = derived && rawName.startsWith('/') ? rawName.slice(1).trim() : rawName;
      let type = String(rawAttribute.type || rawAttribute.datatype || '').trim();
      const stringType = type.match(/^String(?:\s*\(\s*(\d+)\s*\))?$/i);
      const configuredLength = Number.parseInt(rawAttribute.stringLength ?? rawAttribute.string_length, 10);
      const stringLength = stringType
        ? (Number.isInteger(configuredLength) && configuredLength > 0
          ? configuredLength
          : (stringType[1] ? Number.parseInt(stringType[1], 10) : null))
        : null;
      if (stringType) type = 'String';
      return {
        id,
        name,
        type,
        stringLength,
        defaultValue: String(rawAttribute.defaultValue ?? rawAttribute.default_value ?? ''),
        derived
      };
    }
    return { id, name: '', type: '', stringLength: null, defaultValue: '', derived: false };
  }

  function normalizeMethod(rawMethod, nodeIndex, index) {
    const method = rawMethod && typeof rawMethod === 'object' ? rawMethod : {};
    return {
      id: String(method.id || `uml-method-${nodeIndex + 1}-${index + 1}`),
      name: String(method.name || '').trim(),
      parameters: String(method.parameters || '').trim(),
      returnType: String(method.returnType || method.return_type || 'void').trim() || 'void'
    };
  }

  function formatMethodSignature(method) {
    const name = String(method?.name || '').trim() || 'methode';
    return `${name}(${getMethodParameters(method).map((parameter) => parameter.name).join(', ')})`;
  }

  function getMethodParameters(method) {
    return String(method?.parameters || '').split(',').map((value) => {
      const separator = value.indexOf(':');
      return {
        name: (separator < 0 ? value : value.slice(0, separator)).trim(),
        type: separator < 0 ? '' : value.slice(separator + 1).trim()
      };
    }).filter((parameter) => parameter.name || parameter.type);
  }

  function getMethodParameterMarkup(parameter, typeOptions) {
    return `
      <div class="uml-method-parameter" data-uml-method-parameter>
        <label>Parametername
          <input type="text" data-uml-method-parameter-field="name" value="${escapeHtml(parameter.name)}" placeholder="z. B. menge">
        </label>
        <label>Datentyp
          ${getTypeComboMarkup(parameter.type, typeOptions, 'data-uml-method-parameter-field="type" placeholder="z. B. Integer"')}
        </label>
        <button type="button" data-uml-remove-parameter aria-label="Parameter löschen" title="Parameter löschen"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></button>
      </div>
    `;
  }

  function getNodeBodyMarkup(node, attributesMarkup, methodsMarkup, methodsEnabled) {
    const addLabel = node.type === 'enum' ? '+ Wert' : '+ Attribut';
    return `
      ${node.type !== 'primitive' ? `<div class="uml-attributes-compartment">${attributesMarkup || '<div class="uml-node-attr">(keine Attribute)</div>'}</div>` : ''}
      ${methodsMarkup ? `<div class="uml-methods-compartment">${methodsMarkup}</div>` : ''}
      ${node.type !== 'primitive' ? `<div class="uml-node-actions">
        <button type="button" class="uml-inline-add-btn" data-uml-add-attribute="${escapeHtml(node.id)}">${addLabel}</button>
        ${methodsEnabled && node.type === 'class' ? `<button type="button" class="uml-inline-add-btn" data-uml-add-method="${escapeHtml(node.id)}">+ Methode</button>` : ''}
      </div>` : ''}
    `;
  }

  function getStringLengthDefault(task) {
    const config = getUmlTypeConfig(task);
    return config.stringLength;
  }

  function getAttributeTypeLabel(attribute) {
    const type = String(attribute?.type || '').trim();
    if (type.toLocaleLowerCase() !== 'string') return type;
    const length = Number.parseInt(attribute?.stringLength, 10);
    return Number.isInteger(length) && length > 0 ? `String(${length})` : 'String';
  }

  function getAttributeDefaultLabel(attribute) {
    const value = String(attribute?.defaultValue ?? '');
    return value ? ` = ${value}` : '';
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
        type: ['enum', 'structure', 'primitive'].includes(node?.type) ? node.type : 'class',
        abstract: node?.abstract === true,
        name: String(node?.name || (node?.type === 'enum' ? 'Enum' : 'Klasse')),
        attributes: Array.isArray(node?.attributes) ? node.attributes.map((attr, attributeIndex) => normalizeAttribute(attr, index, attributeIndex)).filter((attr) => attr.name || attr.type) : [],
        methods: Array.isArray(node?.methods) ? node.methods.map((method, methodIndex) => normalizeMethod(method, index, methodIndex)).filter((method) => method.name) : [],
        x: Number(node?.x) || 80 + (index * 40),
        y: Number(node?.y) || 80 + (index * 40)
      })),
      relationships: relationships.map((relation, index) => ({
        id: String(relation?.id || `uml-relationship-${index + 1}`),
        sourceId: String(relation?.sourceId || ''),
        targetId: String(relation?.targetId || ''),
        type: relation?.type === 'generalization' ? 'generalization' : 'association',
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
      type: ['enum', 'structure', 'primitive'].includes(type) ? type : 'class',
      name: type === 'enum' ? 'Enum' : type === 'structure' ? 'StrukturT' : type === 'primitive' ? 'NeuerTyp' : 'Klasse',
      abstract: false,
      attributes: type === 'enum'
        ? [{ id: createAttributeId(), name: 'WERT_A', type: '', derived: false }, { id: createAttributeId(), name: 'WERT_B', type: '', derived: false }]
        : [],
      methods: [],
      x: position.x,
      y: position.y
    };
  }

  window.UmlRenderer = {
    _stateCache: {},
    _renderTasks: {},
    _saveStatusCache: {},
    _associationMode: null,
    _connectionType: 'association',
    _associationPreview: null,
    _associationDrag: null,
    _associationCursor: null,
    _dragState: null,
    _relationHandleDrag: null,
    _selectedNodeId: null,
    _selectedRelationId: null,
    _selectedAttributePath: null,
    _selectedMethodPath: null,
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
          gap: 6px;
          align-items: center;
          padding: 6px;
          border: 1px solid var(--border);
          border-radius: 10px;
          background: var(--bg);
        }
        .uml-toolbar button {
          width: 34px;
          height: 34px;
          padding: 0;
          border: 1px solid var(--border);
          border-radius: 8px;
          background: var(--bg-secondary);
          color: var(--text-primary);
          cursor: pointer;
          font-size: 17px;
          line-height: 1;
        }
        .uml-toolbar button svg {
          width: 18px;
          height: 18px;
          fill: none;
          stroke: currentColor;
          stroke-width: 1.8;
          stroke-linecap: round;
          stroke-linejoin: round;
        }
        .uml-toolbar button[data-uml-action="add-class"] svg .uml-class-header {
          fill: #2563eb;
          stroke: #2563eb;
        }
        .uml-toolbar button[data-uml-action="add-class"] svg .uml-class-header-line {
          stroke: #ffffff;
        }
        .uml-toolbar button[data-uml-action="add-enum"] svg .uml-enum-letter {
          fill: currentColor;
          stroke: none;
        }
        .uml-toolbar button:disabled {
          opacity: 0.4;
          cursor: not-allowed;
          border-color: var(--border);
          color: var(--text-secondary);
        }
        .uml-toolbar button:hover {
          border-color: var(--accent);
          color: var(--accent);
        }
        .uml-toolbar button[data-uml-action="delete-selected"] {
          margin-left: auto;
        }
        .uml-toolbar button.active {
          background: var(--accent);
          color: white;
          border-color: var(--accent);
        }
        .uml-context-menu {
          position: fixed;
          z-index: 9999;
          min-width: 190px;
          padding: 5px;
          border: 1px solid var(--border);
          border-radius: 9px;
          background: var(--bg);
          box-shadow: 0 6px 18px rgba(0, 0, 0, 0.16);
        }
        .uml-context-menu button {
          display: block;
          width: 100%;
          padding: 7px 9px;
          border: 0;
          border-radius: 5px;
          background: transparent;
          color: var(--text-primary);
          text-align: left;
          cursor: pointer;
          font: inherit;
        }
        .uml-context-menu button svg {
          width: 16px;
          height: 16px;
          margin-right: 7px;
          vertical-align: middle;
          fill: none;
          stroke: currentColor;
          stroke-width: 1.8;
          stroke-linecap: round;
          stroke-linejoin: round;
        }
        .uml-context-menu button:disabled {
          opacity: 0.45;
          cursor: not-allowed;
        }
        .uml-context-menu button:hover {
          background: var(--bg-secondary);
          color: var(--accent);
        }
        .uml-shortcut-hint {
          font-size: 12px;
          color: var(--text-secondary);
          padding: 0 4px;
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
          color: #334155;
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
        .uml-node.structure {
          border-color: #0891b2;
        }
        .uml-node.primitive {
          border-color: #64748b;
          min-height: 60px;
        }
        .uml-node.abstract .uml-node-title {
          font-style: italic;
        }
        .uml-generalization-arrow {
          position: absolute;
          right: 0;
          top: -2px;
          width: 18px;
          height: 16px;
          fill: white;
          stroke: #64748b;
          stroke-width: 1.5;
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
          color: #334155;
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
        .uml-method-text {
          flex: 1;
          min-height: 18px;
          padding: 1px 4px;
          margin: -1px -4px;
          border-radius: 4px;
          font-family: ui-monospace, monospace;
          cursor: pointer;
        }
        .uml-method-text.selected {
          background: #ede9fe;
          outline: 1px solid #a78bfa;
          color: #4c1d95;
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
          border-top: 1px solid #2563eb;
          font-size: 13px;
          color: #334155;
          display: flex;
          flex-direction: column;
          flex: 1;
          justify-content: flex-start;
        }
        .uml-attributes-compartment,
        .uml-methods-compartment {
          padding: 8px 10px;
          display: flex;
          flex-direction: column;
          gap: 4px;
        }
        .uml-methods-compartment {
          border-top: 1px solid #2563eb;
        }
        .uml-node-actions {
          display: flex;
          gap: 6px;
          padding: 0 10px 8px;
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
          font-family: ui-monospace, monospace;
        }
        .uml-inspector {
          border: 1px solid var(--border);
          border-radius: 10px;
          padding: 8px;
          background: var(--bg);
          display: flex;
          flex-direction: column;
          gap: 6px;
          font-family: system-ui, sans-serif;
          font-size: 12px;
          white-space: normal;
          line-height: 1.35;
        }
        .uml-inspector input,
        .uml-inspector select,
        .uml-inspector textarea {
          width: 100%;
          box-sizing: border-box;
          border: 1px solid var(--border);
          border-radius: 6px;
          padding: 5px 7px;
          font: inherit;
        }
        .uml-inspector input[type="checkbox"] {
          width: auto;
        }
        .uml-inspector textarea {
          min-height: 72px;
          resize: vertical;
        }
        .uml-inspector .uml-section {
          border: 1px solid var(--border);
          border-radius: 8px;
          padding: 7px;
          display: flex;
          flex-direction: column;
          gap: 5px;
          background: var(--bg-secondary);
        }
        .uml-method-parameter {
          display: grid;
          grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
          gap: 5px;
          align-items: end;
          padding: 6px 0;
          border-bottom: 1px solid var(--border);
        }
        .uml-method-parameter label {
          min-width: 0;
        }
        .uml-method-parameter button[data-uml-remove-parameter] {
          display: flex;
          align-items: center;
          padding: 6px;
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
      if (container.dataset.umlTaskId && container.dataset.umlTaskId !== String(task.id)) {
        this._connectionType = 'association';
        this._associationMode = { phase: 'ready', points: [], startNodeId: null };
        this._associationHint = '';
        this._editState = null;
      }
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
            <div class="uml-attr-text ${this._selectedAttributePath?.nodeId === nodeId && Number(this._selectedAttributePath.index) === index ? 'selected' : ''}" data-uml-edit-attribute="${escapeHtml(nodeId)}:${index}" title="Klicken zum Auswählen, Doppelklick zum Bearbeiten" aria-selected="${this._selectedAttributePath?.nodeId === nodeId && Number(this._selectedAttributePath.index) === index}">${attribute.derived ? '/' : ''}${escapeHtml(attribute.name)}${getAttributeTypeLabel(attribute) ? ` : ${escapeHtml(getAttributeTypeLabel(attribute))}` : ''}${escapeHtml(getAttributeDefaultLabel(attribute))}</div>
          </div>
        `).join('');
        const methodsEnabled = getUmlTypeConfig(this.getRenderTask(taskId)).methodsEnabled;
        const methodsMarkup = methodsEnabled && node.type === 'class'
          ? node.methods.map((method, index) => `
            <div class="uml-method-text ${this._selectedMethodPath?.nodeId === nodeId && Number(this._selectedMethodPath.index) === index ? 'selected' : ''}" data-uml-edit-method="${escapeHtml(nodeId)}:${index}" title="Klicken für Datentypen, Doppelklick für Namen und Parameter" aria-selected="${this._selectedMethodPath?.nodeId === nodeId && Number(this._selectedMethodPath.index) === index}">${escapeHtml(formatMethodSignature(method))}</div>
          `).join('')
          : '';
        body.innerHTML = getNodeBodyMarkup(node, attributesMarkup, methodsMarkup, methodsEnabled);
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
      const nodes = Array.isArray(state.nodes) ? state.nodes : [];
      const relationships = Array.isArray(state.relationships) ? state.relationships : [];
      const selectedNode = nodes.find((node) => node.id === this._selectedNodeId) || null;
      const selectedRelation = (state.relationships || []).find((relation) => relation.id === this._selectedRelationId) || null;
      const selectedAttributeIndex = Number(this._selectedAttributePath?.index);
      const selectedAttribute = selectedNode
        && this._selectedAttributePath?.nodeId === selectedNode.id
        && Number.isInteger(selectedAttributeIndex)
        ? selectedNode.attributes[selectedAttributeIndex]
        : null;
      const selectedMethodIndex = Number(this._selectedMethodPath?.index);
      const selectedMethod = selectedNode
        && this._selectedMethodPath?.nodeId === selectedNode.id
        && Number.isInteger(selectedMethodIndex)
        ? selectedNode.methods[selectedMethodIndex]
        : null;
      const canDeleteSelection = Boolean(selectedRelation || selectedAttribute || selectedMethod || selectedNode);
      const deleteDisabled = canDeleteSelection ? '' : ' disabled aria-disabled="true"';
      const selectedNodeName = selectedNode ? escapeHtml(selectedNode.name) : '';
      const selectedNodeType = selectedNode ? selectedNode.type : 'class';
      const selectedAttributeValue = selectedAttribute ? escapeHtml(selectedAttribute.name || '') : '';
      const typeConfig = getUmlTypeConfig(task);
      const methodsEnabled = typeConfig.methodsEnabled;
      const selectedRelationLabel = selectedRelation ? escapeHtml(selectedRelation.label || '') : '';
      const selectedRelationSource = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.sourceId)) : '';
      const selectedRelationTarget = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.targetId)) : '';
      const contextMenuMarkup = `
        <div id="uml-context-menu" class="uml-context-menu" role="menu" style="display:none;">
          <button type="button" role="menuitem" data-uml-context-action="add-class"><span aria-hidden="true">▣</span> Klasse hinzufügen</button>
          <button type="button" role="menuitem" data-uml-context-action="add-enum"><span aria-hidden="true">≡</span> Enumeration hinzufügen</button>
          ${typeConfig.structuresEnabled ? '<button type="button" role="menuitem" data-uml-context-action="add-structure">«S» Struktur hinzufügen</button>' : ''}
          ${typeConfig.customSimpleTypesEnabled ? '<button type="button" role="menuitem" data-uml-context-action="add-primitive">«P» Einfachen Datentyp hinzufügen</button>' : ''}
          <button type="button" role="menuitem" data-uml-context-action="add-association"><span aria-hidden="true">─</span> Assoziation</button>
          ${typeConfig.generalizationEnabled ? '<button type="button" role="menuitem" data-uml-context-action="add-generalization">△ Generalisierung</button>' : ''}
          <button type="button" role="menuitem" data-uml-context-action="delete-selected"${deleteDisabled}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg> Auswahl löschen</button>
          <button type="button" role="menuitem" data-uml-context-action="reset"><span aria-hidden="true">⟲</span> Diagramm leeren</button>
          ${task.umlProject ? '<button type="button" role="menuitem" data-uml-context-action="save"><span aria-hidden="true">💾</span> Speichern</button>' : ''}
        </div>
      `;
      const hintBoxText = this._associationHint || (this._associationMode?.phase === 'awaiting-target' ? 'Klasse B wählen' : 'Klasse A wählen');
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
        const nodeClass = `uml-node ${node.type !== 'class' ? node.type : ''} ${node.abstract && node.type === 'class' ? 'abstract' : ''} ${selectedNode && selectedNode.id === node.id ? 'selected' : ''} ${this._associationMode?.phase === 'awaiting-target' && this._associationMode.startNodeId === node.id ? 'association-target' : ''}`;
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
          const typeLabel = getAttributeTypeLabel(attr);
          const typeSuffix = typeLabel ? ` : ${escapeHtml(typeLabel)}` : '';
          const derivedPrefix = typeof attr === 'object' && attr?.derived ? '/' : '';
          const defaultSuffix = getAttributeDefaultLabel(attr);
          const isSelectedAttribute = this._selectedAttributePath?.nodeId === node.id
            && Number(this._selectedAttributePath.index) === index;
          return `
            <div class="uml-attr-row">
              <div class="uml-attr-text ${isSelectedAttribute ? 'selected' : ''}" data-uml-edit-attribute="${escapeHtml(node.id)}:${index}" title="Klicken zum Auswählen, Doppelklick zum Bearbeiten" aria-selected="${isSelectedAttribute}">${derivedPrefix}${escapeHtml(attrName)}${typeSuffix}${escapeHtml(defaultSuffix)}</div>
            </div>
          `;
        }).join('');
        const methodsMarkup = methodsEnabled && node.type === 'class'
          ? (node.methods || []).map((method, index) => {
            if (this._editState?.mode === 'method' && this._editState.nodeId === node.id && this._editState.index === index) {
              return `<input class="uml-inline-input" data-uml-inline-method="${escapeHtml(node.id)}" value="${escapeHtml(formatMethodSignature(method))}" placeholder="methode(parameter1, parameter2)" aria-label="Methodenname und Parameternamen" spellcheck="false">`;
            }
            const isSelectedMethod = this._selectedMethodPath?.nodeId === node.id
              && Number(this._selectedMethodPath.index) === index;
            return `
              <div class="uml-method-text ${isSelectedMethod ? 'selected' : ''}" data-uml-edit-method="${escapeHtml(node.id)}:${index}" title="Klicken für Datentypen, Doppelklick für Namen und Parameter" aria-selected="${isSelectedMethod}">${escapeHtml(formatMethodSignature(method))}</div>
            `;
          }).join('')
          : '';
        const stereotype = { enum: 'Enumeration', structure: 'datatype', primitive: 'primitive' }[node.type];
        const stereoMarkup = stereotype ? `<div class="uml-node-stereotype">«${stereotype}»</div>` : '';
        const titleMarkup = this._editState?.mode === 'name' && this._editState.nodeId === node.id
          ? `<input class="uml-inline-input" data-uml-inline-name="${escapeHtml(node.id)}" value="${escapeHtml(node.name)}" />`
          : `<div class="uml-node-title" data-uml-edit-name="${escapeHtml(node.id)}" title="Doppelklicken zum Bearbeiten">${escapeHtml(node.name)}</div>`;
        return `
          <div class="${nodeClass}" data-uml-node-id="${escapeHtml(node.id)}" style="left:${Number(node.x || 0)}px; top:${Number(node.y || 0)}px;">
            ${stereoMarkup}
            ${titleMarkup}
            <div class="uml-node-body">
              ${getNodeBodyMarkup(node, attrsMarkup, methodsMarkup, methodsEnabled)}
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
            <button type="button" data-uml-action="add-class" aria-label="Klasse hinzufügen" title="Klasse hinzufügen"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><rect class="uml-class-header" x="4" y="4" width="16" height="5"/><path class="uml-class-header-line" d="M4 9h16M8 13h8M8 17h5"/></svg></button>
            <button type="button" data-uml-action="add-enum" aria-label="Enumeration hinzufügen" title="Enumeration hinzufügen"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16"/><text class="uml-enum-letter" x="12" y="19" text-anchor="middle" font-family="system-ui, sans-serif" font-size="13" font-weight="700">E</text></svg></button>
            ${typeConfig.structuresEnabled ? '<button type="button" data-uml-action="add-structure" aria-label="Struktur hinzufügen" title="Struktur hinzufügen"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16M8 13h8M8 17h8"/><text x="12" y="8" text-anchor="middle" font-size="5" fill="currentColor" stroke="none">«S»</text></svg></button>' : ''}
            ${typeConfig.customSimpleTypesEnabled ? '<button type="button" data-uml-action="add-primitive" aria-label="Einfachen Datentyp hinzufügen" title="Einfachen Datentyp hinzufügen"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16M9 17v-5h3a2 2 0 0 1 0 4H9"/></svg></button>' : ''}
            <button type="button" data-uml-action="add-association" aria-label="Assoziation erstellen" title="Assoziation erstellen"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><path d="M7 12h10"/></svg></button>
            ${typeConfig.generalizationEnabled ? '<button type="button" data-uml-action="add-generalization" aria-label="Generalisierung erstellen" title="Generalisierung: Unterklasse, dann Superklasse wählen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V10M5 10l7-7 7 7z"/></svg></button>' : ''}
            <button type="button" data-uml-action="delete-selected" aria-label="Auswahl löschen" title="Auswahl löschen"${deleteDisabled}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></button>
            <button type="button" data-uml-action="reset" aria-label="Diagramm leeren" title="Diagramm leeren"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.5"/><path d="M4 4v4.5h4.5"/></svg></button>
            ${task.umlProject ? '<button type="button" data-uml-action="save" aria-label="Diagramm speichern" title="Diagramm speichern"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg></button>' : ''}
            <span class="uml-shortcut-hint">Rechtsklick für Aktionen · Entf löscht · Doppelklick bearbeitet · F2 fügt Attribut/Wert hinzu</span>
          </div>
          <div class="uml-hint">Attribut: name:Datentyp · Beziehung: Bezeichner und Kardinalitäten im Eigenschaftenbereich festlegen.</div>
          <div class="uml-workspace">
            <div class="uml-canvas-area${this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target' || this._associationMode?.phase === 'drawing' ? ' association-mode' : ''}">
              ${hintBoxMarkup}
              <svg class="uml-canvas-svg" viewBox="0 0 1000 1000" preserveAspectRatio="none"></svg>
              <div class="uml-relationship-layer"></div>
              <div class="uml-node-layer">${nodeMarkup}</div>
            </div>
          </div>
          <div id="quiz-feedback-${task.id}" class="quiz-feedback" role="status" aria-live="polite"></div>
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
      const selectedMethod = selectedNode && this._selectedMethodPath?.nodeId === selectedNode.id
        ? selectedNode.methods[Number(this._selectedMethodPath.index)]
        : null;
      const selectedAttributeValue = selectedAttribute ? escapeHtml(selectedAttribute.name || '') : '';
      const typeOptions = getTypeSuggestions(nodes, task);
      const selectedRelationLabel = selectedRelation ? escapeHtml(selectedRelation.label || '') : '';
      const selectedRelationSource = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.sourceId)) : '';
      const selectedRelationTarget = selectedRelation ? escapeHtml(this.getNodeNameById(state, selectedRelation.targetId)) : '';

      const typeConfig = getUmlTypeConfig(task);
      const isSuperclass = selectedNode?.type === 'class' && relationships.some((relation) => (
        relation.type === 'generalization' && relation.targetId === selectedNode.id
      ));
      outputEl.innerHTML = `
        <div class="uml-inspector">
          <div class="uml-section">
            <strong>Output / Eigenschaften</strong>
            ${this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target' ? `<div class="uml-hint">${escapeHtml(this._associationHint || 'Klasse wählen.')} Esc bricht ab.</div>` : ''}
            ${selectedNode || selectedRelation ? '' : '<div class="uml-hint">Wählen Sie eine Tabelle oder Beziehung, um die Eigenschaften hier zu bearbeiten.</div>'}
          </div>
          ${selectedRelation ? `
            <div class="uml-section">
              <strong>${selectedRelation.type === 'generalization' ? 'Generalisierung' : 'Beziehung'}</strong>
              <div>${selectedRelationSource} → ${selectedRelationTarget}</div>
              ${selectedRelation.type === 'generalization' ? '<div class="uml-hint">Unterklasse → Superklasse · hohles Dreieck an der Superklasse</div>' : `
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
              `}
            </div>
          ` : ''}
          ${selectedNode ? `
            <div class="uml-section">
              <strong>Klasseneigenschaften</strong>
              <label>Typ
                <select data-uml-editor-field="type">
                  <option value="class" ${selectedNodeType === 'class' ? 'selected' : ''}>Klasse</option>
                  <option value="enum" ${selectedNodeType === 'enum' ? 'selected' : ''}>Enumeration</option>
                  ${typeConfig.structuresEnabled || selectedNodeType === 'structure' ? `<option value="structure" ${selectedNodeType === 'structure' ? 'selected' : ''}>Struktur</option>` : ''}
                  ${typeConfig.customSimpleTypesEnabled || selectedNodeType === 'primitive' ? `<option value="primitive" ${selectedNodeType === 'primitive' ? 'selected' : ''}>Einfacher Datentyp</option>` : ''}
                </select>
              </label>
              ${isSuperclass && typeConfig.generalizationEnabled ? `<label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" data-uml-editor-field="abstract" ${selectedNode.abstract ? 'checked' : ''}> Abstrakte Superklasse</label>` : ''}
              <label>Name
                <input type="text" data-uml-editor-field="name" value="${selectedNodeName}">
              </label>
              <div class="uml-hint">Attribut anklicken zum Auswählen; mit Entf oder „Löschen“ entfernen. Doppelklick öffnet die schnelle Bearbeitung.</div>
            </div>
          ` : ''}
          ${selectedNode && selectedNode.type !== 'primitive' && selectedAttribute != null ? `
            <div class="uml-section">
              <strong>Attribut</strong>
              <label>Attributname
                <input type="text" data-uml-attribute-field="name" value="${selectedAttributeValue}" placeholder="z. B. Nachname">
              </label>
              <label>Datentyp
                ${getTypeComboMarkup(selectedAttribute.type || '', typeOptions, 'data-uml-attribute-field="type" placeholder="z. B. String"')}
              </label>
              ${String(selectedAttribute.type || '').toLocaleLowerCase() === 'string' ? `
                <label>String-Länge
                  <input type="number" min="1" max="65535" data-uml-attribute-field="stringLength" value="${escapeHtml(String(selectedAttribute.stringLength || getStringLengthDefault(task)))}">
                </label>
              ` : ''}
              <label>Standardwert
                <input type="text" data-uml-attribute-field="defaultValue" value="${escapeHtml(selectedAttribute.defaultValue || '')}" placeholder="Optional">
              </label>
              <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" data-uml-attribute-field="derived" ${selectedAttribute.derived ? 'checked' : ''}>
                Abgeleitet
              </label>
              <div class="uml-hint">Typ, String-Länge und Standardwert werden getrennt gespeichert. Der Standardwert ist optional.</div>
            </div>
          ` : ''}
          ${selectedNode?.type === 'class' && selectedMethod != null ? `
            <div class="uml-section">
              <strong>Methode</strong>
              <label>Name
                <input type="text" data-uml-method-field="name" value="${escapeHtml(selectedMethod.name || '')}" placeholder="z. B. berechneSumme">
              </label>
              <label>Rückgabetyp
                ${getTypeComboMarkup(selectedMethod.returnType || 'void', ['void', ...typeOptions], 'data-uml-method-field="returnType" placeholder="void"')}
              </label>
              <strong>Parameter</strong>
              <div data-uml-method-parameters>
                ${getMethodParameters(selectedMethod).map((parameter) => getMethodParameterMarkup(parameter, typeOptions)).join('')}
              </div>
              <button type="button" data-uml-add-parameter>+ Parameter</button>
              <div class="uml-hint">Jeder Parameter hat einen eigenen Datentyp. Im Diagramm: name(parameter1, parameter2), ohne Datentypen.</div>
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
        const field = event.target.closest('[data-uml-editor-field], [data-uml-attribute-field], [data-uml-method-field], [data-uml-method-parameter-field], [data-uml-relation-field]');
        if (!field) return;
        const taskId = outputEl.dataset.umlTaskId;
        const activeTask = this._renderTasks[taskId] || task;
        const activeContainer = activeTask.container || container;
        if (field.hasAttribute('data-uml-editor-field')) {
          const shouldRender = renderCanvas && ['type', 'abstract'].includes(field.getAttribute('data-uml-editor-field'));
          this.updateSelectedNodeFromInspector(taskId, activeContainer, shouldRender);
        } else if (field.hasAttribute('data-uml-attribute-field')) {
          const fieldName = field.getAttribute('data-uml-attribute-field');
          const shouldRender = renderCanvas
            && (field.type === 'checkbox' || fieldName === 'type' || fieldName === 'stringLength');
          this.updateSelectedAttributeFromInspector(taskId, activeContainer, shouldRender);
        } else if (field.hasAttribute('data-uml-method-field') || field.hasAttribute('data-uml-method-parameter-field')) {
          this.updateSelectedMethodFromInspector(taskId, activeContainer, false);
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
      outputEl.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-uml-add-parameter]');
        const removeButton = event.target.closest('[data-uml-remove-parameter]');
        if (!addButton && !removeButton) return;
        const taskId = outputEl.dataset.umlTaskId;
        const activeTask = this.getRenderTask(taskId);
        const activeContainer = activeTask.container || container;
        if (addButton) {
          const list = outputEl.querySelector('[data-uml-method-parameters]');
          list.insertAdjacentHTML('beforeend', getMethodParameterMarkup(
            { name: '', type: '' }, getTypeSuggestions(this.getState(taskId).nodes, activeTask)
          ));
          list.lastElementChild.querySelector('input').focus();
        } else {
          removeButton.closest('[data-uml-method-parameter]').remove();
          this.updateSelectedMethodFromInspector(taskId, activeContainer, false);
        }
      });
      this.attachTypeComboEvents(outputEl, () => {
        const taskId = outputEl.dataset.umlTaskId;
        return this._renderTasks[taskId] || task;
      });
      outputEl.dataset.umlInspectorEventsBound = '1';
    },

    attachEvents(task, container, state) {
      const self = this;
      const getActiveTask = () => self.getRenderTask(container.dataset.umlTaskId || task.id);
      this.attachInspectorEvents(task, container);
      this.attachContextMenuEvents(task, container);

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
          if (action === 'delete-selected') {
            self.handleDeleteSelection(task.id, container);
          } else if (action === 'save' && task.umlProject) {
            self.persistState(task.id, self.getState(task.id), true, true);
          } else {
            self.handleAction(task.id, action, container);
          }
          const menu = container.querySelector('#uml-context-menu');
          if (menu) menu.style.display = 'none';
        });
      });

      if (!container.dataset.umlNodeDelegationBound) {
        container.addEventListener('pointerdown', (event) => {
          const task = getActiveTask();
          if (event.button !== 0) return;
          const relationEl = event.target.closest('[data-uml-relation-id]');
          if (relationEl) {
            event.stopPropagation();
            event.preventDefault();
            self._associationMode = { phase: 'ready', points: [], startNodeId: null };
            self._associationHint = '';
            self._associationCursor = null;
            self._selectedNodeId = null;
            self._selectedAttributePath = null;
            self._selectedMethodPath = null;
            self._selectedRelationId = relationEl.getAttribute('data-uml-relation-id');
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            self.renderInspector(window.assignmentState?.currentTask || { id: task.id }, self.getState(task.id));
            return;
          }
          const nodeEl = event.target.closest('.uml-node');
          if (!nodeEl && !event.target.closest('.uml-canvas-area')) {
            return;
          }
          if (event.target.closest('.uml-attr-text, .uml-method-text')) return;
          if (event.target.closest('input, textarea, select, [data-uml-add-attribute], [data-uml-add-method], [data-uml-type-combo-toggle], [data-uml-type-combo-option]')) return;
          const nodeId = self.resolveNodeIdFromEvent(task.id, container, event);
          if (!nodeId) {
            self._selectedNodeId = null;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            self._selectedMethodPath = null;
            self.render(window.assignmentState?.currentTask || { id: task.id }, container);
            return;
          }
          self._selectedNodeId = nodeId;
          self._selectedRelationId = null;
          self._selectedAttributePath = null;
          self._selectedMethodPath = null;
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
            self._selectedMethodPath = null;
            self._associationHint = self._connectionType === 'generalization'
              ? 'Jetzt klicken Sie auf die Superklasse.' : 'Jetzt klicken Sie auf die zweite Klasse.';
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
          const task = getActiveTask();
          const addButton = event.target.closest('[data-uml-add-attribute]');
          const addMethodButton = event.target.closest('[data-uml-add-method]');
          if (addButton) {
            event.stopPropagation();
            self.addAttribute(task.id, addButton.getAttribute('data-uml-add-attribute'), container);
            return;
          }
          if (addMethodButton) {
            event.stopPropagation();
            self.addMethod(task.id, addMethodButton.getAttribute('data-uml-add-method'), container);
            return;
          }
          const title = event.target.closest('[data-uml-edit-name]');
          const attribute = event.target.closest('[data-uml-edit-attribute]');
          const method = event.target.closest('[data-uml-edit-method]');
          if (title) {
            event.stopPropagation();
            self._selectedNodeId = title.getAttribute('data-uml-edit-name');
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            self._selectedMethodPath = null;
          } else if (attribute) {
            event.stopPropagation();
            const [nodeId, index] = attribute.getAttribute('data-uml-edit-attribute').split(':');
            self._selectedNodeId = nodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = { nodeId, index: Number(index || 0) };
            self._selectedMethodPath = null;
          } else if (method) {
            event.stopPropagation();
            const [nodeId, index] = method.getAttribute('data-uml-edit-method').split(':');
            self._selectedNodeId = nodeId;
            self._selectedRelationId = null;
            self._selectedAttributePath = null;
            self._selectedMethodPath = { nodeId, index: Number(index || 0) };
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
          container.querySelectorAll('.uml-method-text.selected').forEach((selectedMethod) => {
            selectedMethod.classList.remove('selected');
            selectedMethod.setAttribute('aria-selected', 'false');
          });
          if (method) {
            method.classList.add('selected');
            method.setAttribute('aria-selected', 'true');
          }
          container.querySelectorAll('.uml-node.selected').forEach((selectedNode) => {
            selectedNode.classList.remove('selected');
          });
          container.querySelector(`.uml-node[data-uml-node-id="${self._selectedNodeId}"]`)?.classList.add('selected');
          self.renderInspector(task, self.getState(task.id));
        });
        container.addEventListener('dblclick', (event) => {
          const task = getActiveTask();
          const title = event.target.closest('[data-uml-edit-name]');
          const attribute = event.target.closest('[data-uml-edit-attribute]');
          const method = event.target.closest('[data-uml-edit-method]');
          if (title) {
            event.stopPropagation();
            self.beginInlineEdit(task.id, 'name', title.getAttribute('data-uml-edit-name'), null, container);
          } else if (attribute) {
            event.stopPropagation();
            const [nodeId, index] = attribute.getAttribute('data-uml-edit-attribute').split(':');
            self.beginInlineEdit(task.id, 'attribute', nodeId, Number(index || 0), container);
          } else if (method) {
            event.stopPropagation();
            const [nodeId, index] = method.getAttribute('data-uml-edit-method').split(':');
            self.beginInlineEdit(task.id, 'method', nodeId, Number(index || 0), container);
          }
        });
        container.dataset.umlNodeDelegationBound = '1';
      }

      this.attachTypeComboEvents(container, getActiveTask);
      container.querySelectorAll('[data-uml-inline-name], [data-uml-inline-attribute-name], [data-uml-inline-attribute-type], [data-uml-inline-method]').forEach((input) => {
        input.addEventListener('input', () => input.setCustomValidity(''));
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
          const editState = self._editState;
          setTimeout(() => {
            if (!container.contains(input) || self._editState !== editState) return;
            const row = input.closest('.uml-attr-row');
            if (!row?.contains(document.activeElement)) {
              self.commitInlineEdit(task.id, container);
            }
          }, 0);
        });
      });

      if (!container.dataset.umlBound) {
        document.addEventListener('click', (event) => {
          const task = getActiveTask();
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
          const task = getActiveTask();
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
          const task = getActiveTask();
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
          const task = getActiveTask();
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
          const task = getActiveTask();
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
          const task = getActiveTask();
          if (event.button !== 0) return;
          const relationEl = event.target.closest('[data-uml-relation-id]');
          if (relationEl) {
            event.stopPropagation();
            event.preventDefault();
            self._associationMode = { phase: 'ready', points: [], startNodeId: null };
            self._associationHint = '';
            self._associationCursor = null;
            self._selectedNodeId = null;
            self._selectedAttributePath = null;
            self._selectedMethodPath = null;
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
            self._selectedMethodPath = null;
            self._associationHint = self._connectionType === 'generalization'
              ? 'Jetzt klicken Sie auf die Superklasse.' : 'Jetzt klicken Sie auf die zweite Klasse.';
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
            event.preventDefault();
            event.stopPropagation();
          }
        });
        canvasArea?.addEventListener('click', (event) => {
          const task = getActiveTask();
          if (event.target.closest('.uml-node')) return;
          if (event.target.closest('.uml-relationship')) return;
          if (event.target.closest('[data-uml-relation-id]')) return;
          self._selectedNodeId = null;
          self._selectedRelationId = null;
          self._selectedAttributePath = null;
          self._selectedMethodPath = null;
          self.render(task, container);
        });

        document.addEventListener('click', (event) => {
          if (!event.target.closest('#uml-context-menu')) {
            const menu = container.querySelector('#uml-context-menu');
            if (menu) menu.style.display = 'none';
          }
        });

        container.dataset.umlBound = '1';
      }
    },

    attachContextMenuEvents(task, container) {
      if (container.dataset.umlContextMenuBound) return;
      container.addEventListener('contextmenu', (event) => {
        if (!event.target.closest('.uml-canvas-area')) return;
        const activeTask = this.getRenderTask(container.dataset.umlTaskId || task.id);
        event.preventDefault();
        const nodeEl = event.target.closest('.uml-node');
        const relationEl = event.target.closest('[data-uml-relation-id]');
        const selectedId = nodeEl?.getAttribute('data-uml-node-id') || relationEl?.getAttribute('data-uml-relation-id') || null;
        if (nodeEl) {
          this._selectedNodeId = selectedId;
          this._selectedRelationId = null;
          this._selectedAttributePath = null;
          this._selectedMethodPath = null;
        } else if (relationEl) {
          this._selectedRelationId = selectedId;
          this._selectedNodeId = null;
          this._selectedAttributePath = null;
          this._selectedMethodPath = null;
        } else {
          this._selectedNodeId = null;
          this._selectedRelationId = null;
          this._selectedAttributePath = null;
          this._selectedMethodPath = null;
        }
        this.render(activeTask, container);
        const menu = container.querySelector('#uml-context-menu');
        if (menu) {
          menu.style.left = `${event.clientX}px`;
          menu.style.top = `${event.clientY}px`;
          menu.style.display = 'block';
        }
      });
      container.dataset.umlContextMenuBound = '1';
    },

    updateTypeComboOptions(input, task, open = true) {
      const combo = input?.closest('.uml-type-combo');
      const options = combo?.querySelector('[data-uml-type-combo-options]');
      if (!combo || !options) return;
      const suggestions = getTypeSuggestions(this.getState(task.id).nodes, task);
      if (input.getAttribute('data-uml-method-field') === 'returnType') {
        suggestions.unshift('void');
      }
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
            input.dispatchEvent(new Event('change', { bubbles: true }));
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
        this._selectedMethodPath = null;
      } else if (mode === 'attribute') {
        this._selectedNodeId = nodeId;
        this._selectedRelationId = null;
        this._selectedAttributePath = { nodeId, index };
        this._selectedMethodPath = null;
      } else if (mode === 'method') {
        this._selectedNodeId = nodeId;
        this._selectedRelationId = null;
        this._selectedAttributePath = null;
        this._selectedMethodPath = { nodeId, index };
      }
      this.render(this.getRenderTask(taskId), container);
      setTimeout(() => {
        const input = container.querySelector('[data-uml-inline-name], [data-uml-inline-attribute-name], [data-uml-inline-method]');
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
      } else if (editState.mode === 'method') {
        const input = container.querySelector('[data-uml-inline-method]');
        const signature = (input?.value || '').trim().match(/^([^():,]+)\(([^():]*)\)$/);
        if (!signature || !signature[1].trim()
          || signature[2].split(',').some((name) => signature[2].trim() && !name.trim())) {
          input.setCustomValidity('Bitte name(parameter1, parameter2) ohne Datentypen eingeben. Datentypen werden rechts gepflegt.');
          input.reportValidity();
          return;
        }
        const method = node.methods[editState.index];
        const previousParameters = getMethodParameters(method);
        const names = signature[2].trim() ? signature[2].split(',').map((name) => name.trim()) : [];
        method.name = signature[1].trim();
        method.parameters = names.map((name, index) => {
          const previous = previousParameters.find((parameter) => parameter.name === name)
            || (names.length === previousParameters.length ? previousParameters[index] : null);
          return previous?.type ? `${name}: ${previous.type}` : name;
        }).join(', ');
      } else if (editState.mode === 'attribute') {
        const nameInput = container.querySelector(`[data-uml-inline-attribute-name="${editState.nodeId}"]`);
        const typeInput = container.querySelector(`[data-uml-inline-attribute-type="${editState.nodeId}"]`);
        const name = (nameInput?.value || '').trim();
        const previousAttribute = node.attributes[editState.index];
        const type = (typeInput?.value || '').trim();
        if (name) {
          const legacyStringLength = type.match(/^String\s*\(\s*(\d+)\s*\)$/i);
          const previousStringLength = Number.parseInt(previousAttribute?.stringLength, 10);
          node.attributes[editState.index] = normalizeAttribute({
            ...previousAttribute,
            name,
            type,
            stringLength: legacyStringLength
              ? null
              : (type.toLocaleLowerCase() === 'string'
                ? (Number.isInteger(previousStringLength) && previousStringLength > 0
                  ? previousStringLength
                  : getStringLengthDefault(this.getRenderTask(taskId)))
                : null),
            derived: Boolean(previousAttribute?.derived)
          }, 0, editState.index);
        } else {
          node.attributes = node.attributes.filter((_, attributeIndex) => attributeIndex !== editState.index);
        }
      }
      this._editState = null;
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(this.getRenderTask(taskId), container);
    },

    addAttribute(taskId, nodeId, container) {
      const state = this.getState(taskId);
      const node = state.nodes.find((candidate) => candidate.id === nodeId);
      if (!node || node.type === 'primitive') return;
      node.attributes.push({ id: createAttributeId(), name: '', type: '', stringLength: null, defaultValue: '' });
      this._selectedNodeId = nodeId;
      this._selectedAttributePath = null;
      this._selectedMethodPath = null;
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.beginInlineEdit(taskId, 'attribute', nodeId, node.attributes.length - 1, container);
    },

    addMethod(taskId, nodeId, container) {
      const state = this.getState(taskId);
      const node = state.nodes.find((candidate) => candidate.id === nodeId);
      if (!node || node.type !== 'class' || !getUmlTypeConfig(this.getRenderTask(taskId)).methodsEnabled) return;
      node.methods.push({ id: createEntityId('method'), name: '', parameters: '', returnType: 'void' });
      this._selectedNodeId = nodeId;
      this._selectedRelationId = null;
      this._selectedAttributePath = null;
      this._selectedMethodPath = { nodeId, index: node.methods.length - 1 };
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(this.getRenderTask(taskId), container);
      this.renderInspector(this.getRenderTask(taskId), this.getState(taskId));
    },

    handleInlineShortcut(taskId, container, useLiteral) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode || selectedNode.type === 'primitive') {
        return;
      }
      const value = useLiteral ? 'literal' : '';
      selectedNode.attributes.push({ id: createAttributeId(), name: value, type: '', stringLength: null, defaultValue: '' });
      this._selectedAttributePath = null;
      this._selectedMethodPath = null;
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
      if (this._connectionType === 'generalization' && targetNodeId === startNodeId) {
        this._associationMode = { phase: 'awaiting-target', points: [], startNodeId };
        this._associationHint = 'Eine Klasse kann nicht ihre eigene Superklasse sein.';
        this.render(this.getRenderTask(taskId), container);
        return;
      }
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
      if (this._connectionType === 'generalization') {
        const ancestors = [targetNodeId];
        const visited = new Set();
        while (ancestors.length) {
          const id = ancestors.pop();
          if (visited.has(id)) continue;
          visited.add(id);
          state.relationships.filter((relation) => relation.type === 'generalization' && relation.sourceId === id)
            .forEach((relation) => ancestors.push(relation.targetId));
        }
        const duplicate = state.relationships.some((relation) => relation.type === 'generalization'
          && relation.sourceId === startNodeId && relation.targetId === targetNodeId);
        if (startNode?.type !== 'class' || targetNode?.type !== 'class' || visited.has(startNodeId) || duplicate) {
          this._associationMode = { phase: 'awaiting-target', points: [], startNodeId };
          this._associationHint = 'Generalisierung nur zwischen Klassen, ohne Zyklus oder doppelte Verbindung. Bitte eine andere Superklasse wählen.';
          this.render(this.getRenderTask(taskId), container);
          return;
        }
      }
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
        type: this._connectionType || 'association',
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
      if (this._selectedMethodPath) {
        const index = Number(this._selectedMethodPath.index);
        if (!selectedNode || this._selectedMethodPath.nodeId !== selectedNode.id
          || !Number.isInteger(index) || index < 0 || index >= selectedNode.methods.length) return;
        selectedNode.methods.splice(index, 1);
        this._selectedMethodPath = null;
      } else if (this._selectedAttributePath) {
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
      state.nodes.forEach((node) => {
        if (node.abstract && !state.relationships.some((relation) => relation.type === 'generalization'
          && relation.targetId === node.id)) node.abstract = false;
      });
      this.setState(taskId, state);
      this.persistState(taskId, state);
      this.render(window.assignmentState?.currentTask || { id: taskId }, container);
    },

    updateSelectedMethodFromInspector(taskId, container, renderCanvas = true) {
      const state = this.getState(taskId);
      const selectedNode = state.nodes.find((node) => node.id === this._selectedNodeId);
      if (!selectedNode || !this._selectedMethodPath
        || this._selectedMethodPath.nodeId !== selectedNode.id) return;
      const method = selectedNode.methods[Number(this._selectedMethodPath.index)];
      if (!method) return;
      const inspectorHost = document.getElementById('output-container') || container;
      const nameInput = inspectorHost.querySelector('[data-uml-method-field="name"]');
      const returnTypeInput = inspectorHost.querySelector('[data-uml-method-field="returnType"]');
      method.name = (nameInput?.value || '').trim();
      method.parameters = Array.from(inspectorHost.querySelectorAll('[data-uml-method-parameter]')).map((row) => {
        const name = row.querySelector('[data-uml-method-parameter-field="name"]').value.trim();
        const type = row.querySelector('[data-uml-method-parameter-field="type"]').value.trim();
        return type ? `${name}: ${type}` : name;
      }).filter(Boolean).join(', ');
      method.returnType = (returnTypeInput?.value || '').trim() || 'void';
      this.setState(taskId, state);
      this.persistState(taskId, state);
      if (renderCanvas) {
        this.render(this.getRenderTask(taskId), container);
      } else {
        const selector = `[data-uml-edit-method="${selectedNode.id}:${this._selectedMethodPath.index}"]`;
        const methodElement = container.querySelector(selector);
        if (methodElement) methodElement.textContent = formatMethodSignature(method);
        this.renderConnections(this.getRenderTask(taskId), container, state);
      }
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
      } else if (action === 'add-structure' || action === 'add-primitive') {
        const config = getUmlTypeConfig(this.getRenderTask(taskId));
        if (!(action === 'add-structure' ? config.structuresEnabled : config.customSimpleTypesEnabled)) {
          window.alert('Diese Funktion ist für diese Aufgabe nicht aktiviert.');
          return;
        }
        const state = this.getState(taskId);
        const canvasWidth = container?.querySelector('.uml-canvas-area')?.clientWidth || 1000;
        const node = createNode(action === 'add-structure' ? 'structure' : 'primitive', getNewNodePosition(state.nodes, canvasWidth));
        state.nodes.push(node);
        this.setState(taskId, state);
        this.persistState(taskId, state);
        this.beginInlineEdit(taskId, 'name', node.id, null, container);
      } else if (action === 'add-association' || action === 'add-generalization') {
        if (action === 'add-generalization' && !getUmlTypeConfig(this.getRenderTask(taskId)).generalizationEnabled) {
          window.alert('Generalisierung ist für diese Aufgabe nicht aktiviert.');
          return;
        }
        if (this._associationMode?.phase === 'awaiting-source' || this._associationMode?.phase === 'awaiting-target') {
          this._associationMode = { phase: 'ready', points: [], startNodeId: null };
          this._associationHint = '';
          this._associationCursor = null;
          this.render(window.assignmentState?.currentTask || { id: taskId }, container);
          return;
        }
        this._connectionType = action === 'add-generalization' ? 'generalization' : 'association';
        this._associationMode = { phase: 'awaiting-source', points: [], startNodeId: null };
        this._associationPreview = null;
        this._associationHint = this._connectionType === 'generalization'
          ? 'Klicken Sie zuerst auf die Unterklasse, dann auf die Superklasse.' : 'Klicken Sie zuerst auf die erste Klasse.';
        this._associationCursor = { x: 40, y: 40 };
        this.render(window.assignmentState?.currentTask || { id: taskId }, container);
      } else if (action === 'delete-selected') {
        this.handleDeleteSelection(taskId, container);
      } else if (action === 'reset') {
        if (!window.confirm('Möchten Sie das gesamte Diagramm wirklich leeren? Alle Klassen, Datentypen, Enumerationen und Beziehungen werden entfernt.')) {
          return;
        }
        const state = { version: UML_VERSION, nodes: [], relationships: [] };
        this._selectedNodeId = null;
        this._selectedRelationId = null;
        this._selectedAttributePath = null;
        this._selectedMethodPath = null;
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
      const abstractInput = inspectorHost.querySelector('[data-uml-editor-field="abstract"]');
      if (typeInput) {
        const connected = state.relationships.some((relation) => relation.type === 'generalization'
          && (relation.sourceId === selectedNode.id || relation.targetId === selectedNode.id));
        if (connected && typeInput.value !== 'class') {
          window.alert('Klassen mit Generalisierungen können nicht in Datentypen umgewandelt werden. Entfernen Sie zuerst die Generalisierung.');
          this.render(this.getRenderTask(taskId), container);
          return;
        }
        const newType = ['enum', 'structure', 'primitive'].includes(typeInput.value) ? typeInput.value : 'class';
        const removesMethods = newType !== 'class' && selectedNode.methods.length > 0;
        const removesAttributes = newType === 'primitive' && selectedNode.attributes.length > 0;
        if (newType !== selectedNode.type && (removesMethods || removesAttributes)) {
          if (!window.confirm('Dieser Datentyp besitzt keine Methoden; einfache Datentypen besitzen auch keine Attribute. Sollen die nicht zulässigen Inhalte beim Umwandeln entfernt werden?')) {
            this.render(this.getRenderTask(taskId), container);
            return;
          }
          if (removesMethods) selectedNode.methods = [];
          if (removesAttributes) selectedNode.attributes = [];
          this._selectedMethodPath = null;
          this._selectedAttributePath = null;
        }
        selectedNode.type = newType;
        if (newType !== 'class') selectedNode.abstract = false;
      }
      if (abstractInput) selectedNode.abstract = selectedNode.type === 'class' && abstractInput.checked
        && state.relationships.some((relation) => relation.type === 'generalization' && relation.targetId === selectedNode.id);
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
      const stringLengthInput = inspectorHost.querySelector('[data-uml-attribute-field="stringLength"]');
      const defaultValueInput = inspectorHost.querySelector('[data-uml-attribute-field="defaultValue"]');
      const derivedInput = inspectorHost.querySelector('[data-uml-attribute-field="derived"]');
      const name = (nameInput?.value || '').trim();
      const type = (typeInput?.value || '').trim();
      const index = Number(this._selectedAttributePath.index);
      if (Number.isNaN(index)) return;
      if (!name && !type) {
        selectedNode.attributes = selectedNode.attributes.filter((_, attributeIndex) => attributeIndex !== index);
      } else if (name) {
        const previousAttribute = selectedNode.attributes[index] || {};
        const previousLength = Number.parseInt(previousAttribute.stringLength, 10);
        const configuredLength = getStringLengthDefault(this.getRenderTask(taskId));
        const enteredLength = Number.parseInt(stringLengthInput?.value || '', 10);
        const explicitStringLength = type.match(/^String\s*\(\s*(\d+)\s*\)$/i);
        selectedNode.attributes[index] = normalizeAttribute({
          ...previousAttribute,
          name,
          type,
          stringLength: explicitStringLength
            ? null
            : type.toLocaleLowerCase() === 'string'
              ? (Number.isInteger(enteredLength) && enteredLength > 0
                ? enteredLength
                : (Number.isInteger(previousLength) && previousLength > 0 ? previousLength : configuredLength))
            : null,
          defaultValue: defaultValueInput?.value ?? previousAttribute.defaultValue ?? '',
          derived: derivedInput instanceof HTMLInputElement
            ? derivedInput.checked
            : Boolean(previousAttribute.derived)
        }, 0, index);
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

      const createConnectorLine = (startPoint, endPoint, relationId = null, isSelected = false, generalization = false) => {
        const deltaX = endPoint.x - startPoint.x;
        const deltaY = endPoint.y - startPoint.y;
        const distance = Math.max(1, Math.hypot(deltaX, deltaY));
        const angle = Math.atan2(deltaY, deltaX) * 180 / Math.PI;
        const line = document.createElement('div');
        line.className = `uml-relationship-line${isSelected ? ' selected' : ''}`;
        line.style.left = `${startPoint.x}px`;
        line.style.top = `${startPoint.y - 6}px`;
        line.style.width = `${generalization ? distance : Math.max(28, distance)}px`;
        line.style.transform = `rotate(${angle}deg)`;
        line.style.padding = '0';
        line.style.margin = '0';
        if (generalization) {
          line.innerHTML = '<svg class="uml-generalization-arrow" viewBox="0 0 18 16" aria-hidden="true"><path d="M17 8L1 1V15Z"/></svg>';
        }
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

        const isSelected = this._selectedRelationId === relation.id;
        const line = createConnectorLine(sourcePoint, targetPoint, relation.id, isSelected, relation.type === 'generalization');
        relationshipLayer.appendChild(line);
        if (relation.type === 'generalization') return;

        const start = sourcePoint;
        const end = targetPoint;
        const deltaX = end.x - start.x;
        const deltaY = end.y - start.y;
        const distance = Math.max(1, Math.hypot(deltaX, deltaY));
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
          const endpointOffset = Math.min(22, Math.max(12, distance / 4));
          const perpendicularOffset = Math.min(18, Math.max(14, distance * 0.1)) * direction;
          label.style.left = `${point.x + (deltaX / distance) * endpointOffset * direction - (deltaY / distance) * perpendicularOffset}px`;
          label.style.top = `${point.y + (deltaY / distance) * endpointOffset * direction + (deltaX / distance) * perpendicularOffset}px`;
          relationshipLayer.appendChild(label);
        });
      });
    }
  };
})();
