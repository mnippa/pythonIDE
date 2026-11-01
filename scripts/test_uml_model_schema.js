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
assert.equal(normalized.version, 2);
assert.equal(normalized.nodes[0].id, 'class-customer');
assert.equal(normalized.nodes[0].attributes[0].id, 'attr-email');
assert.ok(normalized.nodes[1].attributes[0].id);
assert.equal(normalized.nodes[1].attributes[1].name, 'gesamtsumme');
assert.equal(normalized.nodes[1].attributes[1].derived, true);
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
  const nodeElements = normalized.nodes.map((node) => ({
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
  renderer.renderConnections({ id: 1 }, container, normalized);
  const labels = layer.children.filter((element) => element.className === 'uml-relationship-name selected');
  const cardinalities = layer.children.filter((element) => element.className === 'uml-cardinality-label');
  assert.equal(labels.length, 1);
  assert.equal(labels[0].textContent, 'gibt auf');
  assert.equal(cardinalities.length, 2);
  assert.deepEqual(cardinalities.map((element) => element.textContent), ['1', '0..*']);
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
  assert.ok(html.includes('Klick: auswählen · Entf: löschen · Doppelklick: bearbeiten'));
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

function testTaskConfigurableUmlSimpleTypes() {
  const renderer = context.window.UmlRenderer;
  const model = normalize({
    nodes: [{ id: 'uml-type-test-class', name: 'Beispiel', attributes: ['feld'] }],
    relationships: []
  });
  renderer._editState = { mode: 'attribute', nodeId: 'uml-type-test-class', index: 0 };
  const defaultHtml = renderer.buildHtml({ id: 'uml-default-types' }, model);
  assert.ok(defaultHtml.includes('data-uml-type-combo-option="String(30)">String(30)</button>'));
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
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="String(50)">String(50)</button>'));
  assert.ok(configuredHtml.includes('data-uml-type-combo-option="Date">Date</button>'));
  assert.ok(!configuredHtml.includes('data-uml-type-combo-option="Integer"'));
  assert.ok(!configuredHtml.includes('data-uml-type-combo-option="Currency"'));
  renderer._editState = null;

  renderer._selectedNodeId = 'uml-type-test-class';
  renderer._selectedAttributePath = { nodeId: 'uml-type-test-class', index: 0 };
  renderer.renderInspector(task, model);
  const inspectorMarkup = context.document.getElementById('output-container').innerHTML;
  assert.ok(inspectorMarkup.includes('data-uml-type-combo-option="String(50)">String(50)</button>'));
  assert.ok(inspectorMarkup.includes('data-uml-type-combo-option="Date">Date</button>'));
  assert.ok(!inspectorMarkup.includes('data-uml-type-combo-option="Integer"'));
}

testTaskConfigurableUmlSimpleTypes();
console.log('Task-configurable UML simple type tests passed.');

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
  renderer.setState(projectId, state);
  assert.equal(await renderer.persistState(projectId, state, true), true);
  assert.equal(JSON.stringify(persistedModel), JSON.stringify(state));
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
