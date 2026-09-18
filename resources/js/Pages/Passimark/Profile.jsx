import { Head, Link } from '@inertiajs/react';
import { Award } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';

export default function Profile({ user, summary = {}, certificates = [] }) {
  const hasProgress = (summary.sessions_enrolled || 0) > 0;
  const completion = summary.sessions_total ? Math.round(((summary.sessions_completed || 0) / summary.sessions_total) * 100) : 0;

  return (
    <DashboardLayout>
      <Head title="Profile" />

      <section className="mx-auto max-w-5xl space-y-6">
        <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
          <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Account</p>
              <h2 className="mt-2 text-3xl font-bold text-white">{user?.name}</h2>
              {summary.current_track && <p className="mt-1 text-sm text-slate-400">{summary.current_track}</p>}
            </div>
            <div className="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-300">
              {user?.role || 'student'}
            </div>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
            <h3 className="text-lg font-semibold text-white">Profile details</h3>
            <dl className="mt-5 space-y-4 text-sm text-slate-300">
              <div className="flex items-center justify-between border-b border-slate-700 pb-3">
                <dt className="text-slate-400">Full name</dt>
                <dd className="font-medium text-white">{user?.name}</dd>
              </div>
              <div className="flex items-center justify-between border-b border-slate-700 pb-3">
                <dt className="text-slate-400">Email address</dt>
                <dd className="font-medium text-white">{user?.email}</dd>
              </div>
              <div className="flex items-center justify-between border-b border-slate-700 pb-3">
                <dt className="text-slate-400">Role</dt>
                <dd className="font-medium text-white capitalize">{user?.role || 'student'}</dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-slate-400">Status</dt>
                <dd className="font-medium text-emerald-300">Active</dd>
              </div>
            </dl>
          </div>

          <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
            <h3 className="text-lg font-semibold text-white">Account summary</h3>
            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-sm text-slate-400">Current phase</p>
                <p className="mt-2 text-2xl font-semibold text-white">{summary.current_phase ? `Phase ${summary.current_phase}` : '—'}</p>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-sm text-slate-400">Ability (θ)</p>
                <p className="mt-2 text-2xl font-semibold text-white">{(summary.ability_theta ?? 0).toFixed(2)}</p>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-sm text-slate-400">Sessions completed</p>
                <p className="mt-2 text-2xl font-semibold text-white">{summary.sessions_completed || 0}<span className="text-base font-normal text-slate-500">/{summary.sessions_total || 0}</span></p>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-sm text-slate-400">Average score</p>
                <p className="mt-2 text-2xl font-semibold text-white">{summary.average_score != null ? `${summary.average_score}%` : '—'}</p>
              </div>
            </div>

            <div className="mt-5">
              <div className="flex items-center justify-between text-sm">
                <span className="text-slate-400">Curriculum progress</span>
                <span className="font-medium text-white">{completion}%</span>
              </div>
              <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-900">
                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${completion}%` }} />
              </div>
            </div>

            <p className="mt-5 text-sm leading-6 text-slate-400">
              {hasProgress
                ? `Next milestone: ${summary.next_milestone || 'all sessions completed'}. Progress updates and session approvals will continue to appear here as your learning record grows.`
                : 'You are not enrolled in a certification track yet. Enrolment unlocks the first lesson of each available track.'}
            </p>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
          <h3 className="flex items-center gap-2 text-lg font-semibold text-white">
            <Award className="h-5 w-5 text-emerald-400" /> Certificates
          </h3>
          {certificates.length === 0 ? (
            <p className="mt-3 text-sm text-slate-400">No certificates yet — pass a track's final assessment to earn a verifiable credential.</p>
          ) : (
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {certificates.map((certificate) => (
                <Link
                  key={certificate.credential_id}
                  href={certificate.url}
                  className="rounded-xl border border-slate-700 bg-slate-900 p-4 transition hover:border-emerald-500/50"
                >
                  <p className="text-sm font-semibold text-white">{certificate.certification}</p>
                  {certificate.variant_label && <p className="mt-0.5 text-xs text-slate-400">{certificate.variant_label}</p>}
                  <p className="mt-3 font-mono text-xs text-emerald-300">{certificate.credential_id}</p>
                </Link>
              ))}
            </div>
          )}
        </div>
      </section>
    </DashboardLayout>
  );
}