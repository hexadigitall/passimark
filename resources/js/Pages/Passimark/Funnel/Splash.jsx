import { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';

const ADVANCE_MS = 1800;

export default function Splash({ funnel }) {
  const next = funnel?.next;

  // The sequence should carry itself forward. A visible Continue control stays on
  // screen at all times so auto-advance is never a trap.
  useEffect(() => {
    if (!next) return undefined;

    const timer = setTimeout(() => {
      router.get(next);
    }, ADVANCE_MS);

    return () => clearTimeout(timer);
  }, [next]);

  return (
    <>
      <Head title="Welcome" />

      <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
        <div aria-hidden="true" className="absolute inset-0 overflow-hidden motion-reduce:hidden">
          <div className="absolute -top-32 left-1/3 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
          <div className="absolute -bottom-32 right-1/4 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
        </div>

        <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 text-center shadow-2xl backdrop-blur sm:p-10">
          <div className="flex flex-col items-center">
            <div className="mb-4 text-emerald-400" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="72" height="72" fill="none" stroke="currentColor" strokeWidth="1.2">
                <path d="M4 12a8 8 0 1 0 16 0" />
                <path d="M12 4v8" />
                <circle cx="12" cy="8" r="1" fill="currentColor" />
              </svg>
            </div>

            <h1 className="text-4xl font-bold text-white sm:text-5xl">Passimark</h1>
            <p className="mt-3 max-w-sm text-slate-400">
              Adaptive certification, measured one session at a time.
            </p>
          </div>

          {next && (
            <a
              href={next}
              className="mt-8 inline-flex w-full items-center justify-center rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 sm:mt-10"
            >
              Continue
            </a>
          )}
        </div>
      </div>
    </>
  );
}
