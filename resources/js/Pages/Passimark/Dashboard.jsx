import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  ArrowRight,
  BookOpen,
  Layers,
  Play,
  Search,
  Sparkles,
  Target,
  TrendingUp,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';

const ACCENTS = [
  'from-emerald-500/25 to-emerald-500/0 text-emerald-300 ring-emerald-500/30',
  'from-sky-500/25 to-sky-500/0 text-sky-300 ring-sky-500/30',
  'from-violet-500/25 to-violet-500/0 text-violet-300 ring-violet-500/30',
  'from-amber-500/25 to-amber-500/0 text-amber-300 ring-amber-500/30',
  'from-rose-500/25 to-rose-500/0 text-rose-300 ring-rose-500/30',
  'from-teal-500/25 to-teal-500/0 text-teal-300 ring-teal-500/30',
];

function accentFor(seed = '') {
  const sum = String(seed).split('').reduce((total, char) => total + char.charCodeAt(0), 0);
  return ACCENTS[sum % ACCENTS.length];
}

function monogram(title = '') {
  return title
    .replace(/[^A-Za-z0-9 ]/g, ' ')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase() || '?';
}

export default function Dashboard({ sections = [], stats = {}, region = '', continueSession = null }) {
  const [query, setQuery] = useState('');
  const [resuming, setResuming] = useState(false);

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return sections;
    return sections
      .map((section) => ({
        ...section,
        categories: section.categories.filter((category) =>
          `${category.title} ${category.cert_key} ${category.region}`.toLowerCase().includes(needle),
        ),
      }))
      .filter((section) => section.categories.length);
  }, [sections, query]);

  const resume = () => {
    if (!continueSession) return;
    setResuming(true);
    router.post(
      `/passimark/session/${continueSession.session_id}/start`,
      { mode: 'cat' },
      { preserveScroll: true, onFinish: () => setResuming(false) },
    );
  };

  const totalCerts = sections.reduce((total, section) => total + section.categories.length, 0);

  return (
    <DashboardLayout>
      <Head title="Dashboard" />
      <section className="mx-auto max-w-7xl">
        <div className="flex flex-col gap-3 border-b border-slate-800 pb-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-sm font-medium text-emerald-400">Worldwide mastery</p>
            <h2 className="mt-1 text-3xl font-bold tracking-normal text-white">Choose your certification</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
              Browse {totalCerts} certifications. Open one to pick a bundle, then work the adaptive session ladder.
            </p>
          </div>
        </div>

        {continueSession && (
          <div className="mt-6 flex flex-col gap-4 rounded-2xl border border-emerald-500/30 bg-gradient-to-r from-emerald-500/10 to-transparent p-5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
              <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30">
                <Play className="h-5 w-5" />
              </span>
              <div>
                <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-emerald-300">
                  <Sparkles className="h-3.5 w-3.5" /> Continue where you left off
                </p>
                <p className="mt-1 text-base font-semibold text-white">{continueSession.title}</p>
                <p className="text-xs text-slate-400">
                  {continueSession.track_title}
                  {continueSession.number ? ` · Session ${continueSession.number}` : ''}
                </p>
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              <Link
                href={continueSession.url}
                className="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700"
              >
                View track
              </Link>
              <button
                type="button"
                onClick={resume}
                disabled={resuming}
                className="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {resuming ? 'Starting...' : 'Resume session'}
                <ArrowRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}

        <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Certifications" value={stats.categories ?? totalCerts} />
          <Metric label="Bundles" value={stats.bundles ?? 0} />
          <Metric label="Sessions completed" value={`${stats.sessions_done ?? 0} / ${stats.sessions_total ?? 0}`} />
          <Metric
            label="Overall completion"
            value={`${stats.sessions_total ? Math.round(((stats.sessions_done ?? 0) / stats.sessions_total) * 100) : 0}%`}
          />
        </div>

        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative w-full sm:max-w-sm">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search certifications..."
              className="w-full rounded-lg border border-slate-700 bg-slate-800/70 py-2.5 pl-9 pr-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500/50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
            />
          </div>
          <p className="text-xs text-slate-500">
            {region ? `Filtered to ${region}` : 'All regions'} · press a tile to open its bundles
          </p>
        </div>

        {filtered.length === 0 ? (
          <div className="mt-10 rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center">
            <Target className="mx-auto h-10 w-10 text-slate-500" />
            <h3 className="mt-4 text-xl font-semibold text-white">
              {query ? 'No certifications match your search' : 'No certification tracks yet'}
            </h3>
            <p className="mt-2 text-sm text-slate-400">
              {query ? 'Try a different term or clear the search.' : 'Your admin will assign certification tracks to your account.'}
            </p>
          </div>
        ) : (
          filtered.map((section) => (
            <section key={section.region} className="mt-10">
              <div className="flex items-baseline justify-between gap-4">
                <h3 className="text-lg font-semibold text-white">{section.region}</h3>
                <span className="text-sm text-slate-500">
                  {section.categories.length} {section.categories.length === 1 ? 'certification' : 'certifications'}
                </span>
              </div>
              <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {section.categories.map((category) => (
                  <CategoryTile key={category.cert_key} category={category} />
                ))}
              </div>
            </section>
          ))
        )}
      </section>
    </DashboardLayout>
  );
}

