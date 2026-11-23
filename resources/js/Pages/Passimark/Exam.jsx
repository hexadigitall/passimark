import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import {
  AlertTriangle,
  Calculator,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  Clock3,
  Coffee,
  Flag,
  Highlighter,
  Keyboard,
  LogOut,
  Moon,
  Pause,
  Play,
  RotateCcw,
  Send,
  Sun,
  X,
} from 'lucide-react';
import QuestionSingleChoice from '../../Components/Items/QuestionSingleChoice';
import QuestionMultiSelect from '../../Components/Items/QuestionMultiSelect';
import QuestionDragDrop from '../../Components/Items/QuestionDragDrop';
import QuestionHotspot from '../../Components/Items/QuestionHotspot';
import QuestionReorder from '../../Components/Items/QuestionReorder';
import QuestionCloze from '../../Components/Items/QuestionCloze';
import QuestionCreateTree from '../../Components/Items/QuestionCreateTree';
import QuestionFillBlank from '../../Components/Items/QuestionFillBlank';
import CaseStudyViewer from '../../Components/Exhibits/CaseStudyViewer';
import AdvancedCalculator from '../../Components/Chrome/AdvancedCalculator';
import PreSubmitReviewModal from '../../Components/Chrome/PreSubmitReviewModal';
import BottomSheetPalette from '../../Components/Chrome/BottomSheetPalette';
import ThumbZoneBar from '../../Components/Chrome/ThumbZoneBar';
import DesktopShortcutsModal from '../../Components/Chrome/DesktopShortcutsModal';
import AiDistractorExplainer from '../../Components/Items/AiDistractorExplainer';
import { HapticFeedback } from '../../Services/Mobile/HapticFeedback';
import { TauriBridge } from '../../Services/Desktop/TauriBridge';

/* ---------------------------------------------------------------------------
 * Exam Page — Dual-Mode Navigation, Stateful Timer & Proctoring Chrome
 * ------------------------------------------------------------------------- */
