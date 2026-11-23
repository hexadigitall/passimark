import axios from 'axios';
import { SqliteDatabase } from './SqliteDatabase';

/**
 * Manages the offline background synchronization queue.
 * Transmits queued candidate attempts and answer logs to remote Laravel backend when connectivity is restored.
 */
export class SyncQueue {
  static async getDb() {
    return await SqliteDatabase.getDatabase();
  }

  /**
   * Enqueues an offline attempt into the persistent sync queue.
   */
  static async enqueueAttempt(attemptPayload) {
    const db = await this.getDb();
    await db.insert('sync_queue', {
      action: 'upload_attempt',
      payload: JSON.stringify(attemptPayload),
      status: 'pending',
      attempts_count: 0,
      created_at: new Date().toISOString(),
    });
  }

  /**
   * Synchronizes all pending items with the cloud server.
   */
  static async syncNow() {
    if (typeof window !== 'undefined' && !window.navigator?.onLine) {
      return { synced: 0, status: 'offline', message: 'Device is offline.' };
    }

    const db = await this.getDb();
    const pendingItems = await db.query('sync_queue', item => item.status === 'pending');

    if (pendingItems.length === 0) {
      return { synced: 0, status: 'idle', message: 'Sync queue is empty.' };
    }

    const attemptsToUpload = [];
    for (const item of pendingItems) {
      try {
        const payload = JSON.parse(item.payload);
        attemptsToUpload.push(payload);
      } catch (e) {}
    }

    if (attemptsToUpload.length === 0) {
      return { synced: 0, status: 'idle' };
    }

    try {
      const response = await axios.post('/api/mobile/sync/upload', {
        attempts: attemptsToUpload,
      }, {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      const data = response.data;

      // Mark all successfully synced attempts in local DB
      for (const item of pendingItems) {
        await db.update('sync_queue', item.id, {
          status: 'synced',
          synced_at: new Date().toISOString(),
        });
      }

      for (const att of attemptsToUpload) {
        await db.update('attempts', att.local_id, { synced: 1 });
      }

      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('passimark:sync-complete', { detail: data }));
      }

      return {
        synced: data.synced_count || attemptsToUpload.length,
        status: 'success',
        certificates_minted: data.certificates_minted || [],
      };
    } catch (err) {
      for (const item of pendingItems) {
        await db.update('sync_queue', item.id, {
          attempts_count: (item.attempts_count || 0) + 1,
        });
      }
      return {
        synced: 0,
        status: 'error',
        error: err.response?.data?.message || err.message,
      };
    }
  }

  /**
   * Initializes automatic online/offline listeners.
   */
  static initAutoSync() {
    if (typeof window === 'undefined') return;

    window.addEventListener('online', () => {
      this.syncNow().catch(() => {});
    });

    // Check every 60 seconds when online
    setInterval(() => {
      if (window.navigator?.onLine) {
        this.syncNow().catch(() => {});
      }
    }, 60000);
  }
}