function CategoryTile({ category }) {
  const accent = accentFor(category.cert_key);
  const hasTheta = typeof category.theta === 'number';

  return (
    <Link
      href={`/certs/${category.cert_key}`}
      aria-label={`Open ${category.title}`}
      className="group flex flex-col rounded-2xl border border-slate-700 bg-slate-900 p-5 transition hover:-translate-y-0.5 hover:border-slate-500 hover:bg-slate-800/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60"
    >
      <div className="flex items-start justify-between gap-3">
        <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-bold ring-1 ${accent}`}>
          {monogram(category.title)}
        </span>
        {category.bundle_count > 1 && (
          <span className="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2 py-0.5 text-[11px] font-medium text-slate-300 ring-1 ring-slate-700">
            <Layers className="h-3 w-3" /> {category.bundle_count}
          </span>
        )}
      </div>

      <p className="mt-3 font-mono text-[11px] uppercase tracking-widest text-slate-500">{category.cert_key}</p>
      <h4 className="mt-1 line-clamp-2 text-sm font-semibold leading-5 text-white">{category.title}</h4>

      <div className="mt-4 space-y-1.5">
        <div className="flex items-center justify-between text-xs text-slate-400">
          <span>
            {category.sessions_done} / {category.sessions_total} sessions
          </span>
          <span className="font-mono">{category.percent}%</span>
        </div>
        <div className="h-1.5 overflow-hidden rounded-full bg-slate-800">
          <div
            className={`h-full rounded-full ${category.percent >= 100 ? 'bg-emerald-400' : 'bg-emerald-500/80'}`}
            style={{ width: `${Math.min(category.percent, 100)}%` }}
          />
        </div>
      </div>

      <div className="mt-4 flex items-center justify-between border-t border-slate-800 pt-3 text-xs">
        <span className="text-slate-500">
          {category.has_progress ? (
            <span className="text-emerald-300">In progress</span>
          ) : (
            <span>Not started</span>
          )}
        </span>
        {hasTheta ? (
          <span className="inline-flex items-center gap-1 font-mono text-slate-400">
            <TrendingUp className="h-3.5 w-3.5" /> θ {category.theta.toFixed(2)}
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 font-medium text-emerald-300 opacity-0 transition group-hover:opacity-100">
            Open <ArrowRight className="h-3.5 w-3.5" />
          </span>
        )}
      </div>
    </Link>
  );
}

function Metric({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-700 bg-slate-800 p-4">
      <p className="text-xs uppercase tracking-wider text-slate-400">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-white">{value}</p>
    </div>
  );
}