export default function Exam({
  attempt,
  question: initialQuestion,
  questions: preloadedQuestions = null,
  existingAnswers = {},
  answeredCount: initialAnsweredCount = 0,
  isPaused: initialIsPaused = false,
  remainingSeconds: initialRemainingSeconds = null,
  sessionState: initialSessionState = {},
}) {
  const isCat = attempt.mode === 'cat';
  const isFinal = Boolean(attempt.exam?.is_final);
  const isPractice = attempt.mode === 'practice';
  const totalQuestions = attempt.exam?.question_count || (preloadedQuestions ? preloadedQuestions.length : 25);

  // Question bank state for non-CAT free-roam navigation
  const [questionsList, setQuestionsList] = useState(() => preloadedQuestions || (initialQuestion ? [initialQuestion] : []));
  const [currentIndex, setCurrentIndex] = useState(() => {
    if (isCat) return 0;
    if (initialSessionState.current_index !== undefined && initialSessionState.current_index < questionsList.length) {
      return initialSessionState.current_index;
    }
    return 0;
  });

  // Current active question
  const [currentQuestion, setCurrentQuestion] = useState(() => {
    if (isCat) return initialQuestion;
    return questionsList[currentIndex] || initialQuestion;
  });

  // Answer tracking map: [questionId => { selected, selections, interactionResponse, isCorrect }]
  const [answersMap, setAnswersMap] = useState(() => {
    const map = {};
    if (existingAnswers && typeof existingAnswers === 'object') {
      Object.entries(existingAnswers).forEach(([qId, ans]) => {
        map[Number(qId)] = {
          selected: ans.selected_option || '',
          selections: ans.selected_option ? ans.selected_option.split(',') : [],
          interactionResponse: ans.interaction_response || null,
          isCorrect: ans.is_correct,
        };
      });
    }
    return map;
  });

  // Active question candidate response state
  const activeAnswer = currentQuestion?.id ? answersMap[currentQuestion.id] : null;
  const [selected, setSelected] = useState(() => activeAnswer?.selected || '');
  const [selections, setSelections] = useState(() => activeAnswer?.selections || []);
  const [interactionResponse, setInteractionResponse] = useState(() => activeAnswer?.interactionResponse || null);

  // Answered questions set
  const answeredIds = useMemo(() => new Set(Object.keys(answersMap).map(Number)), [answersMap]);
  const [answeredCount, setAnsweredCount] = useState(initialAnsweredCount);

  // Candidate proctoring aids
  const [flagged, setFlagged] = useState(() => new Set(initialSessionState.flagged || []));
  const [strikesMap, setStrikesMap] = useState(() => initialSessionState.strikes || {});
  const [highlightActive, setHighlightActive] = useState(false);
  const [candidateNotes, setCandidateNotes] = useState(() => initialSessionState.notes || '');

  // Active question's strikes
  const currentStrikes = useMemo(() => {
    const arr = currentQuestion?.id ? strikesMap[currentQuestion.id] || [] : [];
    return new Set(arr);
  }, [strikesMap, currentQuestion?.id]);

  // Timers and pausing
  const timeLimitSeconds = (attempt.exam?.time_minutes ?? attempt.session?.time_limit ?? 0) * 60;
  const isTimed = timeLimitSeconds > 0;
  const [secondsLeft, setSecondsLeft] = useState(() => initialRemainingSeconds !== null ? initialRemainingSeconds : timeLimitSeconds);
  const [isPaused, setIsPaused] = useState(initialIsPaused);

  // Haptic countdown warnings at 5 minutes and 1 minute
  useEffect(() => {
    if (isTimed && (secondsLeft === 300 || secondsLeft === 60)) {
      HapticFeedback.warning();
    }
  }, [isTimed, secondsLeft]);

  // Modals & Panels
  const [instructionsOpen, setInstructionsOpen] = useState(false);
  const [calculatorOpen, setCalculatorOpen] = useState(false);
  const [reviewOpen, setReviewOpen] = useState(false);
  const [shortcutsOpen, setShortcutsOpen] = useState(false);
  const [breakOpen, setBreakOpen] = useState(false);
  const [paletteTab, setPaletteTab] = useState('palette'); // 'palette' | 'details'
  const [paletteFilter, setPaletteFilter] = useState('all'); // 'all' | 'unanswered' | 'flagged'
  const [processing, setProcessing] = useState(false);
  const [feedback, setFeedback] = useState(null);

  // Mobile Touch Ergonomics & OLED Mode
  const [oledMode, setOledMode] = useState(() => {
    try {
      return localStorage.getItem('pmk_oled_mode') === 'true';
    } catch {
      return false;
    }
  });

  const toggleOledMode = () => {
    HapticFeedback.selection();
    setOledMode((prev) => {
      const next = !prev;
      try {
        localStorage.setItem('pmk_oled_mode', String(next));
      } catch {}
      return next;
    });
  };

  const [mobilePaletteOpen, setMobilePaletteOpen] = useState(false);
  const [touchStartPos, setTouchStartPos] = useState({ x: 0, y: 0 });

  const handleTouchStart = (e) => {
    if (e.touches && e.touches.length === 1) {
      setTouchStartPos({ x: e.touches[0].clientX, y: e.touches[0].clientY });
    }
  };

  const handleTouchEnd = (e) => {
    if (!e.changedTouches || e.changedTouches.length === 0) return;
    const deltaX = e.changedTouches[0].clientX - touchStartPos.x;
    const deltaY = e.changedTouches[0].clientY - touchStartPos.y;

    if (Math.abs(deltaX) > 75 && Math.abs(deltaY) < 60) {
      if (deltaX < -75) {
        // Swipe Left: Next question / Submit
        if (!isCat && currentIndex < questionsList.length - 1) {
          HapticFeedback.selection();
          handleNext();
        } else if (canSubmit && !processing) {
          HapticFeedback.selection();
          submitAnswer();
        }
      } else if (deltaX > 75) {
        // Swipe Right: Previous question
        if (!isCat && currentIndex > 0) {
          HapticFeedback.selection();
          handlePrevious();
        }
      }
    }
  };

  // Desktop Power User Keyboard Shortcuts (VCE Parity)
  useEffect(() => {
    const unbind = TauriBridge.registerDesktopShortcuts({
      onSelectOption: (optionIdx) => {
        if (!currentQuestion?.options || optionIdx >= currentQuestion.options.length) return;
        const opt = currentQuestion.options[optionIdx];
        if (!opt) return;
        if (isMultiSelect) {
          setSelections((prev) => {
            const next = prev.includes(opt.key) ? prev.filter((k) => k !== opt.key) : [...prev, opt.key];
            HapticFeedback.selection();
            return next;
          });
        } else {
          handleSingleSelect(opt.key);
        }
      },
      onToggleFlag: toggleFlag,
      onToggleStrike: () => {
        if (selected) {
          toggleStrike(selected);
        } else if (currentQuestion?.options?.[0]) {
          toggleStrike(currentQuestion.options[0].key);
        }
      },
      onTogglePalette: () => {
        if (window.innerWidth < 1024) {
          setMobilePaletteOpen((prev) => !prev);
        } else {
          setPaletteTab((prev) => (prev === 'palette' ? 'details' : 'palette'));
        }
      },
      onToggleCalculator: () => setCalculatorOpen((prev) => !prev),
      onPause: handleTogglePause,
      onNext: () => {
        if (!isCat && currentIndex < questionsList.length - 1) {
          handleNext();
        }
      },
      onPrevious: () => {
        if (!isCat && currentIndex > 0) {
          handlePrevious();
        }
      },
      onSubmit: () => {
        if (canSubmit && !processing) {
          submitAnswer();
        }
      },
    });

    return () => unbind && unbind();
  }, [currentQuestion, isMultiSelect, selected, canSubmit, processing, currentIndex, questionsList.length, isCat]);

  const breakThresholds = useMemo(() => {
    if (isPractice) return [];
    if (totalQuestions >= 75) return [Math.floor(totalQuestions / 2), totalQuestions - 5];
    return [totalQuestions - 5].filter((count) => count >= Math.min(3, totalQuestions) && count < totalQuestions);
  }, [totalQuestions, isPractice]);

  // Sync active question inputs when changing questions
  useEffect(() => {
    if (!currentQuestion) return;
    const ans = answersMap[currentQuestion.id];
    if (ans) {
      setSelected(ans.selected || '');
      setSelections(ans.selections || []);
      setInteractionResponse(ans.interactionResponse || null);
    } else {
      setSelected('');
      setSelections([]);
      setInteractionResponse(null);
    }
    setFeedback(null);
  }, [currentQuestion?.id]);

  // Timer countdown
  useEffect(() => {
    if (!isTimed || !currentQuestion || instructionsOpen || breakOpen || isPaused) return undefined;
    const timer = window.setInterval(() => setSecondsLeft((val) => Math.max(val - 1, 0)), 1000);
    return () => window.clearInterval(timer);
  }, [isTimed, currentQuestion, instructionsOpen, breakOpen, isPaused]);

  // Time expiration auto-finish
  useEffect(() => {
    if (isTimed && secondsLeft === 0 && currentQuestion && !processing && !isPaused) {
      finishAttempt();
    }
  }, [isTimed, secondsLeft, currentQuestion, processing, isPaused]);

  // Periodic proctoring state sync (every 30s)
  useEffect(() => {
    if (!isTimed || isPaused) return undefined;
    const interval = window.setInterval(() => {
      axios.post(`/passimark/attempt/${attempt.id}/state`, {
        remaining_seconds: secondsLeft,
        session_state: {
          current_index: currentIndex,
          flagged: Array.from(flagged),
          strikes: strikesMap,
          notes: candidateNotes,
        },
      }).catch(() => {});
    }, 30000);
    return () => window.clearInterval(interval);
  }, [attempt.id, secondsLeft, isPaused, currentIndex, flagged, strikesMap, candidateNotes]);

  // Finish Attempt
  const finishAttempt = async () => {
    if (processing) return;
    setProcessing(true);
    try {
      await axios.post(`/passimark/attempt/${attempt.id}/finish`);
      router.visit(`/passimark/attempt/${attempt.id}/result`);
    } catch (error) {
      setFeedback({ error: error.response?.data?.message || 'Unable to finish this assessment.' });
      setProcessing(false);
    }
  };

  // Pause / Resume Assessment
  const handleTogglePause = async () => {
    if (processing) return;
    setProcessing(true);
    try {
      if (isPaused) {
        await axios.post(`/passimark/attempt/${attempt.id}/resume`);
        setIsPaused(false);
      } else {
        await axios.post(`/passimark/attempt/${attempt.id}/pause`, {
          remaining_seconds: secondsLeft,
          session_state: {
            current_index: currentIndex,
            flagged: Array.from(flagged),
            strikes: strikesMap,
            notes: candidateNotes,
          },
        });
        setIsPaused(true);
      }
    } catch (error) {
      setFeedback({ error: 'Unable to update pause state.' });
    } finally {
      setProcessing(false);
    }
  };

  // Navigation handlers for non-CAT free-roam
  const jumpToQuestionIndex = (targetIndex) => {
    if (targetIndex < 0 || targetIndex >= questionsList.length) return;
    // Save draft of current question
    if (currentQuestion?.id) {
      setAnswersMap((prev) => ({
        ...prev,
        [currentQuestion.id]: {
          ...(prev[currentQuestion.id] || {}),
          selected,
          selections,
          interactionResponse,
        },
      }));
    }
    setCurrentIndex(targetIndex);
    setCurrentQuestion(questionsList[targetIndex]);
  };

  const handlePrevious = () => {
    if (currentIndex > 0) jumpToQuestionIndex(currentIndex - 1);
  };

  const handleNext = () => {
    if (currentIndex < questionsList.length - 1) jumpToQuestionIndex(currentIndex + 1);
  };

  // Flag toggle
  const activePosition = isCat ? Math.min(answeredCount + 1, totalQuestions) : currentIndex + 1;
  const toggleFlag = () => {
    HapticFeedback.selection();
    setFlagged((current) => {
      const next = new Set(current);
      if (next.has(activePosition)) next.delete(activePosition);
      else next.add(activePosition);
      return next;
    });
  };

  // Strikethrough toggle
  const toggleStrike = (optionKey) => {
    if (!currentQuestion?.id) return;
    HapticFeedback.strikethrough();
    setStrikesMap((prev) => {
      const currentList = prev[currentQuestion.id] || [];
      const set = new Set(currentList);
      if (set.has(optionKey)) set.delete(optionKey);
      else set.add(optionKey);
      return { ...prev, [currentQuestion.id]: Array.from(set) };
    });
  };

  const handleSingleSelect = (val) => {
    HapticFeedback.selection();
    setSelected(val);
  };

  const handleMultiSelect = (vals) => {
    HapticFeedback.selection();
    setSelections(vals);
  };

  // Current item type & submission validation
  const isMultiSelect = Boolean(currentQuestion?.multi_select || currentQuestion?.type === 'multiple_choice');
  const itemType = currentQuestion?.type || (isMultiSelect ? 'multiple_choice' : 'single_choice');
  const isInteractive = ['drag_drop', 'hotspot', 'build_list', 'cloze', 'create_tree', 'fill_blank'].includes(itemType);

  const canSubmit = (() => {
    switch (itemType) {
      case 'drag_drop':
        return Boolean(interactionResponse && Object.keys(interactionResponse).length > 0);
      case 'hotspot':
        return Boolean(interactionResponse !== null);
      case 'build_list':
        return Boolean(Array.isArray(interactionResponse) && interactionResponse.length > 0);
      case 'cloze':
        return Boolean(interactionResponse && Object.keys(interactionResponse).length > 0);
      case 'create_tree':
        return Boolean(Array.isArray(interactionResponse) && interactionResponse.length > 0);
      case 'fill_blank':
        return Boolean(
          typeof interactionResponse === 'string'
            ? interactionResponse.trim().length > 0
            : (interactionResponse?.text?.trim()?.length > 0 || selected?.trim()?.length > 0)
        );
      case 'multiple_choice':
        return selections.length > 0;
      default:
        return Boolean(selected);
    }
  })();

  // Submit Answer
  const submitAnswer = async () => {
    if (!currentQuestion || processing || !canSubmit) return;
    const chosen = isMultiSelect ? selections : selected;
    setProcessing(true);
    setFeedback(null);

    const submissionPayload = {
      question_id: currentQuestion.id,
      selected: isMultiSelect ? chosen.slice().sort().join(',') : (isInteractive ? undefined : chosen),
      selections: isMultiSelect ? chosen : undefined,
      interaction_response: isInteractive ? interactionResponse : undefined,
      time_spent: isTimed ? Math.max(0, timeLimitSeconds - secondsLeft) : 0,
    };

    if (itemType === 'fill_blank') {
      const text = typeof interactionResponse === 'string' ? interactionResponse : (interactionResponse?.text || selected || '');
      submissionPayload.interaction_response = { text };
      submissionPayload.selected = text;
    }

    try {
      const response = await axios.post(`/passimark/attempt/${attempt.id}/answer`, submissionPayload);

      // Record in local answers map
      setAnswersMap((prev) => ({
        ...prev,
        [currentQuestion.id]: {
          selected: isMultiSelect ? chosen.join(',') : selected,
          selections,
          interactionResponse,
          isCorrect: response.data.correct,
        },
      }));

      const newAnsweredCount = response.data.answeredCount ?? (answeredCount + 1);
      setAnsweredCount(newAnsweredCount);

      if (response.data.correct) {
        HapticFeedback.success();
      } else {
        HapticFeedback.selection();
      }

      if (isPractice) {
        setFeedback({ correct: response.data.correct, message: response.data.feedback, explanation: response.data.explanation });
      } else if (isFinal) {
        setFeedback({ correct: response.data.correct, final: true });
      } else {
        setFeedback({ correct: response.data.correct, message: response.data.feedback || 'Answer recorded.' });
      }

      if (isCat) {
        if (response.data.next) {
          setCurrentQuestion(response.data.next);
          setSelected('');
          setSelections([]);
          setInteractionResponse(null);
          if (breakThresholds.includes(newAnsweredCount)) {
            setBreakOpen(true);
          }
        } else if (response.data.score !== undefined) {
          router.visit(`/passimark/attempt/${attempt.id}/result`);
        }
      } else {
        // In free-roam mode, auto-advance to next question if not at end
        if (currentIndex < questionsList.length - 1) {
          setTimeout(() => {
            handleNext();
          }, 600);
        }
      }
    } catch (error) {
      setFeedback({ error: error.response?.data?.message || 'Unable to record this answer.' });
    } finally {
      setProcessing(false);
    }
  };

  // Exit assessment
  const exitAssessment = () => {
    if (window.confirm('Exit now? Your progress is saved and you can resume this assessment later.')) {
      router.visit('/');
    }
  };

  const minutes = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
  const seconds = String(secondsLeft % 60).padStart(2, '0');
  const options = currentQuestion?.options || [];
  const progressPercent = Math.min(100, Math.round((answeredCount / totalQuestions) * 100));

  // Palette Items for interactive navigation
  const paletteItems = Array.from({ length: totalQuestions }, (_, index) => {
    const position = index + 1;
    const qObj = questionsList[index];
    const qId = qObj?.id;
    const isAnswered = qId ? answeredIds.has(qId) : index < answeredCount;
    const isFlagged = flagged.has(position);
    const isCurrent = isCat ? position === activePosition : index === currentIndex;

    let status = 'unanswered';
    if (isCurrent) status = 'current';
    else if (isFlagged) status = 'flagged';
    else if (isAnswered) status = 'answered';

    return {
      index,
      position,
      status,
      isAnswered,
      isFlagged,
      isCurrent,
    };
  });

  const filteredPaletteItems = paletteItems.filter((item) => {
    if (paletteFilter === 'unanswered') return !item.isAnswered;
    if (paletteFilter === 'flagged') return item.isFlagged;
    return true;
  });

  const paletteStyle = {
    answered: 'bg-brand-500/70 text-slate-950 hover:bg-brand-500 font-semibold',
    flagged: 'bg-amber-400 text-slate-950 hover:bg-amber-300 font-semibold',
    current: 'bg-white text-slate-950 ring-2 ring-brand-400 font-bold shadow-md',
    unanswered: 'bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700',
  };

  return (
    <>
      <Head title={`${attempt.session?.title || 'Assessment'} - Exam`} />
      <main className={`min-h-screen select-none transition-colors duration-200 pb-24 lg:pb-8 ${oledMode ? 'bg-black text-white' : 'bg-slate-950 text-white'}`}>
        {/* Top Header */}
        <header className={`border-b px-6 py-3.5 sticky top-0 z-40 transition-colors duration-200 ${oledMode ? 'border-zinc-900 bg-black' : 'border-slate-800 bg-slate-900'}`}>
          <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2">
                <span className="rounded bg-brand-500/15 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-brand-300">
                  {isCat ? 'Adaptive IRT CAT' : `${attempt.mode.toUpperCase()} MODE`}
                </span>
                <span className="text-xs text-slate-400">Passimark Exam Runner</span>
              </div>
              <h1 className="mt-0.5 text-base font-semibold text-slate-100 truncate max-w-md">
                {attempt.session?.title}
              </h1>
            </div>

            <div className="flex items-center gap-3">
              {/* OLED Mode Toggle */}
              <button
                type="button"
                onClick={toggleOledMode}
                className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition ${
                  oledMode
                    ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                    : 'border-slate-700 text-slate-300 hover:bg-slate-800'
                }`}
                title={oledMode ? 'Switch to Dark Slate Mode' : 'Switch to Pure OLED Black Mode'}
              >
                {oledMode ? <Sun className="h-3.5 w-3.5 text-amber-400" /> : <Moon className="h-3.5 w-3.5 text-brand-300" />}
                <span className="hidden sm:inline">{oledMode ? 'OLED' : 'Dark'}</span>
              </button>

              {/* Highlight Tool Toggle */}
              <button
                type="button"
                onClick={() => setHighlightActive((prev) => !prev)}
                className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition ${
                  highlightActive
                    ? 'border-amber-400 bg-amber-400/20 text-amber-300'
                    : 'border-slate-700 text-slate-300 hover:bg-slate-800'
                }`}
                title="Toggle Text Highlight Mode"
              >
                <Highlighter className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Highlight</span>
              </button>

              {/* Calculator Toggle */}
              <button
                type="button"
                onClick={() => setCalculatorOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition"
              >
                <Calculator className="h-3.5 w-3.5 text-brand-300" />
                <span className="hidden sm:inline">Calculator</span>
              </button>

              {/* Desktop Keyboard Shortcuts Modal Trigger */}
              <button
                type="button"
                onClick={() => setShortcutsOpen(true)}
                className="hidden md:inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition"
                title="Desktop Keyboard Shortcuts (1-9, Ctrl+F, Ctrl+S, Esc)"
              >
                <Keyboard className="h-3.5 w-3.5 text-brand-300" />
                <span className="hidden lg:inline">Shortcuts</span>
              </button>

              {/* Pause / Resume Button */}
              {isTimed && (
                <button
                  type="button"
                  onClick={handleTogglePause}
                  className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition ${
                    isPaused
                      ? 'border-brand-500 bg-brand-500/20 text-brand-300'
                      : 'border-slate-700 text-slate-300 hover:bg-slate-800'
                  }`}
                >
                  {isPaused ? <Play className="h-3.5 w-3.5" /> : <Pause className="h-3.5 w-3.5" />}
                  <span>{isPaused ? 'Resume' : 'Pause'}</span>
                </button>
              )}

              {/* Timer Display */}
              <div
                className={`flex items-center gap-2 font-mono text-base font-semibold tabular-nums px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 ${
                  isTimed && secondsLeft < 300 ? 'text-orange-400 animate-pulse' : 'text-slate-200'
                }`}
              >
                <Clock3 className="h-4 w-4 text-brand-400" />
                {isTimed ? `${minutes}:${seconds}` : 'Untimed'}
              </div>

              {/* Review & Submit CTA */}
              <button
                type="button"
                onClick={() => setReviewOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500/15 border border-brand-500/40 px-3 py-1.5 text-xs font-semibold text-brand-300 hover:bg-brand-500/25 transition"
              >
                <ClipboardCheck className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Review &amp; Finish</span>
              </button>

              {/* Save & Exit */}
              <button
                type="button"
                onClick={exitAssessment}
                className="inline-flex items-center gap-1 rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs text-slate-400 hover:bg-slate-800 hover:text-slate-200 transition"
                title="Save & Exit"
              >
                <LogOut className="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </header>

        {/* Global Progress Bar */}
        <div className="mx-auto max-w-7xl px-6 pt-4">
          <div className="flex items-center justify-between text-xs text-slate-400">
            <span>
              Question <strong>{activePosition}</strong> of {totalQuestions}
            </span>
            <span>{progressPercent}% answered</span>
          </div>
          <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-800">
            <div
              className="h-full rounded-full bg-brand-500 transition-all duration-300"
              style={{ width: `${progressPercent}%` }}
            />
          </div>
        </div>

        {/* Main Stage */}
        <section className="mx-auto max-w-7xl px-6 py-6">
          {breakOpen ? (
            <div className="mx-auto max-w-xl rounded-2xl border border-slate-700 bg-slate-900 p-8 text-center shadow-xl">
              <Coffee className="mx-auto h-12 w-12 text-brand-400" />
              <p className="mt-4 text-xs font-bold uppercase tracking-wider text-brand-400">Scheduled Break</p>
              <h2 className="mt-2 text-2xl font-bold">Take a breather</h2>
              <p className="mt-4 text-sm leading-6 text-slate-300">
                You have reached checkpoint {answeredCount} of {totalQuestions}. The timer is paused.
              </p>
              <button
                type="button"
                onClick={() => setBreakOpen(false)}
                className="mt-6 rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-bold text-slate-950 hover:bg-brand-400 transition"
              >
                Continue Assessment
              </button>
            </div>
          ) : currentQuestion ? (
            <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
              {/* Question Workspace Article */}
              <article
                onTouchStart={handleTouchStart}
                onTouchEnd={handleTouchEnd}
                className={`rounded-2xl border p-6 md:p-8 shadow-sm transition-colors duration-200 ${
                  oledMode ? 'border-zinc-800/80 bg-[#050505]' : 'border-slate-800 bg-slate-900'
                }`}
              >
                {/* Meta Strip */}
                <div className="flex items-center justify-between text-xs text-slate-400 border-b border-slate-800 pb-3">
                  <div className="flex items-center gap-3">
                    <span className="font-semibold text-slate-200">Item #{activePosition}</span>
                    {currentQuestion.domain && (
                      <span className="rounded bg-slate-800 px-2 py-0.5 text-[11px] text-slate-300">
                        {currentQuestion.domain}
                      </span>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={toggleFlag}
                      className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                        flagged.has(activePosition)
                          ? 'bg-amber-400/20 text-amber-300 border border-amber-400/40'
                          : 'border border-slate-700 text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                      }`}
                    >
                      <Flag className="h-3.5 w-3.5" />
                      {flagged.has(activePosition) ? 'Flagged' : 'Flag'}
                    </button>
                  </div>
                </div>

                {/* Question Stem */}
                <div className={`mt-6 text-lg font-medium leading-relaxed text-slate-100 ${highlightActive ? 'cursor-text bg-amber-400/5 p-2 rounded-lg' : ''}`}>
                  {currentQuestion.content}
                </div>

                {/* Exhibit Dossier / Case Study Tabs */}
                {Boolean(
                  (currentQuestion.exhibits && currentQuestion.exhibits.length > 0) ||
                    currentQuestion.scenario ||
                    itemType === 'case_study'
                ) && (
                  <div className="mt-6">
                    <CaseStudyViewer
                      scenario={currentQuestion.scenario}
                      exhibits={currentQuestion.exhibits || []}
                      notes={candidateNotes}
                      onNotesChange={setCandidateNotes}
                    />
                  </div>
                )}

                {/* Polymorphic Item Renderers */}
                <div className="mt-8">
                  {itemType === 'drag_drop' ? (
                    <QuestionDragDrop
                      payload={currentQuestion.interactive_payload}
                      value={interactionResponse || {}}
                      onChange={setInteractionResponse}
                      disabled={processing}
                    />
                  ) : itemType === 'hotspot' ? (
                    <QuestionHotspot
                      payload={currentQuestion.interactive_payload}
                      value={interactionResponse}
                      onChange={setInteractionResponse}
                      disabled={processing}
                    />
                  ) : itemType === 'build_list' ? (
                    <QuestionReorder
                      payload={currentQuestion.interactive_payload}
                      value={interactionResponse || []}
                      onChange={setInteractionResponse}
                      disabled={processing}
                    />
                  ) : itemType === 'cloze' ? (
                    <QuestionCloze
                      payload={currentQuestion.interactive_payload}
                      value={interactionResponse || {}}
                      onChange={setInteractionResponse}
                      disabled={processing}
                    />
                  ) : itemType === 'create_tree' ? (
                    <QuestionCreateTree
                      payload={currentQuestion.interactive_payload}
                      value={interactionResponse || []}
                      onChange={setInteractionResponse}
                      disabled={processing}
                    />
                  ) : itemType === 'fill_blank' ? (
                    <QuestionFillBlank
                      payload={currentQuestion.interactive_payload}
                      value={interactionResponse || selected || ''}
                      onChange={(val) => {
                        setInteractionResponse(val);
                        setSelected(val);
                      }}
                      disabled={processing}
                    />
                  ) : isMultiSelect ? (
                    <QuestionMultiSelect
                      options={options}
                      selections={selections}
                      onChange={handleMultiSelect}
                      strikes={currentStrikes}
                      onToggleStrike={toggleStrike}
                      disabled={processing}
                    />
                  ) : (
                    <QuestionSingleChoice
                      options={options}
                      selected={selected}
                      onChange={handleSingleSelect}
                      strikes={currentStrikes}
                      onToggleStrike={toggleStrike}
                      disabled={processing}
                    />
                  )}
                </div>

                {/* Helper notice */}
                {!isInteractive && (
                  <p className="mt-4 text-right text-xs text-slate-500">
                    Strikethrough is a personal aid only — scoring evaluates your selected choice.
                  </p>
                )}

                {/* Feedback Alerts */}
                {feedback?.error && (
                  <div className="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-300">
                    {feedback.error}
                  </div>
                )}
                {feedback?.correct !== undefined && (
                  <div
                    className={`mt-4 rounded-lg p-3 text-xs flex items-center gap-2 ${
                      isFinal
                        ? 'bg-slate-800 text-slate-300 border border-slate-700'
                        : feedback.correct
                        ? 'bg-brand-500/15 text-brand-300 border border-brand-500/30'
                        : 'bg-orange-500/15 text-orange-300 border border-orange-500/30'
                    }`}
                  >
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                    <span>
                      {isFinal
                        ? 'Response saved securely.'
                        : feedback.message || (feedback.correct ? 'Correct answer recorded.' : 'Response recorded.')}
                    </span>
                  </div>
                )}

                {/* AI Tutor Distractor Rationale in Practice Mode */}
                {isPractice && feedback?.correct === false && currentQuestion?.id && (
                  <AiDistractorExplainer
                    questionId={currentQuestion.id}
                    chosenKey={selected || (selections && selections[0]) || ''}
                  />
                )}

                {/* Free-Roam Navigation & Submit Action Bar */}
                <div className="mt-10 flex items-center justify-between border-t border-slate-800 pt-5">
                  <div className="flex items-center gap-2">
                    {!isCat && (
                      <button
                        type="button"
                        onClick={handlePrevious}
                        disabled={currentIndex === 0 || processing}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-200 hover:bg-slate-750 disabled:opacity-30 disabled:cursor-not-allowed transition"
                      >
                        <ChevronLeft className="h-4 w-4" /> Previous
                      </button>
                    )}
                  </div>

                  <div className="flex items-center gap-3">
                    <button
                      type="button"
                      onClick={submitAnswer}
                      disabled={!canSubmit || processing}
                      className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-2.5 text-xs font-bold text-slate-950 transition hover:bg-brand-400 disabled:cursor-not-allowed disabled:bg-slate-800 disabled:text-slate-500 shadow-sm"
                    >
                      {processing ? 'Saving...' : 'Save Answer'}
                      <Send className="h-3.5 w-3.5" />
                    </button>

                    {!isCat && (
                      <button
                        type="button"
                        onClick={handleNext}
                        disabled={currentIndex === questionsList.length - 1 || processing}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-200 hover:bg-slate-750 disabled:opacity-30 disabled:cursor-not-allowed transition"
                      >
                        Next <ChevronRight className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                </div>
              </article>

              {/* Sidebar: Interactive Question Palette & Details */}
              <aside className={`hidden lg:block h-fit rounded-2xl border p-5 shadow-sm transition-colors duration-200 ${
                oledMode ? 'border-zinc-800/80 bg-[#050505]' : 'border-slate-800 bg-slate-900'
              }`}>
                <div className="flex gap-2 border-b border-slate-800 pb-3">
                  <button
                    type="button"
                    onClick={() => setPaletteTab('palette')}
                    className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                      paletteTab === 'palette'
                        ? 'bg-brand-500/15 text-brand-300'
                        : 'text-slate-400 hover:text-slate-200'
                    }`}
                  >
                    Palette Grid
                  </button>
                  <button
                    type="button"
                    onClick={() => setPaletteTab('details')}
                    className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                      paletteTab === 'details'
                        ? 'bg-brand-500/15 text-brand-300'
                        : 'text-slate-400 hover:text-slate-200'
                    }`}
                  >
                    Exam Specs
                  </button>
                </div>

                {paletteTab === 'palette' ? (
                  <>
                    {/* Palette Filter Pills */}
                    <div className="mt-4 flex gap-1 bg-slate-950/80 p-1 rounded-lg border border-slate-800 text-[11px]">
                      <button
                        type="button"
                        onClick={() => setPaletteFilter('all')}
                        className={`flex-1 py-1 rounded font-medium text-center transition ${
                          paletteFilter === 'all'
                            ? 'bg-slate-800 text-slate-100 font-bold'
                            : 'text-slate-400 hover:text-slate-200'
                        }`}
                      >
                        All ({totalQuestions})
                      </button>
                      <button
                        type="button"
                        onClick={() => setPaletteFilter('unanswered')}
                        className={`flex-1 py-1 rounded font-medium text-center transition ${
                          paletteFilter === 'unanswered'
                            ? 'bg-slate-800 text-slate-100 font-bold'
                            : 'text-slate-400 hover:text-slate-200'
                        }`}
                      >
                        Unans ({totalQuestions - answeredCount})
                      </button>
                      <button
                        type="button"
                        onClick={() => setPaletteFilter('flagged')}
                        className={`flex-1 py-1 rounded font-medium text-center transition ${
                          paletteFilter === 'flagged'
                            ? 'bg-slate-800 text-slate-100 font-bold'
                            : 'text-slate-400 hover:text-slate-200'
                        }`}
                      >
                        Flags ({flagged.size})
                      </button>
                    </div>

                    {/* Interactive Question Grid */}
                    <div className="mt-4 grid grid-cols-6 gap-1.5 max-h-72 overflow-y-auto pr-1">
                      {filteredPaletteItems.map((item) => (
                        <button
                          key={item.position}
                          type="button"
                          onClick={() => {
                            if (!isCat) {
                              jumpToQuestionIndex(item.index);
                            }
                          }}
                          disabled={isCat && item.position > activePosition}
                          className={`flex h-8 items-center justify-center rounded text-xs transition disabled:cursor-not-allowed disabled:opacity-40 ${
                            paletteStyle[item.status] || paletteStyle.unanswered
                          }`}
                          title={`Jump to Question ${item.position}`}
                        >
                          {item.position}
                        </button>
                      ))}
                    </div>

                    {/* Palette Legend */}
                    <div className="mt-5 space-y-1.5 border-t border-slate-800 pt-3 text-xs text-slate-400">
                      <p className="flex items-center gap-2">
                        <span className="inline-block h-3 w-3 rounded bg-brand-500/70" /> Answered ({answeredCount})
                      </p>
                      <p className="flex items-center gap-2">
                        <span className="inline-block h-3 w-3 rounded bg-amber-400" /> Flagged ({flagged.size})
                      </p>
                      <p className="flex items-center gap-2">
                        <span className="inline-block h-3 w-3 rounded bg-white ring-1 ring-brand-400" /> Current
                      </p>
                    </div>

                    {/* Pre-Submit Review Modal Trigger */}
                    <button
                      type="button"
                      onClick={() => setReviewOpen(true)}
                      className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-800/80 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800 hover:border-slate-600 transition"
                    >
                      <ClipboardCheck className="h-4 w-4 text-brand-300" /> Pre-Submit Review
                    </button>
                  </>
                ) : (
                  <dl className="mt-4 space-y-3 text-xs">
                    <div className="flex justify-between border-b border-slate-800 pb-2">
                      <dt className="text-slate-400">Mode</dt>
                      <dd className="font-semibold uppercase text-brand-300">{attempt.mode}</dd>
                    </div>
                    <div className="flex justify-between border-b border-slate-800 pb-2">
                      <dt className="text-slate-400">Total Items</dt>
                      <dd className="font-mono text-slate-200">{totalQuestions}</dd>
                    </div>
                    <div className="flex justify-between border-b border-slate-800 pb-2">
                      <dt className="text-slate-400">Pass Requirement</dt>
                      <dd className="text-slate-200">
                        {isFinal ? 'θ ≥ required bar' : `${attempt.session?.pass_score || 70}%`}
                      </dd>
                    </div>
                    {isCat && (
                      <div className="flex justify-between border-b border-slate-800 pb-2">
                        <dt className="text-slate-400">Current Ability θ</dt>
                        <dd className="font-mono text-brand-300">
                          {Number(attempt.theta || 0).toFixed(4)}
                        </dd>
                      </div>
                    )}
                  </dl>
                )}
              </aside>
            </div>
          ) : (
            <div className="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center shadow-lg">
              <h2 className="text-2xl font-bold">Assessment Complete</h2>
              <p className="mt-2 text-sm text-slate-400">Compiling psychometric score and credential evaluation...</p>
              <button
                type="button"
                onClick={() => router.visit('/')}
                className="mt-6 rounded-lg bg-brand-500 px-6 py-2.5 text-xs font-bold text-slate-950 hover:bg-brand-400"
              >
                Return to Dashboard
              </button>
            </div>
          )}
        </section>
      </main>

      {/* Stateful Assessment Paused Full-Screen Overlay */}
      {isPaused && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/95 backdrop-blur-md p-6">
          <div className="max-w-md w-full rounded-2xl border border-slate-700 bg-slate-900 p-8 text-center shadow-2xl">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-500/15 border border-brand-500/40">
              <Pause className="h-8 w-8 text-brand-300" />
            </div>
            <h2 className="mt-5 text-2xl font-bold text-slate-100">Assessment Paused</h2>
            <p className="mt-3 text-xs leading-relaxed text-slate-300">
              The assessment timer is stopped. Your answered items, strikethroughs, and notes are securely saved. You can take a break or resume at any time.
            </p>
            <div className="mt-5 rounded-xl border border-slate-800 bg-slate-950 p-3 text-xs font-mono text-slate-400">
              Time Remaining: <span className="font-bold text-brand-300">{minutes}:{seconds}</span>
            </div>
            <button
              type="button"
              onClick={handleTogglePause}
              disabled={processing}
              className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 py-3 text-sm font-bold text-slate-950 hover:bg-brand-400 transition"
            >
              <Play className="h-4 w-4" /> Resume Assessment
            </button>
          </div>
        </div>
      )}

      {/* Advanced Scientific / Subnetting Calculator Modal */}
      <AdvancedCalculator
        isOpen={calculatorOpen}
        onClose={() => setCalculatorOpen(false)}
      />

      {/* Pre-Submit Review Screen Modal */}
      <PreSubmitReviewModal
        isOpen={reviewOpen}
        onClose={() => setReviewOpen(false)}
        totalQuestions={totalQuestions}
        questions={questionsList}
        answeredIds={answeredIds}
        flaggedIndices={flagged}
        onJumpToQuestion={jumpToQuestionIndex}
        onConfirmSubmit={finishAttempt}
        processing={processing}
      />

      {/* Ergonomic Thumb Zone Bar for Mobile */}
      <ThumbZoneBar
        activePosition={activePosition}
        totalQuestions={totalQuestions}
        canGoPrevious={!isCat && currentIndex > 0}
        onPrevious={handlePrevious}
        onNext={() => {
          if (!isCat && currentIndex < questionsList.length - 1) {
            handleNext();
          } else if (canSubmit && !processing) {
            submitAnswer();
          }
        }}
        isFlagged={flagged.has(activePosition)}
        onToggleFlag={toggleFlag}
        onOpenPalette={() => setMobilePaletteOpen(true)}
        onOpenCalculator={() => setCalculatorOpen(true)}
        isSubmitting={processing}
        isLastQuestion={isCat ? false : currentIndex === questionsList.length - 1}
      />

      {/* Gesture-Friendly Bottom Sheet Question Palette Drawer for Mobile */}
      <BottomSheetPalette
        isOpen={mobilePaletteOpen}
        onClose={() => setMobilePaletteOpen(false)}
        totalQuestions={totalQuestions}
        activePosition={activePosition}
        answeredCount={answeredCount}
        flaggedCount={flagged.size}
        paletteItems={paletteItems}
        onJumpToQuestion={jumpToQuestionIndex}
        isCat={isCat}
        paletteStyle={paletteStyle}
      />

      {/* Desktop VCE Keyboard Shortcuts Guide Modal */}
      <DesktopShortcutsModal
        isOpen={shortcutsOpen}
        onClose={() => setShortcutsOpen(false)}
      />
    </>
  );
}
