import { SqliteDatabase } from './SqliteDatabase';

/**
 * Manages offline storage and local caching of certification tracks, sessions, and question banks.
 */
export class OfflineQuestionBank {
  static async getDb() {
    return await SqliteDatabase.getDatabase();
  }

  /**
   * Caches full question bank from server download response into local SQLite.
   */
  static async cacheQuestionBank(payload) {
    const db = await this.getDb();

    // Cache tracks
    if (Array.isArray(payload.tracks)) {
      for (const track of payload.tracks) {
        const existing = await db.query('tracks', t => t.id == track.id);
        if (existing.length > 0) {
          await db.update('tracks', track.id, track);
        } else {
          await db.insert('tracks', track);
        }
      }
    }

    // Cache sessions
    if (Array.isArray(payload.sessions)) {
      for (const session of payload.sessions) {
        const existing = await db.query('sessions', s => s.id == session.id);
        if (existing.length > 0) {
          await db.update('sessions', session.id, session);
        } else {
          await db.insert('sessions', session);
        }
      }
    }

    // Cache exams
    if (Array.isArray(payload.exams)) {
      for (const exam of payload.exams) {
        const existing = await db.query('exams', e => e.id == exam.id);
        if (existing.length > 0) {
          await db.update('exams', exam.id, exam);
        } else {
          await db.insert('exams', exam);
        }
      }
    }

    // Cache questions
    if (Array.isArray(payload.questions)) {
      for (const question of payload.questions) {
        const existing = await db.query('questions', q => q.id == question.id);
        if (existing.length > 0) {
          await db.update('questions', question.id, question);
        } else {
          await db.insert('questions', question);
        }
      }
    }

    return await this.getStats();
  }

  static async getTracks() {
    const db = await this.getDb();
    return await db.query('tracks');
  }

  static async getSessions(trackId = null) {
    const db = await this.getDb();
    if (trackId) {
      return await db.query('sessions', s => s.certification_track_id == trackId);
    }
    return await db.query('sessions');
  }

  static async getQuestionsForSession(sessionId) {
    const db = await this.getDb();
    return await db.query('questions', q => q.session_id == sessionId);
  }

  static async getQuestion(questionId) {
    const db = await this.getDb();
    const rows = await db.query('questions', q => q.id == questionId);
    return rows[0] || null;
  }

  static async getStats() {
    const db = await this.getDb();
    const tracks = await db.query('tracks');
    const sessions = await db.query('sessions');
    const questions = await db.query('questions');
    const attempts = await db.query('attempts');
    const queuedSync = await db.query('sync_queue', sq => sq.status === 'pending');

    return {
      tracks_count: tracks.length,
      sessions_count: sessions.length,
      questions_count: questions.length,
      attempts_count: attempts.length,
      pending_sync_count: queuedSync.length,
    };
  }
}
