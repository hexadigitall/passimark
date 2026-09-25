export default function Splash({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'splash';
  const next = funnel?.next;

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-32 left-1/3 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
        <div className="absolute -bottom-32 right-1/4 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
      </div>

      <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur">
        <div className="mb-6 flex flex-col items-center text-center">
          <div className="mb-4 text-emerald-400" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="72" height="72" fill="none" stroke="currentColor" strokeWidth="1.2">
              <path d="M4 12a8 8 0 1 0 16 0" />
              <path d="M12 4v8" />
              <circle cx="12" cy="8" r="1" fill="currentColor" />
            </svg>
          </div>
          <h1 className="text-4xl font-bold text-white">Passimark</h1>
          <p className="mt-2 text-slate-400">Sprint 9.5 — content coherence, one credential story.</p>
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
            Continue
          </a>
        ) : null}
      </div>
    </div>
  );
}
