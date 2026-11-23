import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Sparkles, AlertCircle, CheckCircle2, ChevronRight, X } from 'lucide-react';

export default function AiDistractorExplainer({ questionId, chosenKey, onClose }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!questionId || !chosenKey) return;
    setLoading(true);
    setError(null);

    axios
      .post('/api/tutor/explain', {
        question_id: questionId,
        chosen_key: chosenKey,
      })
      .then((res) => setData(res.data))
      .catch(() => setError('Unable to generate AI distractor analysis.'))
      .finally(() => setLoading(false));
  }, [questionId, chosenKey]);

  if (loading) {
    return (
      <div className="mt-4 rounded-xl border border-brand-500/30 bg-brand-500/5 p-4 text-xs text-brand-300 flex items-center gap-2">
        <Sparkles className="h-4 w-4 animate-spin" />
        <span>Synthesizing conceptual distractor breakdown...</span>
      </div>
    );
  }

  if (error || !data) {
    return null;
  }

  return (
    <div className="mt-4 rounded-xl border border-brand-500/40 bg-slate-900/90 p-5 shadow-xl">
      <div className="flex items-center justify-between border-b border-slate-800 pb-3">
        <div className="flex items-center gap-2">
          <Sparkles className="h-4 w-4 text-brand-400" />
          <h4 className="text-xs font-bold uppercase tracking-wider text-brand-300">
            {data.headline || 'Conceptual Breakdown'}
          </h4>
        </div>
        {onClose && (
          <button
            type="button"
            onClick={onClose}
            className="rounded p-1 text-slate-400 hover:text-white"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        )}
      </div>

      <div className="mt-3 space-y-3 text-xs leading-relaxed">
        {data.why_chosen_is_incorrect && (
          <div className="rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-slate-300">
            <span className="font-bold text-red-400">Why Option [{data.chosen?.key}] is flawed: </span>
            {data.why_chosen_is_incorrect}
          </div>
        )}

        {data.why_correct_is_best && (
          <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-slate-300">
            <span className="font-bold text-emerald-400">Why Option [{data.correct?.key}] is optimal: </span>
            {data.why_correct_is_best}
          </div>
        )}

        {data.key_takeaway && (
          <div className="rounded-lg border border-brand-500/20 bg-brand-500/10 p-3 text-brand-200">
            <span className="font-bold text-brand-300">Exam-Day Rule: </span>
            {data.key_takeaway}
          </div>
        )}
      </div>
    </div>
  );
}
