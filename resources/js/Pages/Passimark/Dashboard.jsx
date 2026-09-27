import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Lock, Play, Search, Target, TrendingUp } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';

/**
 * The one-student dashboard.
 *
 * A learner picked one interest. This screen is built around that choice and
 * nothing else: what to do next, how far along they are, a short set of
 * neighbours, and one search box for the rest of the catalog. The full
 * region-grouped catalog is still rendered, but only when explicitly opened —
 * it is a destination, not the landing view.
 */
export default function Dashboard({
  sections = [],
  focus = null,
  continueSession = null,
  catalogTotal = 0,
  region = '',
}) {
  const [browseOpen, setBrowseOpen] = useState(false);

  const resume = () => {
    if (!continueSession) return;
    router.post(
      `/passimark/session/${continueSession.session_id}/start`,
      { mode: 'cat' },
      { preserveScroll: true },
    );
  };

  return (
    <DashboardLayout>
      <Head title={focus ? `${focus.title} — Dashboard` : 'Dashboard'} />

      <div className="mx-auto max-w-3xl">
        {focus ? (
          <>
            <header className="border-b border-slate-800 pb-5">
              <p className="text-xs font-medium uppercase tracking-wider text-slate-500">
                {focus.region}
              </p>
              <h1 className="mt-1 text-2xl font-bold tracking-normal text-white sm:text-3xl">
                {focus.title}
              </h1>
            </header>

            {continueSession ? (
              <ResumeCard session={continueSession} onResume={resume} />
            ) : (
              <NextUp focus={focus} />
            )}

            <ProgressStrip focus={focus} />

            {focus.cohort?.length > 0 && (
              <section className="mt-10">
                <div className="flex items-baseline justify-between gap-4">
                  <h2 className="text-sm font-semibold text-white">Related certifications</h2>
                  <Link
                    href="/passimark/focus"
                    className="text-xs text-slate-400 underline underline-offset-2 hover:text-slate-200"
                  >
                    Change focus
                  </Link>
                </div>

                <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                  {focus.cohort.map((item) => (
                    <li key={item.cert_key}>
                      <Link
                        href={item.url}
                        className="group flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-3 transition hover:border-slate-600 hover:bg-slate-800/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60"
                      >
                        <span className="min-w-0">
                          <span className="block truncate text-sm font-medium text-slate-100">
                            {item.title}
                          </span>
                          <span className="mt-0.5 block text-xs text-slate-500">
                            {item.has_progress
                              ? `${item.sessions_done} of ${item.sessions_total} sessions`
                              : item.region}
                          </span>
                        </span>
                        <ArrowRight
                          className="h-4 w-4 shrink-0 text-slate-600 transition group-hover:text-brand-400"
                          aria-hidden="true"
                        />
                      </Link>
                    </li>
                  ))}
                </ul>
              </section>
            )}

            <CatalogSearch total={catalogTotal} />
          </>
        ) : (
          <NoFocus catalogTotal={catalogTotal} />
        )}

        <BrowseCatalog
          sections={sections}
          open={browseOpen}
          onToggle={() => setBrowseOpen((value) => !value)}
          activeRegion={region}
        />
      </div>
    </DashboardLayout>
  );
}

function NextUp({ focus }) {
  const next = focus.next_session;

  if (!next) {
    return (
      <div className="mt-6 rounded-xl border border-brand-500/30 bg-brand-500/5 px-5 py-4">
        <p className="text-sm text-slate-300">
          Every session in this track is complete. Your certificate is issued.
        </p>
      </div>
    );
  }

  return (
    <section className="mt-6">
      <h2 className="text-xs font-medium uppercase tracking-wider text-slate-500">Next up</h2>

      <div
        className={
          next.locked
            ? 'mt-2 flex flex-col gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-5 sm:flex-row sm:items-center sm:justify-between'
            : 'mt-2 flex flex-col gap-3 rounded-xl border border-brand-500/30 bg-brand-500/5 p-5 sm:flex-row sm:items-center sm:justify-between'
        }
      >
        <div className="min-w-0">
          <p className="flex items-center gap-1.5 text-sm font-semibold text-white">
            {next.locked ? (
              <Lock className="h-4 w-4 shrink-0 text-amber-400" aria-hidden="true" />
            ) : (
              <Play className="h-4 w-4 shrink-0 text-brand-400" aria-hidden="true" />
            )}
            <span className="truncate">
              {next.number ? `Session ${next.number} · ` : ''}
              {next.title}
            </span>
          </p>
          <p className="mt-1 text-xs text-slate-400">
            {next.locked
              ? 'Locked — pass the session before this one to unlock it.'
              : `${next.question_count} questions · adaptive`}
          </p>
        </div>

        <Link
          href={next.url}
          className={
            next.locked
              ? 'shrink-0 rounded-lg border border-slate-700 px-4 py-2 text-center text-sm font-medium text-slate-300 transition hover:bg-slate-800'
              : 'shrink-0 rounded-lg bg-brand-500 px-4 py-2 text-center text-sm font-semibold text-slate-950 transition hover:bg-brand-400'
          }
        >
          {next.locked ? 'View track' : 'Start session'}
        </Link>
      </div>
    </section>
  );
}

