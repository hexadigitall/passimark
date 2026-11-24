import React, { useState, useEffect } from 'react';
import { Layers, Maximize2, ZoomIn, ZoomOut, RotateCcw } from 'lucide-react';

export default function DesktopExhibitWindow() {
  const [exhibit, setExhibit] = useState(null);
  const [zoom, setZoom] = useState(1);

  useEffect(() => {
    // Listen for exhibit update event from Tauri IPC
    if (window.__TAURI__?.event?.listen) {
      const unlistenPromise = window.__TAURI__.event.listen('exhibit-updated', (event) => {
        setExhibit(event.payload);
      });
      return () => {
        unlistenPromise.then((fn) => fn());
      };
    }

    // Local storage fallback for browser popup
    const stored = localStorage.getItem('pmk_detached_exhibit');
    if (stored) {
      try {
        setExhibit(JSON.parse(stored));
      } catch (e) {}
    }
  }, []);

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-slate-950 text-slate-100 select-none">
      <header className="flex h-10 shrink-0 items-center justify-between border-b border-slate-800 bg-slate-900 px-3 text-xs">
        <div className="flex items-center gap-2">
          <Layers className="h-4 w-4 text-brand-400" />
          <span className="font-semibold text-slate-200">
            {exhibit?.title || 'Detached Scenario Exhibit (Secondary Monitor)'}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <button 
            onClick={() => setZoom((z) => Math.max(0.5, z - 0.25))}
            className="rounded bg-slate-800 p-1 text-slate-300 hover:bg-slate-700"
          >
            <ZoomOut className="h-3.5 w-3.5" />
          </button>
          <span className="font-mono text-[10px] text-slate-400">{Math.round(zoom * 100)}%</span>
          <button 
            onClick={() => setZoom((z) => Math.min(3, z + 0.25))}
            className="rounded bg-slate-800 p-1 text-slate-300 hover:bg-slate-700"
          >
            <ZoomIn className="h-3.5 w-3.5" />
          </button>
          <button 
            onClick={() => setZoom(1)}
            className="rounded bg-slate-800 p-1 text-slate-300 hover:bg-slate-700"
          >
            <RotateCcw className="h-3.5 w-3.5" />
          </button>
        </div>
      </header>

      <div className="flex flex-1 items-center justify-center overflow-auto p-6 bg-slate-950/90">
        {exhibit?.src ? (
          <div style={{ transform: `scale(${zoom})`, transformOrigin: 'center center', transition: 'transform 0.15s ease-out' }}>
            <img 
              src={exhibit.src} 
              alt={exhibit.title} 
              className="max-h-[85vh] max-w-[85vw] rounded-lg border border-slate-800 shadow-2xl object-contain"
            />
          </div>
        ) : (
          <div className="max-w-lg text-center text-slate-500">
            <Layers className="mx-auto h-12 w-12 text-slate-700 mb-3" />
            <h3 className="text-sm font-semibold text-slate-300">Detached Secondary Exhibit Monitor Active</h3>
            <p className="mt-1 text-xs text-slate-500">
              When an exhibit question is viewed in the primary exam player window, network diagrams and topology maps will display here automatically.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
