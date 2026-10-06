import {
  DbModelSchema,
  normalizeDbModel,
  stringifyDbModel,
  validateDbModel,
  createEmptyDbModel
} from '../public/js/db-model-schema.js';

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function run() {
  const empty = createEmptyDbModel();
  const emptyValidation = validateDbModel(empty);
  assert(emptyValidation.ok, 'empty template model should validate');

  const legacyV2 = {
    version: 2,
    activeDatabaseIndex: 0,
    databases: [
      {
        name: 'Legacy DB',
        tables: [
          {
            name: 'student',
            columns: [
              { name: 'id', type: 'AUTO', pk: true, fk: false, default: '' },
              { name: 'name', type: 'TEXT', pk: false, fk: false, default: '' }
            ],
            rows: [
              { name: 'Ada' }
            ]
          }
        ]
      }
    ]
  };

  const migrated = normalizeDbModel(legacyV2);
  assert(Number(migrated.model.version) === 3, 'legacy model should migrate to v3');
  assert(migrated.model.databases[0].tables[0].columns[0].type === 'INTEGER', 'AUTO should normalize to INTEGER');

  const migratedValidation = validateDbModel(migrated.model);
  assert(migratedValidation.ok, 'migrated model should validate');

  const jsonA = stringifyDbModel(migrated.model, true);
  const jsonB = stringifyDbModel(migrated.model, true);
  assert(jsonA === jsonB, 'stringify must be deterministic for same model');

  const broken = {
    version: 3,
    activeDatabaseIndex: 5,
    databases: [
      {
        name: 'X',
        tables: [
          {
            name: 't',
            columns: [
              { name: 'id', type: 'INTEGER', pk: true, nullable: true }
            ],
            rows: []
          }
        ]
      }
    ]
  };
  const brokenValidation = validateDbModel(broken);
  assert(!brokenValidation.ok, 'broken model should fail validation');

  console.log('[db-model-schema test] OK');
  console.log(`Schema version: v${DbModelSchema.version}`);
}

try {
  run();
} catch (error) {
  console.error('[db-model-schema test] FAILED:', error.message || error);
  process.exit(1);
}
