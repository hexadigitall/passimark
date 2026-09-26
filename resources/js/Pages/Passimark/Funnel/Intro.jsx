import { Head } from '@inertiajs/react';
export default function Intro({ funnel }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'intro';
  const next = funnel?.next;

  const milestones = [
    {
      rung: 'intro',
      title: 'Two minutes changes your day',
      body: "This is not a wall of tiles and never will be. Seven honest rungs - lock, splash, how-it-works, who you are, your focus, your permissions, and the dashboard you deserve. Every advance is saved; the ladder never dead-ends.",
    },
    {
      rung: 'auth',
      title: 'Confirm who you are',
      body: "Your account is already real. The auth rung simply threads your existing credentials through - nothing is created, nothing extra is required.",
    },
    {
      rung: 'focus',
      title: 'Pick what front-and-center means',
      body: "One focus at a time. Choose the track you want to move now; it stays re-editable, so this is never a permanent decision.",
    },
    {
      rung: 'permissions',
      title: 'Set the guardrails yourself',
      body: "What you allow drives what you can reach. Permissions prime once, stays yours, and is always re-tunable from your dashboard.",
    },
  ];

  return (
    <>
      <Head title="Getting started" />
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
      </div>

      <div className="relative w-full max-w-2xl rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur">
        <span className="text-xs font-semibold uppercase tracking-widest text-emerald-400">
          Rung {ladder.indexOf(step) + 1} of {ladder.length} · {step}
        </span>
        <h1 className="mt-2 text-3xl font-bold text-white">How this works</h1>
        <p className="mt-3 text-slate-400">
          Two minutes changes your day. Confirm who you are, pick what to focus on, and set your
          permissions - then your dashboard is yours, ready to go. Every advance is saved; the
          ladder never dead-ends.
        </p>

        <ol className="mt-6 space-y-3">
          {milestones.map((m, i) => (
            <li
              key={m.rung}
              className="flex gap-3 rounded-xl border border-slate-700/50 bg-slate-900/40 p-4"
            >
              <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-xs font-bold text-emerald-300">
                {i + 1}
              </span>
              <div className="flex flex-col gap-1">
                <strong className="text-sm font-semibold text-white">{m.title}</strong>
                <span className="text-sm leading-relaxed text-slate-400">{m.body}</span>
              </div>
            </li>
          ))}
        </ol>

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
            Start the guide
          </a>
        ) : null}
      </div>
    </div>
    </>
  );
}
