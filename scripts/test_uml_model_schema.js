const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const rendererPath = path.join(__dirname, '..', 'public', 'js', 'uml-renderer.js');
const rendererSource = fs.readFileSync(rendererPath, 'utf8');
const context = { window: {}, Date, Math };
vm.runInNewContext(rendererSource, context, { filename: rendererPath });

const normalize = context.window.UmlRenderer.normalizeModel;
const sourceModel = {
  version: 1,
  nodes: [
    { id: 'class-customer', name: 'Kunde', attributes: [{ id: 'attr-email', name: 'email', type: 'String' }] },
    { id: 'class-order', name: 'Bestellung', attributes: ['datum', '/gesamtsumme'] }
  ],
  relationships: [{
    id: 'rel-customer-orders',
    sourceId: 'class-customer',
    targetId: 'class-order',
    label: 'gibt auf',
    sourceCardinality: '1',
    targetCardinality: '0..*'
  }]
};

const normalized = normalize(sourceModel);
assert.equal(normalized.version, 3);
assert.equal(normalized.nodes[0].id, 'class-customer');
assert.equal(normalized.nodes[0].attributes[0].id, 'attr-email');
assert.ok(normalized.nodes[1].attributes[0].id);
assert.equal(normalized.nodes[1].attributes[1].name, 'gesamtsumme');
assert.equal(normalized.nodes[1].attributes[1].derived, true);
assert.equal(normalized.nodes[0].methods.length, 0);
assert.equal(normalized.relationships[0].sourceCardinality, '1');
assert.equal(normalized.relationships[0].targetCardinality, '0..*');

const roundTripped = normalize(JSON.parse(JSON.stringify(normalized)));
assert.equal(roundTripped.nodes[0].attributes[0].id, 'attr-email');
assert.equal(roundTripped.nodes[1].attributes[0].id, normalized.nodes[1].attributes[0].id);
assert.equal(roundTripped.nodes[1].attributes[1].derived, true);
assert.equal(roundTripped.relationships[0].sourceCardinality, '1');
assert.equal(roundTripped.relationships[0].targetCardinality, '0..*');

const legacyModel = normalize({
  nodes: [{ id: 'legacy-class', attributes: ['name'] }],
  relationships: [{ sourceId: 'legacy-class', targetId: 'other-class' }]
});
assert.ok(legacyModel.nodes[0].attributes[0].id);
assert.equal(legacyModel.relationships[0].sourceCardinality, '');
assert.equal(legacyModel.relationships[0].targetCardinality, '');
assert.equal(
  normalize(legacyModel).nodes[0].attributes[0].id,
  legacyModel.nodes[0].attributes[0].id
);

console.log('UML model schema tests passed.');

function testCardinalityChoices() {
  const renderer = context.window.UmlRenderer;
  const inspector = { innerHTML: '' };
  context.document = { getElementById: () => inspector };
  renderer._selectedRelationId = 'rel-customer-orders';
  renderer.renderInspector({ id: 1 }, normalized);

  for (const value of ['1', '0..1', '1..*', '0..*']) {
    assert.ok(inspector.innerHTML.includes(`value="${value}"`), `Missing cardinality option ${value}`);
  }
  assert.equal((inspector.innerHTML.match(/data-uml-relation-field="sourceCardinality"/g) || []).length, 1);
  assert.equal((inspector.innerHTML.match(/data-uml-relation-field="targetCardinality"/g) || []).length, 1);
  assert.ok(inspector.innerHTML.includes('1..* (1:n)'));
  assert.ok(inspector.innerHTML.includes('0..* (0:n)'));

  const legacyState = normalize({
    nodes: sourceModel.nodes,
    relationships: [{
      ...sourceModel.relationships[0],
      sourceCardinality: 'many',
      targetCardinality: '0..1'
    }]
  });
  renderer.renderInspector({ id: 1 }, legacyState);
  assert.ok(inspector.innerHTML.includes('value="many" selected>many (bisheriger Wert)</option>'));
}

testCardinalityChoices();
console.log('UML cardinality choice tests passed.');

function testConfiguredTypeSuggestionsAndToolbar() {
  const renderer = context.window.UmlRenderer;
  const model = normalize({
    nodes: [
      { id: 'class-customer', name: 'Kunde', type: 'class', attributes: [{ name: 'referenz' }] },
      { id: 'structure-address', name: 'Adresse', type: 'structure' },
      { id: 'enum-status', name: 'Status', type: 'enum' }
    ],
    relationships: []
  });
  renderer._editState = { mode: 'attribute', nodeId: 'class-customer', index: 0 };
  const configuredHtml = renderer.buildHtml({
    id: 'configured',
    uml_template: JSON.stringify({
      type_config: {
        primitive_types: ['Decimal', 'String'],
        primitive_types_enabled: true,
        complex_types_enabled: true,
        enumeration_types_enabled: false,
        string_length: 40
      }
    })
  }, model);
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="Decimal"'));
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="String"'));
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="Kunde"'));
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="Adresse"'));
  assert.ok(!configuredHtml.includes('data-uml-type-combo-option="Status"'));
  assert.ok(configuredHtml.includes('id="quiz-feedback-configured"'));
  assert.ok(configuredHtml.includes('aria-label="Klasse hinzufügen"'));
  assert.ok(configuredHtml.includes('class="uml-class-header"'));
  assert.ok(configuredHtml.includes('class="uml-class-header-line"'));
  assert.ok(configuredHtml.includes('class="uml-enum-letter"'));
  assert.ok(configuredHtml.includes('>E</text>'));
  assert.ok(configuredHtml.includes('data-uml-context-action="add-association"'));

  const disabledHtml = renderer.buildHtml({
    id: 'disabled',
    uml_template: JSON.stringify({
      type_config: {
        primitive_types: ['Decimal'],
        primitive_types_enabled: false,
        complex_types_enabled: false,
        enumeration_types_enabled: false
      }
    })
  }, model);
  assert.ok(!disabledHtml.includes('data-uml-type-combo-option="Decimal"'));
  assert.ok(!disabledHtml.includes('data-uml-type-combo-option="Kunde"'));
  assert.ok(!disabledHtml.includes('data-uml-type-combo-option="Status"'));

  const legacyHtml = renderer.buildHtml({
    id: 'legacy',
    uml_template: JSON.stringify({ type_config: { simple_types: ['String'] } })
  }, model);
  assert.ok(legacyHtml.includes('data-uml-type-combo-option="String"'));
  assert.ok(legacyHtml.includes('data-uml-type-combo-option="Status"'));
  renderer._editState = null;
}

testConfiguredTypeSuggestionsAndToolbar();
console.log('UML configured type suggestion tests passed.');

