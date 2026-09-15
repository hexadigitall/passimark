export default function StatStrip({ stats = [] }) {
  return (
    <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      {stats.map((stat) => (
        <div key={stat.label} className="rounded-xl border border-slate-700 bg-slate-900 p-4">
          <p className="text-xs uppercase tracking-wide text-slate-400">{stat.label}</p>
          <p className="mt-2 text-2xl font-semibold text-white">{stat.value}</p>
          {stat.sub && <p className="mt-1 text-xs text-slate-500">{stat.sub}</p>}
        </div>
      ))}
    </div>
  );
}