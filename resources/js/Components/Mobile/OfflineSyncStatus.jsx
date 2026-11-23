import React, { useEffect, useState } from 'react';
import { Wifi, WifiOff, RefreshCw, Download, Database, CheckCircle2 } from 'lucide-react';
import axios from 'axios';
import { OfflineQuestionBank, SyncQueue } from '../../Services/Offline';

export default function OfflineSyncStatus() {
  const [isOnline, setIsOnline] = useState(typeof window !== 'undefined' ? window.navigator?.onLine : true);
  const [syncing, setSyncing] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [stats, setStats] = useState({ questions_count: 0, pending_sync_count: 0 });
  const [statusMessage, setStatusMessage] = useState('');

  const refreshStats = async () => {
    try {
      const s = await OfflineQuestionBank.getStats();
      setStats(s);
    } catch (e) {}
  };

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    SyncQueue.initAutoSync();
    refreshStats();

    const handleSyncComplete = () => {
      refreshStats();
      setStatusMessage('Sync complete!');
      setTimeout(() => setStatusMessage(''), 4000);
    };

    window.addEventListener('passimark:sync-complete', handleSyncComplete);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener('passimark:sync-complete', handleSyncComplete);
    };
  }, []);

  const handleSyncNow = async () => {
    setSyncing(true);
    setStatusMessage('');
    try {
      const res = await SyncQueue.syncNow();
      if (res.status === 'success') {
        setStatusMessage(`Synced ${res.synced} attempt(s) successfully.`);
      } else {
        setStatusMessage(res.message || 'Already up to date.');
      }
      await refreshStats();
    } catch (err) {
      setStatusMessage('Sync failed: ' + (err.message || 'network error'));
    } finally {
      setSyncing(false);
      setTimeout(() => setStatusMessage(''), 5000);
    }
  };

  const handleDownloadBank = async () => {
    setDownloading(true);
    setStatusMessage('Downloading question bank...');
    try {
      const response = await axios.get('/api/mobile/sync/download');
      const updatedStats = await OfflineQuestionBank.cacheQuestionBank(response.data);
      setStats(updatedStats);
      setStatusMessage(`Cached ${updatedStats.questions_count} questions for offline exams.`);
    } catch (err) {
      setStatusMessage('Download failed: ' + (err.message || 'network error'));
    } finally {
      setDownloading(false);
      setTimeout(() => setStatusMessage(''), 5000);
    }
  };

  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-3 text-xs text-slate-300 backdrop-blur">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          {isOnline ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 font-semibold text-emerald-400 border border-emerald-500/20">
              <Wifi className="h-3 w-3" /> Online
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 font-semibold text-amber-400 border border-amber-500/20">
              <WifiOff className="h-3 w-3" /> Offline (Airplane Mode)
            </span>
          )}

          <span className="inline-flex items-center gap-1 text-slate-400">
            <Database className="h-3 w-3 text-brand-400" />
            <span>{stats.questions_count} cached</span>
          </span>

          {stats.pending_sync_count > 0 && (
            <span className="rounded-full bg-brand-500/20 px-2 py-0.5 text-brand-300 font-semibold border border-brand-500/40">
              {stats.pending_sync_count} pending sync
            </span>
          )}
        </div>

        <div className="flex items-center gap-2">
          {statusMessage && (
            <span className="text-brand-300 font-medium truncate max-w-xs">{statusMessage}</span>
          )}

          {isOnline && (
            <>
              <button
                onClick={handleDownloadBank}
                disabled={downloading}
                className="inline-flex items-center gap-1 rounded bg-slate-800 px-2.5 py-1 text-slate-200 hover:bg-slate-700 hover:text-white disabled:opacity-50 transition-colors"
                title="Download Question Bank for Offline Delivery"
              >
                <Download className="h-3 w-3 text-brand-400" />
                {downloading ? 'Downloading...' : 'Cache Bank'}
              </button>

              <button
                onClick={handleSyncNow}
                disabled={syncing || stats.pending_sync_count === 0}
                className="inline-flex items-center gap-1 rounded bg-brand-500/20 border border-brand-500/30 px-2.5 py-1 text-brand-300 hover:bg-brand-500 hover:text-slate-950 disabled:opacity-40 transition-colors"
                title="Synchronize attempts with cloud"
              >
                <RefreshCw className={`h-3 w-3 ${syncing ? 'animate-spin' : ''}`} />
                {syncing ? 'Syncing...' : 'Sync Queue'}
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
