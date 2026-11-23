import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, FileArchive, Upload } from 'lucide-react';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export default function PackageImport({ imports = [] }) {
  const [file, setFile] = useState(null);
  const [certSlug, setCertSlug] = useState('');
  const [onDuplicate, setOnDuplicate] = useState('skip');
  const [mode, setMode] = useState('create');
  const [plan, setPlan] = useState(null);
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');
  const [busy, setBusy] = useState('');

  const buildForm = (withDuplicate = false) => {
    const form = new FormData();
    form.append('package', file);
    if (certSlug) form.append('cert_slug', certSlug);
    if (withDuplicate) {
      form.append('on_duplicate', onDuplicate);
      form.append('mode', mode);
    }
    return form;
  };

  const preview = async () => {
    setError('');
    setStatus('');
    setPlan(null);
    if (!file) {
      setError('Choose a .psmk package first.');
      return;
    }
    setBusy('preview');
    try {
      const res = await fetch('/admin/packages/preview', {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: buildForm(),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Preview failed.');
      setPlan(data);
    } catch (previewError) {
      setError(previewError.message);
    } finally {
      setBusy('');
    }
  };

  const runImport = async () => {
    setError('');
    setStatus('');
    if (!file) {
      setError('Choose a .psmk package first.');
      return;
    }
    setBusy('import');
    try {
      const response = await fetch('/admin/packages/import', {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: buildForm(true),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Import failed.');
      setStatus(
        data.skipped
          ? 'This package was already imported — skipped (no duplicate content created).'
          : `Imported ${data.content_type} package: created ${data.created.sessions} session(s), ${data.created.exams} exam(s), ${data.created.questions} question(s)` +
            (data.updated
              ? `; updated ${data.updated.sessions} session(s), ${data.updated.exams} exam(s), ${data.updated.questions} question(s).`
              : '.')
      );
      setPlan(null);
    } catch (importError) {
      setError(importError.message);
    } finally {
      setBusy('');
    }
  };

  return (
    <>
      <Head title="Import Package" />
      <main className="min-h-screen bg-slate-950 px-4 py-6 text-white sm:px-6 sm:py-8">
        <section className="mx-auto max-w-4xl space-y-6">
          <Link href="/admin/passimark" className="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white">
            <ArrowLeft className="h-4 w-4" /> Back to control center
          </Link>
          <header>
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-brand-400">Content tools</p>
            <h1 className="mt-2 text-2xl font-bold sm:text-3xl">Portable package import</h1>
            <p className="mt-2 text-sm text-slate-400">
              Upload a <code className="text-slate-300">.psmk</code> package (or its <code className="text-slate-300">.psme</code>/<code className="text-slate-300">.psmm</code> aliases). Preview validates the archive and shows the plan before anything is written.
            </p>
          </header>

          <form
            onSubmit={(event) => {
              event.preventDefault();
              preview();
            }}
            className="rounded-2xl border border-slate-700 bg-slate-900 p-4 sm:p-6"
          >
            <label className="grid gap-2 text-sm text-slate-300">
              Package file
              <input
                type="file"
                accept=".psmk,.psme,.psmm"
                onChange={(event) => {
                  setFile(event.target.files?.[0] ?? null);
                  setPlan(null);
                  setStatus('');
                  setError('');
                }}
                className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 text-white file:mr-3 file:rounded-md file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-slate-200"
              />
            </label>

            <div className="mt-5 grid gap-4 sm:grid-cols-3">
              <label className="grid gap-2 text-sm text-slate-300">
                Cert slug override (optional)
                <input
                  type="text"
                  value={certSlug}
                  onChange={(event) => setCertSlug(event.target.value)}
                  placeholder="cissp"
                  className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 text-white"
                />
              </label>
              <label className="grid gap-2 text-sm text-slate-300">
                If already imported
                <select
                  value={onDuplicate}
                  onChange={(event) => setOnDuplicate(event.target.value)}
                  className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 text-white"
                >
                  <option value="skip">Skip (recommended)</option>
                  <option value="create">Create a duplicate copy</option>
                  <option value="fail">Reject the import</option>
                </select>
              </label>
              <label className="grid gap-2 text-sm text-slate-300">
                Import strategy
                <select
                  value={mode}
                  onChange={(event) => setMode(event.target.value)}
                  className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 text-white"
                >
                  <option value="create">Create new copies</option>
                  <option value="update">Update existing (by external_id)</option>
                </select>
              </label>
            </div>

            {error && (
              <div className="mt-4 flex items-start gap-2 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-200">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{error}</span>
              </div>
            )}
            {status && (
              <div className="mt-4 flex items-start gap-2 rounded-lg border border-brand-500/40 bg-brand-500/10 p-3 text-sm text-brand-200">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{status}</span>
              </div>
            )}

            <div className="mt-5 flex flex-wrap gap-3">
              <button
                type="submit"
                disabled={busy !== '' || !file}
                className="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-5 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50"
              >
                <FileArchive className="h-4 w-4" />
                {busy === 'preview' ? 'Previewing...' : 'Preview'}
              </button>
              <button
                type="button"
                onClick={runImport}
                disabled={busy !== '' || !plan || !plan.valid}
                className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-semibold text-slate-950 disabled:opacity-50"
              >
                <Upload className="h-4 w-4" />
                {busy === 'import' ? 'Importing...' : 'Import package'}
              </button>
            </div>

            {plan && (
              <div className="mt-5 rounded-xl border border-slate-700 bg-slate-950 p-4 text-sm">
                <p className={`font-semibold ${plan.valid ? 'text-brand-300' : 'text-red-300'}`}>
                  {plan.valid ? 'Package is valid and ready to import.' : 'Package failed validation.'}
                </p>
                <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-slate-300 sm:grid-cols-3">
                  <dt className="text-slate-500">Type</dt>
                  <dd>{plan.content_type ?? '—'}</dd>
                  <dt className="text-slate-500">Title</dt>
                  <dd className="col-span-2 truncate">{plan.title ?? '—'}</dd>
                  <dt className="text-slate-500">Target cert</dt>
                  <dd>{plan.cert_slug ?? '—'}</dd>
                  <dt className="text-slate-500">Producer</dt>
                  <dd>{plan.producer ?? '—'}</dd>
                  <dt className="text-slate-500">Sessions</dt>
                  <dd>{plan.counts.sessions}</dd>
                  <dt className="text-slate-500">Exams</dt>
                  <dd>{plan.counts.exams}</dd>
                  <dt className="text-slate-500">Questions</dt>
                  <dd>{plan.counts.questions}</dd>
                </dl>
                {plan.existing_matches &&
                  plan.existing_matches.sessions + plan.existing_matches.exams + plan.existing_matches.questions > 0 && (
                    <p className="mt-3 rounded-lg border border-sky-500/30 bg-sky-500/10 p-3 text-sky-200">
                      Update strategy will overwrite {plan.existing_matches.sessions} session(s), {plan.existing_matches.exams} exam(s) and{' '}
                      {plan.existing_matches.questions} question(s) matching existing external_id(s).
                    </p>
                  )}
                {plan.duplicate && (
                  <p className="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-amber-200">
                    This exact package (checksum {plan.checksum?.slice(0, 12)}) has already been imported {plan.existing_imports} time(s).
                  </p>
                )}
                {plan.errors?.length > 0 && (
                  <ul className="mt-3 list-inside list-disc space-y-1 text-red-200">
                    {plan.errors.map((message) => (
                      <li key={message}>{message}</li>
                    ))}
                  </ul>
                )}
              </div>
            )}
          </form>

          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-4 sm:p-6">
            <h2 className="text-lg font-semibold">Recent imports</h2>
            {imports.length === 0 ? (
              <p className="mt-2 text-sm text-slate-500">No packages imported yet.</p>
            ) : (
              <div className="mt-3 overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                      <th className="py-2 pr-4">File</th>
                      <th className="py-2 pr-4">Type</th>
                      <th className="py-2 pr-4">Status</th>
                      <th className="py-2">When</th>
                    </tr>
                  </thead>
                  <tbody className="text-slate-300">
                    {imports.map((row) => (
                      <tr key={row.id} className="border-t border-slate-800">
                        <td className="max-w-xs truncate py-2 pr-4">{row.original_filename}</td>
                        <td className="py-2 pr-4">{row.content_type ?? '—'}</td>
                        <td className="py-2 pr-4">{row.status}</td>
                        <td className="py-2 text-slate-500">{row.created_at?.replace('T', ' ').slice(0, 19)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </section>
      </main>
    </>
  );
}
