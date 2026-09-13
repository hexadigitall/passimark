import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
  ArrowRight,
  CheckCircle2,
  Circle,
  Clock3,
  LockKeyhole,
  ShieldCheck,
  Timer,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';

const statusPresentation = {
  locked: {
    label: 'Locked',
    card: 'border-slate-700 bg-slate-800/60',
    badge: 'bg-slate-700 text-slate-300',
    icon: LockKeyhole,
    action: 'Complete the previous session',
    cta: 'Locked',
    disabled: true,
  },
  open: {
    label: 'Ready',
    card: 'border-emerald-500/50 bg-emerald-500/5',
    badge: 'bg-emerald-500/15 text-emerald-300',
    icon: Circle,
    action: 'Ready to begin',
    cta: 'Start Session',
    disabled: false,
  },
  in_progress: {
    label: 'In progress',
    card: 'border-amber-500/50 bg-amber-500/5',
    badge: 'bg-amber-500/15 text-amber-300',
    icon: Clock3,
    action: 'Resume your exam',
    cta: 'Resume',
    disabled: false,
  },
  completed: {
    label: 'Completed',
    card: 'border-sky-500/40 bg-sky-500/5',
    badge: 'bg-sky-500/15 text-sky-300',
    icon: CheckCircle2,
    action: 'Request instructor approval',
    cta: 'Request approval',
    disabled: false,
  },
  pending_approval: {
    label: 'Awaiting approval',
    card: 'border-orange-500/50 bg-orange-500/5',
    badge: 'bg-orange-500/15 text-orange-300',
    icon: Clock3,
    action: 'Instructor review in progress',
    cta: 'Awaiting approval',
    disabled: true,
  },
  approved: {
    label: 'Approved',
    card: 'border-emerald-500/50 bg-emerald-500/5',
    badge: 'bg-emerald-500/15 text-emerald-300',
    icon: ShieldCheck,
    action: 'Unlocked for the next stage',
    cta: 'Continue',
    disabled: false,
  },
};

export default function Dashboard({ sessions = [], progress = {} }) {
  const [submittingSessionId, setSubmittingSessionId] = useState(null);
  const progressBySession = Array.isArray(progress)
    ? Object.fromEntries(progress.map((item) => [item.session_id, item]))
    : progress;
  const completed = Object.values(progressBySession).filter(
    (item) => ['completed', 'pending_approval', 'approved'].includes(item.status),
  );
  const averageScore = completed.length
    ? Math.round(completed.reduce((total, item) => total + Number(item.score || 0), 0) / completed.length)
    : null;
  const completionPercent = sessions.length ? Math.round((completed.length / sessions.length) * 100) : 0;
  const abilityTheta = Object.values(progressBySession).reduce(
    (highest, item) => Math.max(highest, Number(item.ability_theta || 0)),
    0,
  );
  const openSession = sessions.find((session) => {
    const item = progressBySession[session.id];
    return item?.status === 'open' || item?.status === 'in_progress' || item?.status === 'approved';
  });

  const handleSessionAction = (session, status) => {
    const isApprovalRequest = status === 'completed';
    const endpoint = isApprovalRequest
      ? `/passimark/session/${session.id}/request-approval`
      : `/passimark/session/${session.id}/start`;

    setSubmittingSessionId(session.id);

    router.post(
      endpoint,
      isApprovalRequest ? {} : { mode: 'cat' },
      {
        preserveScroll: true,
        onFinish: () => setSubmittingSessionId(null),
      },
    );
  };

  return (
    <DashboardLayout>
      <Head title="Dashboard" />
      <section className="mx-auto max-w-7xl">
        <div className="flex flex-col gap-3 border-b border-slate-800 pb-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-sm font-medium text-emerald-400">Learner roadmap</p>
            <h2 className="mt-1 text-3xl font-bold tracking-normal text-white">Your certification path</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
              Build mastery session by session. Completion remains subject to your instructor's approval.
            </p>
          </div>
          <div className="text-sm text-slate-400">
            {openSession ? `Current focus: Session ${openSession.number}` : 'No session is currently open'}
          </div>
        </div>

        <div id="progress" className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <Metric label="Sessions completed" value={`${completed.length} / ${sessions.length}`} />
          <Metric label="Average score" value={averageScore === null ? 'No scores yet' : `${averageScore}%`} />
          <Metric label="Completion" value={`${completionPercent}%`} />
          <Metric label="Ability estimate" value={abilityTheta.toFixed(2)} />
          <Metric label="Current phase" value={`Phase ${openSession?.phase || 1}`} />
        </div>

        <div className="mt-10 flex items-center justify-between">
          <div>
            <h3 className="text-lg font-semibold text-white">Sessions</h3>
            <p className="mt-1 text-sm text-slate-400">Your availability and progression state update here.</p>
          </div>
          <span className="text-sm text-slate-500">{sessions.length} total</span>
        </div>

        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {sessions.map((session) => {
            const item = progressBySession[session.id];
            const state = statusPresentation[item?.status || 'locked'];
            const StatusIcon = state.icon;

            return (
              <article key={session.id} className={`flex min-h-60 flex-col border p-5 ${state.card}`}>
                <div className="flex items-start justify-between gap-4">
                  <span className="font-mono text-xs text-slate-500">SESSION {String(session.number).padStart(2, '0')}</span>
                  <span className={`inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium ${state.badge}`}>
                    <StatusIcon className="h-3.5 w-3.5" />
                    {state.label}
                  </span>
                </div>
                <h4 className="mt-5 text-lg font-semibold leading-6 text-white">{session.title}</h4>
                <p className="mt-2 text-sm leading-5 text-slate-400">{session.domain || 'General assessment'}</p>
                <div className="mt-5 grid grid-cols-2 border-y border-slate-700/80 py-3 text-sm">
                  <span className="text-slate-400">{session.question_count} questions</span>
                  <span className="text-right text-slate-400">Pass {session.pass_score}%</span>
                  <span className="mt-2 inline-flex items-center gap-1 text-slate-500"><Timer className="h-3.5 w-3.5" /> {session.time_limit} min</span>
                  <span className="mt-2 text-right font-medium text-slate-300">{item?.score !== null && item?.score !== undefined ? `${item.score}% score` : 'Not attempted'}</span>
                </div>
                <div className="mt-auto pt-4">
                  <button
                    type="button"
                    className={`inline-flex w-full items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition ${
                      state.disabled
                        ? 'cursor-not-allowed border border-slate-700 bg-slate-800 text-slate-500'
                        : 'border border-emerald-500/60 bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25'
                    }`}
                    disabled={state.disabled || submittingSessionId === session.id}
                    onClick={() => handleSessionAction(session, item?.status || 'locked')}
                  >
                    {submittingSessionId === session.id ? 'Working...' : state.cta}
                    {!state.disabled && !submittingSessionId && <ArrowRight className="h-4 w-4" />}
                  </button>
                  <p className="mt-2 text-sm font-medium text-slate-300">{state.action}</p>
                </div>
              </article>
            );
          })}
        </div>
      </section>
    </DashboardLayout>
  );
}

function Metric({ label, value }) {
  return (
    <div className="border border-slate-700 bg-slate-800 p-5">
      <p className="text-sm text-slate-400">{label}</p>
      <p className="mt-3 text-2xl font-semibold text-white">{value}</p>
    </div>
  );
}
