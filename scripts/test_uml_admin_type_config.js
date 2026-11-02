const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const adminPath = path.join(__dirname, '..', 'public', 'js', 'admin-dashboard.js');
const source = fs.readFileSync(adminPath, 'utf8');
const adminHtml = fs.readFileSync(path.join(__dirname, '..', 'public', 'admin.php'), 'utf8');
const start = source.indexOf('function parseUmlTemplate');
const end = source.indexOf('function normalizeFileSubmissionMaxSize');
assert.ok(start >= 0 && end > start, 'UML admin configuration helpers should be present');
assert.equal((adminHtml.match(/data-uml-type-group="primitive"/g) || []).length, 2);
assert.equal((adminHtml.match(/data-uml-type-group="complex"/g) || []).length, 2);
assert.equal((adminHtml.match(/data-uml-type-group="enumeration"/g) || []).length, 2);
assert.equal((adminHtml.match(/data-uml-methods-enabled/g) || []).length, 2);
['structures', 'custom-simple-types', 'generalization'].forEach((feature) => {
  assert.equal((adminHtml.match(new RegExp(`data-uml-${feature}-enabled`, 'g')) || []).length, 2);
});
assert.equal((adminHtml.match(/data-uml-primitive-types/g) || []).length, 2);
assert.ok(!adminHtml.includes('data-uml-simple-type='));

const context = {
  Array,
  Number,
  String,
  Set,
  JSON,
  console,
  DEFAULT_UML_PRIMITIVE_TYPES: ['String', 'Integer', 'Boolean', 'Float', 'Date', 'Currency', 'Time', 'Datetime']
};
vm.runInNewContext(source.slice(start, end), context, { filename: adminPath });

function makeForm() {
  const groups = ['primitive', 'complex', 'enumeration'].map((group) => ({
    group,
    checked: false,
    getAttribute: () => group
  }));
  const primitiveTypes = { value: '' };
  const stringLength = { value: '' };
  const starterModel = { value: '' };
  const methodsEnabled = { checked: false };
  const features = {
    '[data-uml-structures-enabled]': { checked: false },
    '[data-uml-custom-simple-types-enabled]': { checked: false },
    '[data-uml-generalization-enabled]': { checked: false }
  };
  return {
    groups,
    primitiveTypes,
    stringLength,
    starterModel,
    methodsEnabled,
    features,
    querySelector(selector) {
      return {
        '[data-uml-primitive-types]': primitiveTypes,
        '[data-uml-string-length]': stringLength,
        '[data-uml-starter-model]': starterModel,
        '[data-uml-methods-enabled]': methodsEnabled,
        ...features
      }[selector] || null;
    },
    querySelectorAll(selector) {
      return selector === '[data-uml-type-group]' ? groups : [];
    }
  };
}

const newTaskForm = makeForm();
context.setUmlTypeConfigForm(newTaskForm);
assert.deepEqual(newTaskForm.groups.map((group) => group.checked), [false, false, false]);
assert.equal(newTaskForm.primitiveTypes.value, 'String; Integer; Boolean; Float; Date; Currency; Time; Datetime');
assert.equal(newTaskForm.methodsEnabled.checked, false);
Object.values(newTaskForm.features).forEach((input) => assert.equal(input.checked, false));

const legacyForm = makeForm();
context.setUmlTypeConfigForm(legacyForm, JSON.stringify({
  type_config: { simple_types: ['String', 'Money'], string_length: 45 }
}));
assert.deepEqual(legacyForm.groups.map((group) => group.checked), [true, true, true]);
assert.equal(legacyForm.primitiveTypes.value, 'String; Money');
assert.equal(legacyForm.stringLength.value, '45');
assert.equal(legacyForm.methodsEnabled.checked, false);
legacyForm.methodsEnabled.checked = true;

legacyForm.primitiveTypes.value = ' Decimal ; String; Decimal ;;';
legacyForm.groups[0].checked = true;
legacyForm.groups[1].checked = false;
legacyForm.groups[2].checked = true;
legacyForm.methodsEnabled.checked = true;
const saved = context.getUmlTypeConfigFromForm(legacyForm);
assert.deepEqual(Array.from(saved.type_config.primitive_types), ['Decimal', 'String']);
assert.deepEqual(Array.from(saved.type_config.simple_types), ['Decimal', 'String']);
assert.equal(saved.type_config.primitive_types_enabled, true);
assert.equal(saved.type_config.complex_types_enabled, false);
assert.equal(saved.type_config.enumeration_types_enabled, true);
assert.equal(saved.type_config.methods_enabled, true);
assert.equal(saved.type_config.string_length, 45);
assert.equal(saved.type_config.structures_enabled, false);
assert.equal(saved.type_config.custom_simple_types_enabled, false);
assert.equal(saved.type_config.generalization_enabled, false);
Object.values(legacyForm.features).forEach((input) => { input.checked = true; });
const fullConfig = context.getUmlTypeConfigFromForm(legacyForm);
const fullForm = makeForm();
context.setUmlTypeConfigForm(fullForm, JSON.stringify(fullConfig));
Object.values(fullForm.features).forEach((input) => assert.equal(input.checked, true));

const existingMethodsForm = makeForm();
context.setUmlTypeConfigForm(existingMethodsForm, JSON.stringify({
  type_config: { methods_enabled: true }
}));
assert.equal(existingMethodsForm.methodsEnabled.checked, true);

console.log('UML admin type configuration tests passed.');
