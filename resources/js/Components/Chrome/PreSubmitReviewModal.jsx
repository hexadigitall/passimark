import React, { useState } from 'react';
import {
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  ClipboardCheck,
  Flag,
  Send,
  X,
} from 'lucide-react';

export default function PreSubmitReviewModal({
  isOpen,
  onClose,
  totalQuestions = 25,
  questions = [],
  answeredIds = new Set(),
  flaggedIndices = new Set(),
  onJumpToQuestion,
  onConfirmSubmit,
  processing = false,
}) {
  const [filter, setFilter] = useState('all'); // 'all' | 'unanswered' | 'flagged'

  if (!isOpen) return null;

  // Build items representation
  const items = Array.from({ length: totalQuestions }, (_, idx) => {
    const position = idx + 1;
    const qObj = questions[idx];
    const qId = qObj?.id;
    const isAnswered = qId ? answeredIds.has(qId) : false;
    const isFlagged = flaggedIndices.has(position);

    return {
      index: idx,
      position,
      id: qId,
      content: qObj?.content || `Question ${position}`,
      isAnswered,
      isFlagged,
    };
  });

  const answeredCount = items.filter((i) => i.isAnswered).length;
  const unansweredCount = totalQuestions - answeredCount;
  const flaggedCount = flaggedIndices.size;

  const filteredItems = items.filter((item) => {
    if (filter === 'unanswered') return !item.isAnswered;
    if (filter === 'flagged') return item.isFlagged;
    return true;
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
      <div className="relative flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-800 bg-slate-950 px-6 py-4">
          <div className="flex items-center gap-2">
            <ClipboardCheck className="h-5 w-5 text-brand-300" />
            <h2 className="text-base font-bold text-slate-100">Assessment Submission Review</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Summary Stats Strip */}
        <div className="grid grid-cols-4 gap-2 border-b border-slate-800 bg-slate-950/40 p-4 text-center">
          <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-2.5">
            <span className="text-xs text-slate-400">Total</span>
            <p className="mt-0.5 text-lg font-bold text-slate-100">{totalQuestions}</p>
          </div>
          <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-2.5">
            <span className="text-xs text-slate-400">Answered</span>
            <p className="mt-0.5 text-lg font-bold text-brand-300">{answeredCount}</p>
          </div>
          <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-2.5">
            <span className="text-xs text-slate-400">Unanswered</span>
            <p className="mt-0.5 text-lg font-bold text-orange-300">{unansweredCount}</p>
          </div>
          <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-2.5">
            <span className="text-xs text-slate-400">Flagged</span>
            <p className="mt-0.5 text-lg font-bold text-amber-300">{flaggedCount}</p>
          </div>
        </div>

        {/* Unanswered Alert Warning */}
        {unansweredCount > 0 && (
          <div className="mx-6 mt-4 flex items-center gap-3 rounded-xl border border-orange-500/30 bg-orange-500/10 p-3 text-xs text-orange-200">
            <AlertTriangle className="h-5 w-5 shrink-0 text-orange-300" />
            <span>
              You have <strong>{unansweredCount} unanswered questions</strong>. Review them before submitting to avoid point deductions.
            </span>
          </div>
        )}

        {/* Filter Controls */}
        <div className="flex gap-2 px-6 pt-4">
          <button
            type="button"
            onClick={() => setFilter('all')}
            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
              filter === 'all'
                ? 'bg-brand-500/15 text-brand-300'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            All Questions ({totalQuestions})
          </button>
          <button
            type="button"
            onClick={() => setFilter('unanswered')}
            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
              filter === 'unanswered'
                ? 'bg-brand-500/15 text-brand-300'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Unanswered ({unansweredCount})
          </button>
          <button
            type="button"
            onClick={() => setFilter('flagged')}
            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
              filter === 'flagged'
                ? 'bg-brand-500/15 text-brand-300'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Flagged ({flaggedCount})
          </button>
        </div>

        {/* Question List Table */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          <div className="space-y-2">
            {filteredItems.length === 0 ? (
              <p className="py-8 text-center text-xs text-slate-500">
                No questions matching the selected filter.
              </p>
            ) : (
              filteredItems.map((item) => (
                <div
                  key={item.position}
                  className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/60 p-3 text-sm transition hover:border-slate-700"
                >
                  <div className="flex items-center gap-3 min-w-0 pr-4">
                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-xs font-bold text-slate-300">
                      #{item.position}
                    </span>
                    <div className="min-w-0 truncate">
                      <p className="truncate text-xs font-medium text-slate-200">{item.content}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 shrink-0">
                    {item.isFlagged && (
                      <span className="inline-flex items-center gap-1 rounded bg-amber-400/15 px-2 py-0.5 text-[10px] font-semibold text-amber-300">
                        <Flag className="h-3 w-3" /> Flagged
                      </span>
                    )}
                    {item.isAnswered ? (
                      <span className="inline-flex items-center gap-1 rounded bg-brand-500/15 px-2 py-0.5 text-[10px] font-semibold text-brand-300">
                        <CheckCircle2 className="h-3 w-3" /> Answered
                      </span>
                    ) : (
                      <span className="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-semibold text-slate-400">
                        Unanswered
                      </span>
                    )}

                    <button
                      type="button"
                      onClick={() => {
                        onClose();
                        onJumpToQuestion(item.index);
                      }}
                      className="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:border-brand-500/60 hover:text-white transition"
                    >
                      Jump <ArrowRight className="h-3 w-3" />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-between border-t border-slate-800 bg-slate-950 px-6 py-4">
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-slate-700 px-4 py-2 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-slate-100 transition"
          >
            Return to Assessment
          </button>
          <button
            type="button"
            onClick={onConfirmSubmit}
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2 text-xs font-bold text-slate-950 transition hover:bg-brand-400 disabled:opacity-40"
          >
            {processing ? 'Submitting...' : 'Confirm Final Submission'}
            <Send className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>
  );
}
