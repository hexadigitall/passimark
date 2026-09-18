import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import {
  CheckCircle2,
  Clock3,
  Flag,
  LogOut,
  Send,
  Strikethrough,
  Calculator,
  Coffee,
  X,
  ClipboardCheck,
} from 'lucide-react';

/* ---------------------------------------------------------------------------
 * Minimal shunting-yard calculator (+, -, *, /, %, parentheses) — no eval.
 * ------------------------------------------------------------------------- */
function tokenise(expression) {
  const tokens = [];
  const cleaned = expression.replace(/\s+/g, '');
  let index = 0;
  while (index < cleaned.length) {
    const char = cleaned[index];
    if ('+-*/%()'.includes(char)) {
      tokens.push({ type: 'op', value: char });
      index += 1;
    } else if (/[0-9.]/.test(char)) {
      let end = index;
      while (end < cleaned.length && /[0-9.]/.test(cleaned[end])) end += 1;
      const literal = cleaned.slice(index, end);
      if (Number.isNaN(Number(literal))) return null;
      tokens.push({ type: 'num', value: parseFloat(literal) });
      index = end;
    } else {
      return null;
    }
  }
  return tokens;
}

function simplify(tokens) {
  const output = [];
  const stack = [];
  const precedence = { '+': 1, '-': 1, '*': 2, '/': 2, '%': 2 };
  const popUntil = (top) => {
    while (stack.length && stack[stack.length - 1].type === 'op' && stack[stack.length - 1].value !== '(' &&
        (top === '(' || precedence[top] <= precedence[stack[stack.length - 1].value])) {
      output.push(stack.pop());
    }
  };
  for (const token of tokens) {
    if (token.type === 'num') {
      output.push(token);
    } else if (token.value === '(') {
      stack.push(token);
    } else if (token.value === ')') {
      popUntil('(');
      if (!stack.length) return null;
      stack.pop();
    } else {
      popUntil(token.value);
      stack.push(token);
    }
  }
  while (stack.length) {
    if (stack[stack.length - 1].value === '(') return null;
    output.push(stack.pop());
  }
  return output;
}

function evaluate(tokens) {
  const stack = [];
  const apply = (operator) => {
    const right = stack.pop();
    const left = stack.pop();
    if (left === undefined || right === undefined) return null;
    switch (operator) {
      case '+': return left + right;
      case '-': return left - right;
      case '*': return left * right;
      case '/': return right === 0 ? null : left / right;
      case '%': return right === 0 ? null : left % right;
      default: return null;
    }
  };
  for (const token of tokens) {
    if (token.type === 'num') stack.push(token.value);
    else {
      const result = apply(token.value);
      if (result === null) return null;
      stack.push(result);
    }
  }
  return stack.length === 1 ? stack[0] : null;
}

function calculate(expression) {
  const tokens = tokenise(expression);
  if (!tokens) return null;
  const rpn = simplify(tokens);
  if (!rpn) return null;
  const result = evaluate(rpn);
  return result === null ? null : Math.round(result * 1e9) / 1e9;
}

/* ---------------------------------------------------------------------------
 * Exam page — Pearson VUE style chrome for CAT mocks + finals.
 * ------------------------------------------------------------------------- */
