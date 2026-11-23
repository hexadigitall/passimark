import React from 'react';
import { X, Keyboard, Command } from 'lucide-react';

export default function DesktopShortcutsModal({ isOpen, onClose }) {
  if (!isOpen) return null;

  const shortcuts = [
    { key: '1 - 9', desc: 'Select Option 1 through 9 instantly' },
    { key: 'A - I', desc: 'Select Option A through I instantly' },
    { key: 'Ctrl + F', desc: 'Toggle Flag on current question' },
    { key: 'Ctrl + S', desc: 'Strikethrough focused choice' },
    { key: 'Ctrl + P', desc: 'Open / Close Question Palette' },
    { key: 'Ctrl + K / Alt + C', desc: 'Open Scientific & Subnetting Calculator' },
    { key: 'Ctrl + Enter', desc: 'Submit / Save Answer' },
    { key: '← / →', desc: 'Navigate to Previous / Next question (Free-Roam)' },
    { key: 'Esc', desc: 'Pause Assessment' },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
      <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
          <div className="flex items-center gap-2">
            <Keyboard className="h-5 w-5 text-brand-400" />
            <h3 className="text-base font-bold text-white">VCE Keyboard Shortcuts</h3>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <p className="mt-3 text-xs text-slate-400">
          Passimark Desktop provides full single-key selection and proctoring shortcuts identical to legacy VCE Player.
        </p>

        <div className="mt-4 space-y-2 max-h-72 overflow-y-auto pr-1">
          {shortcuts.map((sc) => (
            <div
              key={sc.key}
              className="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2 text-xs"
            >
              <span className="text-slate-300">{sc.desc}</span>
              <kbd className="rounded border border-slate-700 bg-slate-800 px-2 py-0.5 font-mono text-[11px] font-bold text-brand-300">
                {sc.key}
              </kbd>
            </div>
          ))}
        </div>

        <div className="mt-5 border-t border-slate-800 pt-3 flex justify-end">
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg bg-brand-500 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-brand-400 transition"
          >
            Got it
          </button>
        </div>
      </div>
    </div>
  );
}
