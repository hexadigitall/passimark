import { Head } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import ReportHeader from './ReportHeader';
import StatStrip from './StatStrip';

const fmtNum = (value, precision = 1) => {
  if (value === null || value === undefined) return '—';
  const n = Number(value);
  return Number.isNaN(n) ? '—' : n.toFixed(precision);
};

const pct = (value) => (value === null || value === undefined ? '—' : `${value}%`);

const difficultyLabel = { easy: 'easy (< 0.33)', mid: 'mid (0.33–0.67)', hard: 'hard (> 0.67)' };

export default function Questions({ summary = {}, domains = [], blooms = [], difficulty = {}, weakest = [], never_used_samples = [] }) {
  const stats = [
    { label: 'Questions', value: summary.total ?? 0, sub: 'in the catalog' },
    { label: 'Used', value: summary.used ?? 0, sub: 'appeared in ≥1 attempt' },
    { label: 'Accuracy', value: pct(summary.accuracy), sub: 'across all answers' },
    { label: 'Never used', value: summary.never_used ?? 0, sub: 'no attempt exposure' },
    { label: 'Untagged', value: summary.untagged ?? 0, sub: `no-explanation ${summary.missing_explanation ?? 0} · low-d ${summary.low_discrimination ?? 0}` },
  ];

  const bands = Object.entries(difficulty);
  const bandTotal = bands.reduce((sum, [, n]) => sum + (n ?? 0), 0);

  return (
    <DashboardLayout>
      <Head title="Question Report" />
      <section className="mx-auto max-w-7xl">
        <ReportHeader
          title="Questions"
          description="Item analytics across domains and cognition levels, plus quality flags (never used, untagged, missing explanations, weak discriminators) and the weakest-performing items."
        />
        <StatStrip stats={stats} />

        <div className="mt-6 grid gap-6 lg:grid-cols-2">
          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
            <h3 className="text-lg font-semibold text-white">By domain</h3>
            <table className="mt-4 w-full text-left text-sm">
              <thead className="text-xs uppercase tracking-wide text-slate-400">
                <tr><th className="py-2 pr-2">Domain</th><th className="py-2 pr-2">Questions</th><th className="py-2 pr-2">Times used</th><th className="py-2">Accuracy</th></tr>
              </thead>
              <tbody>
                {domains.map((row) => (
                  <tr key={row.domain} className="border-t border-slate-800">
                    <td className="max-w-[260px] truncate py-2 pr-2 text-slate-200">{row.domain}</td>
                    <td className="py-2 pr-2 text-slate-300">{row.questions}</td>
                    <td className="py-2 pr-2 text-slate-300">{row.used}</td>
                    <td className="py-2 text-slate-300">{pct(row.accuracy)}</td>
                  </tr>
                ))}
                {domains.length === 0 && <tr><td colSpan={4} className="py-4 text-center text-slate-500">No questions.</td></tr>}
              </tbody>
            </table>
          </section>

          <div className="space-y-6">
            <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
              <h3 className="text-lg font-semibold text-white">Cognition levels & difficulty</h3>
              <table className="mt-4 w-full text-left text-sm">
                <thead className="text-xs uppercase tracking-wide text-slate-400">
                  <tr><th className="py-2 pr-2">Bloom level</th><th className="py-2 pr-2">Questions</th><th className="py-2 pr-2">Times used</th><th className="py-2">Accuracy</th></tr>
                </thead>
                <tbody>
                  {blooms.map((row) => (
                    <tr key={row.bloom} className="border-t border-slate-800">
                      <td className="py-2 pr-2 capitalize text-slate-200">{row.bloom}</td>
                      <td className="py-2 pr-2 text-slate-300">{row.questions}</td>
                      <td className="py-2 pr-2 text-slate-300">{row.used}</td>
                      <td className="py-2 text-slate-300">{pct(row.accuracy)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="mt-5 space-y-2">
                {bands.map(([label, count]) => (
                  <div key={label} className="flex items-center justify-between text-sm">
                    <span className="text-slate-300">{difficultyLabel[label] ?? label}</span>
                    <div className="flex flex-1 items-center gap-2 px-3">
                      <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-800">
                        <div className="h-full rounded-full bg-sky-500/70" style={{ width: `${bandTotal ? Math.round((count / bandTotal) * 100) : 0}%` }} />
                      </div>
                      <span className="w-16 text-right font-mono text-slate-400">{count}</span>
                    </div>
                  </div>
                ))}
              </div>
            </section>

            <section className="rounded-2xl border border-slate-700 bg-slate-900 p-5">
              <h3 className="text-lg font-semibold text-white">Weakest items</h3>
              <p className="mt-1 text-xs text-slate-500">Lowest accuracy, requiring at least 5 recorded answers. Ranks from worst upward.</p>
              <table className="mt-4 w-full text-left text-sm">
                <thead className="text-xs uppercase tracking-wide text-slate-400">
                  <tr><th className="py-2 pr-2">Item</th><th className="py-2 pr-2">Domain</th><th className="py-2 pr-2">Used</th><th className="py-2">Accuracy</th></tr>
                </thead>
                <tbody>
                  {weakest.map((row) => (
                    <tr key={row.id} className="border-t border-slate-800">
                      <td className="max-w-[280px] py-2 pr-2 text-slate-200">
                        <p className="truncate" title={row.snippet}>{row.snippet}</p>
                        <p className="text-xs text-slate-500">#{row.id}</p>
                      </td>
                      <td className="py-2 pr-2 text-slate-300">{row.domain}</td>
                      <td className="py-2 pr-2 text-slate-300">{row.used}</td>
                      <td className="py-2 text-red-300">{fmtNum(row.accuracy)}%</td>
                    </tr>
                  ))}
                  {weakest.length === 0 && <tr><td colSpan={4} className="py-4 text-center text-slate-500">No item has 5+ recorded answers yet.</td></tr>}
                </tbody>
              </table>
            </section>
          </div>
        </div>

        <section className="mt-6 rounded-2xl border border-slate-700 bg-slate-900 p-5">
          <h3 className="text-lg font-semibold text-white">Never-used samples</h3>
          <p className="mt-1 text-xs text-slate-500">First 5 items that have never appeared in an attempt ({summary.never_used ?? 0} total).</p>
          {never_used_samples.length === 0
            ? <p className="mt-3 rounded-xl border border-dashed border-slate-700 p-4 text-sm text-slate-400">Every question has been attempted at least once.</p>
            : (
              <ul className="mt-4 space-y-2">
                {never_used_samples.map((row) => (
                  <li key={row.id} className="flex items-start justify-between gap-4 border-b border-slate-800 py-2 text-sm">
                    <span className="max-w-[660px] truncate text-slate-200" title={row.snippet}>{row.snippet}</span>
                    <span className="flex shrink-0 items-center gap-3 text-xs text-slate-500">
                      <span>#{row.id}</span>
                      <span className="max-w-[160px] truncate">{row.domain}</span>
                    </span>
                  </li>
                ))}
              </ul>
            )}
        </section>
      </section>
    </DashboardLayout>
  );
}