function testClassesStartEmptyAndMethodsAreOptional() {
  const renderer = context.window.UmlRenderer;
  const taskId = 'uml-empty-class-test';
  const previousPersistState = renderer.persistState;
  const previousBeginInlineEdit = renderer.beginInlineEdit;
  renderer._stateCache[taskId] = normalize({ nodes: [], relationships: [] });
  renderer._renderTasks[taskId] = { id: taskId };
  renderer.persistState = () => Promise.resolve(true);
  renderer.beginInlineEdit = () => {};
  renderer.handleAction(taskId, 'add-class', {
    querySelector: () => ({ clientWidth: 1000 })
  });
  const newClass = renderer.getState(taskId).nodes[0];
  assert.equal(newClass.attributes.length, 0);
  assert.equal(newClass.methods.length, 0);

  const enabledTask = {
    id: 'uml-methods-enabled',
    uml_template: { type_config: { methods_enabled: true } }
  };
  const methodsEnabledHtml = renderer.buildHtml(enabledTask, normalize({
    nodes: [{
      id: 'method-class',
      name: 'Warenkorb',
      methods: [{
        id: 'calculate-total',
        name: 'berechneSumme',
        parameters: 'menge: Integer, preis: Currency',
        returnType: 'Currency'
      }]
    }],
    relationships: []
  }));
  assert.ok(methodsEnabledHtml.includes('data-uml-add-method="method-class"'));
  assert.ok(methodsEnabledHtml.includes('berechneSumme(menge, preis)'));
  assert.ok(!methodsEnabledHtml.includes('menge: Integer'));
  assert.ok(!methodsEnabledHtml.includes('): Currency'));
  assert.ok(methodsEnabledHtml.includes('class="uml-methods-compartment"'));
  assert.match(methodsEnabledHtml, /class="uml-node-actions">\s*<button[^>]+data-uml-add-attribute="method-class"[^>]*>\+ Attribut<\/button>\s*<button[^>]+data-uml-add-method="method-class"/);
  const emptyMethodsHtml = renderer.buildHtml(enabledTask, normalize({
    nodes: [{ id: 'empty-method-class', name: 'Leer' }],
    relationships: []
  }));
  assert.ok(!emptyMethodsHtml.includes('class="uml-methods-compartment"'));
  assert.ok(emptyMethodsHtml.includes('data-uml-add-method="empty-method-class"'));

  const body = { innerHTML: '' };
  const previousRenderConnections = renderer.renderConnections;
  renderer.renderConnections = () => {};
  renderer._renderTasks[enabledTask.id] = enabledTask;
  renderer._stateCache[enabledTask.id] = normalize({
    nodes: [{ id: 'method-class', name: 'Warenkorb', methods: [{
      name: 'summe', parameters: 'menge: Integer', returnType: 'Currency'
    }] }],
    relationships: []
  });
  const refreshContainer = {
    querySelectorAll: () => [{
      getAttribute: () => 'method-class',
      querySelector: (selector) => selector === '.uml-node-body' ? body : null
    }]
  };
  renderer.refreshNodeText(enabledTask.id, refreshContainer, 'method-class');
  assert.ok(body.innerHTML.includes('class="uml-methods-compartment"'));
  assert.ok(body.innerHTML.includes('summe(menge)'));
  renderer._stateCache[enabledTask.id].nodes[0].methods = [];
  renderer.refreshNodeText(enabledTask.id, refreshContainer, 'method-class');
  assert.ok(!body.innerHTML.includes('class="uml-methods-compartment"'));
  renderer.renderConnections = previousRenderConnections;
  delete renderer._stateCache[enabledTask.id];
  delete renderer._renderTasks[enabledTask.id];

  const methodsDisabledHtml = renderer.buildHtml(
    { id: 'uml-methods-disabled' },
    normalize({ nodes: [{ id: 'class-without-methods' }], relationships: [] })
  );
  assert.ok(!methodsDisabledHtml.includes('data-uml-add-method='));
  assert.ok(!methodsDisabledHtml.includes('class="uml-methods-compartment"'));
  renderer.persistState = previousPersistState;
  renderer.beginInlineEdit = previousBeginInlineEdit;
  delete renderer._stateCache[taskId];
  delete renderer._renderTasks[taskId];
}

testClassesStartEmptyAndMethodsAreOptional();
console.log('UML empty class and method availability tests passed.');

