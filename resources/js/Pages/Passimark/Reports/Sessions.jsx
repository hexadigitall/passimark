import { Head } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import ReportHeader from './ReportHeader';
import StatStrip from './StatStrip';

const fmtDate = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
};

const fmtNum = (value) => {
  if (value === null || value === undefined) return '—';
  const n = Number(value);
  return Number.isNaN(n) ? '—' : n.toFixed(1);
};

const Flag = ({ text, tone }) => (
  <span className={`mr-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${tone === 'amber' ? 'bg-amber-500/10 text-amber-300' : 'bg-slate-700/40 text-slate-300'}`}>
    {text}
  </span>
);

export default function Sessions({ summary = {}, rows = [] }) {
  const stats = [
    { label: 'Sessions', value: summary.sessions ?? 0, sub: 'in the catalog' },
    { label: 'Contentless', value: summary.contentless ?? 0, sub: 'exams but no questions' },
    { label: 'Never attempted', value: summary.unused ?? 0, sub: 'zero attempts recorded' },
    { label: 'Attempts', value: summary.attempts ?? 0, sub: `${summary.questions ?? 0} questions in total` },
    { label: 'Avg pass rate', value: `${summary.avg_pass_rate ?? 0}%`, sub: 'across finished attempts' },
  ];

  return (
    <DashboardLayout>
      <Head title="Sessions Report" />
      <section className="mx-auto max-w-7xl">
        <ReportHeader
          title="Sessions"
          description="Engagement per session: how often each session is attempted, how learners score, and content-health flags that tell you where to invest next."
        />
        <StatStrip stats={stats} />

        <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900">
          <table className="w-full min-w-[1000px] text-left text-sm">
            <thead className="border-b border-slate-700 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th className="px-4 py-3">#</th>
                <th className="px-4 py-3">Session</th>
                <th className="px-4 py-3">Track</th>
                <th className="px-4 py-3">Type</th>
                <th className="px-4 py-3">Questions</th>
                <th className="px-4 py-3">Attempts</th>
                <th className="px-4 py-3">Finished</th>
                <th className="px-4 py-3">Avg score</th>
                <th className="px-4 py-3">Pass rate</th>
                <th className="px-4 py-3">Pending</th>
                <th className="px-4 py-3">Health</th>
                <th className="px-4 py-3">Last activity</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={12} className="px-4 py-8 text-center text-slate-500">No sessions in the catalog yet.</td>
                </tr>
              )}
              {rows.map((session) => (
                <tr key={session.id} className="border-b border-slate-800 transition hover:bg-slate-800/40">
                  <td className="px-4 py-3 text-slate-400">{session.number}</td>
                  <td className="max-w-[320px] px-4 py-3">
                    <p className="truncate font-medium text-white">{session.title}</p>
                  </td>
                  <td className="max-w-[160px] truncate px-4 py-3 text-slate-300">{session.track ?? '—'}</td>
                  <td className="px-4 py-3 capitalize text-slate-300">{session.phase_type ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-300">{session.questions}</td>
                  <td className="px-4 py-3 text-slate-300">{session.attempts}</td>
                  <td className="px-4 py-3 text-slate-300">{session.finished}</td>
                  <td className="px-4 py-3 text-slate-300">{fmtNum(session.avg_score)}</td>
                  <td className="px-4 py-3 text-slate-300">{session.pass_rate}%</td>
                  <td className="px-4 py-3">
                    {session.pending > 0
                      ? <span className="inline-flex rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-300">{session.pending}</span>
                      : <span className="text-slate-600">0</span>}
                  </td>
                  <td className="px-4 py-3">
                    {session.contentless && <Flag text="contentless" tone="amber" />}
                    {session.unused && <Flag text="never used" />}
                    {!session.contentless && !session.unused && <span className="text-slate-600">ok</span>}
                  </td>
                  <td className="px-4 py-3 text-slate-400">{fmtDate(session.last_activity)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </DashboardLayout>
  );
}