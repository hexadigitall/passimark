import React, { useState } from 'react';
import { X, ChevronUp, Flag, CheckCircle2 } from 'lucide-react';

export default function BottomSheetPalette({
  isOpen,
  onClose,
  totalQuestions,
  activePosition,
  answeredCount,
  flaggedCount,
  paletteItems = [],
  onJumpToQuestion,
  isCat = false,
  paletteStyle = {},
}) {
  const [filter, setFilter] = useState('all'); // all, unanswered, flagged

  if (!isOpen) return null;

  const filtered = paletteItems.filter((item) => {
    if (filter === 'unanswered') return item.status === 'unanswered';
    if (filter === 'flagged') return item.isFlagged;
    return true;
  });

  return (
    <div className="fixed inset-0 z-50 flex flex-col justify-end bg-black/70 backdrop-blur-sm transition-opacity">
      {/* Backdrop tap to close */}
      <div className="flex-1" onClick={onClose} />

      {/* Bottom Sheet Drawer */}
      <div className="w-full max-h-[80vh] rounded-t-3xl border-t border-slate-700 bg-slate-900 p-5 shadow-2xl animate-in slide-in-from-bottom duration-200">
        {/* Grab Handle */}
        <div className="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-700" onClick={onClose} />

        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 className="text-base font-bold text-white">Question Navigator</h3>
            <p className="text-xs text-slate-400">
              Item {activePosition} of {totalQuestions} • {answeredCount} answered
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full bg-slate-800 p-1.5 text-slate-400 hover:text-white"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Filter Pills */}
        <div className="mt-3 flex gap-1.5 bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs">
          <button
            type="button"
            onClick={() => setFilter('all')}
            className={`flex-1 py-1.5 rounded-lg font-medium text-center transition ${
              filter === 'all' ? 'bg-slate-800 text-white font-bold' : 'text-slate-400'
            }`}
          >
            All ({totalQuestions})
          </button>
          <button
            type="button"
            onClick={() => setFilter('unanswered')}
            className={`flex-1 py-1.5 rounded-lg font-medium text-center transition ${
              filter === 'unanswered' ? 'bg-slate-800 text-white font-bold' : 'text-slate-400'
            }`}
          >
            Unanswered ({totalQuestions - answeredCount})
          </button>
          <button
            type="button"
            onClick={() => setFilter('flagged')}
            className={`flex-1 py-1.5 rounded-lg font-medium text-center transition ${
              filter === 'flagged' ? 'bg-slate-800 text-white font-bold' : 'text-slate-400'
            }`}
          >
            Flags ({flaggedCount})
          </button>
        </div>

        {/* Touch Grid */}
        <div className="mt-4 grid grid-cols-6 gap-2 max-h-60 overflow-y-auto p-1">
          {filtered.map((item) => (
            <button
              key={item.position}
              type="button"
              onClick={() => {
                if (!isCat || item.position <= activePosition) {
                  onJumpToQuestion(item.index);
                  onClose();
                }
              }}
              disabled={isCat && item.position > activePosition}
              className={`flex h-10 items-center justify-center rounded-lg text-sm font-semibold transition active:scale-95 disabled:opacity-30 ${
                paletteStyle[item.status] || paletteStyle.unanswered
              }`}
            >
              {item.position}
            </button>
          ))}
        </div>

        {/* Legend */}
        <div className="mt-4 flex items-center justify-around border-t border-slate-800 pt-3 text-[11px] text-slate-400">
          <span className="flex items-center gap-1.5">
            <span className="h-2.5 w-2.5 rounded-full bg-brand-500" /> Answered
          </span>
          <span className="flex items-center gap-1.5">
            <span className="h-2.5 w-2.5 rounded-full bg-amber-400" /> Flagged
          </span>
          <span className="flex items-center gap-1.5">
            <span className="h-2.5 w-2.5 rounded-full bg-white" /> Current
          </span>
        </div>
      </div>
    </div>
  );
}
