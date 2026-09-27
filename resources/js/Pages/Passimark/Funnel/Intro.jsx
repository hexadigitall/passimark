import { Head } from '@inertiajs/react';
import { Compass, Gauge, Layers, Award } from 'lucide-react';
import FunnelProgress from '../../../Components/FunnelProgress';

const MILESTONES = [
  {
    icon: Compass,
    title: 'One keystroke to any of 205 certifications',
    body: 'Search the whole worldwide catalog from anywhere. No paging, no region filters to fight through — type a name and go.',
  },
  {
    icon: Layers,
    title: 'Work one track at a time',
    body: 'Pick the certification you care about now and your dashboard rebuilds around it. Everything else stays one search away, never in your way.',
  },
  {
    icon: Gauge,
    title: 'θ adapts to you',
    body: 'Every question is chosen against your current ability estimate, so each session targets the edge of what you know instead of repeating what you have already proved.',
  },
  {
    icon: Award,
    title: 'Earn a verifiable credential',
    body: 'Pass the ladder and a certificate is issued with a public verification code anyone can check.',
  },
];

export default function Intro({ funnel }) {
  const next = funnel?.next;

  return (
    <>
      <Head title="Getting started" />

      <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
        <div aria-hidden="true" className="absolute inset-0 overflow-hidden motion-reduce:hidden">
          <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-brand-500 opacity-20 blur-3xl" />
          <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
        </div>

        <div className="relative w-full max-w-2xl rounded-2xl border border-slate-700/50 bg-slate-800/80 p-6 shadow-2xl backdrop-blur sm:p-8">
          <FunnelProgress ladder={funnel?.ladder} step={funnel?.step ?? 'intro'} className="mb-6" />

          <h1 className="text-2xl font-bold text-white sm:text-3xl">How Passimark works</h1>
          <p className="mt-2 text-sm text-slate-400 sm:text-base">
            Four things worth knowing before you start.
          </p>

          <ol className="mt-6 space-y-3">
            {MILESTONES.map(({ icon: Icon, title, body }) => (
              <li
                key={title}
                className="flex gap-3 rounded-xl border border-slate-700/50 bg-slate-900/40 p-4"
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-500/15 text-brand-300 ring-1 ring-brand-500/30">
                  <Icon className="h-4 w-4" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                  <strong className="block text-sm font-semibold text-white">{title}</strong>
                  <span className="mt-1 block text-sm leading-relaxed text-slate-400">{body}</span>
                </div>
              </li>
            ))}
          </ol>

          {next && (
            <a
              href={next}
              className="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-brand-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-brand-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60"
            >
              Continue
            </a>
          )}
        </div>
      </div>
    </>
  );
}