function testMethodEditingAndDeletion() {
  const renderer = context.window.UmlRenderer;
  const taskId = 'uml-method-editing-test';
  const previousRender = renderer.render;
  const previousRenderInspector = renderer.renderInspector;
  const previousPersistState = renderer.persistState;
  const previousRenderConnections = renderer.renderConnections;
  const previousSetTimeout = context.setTimeout;
  const previousSelectedNodeId = renderer._selectedNodeId;
  const previousSelectedMethodPath = renderer._selectedMethodPath;
  const previousDocument = context.document;
  const methodLabel = { textContent: '' };
  const inputs = {
    name: { value: 'berechneSumme' },
    returnType: { value: 'Currency' }
  };
  const parameterInputs = [
    { name: { value: 'menge' }, type: { value: 'Integer' } },
    { name: { value: 'preis' }, type: { value: 'Currency' } }
  ];
  const inlineInput = {
    value: '',
    validationMessage: '',
    setCustomValidity(message) { this.validationMessage = message; },
    reportValidity() {}
  };
  const container = {
    querySelector(selector) {
      if (selector === '[data-uml-inline-method]') return inlineInput;
      return selector.startsWith('[data-uml-edit-method=') ? methodLabel : null;
    }
  };
  context.document = {
    getElementById: () => ({
      querySelector(selector) {
        const key = selector.match(/="([^"]+)"/)?.[1];
        return inputs[key] || null;
      },
      querySelectorAll() {
        return parameterInputs.map((parameter) => ({
          querySelector(selector) {
            return selector.includes('="name"') ? parameter.name : parameter.type;
          }
        }));
      }
    })
  };
  renderer._stateCache[taskId] = normalize({
    nodes: [{ id: 'method-class', name: 'Warenkorb' }],
    relationships: []
  });
  renderer._renderTasks[taskId] = {
    id: taskId,
    container,
    uml_template: { type_config: { methods_enabled: true } }
  };
  renderer.render = () => {};
  renderer.renderInspector = () => {};
  renderer.persistState = () => Promise.resolve(true);
  renderer.renderConnections = () => {};
  context.setTimeout = () => {};

  renderer.addMethod(taskId, 'method-class', container);
  assert.equal(renderer.getState(taskId).nodes[0].methods.length, 1);
  assert.equal(renderer.getState(taskId).nodes[0].methods[0].returnType, 'void');
  renderer.updateSelectedMethodFromInspector(taskId, container, false);
  const method = renderer.getState(taskId).nodes[0].methods[0];
  assert.equal(method.name, 'berechneSumme');
  assert.equal(method.parameters, 'menge: Integer, preis: Currency');
  assert.equal(method.returnType, 'Currency');
  assert.equal(methodLabel.textContent, 'berechneSumme(menge, preis)');
  const inspector = { innerHTML: '' };
  const inspectorDocument = context.document;
  context.document = { getElementById: () => inspector };
  previousRenderInspector.call(renderer, renderer.getRenderTask(taskId), renderer.getState(taskId));
  assert.equal((inspector.innerHTML.match(/data-uml-method-parameter-field="type"/g) || []).length, 2);
  assert.ok(inspector.innerHTML.includes('value="Integer"'));
  assert.ok(inspector.innerHTML.includes('value="Currency"'));
  assert.ok(!inspector.innerHTML.includes('data-uml-method-field="parameters"'));
  context.document = inspectorDocument;

  renderer.beginInlineEdit(taskId, 'method', 'method-class', 0, container);
  const inlineHtml = renderer.buildHtml(renderer.getRenderTask(taskId), renderer.getState(taskId));
  assert.ok(inlineHtml.includes('data-uml-inline-method='));
  assert.ok(inlineHtml.includes('berechneSumme(menge, preis)'));
  inlineInput.value = 'summe(preis, menge, rabatt)';
  renderer.commitInlineEdit(taskId, container);
  const editedMethod = renderer.getState(taskId).nodes[0].methods[0];
  assert.equal(editedMethod.parameters, 'preis: Currency, menge: Integer, rabatt');
  assert.equal(editedMethod.returnType, 'Currency');
  assert.equal(editedMethod.id, method.id);
  const typedRoundTrip = normalize(JSON.parse(JSON.stringify(renderer.getState(taskId)))).nodes[0].methods[0];
  assert.equal(typedRoundTrip.parameters, 'preis: Currency, menge: Integer, rabatt');
  renderer.beginInlineEdit(taskId, 'method', 'method-class', 0, container);
  inlineInput.value = 'summe(preis: Currency)';
  renderer.commitInlineEdit(taskId, container);
  assert.ok(inlineInput.validationMessage.includes('ohne Datentypen'));
  assert.equal(renderer.getState(taskId).nodes[0].methods[0].parameters, editedMethod.parameters);
  assert.equal(renderer._editState.mode, 'method');
  inlineInput.value = 'summe()';
  renderer.commitInlineEdit(taskId, container);
  assert.equal(renderer.getState(taskId).nodes[0].methods[0].parameters, '');
  assert.equal(renderer.getState(taskId).nodes[0].methods[0].returnType, 'Currency');
  const savedMethod = normalize(JSON.parse(JSON.stringify(renderer.getState(taskId)))).nodes[0].methods[0];
  assert.equal(savedMethod.returnType, 'Currency');

  renderer.handleDeleteSelection(taskId, container);
  assert.equal(renderer.getState(taskId).nodes[0].methods.length, 0);

  renderer.render = previousRender;
  renderer.renderInspector = previousRenderInspector;
  renderer.persistState = previousPersistState;
  renderer.renderConnections = previousRenderConnections;
  context.setTimeout = previousSetTimeout;
  renderer._selectedNodeId = previousSelectedNodeId;
  renderer._selectedMethodPath = previousSelectedMethodPath;
  context.document = previousDocument;
  delete renderer._stateCache[taskId];
  delete renderer._renderTasks[taskId];
}

testMethodEditingAndDeletion();
console.log('UML method editing and deletion tests passed.');

function testDatatypesAndGeneralization() {
  const renderer = context.window.UmlRenderer;
  const taskId = 'uml-full-features';
  const saved = {};
  ['render', 'persistState', 'beginInlineEdit', '_connectionType', '_associationMode',
    '_selectedNodeId', '_selectedRelationId', '_selectedAttributePath', '_selectedMethodPath', '_editState']
    .forEach((key) => { saved[key] = renderer[key]; });
  const previousDocument = context.document;
  const previousAlert = context.window.alert;
  const alerts = [];
  context.window.alert = (message) => alerts.push(message);
  const config = {
    structures_enabled: true, custom_simple_types_enabled: true, generalization_enabled: true,
    methods_enabled: true, complex_types_enabled: true, primitive_types_enabled: true
  };
  const task = { id: taskId, uml_template: { type_config: config } };
  renderer._renderTasks[taskId] = task;
  renderer._stateCache[taskId] = normalize({
    nodes: [{ id: 'child', name: 'ElektronikArtikel' }, { id: 'parent', name: 'Artikel' }],
    relationships: []
  });
  renderer.render = () => {};
  renderer.persistState = () => Promise.resolve(true);
  renderer.beginInlineEdit = () => {};
  const container = { querySelector: () => null };
  renderer.handleAction(taskId, 'add-structure', container);
  renderer.handleAction(taskId, 'add-primitive', container);
  let state = renderer.getState(taskId);
  let structure = state.nodes.find((node) => node.type === 'structure');
  const primitive = state.nodes.find((node) => node.type === 'primitive');
  structure.name = 'AdresseT';
  primitive.name = 'Byte';
  renderer.setState(taskId, state);
  renderer.addMethod(taskId, structure.id, container);
  assert.equal(structure.methods.length, 0);
  renderer.addAttribute(taskId, primitive.id, container);
  assert.equal(primitive.attributes.length, 0);
  renderer.addAttribute(taskId, structure.id, container);
  state = renderer.getState(taskId);
  structure = state.nodes.find((node) => node.type === 'structure');
  assert.equal(structure.attributes.length, 1);
  structure.attributes[0].name = 'strasse';
  structure.attributes[0].type = 'String';
  renderer._editState = { mode: 'attribute', nodeId: 'child', index: 0 };
  state.nodes[0].attributes.push({ name: 'adresse', type: 'AdresseT' });
  renderer.setState(taskId, state);
  const html = renderer.buildHtml(task, state);
  assert.ok(html.includes('«datatype»'));
  assert.ok(html.includes('«primitive»'));
  assert.ok(html.includes('data-uml-type-combo-option="AdresseT"'));
  assert.ok(html.includes('data-uml-type-combo-option="Byte"'));
  assert.ok(!html.includes(`data-uml-add-method="${structure.id}"`));
  assert.ok(!html.includes(`data-uml-add-attribute="${primitive.id}"`));
  ['add-structure', 'add-primitive', 'add-generalization'].forEach((action) => {
    assert.ok(html.includes(`data-uml-action="${action}"`));
    assert.ok(html.includes(`data-uml-context-action="${action}"`));
    assert.ok(!renderer.buildHtml({ id: 'legacy' }, state).includes(`data-uml-action="${action}"`));
  });
  renderer._editState = null;
  renderer._associationMode = null;
  renderer.handleAction(taskId, 'add-generalization', container);
  renderer.completeAssociation(taskId, container, 'parent', 'child');
  state = renderer.getState(taskId);
  assert.equal(state.relationships.length, 1);
  assert.equal(state.relationships[0].type, 'generalization');
  assert.equal(state.relationships[0].targetId, 'parent');
  renderer.completeAssociation(taskId, container, 'child', 'parent');
  assert.equal(renderer.getState(taskId).relationships.length, 1, 'Inheritance cycles must be rejected');
  assert.ok(renderer._associationHint.includes('Zyklus'));
  renderer.completeAssociation(taskId, container, 'parent', 'child');
  assert.equal(renderer.getState(taskId).relationships.length, 1, 'Duplicate generalizations must be rejected');
  renderer.completeAssociation(taskId, container, structure.id, 'child');
  assert.equal(renderer.getState(taskId).relationships.length, 1, 'Datatypes cannot be superclasses');
  renderer.completeAssociation(taskId, container, 'child', 'child');
  assert.equal(renderer.getState(taskId).relationships.length, 1, 'Self inheritance must be rejected');
  renderer._associationMode = null;
  const inspector = { innerHTML: '', querySelector: (selector) => {
    if (selector.includes('="abstract"')) return { checked: true };
    return null;
  } };
  context.document = { getElementById: () => inspector };
  renderer._selectedNodeId = 'child';
  renderer._selectedRelationId = null;
  renderer.renderInspector(task, state);
  assert.ok(!inspector.innerHTML.includes('data-uml-editor-field="abstract"'));
  renderer._selectedNodeId = 'parent';
  renderer.renderInspector(task, state);
  assert.ok(inspector.innerHTML.includes('data-uml-editor-field="abstract"'));
  renderer.updateSelectedNodeFromInspector(taskId, container);
  state = renderer.getState(taskId);
  assert.equal(state.nodes[1].abstract, true);
  assert.ok(renderer.buildHtml(task, state).includes('abstract'));
  renderer._selectedNodeId = null;
  renderer._selectedRelationId = state.relationships[0].id;
  renderer.renderInspector(task, state);
  assert.ok(inspector.innerHTML.includes('Generalisierung'));
  assert.ok(!inspector.innerHTML.includes('data-uml-relation-field="sourceCardinality"'));
  const roundTrip = normalize(JSON.parse(JSON.stringify(state)));
  assert.equal(roundTrip.nodes[1].abstract, true);
  assert.equal(roundTrip.nodes.find((node) => node.id === primitive.id).type, 'primitive');
  assert.equal(roundTrip.relationships[0].type, 'generalization');
  renderer.handleDeleteSelection(taskId, container);
  assert.equal(renderer.getState(taskId).nodes[1].abstract, false, 'Abstract is only allowed on a superclass');
  const disabledTask = 'uml-disabled-features';
  renderer._renderTasks[disabledTask] = { id: disabledTask };
  renderer._stateCache[disabledTask] = normalize(null);
  ['add-structure', 'add-primitive', 'add-generalization'].forEach((action) => {
    renderer.handleAction(disabledTask, action, container);
  });
  assert.equal(alerts.length, 3);
  assert.equal(renderer.getState(disabledTask).nodes.length, 0);
  Object.assign(renderer, saved);
  context.document = previousDocument;
  context.window.alert = previousAlert;
  [taskId, disabledTask].forEach((id) => {
    delete renderer._renderTasks[id];
    delete renderer._stateCache[id];
  });
}