export default function Exam({ attempt, question: initialQuestion, answeredCount: initialAnsweredCount = 0 }) {
  const [question, setQuestion] = useState(initialQuestion);
  const [selected, setSelected] = useState('');
  const [processing, setProcessing] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [instructionsOpen, setInstructionsOpen] = useState(true);
  const [answeredCount, setAnsweredCount] = useState(initialAnsweredCount);
  const [flagged, setFlagged] = useState(() => new Set());
  const [strikes, setStrikes] = useState(() => new Set());
  const timeLimitSeconds = (attempt.exam?.time_minutes ?? attempt.session?.time_limit ?? 0) * 60;
  const isTimed = timeLimitSeconds > 0;
  const [secondsLeft, setSecondsLeft] = useState(timeLimitSeconds);
  const [paletteTab, setPaletteTab] = useState('palette');
  const [calculatorOpen, setCalculatorOpen] = useState(false);
  const [calcExpression, setCalcExpression] = useState('');
  const [reviewOpen, setReviewOpen] = useState(false);
  const [breakOpen, setBreakOpen] = useState(false);
  const totalQuestions = attempt.exam?.question_count || 25;
  const isFinal = Boolean(attempt.exam?.is_final);
  const isPractice = attempt.mode === 'practice';

  const breakThresholds = useMemo(() => {
    if (isPractice) return [];
    if (totalQuestions >= 75) return [Math.floor(totalQuestions / 2), totalQuestions - 5];
    return [totalQuestions - 5].filter((count) => count >= Math.min(3, totalQuestions) && count < totalQuestions);
  }, [totalQuestions, isPractice]);

  useEffect(() => {
    if (!isTimed || !question || instructionsOpen || breakOpen) return undefined;
    const timer = window.setInterval(() => setSecondsLeft((value) => Math.max(value - 1, 0)), 1000);
    return () => window.clearInterval(timer);
  }, [isTimed, question, instructionsOpen, breakOpen]);

  useEffect(() => {
    if (isTimed && secondsLeft === 0 && question && !processing) finishAttempt();
  }, [isTimed, secondsLeft, question, processing]);

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

  const submitAnswer = async () => {
    if (!question || processing || !selected) return;
    setProcessing(true);
    setFeedback(null);

    try {
      const response = await axios.post(`/passimark/attempt/${attempt.id}/answer`, {
        question_id: question.id,
        selected,
        time_spent: isTimed ? Math.max(0, timeLimitSeconds - secondsLeft) : 0,
      });

      if (response.data.next) {
        const nextCount = response.data.answeredCount ?? answeredCount + 1;
        setQuestion(response.data.next);
        setSelected('');
        setStrikes(new Set());
        if (isFinal) {
          setFeedback({ correct: response.data.correct, final: true });
        } else {
          setFeedback({ correct: response.data.correct });
        }
        setAnsweredCount(nextCount);
        if (breakThresholds.includes(nextCount)) {
          setBreakOpen(true);
        }
      } else if (response.data.score !== undefined) {
        setQuestion(null);
        router.visit(`/passimark/attempt/${attempt.id}/result`);
      }
    } catch (error) {
      setFeedback({ error: error.response?.data?.message || 'Unable to submit this answer.' });
    } finally {
      setProcessing(false);
    }
  };

  const minutes = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
  const seconds = String(secondsLeft % 60).padStart(2, '0');
  const options = question?.options || [];
  const progressPercent = Math.min(100, Math.round((answeredCount / totalQuestions) * 100));
  const currentIndex = Math.min(answeredCount + 1, totalQuestions);

  const toggleFlag = () => {
    if (!question) return;
    setFlagged((current) => {
      const next = new Set(current);
      if (next.has(currentIndex)) next.delete(currentIndex);
      else next.add(currentIndex);
      return next;
    });
  };

  const toggleStrike = (optionKey) => {
    setStrikes((current) => {
      const next = new Set(current);
      if (next.has(optionKey)) next.delete(optionKey);
      else next.add(optionKey);
      return next;
    });
  };

  const appendToCalc = (value) => setCalcExpression((expression) => `${expression}${value}`);
  const calcResult = calculate(calcExpression);

  const paletteItems = Array.from({ length: totalQuestions }, (_, index) => index + 1).map((position) => {
    if (flagged.has(position)) return { position, status: 'flagged' };
    if (position > currentIndex) return { position, status: 'unanswered' };
    if (position === currentIndex) return { position, status: 'current' };
    return { position, status: 'answered' };
  });

  const exitAssessment = () => {
    if (window.confirm('Exit now? Your progress is saved and you can resume this assessment later.')) router.visit('/');
  };

  const paletteStyle = {
    answered: 'bg-emerald-500/70 text-slate-950',
    flagged: 'bg-amber-400/80 text-slate-950',
    current: 'bg-white text-slate-900 ring-2 ring-emerald-400',
    unanswered: 'bg-slate-700 text-slate-300',
  };

  return (
    <>
      <Head title={`${attempt.session?.title || 'Assessment'} - Exam`} />
      <main className="min-h-screen bg-slate-950 text-white">
        <header className="border-b border-slate-800 bg-slate-900 px-6 py-4">
          <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
            <div>
              <p className="text-xs font-medium uppercase tracking-[0.2em] text-emerald-400">Adaptive assessment</p>
              <h1 className="mt-1 text-lg font-semibold">{attempt.session?.title}</h1>
            </div>
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setCalculatorOpen((open) => !open)}
                className={`inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition ${
                  calculatorOpen ? 'border-emerald-500/60 bg-emerald-500/15 text-emerald-200' : 'border-slate-700 text-slate-300 hover:bg-slate-800'
                }`}
                aria-label="Toggle calculator"
              >
                <Calculator className="h-4 w-4" /> Calc
              </button>
              <div className={`flex items-center gap-2 font-mono text-lg ${isTimed && secondsLeft < 300 ? 'text-orange-300' : 'text-slate-200'}`}>
                <Clock3 className="h-5 w-5" />
                {isTimed ? `${minutes}:${seconds}` : 'Untimed'}
              </div>
              <button type="button" onClick={exitAssessment} className="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                <LogOut className="h-4 w-4" /> Save &amp; exit
              </button>
            </div>
          </div>
        </header>

        {!instructionsOpen && (
          <div className="mx-auto max-w-6xl px-6 pt-6">
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span>Question {currentIndex} of {totalQuestions}</span>
              <span>{progressPercent}% complete</span>
            </div>
            <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-800">
              <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${progressPercent}%` }} />
            </div>
          </div>
        )}

        <section className="mx-auto max-w-6xl px-6 py-10">
          {instructionsOpen ? (
            <div className="mx-auto max-w-2xl rounded-2xl border border-slate-700 bg-slate-900 p-8">
              <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Before you begin</p>
              <h2 className="mt-3 text-3xl font-bold">Assessment instructions</h2>
              <ul className="mt-6 list-disc space-y-3 pl-5 text-sm leading-6 text-slate-300">
                <li>Choose one answer for each question and submit it to continue.</li>
                {!isFinal && <li>Use strike-through to rule out options as you reason.</li>}
                <li>Use the calculator for calculations during the assessment (you may toggle it anytime).</li>
                {!isPractice && <li>{attempt.mode === 'cat' ? 'The adaptive engine targets the next question to your ability.' : 'Answer as many as you can before the timer ends.'}</li>}
                <li>Your timer begins when you start the assessment. Natural breaks pause it.</li>
                <li>Your result is recorded automatically when the question set is complete.</li>
              </ul>
              <button type="button" onClick={() => setInstructionsOpen(false)} className="mt-8 rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950">Start assessment</button>
            </div>
          ) : breakOpen ? (
            <div className="mx-auto max-w-xl rounded-2xl border border-slate-700 bg-slate-900 p-8 text-center">
              <Coffee className="mx-auto h-10 w-10 text-emerald-400" />
              <p className="mt-4 text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Natural break</p>
              <h2 className="mt-2 text-2xl font-bold">Take a breath</h2>
              <p className="mt-4 text-sm leading-6 text-slate-300">
                You have completed {answeredCount} of {totalQuestions} questions. The timer is paused. When you are ready, continue.
              </p>
              <button type="button" onClick={() => setBreakOpen(false)} className="mt-8 rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-950">Continue assessment</button>
            </div>
          ) : question ? (
            <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
              <article className="rounded-2xl border border-slate-700 bg-slate-900 p-6 md:p-8">
                <div className="flex items-center justify-between text-sm text-slate-400">
                  <span>Question {currentIndex} of {totalQuestions}</span>
                  <button type="button" onClick={toggleFlag} className={`inline-flex items-center gap-2 rounded px-2 py-1 ${flagged.has(currentIndex) ? 'text-amber-300' : 'hover:text-slate-200'}`}>
                    <Flag className="h-4 w-4" /> {flagged.has(currentIndex) ? 'Flagged' : 'Flag for review'}
                  </button>
                </div>
                <h2 className="mt-8 text-xl font-semibold leading-8">{question.content}</h2>
                <div className="mt-8 space-y-3">
                  {options.map((option) => (
                    <div key={option.key} className="flex items-center gap-3">
                      <label className={`flex flex-1 cursor-pointer items-start gap-3 rounded-xl border p-4 transition ${selected === option.key ? 'border-emerald-400 bg-emerald-500/10' : 'border-slate-700 bg-slate-800 hover:border-slate-500'}`}>
                        <input type="radio" name="answer" value={option.key} checked={selected === option.key} onChange={() => setSelected(option.key)} className="mt-1 accent-emerald-500" />
                        <span className={`text-sm leading-6 text-slate-200 ${strikes.has(option.key) ? 'text-slate-500 line-through' : ''}`}>{option.text || option.label || option.key}</span>
                      </label>
                      <button
                        type="button"
                        onClick={() => toggleStrike(option.key)}
                        className={`rounded p-2 text-xs transition ${strikes.has(option.key) ? 'text-emerald-300' : 'text-slate-500 hover:text-slate-200'}`}
                        title="Strike through this option (reasonable-elimination aid)"
                        aria-label={`Strike through option ${option.key}`}
                      >
                        <Strikethrough className="h-4 w-4" />
                      </button>
                    </div>
                  ))}
                </div>
                <p className="mt-3 text-right text-xs text-slate-600">Strikethrough is a visual aid only — evaluation uses your submitted selection.</p>
                {feedback?.error && <p className="mt-4 text-sm text-red-300">{feedback.error}</p>}
                {feedback?.correct !== undefined && (
                  <p className={`mt-4 inline-flex items-center gap-2 text-sm ${isFinal ? 'text-slate-400' : feedback.correct ? 'text-emerald-300' : 'text-orange-300'}`}>
                    <CheckCircle2 className="h-4 w-4" /> {isFinal ? 'Answer recorded.' : feedback.correct ? 'Correct answer recorded.' : 'Answer recorded. Keep going.'}
                  </p>
                )}
                <button type="button" onClick={submitAnswer} disabled={!selected || processing} className="mt-8 inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500">
                  {processing ? 'Submitting...' : 'Submit answer'} <Send className="h-4 w-4" />
                </button>
              </article>

              <aside className="h-fit rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setPaletteTab('palette')}
                    className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${paletteTab === 'palette' ? 'bg-emerald-500/15 text-emerald-300' : 'text-slate-400 hover:text-slate-200'}`}
                  >
                    Palette
                  </button>
                  <button
                    type="button"
                    onClick={() => setPaletteTab('details')}
                    className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${paletteTab === 'details' ? 'bg-emerald-500/15 text-emerald-300' : 'text-slate-400 hover:text-slate-200'}`}
                  >
                    Details
                  </button>
                </div>

                {paletteTab === 'palette' ? (
                  <>
                    <div className="mt-5 grid grid-cols-8 gap-1.5">
                      {paletteItems.map((item) => (
                        <span key={item.position} className={`flex h-7 items-center justify-center rounded text-[10px] font-semibold ${paletteStyle[item.status] || paletteStyle.unanswered}`}>
                          {item.position}
                        </span>
                      ))}
                    </div>
                    <div className="mt-5 space-y-1.5 border-t border-slate-700 pt-4 text-xs text-slate-400">
                      <p className="flex items-center gap-2"><span className="inline-block h-3 w-3 rounded bg-emerald-500/70" /> Answered ({answeredCount})</p>
                      <p className="flex items-center gap-2"><span className="inline-block h-3 w-3 rounded bg-amber-400/80" /> Flagged ({flagged.size})</p>
                      <p className="flex items-center gap-2"><span className="inline-block h-3 w-3 rounded bg-white" /> Current</p>
                    </div>
                    <button type="button" onClick={() => setReviewOpen(true)} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 hover:bg-slate-800">
                      <ClipboardCheck className="h-4 w-4" /> Review flagged ({flagged.size})
                    </button>
                  </>
                ) : (
                  <dl className="mt-5 space-y-4 text-sm">
                    <div className="flex justify-between gap-4"><dt className="text-slate-400">Mode</dt><dd className="capitalize text-slate-200">{attempt.mode}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-slate-400">Domain</dt><dd className="text-right text-slate-200">{question.domain || '—'}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-slate-400">Pass mark</dt><dd className="text-slate-200">{isFinal ? 'θ ≥ required' : `${attempt.session?.pass_score}%`}</dd></div>
                    {isFinal && <div className="flex justify-between gap-4"><dt className="text-slate-400">Difficulty</dt><dd className="text-right font-mono text-slate-200">{(question.b_difficulty ?? 0).toFixed(2)}</dd></div>}
                  </dl>
                )}
              </aside>
            </div>
          ) : (
            <div className="rounded-2xl border border-slate-700 bg-slate-900 p-8 text-center">
              <h2 className="text-2xl font-semibold">Assessment complete</h2>
              <p className="mt-2 text-slate-400">Your result is being recorded.</p>
              <button type="button" onClick={() => router.visit('/')} className="mt-6 rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950">Return to dashboard</button>
            </div>
          )}
        </section>
      </main>

      {reviewOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6" onClick={() => setReviewOpen(false)}>
          <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold">Flagged questions</h3>
              <button type="button" onClick={() => setReviewOpen(false)} className="rounded p-1 text-slate-400 hover:text-white" aria-label="Close flagged review">
                <X className="h-5 w-5" />
              </button>
            </div>
            <p className="mt-2 text-sm text-slate-400">These are your review marks. In adaptive mode you continue along your ability path — consider flagging to revisit after the exam.</p>
            {flagged.size > 0 ? (
              <div className="mt-4 flex flex-wrap gap-2">
                {[...flagged].sort((a, b) => a - b).map((position) => (
                  <span key={position} className="rounded bg-amber-400/20 px-3 py-1 text-sm font-semibold text-amber-300">Q{position}</span>
                ))}
              </div>
            ) : (
              <p className="mt-4 text-sm text-slate-500">No questions flagged yet.</p>
            )}
            <button type="button" onClick={() => setReviewOpen(false)} className="mt-6 w-full rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-600">Close</button>
          </div>
        </div>
      )}

      {calculatorOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6" onClick={() => setCalculatorOpen(false)}>
          <div className="w-full max-w-xs rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-center justify-between">
              <p className="text-sm font-semibold text-slate-300"><Calculator className="mr-1 inline h-4 w-4" /> On-screen calculator</p>
              <button type="button" onClick={() => setCalculatorOpen(false)} className="rounded p-1 text-slate-400 hover:text-white" aria-label="Close calculator">
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="mt-4 rounded-lg bg-slate-950 p-3 text-right font-mono text-sm text-white">
              {calcExpression || '0'}
              <div className="text-emerald-400">{calcResult === null ? '' : `= ${calcResult}`}</div>
            </div>
            <div className="mt-4 grid grid-cols-4 gap-2">
              {['7', '8', '9', '/', '4', '5', '6', '*', '1', '2', '3', '-', '0', '.', '%', '+'].map((key) => (
                <button key={key} type="button" onClick={() => appendToCalc(key)} className={`rounded-lg py-3 text-sm font-semibold transition ${'+-*/%'.includes(key) ? 'bg-slate-700 text-emerald-300 hover:bg-slate-600' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'}`}>
                  {key}
                </button>
              ))}
              <button type="button" onClick={() => setCalcExpression('')} className="rounded-lg bg-slate-800 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700">C</button>
              <button type="button" onClick={() => appendToCalc('(')} className="rounded-lg bg-slate-800 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700">(</button>
              <button type="button" onClick={() => appendToCalc(')')} className="rounded-lg bg-slate-800 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700">)</button>
              <button type="button" onClick={() => setCalcExpression((value) => (calcResult === null ? value : String(calcResult)))} className="rounded-lg bg-emerald-500 py-3 text-sm font-bold text-slate-950 hover:bg-emerald-400">=</button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}