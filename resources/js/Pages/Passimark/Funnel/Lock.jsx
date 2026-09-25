export default function Lock({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'lock';
  const next = funnel?.next;

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
      </div>

      <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur">
        <div className="mb-6 flex flex-col items-center text-center">
          <div className="mb-4 text-emerald-400" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5">
              <rect x="4" y="10" width="16" height="11" rx="2" />
              <path d="M8 10V7a4 4 0 0 1 8 0v3" />
              <circle cx="12" cy="15.5" r="1.6" />
            </svg>
          </div>
          <h1 className="text-4xl font-bold text-white">Passimark</h1>
          <p className="mt-2 text-slate-400">Your certification is locked. Let&apos;s open it together.</p>
        </div>

        <ol className="mb-8 grid grid-cols-7 gap-1.5" aria-label="Track ladder">
          {ladder.map((rung, i) => (
            <li
              key={rung}
              className={
                rung === step
                  ? 'flex flex-col items-center gap-1 rounded-lg border border-emerald-500/50 bg-emerald-500/10 p-2'
                  : 'flex flex-col items-center gap-1 rounded-lg border border-slate-700/50 p-2'
              }
            >
              <span
                className={
                  rung === step
                    ? 'flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-slate-900'
                    : 'flex h-6 w-6 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-slate-300'
                }
              >
                {i + 1}
              </span>
              <span
                className={
                  rung === step
                    ? 'text-center text-[10px] font-semibold uppercase tracking-wide text-emerald-300'
                    : 'text-center text-[10px] font-medium text-slate-400'
                }
              >
                {rung}
              </span>
            </li>
          ))}
        </ol>

        {next ? (
          <a
            href={next}
            className="inline-flex w-full items-center justify-center rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-400"
          >
            Start the ladder
          </a>
        ) : null}
      </div>
    </div>
  );
}
