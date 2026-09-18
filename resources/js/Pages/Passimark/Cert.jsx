import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Layers, TrendingUp } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import Breadcrumbs from '../../Components/Breadcrumbs';

function sourceLabel(source = '') {
  if (source.startsWith('seed:cissp-bundle')) return 'Textbook bundle';
  if (source.startsWith('seed:worldwide')) return 'Worldwide';
  if (source.startsWith('seed:passimark-v1')) return 'Legacy v1';
  if (source.startsWith('import:')) return 'Imported';
  if (!source) return 'Manual';
  return source.replace(/^seed:/, '');
}

export default function Cert({ category = {}, bundles = [] }) {
  const crumbs = [
    { label: 'Dashboard', href: '/' },
    ...(category.region ? [{ label: category.region }] : []),
    { label: category.title || category.cert_key },
  ];

  return (
    <DashboardLayout>
      <Head title={category.title || 'Certification'} />
      <section className="mx-auto max-w-7xl">
        <Breadcrumbs items={crumbs} />

        <div className="flex flex-col gap-2 border-b border-slate-800 pb-6">
          <p className="font-mono text-xs uppercase tracking-widest text-emerald-400">{category.cert_key}</p>
          <h2 className="text-3xl font-bold tracking-normal text-white">{category.title}</h2>
          <p className="text-sm text-slate-400">
            {category.region}
            {category.bundle_count ? ` · ${category.bundle_count} ${category.bundle_count === 1 ? 'bundle' : 'bundles'}` : ''}
            {category.sessions_total ? ` · ${category.sessions_done}/${category.sessions_total} sessions completed` : ''}
          </p>
        </div>

        {bundles.length === 0 ? (
          <div className="mt-10 rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center">
            <BookOpen className="mx-auto h-10 w-10 text-slate-500" />
            <h3 className="mt-4 text-xl font-semibold text-white">No bundles published yet</h3>
            <p className="mt-2 text-sm text-slate-400">This certification has no active bundle.</p>
          </div>
        ) : (
          <div className="mt-6 grid gap-4 lg:grid-cols-2">
            {bundles.map((bundle) => (
              <article
                key={bundle.slug}
                className="flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:border-slate-500"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-medium text-emerald-300">
                        <Layers className="h-3 w-3" />
                        {bundle.variant_label || 'Standard'}
                      </span>
                      <span className="rounded-full bg-slate-800 px-2.5 py-0.5 text-[11px] font-medium text-slate-400">
                        {sourceLabel(bundle.source)}
                      </span>
                      {bundle.advancement !== 'auto' && (
                        <span className="rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-medium text-amber-300">
                          Approval gated
                        </span>
                      )}
                    </div>
                    <h3 className="mt-2 text-lg font-semibold text-white">{bundle.title}</h3>
                    {bundle.description && (
                      <p className="mt-1 line-clamp-2 text-sm text-slate-400">{bundle.description}</p>
                    )}
                  </div>
                </div>

                <div className="mt-4 space-y-1.5">
                  <div className="flex items-center justify-between text-xs text-slate-400">
                    <span>
                      {bundle.sessions_done} / {bundle.sessions_total} sessions
                    </span>
                    <span className="font-mono">{bundle.percent}%</span>
                  </div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-slate-800">
                    <div
                      className="h-full rounded-full bg-emerald-500/80"
                      style={{ width: `${Math.min(bundle.percent, 100)}%` }}
                    />
                  </div>
                </div>

                <div className="mt-5 flex items-center justify-between border-t border-slate-800 pt-4">
                  <span className="text-xs text-slate-500">
                    {typeof bundle.theta === 'number' ? (
                      <span className="inline-flex items-center gap-1 font-mono text-slate-400">
                        <TrendingUp className="h-3.5 w-3.5" /> θ {bundle.theta.toFixed(2)}
                      </span>
                    ) : bundle.is_content_backed ? (
                      'Adaptive bank ready'
                    ) : (
                      'No question bank yet'
                    )}
                  </span>
                  {bundle.is_content_backed ? (
                    <Link
                      href={bundle.url}
                      className="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400"
                    >
                      {bundle.has_progress ? 'Continue' : 'Open track'}
                      <ArrowRight className="h-4 w-4" />
                    </Link>
                  ) : (
                    <span className="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-slate-500">
                      Unavailable
                    </span>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}
      </section>
    </DashboardLayout>
  );
}
