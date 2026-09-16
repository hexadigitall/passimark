import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowUpRight, BookOpen, Check, Database, ShieldCheck, Timer, X } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';

const tileValue = (tile) => {
  if (tile.key === 'average_score') {
    const n = Number(tile.value);
    return Number.isNaN(n) ? '—' : `${Number.isInteger(n) ? n : n.toFixed(1)}%`;
  }
  return tile.value ?? 0;
};

const deltaTone = (text) => {
  if (!text) return 'text-slate-500';
  if (text.includes('▲')) return 'text-emerald-400';
  if (text.includes('▼')) return 'text-red-400';
  return 'text-slate-500';
};

export default function AdminDashboard({ pending = [], events = [], needsAttention = {}, tiles = [] }) {
  const [processingId, setProcessingId] = useState(null);

  const review = async (id, action) => {
    const note = action === 'reject' ? window.prompt('Reason for returning this submission:') : window.prompt('Optional approval note:');
    if (action === 'reject' && !note) return;
    setProcessingId(id);
    try {
      const xsrf = decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) || [])[1] || '');
      const res = await fetch(`/admin/passimark/progress/${id}/${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
        credentials: 'same-origin',
        body: JSON.stringify({ note: note || '' }),
      });
      const data = await res.json().catch(() => ({}));
      window.alert(data.message || (res.ok ? 'Done.' : `Request failed (${res.status}).`));
    } catch {
      window.alert('Network error — could not reach the server.');
    } finally {
      setProcessingId(null);
      router.reload({ only: ['pending', 'events', 'tiles'] });
    }
  };

  const attention = [
    needsAttention.contentless_sessions ? `${needsAttention.contentless_sessions} session${needsAttention.contentless_sessions === 1 ? '' : 's'} with exams but no questions` : null,
    needsAttention.empty_tracks ? `${needsAttention.empty_tracks} empty track${needsAttention.empty_tracks === 1 ? '' : 's'}` : null,
    needsAttention.untagged_questions ? `${needsAttention.untagged_questions} untagged question${needsAttention.untagged_questions === 1 ? '' : 's'}` : null,
  ].filter(Boolean);

  return (
    <DashboardLayout>
      <Head title="Admin Dashboard" />
      <section className="mx-auto max-w-7xl">
        <div className="flex flex-col gap-3 border-b border-slate-800 pb-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Operations</p>
            <h2 className="mt-1 text-3xl font-bold tracking-normal text-white">Learner operations</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
              Review progression decisions, watch content health, and jump into the content tools.
            </p>
          </div>
          <div className="flex shrink-0 gap-2">
            <a href="/admin/passimark" className="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400">
              <Database className="h-4 w-4" /> Control Center
            </a>
            <a href="/admin/import" className="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-700">
              <BookOpen className="h-4 w-4" /> Import questions
            </a>
          </div>
        </div>

        {attention.length > 0 && (
          <div className="mt-6 flex items-start gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
            <div className="text-sm text-amber-100/90">
              <p className="font-semibold text-amber-200">Content needs attention</p>
              <p className="mt-1">{attention.join(' · ')}</p>
            </div>
          </div>
        )}

        <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {tiles.map((tile) => (
            <Link
              key={tile.key}
              href={tile.href}
              className="group rounded-xl border border-slate-700 bg-slate-900 p-4 transition hover:border-emerald-500/40 hover:bg-slate-800/60"
            >
              <div className="flex items-center justify-between gap-2">
                <p className="text-sm text-slate-400">{tile.label}</p>
                <ArrowUpRight className="h-4 w-4 text-slate-600 transition group-hover:text-emerald-400" />
              </div>
              <p className="mt-2 text-2xl font-semibold text-white">{tileValue(tile)}</p>
              <p className="mt-1 truncate text-xs text-slate-500" title={tile.sub}>{tile.sub}</p>
              {tile.deltaText && <p className={`mt-1 truncate text-xs font-medium ${deltaTone(tile.deltaText)}`}>{tile.deltaText}</p>}
            </Link>
          ))}
        </div>

        <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_380px]">
          <section className="rounded-2xl border border-slate-700 bg-slate-900 p-6">
            <div className="flex items-center justify-between">
              <h3 className="text-xl font-semibold text-white">Pending approvals</h3>
              <span className="text-sm text-slate-500">{pending.length}</span>
            </div>
            {pending.length === 0 && <p className="mt-4 rounded-xl border border-dashed border-slate-700 p-6 text-sm text-slate-400">No learner submissions need review.</p>}
            <div className="mt-4 space-y-3">
              {pending.map((item) => (
                <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950 p-4">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-white">{item.user?.name}</p>
                    <p className="mt-0.5 truncate text-sm text-slate-400">{item.session?.title} · Score {item.score}%</p>
                  </div>
                  <div className="flex shrink-0 gap-2">
                    <button type="button" disabled={processingId === item.id} onClick={() => review(item.id, 'approve')} className="inline-flex items-center gap-1 rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:opacity-50">
                      <Check className="h-4 w-4" /> Approve
                    </button>
                    <button type="button" disabled={processingId === item.id} onClick={() => review(item.id, 'reject')} className="inline-flex items-center gap-1 rounded-lg bg-red-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-400 disabled:opacity-50">
                      <X className="h-4 w-4" /> Reject
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <aside className="space-y-6">
            <section className="rounded-2xl border border-slate-700 bg-slate-900 p-6">
              <h3 className="text-lg font-semibold text-white">Recent decisions</h3>
              <div className="mt-4 space-y-3">
                {events.length === 0 && <p className="text-sm text-slate-500">No decisions recorded yet.</p>}
                {events.map((event) => (
                  <div key={event.id} className="border-b border-slate-800 pb-3 text-sm">
                    <p className="capitalize text-white">{event.action}</p>
                    <p className="mt-1 text-slate-400">{event.progress?.user?.name} · {event.progress?.session?.title}</p>
                    {event.note && <p className="mt-0.5 text-xs italic text-slate-500">{event.note}</p>}
                  </div>
                ))}
              </div>
            </section>

            <section className="rounded-2xl border border-slate-700 bg-slate-900 p-6">
              <div className="flex items-center gap-2 text-emerald-300">
                <ShieldCheck className="h-4 w-4" />
                <h3 className="text-lg font-semibold text-white">Staff view</h3>
              </div>
              <p className="mt-2 flex items-center gap-1.5 text-sm text-slate-400"><Timer className="h-4 w-4" /> This dashboard replaces the learner mastery path for admins and instructors.</p>
            </section>
          </aside>
        </div>
      </section>
    </DashboardLayout>
  );
}