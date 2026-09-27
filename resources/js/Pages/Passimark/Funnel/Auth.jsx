import { Head } from '@inertiajs/react';
import FunnelProgress from '../../../Components/FunnelProgress';

export default function Auth({ funnel }) {
  const next = funnel?.next;

  return (
    <>
      <Head title="Confirm your account" />
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
      <div className="absolute inset-0 overflow-hidden" aria-hidden="true">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
      </div>

      <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-6 shadow-2xl backdrop-blur sm:p-8">
        <FunnelProgress ladder={funnel?.ladder} step={funnel?.step ?? 'auth'} className="mb-6" />

        <h1 className="text-2xl font-bold text-white sm:text-3xl">Confirm it&apos;s you</h1>
        <p className="mt-3 text-sm text-slate-400 sm:text-base">
          Sign in with the account that holds your Passimark progress and we&apos;ll pick up
          exactly where you left off.
        </p>

        {next ? (
          <a
            href={next}
            className="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60"
          >
            Sign in &amp; continue
          </a>
        ) : null}
      </div>
    </div>
    </>
  );
}
