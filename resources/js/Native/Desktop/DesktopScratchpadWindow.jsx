import React, { useState, useEffect } from 'react';
import { FileText, Save, Trash2 } from 'lucide-react';

export default function DesktopScratchpadWindow() {
  const [notes, setNotes] = useState('');

  useEffect(() => {
    // Listen for scratchpad updates from Tauri IPC
    if (window.__TAURI__?.event?.listen) {
      const unlistenPromise = window.__TAURI__.event.listen('scratchpad-updated', (event) => {
        setNotes(event.payload);
      });
      return () => {
        unlistenPromise.then((fn) => fn());
      };
    }
  }, []);

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-slate-950 text-slate-100 p-4">
      <header className="flex h-8 shrink-0 items-center justify-between border-b border-slate-800 pb-2 text-xs">
        <div className="flex items-center gap-2">
          <FileText className="h-4 w-4 text-purple-400" />
          <span className="font-semibold text-slate-200">Candidate Detached Scratchpad</span>
        </div>
        <button
          onClick={() => setNotes('')}
          className="text-slate-500 hover:text-rose-400 text-xs flex items-center gap-1"
        >
          <Trash2 className="h-3 w-3" /> Clear
        </button>
      </header>

      <textarea
        value={notes}
        onChange={(e) => setNotes(e.target.value)}
        placeholder="Type calculations, subnetting notes, or exam thoughts..."
        className="mt-3 flex-1 w-full resize-none rounded-lg bg-slate-900 p-3 font-mono text-xs text-slate-200 border border-slate-800 focus:outline-none focus:border-brand-500"
      />
    </div>
  );
}