testDatatypesAndGeneralization();
console.log('UML datatype and generalization tests passed.');

function testNewAssociationRemainsSelectedForCardinalityEditing() {
  const renderer = context.window.UmlRenderer;
  const previousSetTimeout = context.setTimeout;
  context.setTimeout = () => {};
  const taskId = 'uml-association-selection-test';
  const previousAttachInspectorEvents = renderer.attachInspectorEvents;
  const previousAttachContextMenuEvents = renderer.attachContextMenuEvents;
  const previousAttachTypeComboEvents = renderer.attachTypeComboEvents;
  const previousResolveNodeIdFromEvent = renderer.resolveNodeIdFromEvent;
  const previousAddAttribute = renderer.addAttribute;
  const previousRender = renderer.render;
  const previousPersistState = renderer.persistState;
  const previousSelectedNodeId = renderer._selectedNodeId;
  const previousSelectedRelationId = renderer._selectedRelationId;
  const previousSelectedAttributePath = renderer._selectedAttributePath;
  const previousSelectedMethodPath = renderer._selectedMethodPath;
  const previousEditState = renderer._editState;
  const previousAssociationMode = renderer._associationMode;
  const previousDocument = context.document;
  const state = normalize({
    nodes: [
      { id: 'class-source', name: 'Kunde', x: 24, y: 24 },
      { id: 'class-target', name: 'Bestellung', x: 264, y: 24 }
    ],
    relationships: []
  });
  renderer._stateCache[taskId] = state;
  renderer._renderTasks[taskId] = { id: taskId };
  renderer._associationMode = { phase: 'awaiting-target', points: [], startNodeId: 'class-source' };
  renderer._selectedNodeId = 'class-target';
  renderer._selectedRelationId = null;
  renderer.attachInspectorEvents = () => {};
  renderer.attachContextMenuEvents = () => {};
  renderer.attachTypeComboEvents = () => {};
  renderer.resolveNodeIdFromEvent = (_id, _container, event) => event.target.nodeId;
  renderer.render = () => {};
  renderer.persistState = () => Promise.resolve(true);
  context.document = { addEventListener() {} };
  const listeners = {};
  const canvasArea = { addEventListener() {}, getBoundingClientRect: () => ({ left: 0, top: 0 }) };
  const container = {
    dataset: { umlTaskId: taskId },
    querySelector(selector) {
      return selector === '.uml-canvas-area' ? canvasArea : null;
    },
    querySelectorAll: () => [],
    addEventListener(type, listener) {
      listeners[type] = listener;
    }
  };
  const previousTaskId = 'uml-previous-task';
  renderer._renderTasks[previousTaskId] = { id: previousTaskId };
  container.dataset.umlTaskId = previousTaskId;
  renderer.attachEvents({ id: previousTaskId }, container, state);
  container.dataset.umlTaskId = taskId;
  let attributeTaskId = null;
  renderer.addAttribute = (id) => { attributeTaskId = id; };
  listeners.click({
    target: {
      closest: (selector) => selector === '[data-uml-add-attribute]'
        ? { getAttribute: () => 'class-target' }
        : null
    },
    stopPropagation() {}
  });
  assert.equal(attributeTaskId, taskId, 'Attribute actions must use the current task after switching');
  const targetNode = { nodeId: 'class-target' };
  const event = {
    button: 0,
    target: {
      nodeId: 'class-target',
      closest(selector) {
        return selector === '.uml-node' ? targetNode : null;
      }
    },
    clientX: 264,
    clientY: 80,
    preventDefault() {},
    stopPropagation() {}
  };
  listeners.pointerdown(event);
  const relation = renderer.getState(taskId).relationships[0];
  assert.ok(relation);
  assert.equal(renderer._selectedRelationId, relation.id);
  assert.equal(renderer._selectedNodeId, null);

  renderer.addAttribute = previousAddAttribute;
  listeners.click({
    target: {
      closest: (selector) => selector === '[data-uml-add-attribute]'
        ? { getAttribute: () => 'class-target' }
        : null
    },
    stopPropagation() {}
  });
  assert.equal(renderer.getState(taskId).nodes[1].attributes.length, 1);
  assert.equal(renderer._editState.mode, 'attribute');
  assert.equal(renderer._selectedRelationId, null);
  const previousQuerySelector = container.querySelector;
  container.querySelector = (selector) => {
    if (selector.startsWith('[data-uml-inline-attribute-name=')) return { value: 'nachname' };
    if (selector.startsWith('[data-uml-inline-attribute-type=')) return { value: 'String' };
    return previousQuerySelector(selector);
  };
  renderer.commitInlineEdit(taskId, container);
  const attribute = renderer.getState(taskId).nodes[1].attributes[0];
  assert.equal(attribute.name, 'nachname');
  assert.equal(attribute.type, 'String');
  assert.equal(attribute.stringLength, 30);
  assert.equal(renderer._stateCache[previousTaskId], undefined, 'Previous task model must remain untouched');
  container.querySelector = previousQuerySelector;

  renderer.attachInspectorEvents = previousAttachInspectorEvents;
  renderer.attachContextMenuEvents = previousAttachContextMenuEvents;
  renderer.attachTypeComboEvents = previousAttachTypeComboEvents;
  renderer.resolveNodeIdFromEvent = previousResolveNodeIdFromEvent;
  renderer.addAttribute = previousAddAttribute;
  renderer.render = previousRender;
  renderer.persistState = previousPersistState;
  renderer._selectedNodeId = previousSelectedNodeId;
  renderer._selectedRelationId = previousSelectedRelationId;
  renderer._selectedAttributePath = previousSelectedAttributePath;
  renderer._selectedMethodPath = previousSelectedMethodPath;
  renderer._editState = previousEditState;
  renderer._associationMode = previousAssociationMode;
  context.document = previousDocument;
  context.setTimeout = previousSetTimeout;
  delete renderer._stateCache[taskId];
  delete renderer._renderTasks[taskId];
  delete renderer._renderTasks[previousTaskId];
}

