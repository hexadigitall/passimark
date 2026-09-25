export default function Auth({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'auth';
  const next = funnel?.next;

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
      </div>

      <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur">
        <h1 className="text-3xl font-bold text-white">Confirm it&apos;s you</h1>
        <p className="mt-3 text-slate-400">
          The ladder already knows who you are. This rung is a real credential gate — sign in with
          the account that holds your Passimark progress, and the next rung unlocks.
        </p>

        <ol className="mt-6 grid grid-cols-7 gap-1.5" aria-label="Track ladder">
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
            className="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-400"
          >
            Sign in &amp; continue
          </a>
        ) : null}
      </div>
    </div>
  );
}
