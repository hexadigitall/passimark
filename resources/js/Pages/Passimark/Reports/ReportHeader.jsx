import { Link } from '@inertiajs/react';

export default function ReportHeader({ title, description, backHref = '/', children }) {
  return (
    <div className="border-b border-slate-800 pb-6">
      <Link
        href={backHref}
        className="inline-flex items-center gap-1 text-sm font-medium text-brand-400 transition hover:text-brand-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60"
      >
        ← Back to overview
      </Link>
      <h2 className="mt-2 text-2xl font-bold tracking-normal text-white sm:text-3xl">{title}</h2>
      <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-400">{description}</p>
      {children}
    </div>
  );
}