testNewAssociationRemainsSelectedForCardinalityEditing();
console.log('UML association selection tests passed.');

function testContextMenuDelegationSurvivesCanvasReplacement() {
  const renderer = context.window.UmlRenderer;
  const previousRender = renderer.render;
  const previousSelectedNodeId = renderer._selectedNodeId;
  const previousSelectedRelationId = renderer._selectedRelationId;
  const previousSelectedAttributePath = renderer._selectedAttributePath;
  const previousRenderTasks = { ...renderer._renderTasks };
  const listeners = {};
  let menu = { style: {} };
  let renderCount = 0;
  const renderedTaskIds = [];
  const container = {
    dataset: { umlTaskId: 'uml-context-menu-test' },
    addEventListener(type, listener) {
      listeners[type] = listener;
    },
    querySelector(selector) {
      return selector === '#uml-context-menu' ? menu : null;
    }
  };
  const task = { id: 'uml-context-menu-test' };
  renderer._renderTasks[task.id] = task;
  renderer.render = (renderedTask) => {
    renderedTaskIds.push(renderedTask.id);
    renderCount += 1;
    menu = { style: {} };
  };
  renderer.attachContextMenuEvents(task, container);
  renderer.attachContextMenuEvents(task, container);
  assert.equal(Object.keys(listeners).length, 1);
  assert.equal(typeof listeners.contextmenu, 'function');

  function dispatchContextMenu(target, clientX, clientY) {
    let prevented = false;
    listeners.contextmenu({
      target,
      clientX,
      clientY,
      preventDefault() {
        prevented = true;
      }
    });
    return prevented;
  }

  const blankCanvasTarget = {
    closest(selector) {
      return selector === '.uml-canvas-area' ? {} : null;
    }
  };
  assert.equal(dispatchContextMenu(blankCanvasTarget, 80, 90), true);
  assert.equal(renderCount, 1);
  assert.deepEqual(renderedTaskIds, ['uml-context-menu-test']);
  assert.deepEqual(menu.style, { left: '80px', top: '90px', display: 'block' });

  const currentTask = { id: 'uml-context-menu-next-task' };
  renderer._renderTasks[currentTask.id] = currentTask;
  container.dataset.umlTaskId = currentTask.id;
  const node = { getAttribute: () => 'class-after-rerender' };
  const replacementCanvasTarget = {
    closest(selector) {
      if (selector === '.uml-canvas-area') return {};
      if (selector === '.uml-node') return node;
      return null;
    }
  };
  assert.equal(dispatchContextMenu(replacementCanvasTarget, 120, 130), true);
  assert.equal(renderCount, 2);
  assert.deepEqual(renderedTaskIds, ['uml-context-menu-test', 'uml-context-menu-next-task']);
  assert.equal(renderer._selectedNodeId, 'class-after-rerender');
  assert.deepEqual(menu.style, { left: '120px', top: '130px', display: 'block' });

  renderer.render = previousRender;
  renderer._selectedNodeId = previousSelectedNodeId;
  renderer._selectedRelationId = previousSelectedRelationId;
  renderer._selectedAttributePath = previousSelectedAttributePath;
  renderer._renderTasks = previousRenderTasks;
}

testContextMenuDelegationSurvivesCanvasReplacement();
console.log('UML context menu delegation tests passed.');

