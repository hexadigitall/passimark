import { Head, Link } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import ReportHeader from './ReportHeader';
import StatStrip from './StatStrip';

const fmtDate = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
};

const fmtLag = (hours) => {
  if (hours === null || hours === undefined) return '—';
  if (hours < 1) return `${Math.round(hours * 60)} min`;
  if (hours >= 48) return `${(hours / 24).toFixed(1)} d`;
  return `${hours.toFixed(1)} h`;
};

const filters = [
  ['all', 'All decisions'],
  ['approved', 'Approved'],
  ['rejected', 'Rejected'],
  ['pending', 'Pending queue'],
];

const actionChip = (action) =>
  action === 'approved'
    ? <span className="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-300">Approved</span>
    : <span className="inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-300">Rejected</span>;

export default function Approvals({ summary = {}, rows = [], pending = [], trend = [], activeFilter = 'all' }) {
  const stats = [
    { label: 'Decisions', value: summary.decisions ?? 0, sub: 'approved + rejected' },
    { label: 'Approved', value: summary.approved ?? 0, sub: 'unlocked next step' },
    { label: 'Rejected', value: summary.rejected ?? 0, sub: 'returned for revisions' },
    { label: 'Avg review lag', value: fmtLag(summary.avg_lag_hours), sub: 'request → decision' },
    { label: 'Pending now', value: summary.pending ?? 0, sub: 'awaiting your action' },
  ];

  return (
    <DashboardLayout>
      <Head title="Approvals Report" />
      <section className="mx-auto max-w-7xl">
        <ReportHeader
          title="Approvals"
          description="The full decision ledger: how fast submissions are reviewed, the approve/reject balance over the last 8 weeks, and the current pending queue with aging."
        >
          <div className="mt-5 flex flex-wrap items-center gap-2">
            <span className="text-xs uppercase tracking-wide text-slate-500">View</span>
            {filters.map(([value, label]) => (
              <Link key={value} href={value === 'all' ? '/admin/reports/approvals' : `/admin/reports/approvals?filter=${value}`}
                className={`rounded-lg px-3 py-1.5 text-sm font-medium transition ${activeFilter === value ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'}`}>
                {label}
              </Link>
            ))}
          </div>
        </ReportHeader>

        <StatStrip stats={stats} />

        <div className="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h3 className="text-lg font-semibold text-white">Weekly decisions</h3>
            <p className="mt-1 text-xs text-slate-500">Last 8 weeks (ISO weeks starting Monday).</p>
            <table className="mt-4 w-full text-left text-sm">
              <thead className="text-xs uppercase tracking-wide text-slate-400">
                <tr><th className="py-2 pr-2">Week</th><th className="py-2 pr-2 text-right">Approved</th><th className="py-2 text-right">Rejected</th></tr>
              </thead>
              <tbody>
                {trend.map((week) => (
                  <tr key={week.week} className="border-t border-slate-800">
                    <td className="py-2 pr-2 text-slate-300">{week.week}</td>
                    <td className="py-2 pr-2 text-right text-emerald-300">{week.approved}</td>
                    <td className="py-2 text-right text-red-300">{week.rejected}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            {activeFilter === 'pending'
              ? (
                <>
                  <h3 className="text-lg font-semibold text-white">Pending queue</h3>
                  <p className="mt-1 text-xs text-slate-500">Oldest first — age is time since the approval was requested.</p>
                  <table className="mt-4 w-full text-left text-sm">
                    <thead className="text-xs uppercase tracking-wide text-slate-400">
                      <tr><th className="py-2 pr-2">Learner</th><th className="py-2 pr-2">Session</th><th className="py-2 pr-2">Score</th><th className="py-2 pr-2">Requested</th><th className="py-2">Age</th></tr>
                    </thead>
                    <tbody>
                      {pending.map((item) => (
                        <tr key={item.id} className="border-t border-slate-800">
                          <td className="py-2 pr-2 text-slate-200">{item.learner} <span className="text-xs text-slate-500">{item.email}</span></td>
                          <td className="max-w-[240px] truncate py-2 pr-2 text-slate-300">{item.session}</td>
                          <td className="py-2 pr-2 text-slate-300">{item.score !== null ? `${item.score}%` : '—'}</td>
                          <td className="py-2 pr-2 text-slate-400">{fmtDate(item.requested_at)}</td>
                          <td className="py-2">
                            {Number(item.age_days) > 1
                              ? <span className="inline-flex rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-300">{item.age_days} d</span>
                              : <span className="text-slate-400">{item.age_days ?? 0} d</span>}
                          </td>
                        </tr>
                      ))}
                      {pending.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-slate-500">Nothing is awaiting approval.</td></tr>}
                    </tbody>
                  </table>
                </>
              )
              : (
                <>
                  <h3 className="text-lg font-semibold text-white">Decision ledger</h3>
                  <table className="mt-4 w-full text-left text-sm">
                    <thead className="text-xs uppercase tracking-wide text-slate-400">
                      <tr><th className="py-2 pr-2">Action</th><th className="py-2 pr-2">Learner</th><th className="py-2 pr-2">Session</th><th className="py-2 pr-2">Reviewer</th><th className="py-2 pr-2">Note</th><th className="py-2 pr-2">Review lag</th><th className="py-2">Decided</th></tr>
                    </thead>
                    <tbody>
                      {rows.map((row) => (
                        <tr key={row.id} className="border-t border-slate-800">
                          <td className="py-2 pr-2">{actionChip(row.action)}</td>
                          <td className="py-2 pr-2 text-slate-200">{row.learner}</td>
                          <td className="max-w-[220px] truncate py-2 pr-2 text-slate-300">{row.session}</td>
                          <td className="py-2 pr-2 text-slate-300">{row.reviewer}</td>
                          <td className="max-w-[200px] truncate py-2 pr-2 text-slate-500" title={row.note ?? ''}>{row.note ?? '—'}</td>
                          <td className="py-2 pr-2 text-slate-300">{fmtLag(row.lag_hours)}</td>
                          <td className="py-2 text-slate-400">{fmtDate(row.decided_at)}</td>
                        </tr>
                      ))}
                      {rows.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-slate-500">No {activeFilter} decisions recorded yet.</td></tr>}
                    </tbody>
                  </table>
                </>
              )}
          </section>
        </div>
      </section>
    </DashboardLayout>
  );
}