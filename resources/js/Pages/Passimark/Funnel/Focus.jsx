import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import FunnelProgress from '../../../Components/FunnelProgress';

export default function Focus({ funnel, options = [], selected = null }) {
  const [certKey, setCertKey] = useState(selected ?? '');
  const [query, setQuery] = useState('');
  const [regionFilter, setRegionFilter] = useState('');
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});

  const regions = useMemo(() => {
    const grouped = new Map();

    for (const option of options) {
      const bucket = grouped.get(option.region) ?? [];
      bucket.push(option);
      grouped.set(option.region, bucket);
    }

    return [...grouped.entries()].sort(([a], [b]) => a.localeCompare(b));
  }, [options]);

  // The picker must stay usable with 205 options, so it searches and filters rather
  // than asking the learner to scroll a native dropdown.
  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();

    return regions
      .filter(([region]) => !regionFilter || region === regionFilter)
      .flatMap(([, items]) => items)
      .filter((item) =>
        !needle
          ? true
          : `${item.title} ${item.cert_key} ${item.region}`.toLowerCase().includes(needle),
      );
  }, [regions, query, regionFilter]);

  const chosen = options.find((option) => option.cert_key === certKey);

  const submit = (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});

    router.post(
      funnel?.submit ?? '/passimark/focus',
      { cert_key: certKey },
      {
        onFinish: () => setSaving(false),
        onError: (bag) => setErrors(bag),
      },
    );
  };

  return (
    <>
      <Head title="Choose your focus" />
      <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-emerald-500 opacity-20 blur-3xl" />
          <div className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-teal-500 opacity-20 blur-3xl" />
        </div>

        <div className="relative w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur">
          <FunnelProgress ladder={funnel?.ladder} step={funnel?.step ?? 'focus'} className="mb-6" />

          <h1 className="text-2xl font-bold text-white sm:text-3xl">Choose your focus</h1>
          <p className="mt-2 text-sm text-slate-400 sm:text-base">
            Pick the certification you want front and center. Your dashboard is built around this
            one, and you can change it whenever you like.
          </p>

          <form className="mt-6" onSubmit={submit}>
            <label htmlFor="cert_search" className="block text-sm font-medium text-slate-300">
              Search {options.length} certifications
            </label>
            <div className="relative mt-2">
              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
                aria-hidden="true"
              />
              <input
                id="cert_search"
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Try &quot;security&quot;, &quot;aws&quot;, &quot;finance&quot;..."
                autoComplete="off"
                className="w-full rounded-lg border border-slate-700 bg-slate-900 py-2.5 pl-9 pr-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500/50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
              />
            </div>

            <div className="mt-2 flex flex-wrap gap-1.5">
              <button
                type="button"
                onClick={() => setRegionFilter('')}
                aria-pressed={regionFilter === ''}
                className={
                  regionFilter === ''
                    ? 'rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-300 ring-1 ring-emerald-500/40'
                    : 'rounded-full bg-slate-800 px-3 py-1 text-xs font-medium text-slate-400 ring-1 ring-slate-700 hover:text-slate-200'
                }
              >
                All
              </button>
              {regions.map(([region]) => (
                <button
                  key={region}
                  type="button"
                  onClick={() => setRegionFilter(region === regionFilter ? '' : region)}
                  aria-pressed={regionFilter === region}
                  className={
                    regionFilter === region
                      ? 'rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-300 ring-1 ring-emerald-500/40'
                      : 'rounded-full bg-slate-800 px-3 py-1 text-xs font-medium text-slate-400 ring-1 ring-slate-700 hover:text-slate-200'
                  }
                >
                  {region}
                </button>
              ))}
            </div>

            <fieldset className="mt-3">
              <legend className="sr-only">Choose a certification</legend>
              <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-slate-700 bg-slate-900/60 p-1.5">
                {visible.length === 0 ? (
                  <p className="px-3 py-6 text-center text-sm text-slate-500">
                    Nothing matches &quot;{query}&quot;. Try a broader term.
                  </p>
                ) : (
                  visible.map((item) => {
                    const active = item.cert_key === certKey;

                    return (
                      <label
                        key={item.cert_key}
                        className={
                          active
                            ? 'flex cursor-pointer items-center gap-3 rounded-md border border-emerald-500/50 bg-emerald-500/10 px-3 py-2.5'
                            : 'flex cursor-pointer items-center gap-3 rounded-md border border-transparent px-3 py-2.5 hover:bg-slate-800/70'
                        }
                      >
                        <input
                          type="radio"
                          name="cert_key"
                          value={item.cert_key}
                          checked={active}
                          onChange={() => setCertKey(item.cert_key)}
                          className="sr-only"
                        />
                        <span
                          aria-hidden="true"
                          className={
                            active
                              ? 'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-emerald-400'
                              : 'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-slate-600'
                          }
                        >
                          {active && <span className="h-2 w-2 rounded-full bg-emerald-400" />}
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-sm font-medium text-slate-100">
                            {item.title}
                          </span>
                          <span className="block truncate font-mono text-[11px] text-slate-500">
                            {item.cert_key} · {item.region}
                          </span>
                        </span>
                      </label>
                    );
                  })
                )}
              </div>
            </fieldset>

            {errors.cert_key && (
              <p className="mt-2 text-sm text-rose-400">{errors.cert_key}</p>
            )}

            {chosen && (
              <p className="mt-3 text-xs text-slate-400">
                Your dashboard will be built around{' '}
                <span className="font-semibold text-emerald-300">{chosen.title}</span>.
              </p>
            )}

            <button
              type="submit"
              disabled={!certKey || saving}
              className="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {saving ? 'Saving...' : 'Set focus'}
            </button>
          </form>

          <p className="mt-4 text-center text-xs text-slate-500">
            {options.length} certifications available
          </p>
        </div>
      </div>
    </>
  );
}