function testRelationshipLabelsAndCardinalitiesRenderOnDiagram() {
  const renderer = context.window.UmlRenderer;
  const previousDocument = context.document;
  const layer = {
    children: [],
    innerHTML: '',
    appendChild(element) {
      this.children.push(element);
    }
  };
  const canvasArea = {
    getBoundingClientRect: () => ({ left: 0, top: 0 })
  };
  const relationshipState = normalize({
    nodes: normalized.nodes.map((node, index) => ({ ...node, x: 24 + index * 240, y: 24 })),
    relationships: [{
      ...normalized.relationships[0],
      sourceCardinality: '1',
      targetCardinality: '0..*',
      points: [{ x: 10, y: 10 }, { x: 50, y: 10 }]
    }]
  });
  const nodeElements = relationshipState.nodes.map((node) => ({
    getAttribute: () => node.id,
    getBoundingClientRect: () => ({
      left: node.x,
      top: node.y,
      width: 200,
      height: 120
    })
  }));
  context.document = {
    ...previousDocument,
    createElement: () => ({
      style: {},
      setAttribute() {},
      addEventListener() {}
    })
  };
  const container = {
    querySelector: (selector) => selector === '.uml-canvas-area' ? canvasArea : layer,
    querySelectorAll: () => nodeElements
  };
  renderer._selectedRelationId = 'rel-customer-orders';
  renderer.renderConnections({ id: 1 }, container, relationshipState);
  const labels = layer.children.filter((element) => element.className === 'uml-relationship-name selected');
  const cardinalities = layer.children.filter((element) => element.className === 'uml-cardinality-label');
  assert.equal(labels.length, 1);
  assert.equal(labels[0].textContent, 'gibt auf');
  assert.equal(cardinalities.length, 2);
  assert.deepEqual(cardinalities.map((element) => element.textContent), ['1', '0..*']);
  assert.ok(
    Math.abs(Number.parseFloat(cardinalities[0].style.top) - Number.parseFloat(cardinalities[1].style.top)) > 20,
    'Cardinality labels on short horizontal relationships should not overlap'
  );
  const originalLine = layer.children.find((element) => element.className.startsWith('uml-relationship-line'));
  assert.equal(originalLine.style.left, '224px', 'Stored endpoints must not override current class bounds');
  const originalWidth = originalLine.style.width;
  relationshipState.nodes[1].x += 100;
  layer.children = [];
  renderer.renderConnections({ id: 1 }, container, relationshipState);
  const movedLine = layer.children.find((element) => element.className.startsWith('uml-relationship-line'));
  assert.notEqual(movedLine.style.width, originalWidth);
  relationshipState.nodes[0].y += 100;
  layer.children = [];
  renderer.renderConnections({ id: 1 }, container, relationshipState);
  const movedSourceLine = layer.children.find((element) => element.className.startsWith('uml-relationship-line'));
  assert.notEqual(movedSourceLine.style.top, originalLine.style.top);
  assert.notEqual(movedSourceLine.style.transform, originalLine.style.transform);
  assert.equal(relationshipState.relationships[0].sourceCardinality, '1');
  assert.equal(relationshipState.relationships[0].targetCardinality, '0..*');
  relationshipState.relationships[0].type = 'generalization';
  layer.children = [];
  renderer.renderConnections({ id: 1 }, container, relationshipState);
  const generalizationLine = layer.children.find((element) => element.className.startsWith('uml-relationship-line'));
  assert.ok(generalizationLine.innerHTML.includes('class="uml-generalization-arrow"'));
  assert.ok(generalizationLine.innerHTML.includes('M17 8L1 1V15Z'));
  assert.equal(layer.children.filter((element) => element.className === 'uml-cardinality-label').length, 0);
  const oldGeneralizationWidth = generalizationLine.style.width;
  relationshipState.nodes[1].x += 100;
  layer.children = [];
  renderer.renderConnections({ id: 1 }, container, relationshipState);
  assert.notEqual(layer.children[0].style.width, oldGeneralizationWidth);
  context.document = previousDocument;
  renderer._selectedRelationId = null;
}

testRelationshipLabelsAndCardinalitiesRenderOnDiagram();
console.log('UML relationship annotation rendering tests passed.');

function testUmlEditingAffordances() {
  const renderer = context.window.UmlRenderer;
  const model = normalize({
    ...sourceModel,
    nodes: sourceModel.nodes.concat([{
      id: 'enum-order-status',
      type: 'enum',
      name: 'OrderStatus',
      attributes: []
    }])
  });
  const html = renderer.buildHtml({ id: 'uml-editing' }, model);
  assert.ok(html.includes('Rechtsklick für Aktionen · Entf löscht'));
  assert.ok(html.includes('data-uml-edit-name="class-customer"'));
  assert.ok(html.includes('data-uml-edit-attribute="class-customer:0"'));
  renderer._selectedAttributePath = { nodeId: 'class-customer', index: 0 };
  const selectedAttributeMarkup = renderer.buildHtml({ id: 'uml-editing' }, model);
  assert.ok(selectedAttributeMarkup.includes('uml-attr-text selected'));
  assert.ok(selectedAttributeMarkup.includes('aria-selected="true"'));
  renderer._selectedAttributePath = null;
  renderer._editState = { mode: 'attribute', nodeId: 'class-customer', index: 0 };
  const inlineEditMarkup = renderer.buildHtml({ id: 'uml-editing' }, model);
  assert.ok(inlineEditMarkup.includes('data-uml-inline-attribute-name="class-customer"'));
  assert.ok(inlineEditMarkup.includes('data-uml-inline-attribute-type="class-customer"'));
  assert.ok(inlineEditMarkup.includes('data-uml-type-combo-toggle'));
  assert.ok(!inlineEditMarkup.includes('datalist'));
  renderer._editState = null;
  assert.ok(html.includes('name:Datentyp'));

  renderer._selectedNodeId = 'class-customer';
  renderer._selectedAttributePath = { nodeId: 'class-customer', index: 0 };
  renderer.renderInspector({ id: 'uml-editing' }, model);
  const inspectorMarkup = context.document.getElementById('output-container').innerHTML;
  assert.ok(!inspectorMarkup.includes('Attribute (je Zeile)'));
  assert.ok(!inspectorMarkup.includes('data-uml-editor-field="attributes"'));
  assert.ok(inspectorMarkup.includes('data-uml-attribute-field="name"'));
  assert.ok(inspectorMarkup.includes('data-uml-attribute-field="type"'));
  assert.ok(inspectorMarkup.includes('data-uml-type-combo-option="OrderStatus">OrderStatus</button>'));
}

testUmlEditingAffordances();
console.log('UML editing affordance tests passed.');

function testDeletingSelectedAttribute() {
  const renderer = context.window.UmlRenderer;
  const taskId = 'uml-delete-attribute-test';
  const state = normalize({
    nodes: [{
      id: 'delete-class',
      name: 'Beispiel',
      attributes: [
        { id: 'keep-attribute', name: 'behalten', type: 'String' },
        { id: 'delete-attribute', name: 'loeschen', type: 'Integer' }
      ]
    }],
    relationships: []
  });
  renderer._stateCache[taskId] = state;
  renderer._selectedNodeId = 'delete-class';
  renderer._selectedRelationId = null;
  renderer._selectedAttributePath = { nodeId: 'delete-class', index: 1 };
  const originalPersistState = renderer.persistState;
  const originalRender = renderer.render;
  renderer.persistState = () => Promise.resolve(true);
  renderer.render = () => {};
  renderer.handleDeleteSelection(taskId, {});
  renderer.persistState = originalPersistState;
  renderer.render = originalRender;
  const remainingAttributes = renderer.getState(taskId).nodes[0].attributes;
  assert.equal(remainingAttributes.length, 1);
  assert.equal(remainingAttributes[0].id, 'keep-attribute');
  assert.equal(renderer._selectedAttributePath, null);
}

testDeletingSelectedAttribute();
console.log('UML selected attribute deletion tests passed.');

