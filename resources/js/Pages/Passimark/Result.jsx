import { Head, router } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

export default function Result({ attempt, answers = [], history = [] }) {
	const score = Number(attempt.score || 0);

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
							<Metric label="Correct" value={answers.filter((answer) => answer.is_correct).length} />
							<Metric label="Theta estimate" value={Number(attempt.theta || 0).toFixed(2)} />
						</div>
					</div>
					<div className="grid gap-6 lg:grid-cols-[1fr_320px]">
						<div className="rounded-2xl border border-slate-700 bg-slate-900 p-6">
							<h2 className="text-lg font-semibold">Answer review</h2>
							<div className="mt-5 space-y-3">{answers.map((answer, index) => <div key={answer.id} className="flex items-start gap-3 border-b border-slate-800 pb-3 text-sm"><div className={answer.is_correct ? 'text-emerald-300' : 'text-orange-300'}>{answer.is_correct ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}</div><p className="text-slate-300">{index + 1}. {answer.question?.content}</p></div>)}</div>
						</div>
						<aside className="h-fit rounded-2xl border border-slate-700 bg-slate-900 p-6"><h2 className="text-lg font-semibold">Attempt history</h2><div className="mt-5 space-y-3">{history.map((item) => <div key={item.id} className="flex justify-between border-b border-slate-800 pb-3 text-sm"><span className="capitalize text-slate-400">{item.mode}</span><span className="font-medium text-white">{item.score}%</span></div>)}</div><button type="button" onClick={() => router.visit('/')} className="mt-6 w-full rounded-lg bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950">Return to dashboard</button></aside>
					</div>
				</section>
			</main>
		</>
	);
}

function Metric({ label, value }) { return <div className="rounded-xl border border-slate-700 bg-slate-950 p-4"><p className="text-xs text-slate-500">{label}</p><p className="mt-2 text-xl font-semibold">{value}</p></div>; }