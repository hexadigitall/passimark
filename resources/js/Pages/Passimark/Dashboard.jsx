export default function Dashboard({ funnel }) {
  const ladder =
    funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'dashboard';
  const resume = funnel?.resume;
  const favorites = funnel?.favorites ?? [];
  const nextRequired = funnel?.nextRequired;

  return (
    <div className="min-h-screen bg-slate-950 p-6 text-slate-100">
      <div className="mx-auto max-w-4xl space-y-6">
        <header className="rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">
            Your track is unlocked · rung 7 of 7
          </p>
          <h1 className="mt-2 text-3xl font-bold text-white">
            {resume ? 'Welcome back — pick up where you left off' : 'Your track is ready'}
          </h1>
          {resume && (
            <p className="mt-3 text-sm text-slate-300">
              <strong className="text-white">{resume.title}</strong> · {resume.phase} (session{' '}
              {resume.session})
            </p>
          )}
          {nextRequired && (
            <p className="mt-2 text-sm text-slate-300">
              Next required: <strong className="text-white">{nextRequired.title}</strong>
            </p>
          )}
          {!resume && !nextRequired && (
            <p className="mt-3 text-sm text-slate-400">
              Your seven-rung ladder is complete. Pick a track from the catalog to keep moving.
            </p>
          )}
        </header>

        {favorites.length > 0 && (
          <section aria-label="Favorites" className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">
              Your favorites
            </h2>
            <ul className="mt-4 grid gap-2 sm:grid-cols-2">
              {favorites.map((fav, i) => (
                <li
                  key={fav.track ?? i}
                  className="rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm text-slate-200"
                >
                  {fav.title}
                </li>
              ))}
            </ul>
          </section>
        )}

        <section aria-label="Track ladder">
          <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {ladder.map((rung, i) => (
              <li
                key={rung}
                className={
                  rung === step
                    ? 'flex items-center gap-3 rounded-xl border border-emerald-500/50 bg-emerald-500/10 px-4 py-3'
                    : 'flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-3'
                }
              >
                <span
                  className={
                    rung === step
                      ? 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-slate-900'
                      : 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-semibold text-slate-400'
                  }
                >
                  {i + 1}
                </span>
                <span
                  className={
                    rung === step
                      ? 'text-sm font-semibold capitalize text-emerald-300'
                      : 'text-sm font-medium capitalize text-slate-400'
                  }
                >
                  {rung}
                </span>
              </li>
            ))}
          </ol>
        </section>
      </div>
    </div>
  );
}