function testDeletingClassConfirmsAndRemovesRelationships() {
  const renderer = context.window.UmlRenderer;
  const taskId = 'uml-delete-class-test';
  renderer._stateCache[taskId] = normalize(sourceModel);
  renderer._selectedNodeId = 'class-customer';
  renderer._selectedRelationId = null;
  renderer._selectedAttributePath = null;
  const originalConfirm = context.window.confirm;
  const originalPersistState = renderer.persistState;
  const originalRender = renderer.render;
  let confirmationMessage = '';
  context.window.confirm = (message) => {
    confirmationMessage = message;
    return false;
  };
  renderer.persistState = () => Promise.resolve(true);
  renderer.render = () => {};
  renderer.handleDeleteSelection(taskId, {});
  assert.equal(renderer.getState(taskId).nodes.length, 2);
  assert.equal(renderer.getState(taskId).relationships.length, 1);
  assert.ok(confirmationMessage.includes('Kunde'));
  assert.ok(confirmationMessage.includes('eine verbundene Beziehung'));

  context.window.confirm = () => true;
  renderer.handleDeleteSelection(taskId, {});
  renderer.persistState = originalPersistState;
  renderer.render = originalRender;
  context.window.confirm = originalConfirm;
  const deletedClassState = renderer.getState(taskId);
  assert.equal(deletedClassState.nodes.some((node) => node.id === 'class-customer'), false);
  assert.equal(deletedClassState.relationships.length, 0);
}

testDeletingClassConfirmsAndRemovesRelationships();
console.log('UML class deletion confirmation tests passed.');

function testResetRequiresConfirmation() {
  const renderer = context.window.UmlRenderer;
  const taskId = 'uml-reset-confirmation-test';
  renderer._stateCache[taskId] = normalize(sourceModel);
  const originalConfirm = context.window.confirm;
  const originalPersistState = renderer.persistState;
  const originalRender = renderer.render;
  let confirmationMessage = '';
  let persisted = false;
  let rendered = false;
  context.window.confirm = (message) => {
    confirmationMessage = message;
    return false;
  };
  renderer.persistState = () => {
    persisted = true;
    return Promise.resolve(true);
  };
  renderer.render = () => {
    rendered = true;
  };

  renderer.handleAction(taskId, 'reset', {});
  assert.ok(confirmationMessage.includes('gesamte Diagramm'));
  assert.equal(renderer.getState(taskId).nodes.length, 2);
  assert.equal(renderer.getState(taskId).relationships.length, 1);
  assert.equal(persisted, false);
  assert.equal(rendered, false);

  context.window.confirm = () => true;
  renderer.handleAction(taskId, 'reset', {});
  assert.equal(renderer.getState(taskId).nodes.length, 0);
  assert.equal(renderer.getState(taskId).relationships.length, 0);
  assert.equal(persisted, true);
  assert.equal(rendered, true);

  context.window.confirm = originalConfirm;
  renderer.persistState = originalPersistState;
  renderer.render = originalRender;
}

testResetRequiresConfirmation();
console.log('UML reset confirmation tests passed.');

function testTaskConfigurableUmlSimpleTypes() {
  const renderer = context.window.UmlRenderer;
  const model = normalize({
    nodes: [{ id: 'uml-type-test-class', name: 'Beispiel', attributes: ['feld'] }],
    relationships: []
  });
  renderer._editState = { mode: 'attribute', nodeId: 'uml-type-test-class', index: 0 };
  const defaultHtml = renderer.buildHtml({ id: 'uml-default-types' }, model);
  assert.ok(defaultHtml.includes('data-uml-type-combo-option="String">String</button>'));
  assert.ok(defaultHtml.includes('data-uml-type-combo-option="Currency">Currency</button>'));

  const task = {
    id: 'uml-custom-types',
    uml_template: JSON.stringify({
      version: 1,
      type_config: {
        simple_types: ['String', 'Date'],
        string_length: 50
      }
    })
  };
  const configuredHtml = renderer.buildHtml(task, model);
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="String">String</button>'));
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="Date">Date</button>'));
  assert.ok(!configuredHtml.includes('data-uml-type-combo-option="Integer"'));
  assert.ok(!configuredHtml.includes('data-uml-type-combo-option="Currency"'));
  renderer._editState = null;

  renderer._selectedNodeId = 'uml-type-test-class';
  renderer._selectedAttributePath = { nodeId: 'uml-type-test-class', index: 0 };
  renderer.renderInspector(task, model);
  const inspectorMarkup = context.document.getElementById('output-container').innerHTML;
  assert.ok(inspectorMarkup.includes('data-uml-type-combo-option="String">String</button>'));
  assert.ok(inspectorMarkup.includes('data-uml-type-combo-option="Date">Date</button>'));
  assert.ok(!inspectorMarkup.includes('data-uml-type-combo-option="Integer"'));
}

testTaskConfigurableUmlSimpleTypes();
console.log('Task-configurable UML simple type tests passed.');

function testStringLengthDefaultValueAndContextualDelete() {
  const renderer = context.window.UmlRenderer;
  const normalized = normalize({
    nodes: [{
      id: 'class-with-string',
      name: 'Artikel',
      attributes: [{
        id: 'attr-title',
        name: 'bezeichnung',
        type: 'String(20)',
        default_value: 'unbenannt'
      }]
    }],
    relationships: []
  });
  const attribute = normalized.nodes[0].attributes[0];
  assert.equal(attribute.type, 'String');
  assert.equal(attribute.stringLength, 20);
  assert.equal(attribute.defaultValue, 'unbenannt');

  renderer._selectedNodeId = 'class-with-string';
  renderer._selectedRelationId = null;
  renderer._selectedAttributePath = { nodeId: 'class-with-string', index: 0 };
  renderer.renderInspector({ id: 21, uml_template: { type_config: { string_length: 30 } } }, normalized);
  const inspectorMarkup = context.document.getElementById('output-container').innerHTML;
  assert.ok(inspectorMarkup.includes('data-uml-attribute-field="stringLength" value="20"'));
  assert.ok(inspectorMarkup.includes('data-uml-attribute-field="defaultValue" value="unbenannt"'));

  const selectedHtml = renderer.buildHtml({ id: 21 }, normalized);
  assert.ok(selectedHtml.includes('data-uml-action="delete-selected" aria-label="Auswahl löschen" title="Auswahl löschen">'));
  assert.ok(selectedHtml.includes('bezeichnung : String(20) = unbenannt'));
  renderer._selectedAttributePath = null;
  const unselectedHtml = renderer.buildHtml({ id: 21 }, normalized);
  assert.ok(unselectedHtml.includes('data-uml-action="delete-selected" aria-label="Auswahl löschen" title="Auswahl löschen">'));
  renderer._selectedNodeId = null;
  const noSelectionHtml = renderer.buildHtml({ id: 21 }, normalized);
  assert.ok(noSelectionHtml.includes('data-uml-context-action="delete-selected" disabled'));
  assert.ok(noSelectionHtml.includes('data-uml-action="add-association"'));
  assert.ok(!noSelectionHtml.includes('>↔</button>'));
}

testStringLengthDefaultValueAndContextualDelete();
console.log('UML string attributes and contextual delete tests passed.');