function ResumeCard({ session, onResume }) {
  return (
    <section className="mt-6">
      <h2 className="text-xs font-medium uppercase tracking-wider text-slate-500">
        Continue where you left off
      </h2>

      <div className="mt-2 flex flex-col gap-4 rounded-xl border border-brand-500/30 bg-brand-500/5 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-white">
            {session.number ? `Session ${session.number} · ` : ''}
            {session.title}
          </p>
          <p className="mt-1 truncate text-xs text-slate-400">
            {session.track_title}
            {session.score !== null && session.score !== undefined
              ? ` · last score ${Math.round(session.score)}%`
              : ''}
          </p>
        </div>

        <div className="flex shrink-0 gap-2">
          <Link
            href={session.url}
            className="rounded-lg border border-slate-700 px-4 py-2 text-center text-sm font-medium text-slate-200 transition hover:bg-slate-800"
          >
            View track
          </Link>
          <button
            type="button"
            onClick={onResume}
            className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-brand-400"
          >
            Resume
          </button>
        </div>
      </div>
    </section>
  );
}

function ProgressStrip({ focus }) {
  const done = focus.sessions_done ?? 0;
  const total = focus.sessions_total ?? 0;
  const percent = total > 0 ? Math.round((done / total) * 100) : 0;

  return (
    <dl className="mt-6 grid grid-cols-3 gap-4 border-y border-slate-800 py-4">
      <div>
        <dt className="text-xs text-slate-500">Sessions</dt>
        <dd className="mt-1 text-lg font-semibold text-white">
          <span className="tabular-nums">
            {done}/{total}
          </span>
        </dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">Complete</dt>
        <dd className="mt-1 text-lg font-semibold text-white">
          <span className="tabular-nums">{percent}%</span>
        </dd>
      </div>
      <div>
        <dt className="text-xs text-slate-500">Ability θ</dt>
        <dd className="mt-1 flex items-center gap-1 text-lg font-semibold text-white">
          <TrendingUp className="h-4 w-4 text-slate-500" aria-hidden="true" />
          <span className="tabular-nums">
            {focus.theta !== null && focus.theta !== undefined
              ? focus.theta.toFixed(2)
              : '—'}
          </span>
        </dd>
      </div>
    </dl>
  );
}

/**
 * Universal search across every active certification. This is the only route to
 * the rest of the catalog from the default view, which is what lets the rest of
 * the dashboard stay scoped to one interest.
 */
