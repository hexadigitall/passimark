import { Head } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import ReportHeader from './ReportHeader';
import StatStrip from './StatStrip';

const fmtDate = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const NullCell = ({ value, title = '—' }) => <span className="text-slate-500">{value ?? title}</span>;

export default function Learners({ summary = {}, rows = [] }) {
  const stats = [
    { label: 'Learners', value: summary.total ?? 0, sub: 'all registered' },
    { label: 'Active', value: summary.active ?? 0, sub: '≥1 attempt' },
    { label: 'Weekly active', value: summary.weekly_active ?? 0, sub: 'last 7 days' },
    { label: 'Awaiting approval', value: summary.with_pending ?? 0, sub: '≥1 pending decision' },
  ];

  return (
    <DashboardLayout>
      <Head title="Learner Report" />
      <section className="mx-auto max-w-7xl">
        <ReportHeader
          title="Learners"
          description="Every learner's engagement and progression at a glance. Use this to spot who is active, where they are in their certification path, and who is queued for approval."
        />
        <StatStrip stats={stats} />

        <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900">
          <table className="w-full min-w-[900px] text-left text-sm">
            <thead className="border-b border-slate-700 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th className="px-4 py-3">Learner</th>
                <th className="px-4 py-3">Attempts</th>
                <th className="px-4 py-3">Finished</th>
                <th className="px-4 py-3">Pass rate</th>
                <th className="px-4 py-3">Avg score</th>
                <th className="px-4 py-3">Pending</th>
                <th className="px-4 py-3">Completed</th>
                <th className="px-4 py-3">Current session</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Last activity</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={10} className="px-4 py-8 text-center text-slate-500">No learners registered yet.</td>
                </tr>
              )}
              {rows.map((learner) => (
                <tr key={learner.id} className="border-b border-slate-800 transition hover:bg-slate-800/40">
                  <td className="px-4 py-3">
                    <p className="font-medium text-white">{learner.name}</p>
                    <p className="text-xs text-slate-500">{learner.email}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-300">{learner.attempts}</td>
                  <td className="px-4 py-3 text-slate-300">{learner.finished}</td>
                  <td className="px-4 py-3 text-slate-300">{learner.pass_rate}%</td>
                  <td className="px-4 py-3 text-slate-300"><NullCell value={learner.avg_score} /></td>
                  <td className="px-4 py-3">
                    {learner.pending > 0
                      ? <span className="inline-flex rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-300">{learner.pending}</span>
                      : <span className="text-slate-600">0</span>}
                  </td>
                  <td className="px-4 py-3 text-slate-300">{learner.completed}</td>
                  <td className="max-w-[220px] truncate px-4 py-3 text-slate-300"><NullCell value={learner.current} /></td>
                  <td className="px-4 py-3 capitalize text-slate-300"><NullCell value={learner.current_status} /></td>
                  <td className="px-4 py-3 text-slate-400">{fmtDate(learner.last_activity)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {rows.length === 100 && <p className="border-t border-slate-800 px-4 py-3 text-xs text-slate-500">Showing the 100 most recently active learners.</p>}
        </div>
      </section>
    </DashboardLayout>
  );
}