function testUmlStarterModelIsUsedOnlyForFirstOpen() {
  const renderer = context.window.UmlRenderer;
  const starterTaskId = 'uml-starter-test';
  renderer._stateCache[starterTaskId] = undefined;
  renderer._renderTasks[starterTaskId] = {
    id: starterTaskId,
    uml_template: {
      starter_model: {
        nodes: [{ id: 'starter-class', name: 'Kunde', attributes: ['name'] }],
        relationships: []
      }
    }
  };
  context.window.assignmentState = { taskUserAnswers: {} };
  const starterState = renderer.getState(starterTaskId);
  assert.equal(starterState.nodes[0].id, 'starter-class');
  assert.equal(starterState.nodes[0].name, 'Kunde');

  const savedTaskId = 'uml-starter-saved-test';
  renderer._stateCache[savedTaskId] = undefined;
  renderer._renderTasks[savedTaskId] = renderer._renderTasks[starterTaskId];
  renderer._renderTasks[savedTaskId].id = savedTaskId;
  context.window.assignmentState.taskUserAnswers = {
    [savedTaskId]: {
      current_code: JSON.stringify({
        nodes: [{ id: 'saved-class', name: 'Eigener Entwurf', attributes: [] }],
        relationships: []
      })
    }
  };
  assert.equal(renderer.getState(savedTaskId).nodes[0].id, 'saved-class');
  context.window.assignmentState = undefined;
}

testUmlStarterModelIsUsedOnlyForFirstOpen();
console.log('UML starter model tests passed.');

function testNewNodePlacementAvoidsOverlap() {
  const renderer = context.window.UmlRenderer;
  const nodes = [];
  for (let index = 0; index < 5; index += 1) {
    const position = renderer.getNewNodePosition(nodes, 600);
    assert.ok(nodes.every((node) => (
      position.x + 200 <= node.x
      || position.x >= node.x + 200
      || position.y + 120 <= node.y
      || position.y >= node.y + 120
    )), `New node overlaps an existing node at index ${index}`);
    nodes.push({ ...position });
  }
  assert.ok(nodes.some((node) => node.x > 24), 'Expected multiple columns when the canvas allows them');
  assert.ok(nodes.some((node) => node.y > 24), 'Expected a new row after available columns are filled');
}

testNewNodePlacementAvoidsOverlap();
console.log('UML class placement tests passed.');

async function testProjectRenderKeepsPersistenceContext() {
  const renderer = context.window.UmlRenderer;
  const projectId = 'project:test';
  const container = { innerHTML: '', dataset: {} };
  let persistedModel = null;
  const projectTask = {
    id: projectId,
    umlProject: true,
    persistModel: async (model) => {
      persistedModel = model;
    },
    setSaveStatus: () => {}
  };

  renderer.ensureStyles = () => {};
  renderer.renderInspector = () => {};
  renderer.attachEvents = () => {};
  renderer.renderConnections = () => {};

  renderer.render(projectTask, container);
  renderer.render({ id: projectId }, container);
  assert.equal(renderer._renderTasks[projectId].umlProject, true);

  const state = renderer.normalizeModel(sourceModel);
  state.nodes.push({
    id: 'project-structure', type: 'structure', name: 'AdresseT', attributes: [], methods: [], x: 500, y: 200
  }, {
    id: 'project-primitive', type: 'primitive', name: 'Byte', attributes: [], methods: [], x: 740, y: 200
  });
  state.nodes[0].abstract = true;
  state.relationships.push({
    id: 'project-generalization', type: 'generalization',
    sourceId: state.nodes[1].id, targetId: state.nodes[0].id
  });
  renderer.setState(projectId, state);
  assert.equal(await renderer.persistState(projectId, state, true), true);
  assert.equal(JSON.stringify(persistedModel), JSON.stringify(renderer.normalizeModel(state)));
  const reopenedModel = renderer.normalizeModel(JSON.parse(JSON.stringify(persistedModel)));
  assert.equal(reopenedModel.nodes[0].abstract, true);
  assert.equal(reopenedModel.nodes.find((node) => node.id === 'project-primitive').type, 'primitive');
  assert.equal(reopenedModel.relationships.find((relation) => relation.id === 'project-generalization').type, 'generalization');
  const projectHtml = renderer.buildHtml(projectTask, reopenedModel);
  assert.ok(projectHtml.includes('data-uml-action="add-structure"'));
  assert.ok(projectHtml.includes('data-uml-action="add-generalization"'));
}

async function testTaskPromptAndSaveFeedback() {
  const renderer = context.window.UmlRenderer;
  const taskId = 572;
  const task = { id: taskId, task_type: 'uml', task_text: '<Kunde>\nBestellung' };
  const container = {
    innerHTML: '',
    statusElement: { textContent: '' },
    querySelector(selector) {
      return selector === '[data-uml-save-status]' ? this.statusElement : null;
    }
  };
  const html = renderer.buildHtml(task, { version: 2, nodes: [], relationships: [] });
  assert.ok(html.includes('&lt;Kunde&gt;<br>Bestellung'));

  renderer._stateCache[String(taskId)] = { version: 2, nodes: [], relationships: [] };
  renderer._renderTasks[String(taskId)] = { ...task, container };
  context.window.assignmentState = { taskUserAnswers: {} };
  context.fetch = async () => ({
    ok: false,
    json: async () => ({ ok: false, error: 'Testfehler' })
  });

  assert.equal(await renderer.persistState(taskId, renderer.getState(taskId), true), false);
  assert.equal(container.statusElement.textContent, 'Speichern fehlgeschlagen: Testfehler');

  context.fetch = async () => ({
    ok: true,
    json: async () => ({ ok: true })
  });
  assert.equal(await renderer.persistState(taskId, renderer.getState(taskId), true), true);
  assert.equal(container.statusElement.textContent, 'Entwurf gespeichert');
  assert.ok(context.window.assignmentState.taskUserAnswers[taskId].current_code);

  let testModeState = null;
  let testModeFetches = 0;
  context.window.testMode = true;
  context.TestMode = {
    setTaskState: (id, state) => {
      testModeState = { id, ...state };
    }
  };
  context.fetch = async () => {
    testModeFetches += 1;
    throw new Error('Testmodus darf keine API aufrufen.');
  };
  assert.equal(await renderer.persistState(taskId, renderer.getState(taskId), true), true);
  assert.equal(testModeFetches, 0);
  assert.equal(testModeState.id, taskId);
  assert.equal(testModeState.current_code, context.window.assignmentState.taskUserAnswers[taskId].current_code);
  assert.equal(container.statusElement.textContent, 'Entwurf im Testmodus gespeichert');
  delete context.window.testMode;
  delete context.TestMode;
}

testProjectRenderKeepsPersistenceContext()
  .then(() => {
    console.log('UML project persistence context tests passed.');
    return testTaskPromptAndSaveFeedback();
  })
  .then(() => console.log('UML task prompt and save feedback tests passed.'))
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  });
