const DB_MODEL_SCHEMA_VERSION = 3;

function createIdFactory() {
  let counter = 0;
  return function createId(prefix) {
    counter += 1;
    return `${prefix}_${counter}`;
  };
}

function asObject(value) {
  return value && typeof value === 'object' && !Array.isArray(value) ? value : null;
}

function asArray(value) {
  return Array.isArray(value) ? value : [];
}

function safeString(value, fallback = '') {
  if (value == null) return fallback;
  return String(value).trim() || fallback;
}

function toBoolean(value, fallback = false) {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') {
    const v = value.trim().toLowerCase();
    if (v === 'true' || v === '1' || v === 'yes') return true;
    if (v === 'false' || v === '0' || v === 'no') return false;
  }
  return fallback;
}

function normalizeType(rawType) {
  const type = safeString(rawType, 'TEXT').toUpperCase();
  if (type === 'AUTO') return 'AUTO';
  if (type === 'TEXT') return 'VARCHAR';
  if (type === 'REAL' || type === 'NUMERIC') return 'FLOAT';
  return type;
}

function normalizeColumnSize(rawType, rawSize) {
  const type = normalizeType(rawType);
  const sizeValue = Number(rawSize);
  if (type === 'AUTO') {
    return 3;
  }
  if (type === 'VARCHAR') {
    return Number.isFinite(sizeValue) && sizeValue > 0 ? Math.floor(sizeValue) : 50;
  }
  if (type === 'INTEGER') {
    const allowedSizes = [1, 2, 3, 4, 8];
    return allowedSizes.includes(Math.floor(sizeValue)) ? Math.floor(sizeValue) : 2;
  }
  return Number.isFinite(sizeValue) && sizeValue > 0 ? Math.floor(sizeValue) : null;
}

function normalizeRow(rawRow) {
  if (!asObject(rawRow)) return {};
  const normalized = {};
  for (const [key, value] of Object.entries(rawRow)) {
    const cleanKey = safeString(key, '');
    if (!cleanKey) continue;
    if (
      value == null ||
      typeof value === 'string' ||
      typeof value === 'number' ||
      typeof value === 'boolean'
    ) {
      normalized[cleanKey] = value;
    } else {
      normalized[cleanKey] = String(value);
    }
  }
  return normalized;
}

function normalizeReference(rawRef) {
  const ref = asObject(rawRef);
  if (!ref) return null;

  const table = safeString(ref.table || ref.ref_table, '');
  const column = safeString(ref.column || ref.ref_column, '');
  if (!table || !column) return null;

  const onUpdate = safeString(ref.onUpdate || ref.on_update, 'NO ACTION').toUpperCase();
  const onDelete = safeString(ref.onDelete || ref.on_delete, 'NO ACTION').toUpperCase();

  return {
    table,
    column,
    onUpdate,
    onDelete
  };
}

function normalizeColumn(rawColumn, createId) {
  const col = asObject(rawColumn) || {};
  const name = safeString(col.name, `column_${createId('name')}`);
  const type = normalizeType(col.type);
  const pk = toBoolean(col.pk ?? col.isPrimaryKey, false);
  const fk = toBoolean(col.fk ?? col.isForeignKey, false);
  const size = normalizeColumnSize(type, col.size ?? col.length);

  let nullable = toBoolean(col.nullable ?? col.isNullable, !pk);
  if (pk) nullable = false;
  if (type === 'AUTO') {
    nullable = false;
  }

  const defaultValue = col.defaultValue ?? col.default ?? '';
  const references = normalizeReference(col.references);

  return {
    id: safeString(col.id, createId('col')),
    name,
    type,
    size,
    pk,
    fk,
    nullable,
    default: defaultValue,
    references
  };
}

