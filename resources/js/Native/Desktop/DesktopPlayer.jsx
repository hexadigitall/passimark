import React, { useState, useEffect, useRef } from 'react';
import { 
  Play, Pause, Flag, Eye, EyeOff, RotateCcw, CheckCircle, 
  XCircle, ChevronLeft, ChevronRight, Calculator, FileText, 
  ExternalLink, Maximize2, ShieldAlert, Sparkles, HelpCircle
} from 'lucide-react';
import { TauriBridge } from '../../Services/Desktop/TauriBridge';
import QuestionMultiSelect from '../../Components/Items/QuestionMultiSelect';
import QuestionDragDrop from '../../Components/Items/QuestionDragDrop';
import QuestionHotspot from '../../Components/Items/QuestionHotspot';
import QuestionReorder from '../../Components/Items/QuestionReorder';
import QuestionCloze from '../../Components/Items/QuestionCloze';
import QuestionCreateTree from '../../Components/Items/QuestionCreateTree';
import QuestionFillBlank from '../../Components/Items/QuestionFillBlank';

export default function DesktopPlayer({ exam, mode = 'practice', onExitToLibrary }) {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [answers, setAnswers] = useState({});
  const [flagged, setFlagged] = useState({});
  const [strikethroughs, setStrikethroughs] = useState({});
  const [scratchpad, setScratchpad] = useState('');
  const [isPaused, setIsPaused] = useState(false);
  const [showPalette, setShowPalette] = useState(true);
  const [showCalculator, setShowCalculator] = useState(false);
  const [showScratchpad, setShowScratchpad] = useState(false);
  const [showResult, setShowResult] = useState(false);
  const [timeLeftSeconds, setTimeLeftSeconds] = useState((exam.timeLimitMinutes || 60) * 60);

  const questions = exam.questions || [];
  const currentQ = questions[currentIndex] || questions[0];

  // Timer countdown for timed & CAT modes
  useEffect(() => {
    if (mode === 'practice' || isPaused || showResult) return;
    const interval = setInterval(() => {
      setTimeLeftSeconds((prev) => {
        if (prev <= 1) {
          clearInterval(interval);
          setShowResult(true);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
    return () => clearInterval(interval);
  }, [mode, isPaused, showResult]);

  // Physical Keyboard Shortcuts (VCE Parity)
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;

      // 1-9 & A-I for instant choice selection
      if (currentQ?.options) {
        let selectedKey = null;
        if (e.key >= '1' && e.key <= '9') {
          const num = parseInt(e.key, 10);
          if (num <= currentQ.options.length) {
            selectedKey = currentQ.options[num - 1].key;
          }
        } else if (e.key.length === 1 && e.key.toUpperCase() >= 'A' && e.key.toUpperCase() <= 'I') {
          const char = e.key.toUpperCase();
          const match = currentQ.options.find((o) => o.key.toUpperCase() === char);
          if (match) selectedKey = match.key;
        }

        if (selectedKey) {
          e.preventDefault();
          handleSelectOption(selectedKey);
          return;
        }
      }

      // Proctoring Shortcuts
      if (e.ctrlKey && e.key.toLowerCase() === 'f') {
        e.preventDefault();
        toggleFlag();
      } else if (e.ctrlKey && e.key.toLowerCase() === 's') {
        e.preventDefault();
        // Strike active
      } else if (e.ctrlKey && e.key.toLowerCase() === 'p') {
        e.preventDefault();
        setShowPalette((prev) => !prev);
      } else if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        setShowResult(true);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        setIsPaused((prev) => !prev);
      } else if (e.key === 'ArrowRight' || e.key === 'PageDown') {
        e.preventDefault();
        handleNext();
      } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
        e.preventDefault();
        handlePrev();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [currentIndex, currentQ, answers, flagged]);

  const handleSelectOption = (key) => {
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
    setAnswers((prev) => ({ ...prev, [currentQ.id]: payload }));
  };

  const toggleFlag = () => {
    setFlagged((prev) => ({ ...prev, [currentQ.id]: !prev[currentQ.id] }));
  };

  const toggleStrike = (key) => {
    setStrikethroughs((prev) => {
      const qStrikes = prev[currentQ.id] || [];
      const updated = qStrikes.includes(key) ? qStrikes.filter((k) => k !== key) : [...qStrikes, key];
      return { ...prev, [currentQ.id]: updated };
    });
  };

  const handleNext = () => {
    if (currentIndex < questions.length - 1) {
      setCurrentIndex((prev) => prev + 1);
    } else {
      setShowResult(true);
    }
  };

  const handlePrev = () => {
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
    }
  };

  // Detach exhibit to secondary monitor
  const handleDetachExhibit = () => {
    const exhibitPayload = {
      id: currentQ.id,
      title: `${exam.code} — Question ${currentQ.number || currentIndex + 1} Exhibit`,
      type: 'diagram',
      src: currentQ.interactive_payload?.image_src || '/images/passimark/topology_sample.png',
      content: currentQ.reference || currentQ.explanation,
    };
    TauriBridge.detachExhibit(exhibitPayload);
  };

  // Detach scratchpad to secondary floating window
  const handleDetachScratchpad = () => {
    TauriBridge.detachScratchpad(scratchpad);
  };

  // Format countdown mm:ss
  const formatTime = (secs) => {
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  };

  // Scoring
  const calculateScore = () => {
    let correctCount = 0;
    questions.forEach((q) => {
      const ans = answers[q.id];
      if (!ans) return;
      if (q.type === 'single_choice' && ans === q.correct_key) correctCount++;
      else if (q.type === 'multiple_choice' && Array.isArray(ans) && Array.isArray(q.correct_key)) {
        if (ans.length === q.correct_key.length && ans.every((k) => q.correct_key.includes(k))) correctCount++;
      } else if (q.type === 'drag_drop' && typeof ans === 'object') {
        const targets = q.interactive_payload?.targets || [];
        const isMatch = targets.every((t) => ans[t.id] === t.correct_source_id);
        if (isMatch) correctCount++;
      } else if (ans === q.correct_key) {
        correctCount++;
      }
    });
    const percentage = Math.round((correctCount / Math.max(1, questions.length)) * 100);
    return {
      correctCount,
      total: questions.length,
      percentage,
      isPassed: percentage >= 70,
    };
  };

  const currentAnswer = answers[currentQ.id];
  const isAnswered = currentAnswer !== undefined && currentAnswer !== null && currentAnswer !== '';
  const currentStrikes = strikethroughs[currentQ.id] || [];

  if (showResult) {
    const result = calculateScore();
    return (
      <div className="flex flex-1 flex-col items-center justify-center bg-slate-950 p-8 text-slate-100">
        <div className="w-full max-w-xl rounded-2xl border border-slate-800 bg-slate-900/90 p-8 shadow-2xl text-center">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-800 mb-4 shadow-inner">
            {result.isPassed ? (
              <CheckCircle className="h-9 w-9 text-brand-400" />
            ) : (
              <XCircle className="h-9 w-9 text-rose-500" />
            )}
          </div>
          <span className="rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-300">
            {exam.code} Assessment Completed
          </span>
          <h2 className="mt-3 text-2xl font-bold text-slate-100">
            {result.isPassed ? 'Candidate Passed' : 'Assessment Threshold Not Met'}
          </h2>
          <p className="mt-1 text-sm text-slate-400">
            Score: <span className="font-bold text-slate-200">{result.percentage}%</span> ({result.correctCount} / {result.total} items correct)
          </p>

          <div className="my-6 grid grid-cols-3 gap-3 border-y border-slate-800 py-4 text-xs">
            <div>
              <span className="text-slate-500">Mode</span>
              <p className="font-bold text-slate-300 capitalize">{mode}</p>
            </div>
            <div>
              <span className="text-slate-500">Pass Mark</span>
              <p className="font-bold text-slate-300">70%</p>
            </div>
            <div>
              <span className="text-slate-500">Estimated Ability</span>
              <p className="font-bold text-brand-400 font-mono">θ = +{((result.percentage - 50) / 25).toFixed(2)}</p>
            </div>
          </div>

          <div className="flex gap-3 justify-center">
            <button
              onClick={() => {
                setAnswers({});
                setFlagged({});
                setCurrentIndex(0);
                setShowResult(false);
              }}
              className="rounded-lg bg-slate-800 px-5 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700 transition-colors"
            >
              Reattempt Exam
            </button>
            <button
              onClick={onExitToLibrary}
              className="rounded-lg bg-brand-500 px-5 py-2 text-xs font-bold text-slate-950 hover:bg-brand-400 transition-colors"
            >
              Return to Exam Library
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-1 flex-col overflow-hidden bg-slate-950 text-slate-100">
      {/* Exam Header Bar */}
      <div className="flex h-12 shrink-0 items-center justify-between border-b border-slate-800 bg-slate-900/80 px-4 text-xs">
        <div className="flex items-center gap-3">
          <span className="font-bold text-slate-200">{exam.code}</span>
          <span className="text-slate-600">|</span>
          <span className="text-slate-400">Question {currentIndex + 1} of {questions.length}</span>
          {currentQ.domain && (
            <span className="rounded bg-slate-800 px-2 py-0.5 text-[10px] text-slate-400 font-medium">
              {currentQ.domain}
            </span>
          )}
        </div>

        <div className="flex items-center gap-3">
          {mode !== 'practice' && (
            <div className={`flex items-center gap-1.5 rounded bg-slate-800 px-2.5 py-1 font-mono font-semibold text-xs ${
              timeLeftSeconds < 300 ? 'text-rose-400 animate-pulse' : 'text-slate-200'
            }`}>
              <span>⏱ {formatTime(timeLeftSeconds)}</span>
            </div>
          )}

          <button
            onClick={toggleFlag}
            className={`flex items-center gap-1 rounded px-2.5 py-1 font-medium transition-colors ${
              flagged[currentQ.id]
                ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                : 'bg-slate-800 text-slate-400 hover:text-slate-200'
            }`}
          >
            <Flag className={`h-3.5 w-3.5 ${flagged[currentQ.id] ? 'fill-amber-400 text-amber-400' : ''}`} />
            <span>{flagged[currentQ.id] ? 'Flagged' : 'Flag'}</span>
            <span className="text-[10px] text-slate-500 font-mono">Ctrl+F</span>
          </button>

          {/* Detach Exhibit (Desktop Power Invariant) */}
          <button
            onClick={handleDetachExhibit}
            title="Detach exhibit diagram to secondary monitor (Tauri Native Multi-Window)"
            className="flex items-center gap-1 rounded bg-slate-800 px-2.5 py-1 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors"
          >
            <ExternalLink className="h-3.5 w-3.5 text-blue-400" />
            <span>Detach Exhibit</span>
          </button>

          <button
            onClick={() => setShowScratchpad((prev) => !prev)}
            className="flex items-center gap-1 rounded bg-slate-800 px-2.5 py-1 text-slate-300 hover:bg-slate-700 transition-colors"
          >
            <FileText className="h-3.5 w-3.5 text-purple-400" />
            <span>Scratchpad</span>
          </button>
        </div>
      </div>

      {/* Main Examination Workspace: Left/Center Question, Right Question Palette */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left/Center Column: Question Presentation */}
        <div className="flex flex-1 flex-col overflow-y-auto p-6">
          <div className="mx-auto w-full max-w-4xl flex-1 flex flex-col justify-between">
            {/* Question Prompt */}
            <div>
              <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-5 shadow-sm">
                <div className="flex items-center justify-between text-xs text-slate-400 mb-2">
                  <span className="font-semibold text-brand-400 uppercase tracking-wider text-[10px]">
                    Item Type: {currentQ.type.replace('_', ' ')}
                  </span>
                  <span className="text-slate-500">
                    Difficulty: b = {currentQ.irt_b ?? 0.0} | Discrimination: a = {currentQ.irt_a ?? 1.5}
                  </span>
                </div>
                <p className="text-sm md:text-base font-medium text-slate-100 leading-relaxed">
                  {currentQ.content}
                </p>
              </div>

              {/* Interactive Item Renderers */}
              <div className="mt-6">
                {currentQ.type === 'single_choice' && (
                  <div className="flex flex-col gap-2.5">
                    {currentQ.options?.map((opt, idx) => {
                      const isSelected = currentAnswer === opt.key;
                      const isStruck = currentStrikes.includes(opt.key);
                      return (
                        <div
                          key={opt.key}
                          onClick={() => !isStruck && handleSelectOption(opt.key)}
                          className={`group flex items-start justify-between rounded-xl border p-3.5 cursor-pointer transition-all ${
                            isSelected
                              ? 'border-brand-500 bg-brand-500/10 text-slate-100 shadow-sm'
                              : isStruck
                              ? 'border-slate-800/50 bg-slate-950/40 text-slate-600 opacity-50'
                              : 'border-slate-800 bg-slate-900/40 hover:border-slate-700 hover:bg-slate-900/80'
                          }`}
                        >
                          <div className="flex items-start gap-3">
                            <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md font-mono text-xs font-bold ${
                              isSelected ? 'bg-brand-500 text-slate-950' : 'bg-slate-800 text-slate-400 group-hover:text-slate-200'
                            }`}>
                              {opt.key}
                            </span>
                            <span className={`text-sm ${isStruck ? 'line-through' : ''}`}>
                              {opt.text}
                            </span>
                          </div>
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              toggleStrike(opt.key);
                            }}
                            title="Strike through option"
                            className="text-xs text-slate-600 hover:text-slate-400 px-1 opacity-0 group-hover:opacity-100 transition-opacity"
                          >
                            {isStruck ? 'Unstrike' : 'Strike'}
                          </button>
                        </div>
                      );
                    })}
                  </div>
                )}

                {currentQ.type === 'multiple_choice' && (
                  <QuestionMultiSelect
                    question={currentQ}
                    selectedKeys={Array.isArray(currentAnswer) ? currentAnswer : []}
                    onSelectKey={handleSelectOption}
                  />
                )}

                {currentQ.type === 'drag_drop' && (
                  <QuestionDragDrop
                    payload={currentQ.interactive_payload}
                    answers={typeof currentAnswer === 'object' ? currentAnswer : {}}
                    onAnswerChange={handleInteractiveAnswer}
                  />
                )}

                {currentQ.type === 'hotspot' && (
                  <QuestionHotspot
                    payload={currentQ.interactive_payload}
                    selectedCoordinate={typeof currentAnswer === 'object' ? currentAnswer : null}
                    onSelectCoordinate={handleInteractiveAnswer}
                  />
                )}

                {currentQ.type === 'build_list' && (
                  <QuestionReorder
                    payload={currentQ.interactive_payload}
                    arrangedItems={Array.isArray(currentAnswer) ? currentAnswer : []}
                    onSequenceChange={handleInteractiveAnswer}
                  />
                )}

                {currentQ.type === 'cloze' && (
                  <QuestionCloze
                    payload={currentQ.interactive_payload}
                    selections={typeof currentAnswer === 'object' ? currentAnswer : {}}
                    onSelectionChange={handleInteractiveAnswer}
                  />
                )}

                {currentQ.type === 'create_tree' && (
                  <QuestionCreateTree
                    payload={currentQ.interactive_payload}
                    treeState={typeof currentAnswer === 'object' ? currentAnswer : {}}
                    onTreeChange={handleInteractiveAnswer}
                  />
                )}

                {currentQ.type === 'fill_blank' && (
                  <QuestionFillBlank
                    payload={currentQ.interactive_payload}
                    textInput={typeof currentAnswer === 'string' ? currentAnswer : ''}
                    onTextChange={handleInteractiveAnswer}
                  />
                )}
              </div>

              {/* Practice Mode Immediate Explanation */}
              {mode === 'practice' && isAnswered && currentQ.explanation && (
                <div className="mt-6 rounded-xl border border-brand-500/30 bg-brand-500/5 p-4 text-xs text-slate-300">
                  <div className="flex items-center gap-2 font-semibold text-brand-400 mb-1">
                    <Sparkles className="h-4 w-4" />
                    <span>Practice Mode Rationale</span>
                  </div>
                  <p className="leading-relaxed">{currentQ.explanation}</p>
                  {currentQ.reference && (
                    <div className="mt-2 text-[10px] text-slate-500 border-t border-slate-800 pt-1 font-mono">
                      Ref: {currentQ.reference}
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Bottom Desktop Navigation Bar */}
            <div className="mt-8 flex items-center justify-between border-t border-slate-800 pt-4">
              <button
                onClick={handlePrev}
                disabled={currentIndex === 0}
                className="flex items-center gap-1.5 rounded-lg border border-slate-800 bg-slate-900 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 disabled:opacity-40 transition-colors"
              >
                <ChevronLeft className="h-4 w-4" />
                <span>Previous</span>
                <span className="text-[10px] text-slate-500 font-mono ml-1">←</span>
              </button>

              <button
                onClick={handleNext}
                className="flex items-center gap-1.5 rounded-lg bg-brand-500 px-5 py-2 text-xs font-bold text-slate-950 hover:bg-brand-400 transition-colors shadow"
              >
                <span>{currentIndex === questions.length - 1 ? 'Finish Assessment' : 'Next Question'}</span>
                <ChevronRight className="h-4 w-4" />
                <span className="text-[10px] opacity-70 font-mono ml-1">→</span>
              </button>
            </div>
          </div>
        </div>

        {/* Right Column: Question Palette Drawer */}
        {showPalette && (
          <div className="w-64 shrink-0 border-l border-slate-800 bg-slate-900/50 p-4 flex flex-col justify-between overflow-y-auto">
            <div>
              <div className="flex items-center justify-between text-xs font-semibold text-slate-300 mb-3">
                <span>Question Palette</span>
                <span className="text-[10px] text-slate-500 font-mono">Ctrl+P</span>
              </div>

              {/* Grid of question buttons */}
              <div className="grid grid-cols-5 gap-2">
                {questions.map((q, idx) => {
                  const isCur = idx === currentIndex;
                  const isAns = answers[q.id] !== undefined;
                  const isFlg = flagged[q.id];
                  return (
                    <button
                      key={q.id}
                      onClick={() => setCurrentIndex(idx)}
                      className={`relative flex h-8 w-8 items-center justify-center rounded-lg font-mono text-xs font-bold transition-all ${
                        isCur
                          ? 'ring-2 ring-brand-400 bg-slate-800 text-white'
                          : isAns
                          ? 'bg-slate-800 text-brand-400 hover:bg-slate-700'
                          : 'bg-slate-950 text-slate-500 hover:bg-slate-800'
                      }`}
                    >
                      {idx + 1}
                      {isFlg && (
                        <span className="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-amber-400" />
                      )}
                    </button>
                  );
                })}
              </div>

              {/* Palette Legend */}
              <div className="mt-5 flex flex-col gap-2 border-t border-slate-800/80 pt-4 text-[11px] text-slate-400">
                <div className="flex items-center gap-2">
                  <span className="h-2.5 w-2.5 rounded bg-brand-400" />
                  <span>Answered</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="h-2.5 w-2.5 rounded bg-slate-950 border border-slate-700" />
                  <span>Unanswered</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="h-2.5 w-2.5 rounded bg-amber-400" />
                  <span>Flagged</span>
                </div>
              </div>
            </div>

            <button
              onClick={() => setShowResult(true)}
              className="mt-6 w-full rounded-lg bg-slate-800 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition-colors"
            >
              Submit Exam Now
            </button>
          </div>
        )}
      </div>

      {/* Floating In-App Scratchpad Modal (when not detached) */}
      {showScratchpad && (
        <div className="fixed bottom-14 right-8 w-80 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl p-3 z-50">
          <div className="flex items-center justify-between text-xs text-slate-300 mb-2">
            <span className="font-semibold">Candidate Scratchpad</span>
            <button onClick={handleDetachScratchpad} className="text-blue-400 hover:underline text-[10px]">
              Detach ↗
            </button>
          </div>
          <textarea
            value={scratchpad}
            onChange={(e) => setScratchpad(e.target.value)}
            placeholder="Type notes or temporary calculations..."
            className="w-full h-36 rounded bg-slate-950 p-2 text-xs text-slate-200 border border-slate-800 focus:outline-none focus:border-brand-500"
          />
        </div>
      )}
    </div>
  );
}
