import { Head } from '@inertiajs/react';

export default function Lock({ funnel }) {
  const next = funnel?.next;

  return (
    <>
      <Head title="Passimark" />

      <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4 motion-reduce:overflow-y-auto">
        <div aria-hidden="true" className="absolute inset-0 overflow-hidden motion-reduce:hidden">
          <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
          <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
        </div>

        <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 text-center shadow-2xl backdrop-blur sm:p-10">
          <div className="flex flex-col items-center">
            <div className="mb-4 text-emerald-400" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5">
                <rect x="4" y="10" width="16" height="11" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                <circle cx="12" cy="15.5" r="1.6" />
              </svg>
            </div>

            <h1 className="text-4xl font-bold text-white sm:text-5xl">Passimark</h1>
            <p className="mt-3 max-w-sm text-slate-400">
              Your certification is locked. Let&apos;s open it together.
            </p>
          </div>

          {next && (
            <a
              href={next}
              className="mt-8 inline-flex w-full items-center justify-center rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 sm:mt-10"
            >
              Enter
            </a>
          )}
        </div>
      </div>
    </>
  );
}
