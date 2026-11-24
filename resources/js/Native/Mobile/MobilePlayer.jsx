import React, { useState, useEffect } from 'react';
import { 
  Play, Pause, Flag, Eye, CheckCircle, XCircle, ChevronLeft, 
  ChevronRight, Layers, Sparkles, Moon, Sun, ArrowLeft
} from 'lucide-react';
import ThumbZoneBar from '../../Components/Chrome/ThumbZoneBar';
import BottomSheetPalette from '../../Components/Chrome/BottomSheetPalette';
import PinchZoomDiagram from '../../Components/Exhibits/PinchZoomDiagram';
import { HapticFeedback } from '../../Services/Mobile/HapticFeedback';
import QuestionMultiSelect from '../../Components/Items/QuestionMultiSelect';
import QuestionDragDrop from '../../Components/Items/QuestionDragDrop';
import QuestionHotspot from '../../Components/Items/QuestionHotspot';
import QuestionReorder from '../../Components/Items/QuestionReorder';
import QuestionCloze from '../../Components/Items/QuestionCloze';
import QuestionCreateTree from '../../Components/Items/QuestionCreateTree';
import QuestionFillBlank from '../../Components/Items/QuestionFillBlank';

export default function MobilePlayer({ exam, mode = 'practice', onExitToLibrary }) {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [answers, setAnswers] = useState({});
  const [flagged, setFlagged] = useState({});
  const [strikethroughs, setStrikethroughs] = useState({});
  const [showPaletteDrawer, setShowPaletteDrawer] = useState(false);
  const [showResult, setShowResult] = useState(false);
  const [isOledMode, setIsOledMode] = useState(false);
  const [timeLeftSeconds, setTimeLeftSeconds] = useState((exam.timeLimitMinutes || 60) * 60);

  const questions = exam.questions || [];
  const currentQ = questions[currentIndex] || questions[0];

  // Load OLED preference
  useEffect(() => {
    const saved = localStorage.getItem('pmk_oled_mode');
    if (saved === 'true') setIsOledMode(true);
  }, []);

  const toggleOledMode = () => {
    setIsOledMode((prev) => {
      const next = !prev;
      localStorage.setItem('pmk_oled_mode', String(next));
      HapticFeedback.selection();
      return next;
    });
  };

  // Timer countdown
  useEffect(() => {
    if (mode === 'practice' || showResult) return;
    const interval = setInterval(() => {
      setTimeLeftSeconds((prev) => {
        if (prev === 300) HapticFeedback.warning300s();
        if (prev === 60) HapticFeedback.warning60s();
        if (prev <= 1) {
          clearInterval(interval);
          setShowResult(true);
          HapticFeedback.completion();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
    return () => clearInterval(interval);
  }, [mode, showResult]);

  const handleSelectOption = (key) => {
    HapticFeedback.selection();
    setAnswers((prev) => {
      if (currentQ.type === 'multiple_choice') {
        const cur = prev[currentQ.id] || [];
        const next = cur.includes(key) ? cur.filter((k) => k !== key) : [...cur, key];
        return { ...prev, [currentQ.id]: next };
      }
      return { ...prev, [currentQ.id]: key };
    });
  };

  const handleInteractiveAnswer = (payload) => {
    HapticFeedback.selection();
    setAnswers((prev) => ({ ...prev, [currentQ.id]: payload }));
  };

  const toggleFlag = () => {
    HapticFeedback.selection();
    setFlagged((prev) => ({ ...prev, [currentQ.id]: !prev[currentQ.id] }));
  };

  const toggleStrike = (key) => {
    HapticFeedback.strike();
    setStrikethroughs((prev) => {
      const qStrikes = prev[currentQ.id] || [];
      const updated = qStrikes.includes(key) ? qStrikes.filter((k) => k !== key) : [...qStrikes, key];
      return { ...prev, [currentQ.id]: updated };
    });
  };

  const handleNext = () => {
    HapticFeedback.selection();
    if (currentIndex < questions.length - 1) {
      setCurrentIndex((prev) => prev + 1);
    } else {
      setShowResult(true);
      HapticFeedback.completion();
    }
  };

  const handlePrev = () => {
    HapticFeedback.selection();
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
    }
  };

  // Touch Swipe Gesture detection
  const touchStartX = React.useRef(0);
  const handleTouchStart = (e) => {
    touchStartX.current = e.touches[0].clientX;
  };
  const handleTouchEnd = (e) => {
    const diff = touchStartX.current - e.changedTouches[0].clientX;
    if (diff > 75) {
      handleNext(); // Left swipe -> next
    } else if (diff < -75) {
      handlePrev(); // Right swipe -> prev
    }
  };

  const formatTime = (secs) => {
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  };

  const currentAnswer = answers[currentQ.id];
  const isAnswered = currentAnswer !== undefined && currentAnswer !== null && currentAnswer !== '';
  const currentStrikes = strikethroughs[currentQ.id] || [];

  const normalizedOptions = (currentQ.options || []).map((opt, idx) => {
    if (typeof opt === 'string') {
      const key = String.fromCharCode(65 + idx);
      return { key, text: opt, is_correct: currentQ.correct_key === key };
    }
    return {
      key: opt.key || String.fromCharCode(65 + idx),
      text: opt.text || opt.label || opt.content || '',
      is_correct: Boolean(opt.is_correct),
    };
  });

  const bgClass = isOledMode ? 'bg-[#000000]' : 'bg-slate-950';
  const cardClass = isOledMode ? 'bg-[#0a0a0a] border-zinc-800' : 'bg-slate-900/80 border-slate-800';

  if (showResult) {
    let correctCount = 0;
    questions.forEach((q) => {
      if (answers[q.id] === q.correct_key) correctCount++;
    });
    const pct = Math.round((correctCount / Math.max(1, questions.length)) * 100);

    return (
      <div className={`flex flex-1 flex-col items-center justify-center p-6 text-slate-100 ${bgClass}`}>
        <div className={`w-full max-w-sm rounded-2xl border p-6 text-center shadow-xl ${cardClass}`}>
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-800 mb-3">
            {pct >= 70 ? (
              <CheckCircle className="h-8 w-8 text-brand-400" />
            ) : (
              <XCircle className="h-8 w-8 text-rose-500" />
            )}
          </div>
          <h2 className="text-xl font-bold text-slate-100">
            {pct >= 70 ? 'Passed Simulation' : 'Score: ' + pct + '%'}
          </h2>
          <p className="mt-1 text-xs text-slate-400">
            {correctCount} / {questions.length} items correct
          </p>

          <button
            onClick={onExitToLibrary}
            className="mt-6 w-full rounded-xl bg-brand-500 py-3 text-xs font-bold text-slate-950 shadow-lg shadow-brand-500/20"
          >
            Return to Mobile Library
          </button>
        </div>
      </div>
    );
  }

  return (
    <div 
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
      className={`flex flex-1 flex-col overflow-hidden text-slate-100 select-none ${bgClass}`}
      style={{
        paddingTop: 'env(safe-area-inset-top, 0px)',
      }}
    >
      {/* Mobile Header Bar */}
      <div className={`flex h-12 shrink-0 items-center justify-between border-b px-4 text-xs ${cardClass}`}>
        <button
          onClick={onExitToLibrary}
          className="flex items-center gap-1 text-slate-400 hover:text-white"
        >
          <ArrowLeft className="h-4 w-4" />
          <span>Exit</span>
        </button>

        <div className="flex items-center gap-2">
          <img 
            src="./images/passimark/passimark_icon_128x128.png" 
            alt="Passimark" 
            className="h-5 w-5 object-contain drop-shadow"
            onError={(e) => { e.currentTarget.src = './images/passimark/icon.png'; }}
          />
          <span className="font-bold text-slate-200">{exam.code}</span>
          <span className="text-slate-600">|</span>
          <span className="text-slate-400">Q {currentIndex + 1}/{questions.length}</span>
        </div>

        <div className="flex items-center gap-2">
          {mode !== 'practice' && (
            <span className="font-mono text-xs font-bold text-brand-400">
              {formatTime(timeLeftSeconds)}
            </span>
          )}
          <button
            onClick={toggleOledMode}
            title="Toggle true OLED pitch-black mode"
            className="p-1 rounded text-slate-400 hover:text-amber-400"
          >
            {isOledMode ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </button>
        </div>
      </div>

      {/* Main Touch Question Scroll Body */}
      <div className="flex-1 overflow-y-auto p-4 pb-24">
        <div className={`rounded-xl border p-4 shadow-sm ${cardClass}`}>
          <div className="flex items-center justify-between text-[11px] text-slate-400 mb-2">
            <span className="font-semibold text-brand-400 uppercase tracking-wider text-[10px]">
              {currentQ.type.replace('_', ' ')}
            </span>
            {flagged[currentQ.id] && (
              <span className="flex items-center gap-1 text-amber-400 text-[10px] font-bold">
                <Flag className="h-3 w-3 fill-amber-400" /> Flagged
              </span>
            )}
          </div>
          <p className="text-sm font-medium leading-relaxed text-slate-100">
            {currentQ.content}
          </p>
        </div>

        {/* Exhibit Pinch-Zoom diagram viewer if question has image */}
        {currentQ.interactive_payload?.image_src && (
          <div className="mt-4">
            <PinchZoomDiagram
              src={currentQ.interactive_payload.image_src}
              alt="Topology diagram"
              caption="Pinch to zoom (1x - 4x) diagram"
            />
          </div>
        )}

        {/* Item Renderers */}
        <div className="mt-5 flex flex-col gap-2.5">
          {(currentQ.type === 'multiple_choice' || (Array.isArray(currentQ.correct_key) && currentQ.correct_key.length > 1)) ? (
            <QuestionMultiSelect
              options={normalizedOptions}
              selections={Array.isArray(currentAnswer) ? currentAnswer : []}
              onChange={(keys) => handleSelectOption(keys)}
              strikes={new Set(currentStrikes)}
              onToggleStrike={toggleStrike}
              minSelections={Array.isArray(currentQ.correct_key) ? currentQ.correct_key.length : 2}
              maxSelections={Array.isArray(currentQ.correct_key) ? currentQ.correct_key.length : null}
            />
          ) : currentQ.type === 'drag_drop' ? (
            <QuestionDragDrop
              payload={currentQ.interactive_payload || {}}
              value={typeof currentAnswer === 'object' && currentAnswer !== null ? currentAnswer : {}}
              onChange={handleInteractiveAnswer}
            />
          ) : currentQ.type === 'hotspot' ? (
            <QuestionHotspot
              payload={currentQ.interactive_payload || {}}
              value={typeof currentAnswer === 'object' && currentAnswer !== null ? currentAnswer : null}
              onChange={handleInteractiveAnswer}
            />
          ) : currentQ.type === 'build_list' ? (
            <QuestionReorder
              payload={currentQ.interactive_payload || {}}
              value={Array.isArray(currentAnswer) ? currentAnswer : []}
              onChange={handleInteractiveAnswer}
            />
          ) : currentQ.type === 'cloze' ? (
            <QuestionCloze
              payload={currentQ.interactive_payload || {}}
              value={typeof currentAnswer === 'object' && currentAnswer !== null ? currentAnswer : {}}
              onChange={handleInteractiveAnswer}
            />
          ) : currentQ.type === 'create_tree' ? (
            <QuestionCreateTree
              payload={currentQ.interactive_payload || {}}
              value={Array.isArray(currentAnswer) ? currentAnswer : []}
              onChange={handleInteractiveAnswer}
            />
          ) : currentQ.type === 'fill_blank' ? (
            <QuestionFillBlank
              payload={currentQ.interactive_payload || {}}
              value={typeof currentAnswer === 'string' ? currentAnswer : ''}
              onChange={handleInteractiveAnswer}
            />
          ) : (
            /* Single choice, Case study, or any other option-bearing item */
            <div className="flex flex-col gap-2.5">
              {normalizedOptions.length > 0 ? (
                normalizedOptions.map((opt) => {
                  const isSelected = currentAnswer === opt.key;
                  const isStruck = currentStrikes.includes(opt.key);
                  return (
                    <div
                      key={opt.key}
                      onClick={() => !isStruck && handleSelectOption(opt.key)}
                      className={`flex items-start justify-between rounded-xl border p-3.5 active:scale-[0.99] transition-all cursor-pointer ${
                        isSelected
                          ? 'border-brand-500 bg-brand-500/15 text-slate-100 ring-1 ring-brand-500'
                          : isStruck
                          ? 'border-zinc-800/40 bg-zinc-950/40 text-zinc-600 opacity-40'
                          : `${cardClass} text-slate-200`
                      }`}
                    >
                      <div className="flex items-start gap-3">
                        <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-lg font-mono text-xs font-bold ${
                          isSelected ? 'bg-brand-500 text-slate-950' : 'bg-slate-800 text-slate-300'
                        }`}>
                          {opt.key}
                        </span>
                        <span className={`text-xs md:text-sm mt-0.5 ${isStruck ? 'line-through' : ''}`}>
                          {opt.text}
                        </span>
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-5 text-center text-xs text-slate-400">
                  Follow the prompt instructions above to complete your answer.
                </div>
              )}
            </div>
          )}
        </div>

        {/* Practice Mode Instant Rationale */}
        {mode === 'practice' && isAnswered && currentQ.explanation && (
          <div className="mt-5 rounded-xl border border-brand-500/30 bg-brand-500/10 p-3.5 text-xs text-slate-300">
            <div className="flex items-center gap-1.5 font-semibold text-brand-400 mb-1">
              <Sparkles className="h-3.5 w-3.5" />
              <span>Explanation</span>
            </div>
            <p className="leading-relaxed text-[11px]">{currentQ.explanation}</p>
          </div>
        )}
      </div>

      {/* Thumb-Zone Fixed Bottom Navigation Bar (No Stretched Ports) */}
      <ThumbZoneBar
        currentIndex={currentIndex}
        totalQuestions={questions.length}
        isAnswered={isAnswered}
        isFlagged={flagged[currentQ.id]}
        onPrevious={handlePrev}
        onNext={handleNext}
        onToggleFlag={toggleFlag}
        onOpenPalette={() => setShowPaletteDrawer(true)}
      />

      {/* Gesture-Controlled Bottom-Sheet Palette Drawer */}
      <BottomSheetPalette
        isOpen={showPaletteDrawer}
        onClose={() => setShowPaletteDrawer(false)}
        currentIndex={currentIndex}
        totalQuestions={questions.length}
        answers={answers}
        flaggedQuestions={flagged}
        onJumpToQuestion={(idx) => {
          setCurrentIndex(idx);
          setShowPaletteDrawer(false);
          HapticFeedback.selection();
        }}
      />
    </div>
  );
}
