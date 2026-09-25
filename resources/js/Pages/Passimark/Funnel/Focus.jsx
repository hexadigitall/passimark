import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';

export default function Focus({ funnel, options = [], selected = null }) {
  const ladder = funnel?.ladder ?? ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];
  const step = funnel?.step ?? 'focus';
  const [certKey, setCertKey] = useState(selected ?? '');
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
          <h1 className="text-3xl font-bold text-white">Choose your focus</h1>
          <p className="mt-3 text-slate-400">
            Pick the certification you want front and center right now. It stays re-editable,
            so this is never a permanent decision.
          </p>

          <ol className="mt-6 grid grid-cols-7 gap-1.5" aria-label="Track ladder">
            {ladder.map((rung, i) => (
              <li
                key={rung}
                className={
                  rung === step
                    ? 'flex flex-col items-center gap-1 rounded-lg border border-emerald-500/50 bg-emerald-500/10 p-2'
                    : 'flex flex-col items-center gap-1 rounded-lg border border-slate-700/50 p-2'
                }
              >
                <span
                  className={
                    rung === step
                      ? 'flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-slate-900'
                      : 'flex h-6 w-6 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-slate-300'
                  }
                >
                  {i + 1}
                </span>
                <span
                  className={
                    rung === step
                      ? 'text-center text-[10px] font-semibold uppercase tracking-wide text-emerald-300'
                      : 'text-center text-[10px] font-medium text-slate-400'
                  }
                >
                  {rung}
                </span>
              </li>
            ))}
          </ol>

          <form className="mt-6" onSubmit={submit}>
            <label htmlFor="cert_key" className="block text-sm font-medium text-slate-300">
              Your focus
            </label>
            <select
              id="cert_key"
              name="cert_key"
              value={certKey}
              onChange={(event) => setCertKey(event.target.value)}
              className="mt-2 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-emerald-500/50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
            >
              <option value="">Select a certification...</option>
              {regions.map(([region, items]) => (
                <optgroup key={region} label={region}>
                  {items.map((item) => (
                    <option key={item.cert_key} value={item.cert_key}>
                      {item.title}
                    </option>
                  ))}
                </optgroup>
              ))}
            </select>

            {errors.cert_key && (
              <p className="mt-2 text-sm text-rose-400">{errors.cert_key}</p>
            )}

            {chosen && (
              <p className="mt-3 text-xs text-slate-400">
                Focused on <span className="font-semibold text-emerald-300">{chosen.title}</span>
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
