import { Head, router } from '@inertiajs/react';
import { BellRing, BellOff } from 'lucide-react';
import FunnelProgress from '../../../Components/FunnelProgress';

export default function Permissions({ funnel }) {
  const next = funnel?.next;

  const choose = (deadline_reminders) => {
    router.post(
      funnel?.submit ?? '/passimark/permissions/complete',
      { deadline_reminders },
      { preserveScroll: true },
    );
  };

  return (
    <>
      <Head title="Stay on track" />

      <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
        <div aria-hidden="true" className="absolute inset-0 overflow-hidden motion-reduce:hidden">
          <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
          <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
        </div>

        <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-6 shadow-2xl backdrop-blur sm:p-8">
          <FunnelProgress
            ladder={funnel?.ladder}
            step={funnel?.step ?? 'permissions'}
            className="mb-6"
          />

          <h1 className="text-2xl font-bold text-white sm:text-3xl">Stay on track</h1>
          <p className="mt-2 text-sm text-slate-400 sm:text-base">
            Want a nudge when a session unlocks or a deadline lands? Either answer is fine —
            you can change this any time in Settings.
          </p>

          <div className="mt-6 space-y-3">
            <button
              type="button"
              onClick={() => choose(true)}
              className="flex w-full items-center gap-3 rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60"
            >
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-300">
                <BellRing className="h-4 w-4" aria-hidden="true" />
              </span>
              <span className="min-w-0">
                <strong className="block text-sm font-semibold text-white">Remind me</strong>
                <span className="mt-0.5 block text-xs text-slate-400">
                  Unlock and deadline notifications. Stored on your account.
                </span>
              </span>
            </button>

            <button
              type="button"
              onClick={() => choose(false)}
              className="flex w-full items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/40 p-4 text-left transition hover:border-slate-600 hover:bg-slate-900/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60"
            >
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-700/60 text-slate-300">
                <BellOff className="h-4 w-4" aria-hidden="true" />
              </span>
              <span className="min-w-0">
                <strong className="block text-sm font-semibold text-white">Not now</strong>
                <span className="mt-0.5 block text-xs text-slate-400">
                  No reminders. Everything in Passimark still works.
                </span>
              </span>
            </button>
          </div>

          <p className="mt-4 text-center text-xs text-slate-500">
            Either answer takes you to your dashboard. You can change this later in Settings.
          </p>
        </div>
      </div>
    </>
  );
}
