import { Head, Link } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import ReportHeader from './ReportHeader';
import StatStrip from './StatStrip';

const fmtDate = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const fmtNum = (value, precision = 1) => {
  if (value === null || value === undefined) return '—';
  const n = Number(value);
  return Number.isNaN(n) ? '—' : n.toFixed(precision);
};

const statusOptions = [
  ['all', 'All'],
  ['completed', 'Completed'],
  ['passed', 'Passed'],
  ['failed', 'Failed'],
  ['in_progress', 'In progress'],
];
const modeOptions = [
  ['all', 'All modes'],
  ['cat', 'CAT'],
  ['practice', 'Practice'],
  ['timed', 'Timed'],
];

const chip = (href, label, active) => (
  <Link key={label} href={href} className={`rounded-lg px-3 py-1.5 text-sm font-medium transition ${active ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'}`}>
    {label}
  </Link>
);

const bar = (count, total) => (total > 0 ? (count / total) * 100 : 0);

export default function Attempts({ summary = {}, bands = {}, modes = [], trend = [], rows = [], filters = { status: 'all', mode: 'all' } }) {
  const hrefFor = (status, mode) => `/admin/reports/attempts?status=${status}&mode=${mode}`;
  const bandTotal = Object.values(bands).reduce((sum, n) => sum + (n ?? 0), 0);

  const stats = [
    { label: 'Attempts', value: summary.total ?? 0, sub: `in progress ${summary.in_progress ?? 0}` },
    { label: 'Finished', value: summary.finished ?? 0, sub: `completion rate ${summary.completion_rate ?? 0}%` },
    { label: 'Passed', value: summary.passed ?? 0, sub: `failed ${summary.failed ?? 0}` },
    { label: 'Average score', value: summary.avg_score !== null ? `${fmtNum(summary.avg_score)}%` : '—', sub: `median ${summary.median_score !== null ? `${fmtNum(summary.median_score)}%` : '—'}` },
    { label: 'Best', value: summary.highest !== null ? `${fmtNum(summary.highest)}%` : '—', sub: `lowest ${summary.lowest !== null ? `${fmtNum(summary.lowest)}%` : '—'}` },
  ];

  return (
    <DashboardLayout>
      <Head title="Attempt Report" />
      <section className="mx-auto max-w-7xl">
        <ReportHeader
          title="Attempts"
          description="Every attempt across every learner: who sat what, in what mode, with what score and outcome. Filter by completion status or exam mode, and watch the 7-day cadence."
        >
          <div className="mt-5 flex flex-wrap items-center gap-2">
            <span className="text-xs uppercase tracking-wide text-slate-500">Status</span>
            {statusOptions.map(([value, label]) => chip(hrefFor(value, filters.mode), label, filters.status === value))}
            <span className="ml-3 text-xs uppercase tracking-wide text-slate-500">Mode</span>
            {modeOptions.map(([value, label]) => chip(hrefFor(filters.status, value), label, filters.mode === value))}
          </div>
        </ReportHeader>

        <StatStrip stats={stats} />

        <div className="mt-6 grid gap-6 lg:grid-cols-3">
          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h3 className="text-lg font-semibold text-white">Score distribution</h3>
            {bandTotal === 0 && <p className="mt-3 rounded-xl border border-dashed border-slate-700 p-4 text-sm text-slate-400">No finished attempts with scores yet.</p>}
            <div className="mt-4 space-y-3">
              {Object.entries(bands).map(([label, count]) => (
                <div key={label}>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-slate-300">{label}</span>
                    <span className="font-mono text-slate-400">{count} · {bandTotal ? `${Math.round((count / bandTotal) * 100)}%` : '0%'}</span>
                  </div>
                  <div className="mt-1 h-2 overflow-hidden rounded-full bg-slate-800">
                    <div className="h-full rounded-full bg-emerald-500/70" style={{ width: `${bar(count, bandTotal)}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h3 className="text-lg font-semibold text-white">By mode</h3>
            <table className="mt-4 w-full text-left text-sm">
              <thead className="text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="py-2 pr-2">Mode</th>
                  <th className="py-2 pr-2">Attempts</th>
                  <th className="py-2 pr-2">Passed</th>
                  <th className="py-2 pr-2">Avg</th>
                  <th className="py-2">Pass rate</th>
                </tr>
              </thead>
              <tbody>
                {modes.map((mode) => (
                  <tr key={mode.mode} className="border-t border-slate-800">
                    <td className="py-2 pr-2 capitalize text-white">{mode.mode}</td>
                    <td className="py-2 pr-2 text-slate-300">{mode.total}</td>
                    <td className="py-2 pr-2 text-slate-300">{mode.passed}</td>
                    <td className="py-2 pr-2 text-slate-300">{mode.avg_score !== null ? `${fmtNum(mode.avg_score)}%` : '—'}</td>
                    <td className="py-2 text-slate-300">{mode.pass_rate}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h3 className="text-lg font-semibold text-white">7-day cadence</h3>
            <p className="mt-1 text-xs text-slate-500">Finished attempts per day, with average score.</p>
            <table className="mt-4 w-full text-left text-sm">
              <thead className="text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="py-2 pr-2">Day</th>
                  <th className="py-2 pr-2">Finished</th>
                  <th className="py-2">Avg score</th>
                </tr>
              </thead>
              <tbody>
                {trend.map((day) => (
                  <tr key={day.day} className="border-t border-slate-800">
                    <td className="py-2 pr-2 text-slate-300">{day.day}</td>
                    <td className="py-2 pr-2 text-slate-300">{day.finished}</td>
                    <td className="py-2 text-slate-300">{day.avg !== null ? `${fmtNum(day.avg)}%` : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        </div>

        <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900">
          <table className="w-full min-w-[1100px] text-left text-sm">
            <thead className="border-b border-slate-700 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th className="px-4 py-3">Learner</th>
                <th className="px-4 py-3">Session / exam</th>
                <th className="px-4 py-3">Mode</th>
                <th className="px-4 py-3">Score</th>
                <th className="px-4 py-3">Result</th>
                <th className="px-4 py-3">θ</th>
                <th className="px-4 py-3">Questions</th>
                <th className="px-4 py-3">Time (min)</th>
                <th className="px-4 py-3">Finished</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={9} className="px-4 py-8 text-center text-slate-500">No attempts match this filter.</td>
                </tr>
              )}
              {rows.map((attempt) => (
                <tr key={attempt.id} className="border-b border-slate-800 transition hover:bg-slate-800/40">
                  <td className="px-4 py-3 font-medium text-white">{attempt.learner}</td>
                  <td className="max-w-[300px] px-4 py-3">
                    <p className="truncate text-slate-300">{attempt.session}</p>
                    <p className="text-xs text-slate-500">{attempt.exam}</p>
                  </td>
                  <td className="px-4 py-3 capitalize text-slate-300">{attempt.mode}</td>
                  <td className="px-4 py-3 text-slate-300">{attempt.score !== null ? `${fmtNum(attempt.score)}%` : '—'}</td>
                  <td className="px-4 py-3">
                    {attempt.score !== null
                      ? (attempt.passed
                        ? <span className="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-300">Pass</span>
                        : <span className="inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-300">Fail</span>)
                      : <span className="text-slate-600">—</span>}
                  </td>
                  <td className="px-4 py-3 font-mono text-slate-300">{fmtNum(attempt.theta, 2)}</td>
                  <td className="px-4 py-3 text-slate-300">{attempt.questions}</td>
                  <td className="px-4 py-3 text-slate-300">{attempt.duration !== null ? attempt.duration : '—'}</td>
                  <td className="px-4 py-3 text-slate-400">{fmtDate(attempt.finished_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </DashboardLayout>
  );
}