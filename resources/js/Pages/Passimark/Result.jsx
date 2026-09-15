import { Head, router } from '@inertiajs/react';
import { CheckCircle2, XCircle, LockKeyhole, UnlockKeyhole, FileQuestion } from 'lucide-react';

export default function Result({ attempt, answers = [], history = [], review_unlocked = false, progress_status = 'locked' }) {
	const score = Number(attempt.score || 0);
	const correctCount = answers.filter((answer) => answer.is_correct).length;
	const lockedCount = answers.filter((answer) => !answer.exposed).length;
	const canRequestApproval = progress_status === 'completed' && lockedCount > 0;

	const requestApproval = () => {
		router.post(`/passimark/session/${attempt.session_id}/request-approval`, {}, {
			preserveScroll: true,
			onFinish: () => router.reload(),
		});
	};

	return (
		<>
			<Head title="Assessment result" />
			<main className="min-h-screen bg-slate-950 px-6 py-10 text-white">
				<section className="mx-auto max-w-5xl space-y-6">
					<div className="rounded-2xl border border-slate-700 bg-slate-900 p-8">
						<p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Assessment result</p>
						<div className="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
							<div><h1 className="text-3xl font-bold">{attempt.is_passed ? 'Session passed' : 'Keep building mastery'}</h1><p className="mt-2 text-slate-400">{attempt.session?.title}</p></div>
							<div className="text-5xl font-bold text-emerald-300">{score}%</div>
						</div>
						<div className="mt-8 grid gap-3 sm:grid-cols-3">
							<Metric label="Answers" value={answers.length} />
							<Metric label="Correct" value={correctCount} />
							<Metric label="Theta estimate" value={Number(attempt.theta || 0).toFixed(2)} />
						</div>
					</div>

					{!review_unlocked && (
						<div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5">
							<div className="flex items-start gap-3">
								<LockKeyhole className="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
								<div>
									<p className="text-sm font-semibold text-amber-200">Answer review partially locked</p>
									<p className="mt-1 text-sm text-amber-100/70">
										{lockedCount} item{lockedCount === 1 ? '' : 's'}. Your correct answers are explained now; the correct answer and explanation for the rest unlock when
										you get every item right, or an instructor approves this session.
									</p>
								</div>
							</div>
							{canRequestApproval && (
								<button
									type="button"
									onClick={requestApproval}
									className="shrink-0 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-300"
								>
									Request approval to unlock full review
								</button>
							)}
						</div>
					)}

					<div className="grid gap-6 lg:grid-cols-[1fr_320px]">
						<div className="rounded-2xl border border-slate-700 bg-slate-900 p-6">
							<h2 className="text-lg font-semibold">Answer review</h2>
							<div className="mt-5 space-y-3">
								{answers.length === 0 && <p className="text-sm text-slate-500">No items recorded for this attempt.</p>}
								{answers.map((answer, index) => (
									<div key={answer.id} className={answer.exposed ? 'rounded-xl border border-slate-700 bg-slate-950 p-4' : 'rounded-xl border border-dashed border-slate-700 p-4'}>
										<div className="flex items-start gap-3">
											<div className={answer.is_correct ? 'text-emerald-300' : 'text-orange-300'}>
												{answer.is_correct ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
											</div>
											<div className="min-w-0 flex-1">
												<p className="text-sm text-slate-300"><span className="font-mono text-xs text-slate-500">{index + 1}.</span> {answer.question?.content}</p>
												<p className="mt-2 text-sm text-slate-400">You selected <span className="font-mono text-slate-200">{answer.selected_option}</span> — <span className={answer.is_correct ? 'text-emerald-300' : 'text-orange-300'}>{answer.is_correct ? 'Correct' : 'Incorrect'}</span></p>

												{answer.exposed ? (
													<div className="mt-3 space-y-2">
														<p className="text-sm text-slate-300">
															<span className="text-emerald-300">Correct answer:</span> <span className="font-mono text-slate-100">{answer.question?.correct_key}</span>
															<span className="ml-2 text-slate-400">{answer.question?.options?.find((option) => option.key === answer.question?.correct_key)?.text}</span>
														</p>
														{answer.question?.explanation && <p className="rounded-lg bg-slate-800 p-3 text-sm leading-relaxed text-slate-300">{answer.question.explanation}</p>}
													</div>
												) : (
													<p className="mt-3 flex items-center gap-1.5 text-xs text-slate-500"><LockKeyhole className="h-3.5 w-3.5" /> Correct answer &amp; explanation hidden — approves or perfect attempt to unlock.</p>
												)}
											</div>
										</div>
									</div>
								))}
							</div>
						</div>

						<aside className="h-fit rounded-2xl border border-slate-700 bg-slate-900 p-6">
							<h2 className="text-lg font-semibold">Attempt history</h2>
							<div className="mt-5 space-y-3">
								{history.map((item) => (
									<div key={item.id} className="flex justify-between border-b border-slate-800 pb-3 text-sm">
										<span className="capitalize text-slate-400">{item.mode}</span>
										<span className="font-medium text-white">{item.score}%</span>
									</div>
								))}
							</div>
							<div className="mt-6 flex flex-col gap-2">
								<button type="button" onClick={() => router.post(`/passimark/session/${attempt.session_id}/start`, { mode: 'cat' })} className="w-full rounded-lg bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950">
									Reattempt session
								</button>
								<button type="button" onClick={() => router.visit('/')} className="w-full rounded-lg bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700">
									Return to dashboard
								</button>
							</div>
							{review_unlocked && <p className="mt-4 flex items-center gap-1.5 text-xs text-emerald-300"><UnlockKeyhole className="h-3.5 w-3.5" /> Full review unlocked.</p>}
							{progress_status === 'pending_approval' && <p className="mt-4 flex items-center gap-1.5 text-xs text-orange-300"><FileQuestion className="h-3.5 w-3.5" /> Approval pending with instructor.</p>}
						</aside>
					</div>
				</section>
			</main>
		</>
	);
}

function Metric({ label, value }) { return <div className="rounded-xl border border-slate-700 bg-slate-950 p-4"><p className="text-xs text-slate-500">{label}</p><p className="mt-2 text-xl font-semibold">{value}</p></div>; }