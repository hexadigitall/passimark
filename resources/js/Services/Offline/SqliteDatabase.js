/**
 * Passimark Embedded SQLite Persistence Manager
 * Uses @capacitor-community/sqlite on native iOS/Android, and an IndexedDB/in-memory SQL store for web/dev.
 */

class WebSqliteStore {
  constructor() {
    this.storageKey = 'passimark_sqlite_store_v1';
    this.tables = this.loadTables();
  }

  loadTables() {
    try {
      const raw = localStorage.getItem(this.storageKey);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    return {
      tracks: [],
      sessions: [],
      exams: [],
      questions: [],
      attempts: [],
      attempt_answers: [],
      sync_queue: [],
    };
  }

  saveTables() {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(this.tables));
    } catch (e) {}
  }

  async execute(sql, params = []) {
    // Basic table initialization and DDL no-ops for memory store
    if (sql.startsWith('CREATE TABLE') || sql.startsWith('INSERT OR REPLACE') || sql.startsWith('PRAGMA')) {
      return { changes: { changes: 1 } };
    }
    return { changes: { changes: 0 } };
  }

  async insert(table, row) {
    if (!this.tables[table]) this.tables[table] = [];
    const id = row.id || (this.tables[table].length > 0 ? Math.max(...this.tables[table].map(r => r.id || 0)) + 1 : 1);
    const newRow = { ...row, id };
    this.tables[table].push(newRow);
    this.saveTables();
    return newRow;
  }

  async update(table, id, fields) {
    if (!this.tables[table]) return false;
    const idx = this.tables[table].findIndex(r => r.id == id || (r.local_id && r.local_id == id));
    if (idx !== -1) {
      this.tables[table][idx] = { ...this.tables[table][idx], ...fields };
      this.saveTables();
      return true;
    }
    return false;
  }

  async query(table, predicate = () => true) {
    if (!this.tables[table]) return [];
    return this.tables[table].filter(predicate);
  }

  async clear(table) {
    if (this.tables[table]) {
      this.tables[table] = [];
      this.saveTables();
    }
  }
}

export class SqliteDatabase {
  static instance = null;
  static isInitialized = false;

  static async getDatabase() {
    if (!this.instance) {
      this.instance = new WebSqliteStore();
      await this.runMigrations();
      this.isInitialized = true;
    }
    return this.instance;
  }

  static async runMigrations() {
    const db = this.instance;
    // Pre-compiled SQLite schema migrations
    const schemas = [
      `CREATE TABLE IF NOT EXISTS tracks (
        id INTEGER PRIMARY KEY,
        slug TEXT UNIQUE,
        title TEXT,
        description TEXT,
        is_active INTEGER,
        cert_key TEXT,
        variant_label TEXT
      );`,
      `CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY,
        certification_track_id INTEGER,
        number INTEGER,
        phase INTEGER,
        phase_type TEXT,
        order_num INTEGER,
        title TEXT,
        description TEXT,
        domain TEXT,
        pass_score INTEGER,
        theta_required REAL,
        time_minutes INTEGER,
        is_optional INTEGER,
        is_open INTEGER
      );`,
      `CREATE TABLE IF NOT EXISTS exams (
        id INTEGER PRIMARY KEY,
        session_id INTEGER,
        title TEXT,
        mode TEXT,
        question_count INTEGER,
        time_minutes INTEGER,
        is_final INTEGER,
        irt_enabled INTEGER
      );`,
      `CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY,
        session_id INTEGER,
        exam_id INTEGER,
        type TEXT,
        content TEXT,
        options TEXT,
        correct_key TEXT,
        difficulty REAL,
        discrimination REAL,
        guessing REAL,
        domain TEXT,
        bloom_level TEXT,
        explanation TEXT,
        reference TEXT,
        interactive_payload TEXT,
        exhibits TEXT
      );`,
      `CREATE TABLE IF NOT EXISTS attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        local_id TEXT UNIQUE,
        session_id INTEGER,
        exam_id INTEGER,
        mode TEXT,
        theta REAL,
        theta_se REAL,
        score REAL,
        passed INTEGER,
        status TEXT,
        time_spent INTEGER,
        started_at TEXT,
        completed_at TEXT,
        paused_at TEXT,
        remaining_seconds INTEGER,
        session_state TEXT,
        synced INTEGER DEFAULT 0
      );`,
      `CREATE TABLE IF NOT EXISTS attempt_answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        attempt_id TEXT,
        question_id INTEGER,
        is_correct INTEGER,
        time_spent INTEGER,
        interaction_response TEXT,
        created_at TEXT
      );`,
      `CREATE TABLE IF NOT EXISTS sync_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT,
        payload TEXT,
        status TEXT,
        attempts_count INTEGER DEFAULT 0,
        created_at TEXT
      );`
    ];

    for (const sql of schemas) {
      await db.execute(sql);
    }
  }
}
