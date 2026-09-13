import { useEffect, useState } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, Flag, LogOut, Send } from 'lucide-react';

export default function Exam({ attempt, question: initialQuestion, answeredCount: initialAnsweredCount = 0 }) {
	const [question, setQuestion] = useState(initialQuestion);
	const [selected, setSelected] = useState('');
	const [processing, setProcessing] = useState(false);
	const [feedback, setFeedback] = useState(null);
	const [instructionsOpen, setInstructionsOpen] = useState(true);
	const [answeredCount, setAnsweredCount] = useState(initialAnsweredCount);
	const [flagged, setFlagged] = useState(() => new Set());
	const [secondsLeft, setSecondsLeft] = useState((attempt.session?.time_limit || 180) * 60);
	const totalQuestions = attempt.exam?.question_count || 25;

	useEffect(() => {
		if (!question || instructionsOpen) return undefined;
		const timer = window.setInterval(() => setSecondsLeft((value) => Math.max(value - 1, 0)), 1000);
		return () => window.clearInterval(timer);
	}, [question, instructionsOpen]);

	useEffect(() => {
		if (secondsLeft === 0 && question && !processing) finishAttempt();
	}, [secondsLeft, question, processing]);

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
				time_spent: Math.max(0, (attempt.session?.time_limit || 180) * 60 - secondsLeft),
			});

			if (response.data.next) {
				setQuestion(response.data.next);
				setSelected('');
				setFeedback({ correct: response.data.correct });
				setAnsweredCount(response.data.answeredCount ?? answeredCount + 1);
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
	const toggleFlag = () => {
		if (!question) return;
		setFlagged((current) => {
			const next = new Set(current);
			next.has(question.id) ? next.delete(question.id) : next.add(question.id);
			return next;
		});
	};
	const exitAssessment = () => {
		if (window.confirm('Exit now? Your progress is saved and you can resume this assessment later.')) router.visit('/');
	};

	return (
		<>
			<Head title={`${attempt.session?.title || 'Assessment'} - Exam`} />
			<main className="min-h-screen bg-slate-950 text-white">
				<header className="border-b border-slate-800 bg-slate-900 px-6 py-4">
					<div className="mx-auto flex max-w-5xl items-center justify-between gap-4">
						<div>
							<p className="text-xs font-medium uppercase tracking-[0.2em] text-emerald-400">Adaptive assessment</p>
							<h1 className="mt-1 text-lg font-semibold">{attempt.session?.title}</h1>
						</div>
						<div className={`flex items-center gap-2 font-mono text-lg ${secondsLeft < 300 ? 'text-orange-300' : 'text-slate-200'}`}>
							<Clock3 className="h-5 w-5" />
							{minutes}:{seconds}
						</div>
						<button type="button" onClick={exitAssessment} className="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
							<LogOut className="h-4 w-4" /> Save &amp; exit
						</button>
					</div>
				</header>

				{!instructionsOpen && (
					<div className="mx-auto max-w-5xl px-6 pt-6">
						<div className="flex items-center justify-between text-xs text-slate-400">
							<span>Question {Math.min(answeredCount + 1, totalQuestions)} of {totalQuestions}</span>
							<span>{progressPercent}% complete</span>
						</div>
						<div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-800">
							<div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${progressPercent}%` }} />
						</div>
					</div>
				)}

				<section className="mx-auto max-w-5xl px-6 py-10">
					{instructionsOpen ? (
						<div className="mx-auto max-w-2xl rounded-2xl border border-slate-700 bg-slate-900 p-8">
							<p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Before you begin</p>
							<h2 className="mt-3 text-3xl font-bold">Assessment instructions</h2>
							<ul className="mt-6 list-disc space-y-3 pl-5 text-sm leading-6 text-slate-300">
								<li>Choose one answer for each question and submit it to continue.</li>
								<li>Your timer begins when you start the assessment.</li>
								<li>Your result is recorded automatically when the question set is complete.</li>
							</ul>
							<button type="button" onClick={() => setInstructionsOpen(false)} className="mt-8 rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950">Start assessment</button>
						</div>
					) : question ? (
						<div className="grid gap-6 lg:grid-cols-[1fr_280px]">
							<article className="rounded-2xl border border-slate-700 bg-slate-900 p-6 md:p-8">
								<div className="flex items-center justify-between text-sm text-slate-400">
									<span>Question {Math.min(answeredCount + 1, totalQuestions)} of {totalQuestions}</span>
									<button type="button" onClick={toggleFlag} className={`inline-flex items-center gap-2 rounded px-2 py-1 ${flagged.has(question.id) ? 'text-amber-300' : 'hover:text-slate-200'}`}>
										<Flag className="h-4 w-4" /> {flagged.has(question.id) ? 'Flagged' : 'Flag for review'}
									</button>
								</div>
								<h2 className="mt-8 text-xl font-semibold leading-8">{question.content}</h2>
								<div className="mt-8 space-y-3">
									{options.map((option) => (
										<label key={option.key} className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition ${selected === option.key ? 'border-emerald-400 bg-emerald-500/10' : 'border-slate-700 bg-slate-800 hover:border-slate-500'}`}>
											<input type="radio" name="answer" value={option.key} checked={selected === option.key} onChange={() => setSelected(option.key)} className="mt-1 accent-emerald-500" />
											<span className="text-sm leading-6 text-slate-200">{option.text || option.label || option.key}</span>
										</label>
									))}
								</div>
								{feedback?.error && <p className="mt-4 text-sm text-red-300">{feedback.error}</p>}
								{feedback?.correct !== undefined && <p className={`mt-4 inline-flex items-center gap-2 text-sm ${feedback.correct ? 'text-emerald-300' : 'text-orange-300'}`}><CheckCircle2 className="h-4 w-4" /> {feedback.correct ? 'Correct answer recorded.' : 'Answer recorded. Keep going.'}</p>}
								<button type="button" onClick={submitAnswer} disabled={!selected || processing} className="mt-8 inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500">
									{processing ? 'Submitting...' : 'Submit answer'} <Send className="h-4 w-4" />
								</button>
							</article>
							<aside className="h-fit rounded-2xl border border-slate-700 bg-slate-900 p-5">
								<p className="text-sm font-semibold text-white">Assessment details</p>
								<dl className="mt-5 space-y-4 text-sm">
									<div className="flex justify-between gap-4"><dt className="text-slate-400">Mode</dt><dd className="capitalize text-slate-200">{attempt.mode}</dd></div>
									<div className="flex justify-between gap-4"><dt className="text-slate-400">Domain</dt><dd className="text-right text-slate-200">{question.domain}</dd></div>
									<div className="flex justify-between gap-4"><dt className="text-slate-400">Pass mark</dt><dd className="text-slate-200">{attempt.session?.pass_score}%</dd></div>
								</dl>
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
		</>
	);
}

