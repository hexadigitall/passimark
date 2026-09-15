import { Head } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import ReportHeader from './ReportHeader';
import StatStrip from './StatStrip';

const fmtNum = (value) => {
  if (value === null || value === undefined) return '—';
  const n = Number(value);
  return Number.isNaN(n) ? '—' : n.toFixed(1);
};

const Chip = ({ label, tone = 'slate' }) => (
  <span className={`mr-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${tone === 'emerald' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-700/40 text-slate-300'}`}>
    {label}
  </span>
);

export default function Tracks({ summary = {}, rows = [] }) {
  const stats = [
    { label: 'Tracks', value: summary.tracks ?? 0, sub: 'certification programs' },
    { label: 'Sessions', value: summary.sessions ?? 0, sub: `${summary.questions ?? 0} questions` },
    { label: 'Enrolled learners', value: summary.enrolled ?? 0, sub: 'with progression records' },
    { label: 'Completions', value: summary.completions ?? 0, sub: 'completed or approved' },
    { label: 'Pending approvals', value: summary.pending ?? 0, sub: 'across all tracks' },
  ];

  return (
    <DashboardLayout>
      <Head title="Tracks Report" />
      <section className="mx-auto max-w-7xl">
        <ReportHeader
          title="Tracks"
          description="The certification completion matrix: content size, learners enrolled, engagement, pass performance, and how far each track's cohort has progressed."
        />
        <StatStrip stats={stats} />

        <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900">
          <table className="w-full min-w-[1000px] text-left text-sm">
            <thead className="border-b border-slate-700 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th className="px-4 py-3">Track</th>
                <th className="px-4 py-3">Region</th>
                <th className="px-4 py-3">Advancement</th>
                <th className="px-4 py-3">Sessions</th>
                <th className="px-4 py-3">Questions</th>
                <th className="px-4 py-3">Enrolled</th>
                <th className="px-4 py-3">Attempts</th>
                <th className="px-4 py-3">Avg score</th>
                <th className="px-4 py-3">Pass rate</th>
                <th className="px-4 py-3">Completions</th>
                <th className="px-4 py-3">Pending</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={11} className="px-4 py-8 text-center text-slate-500">No certification tracks yet.</td>
                </tr>
              )}
              {rows.map((track) => (
                <tr key={track.id} className="border-b border-slate-800 transition hover:bg-slate-800/40">
                  <td className="px-4 py-3">
                    <p className="font-medium text-white">{track.title}</p>
                    <p className="text-xs text-slate-500">{track.slug}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-300">{track.region ?? '—'}</td>
                  <td className="px-4 py-3">
                    <Chip label={track.advancement} tone={track.advancement === 'auto' ? 'emerald' : 'slate'} />
                  </td>
                  <td className="px-4 py-3 text-slate-300">{track.sessions}</td>
                  <td className="px-4 py-3 text-slate-300">{track.questions}</td>
                  <td className="px-4 py-3 text-slate-300">{track.enrolled}</td>
                  <td className="px-4 py-3 text-slate-300">{track.attempts}</td>
                  <td className="px-4 py-3 text-slate-300">{fmtNum(track.avg_score)}</td>
                  <td className="px-4 py-3 text-slate-300">{track.pass_rate}%</td>
                  <td className="px-4 py-3">
                    {track.completions > 0
                      ? <span className="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-300">{track.completions}</span>
                      : <span className="text-slate-600">0</span>}
                  </td>
                  <td className="px-4 py-3">
                    {track.pending > 0
                      ? <span className="inline-flex rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-300">{track.pending}</span>
                      : <span className="text-slate-600">0</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </DashboardLayout>
  );
}