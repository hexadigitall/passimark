import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';

const example = JSON.stringify([{ content: 'Which control protects confidentiality?', domain: 'Security', options: [{ key: 'A', text: 'Encryption', is_correct: true }, { key: 'B', text: 'CCTV', is_correct: false }] }], null, 2);

export default function ContentImport({ sessions = [] }) {
  const [sessionId, setSessionId] = useState('');
  const [rawQuestions, setRawQuestions] = useState(example);
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');
  const [processing, setProcessing] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setStatus('');
    let questions;
    try { questions = JSON.parse(rawQuestions); } catch { setError('Questions must be valid JSON.'); return; }
    if (!Array.isArray(questions) || !questions.length) { setError('Provide at least one question.'); return; }
    setProcessing(true);
    try {
      const response = await fetch('/admin/passimark/questions/import', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' }, body: JSON.stringify({ session_id: sessionId, questions }) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Import failed.');
      setStatus(`${data.imported} question${data.imported === 1 ? '' : 's'} imported successfully.`);
      setRawQuestions('');
    } catch (importError) { setError(importError.message); } finally { setProcessing(false); }
  };

  return <><Head title="Import Questions" /><main className="min-h-screen bg-slate-950 px-6 py-8 text-white"><section className="mx-auto max-w-4xl space-y-6"><Link href="/admin/passimark" className="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white"><ArrowLeft className="h-4 w-4" /> Back to control center</Link><header><p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Content tools</p><h1 className="mt-2 text-3xl font-bold">Bulk question import</h1><p className="mt-2 text-sm text-slate-400">Import a validated JSON question batch into one curriculum session.</p></header><form onSubmit={submit} className="rounded-2xl border border-slate-700 bg-slate-900 p-6"><label className="grid gap-2 text-sm text-slate-300">Target session<select required value={sessionId} onChange={(event) => setSessionId(event.target.value)} className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 text-white"><option value="">Choose a session</option>{sessions.map((session) => <option key={session.id} value={session.id}>{session.number}. {session.title}</option>)}</select></label><label className="mt-5 grid gap-2 text-sm text-slate-300">Questions JSON<textarea required value={rawQuestions} onChange={(event) => setRawQuestions(event.target.value)} className="min-h-96 rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 font-mono text-sm text-white" /></label>{error && <p className="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-200">{error}</p>}{status && <p className="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-200">{status}</p>}<button disabled={processing} className="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 disabled:opacity-50"><Upload className="h-4 w-4" />{processing ? 'Importing...' : 'Import questions'}</button></form></section></main></>;
}