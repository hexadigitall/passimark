import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  CheckCircle2,
  Circle,
  Clock3,
  LockKeyhole,
  ShieldCheck,
  TrendingUp,
  Target,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import Breadcrumbs from '../../Components/Breadcrumbs';

const statusPresentation = {
  locked: {
    label: 'Locked',
    badge: 'bg-slate-700 text-slate-300',
    icon: LockKeyhole,
    cta: 'Locked',
    disabled: true,
  },
  open: {
    label: 'Ready',
    badge: 'bg-emerald-500/15 text-emerald-300',
    icon: Circle,
    cta: 'Start Session',
    disabled: false,
  },
  in_progress: {
    label: 'In progress',
    badge: 'bg-amber-500/15 text-amber-300',
    icon: Clock3,
    cta: 'Resume',
    disabled: false,
  },
  completed: {
    label: 'Completed',
    badge: 'bg-sky-500/15 text-sky-300',
    icon: CheckCircle2,
  },
  pending_approval: {
    label: 'Awaiting approval',
    badge: 'bg-orange-500/15 text-orange-300',
    icon: Clock3,
  },
  approved: {
    label: 'Approved',
    badge: 'bg-emerald-500/15 text-emerald-300',
    icon: ShieldCheck,
  },
};

export default function Track({ track, category = {}, siblings = [] }) {
  const [submittingSessionId, setSubmittingSessionId] = useState(null);

  const doneStatuses = ['completed', 'pending_approval', 'approved'];
  const sessions = track.sessions || [];
  const completed = sessions.filter((session) => doneStatuses.includes(session.progress?.status));
  const scored = sessions.filter((session) => session.progress?.score !== null && session.progress?.score !== undefined);
  const averageScore = scored.length
    ? Math.round(scored.reduce((total, item) => total + Number(item.progress.score || 0), 0) / scored.length)
    : null;
  const completionPercent = sessions.length ? Math.round((completed.length / sessions.length) * 100) : 0;
  const lastTheta = track.theta_history?.length ? track.theta_history[track.theta_history.length - 1] : null;
  const approvalGated = track.advancement !== 'auto';

  const crumbs = [
    { label: 'Dashboard', href: '/' },
    ...(category.region ? [{ label: category.region }] : []),
    { label: category.title || track.cert_key, href: category.url },
    { label: track.variant_label || track.title },
  ];

  const handleSessionAction = (sessionId, action) => {
    if (action === 'review') {
      router.visit(`/passimark/session/${sessionId}/review`);
      return;
    }

    const isApprovalRequest = action === 'request-approval';
    const endpoint = isApprovalRequest
      ? `/passimark/session/${sessionId}/request-approval`
      : `/passimark/session/${sessionId}/start`;

    setSubmittingSessionId(sessionId);

    router.post(
      endpoint,
      isApprovalRequest ? {} : { mode: 'cat' },
      { preserveScroll: true, onFinish: () => setSubmittingSessionId(null) },
    );
  };

  return (
    <DashboardLayout>
      <Head title={track.title || 'Track'} />
      <section className="mx-auto max-w-5xl">
        <Breadcrumbs items={crumbs} />

        <div className="flex flex-col gap-5 border-b border-slate-800 pb-6 md:flex-row md:items-start md:justify-between">
          <div>
            <p className="font-mono text-xs uppercase tracking-widest text-emerald-400">
              {track.cert_key}
              {track.variant_label ? ` · ${track.variant_label}` : ''}
            </p>
            <h2 className="mt-1 text-3xl font-bold tracking-normal text-white">{track.title}</h2>
            {track.description && <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">{track.description}</p>}
            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
              {track.region && <span className="rounded-full bg-slate-800 px-2.5 py-0.5 text-slate-400">{track.region}</span>}
              <span className={`rounded-full px-2.5 py-0.5 ${approvalGated ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300'}`}>
                {approvalGated ? 'Instructor approved' : 'Self-paced'}
              </span>
              <span className="rounded-full bg-slate-800 px-2.5 py-0.5 text-slate-400">
                {completed.length}/{sessions.length} sessions
              </span>
              {averageScore !== null && (
                <span className="rounded-full bg-slate-800 px-2.5 py-0.5 text-slate-400">avg {averageScore}%</span>
              )}
            </div>
          </div>
          <div className="flex items-center gap-5">
            <ProgressRing done={completed.length} total={sessions.length} theta={lastTheta} />
          </div>
        </div>

        {siblings.length > 0 && (
          <div className="mt-5 flex flex-wrap items-center gap-2">
            <span className="text-xs uppercase tracking-wider text-slate-500">Other bundles</span>
            {siblings.map((sibling) => (
              <Link
                key={sibling.slug}
                href={sibling.url}
                className="rounded-full border border-slate-700 px-3 py-1 text-xs text-slate-300 transition hover:border-emerald-500/50 hover:text-emerald-300"
              >
                {sibling.variant_label || sibling.title}
              </Link>
            ))}
          </div>
        )}

        <div className="mt-6 grid gap-4 lg:grid-cols-2">
          <div className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-slate-500">
              <TrendingUp className="h-3.5 w-3.5" /> θ trend
              <span className="ml-auto normal-case text-slate-600">Last {track.theta_history?.length ?? 0} completed</span>
            </p>
            <ThetaSparkline points={track.theta_history} />
          </div>

          <div className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Domain mastery</p>
            {track.domains?.length ? (
              <div className="mt-3 space-y-2">
                {track.domains.map((domain) => {
                  const tone = domain.accuracy >= 0.8 ? 'bg-emerald-500' : domain.accuracy >= 0.5 ? 'bg-amber-500' : 'bg-orange-600/80';
                  return (
                    <div key={domain.name} className="grid grid-cols-[1fr_auto] items-center gap-3 text-xs">
                      <div className="flex items-center gap-2">
                        <span className="truncate text-slate-300">{domain.name}</span>
                        <span className="shrink-0 text-slate-500">{domain.correct}/{domain.total}</span>
                      </div>
                      <div className="w-24 overflow-hidden rounded-full bg-slate-800">
                        <div className={`h-1.5 rounded-full ${tone}`} style={{ width: `${Math.round(domain.accuracy * 100)}%` }} />
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="mt-3 text-xs text-slate-600">No domain data yet — answer items to build your heatmap.</p>
            )}
          </div>
        </div>

        <div className="mt-6">
          <div className="flex items-baseline justify-between gap-4">
            <h3 className="text-lg font-semibold text-white">Session ladder</h3>
            <span className="text-sm text-slate-500">{completionPercent}% complete</span>
          </div>

          {sessions.length === 0 ? (
            <div className="mt-4 rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center">
              <Target className="mx-auto h-10 w-10 text-slate-500" />
              <h3 className="mt-4 text-xl font-semibold text-white">No sessions in this bundle</h3>
              <p className="mt-2 text-sm text-slate-400">Content has not been published for this bundle yet.</p>
            </div>
          ) : (
            <div className="mt-4 divide-y divide-slate-800 overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
              {sessions.map((session) => {
                const status = session.progress?.status || 'locked';
                const state = statusPresentation[status];
                const StatusIcon = state.icon;
                const done = doneStatuses.includes(status);
                const actions = done
                  ? [
                      { key: 'reattempt', label: 'Reattempt', action: 'start', tone: 'emerald' },
                      { key: 'review', label: 'Review answers', action: 'review', tone: 'slate' },
                      ...(status === 'completed' && approvalGated
                        ? [{ key: 'approval', label: 'Request approval', action: 'request-approval', tone: 'amber' }]
                        : []),
                    ]
                  : [{ key: 'main', label: state.cta, action: 'start', tone: 'emerald', disabled: state.disabled }];

                return (
                  <div key={session.id} className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                    <span className={`inline-flex shrink-0 items-center gap-1 rounded px-2 py-0.5 text-[11px] font-medium ${state.badge}`}>
                      <StatusIcon className="h-3 w-3" />
                      {state.label}
                    </span>
                    <p className="min-w-0 flex-1 truncate text-sm text-slate-300">
                      <span className="font-mono text-xs text-slate-500">{String(session.number).padStart(2, '0')}&nbsp;</span>
                      {session.title}
                    </p>
                    {session.progress?.score !== null && session.progress?.score !== undefined && (
                      <span className="shrink-0 font-mono text-xs text-slate-400">{session.progress.score}%</span>
                    )}
                    <div className="flex shrink-0 items-center gap-1.5">
                      {actions.map((action) => (
                        <button
                          key={action.key}
                          type="button"
                          className={`rounded px-2.5 py-1 text-xs font-medium transition ${
                            action.disabled
                              ? 'cursor-not-allowed bg-slate-800 text-slate-500'
                              : action.tone === 'amber'
                                ? 'bg-amber-500/15 text-amber-200 hover:bg-amber-500/25'
                                : action.tone === 'slate'
                                  ? 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                                  : 'bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25'
                          }`}
                          disabled={action.disabled || submittingSessionId === session.id}
                          onClick={() => handleSessionAction(session.id, action.action)}
                        >
                          {submittingSessionId === session.id ? 'Working...' : action.label}
                        </button>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </section>
    </DashboardLayout>
  );
}

function ProgressRing({ done, total, theta }) {
  const radius = 34;
  const circumference = 2 * Math.PI * radius;
  const ratio = total ? done / total : 0;
  const complete = ratio >= 1;
  const dashoffset = circumference * (1 - ratio);

  return (
    <div className="relative h-20 w-20 shrink-0">
      <svg viewBox="0 0 80 80" className="h-20 w-20 -rotate-90">
        <circle cx="40" cy="40" r={radius} fill="none" strokeWidth="7" className="stroke-slate-800" />
        <circle
          cx="40"
          cy="40"
          r={radius}
          fill="none"
          strokeWidth="7"
          strokeLinecap="round"
          className={complete ? 'stroke-emerald-400' : 'stroke-slate-500'}
          strokeDasharray={complete ? `${circumference} ${circumference}` : '4 6'}
          strokeDashoffset={complete ? 0 : dashoffset}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className={`font-mono text-sm font-semibold ${complete ? 'text-emerald-300' : 'text-slate-300'}`}>
          {Math.round(ratio * 100)}%
        </span>
        {theta !== null && theta !== undefined && <span className="font-mono text-[10px] text-slate-500">θ {Number(theta).toFixed(2)}</span>}
      </div>
    </div>
  );
}

function ThetaSparkline({ points }) {
  if (!points || points.length < 2) {
    return (
      <p className="pt-3 text-xs text-slate-600">
        {points?.length === 1 ? 'One attempt recorded — take more to see your trend.' : 'No trend data yet — complete an IRT session.'}
      </p>
    );
  }

  const width = 200;
  const height = 40;
  const min = Math.min(-3, ...points, 3);
  const max = Math.max(3, ...points, -3);
  const span = Math.max(max - min, 0.001);
  const x = (index) => (index / (points.length - 1)) * (width - 4) + 2;
  const y = (value) => ((max - value) / span) * (height - 8) + 4;
  const coords = points.map((value, index) => `${x(index).toFixed(1)},${y(value).toFixed(1)}`).join(' ');

  return (
    <div className="mt-2">
      <svg viewBox={`0 0 ${width} ${height}`} className="w-full">
        <line x1="2" y1={y(0)} x2={width - 2} y2={y(0)} className="stroke-slate-800" strokeWidth="1" strokeDasharray="3 3" />
        <polyline points={coords} fill="none" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" className="stroke-emerald-400" />
        {points.map((value, index) => (
          <circle key={index} cx={x(index)} cy={y(value)} r="2.5" className={value >= 0 ? 'fill-emerald-400' : 'fill-amber-400'} />
        ))}
      </svg>
      <p className="mt-1 flex justify-between font-mono text-[10px] text-slate-600">
        <span>early</span>
        <span>now</span>
      </p>
    </div>
  );
}
