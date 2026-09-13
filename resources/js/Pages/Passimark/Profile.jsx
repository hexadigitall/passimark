import { Head } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';

export default function Profile({ user }) {
  return (
    <DashboardLayout>
      <Head title="Profile" />

      <section className="mx-auto max-w-5xl space-y-6">
        <div className="rounded-2xl border border-slate-700 bg-slate-800 p-6">
          <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-400">Account</p>
              <h2 className="mt-2 text-3xl font-bold text-white">{user?.name}</h2>
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
                <p className="mt-2 text-2xl font-semibold text-white">Phase 1</p>
              </div>
              <div className="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p className="text-sm text-slate-400">Next milestone</p>
                <p className="mt-2 text-2xl font-semibold text-white">Session 2</p>
              </div>
            </div>
            <p className="mt-5 text-sm leading-6 text-slate-400">
              Your account is aligned with the adaptive certification path. Progress updates and session approvals will continue to appear here as your learning record grows.
            </p>
          </div>
        </div>
      </section>
    </DashboardLayout>
  );
}