function normalizeTable(rawTable, createId) {
  const table = asObject(rawTable) || {};
  const columns = asArray(table.columns).map((c) => normalizeColumn(c, createId));
  const rows = asArray(table.rows).map((r) => normalizeRow(r));

  return {
    id: safeString(table.id, createId('tbl')),
    name: safeString(table.name, `table_${createId('name')}`),
    columns,
    rows
  };
}

function normalizeDatabase(rawDatabase, createId) {
  const db = asObject(rawDatabase) || {};
  const tables = asArray(db.tables).map((t) => normalizeTable(t, createId));

  return {
    id: safeString(db.id, createId('db')),
    name: safeString(db.name, `Database ${createId('name')}`),
    tables
  };
}

function ensureActiveDatabaseIndex(activeDatabaseIndex, databaseCount) {
  if (databaseCount <= 0) return 0;
  const n = Number.isFinite(Number(activeDatabaseIndex)) ? Number(activeDatabaseIndex) : 0;
  if (n < 0) return 0;
  if (n >= databaseCount) return databaseCount - 1;
  return Math.floor(n);
}

function buildCanonicalModel(model) {
  const dbs = asArray(model?.databases).map((db) => ({
    id: safeString(db.id, ''),
    name: safeString(db.name, ''),
    tables: asArray(db.tables).map((table) => ({
      id: safeString(table.id, ''),
      name: safeString(table.name, ''),
      columns: asArray(table.columns).map((col) => ({
        id: safeString(col.id, ''),
        name: safeString(col.name, ''),
        type: normalizeType(col.type),
        size: normalizeColumnSize(col.type, col.size ?? col.length),
        pk: Boolean(col.pk),
        fk: Boolean(col.fk),
        nullable: Boolean(col.nullable),
        default: col.default ?? '',
        references: col.references
          ? {
              table: safeString(col.references.table, ''),
              column: safeString(col.references.column, ''),
              onUpdate: safeString(col.references.onUpdate, 'NO ACTION').toUpperCase(),
              onDelete: safeString(col.references.onDelete, 'NO ACTION').toUpperCase()
            }
          : null
      })),
      rows: asArray(table.rows).map((row) => normalizeRow(row))
    }))
  }));

  return {
    version: DB_MODEL_SCHEMA_VERSION,
    activeDatabaseIndex: ensureActiveDatabaseIndex(model?.activeDatabaseIndex, dbs.length),
    databases: dbs
  };
}

export function createEmptyDbModel() {
  return {
    version: DB_MODEL_SCHEMA_VERSION,
    activeDatabaseIndex: 0,
    databases: [
      {
        id: 'db_1',
        name: 'Zwischenstand 1',
        tables: [
          {
            id: 'tbl_1',
            name: 'student',
            columns: [
              {
                id: 'col_1',
                name: 'id',
                type: 'AUTO',
                size: 3,
                pk: true,
                fk: false,
                nullable: false,
                default: '',
                references: null
              },
              {
                id: 'col_2',
                name: 'name',
                type: 'VARCHAR',
                size: 50,
                pk: false,
                fk: false,
                nullable: false,
                default: '',
                references: null
              }
            ],
            rows: [
              { name: 'Ada' }
            ]
          }
        ]
      }
    ]
  };
}

export function normalizeDbModel(rawModel) {
  const createId = createIdFactory();
  const warnings = [];

  let source = rawModel;
  if (typeof rawModel === 'string') {
    try {
      source = JSON.parse(rawModel);
    } catch (error) {
      return {
        model: createEmptyDbModel(),
        warnings: ['JSON parse failed, using empty model'],
        migratedFromVersion: null,
        parseError: String(error?.message || error)
      };
    }
  }

  const sourceObject = asObject(source);
  if (!sourceObject) {
    return {
      model: createEmptyDbModel(),
      warnings: ['Model was not an object, using empty model'],
      migratedFromVersion: null,
      parseError: null
    };
  }

  const sourceVersion = Number(sourceObject.version || 0) || 0;
  const databases = asArray(sourceObject.databases).map((db) => normalizeDatabase(db, createId));

  const normalized = buildCanonicalModel({
    version: DB_MODEL_SCHEMA_VERSION,
    activeDatabaseIndex: sourceObject.activeDatabaseIndex,
    databases: databases.length > 0 ? databases : createEmptyDbModel().databases
  });

  if (sourceVersion !== DB_MODEL_SCHEMA_VERSION) {
    warnings.push(`Model normalized from version ${sourceVersion || 'unknown'} to ${DB_MODEL_SCHEMA_VERSION}`);
  }

  return {
    model: normalized,
    warnings,
    migratedFromVersion: sourceVersion || null,
    parseError: null
  };
}

