import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Brain, Flame, ArrowRight, CheckCircle2, RotateCw } from 'lucide-react';

export default function DailyDrillCard() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    axios
      .get('/api/drill/stats')
      .then((res) => setStats(res.data))
      .catch(() => setStats(null))
      .finally(() => setLoading(false));
  }, []);

  if (loading || !stats || stats.total_tracked === 0) {
    return null;
  }

  const due = stats.due_for_review ?? 0;
  const boxes = [
    { label: 'B1 (12h)', count: stats.box_1, color: 'bg-red-500/80' },
    { label: 'B2 (3d)', count: stats.box_2, color: 'bg-amber-500/80' },
    { label: 'B3 (7d)', count: stats.box_3, color: 'bg-yellow-500/80' },
    { label: 'B4 (14d)', count: stats.box_4, color: 'bg-blue-500/80' },
    { label: 'B5 (Mastered)', count: stats.box_5, color: 'bg-emerald-500/80' },
  ];

  return (
    <div className="rounded-2xl border border-slate-800 bg-gradient-to-r from-slate-900 via-slate-900 to-brand-950/20 p-5 shadow-lg">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-brand-500/20 text-brand-300">
              <Brain className="h-4 w-4" />
            </span>
            <h3 className="text-sm font-bold text-slate-100">Intelligent Spaced Repetition (Leitner 5-Box)</h3>
          </div>
          <p className="mt-1 text-xs text-slate-400">
            {due > 0 ? (
              <span className="text-amber-400 font-semibold">{due} question{due > 1 ? 's' : ''} due for reinforcement</span>
            ) : (
              <span className="text-emerald-400 font-semibold">All spaced reviews up to date!</span>
            )}
            {' '}• {stats.mastered_count} of {stats.total_tracked} items in permanent mastery (Box 5).
          </p>
        </div>

        {due > 0 && (
          <button
            type="button"
            onClick={() => {
              window.location.href = '/passimark/focus';
            }}
            className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-xs font-bold text-slate-950 hover:bg-brand-400 transition shrink-0"
          >
            <Flame className="h-4 w-4 fill-current" />
            <span>Daily Weakness Drill</span>
            <ArrowRight className="h-3.5 w-3.5" />
          </button>
        )}
      </div>

      {/* Leitner Box Progress Ladder */}
      <div className="mt-4 grid grid-cols-5 gap-2 border-t border-slate-800/80 pt-3">
        {boxes.map((box, idx) => (
          <div key={box.label} className="text-center">
            <div className="text-[10px] text-slate-400 truncate">{box.label}</div>
            <div className="mt-1 flex h-2 w-full overflow-hidden rounded-full bg-slate-800">
              <div
                className={`h-full ${box.color}`}
                style={{
                  width: stats.total_tracked > 0 ? `${(box.count / stats.total_tracked) * 100}%` : '0%',
                }}
              />
            </div>
            <div className="mt-1 font-mono text-[11px] font-bold text-slate-300">
              {box.count}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