function CatalogSearch({ total }) {
  const [term, setTerm] = useState('');
  const [results, setResults] = useState(null);
  const [busy, setBusy] = useState(false);
  const requestId = useRef(0);

  useEffect(() => {
    const needle = term.trim();

    if (needle.length < 2) {
      setResults(null);
      return undefined;
    }

    const id = ++requestId.current;
    setBusy(true);

    const timer = setTimeout(() => {
      fetch(`/catalog/search?q=${encodeURIComponent(needle)}`, {
        headers: { Accept: 'application/json' },
      })
        .then((response) => response.json())
        .then((payload) => {
          // Ignore responses that arrive after a newer keystroke.
          if (id === requestId.current) setResults(payload);
        })
        .catch(() => {
          if (id === requestId.current) setResults({ data: [], total: 0 });
        })
        .finally(() => {
          if (id === requestId.current) setBusy(false);
        });
    }, 250);

    return () => clearTimeout(timer);
  }, [term]);

  return (
    <section className="mt-10">
      <h2 className="text-sm font-semibold text-white">Explore other certifications</h2>
      <p className="mt-1 text-xs text-slate-500">
        Search all {total} certifications across 9 regions. Switching focus keeps this dashboard
        scoped to one track.
      </p>

      <div className="relative mt-3">
        <Search
          className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
          aria-hidden="true"
        />
        <input
          type="search"
          value={term}
          onChange={(event) => setTerm(event.target.value)}
          placeholder="Search certifications..."
          aria-label="Search all certifications"
          className="w-full rounded-lg border border-slate-700 bg-slate-900 py-2.5 pl-9 pr-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand-500/50 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
        />
      </div>

      {busy && <p className="mt-3 text-xs text-slate-500">Searching…</p>}

      {!busy && results && (
        <div className="mt-3">
          <p className="text-xs text-slate-500" role="status">
            {results.total} {results.total === 1 ? 'match' : 'matches'}
          </p>

          {results.data.length === 0 ? (
            <p className="mt-2 rounded-lg border border-slate-800 bg-slate-900/60 px-4 py-6 text-center text-sm text-slate-500">
              Nothing matches &quot;{results.query}&quot;. Try a broader term.
            </p>
          ) : (
            <ul className="mt-2 divide-y divide-slate-800 rounded-lg border border-slate-800">
              {results.data.slice(0, 8).map((item) => (
                <li key={item.cert_key}>
                  <Link
                    href={item.bundle_url}
                    className="flex items-center justify-between gap-3 px-4 py-2.5 transition hover:bg-slate-800/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500/60"
                  >
                    <span className="min-w-0">
                      <span className="block truncate text-sm text-slate-100">{item.title}</span>
                      <span className="block truncate font-mono text-[11px] text-slate-500">
                        {item.cert_key} · {item.region}
                      </span>
                    </span>
                    <ArrowRight className="h-4 w-4 shrink-0 text-slate-600" aria-hidden="true" />
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </section>
  );
}

function NoFocus({ catalogTotal }) {
  return (
    <div className="py-12 text-center">
      <Target className="mx-auto h-8 w-8 text-slate-600" aria-hidden="true" />
      <h1 className="mt-4 text-xl font-semibold text-white">Pick what you&apos;re working toward</h1>
      <p className="mx-auto mt-2 max-w-sm text-sm text-slate-400">
        Choose one certification and this dashboard rebuilds around it — next session, progress,
        and what&apos;s nearby. Everything else stays a search away.
      </p>

      <Link
        href="/passimark/focus"
        className="mt-6 inline-flex rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-brand-400"
      >
        Choose your focus
      </Link>

      <p className="mt-6 text-xs text-slate-500">
        Or search all {catalogTotal} certifications below.
      </p>
    </div>
  );
}

/**
 * The full region-grouped catalog. Kept behind a disclosure: it is a real
 * destination for someone who wants the whole catalog, and a wall of 205 tiles
 * for someone who came here to work on one track.
 */
function BrowseCatalog({ sections, open, onToggle, activeRegion }) {
  return (
    <section className="mt-12 border-t border-slate-800 pt-6">
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60"
      >
        <span>
          <span className="block text-sm font-semibold text-white">Browse the full catalog</span>
          <span className="mt-0.5 block text-xs text-slate-500">
            All certifications by region
            {activeRegion ? ` · filtered to ${activeRegion}` : ''}
          </span>
        </span>
        <span className="shrink-0 text-xs font-medium text-slate-400">
          {open ? 'Hide' : 'Show'}
        </span>
      </button>

      {open &&
        (sections.length === 0 ? (
          <p className="mt-4 rounded-lg border border-slate-800 bg-slate-900/60 px-4 py-6 text-center text-sm text-slate-500">
            No certification tracks are available yet.
          </p>
        ) : (
          <div className="mt-6 space-y-8">
            {sections.map((section) => (
              <div key={section.region}>
                <div className="flex items-baseline justify-between gap-4">
                  <h3 className="text-sm font-semibold text-white">{section.region}</h3>
                  <span className="text-xs text-slate-500">
                    {section.categories.length} certifications
                  </span>
                </div>

                <ul className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                  {section.categories.map((category) => (
                    <li key={category.cert_key}>
                      <Link
                        href={`/certs/${category.cert_key}`}
                        className="group flex items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-900/60 px-3 py-2.5 transition hover:border-slate-600 hover:bg-slate-800/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60"
                      >
                        <span className="min-w-0">
                          <span className="block truncate text-sm text-slate-200">
                            {category.title}
                          </span>
                          <span className="mt-0.5 block text-xs tabular-nums text-slate-500">
                            {category.sessions_done}/{category.sessions_total}
                          </span>
                        </span>
                        <ArrowRight
                          className="h-3.5 w-3.5 shrink-0 text-slate-600 transition group-hover:text-brand-400"
                          aria-hidden="true"
                        />
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        ))}
    </section>
  );
}