export function validateDbModel(model) {
  const errors = [];
  const warnings = [];

  if (!asObject(model)) {
    return {
      ok: false,
      errors: ['Model is not an object'],
      warnings
    };
  }

  if (Number(model.version) !== DB_MODEL_SCHEMA_VERSION) {
    warnings.push(`Expected version ${DB_MODEL_SCHEMA_VERSION}, got ${model.version}`);
  }

  const databases = asArray(model.databases);
  if (databases.length === 0) {
    errors.push('Model must contain at least one database');
  }

  databases.forEach((db, dbIndex) => {
    if (!safeString(db.name, '')) {
      errors.push(`Database[${dbIndex}] requires a non-empty name`);
    }

    const tables = asArray(db.tables);
    const tableNames = new Set();
    tables.forEach((table, tableIndex) => {
      const tableName = safeString(table.name, '');
      if (!tableName) {
        errors.push(`Database[${dbIndex}] Table[${tableIndex}] requires a non-empty name`);
      }
      if (tableNames.has(tableName.toLowerCase())) {
        errors.push(`Database[${dbIndex}] has duplicate table name '${tableName}'`);
      }
      tableNames.add(tableName.toLowerCase());

      const columns = asArray(table.columns);
      if (columns.length === 0) {
        warnings.push(`Table '${tableName || tableIndex}' has no columns`);
      }

      const columnNames = new Set();
      let pkCount = 0;
      columns.forEach((column, columnIndex) => {
        const columnName = safeString(column.name, '');
        if (!columnName) {
          errors.push(`Table '${tableName || tableIndex}' Column[${columnIndex}] requires a non-empty name`);
        }
        if (columnNames.has(columnName.toLowerCase())) {
          errors.push(`Table '${tableName || tableIndex}' has duplicate column '${columnName}'`);
        }
        columnNames.add(columnName.toLowerCase());

        if (toBoolean(column.pk, false)) {
          pkCount += 1;
          if (toBoolean(column.nullable, false)) {
            errors.push(`Table '${tableName || tableIndex}' primary key column '${columnName}' cannot be nullable`);
          }
        }

        if (toBoolean(column.fk, false) && !column.references) {
          warnings.push(`Table '${tableName || tableIndex}' FK column '${columnName}' has no references object`);
        }
      });

      if (pkCount === 0) {
        warnings.push(`Table '${tableName || tableIndex}' has no primary key`);
      }
    });
  });

  const dbCount = databases.length;
  const idx = Number(model.activeDatabaseIndex);
  if (!Number.isFinite(idx) || idx < 0 || idx >= Math.max(dbCount, 1)) {
    errors.push('activeDatabaseIndex is out of range');
  }

  return {
    ok: errors.length === 0,
    errors,
    warnings
  };
}

export function stringifyDbModel(model, pretty = true) {
  const canonical = buildCanonicalModel(model);
  return JSON.stringify(canonical, null, pretty ? 2 : 0);
}

export const DbModelSchema = {
  version: DB_MODEL_SCHEMA_VERSION,
  createEmptyDbModel,
  normalizeDbModel,
  validateDbModel,
  stringifyDbModel
};

if (typeof window !== 'undefined') {
  window.DbModelSchema = DbModelSchema;
}
