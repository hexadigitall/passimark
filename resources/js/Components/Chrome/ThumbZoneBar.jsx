import React from 'react';
import { ChevronLeft, ChevronRight, Flag, Grid, Calculator, Send } from 'lucide-react';
import { HapticFeedback } from '../../Services/Mobile/HapticFeedback';

export default function ThumbZoneBar({
  activePosition,
  totalQuestions,
  canGoPrevious,
  onPrevious,
  onNext,
  isFlagged,
  onToggleFlag,
  onOpenPalette,
  onOpenCalculator,
  isSubmitting = false,
  isLastQuestion = false,
}) {
  const handleFlag = () => {
    HapticFeedback.selection();
    onToggleFlag();
  };

  const handlePrev = () => {
    HapticFeedback.selection();
    onPrevious();
  };

  const handleNext = () => {
    HapticFeedback.selection();
    onNext();
  };

  return (
    <div className="fixed bottom-0 inset-x-0 z-40 lg:hidden border-t border-slate-800 bg-slate-950/95 backdrop-blur-md px-4 py-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))] shadow-2xl">
      <div className="flex items-center justify-between gap-2 max-w-md mx-auto">
        {/* Previous Button */}
        <button
          type="button"
          onClick={handlePrev}
          disabled={!canGoPrevious}
          className="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-800 bg-slate-900 text-slate-300 active:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed transition"
          aria-label="Previous Question"
        >
          <ChevronLeft className="h-6 w-6" />
        </button>

        {/* Flag Toggle */}
        <button
          type="button"
          onClick={handleFlag}
          className={`flex h-12 w-12 items-center justify-center rounded-xl border transition ${
            isFlagged
              ? 'border-amber-400/50 bg-amber-400/20 text-amber-300'
              : 'border-slate-800 bg-slate-900 text-slate-400 active:bg-slate-800'
          }`}
          aria-label="Flag Question"
        >
          <Flag className="h-5 w-5" />
        </button>

        {/* Palette Drawer Trigger */}
        <button
          type="button"
          onClick={onOpenPalette}
          className="flex flex-1 h-12 items-center justify-center gap-1.5 rounded-xl border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-slate-200 active:bg-slate-800 transition"
        >
          <Grid className="h-4 w-4 text-brand-400" />
          <span>{activePosition} / {totalQuestions}</span>
        </button>

        {/* Calculator Tool */}
        <button
          type="button"
          onClick={onOpenCalculator}
          className="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-800 bg-slate-900 text-slate-400 active:bg-slate-800 transition"
          aria-label="Calculator"
        >
          <Calculator className="h-5 w-5" />
        </button>

        {/* Next / Submit Button */}
        <button
          type="button"
          onClick={handleNext}
          disabled={isSubmitting}
          className="flex h-12 px-5 items-center justify-center gap-1.5 rounded-xl bg-brand-500 font-bold text-slate-950 active:bg-brand-400 shadow-lg shadow-brand-500/20 transition disabled:opacity-50"
        >
          <span>{isLastQuestion ? 'Submit' : 'Next'}</span>
          {isLastQuestion ? <Send className="h-4 w-4" /> : <ChevronRight className="h-5 w-5" />}
        </button>
      </div>
    </div>
  );
}
